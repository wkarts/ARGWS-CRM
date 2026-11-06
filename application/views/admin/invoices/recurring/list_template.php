<?php defined('BASEPATH') or exit('No direct script access allowed');
$nativeRecurring=$native_recurring??false;
$recurringTableSelector=$nativeRecurring?'.table-recurring-invoices':'.table-invoices';
?>
<div class="col-md-12">
    <div class="tw-mb-2">
        <div class="_buttons">
            <?php if(!$nativeRecurring){ ?><a href="<?= admin_url('recurring_rules'); ?>" class="btn btn-default pull-left mright5">Gerenciar recorrências</a><?php } ?>
            <?php if($nativeRecurring&&$can_manage){ ?><a href="<?= admin_url('invoices/recurring?new=1'); ?>" class="btn btn-primary pull-left mright5"><i class="fa-regular fa-plus tw-mr-1" aria-hidden="true"></i>Nova recorrência</a><?php } ?>
            <?php if ($nativeRecurring?$can_create_invoice:staff_can('create', 'invoices')) { ?>
            <a href="<?= admin_url('invoices/invoice'); ?>"
                class="btn btn-primary pull-left new new-invoice-list">
                <i class="fa-regular fa-plus tw-mr-1"></i>
                <?= _l('create_new_invoice'); ?>
            </a>
            <?php } ?>
            <a href="<?= admin_url('invoices'); ?>"
                class="btn btn-default pull-left mleft5">
                <?= _l('go_back'); ?>
            </a>
            <div class="display-block text-right">
                <div class="btn-group pull-right mleft4 invoice-view-buttons btn-with-tooltip-group _filter_data"
                    data-toggle="tooltip"
                    data-title="<?= _l('filter_by'); ?>">
                    <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown"
                        aria-haspopup="true" aria-expanded="false">
                        <i class="fa fa-filter" aria-hidden="true"></i>
                    </button>
                    <ul class="dropdown-menu width300">
                        <li>
                            <a href="#" data-cview="all"
                                onclick="dt_custom_view('','<?= $recurringTableSelector; ?>',''); return false;">
                                <?= _l('invoices_list_all'); ?>
                            </a>
                        </li>
                        <?php if (count($invoices_years) > 0) { ?>
                        <li class="divider"></li>
                        <?php foreach ($invoices_years as $year) { ?>
                        <li class="active">
                            <a href="#"
                                data-cview="year_<?= e($year['year']); ?>"
                                onclick="dt_custom_view(<?= e($year['year']); ?>,'<?= $recurringTableSelector; ?>','year_<?= e($year['year']); ?>'); return false;"><?= e($year['year']); ?>
                            </a>
                        </li>
                        <?php } ?>
                        <?php } ?>
                        <?php if (count($invoices_sale_agents) > 0) { ?>
                        <div class="clearfix"></div>
                        <li class="divider"></li>
                        <li class="dropdown-submenu pull-left">
                            <a href="#"
                                tabindex="-1"><?= _l('sale_agent_string'); ?></a>
                            <ul class="dropdown-menu dropdown-menu-left">
                                <?php foreach ($invoices_sale_agents as $agent) { ?>
                                <li>
                                    <a href="#"
                                        data-cview="sale_agent_<?= e($agent['sale_agent']); ?>"
                                        onclick="dt_custom_view(<?= e($agent['sale_agent']); ?>,'<?= $recurringTableSelector; ?>','sale_agent_<?= e($agent['sale_agent']); ?>'); return false;"><?= e($agent['full_name']); ?>
                                    </a>
                                </li>
                                <?php } ?>
                            </ul>
                        </li>
                        <?php } ?>
                    </ul>
                </div>
                <a href="#" class="btn btn-default btn-with-tooltip toggle-small-view hidden-xs sm:!tw-px-3"
                    onclick="toggle_small_view('<?= $recurringTableSelector; ?>','#invoice'); return false;" data-toggle="tooltip"
                    title="<?= _l('invoices_toggle_table_tooltip'); ?>">
                    <i class="fa fa-angle-double-left"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12" id="small-table">
            <div class="panel_s panel-table-full">
                <div class="panel-body">
                    <!-- if invoiceid found in url -->
                    <?= form_hidden($nativeRecurring?'schedule_id':'invoiceid', $invoiceid); ?>
                    <?php $this->load->view('admin/invoices/recurring/table_html'); ?>
                </div>
            </div>
        </div>
        <div class="col-md-7 small-table-right-col">
            <div id="invoice" class="hide">
            </div>
        </div>
    </div>
</div>