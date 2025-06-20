<?php
/**
 * SQL installation file for Credit Slip Manager module
 */

$sql = array();

// Tabla para registrar cambios de precios
$sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'creditslip_price_change_log` (
    `id_price_change_log` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `id_order_slip` int(10) unsigned NOT NULL,
    `id_order_detail` int(10) unsigned NOT NULL,
    `old_price_excl` decimal(20,6) NOT NULL,
    `new_price_excl` decimal(20,6) NOT NULL,
    `old_price_incl` decimal(20,6) NOT NULL,
    `new_price_incl` decimal(20,6) NOT NULL,
    `date_add` datetime NOT NULL,
    PRIMARY KEY (`id_price_change_log`),
    KEY `id_order_slip` (`id_order_slip`),
    KEY `id_order_detail` (`id_order_detail`)
) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

// Tabla para registro de acciones
$sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'creditslip_action_log` (
    `id_action_log` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `id_order_slip` int(10) unsigned NOT NULL,
    `action` varchar(50) NOT NULL,
    `message` text NOT NULL,
    `date_add` datetime NOT NULL,
    PRIMARY KEY (`id_action_log`),
    KEY `id_order_slip` (`id_order_slip`)
) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}