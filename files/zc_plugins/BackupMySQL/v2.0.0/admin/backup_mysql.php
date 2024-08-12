<?php

declare(strict_types=1);
/**
 * part of Backup MySQL plugin
 * @copyright Copyright 2024 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @author Dr.Byte
 * @version $Id: torvista 2024 Aug 12 $
 */

/** for phpStorm
 * @var queryFactoryResult $db
 * @var messageStack $messageStack
 */

// determine operating system to use specific quotes with password
$os_win = str_starts_with(strtoupper(PHP_OS), 'WIN');
if ($os_win) {
    define('OS_DELIM', '"');
} else {
    define('OS_DELIM', "'");
}

require('includes/application_top.php');

function checkMysqlPath($path): array
{
    $mysql_exe = 'unknown';
    $mysqldump_exe = 'unknown';
    if (file_exists($path . 'mysql')) {
        $mysql_exe = $path . 'mysql';
    }
    if (file_exists($path . 'mysql.exe')) {
        $mysql_exe = $path . 'mysql.exe';
    }
    if (file_exists($path . 'mysqldump')) {
        $mysqldump_exe = $path . 'mysqldump';
    }
    if (file_exists($path . 'mysqldump.exe')) {
        $mysqldump_exe = $path . 'mysqldump.exe';
    }
    return [$mysql_exe, $mysqldump_exe];
}

$dump_params = '';
$tables_to_export = !empty($_GET['tables']) ? str_replace(',', ' ', $_GET['tables']) : '';
$redirect = !empty($_GET['returnto']) ? $_GET['returnto'] : '';

$resultcodes = '';
$_POST['compress'] = (isset($_REQUEST['compress'])) ? $_REQUEST['compress'] : false;
$strA = '';
$strB = '';
$compress_override = (isset($_GET['comp']) && $_GET['comp'] > 0) || COMPRESS_OVERRIDE == 'true';

$debug = isset($_GET['debug']) && ($_GET['debug'] === 'ON' || (int)$_GET['debug'] === 1);

$skip_locks_requested = isset($_REQUEST['skiplocks']) && $_REQUEST['skiplocks'] == 'yes';
$ssl_on = str_starts_with(HTTP_SERVER, 'https');
$gzip_enabled = function_exists('ob_gzhandler') && ini_get('zlib.output_compression');
$zip_enabled = class_exists('ZipArchive');

// check if the backup directory exists
$dir_ok = false;
if (is_dir(DIR_FS_BACKUP)) {
    if (is_writable(DIR_FS_BACKUP)) {
        $dir_ok = true;
    } else {
        $messageStack->add(ERROR_BACKUP_DIRECTORY_NOT_WRITEABLE . ': ' . DIR_FS_BACKUP);
    }
} else {
    $messageStack->add(ERROR_BACKUP_DIRECTORY_DOES_NOT_EXIST . ': ' . DIR_FS_BACKUP);
}
if ($debug && $dir_ok) {
    $messageStack->add(sprintf(TEXT_BACKUP_DIRECTORY_OK, DIR_FS_BACKUP), 'success');
}
// check to see if open_basedir restrictions in effect -- if so, likely won't be able to use this tool.
$flag_basedir = false;
$open_basedir = ini_get('open_basedir');
if ($open_basedir !== '') {
    $basedir_check_array = explode(':', $open_basedir);
    foreach ($basedir_check_array as $basedir_check) {
        if (!strstr(DIR_FS_ADMIN, $basedir_check)) {
            $flag_basedir = true;
        }
    }
    if ($flag_basedir) {
        $messageStack->add(ERROR_CANT_BACKUP_IN_SAFE_MODE, 'error');
    }
}

// check to see if "exec()" is disabled in PHP -- if so, won't be able to use this tool.
$exec_disabled = false;
$shell_exec_disabled = false;
$php_disabled_functions = ini_get("disable_functions");
if (in_array('exec', explode(",", str_replace(' ', '', $php_disabled_functions)), true)) {
    $messageStack->add(ERROR_EXEC_DISABLED);
    $exec_disabled = true;
}
if (!$os_win && in_array('shell_exec', explode(",", str_replace(' ', '', $php_disabled_functions)), true)) {
    //$messageStack->add(ERROR_SHELL_EXEC_DISABLED, 'error');//shell_exec only used on Unix to find mysql: show error later
    $shell_exec_disabled = true;
}
if ($exec_disabled || ($shell_exec_disabled)) {
    $messageStack->add(ERROR_PHP_DISABLED_FUNCTIONS . $php_disabled_functions, 'warning');
}

// WHERE ARE THE MYSQL EXECUTABLES?
// The location will vary per server/installation.
$mysql_exe = 'unknown';
$mysqldump_exe = 'unknown';
$path_found = '';

