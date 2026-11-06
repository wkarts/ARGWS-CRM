<?php defined('BASEPATH')or exit('No direct script access allowed'); ?>
<?php if(!empty($channel_preferences_error)){ ?><p class="alert alert-danger">Não foi possível confirmar os meios de pagamento desta empresa. Nenhuma ativação foi presumida.</p><?php } ?>
<?php if($selected&&is_array($channel_preferences??null)){ ?>
<h2 class="tw-text-lg">Meios de pagamento da empresa</h2>
<p>API e CNAB são independentes. Habilitar permite novas cobranças somente com configuração e permissões válidas; não registra títulos nem confirma homologação bancária.</p>
<?php foreach(['api'=>'Banco do Brasil — API','cnab'=>'Banco do Brasil — CNAB']as$channel=>$label){ ?>
<div class="panel panel-default"><div class="panel-body"><h3 class="tw-text-base"><?= html_escape($label); ?></h3><p><?= $channel_preferences[$channel]?'Habilitado nesta empresa':'Desabilitado nesta empresa'; ?></p>
<?php if($can_manage){echo form_open($bbAdminUrl('banco_brasil/save_channel_preferences')); ?>
<input type="hidden" name="csrf_bb" value="<?= html_escape($csrf); ?>"><input type="hidden" name="issuer_id" value="<?= (int)$selected['id']; ?>">
<input type="hidden" name="channel" value="<?= $channel; ?>"><input type="hidden" name="enabled" value="<?= $channel_preferences[$channel]?'0':'1'; ?>"><input type="hidden" name="revision" value="<?= html_escape($channel_preferences['revision']); ?>">
<button type="submit" class="btn btn-default"><?= $channel_preferences[$channel]?'Desabilitar':'Habilitar'; ?></button><?= form_close(); } ?>
</div></div><?php } ?>
<?php } ?>
