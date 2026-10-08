<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content">
<div class="row"><div class="col-md-12"><div class="panel_s"><div class="panel-body">
<div class="row"><div class="col-md-7"><h4 class="no-margin"><i class="fa fa-comments"></i> <?php echo _l('connect_api_chat_settings'); ?></h4></div><div class="col-md-5 text-right"><a class="btn btn-default" href="<?php echo admin_url('connect_api_chat/diagnostics'); ?>"><i class="fa fa-stethoscope"></i> <?php echo _l('connect_api_chat_open_diagnostics'); ?></a> <a class="btn btn-default" href="<?php echo admin_url('connect_api_chat'); ?>"><i class="fa fa-arrow-left"></i> Chat</a></div></div>
<hr class="hr-panel-heading" />
<?php if (!$connector_available) { ?><div class="alert alert-danger"><?php echo _l('connect_api_chat_connector_required'); ?></div><?php } elseif (!$connector_configured) { ?><div class="alert alert-warning"><?php echo _l('connect_api_chat_connector_not_configured'); ?></div><?php } else { ?><div class="alert alert-success"><strong><?php echo _l('connect_api_chat_dependency_status'); ?>:</strong> <?php echo html_escape($connector['instance_name']); ?></div><?php } ?>
<div class="row"><div class="col-md-7">
<?php echo form_open(admin_url('connect_api_chat/settings')); ?>
<h4>Atendimento</h4>
<div class="checkbox checkbox-primary"><input type="checkbox" name="all_staff" id="all_staff" value="1" <?php echo get_option('connect_api_chat_all_staff')!=='0'?'checked':''; ?>><label for="all_staff"><?php echo _l('connect_api_chat_all_staff'); ?></label></div>
<div class="checkbox checkbox-primary"><input type="checkbox" name="auto_claim_on_send" id="auto_claim_on_send" value="1" <?php echo get_option('connect_api_chat_auto_claim_on_send')!=='0'?'checked':''; ?>><label for="auto_claim_on_send"><?php echo _l('connect_api_chat_auto_claim_on_send'); ?></label></div>
<div class="checkbox checkbox-primary"><input type="checkbox" name="notify_unassigned" id="notify_unassigned" value="1" <?php echo get_option('connect_api_chat_notify_unassigned')!=='0'?'checked':''; ?>><label for="notify_unassigned"><?php echo _l('connect_api_chat_notify_unassigned'); ?></label></div>
<div class="row"><div class="col-md-3"><?php echo render_input('poll_interval','connect_api_chat_poll_interval',get_option('connect_api_chat_poll_interval')?:3,'number',['min'=>2,'max'=>15]); ?></div><div class="col-md-3"><?php echo render_input('api_sync_interval','connect_api_chat_api_sync_interval',get_option('connect_api_chat_api_sync_interval')?:60,'number',['min'=>15,'max'=>300]); ?></div><div class="col-md-3"><?php echo render_input('page_size','connect_api_chat_page_size',get_option('connect_api_chat_page_size')?:30,'number',['min'=>20,'max'=>100]); ?></div><div class="col-md-3"><?php echo render_input('upload_limit_mb','connect_api_chat_upload_limit',get_option('connect_api_chat_upload_limit_mb')?:16,'number',['min'=>1,'max'=>64]); ?></div></div><p class="text-muted">O polling local mantém a interface atualizada. A sincronização com a API funciona como fallback do webhook e deve ficar mais espaçada para reduzir carga.</p>
<hr>
<?php if(count($instance_contexts)>1){ ?>
<h4><i class="fa fa-random"></i> <?php echo _l('connect_api_chat_multi_instance'); ?></h4>
<div class="checkbox checkbox-primary"><input type="checkbox" name="multi_instance_enabled" id="multi_instance_enabled" value="1" <?php echo get_option('connect_api_chat_multi_instance_enabled')!=='0'?'checked':''; ?>><label for="multi_instance_enabled"><?php echo _l('connect_api_chat_multi_instance_enable'); ?></label></div>
<p class="text-muted"><?php echo _l('connect_api_chat_multi_instance_help'); ?></p>
<input type="hidden" name="instance_access_present" value="1">
<div class="table-responsive"><table class="table table-bordered table-hover"><thead><tr><th>Usuário</th><th><?php echo _l('connect_api_chat_allowed_instances'); ?></th><th style="width:260px"><?php echo _l('connect_api_chat_default_instance'); ?></th></tr></thead><tbody>
<?php foreach($staff_rows as $staffRow){ $sid=(int)$staffRow['staffid']; $selected=[];$default=''; foreach(($staff_instance_access[$sid]??[]) as $ar){$selected[]=(string)$ar['instance_name'];if(!empty($ar['is_default']))$default=(string)$ar['instance_name'];} ?>
<tr><td><strong><?php echo html_escape(trim($staffRow['firstname'].' '.$staffRow['lastname'])); ?></strong></td><td><select class="form-control selectpicker" multiple data-actions-box="true" data-live-search="true" name="staff_instances[<?php echo $sid; ?>][]"><?php foreach($instance_contexts as $ic){$iname=(string)$ic['instance_name'];?><option value="<?php echo html_escape($iname); ?>" <?php echo in_array($iname,$selected,true)?'selected':''; ?>><?php echo html_escape($iname); ?><?php echo ($ic['source']??'')==='connector'?' · Conector':' · Manage'; ?></option><?php } ?></select></td><td><select class="form-control selectpicker" name="staff_default[<?php echo $sid; ?>]"><option value="">Automática</option><?php foreach($instance_contexts as $ic){$iname=(string)$ic['instance_name'];?><option value="<?php echo html_escape($iname); ?>" <?php echo $default===$iname?'selected':''; ?>><?php echo html_escape($iname); ?></option><?php } ?></select></td></tr>
<?php } ?>
</tbody></table></div>
<?php } ?>
<hr>
<h4><?php echo _l('connect_api_chat_pwa'); ?></h4>
<div class="checkbox checkbox-primary"><input type="checkbox" name="pwa_enabled" id="pwa_enabled" value="1" <?php echo get_option('connect_api_chat_pwa_enabled')!=='0'?'checked':''; ?>><label for="pwa_enabled"><?php echo _l('connect_api_chat_pwa_enable'); ?></label></div>
<?php if(get_option('connect_api_chat_pwa_enabled')!=='0'){ ?><p><a class="btn btn-info btn-sm" target="_blank" href="<?php echo $pwa_url; ?>"><i class="fa fa-external-link"></i> <?php echo _l('connect_api_chat_open_pwa'); ?></a></p><?php } ?>
<hr>
<h4><?php echo _l('connect_api_chat_debug_mode'); ?></h4>
<div class="checkbox checkbox-danger"><input type="checkbox" name="debug_enabled" id="debug_enabled" value="1" <?php echo get_option('connect_api_chat_debug_enabled')==='1'?'checked':''; ?>><label for="debug_enabled"><?php echo _l('connect_api_chat_debug_mode'); ?></label></div>
<p class="text-muted"><?php echo _l('connect_api_chat_debug_mode_help'); ?></p>
<?php echo render_input('debug_retention','connect_api_chat_debug_retention',get_option('connect_api_chat_debug_retention')?:300,'number',['min'=>50,'max'=>2000]); ?>
<hr>
<?php echo render_input('forward_webhook_url','connect_api_chat_forward_webhook_url',get_option('connect_api_chat_forward_webhook_url'),'url',['placeholder'=>'https://...']); ?><p class="text-muted"><?php echo _l('connect_api_chat_forward_webhook_help'); ?></p>
<button class="btn btn-primary" type="submit"><i class="fa fa-save"></i> <?php echo _l('save'); ?></button><?php echo form_close(); ?>
</div>
<div class="col-md-5">
<div class="panel_s"><div class="panel-body" style="border:1px solid #e4e8ec;border-radius:8px;box-shadow:none">
<h4><i class="fa fa-link"></i> <?php echo _l('connect_api_chat_webhook'); ?></h4><p class="text-muted"><?php echo _l('connect_api_chat_webhook_help'); ?></p>
<label>URL</label><input class="form-control" readonly value="<?php echo html_escape($webhook_url); ?>">
<p class="text-muted mtop10"><?php echo _l('connect_api_chat_webhook_health_help'); ?></p>
<a class="btn btn-default btn-sm" target="_blank" href="<?php echo html_escape($webhook_url); ?>"><i class="fa fa-heartbeat"></i> <?php echo _l('connect_api_chat_webhook_health'); ?></a>
<?php if ($webhook_error) { ?><p class="text-danger mtop10"><?php echo html_escape($webhook_error); ?></p><?php } ?>
<?php if ($connector_configured) { echo form_open(admin_url('connect_api_chat/configure_webhook'),['class'=>'mtop15']); ?><button class="btn btn-info" type="submit"><i class="fa fa-refresh"></i> <?php echo _l('connect_api_chat_webhook_configure'); ?></button><?php echo form_close(); } ?>
</div></div>
</div></div>
</div></div></div></div>
</div></div>
<?php init_tail(); ?>
