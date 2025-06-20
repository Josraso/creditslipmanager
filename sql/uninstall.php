<?php
/**
 * SQL uninstallation file for Credit Slip Manager module
 */

$sql = array();

// Eliminar tablas creadas por el módulo
$sql[] = 'DROP TABLE IF EXISTS `'._DB_PREFIX_.'creditslip_price_change_log`';
$sql[] = 'DROP TABLE IF EXISTS `'._DB_PREFIX_.'creditslip_action_log`';

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}