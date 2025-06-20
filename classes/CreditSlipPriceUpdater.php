<?php
/**
 * Clase para gestionar la actualización de precios en credit slips
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class CreditSlipPriceUpdater
{
    /**
     * Actualiza los precios de un credit slip específico
     */
    public static function updatePrices($idOrderSlip)
    {
        $orderSlip = new OrderSlip($idOrderSlip);
        if (!Validate::isLoadedObject($orderSlip)) {
            return false;
        }
        
        $order = new Order($orderSlip->id_order);
        if (!Validate::isLoadedObject($order)) {
            return false;
        }
        
        // Comenzar transacción
        Db::getInstance()->execute('START TRANSACTION');
        
        try {
            // Obtener detalles de order slip directamente con SQL
            $orderSlipDetails = Db::getInstance()->executeS('
                SELECT * FROM `'._DB_PREFIX_.'order_slip_detail` 
                WHERE `id_order_slip` = '.(int)$orderSlip->id
            );
            
            $updated = false;
            
            foreach ($orderSlipDetails as $detail) {
                $orderDetail = new OrderDetail($detail['id_order_detail']);
                $product = new Product($orderDetail->product_id);
                
                // Obtener precio actual
                $currentPrice = $product->getPrice(
                    false, // Sin impuestos
                    $orderDetail->product_attribute_id,
                    6,
                    null,
                    false,
                    true,
                    1
                );
                
                // Calcular el precio con impuestos
                $taxRate = 1;
                if ($orderDetail->id_tax_rules_group > 0) {
                    $address = new Address($order->id_address_invoice);
                    $taxManager = TaxManagerFactory::getManager($address, $orderDetail->id_tax_rules_group);
                    $taxCalculator = $taxManager->getTaxCalculator();
                    $taxRate = 1 + ($taxCalculator->getTotalRate() / 100);
                }
                
                $currentPriceWithTax = $currentPrice * $taxRate;
                
                // Actualizar precio en order_slip_detail
                Db::getInstance()->execute(
                    'UPDATE `'._DB_PREFIX_.'order_slip_detail` 
                    SET `unit_price_tax_excl` = '.(float)$currentPrice.', 
                        `unit_price_tax_incl` = '.(float)$currentPriceWithTax.' 
                    WHERE `id_order_slip` = '.(int)$orderSlip->id.' 
                    AND `id_order_detail` = '.(int)$detail['id_order_detail']
                );
                
                $updated = true;
                
                // Registrar cambio de precio
                if (Configuration::get('CREDITSLIP_LOG_PRICE_CHANGES') &&
                    ($currentPrice != $detail['unit_price_tax_excl'] || 
                     $currentPriceWithTax != $detail['unit_price_tax_incl'])) {
                    self::logPriceChange(
                        $orderSlip->id,
                        $detail['id_order_detail'],
                        $detail['unit_price_tax_excl'],
                        $currentPrice,
                        $detail['unit_price_tax_incl'],
                        $currentPriceWithTax
                    );
                }
            }
            
            if ($updated) {
                // Recalcular totales
                self::recalculateOrderSlipTotals($orderSlip->id);
            }
            
            // Confirmar transacción
            Db::getInstance()->execute('COMMIT');
            
            // Registrar actualización exitosa
            self::logAction($orderSlip->id, 'update', 'Prices updated successfully');
            
            return true;
        } catch (Exception $e) {
            // Revertir en caso de error
            Db::getInstance()->execute('ROLLBACK');
            
            // Registrar error
            self::logAction(
                $orderSlip->id,
                'error',
                'Error updating prices: ' . $e->getMessage()
            );
            
            return false;
        }
    }
    
    /**
     * Recalcula los totales del credit slip
     */
    public static function recalculateOrderSlipTotals($idOrderSlip)
    {
        $result = Db::getInstance()->executeS(
            'SELECT SUM(unit_price_tax_excl * product_quantity) as total_products_tax_excl,
                    SUM(unit_price_tax_incl * product_quantity) as total_products_tax_incl
            FROM `'._DB_PREFIX_.'order_slip_detail`
            WHERE `id_order_slip` = '.(int)$idOrderSlip
        );
        
        if (!$result || !isset($result[0])) {
            return false;
        }
        
        $totalProductsTaxExcl = (float)$result[0]['total_products_tax_excl'];
        $totalProductsTaxIncl = (float)$result[0]['total_products_tax_incl'];
        $totalTax = $totalProductsTaxIncl - $totalProductsTaxExcl;
        
        return Db::getInstance()->execute(
            'UPDATE `'._DB_PREFIX_.'order_slip`
            SET `total_products_tax_excl` = '.(float)$totalProductsTaxExcl.',
                `total_products_tax_incl` = '.(float)$totalProductsTaxIncl.',
                `amount` = '.(float)$totalProductsTaxExcl.',
                `amount_tax_incl` = '.(float)$totalProductsTaxIncl.',
                `total_tax` = '.(float)$totalTax.'
            WHERE `id_order_slip` = '.(int)$idOrderSlip
        );
    }
    
    /**
     * Registra un cambio de precio
     */
    public static function logPriceChange($idOrderSlip, $idOrderDetail, $oldPriceExcl, $newPriceExcl, $oldPriceIncl, $newPriceIncl)
    {
        return Db::getInstance()->execute(
            'INSERT INTO `'._DB_PREFIX_.'creditslip_price_change_log`
            (`id_order_slip`, `id_order_detail`, `old_price_excl`, `new_price_excl`, 
             `old_price_incl`, `new_price_incl`, `date_add`)
            VALUES
            ('.(int)$idOrderSlip.', '.(int)$idOrderDetail.', '.(float)$oldPriceExcl.', 
             '.(float)$newPriceExcl.', '.(float)$oldPriceIncl.', '.(float)$newPriceIncl.', 
             NOW())'
        );
    }
    
    /**
     * Registra una acción
     */
    public static function logAction($idOrderSlip, $action, $message)
    {
        return Db::getInstance()->execute(
            'INSERT INTO `'._DB_PREFIX_.'creditslip_action_log`
            (`id_order_slip`, `action`, `message`, `date_add`)
            VALUES
            ('.(int)$idOrderSlip.', "'.pSQL($action).'", "'.pSQL($message).'", NOW())'
        );
    }
}