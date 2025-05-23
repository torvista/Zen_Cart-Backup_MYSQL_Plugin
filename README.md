# Zen Cart - Backup MYSQL Encapsulated Plugin v2.0.0

*February 2025: This fileset is now very different to the last version in the Zen Cart Plugins.*

## Functionality
Backup/Restore your database from the Zen Cart Admin.  
Use for backup security or for fast and easy restores to your development server.

Compatible with Zen Cart 2.1+ and php 8+.

This add-on works much faster than a php-scripted backup tool and supports backups of "huge" databases. Supports zip and gzip in Unix and Windows servers.

Originally created by: DrByte  
Donations:  Please support Zen Cart!  paypal@zen-cart.com  - Thank you!

Plugin History: https://www.zen-cart.com/downloads.php?do=file&id=7  
Support Thread: https://www.zen-cart.com/showthread.php?35714-Backup-MySQL-Database

## Restrictions
Uses exec() function calls to run mysqldump and mysql binaries.  
Sometimes exec() is restricted from use in shared-hosting environments.

Does not work in strict safe mode or with open.basedir restrictions in effect, or on servers where the mysqldump or mysql binaries have restricted access.

There is no way to work around these limitations 
without turning off the restrictions on your server.  Contact your host 
for more information on such possibilities.

Does not work on GoDaddy servers or other servers where the database is hosted on a different physical machine

This tool looks for the mysql binary tools "mysql" and "mysqldump" to perform imports/exports.

There is debugging output available by adding &debug=on to the url.

In many cases, Windows servers will prevent the use of exec() commands by virtue of the fact that Windows restricts the Internet Guest Account from being allowed to run cmd.exe.  
To override this, you would need to alter the security permissions on cmd.exe and grant the Internet Guest Account read/execute as "Special Access" permissions.  
NOTE: This may be a security risk to your server, so it's best to consult with your security expert before making such a change.

## Installation
1. Copy all the files from */files/zc_plugins/* to your development site for testing before copying to your production server.
1. Admin->Modules->Plugin Manager->Select the plugin and Install it.  
Note that to perform backups, your */ADMIN_FOLDER/backups* folder must be 
writable by the webserver userID.  
This is usually best accomplished 
by CHMOD 777 on the */ADMIN_FOLDER/backups* folder, either by shell console 
or by FTP program. CHMOD777 means read/write/execute permissions for everyone.
Some servers don't permit use of 777 (blank screen or 500 error), in which case 755 will be required.


## Use
Admin->Tools->MySQL Database Backup-Restore

When you open the page the script will attempt to locate the mysql executables used for backups and restores.  
If it finds them, it will save that location in the database for future use.  
If it does not find them, you will need to add your specific path in  

/admin/includes/extra_datafiles/backup_mysql.php  

		define('BACKUP_MYSQL_LOCAL_EXE_PATH', '');  
		define('BACKUP_MYSQL_PRODUCTION_EXE_PATH', '');  

If you continue to have problems, you may add &debug=on to the url to see debugging information.

### Backup
You may download a backup file directly on creation (not saving it on the server) by selecting the appropriate checkbox prior to the backup.

You can also download previous backup files from the list by clicking on the small down-arrow icon to the left of the file name.  
However this will most likely require an edit to the */ADMIN_FOLDER/backups/.htaccess* file to allow this.

Add this to the end of the .htaccess file:

	# Backup MySQL: allow specific backup files:
	<FilesMatch "(?i).*\.(gz?|sql|zip)$">
	  <IfModule mod_authz_core.c>
		Require all granted
	</IfModule>
	<IfModule !mod_authz_core.c>
		Order Allow,Deny
		Allow from all
	</IfModule>
	</FilesMatch>

The availability of Gzip/Zip compression depends on your server configuration, but should be automatically detected.

### Restore

