<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="modal fade" id="expense-category-modal" tabindex="-1" role="dialog" data-native-category-modal data-inline="<?= !empty($native_expense_category_inline)?'1':'0'; ?>">
    <div class="modal-dialog"><?= form_open(admin_url('expenses/category?source=company'),['id'=>'expense-category-form']); ?>
        <?= form_hidden('native_expense_category','original_expense_category_v1').form_hidden('native_expenses_csrf',$native_expenses_csrf).form_hidden('request_key',bin2hex(random_bytes(32))).form_hidden('id','').form_hidden('revision',''); ?>
        <div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="<?= e(_l('close')); ?>"><span aria-hidden="true">&times;</span></button><h4 class="modal-title"><span class="edit-title"><?= _l('edit_expense_category'); ?></span><span class="add-title"><?= _l('new_expense_category'); ?></span></h4></div>
        <div class="modal-body"><div class="row"><div class="col-md-12"><div id="additional"></div>
            <div class="alert alert-danger hide" role="alert" data-category-form-error></div>
            <div class="form-group"><label for="category-name"><?= _l('expense_add_edit_name'); ?></label><input type="text" id="category-name" name="name" class="form-control" maxlength="191" required></div>
            <div class="form-group"><label for="category-description"><?= _l('expense_add_edit_description'); ?></label><textarea id="category-description" name="description" class="form-control" maxlength="4000" rows="4"></textarea></div>
            <?php if(!empty($native_expense_category_inline)){echo form_hidden('active','1');}else{ ?><div class="checkbox"><input id="category-active" type="checkbox" name="active" value="1" checked><label for="category-active">Ativa para novos registros</label></div><?php } ?>
        </div></div></div>
        <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal"><?= _l('close'); ?></button><button type="submit" class="btn btn-primary"><?= _l('submit'); ?></button></div></div>
    <?= form_close(); ?></div>
</div>
