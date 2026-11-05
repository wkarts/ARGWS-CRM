<?php defined('BASEPATH') or exit('No direct script access allowed');
require_once FCPATH.'modules/asaas/libraries/Asaas/RemoteWebhook.php';
$escapeRemote=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$remoteValues=$remote_defaults??[];
$remoteEvents=$remoteValues['events']??['PAYMENT_CONFIRMED','PAYMENT_RECEIVED'];
?>
<details class="mtop15 asaas-remote-webhook"><summary>Gerenciar webhook no Asaas</summary>
<p>Operações na conta e ambiente exibidos acima. A URL e o token são os deste recebedor. Consulte antes de alterar. Salvar as configurações locais não altera o webhook remoto.</p>
<form method="post" action="<?= $escapeRemote($remote_form_action); ?>">
<?php $remote_hidden(); ?><input type="hidden" name="remote_action" value="inspect">
<button class="btn btn-default" type="submit">Consultar webhook no Asaas</button></form>
<form method="post" action="<?= $escapeRemote($remote_form_action); ?>" autocomplete="off">
<?php $remote_hidden(); ?>
<div class="form-group"><label for="<?= $remote_prefix; ?>-name">Nome do webhook</label><input class="form-control" id="<?= $remote_prefix; ?>-name" name="remote_config[name]" maxlength="100" required value="<?= $escapeRemote($remoteValues['name']??'ARGWS CRM'); ?>"></div>
<div class="form-group"><label for="<?= $remote_prefix; ?>-email">E-mail para alertas do Asaas</label><input class="form-control" id="<?= $remote_prefix; ?>-email" name="remote_config[email]" type="email" maxlength="254" required value="<?= $escapeRemote($remoteValues['email']??''); ?>"></div>
<label for="<?= $remote_prefix; ?>-enabled">Recebimento de eventos</label><select class="form-control" id="<?= $remote_prefix; ?>-enabled" name="remote_config[enabled]"><option value="1" <?= ($remoteValues['enabled']??true)?'selected':''; ?>>Habilitado</option><option value="0" <?= ($remoteValues['enabled']??true)?'':'selected'; ?>>Desabilitado</option></select>
<fieldset><legend>Eventos enviados pelo Asaas</legend><?php foreach(\Asaas\RemoteWebhook::EVENTS as $remoteEvent){ ?><label style="display:block;overflow-wrap:anywhere"><input type="checkbox" name="remote_config[events][]" value="<?= $remoteEvent; ?>" <?= in_array($remoteEvent,$remoteEvents,true)?'checked':''; ?>> <?= $remoteEvent; ?></label><?php } ?></fieldset>
<p>Atualizar substitui os campos acima e sincroniza o token local. Uma fila interrompida só será retomada pela ação específica abaixo.</p>
<label><input type="checkbox" name="confirm" value="1" required>Confirmo estas configurações nesta conta e ambiente.</label>
<p><button class="btn btn-primary" type="submit" name="remote_action" value="create">Criar webhook no Asaas</button> <button class="btn btn-default" type="submit" name="remote_action" value="update">Atualizar webhook no Asaas</button></p></form>
<?php foreach(['resume'=>'Retomar fila interrompida','delete'=>'Excluir webhook no Asaas'] as $remoteAction=>$remoteLabel){ ?>
<form method="post" action="<?= $escapeRemote($remote_form_action); ?>"><?php $remote_hidden(); ?><input type="hidden" name="remote_action" value="<?= $remoteAction; ?>">
<p><?= $remoteAction==='resume'?'Retome somente após corrigir a causa das falhas. Eventos pendentes voltarão a ser enviados.':'A exclusão interrompe novos eventos desta URL no Asaas. Cobranças, pagamentos e histórico local são preservados.'; ?></p>
<label><input type="checkbox" name="confirm" value="1" required>Confirmo <?= $escapeRemote(mb_strtolower($remoteLabel)); ?> nesta conta.</label> <button class="btn btn-default" type="submit"><?= $remoteLabel; ?></button></form>
<?php } ?></details>
