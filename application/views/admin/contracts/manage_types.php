<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if(isset($native_contract_types)){init_head();
    $typeHidden=static function(?array$row=null)use($native_contracts_csrf):void{echo form_hidden('native_contracts_csrf',$native_contracts_csrf).form_hidden('request_key',bin2hex(random_bytes(32)));if($row!==null)echo form_hidden('id',$row['id']).form_hidden('revision',$row['revision']);}; ?>
<div id="wrapper"><div class="content"><h4><?= e(_l('contract_types')); ?></h4><div class="panel_s"><div class="panel-body">
<?php if($can_manage){echo form_open(admin_url('contracts/type?source=company'),['id'=>'contract-type-create']);$typeHidden(); ?><label for="contract-type-name"><?= e(_l('name')); ?></label><input class="form-control" id="contract-type-name" name="name" maxlength="191" required><button type="submit" class="btn btn-primary mtop10"><?= e(_l('new_contract_type')); ?></button><?= form_close(); ?><?php } ?>
<table class="table"><thead><tr><th><?= e(_l('name')); ?></th><th><?= e(_l('options')); ?></th></tr></thead><tbody>
<?php foreach($native_contract_types as$type){ ?><tr data-contract-type="<?= e($type['id']); ?>"><td><?= e($type['name']); ?><?php if(!$type['active']){ ?> · Inativo<?php } ?></td><td><?php if($can_manage){echo form_open(admin_url('contracts/type/'.$type['id'].'?source=company'));$typeHidden($type); ?><input class="form-control" aria-label="<?= e(_l('name')); ?>" name="name" maxlength="191" value="<?= e($type['name']); ?>" required><button type="submit" class="btn btn-default btn-sm mtop5"><?= $type['active']?e(_l('save')):'Reativar com este nome'; ?></button><?= form_close(); ?><?php if($type['active']){echo form_open(admin_url('contracts/delete_contract_type/'.$type['id'].'?source=company'));$typeHidden($type); ?><button type="submit" class="btn btn-default btn-sm mtop5">Desativar e preservar histórico</button><?= form_close(); ?><?php }} ?></td></tr><?php } ?>
</tbody></table><a class="btn btn-default" href="<?= admin_url('contracts/contract?source=company'); ?>"><?= e(_l('new_contract')); ?></a></div></div></div></div>
<?php init_tail(); ?></body></html><?php return;} ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-mb-2">
                    <a href="#" onclick="new_type(); return false;" class="btn btn-primary">
                        <i class="fa-regular fa-plus tw-mr-1"></i>
                        <?php echo _l('new_contract_type'); ?>
                    </a>
                </div>
                <div class="panel_s">
                    <div class="panel-body panel-table-full">
                        <?php render_datatable([
                        _l('name'),
                        _l('options'),
                        ], 'contract-types'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $this->load->view('admin/contracts/contract_type'); ?>
<?php init_tail(); ?>
<script>
$(function() {
    initDataTable('.table-contract-types', window.location.href, [1], [1]);
});
</script>
</body>

</html>