// try the last successful path saved
if (defined('BACKUP_MYSQL_LOCATION')) {
    [$mysql_exe, $mysqldump_exe] = checkMysqlPath(BACKUP_MYSQL_LOCATION);
}
if ($mysql_exe !== 'unknown' && $mysqldump_exe !== 'unknown') {
    $path_found = BACKUP_MYSQL_LOCATION;
    if ($debug) {
        $messageStack->add(sprintf(TEXT_EXECUTABLES_FOUND_DB, $path_found), 'success');
    }
// or, if DB constant is not valid
} else {
// The following code attempts to locate the executables programmatically and by checking some common paths.
// But if this fails, you will have to define the locations manually in
// admin/includes/extra_datafiles/backup_mysql.php file
// where
// MYSQL_EXE and MYSQLDUMP_EXE: production server
// MYSQL_EXE_LOCAL and MYSQLDUMP_EXE_LOCAL: development server
// These could be overridden in the URL by specifying &tool=/path/to/foo/bar/plus/utilname, depending on server support

// Try and get some paths automatically
    $possiblePaths = [];
    $paths_auto = '';
//Windows
    if ($os_win) {
        $basedir_result = $db->Execute("SHOW VARIABLES LIKE 'basedir'");
        foreach ($basedir_result as $result) {
            $path = $result['Value'];
            //check the path
            if (preg_match('/^[A-Z]:/i', $path)) {//path has a drive letter
                /*if ($debug) {
                    $messageStack->add(__LINE__ . ': $path=' . $path, 'info');
                }*/
                $paths_auto = $path . 'bin/';
            } else {//path has no drive letter: portable installation Xampp?
                $possiblePaths = array_merge(range('A', 'Z'), range('a', 'z'));
                array_walk($possiblePaths, static function (&$value, $key, $path) {
                    $value .= $path . '/bin/';
                }, ':' . $path);//make an array of all the possible drives+path
            }
        }
    } elseif ($shell_exec_disabled) {
        $messageStack->add(ERROR_SHELL_EXEC_DISABLED, 'warning');
//Unix "which" command finds the executable.
    } else {
        $paths_auto = str_replace('mysql', '', trim(shell_exec('which mysql')));
    }
    if ($debug) {
        $messageStack->add(sprintf(TEXT_AUTO_DETECTED_PATH, PHP_OS, $paths_auto), 'info');
    }

    //is a path manually defined in /extra_datafiles?
    $path_file1 = '';
    $path_file2 = '';
    if (defined('LOCAL_MYSQL_EXE')) {
        $path_file1 = str_replace('mysql.exe', '', MYSQL_EXE) . '/';
        $path_file2 = str_replace('mysql', '', MYSQL_EXE) . '/';
    }
    if (!empty($path_file1)) {
        $possiblePaths[] = $path_file1;
    }
    if (!empty($path_file1)) {
        $possiblePaths[] = $path_file2;
    }

//some possible paths to search
    $pathsearch = array_merge($possiblePaths, [
        $paths_auto,
        '/usr/bin/',
        '/usr/local/bin/',
        '/usr/local/mysql/bin/',
        'c:/mysql/bin/',
        'd:/mysql/bin/',
        'e:/mysql/bin/',
        'c:/server/mysql/bin/',
        '\'c:/Program Files/MySQL/MySQL Server 5.0/bin/\'',
        '\'d:\\Program Files\\MySQL\\MySQL Server 5.0\\bin\\\''
    ]);

    $pathsearch = array_merge($pathsearch, explode(':', $open_basedir));

    foreach ($pathsearch as $path) {
        // convert backslashes to forward, double slashes to singles, remove single quotes
        $path = str_replace(['\\', '//', "'"], ['/', '/', ""], $path);
        $path = (!str_ends_with($path, '/') && !str_ends_with($path, '\\')) ? $path . '/' : $path; // add a '/' to the end if missing
        if ($debug) {
            $messageStack->add(TEXT_CHECK_PATH . $path, 'info');
        }

        [$mysql_exe, $mysqldump_exe] = checkMysqlPath($path);
        if ($mysql_exe !== 'unknown' && $mysqldump_exe !== 'unknown') {
            $path_found = $path;
            break; //exit when executables are found

        } else {
            if ($debug) {
                $messageStack->add(TEXT_EXECUTABLES_NOT_FOUND, 'info');
            }
        }
    }
    if (empty($path_found)) {
        $messageStack->add(TEXT_EXECUTABLES_NOT_FOUND, 'error');
    }
}

if (($shell_exec_disabled || $debug) && !empty($path_found)) {
    if ($debug) {
        $messageStack->add(sprintf(TEXT_EXECUTABLES_FOUND, 'mysql exe =' . $mysql_exe, 'mysqldump exe=' . $mysqldump_exe), 'success');
    }
    //store path in db
    $db->Execute(
        "INSERT INTO " . TABLE_CONFIGURATION . "
                        (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, date_added)
                        VALUES
                        ('Backup MySQL: mysql tools location', 'BACKUP_MYSQL_LOCATION', '" . $path_found . "', 'Backup MySQL: mysql executables location', 6, now())
                        ON DUPLICATE KEY UPDATE configuration_value ='" . $path_found . "'"
    );
}

$action = $_GET['action'] ?? '';

