<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<section id="card" class="panel_s"><div class="panel-body">
<h2>Cartão</h2>
<p>Configure a disponibilidade de cartão separadamente em cada ambiente da empresa. Salvar não emite nem paga uma cobrança.</p>
<?php if(!($preferences['ready']??false)){ ?>
<p class="alert alert-warning" role="status"><?= $e($translate('asaas_company_preferences_upgrade')); ?></p>
<?php }else{ $cardAccounts=[];foreach($accounts as $cardAccount)$cardAccounts[$cardAccount['environment']]=$cardAccount;
foreach(['production','sandbox'] as $cardEnvironment){$cardProfile=$preferences['profiles'][$cardEnvironment];$cardAccount=$cardAccounts[$cardEnvironment]??null; ?>
<div class="panel panel-default"><div class="panel-heading"><h3 class="panel-title"><?= $e($environmentLabel($cardEnvironment)); ?></h3></div><div class="panel-body">
<p>Conta: <?= $e($cardAccount['account_id']??'Não cadastrada'); ?></p>
<?php if($can_configure){ ?>
<form method="post" action="<?= $e($action_url); ?>">
<?php $hidden(['action'=>'save_card_settings','environment'=>$cardEnvironment,'profile_revision'=>$cardProfile['revision'],'account_revision'=>$cardAccount['revision']??\Asaas\CompanyPreferences::ZERO,'company_asaas_preferences_csrf'=>$preferences_csrf]); ?>
<div class="form-group"><label for="asaas-card-enabled-<?= $e($cardEnvironment); ?>">Disponibilizar cartão</label>
<select class="form-control" id="asaas-card-enabled-<?= $e($cardEnvironment); ?>" name="enabled"><option value="0" <?= $cardProfile['allow_card']==='0'?'selected':''; ?>>Desabilitado</option><option value="1" <?= $cardProfile['allow_card']==='1'?'selected':''; ?>>Habilitado</option></select></div>
<button class="btn btn-primary" type="submit">Salvar cartão neste ambiente</button>
</form>
<?php }else{ ?><p><?= $cardProfile['allow_card']==='1'?'Habilitado':'Desabilitado'; ?>. É necessária permissão de edição para alterar.</p><?php } ?>
</div></div>
<?php }} ?>
<p class="alert alert-info">O fluxo disponível nesta versão ainda é o checkout hospedado. Pagamento direto, tokenização, autenticação 3D Secure e voucher ainda não estão habilitados neste CRM.</p>
</div></section>
