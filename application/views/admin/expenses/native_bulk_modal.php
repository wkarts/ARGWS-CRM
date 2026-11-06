<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="modal fade bulk_actions" id="expenses_bulk_actions" tabindex="-1" role="dialog" data-table=".table-expenses">
    <div class="modal-dialog" role="document"><?= form_open(admin_url('expenses/bulk_action?source=company'),['id'=>'native-expense-bulk-form']); ?>
        <?= form_hidden('request_key',bin2hex(random_bytes(32))); ?>
        <div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="<?= e(_l('close')); ?>"><span aria-hidden="true">&times;</span></button><h4 class="modal-title"><?= _l('bulk_actions'); ?></h4></div>
        <div class="modal-body"><div class="alert alert-danger hide" role="alert" data-expense-bulk-error></div>
            <p data-expense-bulk-count></p>
            <?php if($capabilities['delete']){ ?><div class="checkbox checkbox-danger"><input type="checkbox" name="mass_delete" id="expense-bulk-cancel" value="1"><label for="expense-bulk-cancel">Cancelar despesas selecionadas</label></div><p class="text-muted">O cancelamento preserva os valores e o histórico.</p><hr class="mass_delete_separator"><?php } ?>
            <?php if($capabilities['edit']){ ?><div id="bulk_change">
                <div class="form-group"><label for="expenses_bulk_amount"><?= _l('expense_add_edit_amount'); ?></label><input type="number" name="amount" id="expenses_bulk_amount" class="form-control" step="0.01" min="0.01"></div>
                <div class="form-group"><label for="expenses_bulk_category"><?= _l('expense_category'); ?></label><select name="category" id="expenses_bulk_category" class="form-control selectpicker" data-live-search="true"><option value=""></option><?php foreach($bulk_categories as$category){ ?><option value="<?= e($category['id']); ?>"><?= e($category['name']); ?></option><?php } ?></select></div>
                <div class="form-group"><label for="expenses_bulk_date"><?= _l('expense_add_edit_date'); ?></label><div class="input-group date"><input type="text" name="date" id="expenses_bulk_date" class="form-control datepicker" autocomplete="off"><div class="input-group-addon"><i class="fa-regular fa-calendar calendar-icon"></i></div></div></div>
                <div class="form-group"><label for="expenses_bulk_paymentmode"><?= _l('payment_mode'); ?></label><select name="paymentmode" id="expenses_bulk_paymentmode" class="form-control selectpicker" data-live-search="true"><option value=""></option><?php foreach($bulk_payment_modes as$mode){ ?><option value="<?= e($mode['id']); ?>"><?= e($mode['name']); ?></option><?php } ?></select></div>
            </div><?php } ?>
        </div><div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close'); ?></button><button type="submit" class="btn btn-primary"><?= _l('confirm'); ?></button></div></div>
    <?= form_close(); ?></div>
</div>