if (zen_not_null($action)) {
    switch ($action) {
        case 'forget':
            $db->Execute("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'BACKUP_MYSQL_LAST_RESTORE'");
            $messageStack->add_session(SUCCESS_LAST_RESTORE_CLEARED, 'success');
            zen_redirect(zen_href_link(FILENAME_BACKUP_MYSQL, ($debug ? 'debug=ON' : '')));
            break;

        case 'backupnow':
            //zen_set_time_limit(250);  // not needed?
            if (!empty($_POST['suffix'])) {
                $suffix = '_' . zen_output_string_protected($_POST['suffix']); //sanitise input
                $suffix = preg_replace('/\s+/', '_', $suffix, -1); //swap whitespace for an underscore
                $suffix = preg_replace('/[^-a-zA-Z0-9_]+/', '', $suffix); //strip to simple ascii only
            } else {
                $suffix = '';
            }

            $backup_file = 'db_' . DB_DATABASE . '-' . ($tables_to_export !== '' ? 'limited-' : '') . date('YmdHis') . $suffix . '.sql';
            /* this parameter needed for cron backup, but not here, although same db
            //echo "MySQL version=" . mysqli_get_client_version();die;
            $mysql_version = (int)(mysqli_get_client_version()/10000);
            $dump_params .= $mysql_version >= 8 ? ' --column-statistics=0 ' : ''; //default parameter in mysqldump 8 https://serverfault.com/questions/912162/mysqldump-throws-unknown-table-column-statistics-in-information-schema-1109
             */
            $dump_params .= ' --host=' . DB_SERVER;
            $dump_params .= ' --user=' . DB_SERVER_USERNAME;
            //$dump_params .= ' --password="' . DB_SERVER_PASSWORD . '"';//WIN DEFINITELY needs double quotes around the filename when shell metacharacters *%&$& etc. are in the password
            $dump_params .= ' --password=' . OS_DELIM . DB_SERVER_PASSWORD . OS_DELIM;//NIX DEFINITELY needs single quotes around the filename when shell metacharacters *%&$& etc. are in the password
            $dump_params .= ' --opt';   //"optimized" -- turns on all "fast" and optimized export methods
            $dump_params .= ' --complete-insert';  // undo optimization slightly and do "complete inserts"--lists all column names for benefit of restore of diff systems
            if ($skip_locks_requested) {
                $dump_params .= ' --skip-lock-tables --skip-add-locks';     //use this if your host prevents you from locking tables for backup
            }
//        $dump_params .= ' --skip-comments'; // mysqldump inserts '--' as comment delimiters, which is invalid on import (only for mysql v4.01+)
//        $dump_params .= ' --skip-quote-names';
//        $dump_params .= ' --force';  // ignore SQL errors if they occur
//        $dump_params .= ' --compatible=postgresql'; // other options are: ,mysql323, mysql40
            $dump_params .= ' --result-file=' . OS_DELIM . DIR_FS_BACKUP . $backup_file . OS_DELIM;//WIN DEFINITELY needs double quote around the filename
            //$dump_params .= ' --databases ' . DB_DATABASE;//this option will restore only to the same-named database
            $dump_params .= ' ' . DB_DATABASE;

            // if using the "--tables" parameter, this should be the last parameter, and tables should be space-delimited
            // fill $tables_to_export with list of tables, separated by spaces, if wanna just export certain tables
            $dump_params .= (($tables_to_export === '') ? '' : ' --tables ' . $tables_to_export);
            $dump_params .= ' 2>&1';// ensures console output is sent to the $output array

            // allow overriding the path to tool via url
            $toolfilename = !empty($_GET['tool']) ? $_GET['tool'] : $mysqldump_exe;

            // remove " marks in parameters for friendlier IIS support
//REQUIRES TESTING:        if (strstr($toolfilename,'.exe')) $dump_params = str_replace('"','',$dump_params);

            if ($debug) {
                $messageStack->add_session(TEXT_COMMAND . $toolfilename . ' ' . $dump_params, 'caution');
            }

            //- In PHP/5.2 and older you have to surround the full command plus arguments in double quotes
            //- In PHP/5.3 and greater you don't have to (if you do, your script will break)

            // this is the actual mysqldump. steve removed @: why hide errors?
            $resultcodes = exec($toolfilename . $dump_params, $output, $dump_results);
            // $dump_results is a number returned by operating system: anything other than 0 is a fail
            // Windows System Error Codes https://msdn.microsoft.com/en-us/library/windows/desktop/ms681381(v=vs.85).aspx
            // UNIX Exit codes http://www.faqs.org/docs/abs/HTML/exitcodes.html

            exec('exit(0)'); // terminates the current script successfully

            //Exit code = -1? Cannot find any reference to -1
            if ($dump_results === -1) {
                //hide password
                $messageStack->add_session(FAILURE_BACKUP_FAILED_CHECK_PERMISSIONS . TEXT_COMMAND_RUN . $toolfilename . str_replace('--password=' . DB_SERVER_PASSWORD, '--password=*****', str_replace('2>&1', '', $dump_params)));
            }

            if ($dump_results !== 0 && zen_not_null($dump_results)) {
                $messageStack->add_session(TEXT_RESULT_CODE . ' ($dump_results) ' . $dump_results);
            }

            // parse the value that comes back from the script
            if (zen_not_null($resultcodes)) {
                if ($debug) {
                    $messageStack->add_session('$resultcodes:' . '<br>' . mv_printVar($resultcodes));//steve using custom function
                }
                [$strA, $strB] = array_pad(explode('|', $resultcodes, 2), 2, null);//steve php notice when only one value
                //https://www.zen-cart.com/showthread.php?35714-Backup-MySQL-Database&p=1396099#post1396099
                //$array = print_r($resultcodes, true);if ($debug) $messageStack->add('$resultcodes: ' . $array, 'error');
                if ($debug) {
                    $messageStack->add_session('$resultcodes valueA: ' . $strA);
                }
                if ($debug) {
                    $messageStack->add_session('$resultcodes valueB: ' . $strB);
                }
            }

            // $output contains response strings from execution.
            if (zen_not_null($output)) {
                foreach ($output as $key => $value) {
                    $messageStack->add_session('console $output: [' . $key . '] => "' . $value . '<br>');
                }
            }

            if (($dump_results === 0 || $dump_results === '') && file_exists(DIR_FS_BACKUP . $backup_file)) {
                // display success message noting that MYSQLDUMP was used
                $messageStack->add_session(
                    '<a href="' . ((ENABLE_SSL_ADMIN === 'true') ? DIR_WS_HTTPS_ADMIN : DIR_WS_ADMIN) . 'backups/' . $backup_file . '">' . SUCCESS_DATABASE_SAVED . '</a>',
                    'success'
                );
            } elseif ($dump_results === 127) { // 127 = command not found
                $messageStack->add_session(FAILURE_DATABASE_NOT_SAVED_UTIL_NOT_FOUND);
            } elseif (stripos($strA, 'Access denied') !== false && stripos($strA, 'LOCK TABLES') !== false) {
                unlink(DIR_FS_BACKUP . $backup_file);
                zen_redirect(
                    zen_href_link(
                        FILENAME_BACKUP_MYSQL,
                        'action=backupnow' . ($debug ? '&debug=1' : '') . (($_POST['compress'] !== false) ? '&compress=' . $_POST['compress'] : '') . (($tables_to_export !== '') ? '&tables='
                            . str_replace(' ', ',', $tables_to_export) : '') . '&skiplocks=yes'
                    )
                );
            } else {
                $messageStack->add_session(FAILURE_DATABASE_NOT_SAVED, 'error');
            }

            //compress the file as requested & optionally download
            if (isset($_POST['download']) && ($_POST['download'] == 'yes') && file_exists(DIR_FS_BACKUP . $backup_file)) {
                switch ($_POST['compress']) {
                    case 'gzip':
                        @exec(LOCAL_EXE_GZIP . ' ' . DIR_FS_BACKUP . $backup_file);
                        $backup_file .= '.gz';
                        break;
                    case 'zip':
                        @exec(LOCAL_EXE_ZIP . ' -j ' . DIR_FS_BACKUP . $backup_file . '.zip ' . DIR_FS_BACKUP . $backup_file);
                        if (file_exists(DIR_FS_BACKUP . $backup_file) && file_exists(DIR_FS_BACKUP . $backup_file . 'zip')) {
                            unlink(DIR_FS_BACKUP . $backup_file);
                        }
                        $backup_file .= '.zip';
                }
                if (preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT'])) {
                    header('Content-Type: application/octetstream');
//            header('Content-Disposition: inline; filename="' . $backup_file . '"');
                    header('Content-Disposition: attachment; filename=' . $backup_file);
                    header("Expires: Mon, 26 Jul 2001 05:00:00 GMT");
                    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
                    header("Cache-Control: must_revalidate, post-check=0, pre-check=0");
                    header("Pragma: public");
                    header("Cache-control: private");
                } else {
                    header('Content-Type: application/x-octet-stream');
                    header('Content-Disposition: attachment; filename=' . $backup_file);
                    header("Expires: Mon, 26 Jul 2001 05:00:00 GMT");
                    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
                    header("Pragma: no-cache");
                }

                readfile(DIR_FS_BACKUP . $backup_file);
                unlink(DIR_FS_BACKUP . $backup_file);

                exit;
            } else {
                switch ($_POST['compress'] && file_exists(DIR_FS_BACKUP . $backup_file)) {
                    case 'gzip':
                        @exec(LOCAL_EXE_GZIP . ' ' . DIR_FS_BACKUP . $backup_file);
                        if (file_exists(DIR_FS_BACKUP . $backup_file)) {
                            @exec('gzip ' . DIR_FS_BACKUP . $backup_file);
                        }
                        break;
                    case 'zip':
                        @exec(LOCAL_EXE_ZIP . ' -j ' . DIR_FS_BACKUP . $backup_file . '.zip ' . DIR_FS_BACKUP . $backup_file);
                        if (file_exists(DIR_FS_BACKUP . $backup_file) && file_exists(DIR_FS_BACKUP . $backup_file . 'zip')) {
                            unlink(DIR_FS_BACKUP . $backup_file);
                        }
                }
            }
            zen_redirect(zen_href_link(FILENAME_BACKUP_MYSQL, ($debug ? 'debug=ON' : '')));
            break;

        case 'restorenow':
        case 'restorelocalnow':
            zen_set_time_limit(300);
            $specified_restore_file = (isset($_GET['file'])) ? $_GET['file'] : '';

            if ($specified_restore_file != '' && file_exists(DIR_FS_BACKUP . $specified_restore_file)) {
                $restore_file = DIR_FS_BACKUP . $specified_restore_file;
                $extension = substr($specified_restore_file, -3);

                //determine file format and unzip if needed
                if (($extension == 'sql') || ($extension == '.gz') || ($extension == 'zip')) {
                    switch ($extension) {
                        case 'sql':
                            $restore_from = $restore_file;
                            $remove_raw = false;
                            break;
                        case '.gz':
                            $restore_from = substr($restore_file, 0, -3);
                            exec(LOCAL_EXE_GUNZIP . ' ' . $restore_file . ' -c > ' . $restore_from);
                            $remove_raw = true;
                            break;
                        case 'zip':
                            $restore_from = substr($restore_file, 0, -4);
                            exec(LOCAL_EXE_UNZIP . ' ' . $restore_file . ' -d ' . DIR_FS_BACKUP);
                            $remove_raw = true;
                    }
                }
            } elseif ($action === 'restorelocalnow') {
                $sql_file = new upload('sql_file', DIR_FS_BACKUP);
                $specified_restore_file = $sql_file->filename;
                $restore_from = DIR_FS_BACKUP . $specified_restore_file;
            }

            //Restore using "mysql"
            $load_params = ' --database=' . DB_DATABASE;
            $load_params .= ' --host=' . DB_SERVER;
            $load_params .= ' --user=' . DB_SERVER_USERNAME;
            $load_params .= ((DB_SERVER_PASSWORD === '') ? '' : ' --password=' . OS_DELIM . DB_SERVER_PASSWORD . OS_DELIM);
            $load_params .= ' ' . DB_DATABASE; // this needs to be the 2nd-last parameter
            $load_params .= ' < ' . OS_DELIM . $restore_from . OS_DELIM; // this needs to be the LAST parameter
            $load_params .= ' 2>&1';
            //DEBUG echo $mysql_exe . ' ' . $load_params;

            if ($specified_restore_file !== '' && file_exists($restore_from)) {
                $toolfilename = !empty($_GET['tool']) ? $_GET['tool'] : $mysql_exe;

                //set all slashes to forward
                $toolfilename = str_replace('\\', '/', $toolfilename);

                if ($debug) {
                    $messageStack->add_session('$toolfilename=' . $toolfilename, 'info');
                }
                // remove " marks in parameters for friendlier IIS support
//REQUIRES TESTING:          if (strstr($toolfilename,'.exe')) $load_params = str_replace('"','',$load_params);

                if ($debug) {
                    $messageStack->add_session(TEXT_COMMAND . $toolfilename . $load_params, 'info');
                }
                $resultcodes = exec($toolfilename . $load_params, $output, $load_results);
                //$output gets filled with an array of all the normally displayed dialogue that comes back from the command, $load_results is an integer of the execution result
                exec('exit(0)');

                // parse the value that comes back from the script
                if (zen_not_null($resultcodes)) { // what gets returned from exec() depends on the program that was run
                    [$strA, $strB] = array_pad(explode('|', $resultcodes, 2), 2, null);
                    if (!empty($strA)) {
                        $messageStack->add_session('restore $resultcodes valueA: ' . $strA);
                    }
                    if (!empty($strB)) {
                        $messageStack->add_session('restore $resultcodes valueB: ' . $strB);
                    }
                }

                if ($load_results !== 0 && zen_not_null($load_results)) {
                    $messageStack->add_session(TEXT_RESULT_CODE . $load_results);
                }

                // $output contains response strings from execution. This displays if anything is generated.
                // $output= array(1,2,3);//to test, as nothing ever seems to come out of $output!
                if (zen_not_null($output)) {
                    foreach ($output as $key => $value) {
                        $messageStack->add_session('console $output:' . "$key => $value<br>");
                    }
                }

                if ($load_results === 0) {
                    // store the last-restore-date, if successful. Update key if it exists rather than delete and insert or the insert increments the id
                    $db->Execute(
                        "INSERT INTO " . TABLE_CONFIGURATION . "
                        (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, date_added)
                        VALUES
                        ('Last Database Restore', 'BACKUP_MYSQL_LAST_RESTORE', '" . $specified_restore_file . "', 'Last database restore file', 6, now())
                        ON DUPLICATE KEY UPDATE configuration_value ='" . $specified_restore_file . "'"
                    );
                    $messageStack->add_session('<a href="' . ((ENABLE_SSL_ADMIN == 'true') ? DIR_WS_HTTPS_ADMIN : DIR_WS_ADMIN) . 'backups/' . $specified_restore_file . '">' . SUCCESS_DATABASE_RESTORED . '</a>', 'success');
                } elseif ($load_results == '127') {
                    $messageStack->add_session(FAILURE_DATABASE_NOT_RESTORED_UTIL_NOT_FOUND, 'error');
                } else {
                    $messageStack->add_session(FAILURE_DATABASE_NOT_RESTORED, 'error');
                } // endif $load_results
            } else {
                $messageStack->add_session(sprintf(FAILURE_DATABASE_NOT_RESTORED_FILE_NOT_FOUND, '[' . $restore_from . ']'), 'error');
            } // endif file_exists

            zen_redirect(zen_href_link(FILENAME_BACKUP_MYSQL, ($debug ? 'debug=ON' : '')));
            break;

        case 'download':
            $extension = substr($_GET['file'], -3);

            if (($extension === 'zip') || ($extension === '.gz') || ($extension === 'sql')) {
                if ($fp = fopen(DIR_FS_BACKUP . $_GET['file'], 'rb')) {
                    $buffer = fread($fp, filesize(DIR_FS_BACKUP . $_GET['file']));
                    fclose($fp);
                    header('Content-type: application/x-octet-stream');
                    header('Content-disposition: attachment; filename=' . $_GET['file']);
                    echo $buffer;
                    exit;
                }
            } else {
                $messageStack->add(ERROR_DOWNLOAD_LINK_NOT_ACCEPTABLE, 'error');
            }
            break;

        case 'deleteconfirm':
            if (str_contains($_GET['file'], '..')) {
                zen_redirect(zen_href_link(FILENAME_BACKUP_MYSQL, ($debug ? 'debug=ON' : '')));
            }

            // zen_remove does not return a value
            /*
                $zremove_error = zen_remove(DIR_FS_BACKUP . '/' . $_GET['file']);
                // backwards compatibility:
                if (isset($zen_remove_error) && $zen_remove_error == true) {
                    $zremove_error = $zen_remove_error;
                }
                if (!$zremove_error) {
                    $messageStack->add_session(SUCCESS_BACKUP_DELETED, 'success');
                    zen_redirect(zen_href_link(FILENAME_BACKUP_MYSQL));
                }
            */
            zen_remove(DIR_FS_BACKUP . '/' . $_GET['file']);
            $messageStack->add_session(SUCCESS_BACKUP_DELETED, 'success');
            zen_redirect(zen_href_link(FILENAME_BACKUP_MYSQL, ($debug ? 'debug=ON' : '')));
            break;
    }
}

