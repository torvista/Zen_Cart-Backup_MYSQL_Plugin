<?php

declare(strict_types=1);

/**
 * part of the Backup MySQL plugin
 * @copyright Copyright 2024 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @author Dr.Byte
 * @author torvista
 * @version $Id: torvista 01 Feb 2025
 */

/** phpStorm
 * @var queryFactoryResult $db
 * @var messageStack $messageStack
 */

// determine the operating system, to use the correct quotes around the  password
$os_win = str_starts_with(strtoupper(PHP_OS), 'WIN');
if ($os_win) {
    define('OS_DELIM', '"');
} else {
    define('OS_DELIM', "'");
}

require('includes/application_top.php');

//echo ini_get('memory_limit');

/**
 * @param $path
 * @return string[]
 */
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

/**
 * Compress a file using gzip
 *
 * Rewritten from Simon East's version here:
 * https://stackoverflow.com/a/22754032/3499843
 *
 * @param  string  $inFilename  Input filename
 * @param  int  $level  Compression level (default: 9)
 *
 * @return string Output filename
 * @throws Exception if the input or output file cannot be opened
 *
 */
function gzcompressfile(string $inFilename, int $level = 9): string
{
    // Is the file gzipped already?
    $extension = pathinfo($inFilename, PATHINFO_EXTENSION);
    if ($extension == 'gz') {
        return $inFilename;
    }

    // Open input file
    $inFile = fopen($inFilename, 'rb');
    if ($inFile === false) {
        throw new \Exception("Unable to open input file: $inFilename");
    }

    // Open output file
    $gzFilename = $inFilename . '.gz';
    $mode = 'wb' . $level;
    $gzFile = gzopen($gzFilename, $mode);
    if ($gzFile === false) {
        fclose($inFile);
        throw new \Exception("Unable to open output file: $gzFilename");
    }

    // Stream copy
    $length = 512 * 1024; // 512 kB
    while (!feof($inFile)) {
        gzwrite($gzFile, fread($inFile, $length));
    }

    // Close files
    fclose($inFile);
    gzclose($gzFile);

    // Return the new filename
    return $gzFilename;
}

$dump_params = '';
$restore_file = '';
$resultcodes = '';
$strA = '';
$strB = '';

$debug = isset($_GET['debug']) && (strtoupper($_GET['debug']) === 'ON' || (int)$_GET['debug'] === 1);

$tables_to_export = !empty($_GET['tables']) ? str_replace(',', ' ', $_GET['tables']) : '';
$redirect = !empty($_GET['returnto']) ? $_GET['returnto'] : '';
$_POST['compress'] = !empty($_REQUEST['compress']) ? $_REQUEST['compress'] : false;
$skip_locks_requested = isset($_REQUEST['skiplocks']) && $_REQUEST['skiplocks'] == 'yes';

// check for use of SSL
$ssl_on = str_starts_with(HTTP_SERVER, 'https');

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
    $messageStack->add('Backup Directory: "' . DIR_FS_BACKUP . '" valid.', 'success');
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
    $messageStack->add(ERROR_EXEC_DISABLED, 'caution');
    $exec_disabled = true;
}
if (!$os_win && in_array('shell_exec', explode(',', str_replace(' ', '', $php_disabled_functions)), true)) {
    //$messageStack→add(ERROR_SHELL_EXEC_DISABLED, 'error');//shell_exec only used on Unix to find mysql: show error later
    $shell_exec_disabled = true;
}
if ($exec_disabled || $shell_exec_disabled) {
    $messageStack->add(ERROR_PHP_DISABLED_FUNCTIONS . $php_disabled_functions, 'info');
}

//Compression
//GZIP
//
//$gzip_enabled = function_exists('ob_gzhandler') && ini_get('zlib.output_compression');
$gzip_enabled = true;

//ZIP
$zip_enabled = class_exists('ZipArchive');
if ($debug) {
    $messageStack->add($zip_enabled ? 'ZIP compression enabled in PHP' : 'ZIP compression not enabled in PHP (class ZipArchive not found', 'info');
}

