<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$editing=$contract!==null;$prefix=$editing?'contract-edit':'contract-new';
$values=$contract??['client_id'=>'','name'=>'','body'=>'','start_date'=>gmdate('Y-m-d'),'due_date'=>null];
$metadata=$contract['native_metadata']??['description'=>'','project'=>null,'custom_fields'=>[]];
$display=new NativeCompanyFormatting(CompanySettingsFormats::dates()[$contract_form_format['dateformat']],$contract_form_format['time_format']);
$formClient=$contract_form_client;$formProject=$contract_form_project;
?>
<?= form_open($editing?$detailUrl:$createUrl,['id'=>'contract-form','data-native-contract-editor'=>'1','data-contract-id'=>$contract_id??'']); ?>
<?php $hidden($editing?'update':'create',$contract_id,$contract['revision']??null); ?>
<input type="hidden" name="native_contract_form" value="1">
<input type="hidden" name="native_contract_fields_hash" value="<?= e($contract_form_fields_hash); ?>">
<?php foreach($contract_form_format as$key=>$value){ ?><input type="hidden" name="native_contract_format[<?= e($key); ?>]" value="<?= e($value); ?>"><?php } ?>
<?php foreach($contract_form_fields as$field){if(in_array($field['type'],['date_picker','date_picker_time'],true)){ ?><input type="hidden" name="native_contract_field_types[<?= e($field['id']); ?>]" value="<?= e($field['type']); ?>"><?php }} ?>
<div id="contract-form-error" class="alert alert-danger hide" role="alert"></div>
<div class="form-group select-placeholder">
  <label for="contract-client"><small class="req text-danger">* </small><?= e(_l('contract_client_string')); ?></label>
  <select id="contract-client" class="selectpicker ajax-search" name="contract[client_id]" data-live-search="true" data-width="100%" data-none-selected-text="<?= e(_l('dropdown_non_selected_tex')); ?>" required>
    <option value=""></option><?php foreach($clients as$client){ ?><option value="<?= (int)$client['userid']; ?>" <?= (int)$values['client_id']===(int)$client['userid']?'selected':''; ?>><?= e($client['company']); ?></option><?php } ?>
  </select>
</div>
<?php if($contract_form_can_projects){ ?>
<div class="form-group select-placeholder" id="contract-project-wrapper">
  <label for="contract-project"><?= e(_l('project')); ?></label>
  <select id="contract-project" class="selectpicker" name="contract[project_id]" data-live-search="true" data-width="100%" data-none-selected-text="<?= e(_l('dropdown_non_selected_tex')); ?>">
    <option value=""></option><?php foreach($formClient['projects']??[] as$project){ ?><option value="<?= e($project['id']); ?>" data-revision="<?= (int)$project['revision']; ?>" <?= ($formProject['source_id']??'')===$project['id']?'selected':''; ?>><?= e($project['name']); ?></option><?php } ?>
  </select>
  <input type="hidden" id="contract-project-revision" name="contract[project_revision]" value="<?= e($formProject['source_revision']??''); ?>">
</div>
<?php } ?>
<div class="form-group"><label for="<?= e($prefix); ?>-name"><small class="req text-danger">* </small><?= e(_l('contract_subject')); ?></label><input id="<?= e($prefix); ?>-name" class="form-control" name="contract[name]" maxlength="191" required value="<?= e($values['name']); ?>"></div>
<div class="row">
  <div class="col-md-8 form-group"><label for="<?= e($prefix); ?>-value"><?= e(_l('contract_value')); ?></label><input id="<?= e($prefix); ?>-value" class="form-control" type="number" min="0" max="99999999999.99" step="0.01" name="contract[contract_value]" value="<?= e($contract_value['contract_value']??''); ?>"></div>
  <div class="col-md-4 form-group select-placeholder"><label for="<?= e($prefix); ?>-currency"><?= e(_l('invoice_add_edit_currency')); ?></label><select id="<?= e($prefix); ?>-currency" class="selectpicker" data-width="100%" name="contract[currency]"><?php foreach($contract_currencies as$currency){ ?><option value="<?= e($currency['name']); ?>" <?= ($contract_value['currency']??$contract_form_base_currency)===$currency['name']?'selected':''; ?>><?= e($currency['name']); ?></option><?php } ?></select></div>
