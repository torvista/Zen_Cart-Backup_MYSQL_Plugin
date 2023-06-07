# Zen_Cart-Backup_MYSQL_Plugin

Original Plugin History: https://www.zen-cart.com/downloads.php?do=file&id=7

Currently this repository is identical to the last 1.5g Plugin release.

I intend to include my modifcations here at some point....which are in a (very) modified version here:  
https://github.com/torvista/Zen_Cart-Backup_MySQL_database

Support Thread: https://www.zen-cart.com/showthread.php?35714-Backup-MySQL-Database

NOTE: uses exec() function calls to run mysqldump and mysql binaries. Sometimes exec() is restricted from use in shared-hosting environments. Also, doesn't work in safe mode or on servers where the mysqldump or mysql binaries have restricted access.

Does not work on GoDaddy servers or other servers where the database is hosted on a different physical machine
