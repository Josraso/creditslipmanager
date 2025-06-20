<?php
/**
 * Credit Slip Manager Module
 *
 * @author    José Ramón Soria
 * @copyright Copyright © 2023
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class CreditSlipManager extends Module
{
    public function __construct()
    {
        $this->name = 'creditslipmanager';
        $this->tab = 'administration';
        $this->version = '1.2.0';
        $this->author = 'José Ramón Soria';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7.0.0',
            'max' => _PS_VERSION_,
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Credit Slip Manager');
        $this->description = $this->l('Advanced management for credit slips and product returns');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    public function install()
    {
        include(dirname(__FILE__).'/sql/install.php');
        
        // Registrar el controlador de administración
        if (!$this->installTab()) {
            return false;
        }
        
        return parent::install() &&
            $this->registerHook('actionOrderSlipAdd') &&
            $this->registerHook('displayAdminOrderTabContent') &&
            $this->registerHook('displayAdminOrderTabLink') &&
            $this->installConfiguration();
    }

    public function uninstall()
    {
        include(dirname(__FILE__).'/sql/uninstall.php');
        
        // Desinstalar el tab de administración
        if (!$this->uninstallTab()) {
            return false;
        }
        
        return parent::uninstall() &&
            $this->uninstallConfiguration();
    }
    
    /**
     * Instalar el tab/controlador de administración
     */
    public function installTab()
    {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminCreditSlipManager';
        $tab->name = array();
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'Credit Slip Manager';
        }
        $tab->id_parent = (int)Tab::getIdFromClassName('AdminParentOrders');
        $tab->module = $this->name;
        return $tab->add();
    }

    /**
     * Desinstalar el tab/controlador de administración
     */
    public function uninstallTab()
    {
        $id_tab = (int)Tab::getIdFromClassName('AdminCreditSlipManager');
        if ($id_tab) {
            $tab = new Tab($id_tab);
            return $tab->delete();
        }
        return true;
    }

    private function installConfiguration()
    {
        // Configuración para determinar qué precio usar en devoluciones
        Configuration::updateValue('CREDITSLIP_USE_CURRENT_PRICES', false);
        Configuration::updateValue('CREDITSLIP_LOG_PRICE_CHANGES', true);
        return true;
    }

    private function uninstallConfiguration()
    {
        Configuration::deleteByName('CREDITSLIP_USE_CURRENT_PRICES');
        Configuration::deleteByName('CREDITSLIP_LOG_PRICE_CHANGES');
        return true;
    }

    public function getContent()
    {
        $output = '';
        
        if (Tools::isSubmit('submit'.$this->name)) {
            // Procesar la configuración
            $useCurrentPrices = (bool)Tools::getValue('CREDITSLIP_USE_CURRENT_PRICES');
            $logPriceChanges = (bool)Tools::getValue('CREDITSLIP_LOG_PRICE_CHANGES');
            
            Configuration::updateValue('CREDITSLIP_USE_CURRENT_PRICES', $useCurrentPrices);
            Configuration::updateValue('CREDITSLIP_LOG_PRICE_CHANGES', $logPriceChanges);
            
            $output .= $this->displayConfirmation($this->l('Settings updated'));
        }
        
        return $output.$this->displayForm();
    }

    public function displayForm()
    {
        // Formulario de configuración
        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Credit Slip Settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Use current product prices for refunds'),
                        'name' => 'CREDITSLIP_USE_CURRENT_PRICES',
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Yes'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('No'),
                            ],
                        ],
                        'desc' => $this->l('If enabled, refunds will use the current product prices instead of the original order prices.'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Log price changes'),
                        'name' => 'CREDITSLIP_LOG_PRICE_CHANGES',
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'log_on',
                                'value' => true,
                                'label' => $this->l('Yes'),
                            ],
                            [
                                'id' => 'log_off',
                                'value' => false,
                                'label' => $this->l('No'),
                            ],
                        ],
                        'desc' => $this->l('Keep track of price changes for products with pending returns.'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];
        
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit'.$this->name;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => [
                'CREDITSLIP_USE_CURRENT_PRICES' => Configuration::get('CREDITSLIP_USE_CURRENT_PRICES'),
                'CREDITSLIP_LOG_PRICE_CHANGES' => Configuration::get('CREDITSLIP_LOG_PRICE_CHANGES'),
            ],
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        // Añadir un enlace al controlador de administración
        $output = '<div class="panel">
            <div class="panel-heading">
                <i class="icon-cogs"></i> ' . $this->l('Credit Slip Manager') . '
            </div>
            <div class="panel-body">
                <div class="alert alert-info">
                    ' . $this->l('Manage your credit slips and price updates in the dedicated section.') . '
                </div>
                <a href="' . $this->context->link->getAdminLink('AdminCreditSlipManager') . '" class="btn btn-primary">
                    <i class="icon-cog"></i> ' . $this->l('Manage Credit Slips') . '
                </a>
            </div>
        </div>';

        return $output . $helper->generateForm([$form]);
    }

    /**
     * Hook ejecutado cuando se crea un nuevo slip de crédito/devolución
     */
    public function hookActionOrderSlipAdd($params)
    {
        if (!isset($params['order_slip']) || !$params['order_slip']) {
            return;
        }

        $orderSlip = $params['order_slip'];
        $order = new Order((int)$orderSlip->id_order);
        
        // Comprobar si debemos usar los precios actuales
        $useCurrentPrices = Configuration::get('CREDITSLIP_USE_CURRENT_PRICES');
        
        if ($useCurrentPrices) {
            $this->updateCreditSlipPrices($orderSlip, $order);
        }
        
        // Registrar el procesamiento de la devolución
        $this->logCreditSlipAction($orderSlip->id, 'created', 'Credit slip created successfully');
    }
    
    /**
     * Actualiza los precios en la devolución con los actuales
     */
    private function updateCreditSlipPrices($orderSlip, $order)
    {
        try {
            // Comenzar transacción
            Db::getInstance()->execute('START TRANSACTION');
            
            // Obtener los detalles del slip de crédito
            $orderSlipDetails = Db::getInstance()->executeS('
                SELECT * FROM `'._DB_PREFIX_.'order_slip_detail` 
                WHERE `id_order_slip` = '.(int)$orderSlip->id
            );
            
            foreach ($orderSlipDetails as $detail) {
                $orderDetail = new OrderDetail($detail['id_order_detail']);
                $product = new Product($orderDetail->product_id);
                
                // Obtener el precio actual del producto
                $currentPrice = $product->getPrice(
                    false, // Sin impuestos
                    $orderDetail->product_attribute_id,
                    6, // Precisión decimal
                    null,
                    false,
                    true,
                    1  // Cantidad
                );
                
                // Calcular el precio con impuestos si es necesario
                $taxRate = 1;
                if ($orderDetail->id_tax_rules_group > 0) {
                    $address = new Address($order->id_address_invoice);
                    $taxManager = TaxManagerFactory::getManager($address, $orderDetail->id_tax_rules_group);
                    $taxCalculator = $taxManager->getTaxCalculator();
                    $taxRate = 1 + ($taxCalculator->getTotalRate() / 100);
                }
                
                $currentPriceWithTax = $currentPrice * $taxRate;
                
                // Actualizar el precio en order_slip_detail
                Db::getInstance()->execute(
                    'UPDATE `'._DB_PREFIX_.'order_slip_detail` 
                    SET `unit_price_tax_excl` = '.(float)$currentPrice.', 
                        `unit_price_tax_incl` = '.(float)$currentPriceWithTax.' 
                    WHERE `id_order_slip` = '.(int)$orderSlip->id.' 
                    AND `id_order_detail` = '.(int)$detail['id_order_detail']
                );
                
                // Si los precios son diferentes, registrar el cambio
                if (Configuration::get('CREDITSLIP_LOG_PRICE_CHANGES') && 
                    ($currentPrice != $detail['unit_price_tax_excl'] || 
                     $currentPriceWithTax != $detail['unit_price_tax_incl'])) {
                    $this->logPriceChange(
                        $orderSlip->id, 
                        $detail['id_order_detail'], 
                        $detail['unit_price_tax_excl'], 
                        $currentPrice,
                        $detail['unit_price_tax_incl'],
                        $currentPriceWithTax
                    );
                }
            }
            
            // Recalcular los totales del slip de crédito
            $this->recalculateOrderSlipTotals($orderSlip->id);
            
            // Confirmar la transacción
            Db::getInstance()->execute('COMMIT');
            
            return true;
        } catch (Exception $e) {
            // Revertir en caso de error
            Db::getInstance()->execute('ROLLBACK');
            $this->logCreditSlipAction(
                $orderSlip->id, 
                'error',
                'Error updating prices: ' . $e->getMessage()
            );
            return false;
        }
    }

    /**
     * Recalcula los totales del slip de crédito después de actualizar precios
     */
    private function recalculateOrderSlipTotals($orderSlipId)
    {
        // Calcular totales desde los detalles
        $result = Db::getInstance()->executeS(
            'SELECT SUM(unit_price_tax_excl * product_quantity) as total_products_tax_excl,
                    SUM(unit_price_tax_incl * product_quantity) as total_products_tax_incl
            FROM `'._DB_PREFIX_.'order_slip_detail`
            WHERE `id_order_slip` = '.(int)$orderSlipId
        );
        
        if (!$result || !isset($result[0])) {
            return false;
        }
        
        $totalProductsTaxExcl = (float)$result[0]['total_products_tax_excl'];
        $totalProductsTaxIncl = (float)$result[0]['total_products_tax_incl'];
        $totalTax = $totalProductsTaxIncl - $totalProductsTaxExcl;
        
        // Actualizar el registro principal de order_slip
        Db::getInstance()->execute(
            'UPDATE `'._DB_PREFIX_.'order_slip`
            SET `total_products_tax_excl` = '.(float)$totalProductsTaxExcl.',
                `total_products_tax_incl` = '.(float)$totalProductsTaxIncl.',
                `amount` = '.(float)$totalProductsTaxExcl.',
                `amount_tax_incl` = '.(float)$totalProductsTaxIncl.',
                `total_tax` = '.(float)$totalTax.'
            WHERE `id_order_slip` = '.(int)$orderSlipId
        );
        
        return true;
    }

    /**
     * Registra un cambio de precio para estadísticas y auditoría
     */
    private function logPriceChange($idOrderSlip, $idOrderDetail, $oldPriceExcl, $newPriceExcl, $oldPriceIncl, $newPriceIncl)
    {
        // Crear la tabla si no existe
        $this->createPriceChangeLogTable();
        
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
     * Registra acciones realizadas por el módulo
     */
    private function logCreditSlipAction($idOrderSlip, $action, $message)
    {
        // Crear la tabla si no existe
        $this->createActionLogTable();
        
        return Db::getInstance()->execute(
            'INSERT INTO `'._DB_PREFIX_.'creditslip_action_log`
            (`id_order_slip`, `action`, `message`, `date_add`)
            VALUES
            ('.(int)$idOrderSlip.', "'.pSQL($action).'", "'.pSQL($message).'", NOW())'
        );
    }

    /**
     * Crea la tabla de registro de cambios de precio si no existe
     */
    private function createPriceChangeLogTable()
    {
        return Db::getInstance()->execute('
            CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'creditslip_price_change_log` (
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
            ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;
        ');
    }

    /**
     * Crea la tabla de registro de acciones si no existe
     */
    private function createActionLogTable()
    {
        return Db::getInstance()->execute('
            CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'creditslip_action_log` (
                `id_action_log` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `id_order_slip` int(10) unsigned NOT NULL,
                `action` varchar(50) NOT NULL,
                `message` text NOT NULL,
                `date_add` datetime NOT NULL,
                PRIMARY KEY (`id_action_log`),
                KEY `id_order_slip` (`id_order_slip`)
            ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;
        ');
    }

    /**
     * Hook que añade contenido a la pestaña de administración de orden
     */
    public function hookDisplayAdminOrderTabContent($params)
    {
        $orderId = $params['id_order'];
        
        // Obtener todos los credit slips para este pedido usando consulta directa
        $creditSlips = Db::getInstance()->executeS('
            SELECT * FROM `'._DB_PREFIX_.'order_slip` 
            WHERE `id_order` = '.(int)$orderId.'
            ORDER BY date_add DESC
        ');
        
        // Si no hay credit slips, no mostrar nada
        if (empty($creditSlips)) {
            return '';
        }
        
        // Preparar datos para la vista
        $slipsWithDetails = [];
        foreach ($creditSlips as $slip) {
            $slipDetails = Db::getInstance()->executeS('
                SELECT d.*, od.product_name
                FROM `'._DB_PREFIX_.'order_slip_detail` d
                LEFT JOIN `'._DB_PREFIX_.'order_detail` od ON d.id_order_detail = od.id_order_detail
                WHERE d.`id_order_slip` = '.(int)$slip['id_order_slip']
            );
            
            $slipsWithDetails[] = [
                'slip' => $slip,
                'details' => $slipDetails
            ];
        }
        
        $this->context->smarty->assign([
            'creditSlips' => $slipsWithDetails,
            'useCurrentPrices' => Configuration::get('CREDITSLIP_USE_CURRENT_PRICES'),
            'order' => new Order($orderId),
            'refresh_url' => $this->context->link->getAdminLink('AdminOrders') . '&id_order=' . $orderId . '&vieworder'
        ]);
        
        return $this->display(__FILE__, 'views/templates/admin/order_tab_content.tpl');
    }

    /**
     * Hook que añade un enlace a la pestaña de administración de orden
     */
    public function hookDisplayAdminOrderTabLink($params)
    {
        // Obtener todos los credit slips para este pedido
        $creditSlips = Db::getInstance()->executeS('
            SELECT COUNT(*) as total FROM `'._DB_PREFIX_.'order_slip` 
            WHERE `id_order` = '.(int)$params['id_order']
        );
        
        $count = $creditSlips[0]['total'] ?? 0;
        
        // Si no hay credit slips, no mostrar la pestaña
        if ($count == 0) {
            return '';
        }
        
        $this->context->smarty->assign([
            'creditSlipCount' => $count
        ]);
        
        return $this->display(__FILE__, 'views/templates/admin/order_tab_link.tpl');
    }
}