// WHERE ARE THE MYSQL EXECUTABLES?
// The location will vary per server/installation.
$mysql_exe = 'unknown';
$mysqldump_exe = 'unknown';
$path_found = '';

// try the last successful path saved
if (defined('BACKUP_MYSQL_LOCATION')) {
    if ($debug) {
        $messageStack->add('BACKUP_MYSQL_LOCATION = "' . BACKUP_MYSQL_LOCATION, 'info');
    }
    [$mysql_exe, $mysqldump_exe] = checkMysqlPath(BACKUP_MYSQL_LOCATION);
}
if (defined('BACKUP_MYSQL_LOCATION') && ($mysql_exe !== 'unknown' && $mysqldump_exe !== 'unknown')) {
    $path_found = BACKUP_MYSQL_LOCATION;
    if ($debug) {
        $messageStack->add('MySQL tools found from BACKUP_MYSQL_LOCATION: $mysql_exe="' . $mysql_exe . '", $mysqldump_exe="' . $mysqldump_exe . '"', 'success');
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
        $messageStack->add('Auto-Detected path to check:"' . $paths_auto . '"', 'info');
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
            $messageStack->add('Checking Path: "' . $path . '"', 'info');
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
        $messageStack->add('MySQL tools found: $mysql_exe="' . $mysql_exe . '", $mysqldump_exe="' . $mysqldump_exe . '"', 'success');
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

            $backup_file = 'db_' . DB_DATABASE . '-' . ($tables_to_export !== '' ? 'limited-' : '') . date('Y-m-d_H-i-s') . $suffix . '.sql';
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
            $dump_params .= ' --complete-insert';  // undo optimization slightly and do "complete inserts"--lists all column names for the benefit of restore in different systems
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

            //  $dump_params .= ' | tail+2';// ensures console output is sent to the $output array


            // if using the "--tables" parameter, this should be the last parameter, and tables should be space-delimited
            // fill $tables_to_export with list of tables, separated by spaces, if wanna just export certain tables
            $dump_params .= (($tables_to_export === '') ? '' : ' --tables ' . $tables_to_export);

            $dump_params .= ' 2>&1';// ensures console output is sent to the $output array

            // allow overriding the path to tool via url
            $toolfilename = !empty($_GET['tool']) ? $_GET['tool'] : $mysqldump_exe;

            if ($debug) {
                $messageStack->add_session('Backup COMMAND: ' . $toolfilename . ' ' . $dump_params, 'info');
            }

            //- In PHP/5.2 and older you have to surround the full command plus arguments in double quotes
            //- In PHP/5.3 and greater you don't have to (if you do, your script will break)

            // this is the actual mysqldump command
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
                // display a success message noting that MYSQLDUMP was used
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

            if (file_exists(DIR_FS_BACKUP . $backup_file)) {
                switch ($_POST['compress']) {
                    case 'gzip':
                        try {
                            $gzipped_filename = gzcompressfile(DIR_FS_BACKUP . $backup_file);
                        } catch (Exception $e) {
                            die ('line ' . __LINE__ . ': gzcompressfile failed with "' . DIR_FS_BACKUP . $backup_file . '"');
                        }
                        //successful compression
                        if ($gzipped_filename = DIR_FS_BACKUP . $backup_file . '.gz') {
                            unlink(DIR_FS_BACKUP . $backup_file);
                            $backup_file = $backup_file . '.gz';
                        }
                        break;

                    case 'zip':
                        $zip = new ZipArchive();
                        $zipped_file = DIR_FS_BACKUP . $backup_file . '.zip';
                        if ($zip->open($zipped_file, ZipArchive::CREATE) !== true) {
                            exit("cannot open <$zipped_file>\n");
                        }
                        $zip->addFile(DIR_FS_BACKUP . $backup_file, $backup_file);
                        $zip->close();
                        if (file_exists($zipped_file) && file_exists(DIR_FS_BACKUP . $backup_file)) {
                            unlink(DIR_FS_BACKUP . $backup_file);
                        }
                        $backup_file .= '.zip';
                        break;
                }
            }

            //download
            if ((isset($_POST['download']) && $_POST['download'] === 'yes') && file_exists(DIR_FS_BACKUP . $backup_file)) {
                if (str_contains($_SERVER['HTTP_USER_AGENT'], 'MSIE')) {
                    header('Content-Type: application/octetstream');
                    // header('Content-Disposition: inline; filename="' . $backup_file . '"');
                    header('Content-Disposition: attachment; filename="' . $backup_file . '"');//steve added double quotes
                    header('Content-Length: ' . filesize(DIR_FS_BACKUP . $backup_file));//steve added
                    header('Expires: Mon, 26 Jul 2001 05:00:00 GMT');
                    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
                    header('Cache-Control: must_revalidate, post-check=0, pre-check=0');
                    header('Pragma: public');
                    header('Cache-control: private');
                } else {
                    header('Content-Type: application/x-octet-stream');
                    header('Content-Disposition: attachment; filename="' . $backup_file . '"');//steve added double quotes
                    header('Content-Length: ' . filesize(DIR_FS_BACKUP . $backup_file));//steve added
                    header('Expires: Mon, 26 Jul 2001 05:00:00 GMT');
                    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
                    header('Pragma: no-cache');
                }

                if (ob_get_level() !== 0) {
                    ob_end_clean();
                }
                readfile(DIR_FS_BACKUP . $backup_file);
                unlink(DIR_FS_BACKUP . $backup_file);
                unset($_GET['action']);
                exit();
            }

            zen_redirect(zen_href_link(FILENAME_BACKUP_MYSQL, ($debug ? 'debug=ON' : '')));
            break;

        case 'deleteconfirm':
            if (str_contains($_GET['file'], '..')) {
                zen_redirect(zen_href_link(FILENAME_BACKUP_MYSQL, ($debug ? 'debug=ON' : '')));
            }

            // zen_remove does not return a value, yet...
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

        //restorelocalnow: uploads a browsed file to the backups folder and restores
        case 'restorelocalnow':
            $sql_file = new upload('sql_file', DIR_FS_BACKUP, '644', ['gz', 'sql', 'zip']);
            if (!empty($sql_file->filename && file_exists(DIR_FS_BACKUP . $sql_file->filename))) {
                $restore_file = DIR_FS_BACKUP . $sql_file->filename;
            }

        //restorenow: restores a selected file from the backup folder
        case 'restorenow':

            zen_set_time_limit(300); //needed??

            if (empty($restore_file)) {
                if (!empty($_GET['file']) && file_exists(DIR_FS_BACKUP . $_GET['file'])) {
                    $restore_file = DIR_FS_BACKUP . $_GET['file'];
                } else {
                    $messageStack->add(sprintf(ERROR_RESTORE_FILE_NOT_FOUND, $restore_file), 'error');
                    zen_redirect(zen_href_link(FILENAME_BACKUP_MYSQL, ($debug ? 'debug=ON' : '')));
                }
            }
            $fileinfo = pathinfo(realpath($restore_file));

            // Determine the file format and unzip if needed. Note that *.sql.gz and *.sql.zip are first extracted to a temporary file.
            if (in_array($fileinfo['extension'], ['sql', 'gz', 'zip'])) {
                $tempfile_name = '';

                switch ($fileinfo['extension']) {
                    case 'sql':
                        if ($debug) {
                            $messageStack->add_session('source filetype=.sql', 'info');
                        }
                        $restore_from = $restore_file;
                        break;

                    case 'gz': // filename MUST be .sql.gz NOT just .gz
                        if ($debug) {
                            $messageStack->add_session('source filetype=.gz', 'info');
                        }
                        // Raising this value may increase performance
                        $buffer_size = 4096; // read 4kb at a time

                        // Open .gz file (in binary mode)
                        // 'r': open for reading only; place the file pointer at the beginning of the file.
                        // 'b': to force binary mode, which will not translate the line endings  \n to \r\n .
                        $file_gz = gzopen($restore_file, 'rb');

                        //create a temporary file
                        $tempfile = tmpfile();
                        // 'w':	open for writing only; place the file pointer at the beginning of the file and truncate the file to zero length.
                        // If the file does not exist, attempt to create it.
                        // fwrite($tempfile, 'wb');

                        // parse the source file
                        while (!gzeof($file_gz)) {
                            // Read buffer-size bytes
                            // Both fwrite and gzread and binary-safe
                            fwrite($tempfile, gzread($file_gz, $buffer_size));
                        }
                        // close source file
                        gzclose($file_gz);

                        // get temp file details
                        $tempfile_meta_data = stream_get_meta_data($tempfile);
                        $tempfile_name = $tempfile_meta_data["uri"];
                        $restore_from = $tempfile_name;
                        break;

                    case 'zip': // filename MUST be .sql.zip NOT just .zip
                        if ($debug) {
                            $messageStack->add_session('source filetype=.zip', 'info');
                        }

                        $temp_directory = sys_get_temp_dir();
                        $zip = new ZipArchive();
                        if ($zip->open($restore_file) === true) {
                            $zip->extractTo($temp_directory);
                            $tempfile_name = $temp_directory . '/' . $zip->getNameIndex(0);//name may not be the same as the source .zip name
                            $restore_from = $tempfile_name;
                            $zip->close();
                        } else {
                            $messageStack->add(sprintf(ERROR_RESTORE_FILE_ZIP_NOT_OPENED, $restore_file), 'error');
                        }
                }

                //handle MariaDB compatibility problem
                //https://mariadb.org/mariadb-dump-file-compatibility-change/
                //example problem lines: /*!999999\- enable the sandbox mode */  and  /*M!999999\- enable the sandbox mode */
                $problem_strings = ['/*!999999\- enable the sandbox mode */', '/*M!999999\- enable the sandbox mode */'];
                $spl_debug = false;

                if ($spl_debug) echo $restore_from . '<br>';
                try {
                    $file = new SplFileObject($restore_from);
                } catch (Exception $e) {
                    die ('line ' . __LINE__ . ': SplFileObject construct failed with "' . $restore_from . '"');
                }

                $file->seek(0);
                if ($spl_debug) echo 'first line:<br>' . $file->current() . '<br>';
                if (in_array(trim($file->current()), $problem_strings)) {
                    if ($spl_debug) echo 'problem string found!' . '<br>';
                    $linesToDelete = [1];

                    // lock the source file (which is a temp file post-extraction)
                    $file->flock(LOCK_EX);
                    
                    // create a new temp File
                    $tempFileName = tempnam(sys_get_temp_dir(), (string)rand());
                    $temp = new SplFileObject($tempFileName, 'w+');
                    if ($spl_debug) echo 'new temp file created: "' . $tempFileName . '"<br>';

                    // lock the new temp file
                    $temp->flock(LOCK_EX);
                    // write to the temp file without the lines
                    foreach ($file as $key => $line) {
                        if (in_array($key + 1, $linesToDelete) === false) {
                            $temp->fwrite($line);
                        } else {
                            if ($spl_debug) echo '$key=' . $key . ':deleted line ' . $key + 1 . '<br>';
                        }
                    }
                    // release the files to rename
                    if ($spl_debug) echo 'release files<br>';
                    $file->flock(LOCK_UN);
                    $temp->flock(LOCK_UN);
                    if ($spl_debug) echo 'unset SPL object<br>';
                    unset($file, $temp); // Kill the SPL objects releasing further locks

                    if ($spl_debug) echo 'unlink source restore file: "' . $restore_from . '"<br>';
                    unlink($restore_from);

                    if ($spl_debug) echo 'rename new temp file as source restore file<br>';
                    rename($tempFileName, $restore_from);
                    if ($spl_debug) echo 'renamed file:' . '<br>' . $tempFileName . '<br>to<br>' . $restore_from . '<br>';
                } else {
                    if ($spl_debug) echo 'problem string not found';
                }
                if ($spl_debug) die;
                //eof Maria bug

                //Restore using "mysql"
                $load_params = ' --database=' . DB_DATABASE;
                $load_params .= ' --host=' . DB_SERVER;
                $load_params .= ' --user=' . DB_SERVER_USERNAME;
                $load_params .= ((DB_SERVER_PASSWORD === '') ? '' : ' --password=' . OS_DELIM . DB_SERVER_PASSWORD . OS_DELIM);
                $load_params .= ' ' . DB_DATABASE; // this needs to be the 2nd-last parameter
                $load_params .= ' < ' . OS_DELIM . $restore_from . OS_DELIM; // this needs to be the LAST parameter
                $load_params .= ' 2>&1';

                if ($restore_from !== '' && file_exists($restore_from)) {
                    $toolfilename = !empty($_GET['tool']) ? $_GET['tool'] : $mysql_exe;

                    //set all slashes to forward
                    $toolfilename = str_replace('\\', '/', $toolfilename);
                    if ($debug) {
                        $messageStack->add_session('$toolfilename=' . $toolfilename, 'info');
                    }
                    $restore_from = str_replace('\\', '/', $restore_from);
                    if ($debug) {
                        $messageStack->add_session('$restore_from=' . $restore_from, 'info');
                    }
                    if ($debug) {
                        $messageStack->add_session('Restore COMMAND: ' . $toolfilename . $load_params, 'info');
                    }
                    $resultcodes = exec($toolfilename . $load_params, $output, $load_results);
                    // $output gets filled with an array of all the normally displayed dialogue that comes back from the command
                    // $load_results is an integer of the execution result
                    
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
                    if (!empty($load_results) && $load_results !== 0) {
                        $messageStack->add_session(TEXT_RESULT_CODE . $load_results);
                    }

                    // $output contains response strings from execution. This displays if anything is generated.
                    if (zen_not_null($output)) {
                        foreach ($output as $key => $value) {
                            $messageStack->add_session('console $output:' . "$key => $value<br>");
                        }
                    }

                    if ($load_results === 0) {
                        // Store the last-restore-date, if successful. Update key if it exists rather than delete and insert or the insert increments the id
                        $db->Execute(
                            "INSERT INTO " . TABLE_CONFIGURATION . "
                        (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, date_added)
                        VALUES
                        ('Last Database Restore', 'BACKUP_MYSQL_LAST_RESTORE', '" . $restore_from . "', 'Last database restore file', 6, now())
                        ON DUPLICATE KEY UPDATE configuration_value ='" . $restore_from . "'"
                        );

                        // Was a temp file used from a compressed (.zip, .gz) file?
                        if (file_exists($tempfile_name)) {
                            $restore_file_info = $restore_file . " ($tempfile_name)";
                            if (isset($tempfile) && is_resource($tempfile)) {
                                // close and delete the temp file used for gz
                                fclose($tempfile);
                            } else {
                                unlink($tempfile_name);
                            }
                            $messageStack->add_session(!file_exists($tempfile_name) ? sprintf(TEXT_TEMP_SQL_DELETED, $restore_from) : sprintf(TEXT_TEMP_SQL_NOT_DELETED, $tempfile_name), !file_exists($tempfile_name) ? 'success' : 'error');
                        } else {
                            $restore_file_info = $restore_from;
                        }

                        $messageStack->add_session(sprintf(SUCCESS_DATABASE_RESTORED, $restore_file_info), 'success');

                        // a redirect back after completion does not work
                        //zen_redirect(zen_href_link(FILENAME_BACKUP_MYSQL, ($debug ? 'debug=ON' : '')));

                    } elseif ($load_results == '127') {
                        $messageStack->add_session(FAILURE_DATABASE_NOT_RESTORED_UTIL_NOT_FOUND, 'error');
                    } else {
                        $messageStack->add_session(FAILURE_DATABASE_NOT_RESTORED, 'error');
                    } // endif $load_results
                } else {
                    $messageStack->add_session(sprintf(FAILURE_DATABASE_NOT_RESTORED_FILE_NOT_FOUND, '[' . $restore_from . ']'), 'error');
                } // endif file_exists

            } else {
                $messageStack->add_session(sprintf(FAILURE_DATABASE_NOT_RESTORED_FILE_EXTENSION_INVALID, $restore_file), 'error');
            }// end if the extension type is not allowed
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
                // build an array of the files in the backup directory
                while ($file_gz = $dir->read()) {
                    if (!is_dir(DIR_FS_BACKUP . $file_gz)) {
                        if (!str_starts_with($file_gz, '.') && !in_array($file_gz, ['empty.txt', 'index.php', 'index.htm', 'index.html'])) {
                            $contents[] = $file_gz;
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
                <p><?= TEXT_BACKUP_DIRECTORY . ' ' . DIR_FS_BACKUP ?><br>
                    <?= defined('BACKUP_MYSQL_LAST_RESTORE') ? TEXT_LAST_RESTORATION . ' ' . BACKUP_MYSQL_LAST_RESTORE . ' <a href="' . zen_href_link(FILENAME_BACKUP_MYSQL, 'action=forget') . '">' . TEXT_FORGET . '</a>' : '' ?></p>
                <p>memory limit:<?= ini_get('memory_limit') ?></p>
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

                    $contents[] = [
                        'text' => '<label>' . zen_draw_radio_field('compress', 'no', false) . ' ' . TEXT_INFO_USE_NO_COMPRESSION . '</label>'
                    ];

                    if ($gzip_enabled || function_exists('gzcompressfile')) {
                        $contents[] = [
                            'text' => '<label>' . zen_draw_radio_field('compress', 'gzip', true) . ' ' . TEXT_INFO_USE_GZIP . '</label>'
                        ];
                    }

                    if ($zip_enabled) {
                        $contents[] = [
                            'text' => '<label>' . zen_draw_radio_field('compress', 'zip', false) . ' ' . TEXT_INFO_USE_ZIP . '</label>'
                        ];
                    }

                    $contents[] = [
                        'text' => '<label>' . zen_draw_checkbox_field('skiplocks', 'yes', false) . ' ' . TEXT_INFO_SKIP_LOCKS . '</label>'
                    ];

                    // Download to file --- Should only be done if SSL is active, otherwise the database is exposed as clear text
                    if ($dir_ok) {
                        $contents[] = ['text' => '<label>' . zen_draw_checkbox_field('download', 'yes') . ' ' . TEXT_INFO_DOWNLOAD_ONLY . '</label>'];
                    } else {
                        $contents[] = ['text' => '<label>' . zen_draw_radio_field('download', 'yes', true) . ' ' . TEXT_INFO_DOWNLOAD_ONLY . '</label>'];
                    }
                    if (!$ssl_on) {
                        $contents[] = ['text' => '<span class="errorText">* ' . TEXT_INFO_BEST_THROUGH_HTTPS . ' * </span>'];
                    }
                    // add suffix to the backup filename
                    $contents[] = [
                        'text' => '<label>' . TEXT_ADD_SUFFIX . '</label><br>' . zen_draw_input_field('suffix', '', 'size="31" maxlength="30"')
                    ];

                    // Display Backup and Cancel buttons
                    $contents[] = [
                        'align' => 'center',
                        'text' => '<button type="submit" form="backup" class="btn btn-primary" role="button">' . IMAGE_BACKUP . '</button>' . '&nbsp;&nbsp;' .
                            '<a href="' . zen_href_link(FILENAME_BACKUP_MYSQL, ($debug ? 'debug=ON' : '')) . '" class="btn btn-primary" role="button">' . IMAGE_CANCEL . '</a>'
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
                        'text' => '<a id="restoreNowButton" class="btn btn-primary" role="button" href="' . zen_href_link(FILENAME_BACKUP_MYSQL, 'file=' . $buInfo->file . '&action=restorenow' . ($debug ? '&debug=ON' : '')) . '">' . IMAGE_RESTORE . '</a>' . '&nbsp;&nbsp' .
                            '<a class="btn btn-primary" role="button" href="' . zen_href_link(
                                FILENAME_BACKUP_MYSQL,
                                'file=' . $buInfo->file . ($debug ? '&debug=ON' : '')
                            ) . '">' . IMAGE_CANCEL . '</a>'
                    ];
                    break;

                case 'restorelocal':
                    $heading[] = ['text' => '<strong>' . TEXT_INFO_HEADING_RESTORE_LOCAL . '</strong>'];

                    $contents = ['form' => zen_draw_form('restore', FILENAME_BACKUP_MYSQL, 'action=restorelocalnow' . ($debug ? '&debug=ON' : ''), 'post', 'enctype="multipart/form-data"')];
                    $contents[] = ['text' => sprintf(TEXT_INFO_RESTORE_LOCAL, ($gzip_enabled ? ', .gz' : '') . ($zip_enabled ? ', .zip' : ''))];
                    if (!$ssl_on) {
                        $contents[] = ['text' => '<span class="errorText">* ' . TEXT_INFO_BEST_THROUGH_HTTPS . ' *</span>'];
                    }
                    $contents[] = ['text' => zen_draw_file_field('sql_file')];

                    //Display Restore and Cancel buttons
                    $contents[] = [
                        'align' => 'center',
                        'text' => '<button id="restoreLocalNowButton" type="submit" class="btn btn-primary" role="button">' . IMAGE_RESTORE . '</button>' . '&nbsp;&nbsp;' .
                            '<a class="btn btn-primary" role="button" href="' . zen_href_link(FILENAME_BACKUP_MYSQL, ($debug ? 'debug=ON' : '')) . '">' . IMAGE_CANCEL . '</a>'
                    ];
                    break;

                default:
                    if (isset($buInfo) && is_object($buInfo)) {
                        $heading[] = ['text' => '<strong>' . $buInfo->file . '</strong>'];

                        $contents[] = ['text' => TEXT_INFO_DATE . ' ' . $buInfo->date];
                        $contents[] = ['text' => TEXT_INFO_SIZE . ' ' . $buInfo->size];
                        $contents[] = ['text' => TEXT_INFO_COMPRESSION . ' ' . $buInfo->compression];

                        // Disable restore if compression not supported
                        $disable_restore = false;
                        if ($buInfo->compression === 'GZIP' && !$gzip_enabled) {
                            $contents[] = ['text' => sprintf(TEXT_RESTORE_NO_COMPRESSION_METHOD, $buInfo->compression)];
                            $disable_restore = true;
                        }
                        if ($buInfo->compression === 'ZIP' && !$zip_enabled) {
                            $contents[] = ['text' => sprintf(TEXT_RESTORE_NO_COMPRESSION_METHOD, $buInfo->compression)];
                            $disable_restore = true;
                        }

                        //Display Restore and Delete buttons
                        $contents[] = [
                            'align' => 'center',
                            'text' => '<a class="btn btn-primary" role="button"' . ($disable_restore ? '' : 'href="' . zen_href_link(FILENAME_BACKUP_MYSQL, 'file=' . $buInfo->file . '&action=restore' . ($debug ? '&debug=ON' : '')) . '"') . '>' . IMAGE_RESTORE . '</a>' . '&nbsp;&nbsp;' .
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
<?php
if (defined('BACKUP_MYSQL_SERVER_NAME') && gethostname() === BACKUP_MYSQL_SERVER_NAME) { ?>
    <script>
        $("#restoreLocalNowButton, #restoreNowButton").on("click", function () {
            return confirm('<?= sprintf(TEXT_WARNING_REMOTE_RESTORE, BACKUP_MYSQL_SERVER_NAME); ?>');
        });
    </script>
    <?php
} ?>
</body>
</html>
<?php
require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
