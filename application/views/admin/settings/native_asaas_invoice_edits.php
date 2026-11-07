<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if($invoice_edit_ready??false){ ?><div class="panel_s"><div class="panel-body"><h3>Edições de faturas pendentes</h3>
<p>A consulta abaixo conclui a edição já solicitada pelo seu usuário. O envio ao Asaas não será repetido. A fatura permanece com os dados anteriores até confirmar o resultado remoto e a gravação local.</p>
<?php foreach($invoice_edit_pending as $edit){ ?><form method="post" action="<?= $e($action_url); ?>" class="mbot15">
<?php $hidden(['action'=>'recover_invoice_edit','request_key'=>$edit['request_key'],'company_asaas_preferences_csrf'=>$preferences_csrf]); ?>
<p>Fatura #<?= (int)$edit['invoice_id']; ?> · <?= $e($edit['state']); ?></p>
<div class="checkbox"><input type="checkbox" id="asaas-edit-confirm-<?= $e($edit['request_key']); ?>" name="confirm" value="1" required><label for="asaas-edit-confirm-<?= $e($edit['request_key']); ?>">Confirmo consultar e concluir esta edição.</label></div><button type="submit" class="btn btn-default">Consultar e concluir edição</button></form><?php } ?>
<?php if(!$invoice_edit_pending){ ?><p>Nenhuma edição pendente sob sua identidade.</p><?php } ?></div></div><?php } ?>
