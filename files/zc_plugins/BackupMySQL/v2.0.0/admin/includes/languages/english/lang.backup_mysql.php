<?php

declare(strict_types=1);

/**
 * part of the Backup MySQL plugin
 * @copyright Copyright 2024 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @author Dr.Byte
 * @author torvista
 * @version $Id: lang.backup_mysql.php torvista 02 Feb 2025
 */

$define = [
    'ERROR_BACKUP_DIRECTORY_DOES_NOT_EXIST' => 'Error: Backup directory<br>"' . DIR_FS_BACKUP . '"<br>does not exist (slash orientation is not significant).<br>Please check configure.php (or /local/configure.php if used).',
    'ERROR_BACKUP_DIRECTORY_NOT_WRITEABLE' => 'Error: Backup directory is not writeable.',
    'ERROR_CANT_BACKUP_IN_SAFE_MODE' => 'ERROR: This backup script seldom works when safe_mode is enabled or open_basedir restrictions are in effect.<br>If you get no errors doing a backup, check to see whether the file is less than 200kb. If so, then the backup is likely unreliable.',
    'ERROR_DOWNLOAD_LINK_NOT_ACCEPTABLE' => 'Error: Download link not acceptable.',
    'ERROR_EXEC_DISABLED' => 'ERROR: Your server\'s "exec()" command has been disabled. This script cannot run. Ask your host if they are willing to re-enable PHP exec().',
    'ERROR_FILE_NOT_REMOVEABLE' => 'Error: Could not remove the file specified. You may have to use FTP to remove the file, due to a server-permissions configuration limitation.',
    'ERROR_PHP_DISABLED_FUNCTIONS' => 'PHP-Disabled Functions: ',
    'ERROR_RESTORE_FILE_NOT_FOUND' => 'Restore file "%s" not found.',
    'ERROR_RESTORE_FILE_ZIP_NOT_OPENED' => 'Restore file "%s" could not be unzipped.',
    'ERROR_SHELL_EXEC_DISABLED' => 'The script cannot <b>auto-detect</b> the path to mysql as the "shell_exec()" function has been disabled: checking paths hard-coded in script...',
    'FAILURE_BACKUP_FAILED_CHECK_PERMISSIONS' => 'The backup failed because there was an error starting the backup program (mysqldump or mysqldump.exe).<br>If running on Windows Server, you may need to alter permissions on cmd.exe to allow Special Access to the Internet Guest Account to read/execute.<br>You should talk to your webhost about why exec() commands are failing when attempting to run the mysqldump binary/program.',
    'FAILURE_DATABASE_NOT_RESTORED' => 'Failure: The database may NOT have been restored properly. Please check it carefully.',
    'FAILURE_DATABASE_NOT_RESTORED_FILE_EXTENSION_INVALID' => 'File extension is not one of the allowed types (.sql, .gz, .zip).',
    'FAILURE_DATABASE_NOT_RESTORED_FILE_NOT_FOUND' => 'Failure: The database was NOT restored.  ERROR: FILE NOT FOUND: %s',
    'FAILURE_DATABASE_NOT_RESTORED_UTIL_NOT_FOUND' => 'ERROR: Could not locate the MYSQL restore utility. RESTORE FAILED.',
    'FAILURE_DATABASE_NOT_SAVED' => 'Failure: The database has NOT been saved.',
    'FAILURE_DATABASE_NOT_SAVED_UTIL_NOT_FOUND' => 'ERROR: Could not locate the MYSQLDUMP backup utility. BACKUP FAILED.',
    'HEADING_TITLE' => 'MySQL Database Backup/Restore',
    'ICON_FILE_DOWNLOAD' => 'Download',
    'IMAGE_BACKUP' => 'Backup',
    'IMAGE_RESTORE' => 'Restore',
    'SUCCESS_BACKUP_DELETED' => 'Success: The backup has been removed.',
    'SUCCESS_DATABASE_RESTORED' => 'Success: The database has been restored from "%s".',
    'SUCCESS_DATABASE_SAVED' => 'Success: The database has been saved.',
    'SUCCESS_LAST_RESTORE_CLEARED' => 'Success: The last restoration date has been cleared.',
    'TABLE_HEADING_ACTION' => 'Action',
    'TABLE_HEADING_FILE_DATE' => 'Date',
    'TABLE_HEADING_FILE_SIZE' => 'Size',
    'TABLE_HEADING_TITLE' => 'Title',
    'TEXT_ADD_SUFFIX' => 'Add an optional suffix to the filename (ascii characters only):',
    'TEXT_BACKUP_DIRECTORY' => 'Backup Directory:',
    'TEXT_COMMAND_RUN' =>'<br>The command being run is: ',
    'TEXT_DELETE_INTRO' => 'Are you sure you want to delete this backup?',
    'TEXT_EXECUTABLES_FOUND' => 'MySQL tools found:<br>%1$s<br>%2$s',
    'TEXT_EXECUTABLES_NOT_FOUND' => 'MySQL tools (mysql, mysqldump) not found.',
    'TEXT_FORGET' => '(forget)',
    'TEXT_INFO_BEST_THROUGH_HTTPS' => 'This is safer via a secured HTTPS connection!',
    'TEXT_INFO_COMPRESSION' => 'Compression:',
    'TEXT_INFO_DATE' => 'Date:',
    'TEXT_INFO_DOWNLOAD_ONLY' => 'Download without storing on server',
    'TEXT_INFO_HEADING_NEW_BACKUP' => 'New Backup',
    'TEXT_INFO_HEADING_RESTORE_LOCAL' => 'Restore from a Local file',
    'TEXT_INFO_NEW_BACKUP' => 'Do not interrupt the backup process which might take a couple of minutes.',
    'TEXT_INFO_RESTORE' => 'Do not interrupt the restoration process.<br><br>The larger the backup, the longer this process takes!<br><br>If possible, use the mysql client.<br><br>For example:<br><br><b>mysql -h' . DB_SERVER . ' -u' . DB_SERVER_USERNAME . ' -p ' . DB_DATABASE . ' < %s </b> %s',
    'TEXT_INFO_RESTORE_LOCAL' => 'Do not interrupt the restoration process.<br><br>The larger the backup, the longer this process takes!<br><br>The file uploaded must be .sql%s.',
    'TEXT_INFO_SIZE' => 'Size:',
    'TEXT_INFO_SKIP_LOCKS' => 'Skip Lock option (check this if you get a LOCK TABLES permissions error)',
    'TEXT_INFO_UNPACK' => '<br><br>(after unpacking the file from the archive)',
    'TEXT_INFO_USE_GZIP' => 'Use GZIP',
    'TEXT_INFO_USE_NO_COMPRESSION' => 'No Compression (Pure SQL)',
    'TEXT_INFO_USE_ZIP' => 'Use ZIP',
    'TEXT_LAST_RESTORATION' => 'Last Restoration:',
    'TEXT_NO_EXTENSION' => 'None',
    'TEXT_RESULT_CODE' => 'Result code: ',
    'TEXT_RESTORE_NO_COMPRESSION_METHOD' => '<strong>%s</strong> not supported for Restore.',
    'TEXT_TEMP_SQL_DELETED' => 'Temporary file "%s" deleted',
    'TEXT_TEMP_SQL_NOT_DELETED' => 'Temporary file "%s" NOT deleted',
    'TEXT_WARNING_REMOTE_RESTORE' => 'You appear to be restoring to the PRODUCTION site (%s): ARE YOU SURE?',
    'WARNING_NOT_SECURE_FOR_DOWNLOADS' => '<span class="errorText">NOTE: You do not have SSL enabled. Any downloads you do from this page will not be encrypted. Doing backups and restores will be fine, but the download/upload of files from/to the server presents a security risk.',
];

return $define;
