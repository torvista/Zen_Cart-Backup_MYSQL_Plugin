<?php
/**
 * part of Backup MySQL plugin
 * @copyright Copyright 2024 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @author Dr.Byte
 * @version $Id: torvista 2024 Aug 12 $
 */

use Zencart\PluginSupport\ScriptedInstaller as ScriptedInstallBase;

class ScriptedInstaller extends ScriptedInstallBase
{
    protected function executeInstall(): void
    {
        zen_deregister_admin_pages(['backup_mysql']);//original plugin name
        zen_deregister_admin_pages(['toolsBackupMysql']);
        zen_register_admin_page(
            'toolsBackupMysql', 'BOX_TOOLS_BACKUP_MYSQL', 'FILENAME_BACKUP_MYSQL', '', 'tools', 'Y');

        $this->deleteConfigurationKeys(['DB_LAST_RESTORE']); //DB_LAST_RESTORE was original configuration_key
    }

    protected function executeUninstall(): void
    {
        zen_deregister_admin_pages(['toolsBackupMysql']);
        $this->deleteConfigurationKeys(['DB_LAST_RESTORE', 'BACKUP_MYSQL_LAST_RESTORE', 'BACKUP_MYSQL_LOCATION']); //DB_LAST_RESTORE was original configuration_key
    }
}
