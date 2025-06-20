{*
* Credit Slip Manager module
* Credit slip detailed view template
*}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-file-text"></i> {l s='Credit Slip Details' mod='creditslipmanager'} #{$order_slip->id}
    </div>
    
    <div class="row">
        <div class="col-lg-6">
            <div class="panel">
                <div class="panel-heading">
                    <i class="icon-info-circle"></i> {l s='General Information' mod='creditslipmanager'}
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <tr>
                            <td><strong>{l s='Credit Slip ID' mod='creditslipmanager'}</strong></td>
                            <td>{$order_slip->id}</td>
                        </tr>
                        <tr>
                            <td><strong>{l s='Order Reference' mod='creditslipmanager'}</strong></td>
                            <td>
                                <a href="{$link->getAdminLink('AdminOrders')}&vieworder&id_order={$order->id}" target="_blank">
                                    #{$order->reference}
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>{l s='Customer' mod='creditslipmanager'}</strong></td>
                            <td>
                                <a href="{$link->getAdminLink('AdminCustomers')}&viewcustomer&id_customer={$customer->id}" target="_blank">
                                    {$customer->firstname} {$customer->lastname}
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>{l s='Date issued' mod='creditslipmanager'}</strong></td>
                            <td>{$order_slip->date_add}</td>
                        </tr>
                        <tr>
                            <td><strong>{l s='Total Amount (tax excl.)' mod='creditslipmanager'}</strong></td>
                            <td>{displayPrice price=$order_slip->total_products_tax_excl}</td>
                        </tr>
                        <tr>
                            <td><strong>{l s='Total Amount (tax incl.)' mod='creditslipmanager'}</strong></td>
                            <td>{displayPrice price=$order_slip->total_products_tax_incl}</td>
                        </tr>
                        <tr>
                            <td><strong>{l s='Total Tax' mod='creditslipmanager'}</strong></td>
                            <td>{displayPrice price=$order_slip->total_products_tax_incl-$order_slip->total_products_tax_excl}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="panel">
                <div class="panel-heading">
                    <i class="icon-cogs"></i> {l s='Actions' mod='creditslipmanager'}
                </div>
                <div class="panel-body">
                    <div class="btn-group">
                        <a href="{$link->getAdminLink('AdminPdf')}&submitAction=generateOrderSlipPDF&id_order_slip={$order_slip->id}" class="btn btn-default" target="_blank">
                            <i class="icon-file-pdf-o"></i> {l s='Download PDF' mod='creditslipmanager'}
                        </a>
                        
                        {if !$use_current_prices}
                            <a href="{$link->getAdminLink('AdminCreditSlipManager')}&updatePricesorder_slip&id_order_slip={$order_slip->id}" class="btn btn-default">
                                <i class="icon-refresh"></i> {l s='Update to Current Prices' mod='creditslipmanager'}
                            </a>
                        {/if}
                        
                        <a href="{$back_url}" class="btn btn-default">
                            <i class="icon-arrow-left"></i> {l s='Back to List' mod='creditslipmanager'}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <form method="post" action="{$current_url}">
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-shopping-cart"></i> {l s='Products' mod='creditslipmanager'}
                <div class="panel-heading-action">
                    <button type="submit" name="submitUpdateSlipPrices" class="btn btn-primary">
                        <i class="icon-save"></i> {l s='Save Changes' mod='creditslipmanager'}
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{l s='Product' mod='creditslipmanager'}</th>
                            <th>{l s='Quantity' mod='creditslipmanager'}</th>
                            <th class="text-right">{l s='Unit Price (tax excl.)' mod='creditslipmanager'}</th>
                            <th class="text-right">{l s='Unit Price (tax incl.)' mod='creditslipmanager'}</th>
                            <th class="text-right">{l s='Total (tax excl.)' mod='creditslipmanager'}</th>
                            <th class="text-right">{l s='Total (tax incl.)' mod='creditslipmanager'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$order_slip_details item=detail}
                            <tr>
                                <td>{$detail.product_name}</td>
                                <td>
                                    <div class="input-group fixed-width-xs">
                                        <input type="number" name="quantity_{$detail.id_order_detail}" value="{$detail.product_quantity}" class="form-control text-center product-quantity" min="1">
                                        <input type="hidden" name="id_order_detail[]" value="{$detail.id_order_detail}">
                                    </div>
                                </td>
                                <td class="text-right">
                                    <div class="input-group fixed-width-md">
                                        <span class="input-group-addon">{Currency::getDefaultCurrency()->sign}</span>
                                        <input type="text" name="price_excl_{$detail.id_order_detail}" value="{$detail.unit_price_tax_excl|floatval}" class="form-control text-right price-excl" data-id="{$detail.id_order_detail}">
                                    </div>
                                </td>
                                <td class="text-right">
                                    <div class="input-group fixed-width-md">
                                        <span class="input-group-addon">{Currency::getDefaultCurrency()->sign}</span>
                                        <input type="text" name="price_incl_{$detail.id_order_detail}" value="{$detail.unit_price_tax_incl|floatval}" class="form-control text-right price-incl" data-id="{$detail.id_order_detail}">
                                    </div>
                                </td>
                                <td class="text-right">
                                    <span class="total-excl" data-id="{$detail.id_order_detail}">{displayPrice price=$detail.unit_price_tax_excl*$detail.product_quantity}</span>
                                </td>
                                <td class="text-right">
                                    <span class="total-incl" data-id="{$detail.id_order_detail}">{displayPrice price=$detail.unit_price_tax_incl*$detail.product_quantity}</span>
                                </td>
                            </tr>
                        {/foreach}
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-right"><strong>{l s='Total' mod='creditslipmanager'}</strong></td>
                            <td class="text-right"><strong id="grand-total-excl">{displayPrice price=$order_slip->total_products_tax_excl}</strong></td>
                            <td class="text-right"><strong id="grand-total-incl">{displayPrice price=$order_slip->total_products_tax_incl}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <div class="panel-footer">
                <button type="submit" name="submitUpdateSlipPrices" class="btn btn-primary pull-right">
                    <i class="icon-save"></i> {l s='Save Changes' mod='creditslipmanager'}
                </button>
                <div class="clearfix"></div>
            </div>
        </div>
        
        <input type="hidden" name="id_order_slip" value="{$order_slip->id}">
        <input type="hidden" name="token" value="{$token}">
    </form>
    
    {if $price_changes}
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-history"></i> {l s='Price Change History' mod='creditslipmanager'}
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{l s='Date' mod='creditslipmanager'}</th>
                            <th>{l s='Product Detail ID' mod='creditslipmanager'}</th>
                            <th class="text-right">{l s='Old Price (tax excl.)' mod='creditslipmanager'}</th>
                            <th class="text-right">{l s='New Price (tax excl.)' mod='creditslipmanager'}</th>
                            <th class="text-right">{l s='Old Price (tax incl.)' mod='creditslipmanager'}</th>
                            <th class="text-right">{l s='New Price (tax incl.)' mod='creditslipmanager'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$price_changes item=change}
                            <tr>
                                <td>{$change.date_add}</td>
                                <td>{$change.id_order_detail}</td>
                                <td class="text-right">{displayPrice price=$change.old_price_excl}</td>
                                <td class="text-right">{displayPrice price=$change.new_price_excl}</td>
                                <td class="text-right">{displayPrice price=$change.old_price_incl}</td>
                                <td class="text-right">{displayPrice price=$change.new_price_incl}</td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        </div>
    {/if}
    
    {if $action_logs}
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-list"></i> {l s='Activity Log' mod='creditslipmanager'}
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{l s='Date' mod='creditslipmanager'}</th>
                            <th>{l s='Action' mod='creditslipmanager'}</th>
                            <th>{l s='Message' mod='creditslipmanager'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$action_logs item=log}
                            <tr>
                                <td>{$log.date_add}</td>
                                <td>{$log.action}</td>
                                <td>{$log.message}</td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        </div>
    {/if}
