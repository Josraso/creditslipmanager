{*
* Credit Slip Manager module
* Admin order tab content template
*}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-file-text"></i> {l s='Credit Slips for this order' mod='creditslipmanager'} <span class="badge">{count($creditSlips)}</span>
    </div>
    
    {if $useCurrentPrices}
        <div class="alert alert-info">
            <p>{l s='Current product prices are being used for refunds instead of original order prices.' mod='creditslipmanager'}</p>
        </div>
    {/if}
    
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>{l s='ID' mod='creditslipmanager'}</th>
                    <th>{l s='Date' mod='creditslipmanager'}</th>
                    <th>{l s='Products' mod='creditslipmanager'}</th>
                    <th class="text-right">{l s='Amount (tax excl.)' mod='creditslipmanager'}</th>
                    <th class="text-right">{l s='Amount (tax incl.)' mod='creditslipmanager'}</th>
                    <th class="text-center">{l s='Actions' mod='creditslipmanager'}</th>
                </tr>
            </thead>
            <tbody>
                {foreach from=$creditSlips item=slipData}
                    <tr>
                        <td>{$slipData.slip.id_order_slip}</td>
                        <td>{$slipData.slip.date_add}</td>
                        <td>
                            <ul class="list-unstyled">
                                {foreach from=$slipData.details item=detail}
                                    <li>
                                        {$detail.product_name} x {$detail.product_quantity}
                                        <br>
                                        <small>
                                            {l s='Price:' mod='creditslipmanager'} {displayPrice price=$detail.unit_price_tax_incl}
                                        </small>
                                    </li>
                                {/foreach}
                            </ul>
                        </td>
                        <td class="text-right">{displayPrice price=$slipData.slip.total_products_tax_excl}</td>
                        <td class="text-right">{displayPrice price=$slipData.slip.total_products_tax_incl}</td>
                        <td class="text-center">
                            <div class="btn-group">
                                <a class="btn btn-default" href="{$link->getAdminLink('AdminCreditSlipManager')}&vieworder_slip&id_order_slip={$slipData.slip.id_order_slip}">
                                    <i class="icon-search"></i> {l s='View details' mod='creditslipmanager'}
                                </a>
                                {if !$useCurrentPrices}
                                    <a class="btn btn-default" href="{$link->getAdminLink('AdminCreditSlipManager')}&updatePricesorder_slip&id_order_slip={$slipData.slip.id_order_slip}">
                                        <i class="icon-refresh"></i> {l s='Update prices' mod='creditslipmanager'}
                                    </a>
                                {/if}
                                <a class="btn btn-default" href="{$link->getAdminLink('AdminPdf')}&submitAction=generateOrderSlipPDF&id_order_slip={$slipData.slip.id_order_slip}" target="_blank">
                                    <i class="icon-file-pdf-o"></i> {l s='PDF' mod='creditslipmanager'}
                                </a>
                            </div>
                        </td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
</div>