</div>
<div class="form-group select-placeholder"><label for="<?= e($prefix); ?>-type"><?= e(_l('contract_type')); ?></label><div class="<?= !empty($comment_admin)?'input-group':''; ?>">
  <select id="<?= e($prefix); ?>-type" class="selectpicker" data-width="100%" name="contract[contract_type]"><option value=""></option><?php foreach($contract_types as$type){ ?><option value="<?= e($type['id']); ?>" <?= ($contract_type['contract_type']['id']??'')===$type['id']?'selected':''; ?>><?= e($type['name']); ?></option><?php } ?></select>
  <?php if(!empty($comment_admin)){ ?><span class="input-group-btn"><button type="button" class="btn btn-default" data-toggle="modal" data-target="#native-contract-type-modal" aria-label="<?= e(_l('new_contract_type')); ?>"><i class="fa fa-plus"></i></button></span><?php } ?>
</div></div>
<div class="row">
  <div class="col-md-6 form-group"><label for="<?= e($prefix); ?>-start"><small class="req text-danger">* </small><?= e(_l('contract_start_date')); ?></label><div class="input-group date"><input id="<?= e($prefix); ?>-start" class="form-control datepicker" name="contract[start_date]" autocomplete="off" required value="<?= e($display->date($values['start_date'])); ?>"><div class="input-group-addon"><i class="fa-regular fa-calendar calendar-icon"></i></div></div></div>
  <div class="col-md-6 form-group"><label for="<?= e($prefix); ?>-due"><?= e(_l('contract_end_date')); ?></label><div class="input-group date"><input id="<?= e($prefix); ?>-due" class="form-control datepicker" name="contract[due_date]" autocomplete="off" value="<?= e($display->date($values['due_date'])); ?>"><div class="input-group-addon"><i class="fa-regular fa-calendar calendar-icon"></i></div></div></div>
</div>
<div class="form-group"><label for="contract-description"><?= e(_l('contract_description')); ?></label><textarea id="contract-description" name="contract[description]" class="form-control" rows="4" maxlength="20000"><?= e($metadata['description']??''); ?></textarea></div>
<div class="row"><?= render_custom_fields('contracts',$contract_id??false,[],['native_purpose'=>$editing?'view':'create']); ?></div>
<hr>
<div class="form-group"><label for="<?= e($prefix); ?>-body"><?= e(_l('contract_content')); ?></label><textarea id="<?= e($prefix); ?>-body" class="form-control" name="contract[body]" maxlength="40000" rows="12"><?= e($values['body']); ?></textarea><p class="text-muted mtop5">O conteúdo pode ser concluído antes de ativar o contrato. Após a ativação, o documento permanece preservado.</p></div>
<div class="text-right"><button class="btn btn-primary" type="submit"><?= $editing?'Salvar rascunho':'Criar rascunho de contrato'; ?></button></div>
<?= form_close(); ?>
<?php if(!empty($comment_admin)){ ?>
<div class="modal fade" id="native-contract-type-modal" tabindex="-1" role="dialog" aria-labelledby="native-contract-type-title"><div class="modal-dialog" role="document"><div class="modal-content">
  <?= form_open(admin_url('contracts/type'),['id'=>'native-contract-type-form']); ?>
  <div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="<?= e(_l('close')); ?>"><span aria-hidden="true">&times;</span></button><h4 class="modal-title" id="native-contract-type-title"><?= e(_l('new_contract_type')); ?></h4></div>
  <div class="modal-body"><input type="hidden" name="native_contracts_csrf" value="<?= e($native_contracts_csrf); ?>"><input type="hidden" name="request_key" value="<?= e(bin2hex(random_bytes(32))); ?>"><label for="native-contract-type-name"><?= e(_l('contract_type_name')); ?></label><input id="native-contract-type-name" class="form-control" name="name" maxlength="191" required><div class="alert alert-danger hide mtop10" role="alert" data-contract-type-error></div></div>
  <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal"><?= e(_l('close')); ?></button><button type="submit" class="btn btn-primary"><?= e(_l('submit')); ?></button></div>
  <?= form_close(); ?>
</div></div></div>
<?php } ?>
