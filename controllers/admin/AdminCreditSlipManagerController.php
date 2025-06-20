<?php
/**
 * Controlador de administración para Credit Slip Manager
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminCreditSlipManagerController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'order_slip';
        $this->className = 'OrderSlip';
        $this->lang = false;
        $this->addRowAction('view');
        $this->addRowAction('updatePrices');
        $this->explicitSelect = true;
        $this->allow_export = true;
        
        parent::__construct();
        
        // Definir campos para lista
        $this->fields_list = [
            'id_order_slip' => [
                'title' => $this->trans('ID', [], 'Admin.Global'),
                'align' => 'text-center',
                'class' => 'fixed-width-xs'
            ],
            'id_order' => [
                'title' => $this->trans('Order ID', [], 'Admin.Global'),
                'align' => 'text-center',
                'class' => 'fixed-width-sm'
            ],
            'date_add' => [
                'title' => $this->trans('Date issued', [], 'Admin.Global'),
                'type' => 'datetime'
            ],
            'total_products_tax_excl' => [
                'title' => $this->trans('Product amount (tax excl.)', [], 'Admin.Global'),
                'type' => 'price',
                'align' => 'text-right'
            ],
            'total_products_tax_incl' => [
                'title' => $this->trans('Product amount (tax incl.)', [], 'Admin.Global'),
                'type' => 'price',
                'align' => 'text-right'
            ],
            'updated_prices' => [
                'title' => $this->trans('Updated Prices', [], 'Modules.Creditslipmanager.Admin'),
                'align' => 'text-center',
                'type' => 'bool',
                'orderby' => false,
                'callback' => 'getUpdatedPricesStatus'
            ]
        ];
        
        // Agregar filtros y acciones
        $this->bulk_actions = [
            'updatePrices' => [
                'text' => $this->trans('Update prices', [], 'Modules.Creditslipmanager.Admin'),
                'icon' => 'icon-refresh',
                'confirm' => $this->trans('Are you sure you want to update prices for the selected credit slips?', [], 'Modules.Creditslipmanager.Admin')
            ],
        ];
        
        // Consulta SQL para determinar si los precios se han actualizado
        $this->_select = 'a.id_order_slip, a.id_order, a.date_add, a.total_products_tax_excl, a.total_products_tax_incl, 
            EXISTS(SELECT 1 FROM `' . _DB_PREFIX_ . 'creditslip_price_change_log` cl WHERE cl.id_order_slip = a.id_order_slip) as updated_prices';
    }
    
    /**
     * Determina si los precios del credit slip han sido actualizados
     */
    public function getUpdatedPricesStatus($value, $row)
    {
        if ($value) {
            return '<span class="badge badge-success">' . $this->trans('Yes', [], 'Admin.Global') . '</span>';
        }
        return '<span class="badge badge-danger">' . $this->trans('No', [], 'Admin.Global') . '</span>';
    }
    
    /**
     * Procesa la acción de actualizar precios
     */
    public function postProcess()
    {
        // Si es una solicitud para guardar precios editados manualmente
        if (Tools::isSubmit('submitUpdateSlipPrices')) {
            return $this->processSaveManualPrices();
        }
        
        return parent::postProcess();
    }
    
    /**
     * Guarda los precios editados manualmente
     */
    protected function processSaveManualPrices()
    {
        $idOrderSlip = (int)Tools::getValue('id_order_slip');
        if (!$idOrderSlip) {
            $this->errors[] = $this->trans('Invalid credit slip ID', [], 'Modules.Creditslipmanager.Admin');
            return false;
        }
        
        $orderSlip = new OrderSlip($idOrderSlip);
        if (!Validate::isLoadedObject($orderSlip)) {
            $this->errors[] = $this->trans('The credit slip was not found', [], 'Modules.Creditslipmanager.Admin');
            return false;
        }
        
        // Obtener detalles del slip
        $detailsData = Db::getInstance()->executeS('
            SELECT id_order_detail FROM `'._DB_PREFIX_.'order_slip_detail` 
            WHERE `id_order_slip` = '.(int)$idOrderSlip
        );
        
        if (!$detailsData) {
            $this->errors[] = $this->trans('No details found for this credit slip', [], 'Modules.Creditslipmanager.Admin');
            return false;
        }
        
        // Comenzar transacción
        Db::getInstance()->execute('START TRANSACTION');
        
        try {
            $updated = false;
            $totals = [
                'tax_excl' => 0,
                'tax_incl' => 0
            ];
            
            // Actualizar cada detalle
            foreach ($detailsData as $detail) {
                $detailId = (int)$detail['id_order_detail'];
                $priceExcl = (float)Tools::getValue('price_excl_' . $detailId);
                $priceIncl = (float)Tools::getValue('price_incl_' . $detailId);
                $quantity = (int)Tools::getValue('quantity_' . $detailId);
                
                if ($priceExcl <= 0 || $priceIncl <= 0 || $quantity <= 0) {
                    $this->errors[] = $this->trans('Invalid price or quantity for detail %d', [$detailId], 'Modules.Creditslipmanager.Admin');
                    Db::getInstance()->execute('ROLLBACK');
                    return false;
                }
                
                // Obtener precio actual para saber si cambió
                $currentDetail = Db::getInstance()->getRow('
                    SELECT * FROM `'._DB_PREFIX_.'order_slip_detail` 
                    WHERE `id_order_slip` = '.(int)$idOrderSlip.' 
                    AND `id_order_detail` = '.(int)$detailId
                );
                
                // Actualizar precios y cantidad
                Db::getInstance()->execute('
                    UPDATE `'._DB_PREFIX_.'order_slip_detail` 
                    SET `unit_price_tax_excl` = '.(float)$priceExcl.', 
                        `unit_price_tax_incl` = '.(float)$priceIncl.',
                        `product_quantity` = '.(int)$quantity.'
                    WHERE `id_order_slip` = '.(int)$idOrderSlip.' 
                    AND `id_order_detail` = '.(int)$detailId
                );
                
                // Sumar al total
                $totals['tax_excl'] += $priceExcl * $quantity;
                $totals['tax_incl'] += $priceIncl * $quantity;
                $updated = true;
                
                // Registrar cambio de precio si es diferente
                if (Configuration::get('CREDITSLIP_LOG_PRICE_CHANGES') && 
                    ($priceExcl != $currentDetail['unit_price_tax_excl'] || 
                     $priceIncl != $currentDetail['unit_price_tax_incl'])) {
                    
                    require_once(_PS_MODULE_DIR_.'creditslipmanager/classes/CreditSlipPriceUpdater.php');
                    CreditSlipPriceUpdater::logPriceChange(
                        $idOrderSlip,
                        $detailId,
                        $currentDetail['unit_price_tax_excl'],
                        $priceExcl,
                        $currentDetail['unit_price_tax_incl'],
                        $priceIncl
                    );
                    
                    CreditSlipPriceUpdater::logAction(
                        $idOrderSlip,
                        'manual_update',
                        'Prices updated manually by user ' . $this->context->employee->firstname . ' ' . $this->context->employee->lastname
                    );
                }
            }
            
            if ($updated) {
                // Actualizar totales en el slip
                $totalTax = $totals['tax_incl'] - $totals['tax_excl'];
                
                Db::getInstance()->execute('
                    UPDATE `'._DB_PREFIX_.'order_slip`
                    SET `total_products_tax_excl` = '.(float)$totals['tax_excl'].',
                        `total_products_tax_incl` = '.(float)$totals['tax_incl'].',
                        `amount` = '.(float)$totals['tax_excl'].',
                        `amount_tax_incl` = '.(float)$totals['tax_incl'].',
                        `total_tax` = '.(float)$totalTax.'
                    WHERE `id_order_slip` = '.(int)$idOrderSlip
                );
            }
            
            // Confirmar la transacción
            Db::getInstance()->execute('COMMIT');
            
            $this->confirmations[] = $this->trans('Prices updated successfully', [], 'Modules.Creditslipmanager.Admin');
            
            // Redirigir a la vista de detalles
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminCreditSlipManager') . '&vieworder_slip&id_order_slip=' . $idOrderSlip);
            
            return true;
        } catch (Exception $e) {
            // Revertir en caso de error
            Db::getInstance()->execute('ROLLBACK');
            $this->errors[] = $this->trans('Error updating prices: %s', [$e->getMessage()], 'Modules.Creditslipmanager.Admin');
            return false;
        }
    }
    
    /**
     * Renderiza el formulario de visualización
     */
    public function renderView()
    {
        $idOrderSlip = (int)Tools::getValue('id_order_slip');
        $orderSlip = new OrderSlip($idOrderSlip);
        
        if (!Validate::isLoadedObject($orderSlip)) {
            $this->errors[] = $this->trans('The credit slip was not found', [], 'Modules.Creditslipmanager.Admin');
            return parent::renderView();
        }
        
        $order = new Order($orderSlip->id_order);
        $customer = new Customer($order->id_customer);
        
        // Consulta corregida: obtener detalles del slip con nombre de producto
        $orderSlipDetails = Db::getInstance()->executeS('
            SELECT osd.*, od.product_name 
            FROM `'._DB_PREFIX_.'order_slip_detail` osd
            LEFT JOIN `'._DB_PREFIX_.'order_detail` od ON osd.id_order_detail = od.id_order_detail
            WHERE osd.`id_order_slip` = '.(int)$orderSlip->id
        );
        
        // Obtener historial de cambios de precios
        $priceChanges = Db::getInstance()->executeS('
            SELECT * FROM `'._DB_PREFIX_.'creditslip_price_change_log` 
            WHERE `id_order_slip` = '.(int)$idOrderSlip.'
            ORDER BY date_add DESC
        ');
        
        // Obtener historial de acciones
        $actionLogs = Db::getInstance()->executeS('
            SELECT * FROM `'._DB_PREFIX_.'creditslip_action_log` 
            WHERE `id_order_slip` = '.(int)$idOrderSlip.'
            ORDER BY date_add DESC
        ');
        
        $this->context->smarty->assign([
            'order_slip' => $orderSlip,
            'order' => $order,
            'customer' => $customer,
            'order_slip_details' => $orderSlipDetails,
            'price_changes' => $priceChanges,
            'action_logs' => $actionLogs,
            'use_current_prices' => Configuration::get('CREDITSLIP_USE_CURRENT_PRICES'),
            'back_url' => $this->context->link->getAdminLink('AdminCreditSlipManager'),
            'current_url' => $this->context->link->getAdminLink('AdminCreditSlipManager') . '&vieworder_slip&id_order_slip=' . $idOrderSlip,
            'token' => $this->token
        ]);
        
        // Asegurar que la plantilla existe
        $tplFile = _PS_MODULE_DIR_ . 'creditslipmanager/views/templates/admin/view.tpl';
        if (file_exists($tplFile)) {
            return $this->context->smarty->fetch($tplFile);
        } else {
            $this->errors[] = $this->trans('Template file not found', [], 'Modules.Creditslipmanager.Admin');
            return parent::renderView();
        }
    }
    
    /**
     * Procesa la acción de actualizar precios
     */
    public function processUpdatePrices()
    {
        $idOrderSlip = (int)Tools::getValue('id_order_slip');
        
        if (!$idOrderSlip) {
            $this->errors[] = $this->trans('Invalid credit slip ID', [], 'Modules.Creditslipmanager.Admin');
            return false;
        }
        
        require_once(_PS_MODULE_DIR_.'creditslipmanager/classes/CreditSlipPriceUpdater.php');
        
        if (CreditSlipPriceUpdater::updatePrices($idOrderSlip)) {
            $this->confirmations[] = $this->trans('Prices updated successfully', [], 'Modules.Creditslipmanager.Admin');
        } else {
            $this->errors[] = $this->trans('Failed to update prices', [], 'Modules.Creditslipmanager.Admin');
        }
        
        return true;
    }
    
    /**
     * Procesa la acción masiva de actualizar precios
     */
    public function processBulkUpdatePrices()
    {
        if (is_array($this->boxes) && !empty($this->boxes)) {
            $success = true;
            $updatedCount = 0;
            
            require_once(_PS_MODULE_DIR_.'creditslipmanager/classes/CreditSlipPriceUpdater.php');
            
            foreach ($this->boxes as $id) {
                if (CreditSlipPriceUpdater::updatePrices((int)$id)) {
                    $updatedCount++;
                } else {
                    $success = false;
                }
            }
            
            if ($success) {
                $this->confirmations[] = $this->trans('Successfully updated prices for %d credit slip(s)', [$updatedCount], 'Modules.Creditslipmanager.Admin');
            } else {
                if ($updatedCount > 0) {
                    $this->confirmations[] = $this->trans('Successfully updated prices for %d credit slip(s)', [$updatedCount], 'Modules.Creditslipmanager.Admin');
                }
                $this->errors[] = $this->trans('Some credit slips could not be updated', [], 'Modules.Creditslipmanager.Admin');
            }
        } else {
            $this->errors[] = $this->trans('You must select at least one item to perform this action', [], 'Admin.Notifications.Error');
        }
        
        return true;
    }
}