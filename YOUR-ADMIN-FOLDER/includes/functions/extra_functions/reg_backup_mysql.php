<?php
/**
 * $Id: /admin/includes/functions/extra_functions/reg_backup_mysql.php $
 * DESCRIPTION: Add Backup SQL to Tools Menu
 */

if (function_exists('zen_register_admin_page')) {
    if (!zen_page_key_exists('backup_mysql')) {
        // Add backup_mysql to Tools menu
        zen_register_admin_page('backup_mysql', 'BOX_TOOLS_BACKUP_MYSQL','FILENAME_BACKUP_MYSQL', '', 'tools', 'Y', 17);
    }
}
