<?php

declare(strict_types=1);
/**
 * part of Backup MySQL plugin
 * @copyright Copyright 2024 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @author Dr.Byte
 * @version $Id: torvista 2024 Aug 12 $
 */

define('FILENAME_BACKUP_MYSQL', 'backup_mysql');
//TODO detect compression support accurately and remove this
// Set this to 'true' if the zip options aren't appearing while doing a backup, and you are certain that gzip support exists on your server
define('COMPRESS_OVERRIDE','false');
//define('COMPRESS_OVERRIDE','true');

// define the locations of the mysql utilities if not found automatically
// Use FORWARD slashes /.
// Typical location is in '/usr/bin/' ... but not on Windows servers.
// try 'c:/mysql/bin/mysql.exe' and 'c:/mysql/bin/mysqldump.exe' on Windows hosts ... change drive letter and path as needed
define('LOCAL_EXE_MYSQL', '/usr/bin/mysql');  // used for restores
define('LOCAL_EXE_MYSQLDUMP', '/usr/bin/mysqldump');  // used for backups
