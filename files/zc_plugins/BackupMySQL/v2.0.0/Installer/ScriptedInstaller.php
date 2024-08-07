<?php
/**
 * part of Backup MySQL plugin
 * @copyright Copyright 2024 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: torvista 2024 Aug 07 $
 */

use Zencart\PluginSupport\ScriptedInstaller as ScriptedInstallBase;

class ScriptedInstaller extends ScriptedInstallBase
{
    protected function executeInstall()
    {
        zen_deregister_admin_pages(['backup_mysql']);//original plugin name
        zen_deregister_admin_pages(['toolsBackupMysql']);
        zen_register_admin_page(
            'toolsBackupMysql', 'BOX_TOOLS_BACKUP_MYSQL', 'FILENAME_BACKUP_MYSQL', '', 'tools', 'Y');
    }

    protected function executeUninstall()
    {
        zen_deregister_admin_pages(['toolsBackupMysql']);
    }
}