// Check if the backup directory exists
$dir_ok = false;
if (is_dir(DIR_FS_BACKUP)) {
    if (is_writable(DIR_FS_BACKUP)) {
        $dir_ok = true;
    } else {
        $messageStack->add(ERROR_BACKUP_DIRECTORY_NOT_WRITEABLE, 'error');
    }
} else {
    $messageStack->add(ERROR_BACKUP_DIRECTORY_DOES_NOT_EXIST, 'error');
}
?>
<!doctype html >
<html <?= HTML_PARAMS ?>>
<head>
    <?php
    require DIR_WS_INCLUDES . 'admin_html_head.php'; ?>
</head>
<body>
<!-- header //-->
<?php
require DIR_WS_INCLUDES . 'header.php'; ?>
<!-- header_eof //-->
<!-- body //-->
<div class="container-fluid">
    <h1><?= HEADING_TITLE ?></h1>
    <div class="row">
        <!-- body_text //-->
        <?php
        if (!$ssl_on) {  // display security warning about downloads if not SSL
            echo WARNING_NOT_SECURE_FOR_DOWNLOADS;
        } ?>
        <div class="col-xs-12 col-sm-12 col-md-9 col-lg-9 configurationColumnLeft">
            <table class="table table-hover">
                <thead>
                <tr class="dataTableHeadingRow">
                    <th class="dataTableHeadingContent"><?= TABLE_HEADING_TITLE ?></th>
                    <th class="dataTableHeadingContent center"><?= TABLE_HEADING_FILE_DATE ?></th>
                    <th class="dataTableHeadingContent right"><?= TABLE_HEADING_FILE_SIZE ?></th>
                    <th class="dataTableHeadingContent right"><?= TABLE_HEADING_ACTION ?>&nbsp;</th>
                </tr>
                </thead>
                <tbody>
                <?php
                //  if (!get_cfg_var('safe_mode') && $dir_ok === true) {
                $dir = dir(DIR_FS_BACKUP);
                $contents = [];
                // build array of files in backup directory
                while ($file = $dir->read()) {
                    if (!is_dir(DIR_FS_BACKUP . $file)) {
                        if (!str_starts_with($file, '.') && !in_array($file, ['empty.txt', 'index.php', 'index.htm', 'index.html'])) {
                            $contents[] = $file;
                        }
                    }
                }
                sort($contents);
                for ($i = 0, $n = sizeof($contents); $i < $n; $i++) {
                    $entry = $contents[$i];
                    $check = 0;

                    if (
                        (!isset($_GET['file']) || $_GET['file'] == $entry)
                        && !isset($buInfo)
                        && ($action != 'backup')
                        && ($action != 'restorelocal')) {
                        $file_array['file'] = $entry;
                        $file_array['date'] = date(PHP_DATE_TIME_FORMAT, filemtime(DIR_FS_BACKUP . $entry));
                        $file_array['size'] = number_format(filesize(DIR_FS_BACKUP . $entry) / 1024) . ' kb';

                        switch (substr($entry, -3)) {
                            case 'zip':
                                $file_array['compression'] = 'ZIP';
                                break;
                            case '.gz':
                                $file_array['compression'] = 'GZIP';
                                break;
                            default:
                                $file_array['compression'] = TEXT_NO_EXTENSION;
                                break;
                        }

                        $buInfo = new objectInfo($file_array);
                    }

                    if (isset($buInfo) && is_object($buInfo) && ($entry === $buInfo->file)) {
                        $onclick_link = 'file=' . $buInfo->file . '&action=restore' . ($debug ? '&debug=ON' : '');
                        ?>
                        <tr id="defaultSelected" class="dataTableRowSelected">
                        <?php
                    } else {
                        $onclick_link = 'file=' . $entry . ($debug ? '&debug=ON' : ''); ?>
                        <tr class="dataTableRow">
                        <?php
                    }
                    ?>
                    <td class="dataTableContent" onClick="document.location.href='<?= zen_href_link(FILENAME_BACKUP_MYSQL, $onclick_link) ?>'">
                        <?= '<a href="' . ((ENABLE_SSL_ADMIN == 'true') ? DIR_WS_HTTPS_ADMIN : DIR_WS_ADMIN) . 'backups/' . $entry . '">' . zen_image(DIR_WS_ICONS . 'file_download.gif', ICON_FILE_DOWNLOAD) . '</a>&nbsp;' . $entry ?></td>
                    <td class="dataTableContent center" onClick="document.location.href='<?= zen_href_link(FILENAME_BACKUP_MYSQL, $onclick_link) ?>'">
                        <?= date(PHP_DATE_TIME_FORMAT, filemtime(DIR_FS_BACKUP . $entry)) ?></td>
                    <td class="dataTableContent right" onClick="document.location.href='<?= zen_href_link(FILENAME_BACKUP_MYSQL, $onclick_link) ?>'"><?= number_format(filesize(DIR_FS_BACKUP . $entry) / 1024) ?> Kb</td>
                    <td class="dataTableContent right"><?php
                        if (isset($buInfo) && is_object($buInfo) && ($entry == $buInfo->file)) {
                            echo zen_image(DIR_WS_IMAGES . 'icon_arrow_right.gif', '');
                        } else {
                            echo '<a href="' . zen_href_link(FILENAME_BACKUP_MYSQL, 'file=' . $entry) . '">' . zen_image(DIR_WS_IMAGES . 'icon_info.gif', IMAGE_ICON_INFO) . '</a>';
                        } ?>&nbsp;
                    </td>
                    </tr>
                    <?php
                }
                $dir->close();
                //  } // endif safe-mode & dir_ok
                ?>
                </tbody>
            </table>

            <div class="right">
                <?php
                if (($action !== 'backup') && !ini_get('safe_mode') && $dir_ok) {
                    echo '<a class="btn btn-primary" role="button" href="' . zen_href_link(FILENAME_BACKUP_MYSQL, 'action=backup' . ($debug ? '&debug=ON' : '')) . (($tables_to_export != '') ? '&tables=' . str_replace(' ', ',', $tables_to_export) : '') . '">' . IMAGE_BACKUP . '</a>&nbsp;&nbsp;';
                }
                if (($action != 'restorelocal') && isset($dir)) {
                    echo '<a class="btn btn-primary" role="button" href="' . zen_href_link(FILENAME_BACKUP_MYSQL, 'action=restorelocal' . ($debug ? '&debug=ON' : '')) . '">' . IMAGE_RESTORE . '</a>';
                } ?>
            </div>
            <div class="small">
                <?= TEXT_BACKUP_DIRECTORY . ' ' . DIR_FS_BACKUP ?>
                <br>
                <?php
                if (defined('BACKUP_MYSQL_LAST_RESTORE')) {
                    echo TEXT_LAST_RESTORATION . ' ' . BACKUP_MYSQL_LAST_RESTORE . ' <a href="' . zen_href_link(FILENAME_BACKUP_MYSQL, 'action=forget') . '">' . TEXT_FORGET . '</a>';
                }
                ?>
            </div>
        </div>

        <div class="col-xs-12 col-sm-12 col-md-3 col-lg-3 configurationColumnRight">
            <?php
            $heading = [];
            $contents = [];

            switch ($action) {
                case 'backup':
                    $heading[] = ['text' => '<strong>' . TEXT_INFO_HEADING_NEW_BACKUP . '</strong>'];

                    $contents = ['form' => zen_draw_form('backup', FILENAME_BACKUP_MYSQL, 'action=backupnow' . ($debug ? '&debug=ON' : '') . (($tables_to_export != '') ? '&tables=' . str_replace(' ', ',', $tables_to_export) : ''), 'post', 'id="backup"')];

                    $contents[] = ['text' => TEXT_INFO_NEW_BACKUP];

                    $contents[] = ['text' => zen_draw_radio_field('compress', 'no', !@file_exists(LOCAL_EXE_GZIP) && !$compress_override) . ' ' . TEXT_INFO_USE_NO_COMPRESSION];

                    if (@file_exists(LOCAL_EXE_GZIP) || $compress_override) {
                        $contents[] = ['text' => zen_draw_radio_field('compress', 'gzip', true) . ' ' . TEXT_INFO_USE_GZIP];
                    }
                    if (@file_exists(LOCAL_EXE_ZIP)) {
                        $contents[] = ['text' => zen_draw_radio_field('compress', 'zip', !@file_exists(LOCAL_EXE_GZIP)) . ' ' . TEXT_INFO_USE_ZIP];
                    }

                    $contents[] = ['text' => zen_draw_radio_field('skiplocks', 'yes', false) . ' ' . TEXT_INFO_SKIP_LOCKS];

                    // Download to file --- Should only be done if SSL is active, otherwise database is exposed as clear text
                    if ($dir_ok) {
                        $contents[] = ['text' => zen_draw_checkbox_field('download', 'yes') . ' ' . TEXT_INFO_DOWNLOAD_ONLY];
                    } else {
                        $contents[] = ['text' => zen_draw_radio_field('download', 'yes', true) . ' ' . TEXT_INFO_DOWNLOAD_ONLY];
                    }
                    if (!$ssl_on) {
                        $contents[] = ['text' => '<span class="errorText">* ' . TEXT_INFO_BEST_THROUGH_HTTPS . ' * </span>'];
                    }
                    // add suffix to backup filename
                    $contents[] = [
                        'text' => '<label>' . TEXT_ADD_SUFFIX . '</label><br>' . zen_draw_input_field('suffix', '', 'size="31" maxlength="30"')
                    ];

                    // Display Backup and Cancel buttons
                    $contents[] = [
                        'align' => 'center',
                        'text' => '<button type="submit" form="backup" class="btn btn-primary" role="button">' . IMAGE_BACKUP . '</button>' . '&nbsp;&nbsp;' .
                            '<a href="' . zen_href_link(FILENAME_BACKUP_MYSQL) . '" class="btn btn-primary" role="button">' . IMAGE_CANCEL . '</a>'
                    ];
                    break;

                case 'restore':
                    $heading[] = ['text' => '<strong>' . $buInfo->file . '</strong>'];

                    $contents[] = ['text' => TEXT_INFO_DATE . ' ' . $buInfo->date];
                    $contents[] = ['text' => TEXT_INFO_SIZE . ' ' . $buInfo->size];
                    $contents[] = ['text' => TEXT_INFO_COMPRESSION . ' ' . $buInfo->compression];

                    $contents[] = [
                        'text' => zen_break_string(
                            sprintf(TEXT_INFO_RESTORE, DIR_FS_BACKUP . (($buInfo->compression != TEXT_NO_EXTENSION) ? substr($buInfo->file, 0, strrpos($buInfo->file, '.')) : $buInfo->file), ($buInfo->compression != TEXT_NO_EXTENSION) ? TEXT_INFO_UNPACK : ''),
                            35,
                            ' '
                        )
                    ];

                    // Display Restore and Cancel buttons
                    $contents[] = [
                        'align' => 'center',
                        'text' => '<a class="btn btn-primary" role="button" href="' . zen_href_link(FILENAME_BACKUP_MYSQL, 'file=' . $buInfo->file . '&action=restorenow' . ($debug ? '&debug=ON' : '')) . '">' . IMAGE_RESTORE . '</a>' . '&nbsp;&nbsp' .
                            '<a class="btn btn-primary" role="button" href="' . zen_href_link(
                                FILENAME_BACKUP_MYSQL,
                                'file=' . $buInfo->file . ($debug ? '&debug=ON' : '')
                            ) . '">' . IMAGE_CANCEL . '</a>'
                    ];
                    break;

                case 'restorelocal':
                    $heading[] = ['text' => '<strong>' . TEXT_INFO_HEADING_RESTORE_LOCAL . '</strong>'];

                    $contents = ['form' => zen_draw_form('restore', FILENAME_BACKUP_MYSQL, 'action=restorelocalnow' . ($debug ? '&debug=ON' : ''), 'post', 'enctype="multipart/form-data"')];
                    $contents[] = ['text' => TEXT_INFO_RESTORE_LOCAL];
                    if (!$ssl_on) {
                        $contents[] = ['text' => '<span class="errorText">* ' . TEXT_INFO_BEST_THROUGH_HTTPS . ' *</span>'];
                    }
                    $contents[] = ['text' => zen_draw_file_field('sql_file')];
                    $contents[] = ['text' => TEXT_INFO_RESTORE_LOCAL_RAW_FILE];

                    //Display Restore and Cancel buttons
                    $contents[] = [
                        'align' => 'center',
                        'text' => '<button type="submit" class="btn btn-primary" role="button">' . IMAGE_RESTORE . '</button>' . '&nbsp;&nbsp;' .
                            '<a class="btn btn-primary" role="button" href="' . zen_href_link(FILENAME_BACKUP_MYSQL, ($debug ? 'debug=ON' : '')) . '">' . IMAGE_CANCEL . '</a>'
                    ];
                    break;

                case 'delete':
                    if (!$dir_ok) {
                        break;
                    }
                    $heading[] = ['text' => '<strong>' . $buInfo->date . '</strong>'];

                    $contents = ['form' => zen_draw_form('delete', FILENAME_BACKUP_MYSQL, 'file=' . $buInfo->file . '&action=deleteconfirm' . ($debug ? '&debug=ON' : ''))];
                    $contents[] = ['text' => TEXT_DELETE_INTRO];
                    $contents[] = ['text' => '<strong>' . $buInfo->file . '</strong>'];

                    //Display Delete and Cancel buttons
                    $contents[] = [
                        'align' => 'center',
                        'text' => '<button type="submit" class="btn btn-primary">' . IMAGE_DELETE . '</button>' . '&nbsp;&nbsp;' .
                            '<a class="btn btn-primary" role="button" href="' . zen_href_link(FILENAME_BACKUP_MYSQL, 'file=' . $buInfo->file . ($debug ? '&debug=ON' : '')) . '">' . IMAGE_CANCEL . '</a>'
                    ];
                    break;

                default:
                    if (isset($buInfo) && is_object($buInfo)) {
                        $heading[] = ['text' => '<strong>' . $buInfo->file . '</strong>'];

                        $contents[] = ['text' => TEXT_INFO_DATE . ' ' . $buInfo->date];
                        $contents[] = ['text' => TEXT_INFO_SIZE . ' ' . $buInfo->size];
                        $contents[] = ['text' => TEXT_INFO_COMPRESSION . ' ' . $buInfo->compression];

                        //Display Restore and Delete buttons
                        $contents[] = [
                            'align' => 'center',
                            'text' => '<a class="btn btn-primary" role="button" href="' . zen_href_link(FILENAME_BACKUP_MYSQL, 'file=' . $buInfo->file . '&action=restore' . ($debug ? '&debug=ON' : '')) . '">' . IMAGE_RESTORE . '</a>' . '&nbsp;&nbsp;' .
                                (($dir_ok && !$exec_disabled) ? '<a class="btn btn-primary" role="button" href="' . zen_href_link(FILENAME_BACKUP_MYSQL, 'file=' . $buInfo->file . '&action=delete' . ($debug ? '&debug=ON' : '')) . '">' . IMAGE_DELETE . '</a>' : '')
                        ];
                    }
                    break;
            }

            if (zen_not_null($heading) && zen_not_null($contents)) {
                $box = new box();
                echo $box->infoBox($heading, $contents);
            }
            ?>
        </div>
    </div>
    <!-- body_text_eof //-->
</div>
<!-- body_eof //-->
<!-- footer //-->
<?php
require DIR_WS_INCLUDES . 'footer.php'; ?>
<!-- footer_eof //-->
</body>
</html>
<?php
require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
