<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<section id="manual" class="panel_s"><div class="panel-body">
<h2>Recebimentos manuais</h2>
<p>Parcelas recebidas fora do Asaas são registradas localmente e mantêm a cobrança aberta pelo valor original. Ao quitar o saldo, a ação abaixo será aplicada à conta da cobrança. Nenhuma opção é habilitada automaticamente.</p>
<?php if(!($manual_ready??false)){ ?><p class="alert alert-warning">Recurso indisponível. Confira permissões e atualização do módulo.</p><?php }else{ ?>
<?php foreach($accounts as $account){$policy=$manual_policies[(int)$account['id']]??null;if(!$policy)continue; ?>
<h3><?= $e($account['account_id'].' / '.$environmentLabel($account['environment'])); ?></h3>
<?php if($can_configure){ ?><form method="post" action="<?= $e($action_url); ?>">
<?php $hidden(['action'=>'save_manual_policy','account_row'=>$account['id'],'account_revision'=>$account['revision'],'policy_revision'=>$policy['revision'],'company_asaas_preferences_csrf'=>$preferences_csrf]); ?>
<div class="form-group"><label for="asaas-manual-policy-<?= (int)$account['id']; ?>">Ao quitar integralmente</label><select class="form-control" id="asaas-manual-policy-<?= (int)$account['id']; ?>" name="operation">
<?php foreach(['disabled'=>'Bloquear quitação manual integrada','cash'=>'Confirmar dinheiro recebido fora do Asaas','remove'=>'Remover a cobrança sem registrar dinheiro no Asaas'] as $value=>$label){ ?><option value="<?= $e($value); ?>" <?= $policy['operation']===$value?'selected':''; ?>><?= $e($label); ?></option><?php } ?></select></div>
<div class="form-group"><label for="asaas-manual-notify-<?= (int)$account['id']; ?>">Notificação do Asaas (somente confirmação em dinheiro)</label><select class="form-control" id="asaas-manual-notify-<?= (int)$account['id']; ?>" name="notify_customer"><option value="0" <?= !(int)$policy['notify_customer']?'selected':''; ?>>Não enviar</option><option value="1" <?= (int)$policy['notify_customer']?'selected':''; ?>>Enviar</option></select></div>
<p>Confirmar dinheiro altera a natureza da cobrança para RECEIVED_IN_CASH e não credita saldo Asaas. Remover não é reembolsar. Cobranças com sinais de disputa, estorno, assinatura ou parcelamento exigem revisão.</p>
<div class="checkbox"><input type="checkbox" id="asaas-manual-confirm-<?= (int)$account['id']; ?>" name="confirm" value="1" required><label for="asaas-manual-confirm-<?= (int)$account['id']; ?>">Confirmo a política desta conta e ambiente.</label></div><button class="btn btn-primary" type="submit">Salvar política</button>
</form><?php }else{ ?><p>Política: <?= $e($policy['operation']); ?>. Edição exige permissão de configurações.</p><?php } ?>
<?php } ?>
<h3>Recebimentos integrais pendentes sob sua identidade</h3>
<p>O comando enviado não será repetido. A consulta confirma o resultado remoto e só então conclui o recibo nativo. Alterações de permissão, conta ou saldo podem exigir revisão.</p>
<?php foreach($manual_pending as $pending){ ?><form method="post" action="<?= $e($action_url); ?>" class="mbot15">
<?php $hidden(['action'=>'recover_manual','request_key'=>$pending['request_key'],'company_asaas_preferences_csrf'=>$preferences_csrf]); ?>
<p>Fatura #<?= (int)$pending['invoice_id']; ?> · <?= $e($pending['operation'].' / '.$pending['state']); ?></p>
<div class="checkbox"><input type="checkbox" name="confirm" value="1" required id="asaas-manual-recover-<?= (int)$pending['origin_id']; ?>"><label for="asaas-manual-recover-<?= (int)$pending['origin_id']; ?>">Confirmo a consulta e conclusão do recebimento já solicitado.</label></div><button class="btn btn-default" type="submit">Consultar e concluir</button></form><?php } ?>
<?php } ?></div></section>
