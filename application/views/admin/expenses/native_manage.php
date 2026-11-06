<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper" data-native-expenses data-open-expense="<?= e($expenseid); ?>">
    <div class="content"><div class="row"><div class="col-md-12">
        <h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-3"><?= _l('expenses'); ?></h4>
        <div id="stats-top" class="tw-mb-6"><div id="expenses_total" class="empty:tw-min-h-[61px]"></div></div>
        <div class="tw-mb-2"><div class="_buttons sm:tw-space-x-1 rtl:sm:tw-space-x-reverse">
            <?php if($capabilities['create']){ ?><a href="<?= e(admin_url('expenses/expense?source=company')); ?>" class="btn btn-primary"><i class="fa-regular fa-plus"></i> <?= _l('new_expense'); ?></a><a href="<?= e(admin_url('expenses/import?source=company')); ?>" class="hidden-xs btn btn-default"><i class="fa-solid fa-upload tw-mr-1"></i><?= _l('import_expenses'); ?></a><?php } ?>
            <?php if($capabilities['bulk_pdf']){ ?><a href="<?= e(admin_url('utilities/bulk_pdf_exporter?feature=expenses')); ?>" class="btn-with-tooltip btn btn-default !tw-px-3" title="<?= e(_l('bulk_pdf_exporter')); ?>"><i class="fa-regular fa-file-pdf"></i></a><?php } ?>
            <a href="<?= e(admin_url('expenses?source=company&workspace=1')); ?>" class="btn btn-default">Categorias e recorrências</a>
            <div id="vueApp" class="tw-inline pull-right tw-ml-0 sm:tw-ml-1.5 rtl:tw-mr-1.5 rtl:tw-ml-0"><app-filters id="company_expenses" view="expenses" :saved-filters="<?= $table->filtersJs(); ?>" :available-rules="<?= $table->rulesJs(); ?>"></app-filters></div>
            <a href="#" class="btn btn-default pull-right btn-with-tooltip toggle-small-view hidden-xs" data-expense-toggle title="<?= e(_l('invoices_toggle_table_tooltip')); ?>"><i class="fa fa-angle-double-left"></i></a>
        </div></div>
        <input type="hidden" id="native-expenses-csrf" name="native_expenses_csrf" value="<?= e($native_expenses_csrf); ?>">
        <div id="native-expense-page-error" class="alert alert-danger hide" role="alert"></div>
        <div class="row"><div class="col-md-12" id="small-table"><div class="panel_s"><div class="panel-body">
            <div class="row mbot15"><div class="col-md-3"><label for="native-expense-state">Exibir</label><select id="native-expense-state" class="form-control selectpicker"><option value="recorded">Registradas</option><option value="cancelled">Canceladas</option><option value="all">Todas</option></select></div></div>
            <div class="clearfix"></div><?= form_hidden('expenseid',$expenseid); ?>
            <?php if($capabilities['edit']||$capabilities['delete']){ ?><a href="#" class="hide bulk-actions-btn table-btn" data-native-expense-bulk-open><?= _l('bulk_actions'); ?></a><?php } ?>
            <div class="panel-table-full">
            <?php $selectable=$capabilities['edit']||$capabilities['delete'];$columns=[['name'=>$selectable?'<span class="hide"> - </span><div class="checkbox mass_select_all_wrap"><input type="checkbox" id="expense-select-all" data-expense-select-all><label for="expense-select-all"></label></div>':'<span class="hide"> - </span>','th_attrs'=>['class'=>$selectable?'not-export':'not_visible']],_l('the_number_sign'),_l('expense_dt_table_heading_category'),_l('expense_dt_table_heading_amount'),_l('expense_name'),_l('receipt'),_l('expense_dt_table_heading_date'),_l('project'),_l('expense_dt_table_heading_customer'),_l('invoice'),_l('expense_dt_table_heading_reference_no'),_l('expense_dt_table_heading_payment_mode')];
            foreach($custom_fields as$field)$columns[]=['name'=>e($field['name']),'th_attrs'=>['data-type'=>$field['type'],'data-custom-field'=>1]];
            render_datatable($columns,'expenses',[],['id'=>'company_expenses','data-last-order-identifier'=>'expenses','data-default-order'=>get_table_last_order('expenses')]); ?>
            </div>
        </div></div></div><div class="col-md-7 small-table-right-col hide"><div id="expense" class="hide"></div></div></div>
    </div></div></div>
</div>
<?php if($capabilities['edit']||$capabilities['delete']){require __DIR__.'/native_bulk_modal.php';} ?>
<script id="hidden-columns-company_expenses" type="application/json">[4,5,6,7,8,9]</script>
<?php init_tail(); ?>
</body></html>
