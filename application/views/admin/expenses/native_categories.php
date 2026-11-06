<?php defined('BASEPATH') or exit('No direct script access allowed');init_head(); ?>
<div id="wrapper" data-native-expense-categories><div class="content"><div class="row"><div class="col-md-12">
    <div class="tw-mb-2">
        <?php if($capabilities['create']){ ?><a href="#" data-native-expense-category="new" class="btn btn-primary"><i class="fa-regular fa-plus tw-mr-1"></i><?= _l('new_expense_category'); ?></a><?php } ?>
        <a href="<?= e(admin_url('expenses')); ?>" class="btn btn-default"><?= _l('expenses'); ?></a>
    </div>
    <input type="hidden" id="native-expenses-csrf" value="<?= e($native_expenses_csrf); ?>">
    <div id="native-expense-category-error" class="alert alert-danger hide" role="alert"></div>
    <div class="panel_s"><div class="panel-body panel-table-full"><?php render_datatable([_l('id'),_l('name'),_l('dt_expense_description'),_l('options')],'expenses-categories'); ?></div></div>
</div></div></div></div>
<?php if($capabilities['create']||$capabilities['edit']){require __DIR__.'/native_category_modal.php';} ?>
<?php init_tail(); ?></body></html>
