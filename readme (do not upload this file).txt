Add-On: BACKUP_MYSQL Admin Tool 
Designed for: Zen Cart v1.5.x series
Created by: DrByte

Donations:  Please support Zen Cart!  paypal@zen-cart.com  - Thank you!
===========================================================

NOTES:  This add-on was created for a couple reasons:
- much faster than a php-scripted backup tool
- supports backups of "huge" databases

===========================================================

INSTALLATION:  Upload all files from the YOUR-ADMIN-FOLDER folder of this ZIP as-is to your store's "admin" folder (whatever name it is), retaining the subdirectories included here.

===========================================================

USE:  To use, just log into your admin area, click on "Tools", 
and click on "Database Backup-MySQL".

Note that to perform backups, your /admin/backups folder must be 
writable by the webserver userID. This is usually best accomplished 
by CHMOD 777 on the /admin/backups folder, either by shell console 
or by FTP program. CHMOD777 means read/write/execute permissions for everyone.
Some servers don't permit use of 777 (blank screen or 500 error), in which case 755 will be required.

You can also download backup files from this window by clicking on 
the small down-arrow icon to the left of the listed backup files, 
or you may download directly during the backup process by clicking 
on the checkbox.

We hope you enjoy the speed of backups using this plug-in tool!

===========================================================

CAVEATS:  This tool does not work on servers running in strict safe_mode, 
or with open.basedir restrictions in effect, or with restrictions against 
using "exec()" commands. There is no way to work around these limitations 
without turning off the restrictions on your server.  Contact your host 
for more information on such possibilities.
          
===========================================================

ADDENDUM: 

This tool looks for the mysql binary tools "mysql" and "mysqldump" to 
process import/export activities.
If for some reason it's not finding the tools properly, you can edit the
/admin/includes/languages/english/backup_mysql.php file and specify the 
exact path to these utilities for your server, by defining the LOCAL_EXE_MYSQL 
and LOCAL_EXE_MYSQLDUMP settings.

Windows servers may require this.... but some detection is already built-in 
for standard paths on Windows configurations, so give it a try as-is first. 
Otherwise, edit the settings in the language file, and it should be fine.  
(do NOT put a trailing / in the end of the define).

In many cases, Windows servers will prevent the use of exec() commands 
by virtue of the fact that Windows restricts the Internet Guest Account from
being allowed to run cmd.exe. To override this, you would need to alter the
security permissions on cmd.exe and grant the Internet Guest Account read/execute
as "Special Access" permissions.  NOTE: This may be a security risk to your server,
so it's best to consult with your security expert before making such a change.

===========================================================

HISTORY:
Aug 1/04 - Initial Release
Aug 4/04 - Added additional search paths for finding binaries to be executed
Aug 17/04 - Added additional error-checking output for better indication of causes of failures.
Aug 18/04 - Modified script to work on servers where database is hosted remotely.
Sept 25/04 - Added additional search paths for finding binaries, as well as more error-checking.
March 21/05 - Added exclusion for "index.php" in listing of backup archives.
July 20/05 - Set GZIP on by default for new backups, and fixed logic bug on path detection (thanks to masterblaster)
July 21/05 - Tiny update to predeclare some vars for Windows hosts
July 21/05 - Updated to allow option to "skip locks" in case your host has not given you the "LOCK TABLES" permission.
Nov 10/05 - Now accommodates path-names containing spaces (Windows hosts)
Nov 12/05 - Updated to default to typical binary names if none found -- attempted safe-mode workaround
Dec 30/05 - Updated to allow more overrides in compression options, to detect failures due to Win2003 limitations, etc
Jan 10/06 - Small typo fixed related to open_basedir detection
Feb 28/06 - Completed support for individual table export (&tables=xxx,xxxx,xxxx) and added more tweaks for IIS support
Apr 28/07 - Updated contrib to new version number: 1.3 -- auto-handles lock-tables limitations and various bugfixes
Jan 04/08 - Updated to 1.3.5, compression improvements
Jun 2010 - Updated to v1.4 - includes PHP 5.3 fixes and smarter detection of whether exec() is disabled
Dec 9/2011 - Updated to v1.5 - with additional file to register page for 1.5.0
July 3/2012 - Updated to better detect and avoid display of files that aren't related to backups
June 12/2015 - Updated avoid inserting null value for integer field when restoring data.
Dec 2018 - Dropped support for ZC v1.3.x (it still works, but the menu option doesn't get installed)
June 2020 - Fixed Warning: "continue" targeting switch is equivalent to "break". Did you mean to use "continue 2"? in backup_mysql.php on line 518 $mprough
July 2020 - Fixed warnings about undefined constants. Fixed undefined offset during restores.
