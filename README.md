# Zen Cart - Backup MYSQL Plugin
This add-on works much faster than a php-scripted backup tool and supports backups of "huge" databases.

Created by: DrByte  
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

## Installation
1. Copy all the files from */files/zc_plugins/* to your development site for testing before copying to your production server.
2. Admin->Modules->Plugin Manager->Select the plugin and Install it.

Note that to perform backups, your */ADMIN_FOLDER/backups* folder must be 
writable by the webserver userID. This is usually best accomplished 
by CHMOD 777 on the */ADMIN_FOLDER/backups* folder, either by shell console 
or by FTP program. CHMOD777 means read/write/execute permissions for everyone.
Some servers don't permit use of 777 (blank screen or 500 error), in which case 755 will be required.

## Use
Admin->Tools->MySQL Database Backup-Restore

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

## Info
This tool looks for the mysql binary tools "mysql" and "mysqldump" to
perform imports/exports.
If for some reason it's not finding the tools properly, you can edit the file  
/admin/includes/extra_datafiles/backup_mysql.php  
and specify the correct path to these utilities for your server, by defining the LOCAL_EXE_MYSQL and LOCAL_EXE_MYSQLDUMP constants.

Windows servers may require this.... but some detection is already built-in for standard paths on Windows configurations, so give it a try as-is first.

In many cases, Windows servers will prevent the use of exec() commands by virtue of the fact that Windows restricts the Internet Guest Account from being allowed to run cmd.exe.  
To override this, you would need to alter the security permissions on cmd.exe and grant the Internet Guest Account read/execute as "Special Access" permissions.  
NOTE: This may be a security risk to your server, so it's best to consult with your security expert before making such a change.

## TODO
1. Sort out parsing of possible tool locations.
1. Fix detection and use of compression options.
admin\includes\extra_datafiles\backup_mysql.php  
Review this  

		// Set this to true if the zip options aren't appearing while doing a backup, and you are certain that gzip support exists on your server  
		define('COMPRESS_OVERRIDE',false);  
		//define('COMPRESS_OVERRIDE',true);  
1. Fix delimiters for Windows/Unix with extended Ascii characters in passwords.
1. Allow adding a suffix to the filename to identify specific backups.
1. Show SSL warnings only when necessary
1. Add stuff I've done in my own version that I don't remember.

## Changelog
2024 08 torvista  
formatting, use short echo tags, remove unused td of download icon, remove unused row hover effect, remove br tags from infobox, add th tags to table, simplifications (use str_starts_with, str_ends with etc., use of $dir_ok, $exec_disabled, unnecessary clauses), disable check for return value of zen_remove, use CSS buttons, use Kb for size, simplify use of $debug

Converted to encapsulated plugin.  
move Last Restored and buttons outside file list table  
remove/replace obsolete html4 tags, br / to br, replaced nested table structure with divs, use null coalesce, short array syntax  
use admin html_head  
Moved tool locations defines to extra_datafiles.  
Converted language file to lang. format, alpha-sort constants

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
