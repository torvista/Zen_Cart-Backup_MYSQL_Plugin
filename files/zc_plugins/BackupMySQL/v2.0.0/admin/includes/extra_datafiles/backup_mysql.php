<?php

declare(strict_types=1);

/**
 * part of the Backup MySQL plugin
 * @copyright Copyright 2024 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @author Dr.Byte
 * @author torvista
 * @version $Id: torvista 2025-01-17
 */

define('FILENAME_BACKUP_MYSQL', 'backup_mysql');

// optional: define your production server name to prevent accidental restores!
// Use the name shown in the Admin header in front of the timezone
//define('BACKUP_MYSQL_SERVER_NAME', 'YOUR_SERVER_NAME');

// define the locations of the mysql utilities if not found automatically
// Use FORWARD slashes /.
// Typical location is in '/usr/bin/' ... but not on Windows servers.
// try 'c:/mysql/bin/mysql.exe' and 'c:/mysql/bin/mysqldump.exe' on Windows hosts ... change drive letter and path as needed
//define('LOCAL_EXE_MYSQL', '/usr/bin/mysql');  // used for restores
//define('LOCAL_EXE_MYSQLDUMP', '/usr/bin/mysqldump');  // used for backups
