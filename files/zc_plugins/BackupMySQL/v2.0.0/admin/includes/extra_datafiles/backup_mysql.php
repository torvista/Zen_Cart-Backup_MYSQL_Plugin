<?php

declare(strict_types=1);

/**
 * part of the Backup MySQL plugin
 * @copyright Copyright 2024 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @author Dr.Byte
 * @author torvista
 * @version $Id: backup_mysql.php torvista 12 Feb 2025
 */

// Define your production server name to show a warning if restoring to the production server!
// Use the name shown in the Admin header in front of the timezone
define('BACKUP_MYSQL_SERVER_NAME', '');

// define the locations of the mysql utilities if not found automatically
// Use FORWARD slashes /
// A typical location is in '/usr/bin/' ... but not on Windows servers.
// try 'c:/mysql/bin/' on Windows hosts ... change drive letter and path as needed
// include the trailing slash
define('BACKUP_MYSQL_LOCAL_EXE_PATH', '');      // local/development server
define('BACKUP_MYSQL_PRODUCTION_EXE_PATH', ''); // production server

//Do not edit
define('FILENAME_BACKUP_MYSQL', 'backup_mysql');