</div>

{* JavaScript para calcular los totales automáticamente al modificar cantidades o precios *}
<script type="text/javascript">
    $(document).ready(function() {
        // Función para recalcular los totales
        function recalculateTotals() {
            var grandTotalExcl = 0;
            var grandTotalIncl = 0;
            
            // Recalcular para cada producto
            $('.product-quantity').each(function() {
                var detailId = $(this).siblings('input[name^="id_order_detail"]').val();
                var quantity = parseInt($(this).val() || 0);
                var priceExcl = parseFloat($('input[name="price_excl_' + detailId + '"]').val() || 0);
                var priceIncl = parseFloat($('input[name="price_incl_' + detailId + '"]').val() || 0);
                
                var totalExcl = quantity * priceExcl;
                var totalIncl = quantity * priceIncl;
                
                // Actualizar los totales de línea
                $('.total-excl[data-id="' + detailId + '"]').html(formatCurrency(totalExcl));
                $('.total-incl[data-id="' + detailId + '"]').html(formatCurrency(totalIncl));
                
                // Añadir al total general
                grandTotalExcl += totalExcl;
                grandTotalIncl += totalIncl;
            });
            
            // Actualizar los totales generales
            $('#grand-total-excl').html(formatCurrency(grandTotalExcl));
            $('#grand-total-incl').html(formatCurrency(grandTotalIncl));
        }
        
        // Función para formatear moneda
        function formatCurrency(amount) {
            return amount.toFixed(2).replace('.', ',') + ' €';
        }
        
        // Eventos para recalcular al cambiar valores
        $('.product-quantity, .price-excl, .price-incl').on('change keyup', function() {
            recalculateTotals();
        });
        
        // Inicializar
        recalculateTotals();
    });
</script>