1. There is an optional define you may edit in  
/admin/includes/extra_datafiles/backup_mysql.php  

		define('BACKUP_MYSQL_SERVER_NAME', 'YOUR_SERVER_NAME');  
in which you may put the name of your production server  (use the name that is shown in the Admin header in front of the timezone).  
This will allow a popup warning if you try to restore to that production server instead of your local development server. It won't stop it, it's just to give you pause for thought!

## Why is this plugin not included with Zen Cart?
https://github.com/zencart/zencart/issues/3050

## Changelog
See commit history for subsequent modifcations.

2025 02 torvista: handle Maria compatibility problem

2024 08 torvista: incorporation of custom code from years of endless fettling.

Features:  
* converted to an encapsulated plugin  
* auto-finds the mysql executables (stores result in db so does not run auto-find every time).
* allow a suffix to be added to the backup filename  
* allow gzip and zip compression for backup and restore
* use temporary files for extractions/compression
* handle a MariaDB incompatibility bug with some dump files

misc:  
move Last Restored and buttons outside file list table, remove/replace obsolete html4 tags, br / to br, replaced nested table structure with divs, use null coalesce, short array syntax, use admin html_head, 
moved tool locations defines to extra_datafiles, converted language file to lang. format, alpha-sort constants, formatting, use short echo tags, remove unused td of download icon, remove unused row hover effect, remove br tags from infobox, add th tags to table, simplifications (use str_starts_with, str_ends with etc.., use of $dir_ok, $exec_disabled, unnecessary clauses), disable check for return value of zen_remove, use CSS buttons, use Kb for size, simplify use of $debug, strict comparisons, SSL warnings, use constant BACKUP_MYSQL_LAST_RESTORED, make $debug parameter persist on all buttons, allow for different quote styles in Windows/Unix (fixes problem with some passwords), endless debugging output.

July 2020 - Fixed warnings about undefined constants. Fixed undefined offset during restores.  
June 2020 - Fixed Warning: "continue" targeting switch is equivalent to "break". Did you mean to use "continue 2"? in backup_mysql.php on line 518 $mprough  
Dec 2018 - Dropped support for ZC v1.3.x (it still works, but the menu option doesn't get installed)  
June 12/2015 - Updated avoid inserting null value for integer field when restoring data.  
July 3/2012 - Updated to better detect and avoid display of files that aren't related to backups  
Dec 9/2011 - Updated to v1.5 - with additional file to register page for 1.5.0  
Jun 2010 - Updated to v1.4 - includes PHP 5.3 fixes and smarter detection of whether exec() is disabled  
Jan 04/08 - Updated to 1.3.5, compression improvements  
Apr 28/07 - Updated contrib to new version number: 1.3 -- auto-handles lock-tables limitations and various bugfixes  
Feb 28/06 - Completed support for individual table export (&tables=xxx,xxxx,xxxx) and added more tweaks for IIS support  
Jan 10/06 - Small typo fixed related to open_basedir detection  
Dec 30/05 - Updated to allow more overrides in compression options, to detect failures due to Win2003 limitations, etc  
Nov 12/05 - Updated to default to typical binary names if none found -- attempted safe-mode workaround  
Nov 10/05 - Now accommodates path-names containing spaces (Windows hosts)  
July 21/05 - Updated to allow option to "skip locks" in case your host has not given you the "LOCK TABLES" permission.  
July 21/05 - Tiny update to predeclare some vars for Windows hosts  
July 20/05 - Set GZIP on by default for new backups, and fixed logic bug on path detection (thanks to masterblaster)  
March 21/05 - Added exclusion for "index.php" in listing of backup archives.  
Sept 25/04 - Added additional search paths for finding binaries, as well as more error-checking.  
Aug 18/04 - Modified script to work on servers where database is hosted remotely.  
Aug 17/04 - Added additional error-checking output for better indication of causes of failures.  
Aug 4/04 - Added additional search paths for finding binaries to be executed  
Aug 1/04 - Initial Release
