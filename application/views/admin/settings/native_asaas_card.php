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
<h3>Pagar cobrança existente com token</h3>
<p>O token deve ter sido gerado pelo Asaas para o mesmo cliente da cobrança. Escolha o contrato correspondente ao token recebido. A operação é imediata, mesmo com vencimento futuro. Esta tela não coleta número do cartão ou CVV e não cria tokens.</p>
<?php if(($card_payments_ready??false)&&$can_issue&&$can_settle){foreach($origins as $cardOrigin){if(($cardOrigin['billing_type']??null)!=='CREDIT_CARD')continue;$cardAttempt=$card_payments[(int)$cardOrigin['id']]??null; ?>
<article class="panel panel-default"><div class="panel-body"><h4>Fatura #<?= (int)$cardOrigin['invoice_id']; ?> — <?= $e($cardOrigin['account_id'].' / '.$environmentLabel($cardOrigin['environment'])); ?></h4>
<?php if($cardAttempt){ ?><p role="status">Tentativa: <?= $e($cardAttempt['state']); ?>. Consulta confirma o estado remoto; não repete o pagamento.</p>
<form method="post" action="<?= $e($action_url); ?>"><?php $hidden(['action'=>'card_query','origin_id'=>$cardOrigin['id'],'company_asaas_preferences_csrf'=>$preferences_csrf]); ?><label><input type="checkbox" name="confirm" value="1" required> Confirmo consultar esta origem.</label><button class="btn btn-default" type="submit">Consultar tentativa</button></form>
<?php }elseif($cardOrigin['state']==='issued'&&in_array($cardOrigin['proof']['status']??'', ['PENDING','OVERDUE'],true)){ ?>
<form method="post" action="<?= $e($action_url); ?>" autocomplete="off">
<?php $hidden(['action'=>'card_pay','origin_id'=>$cardOrigin['id'],'request_key'=>hash('sha256',$card_request_key.':'.$cardOrigin['id']),'company_asaas_preferences_csrf'=>$preferences_csrf]); ?>
<div class="form-group"><label for="card-operation-<?= (int)$cardOrigin['id']; ?>">Contrato de pagamento</label><select class="form-control" name="card_operation" id="card-operation-<?= (int)$cardOrigin['id']; ?>" required><option value="">Selecione</option><option value="payWithCreditCard">Crédito — creditCardToken</option><option value="payWithCard">Cartão CREDIT — cardToken</option></select></div>
<div class="form-group"><label for="card-token-<?= (int)$cardOrigin['id']; ?>">Token do mesmo cliente</label><input class="form-control" type="password" name="card_token" id="card-token-<?= (int)$cardOrigin['id']; ?>" maxlength="512" autocomplete="new-password" required></div>
<label><input type="checkbox" name="confirm" value="1" required> Confirmo executar agora o pagamento integral desta cobrança, na conta e ambiente indicados.</label><button class="btn btn-primary" type="submit">Pagar com token via API</button></form>
<?php } ?></div></article>
<?php }}else{ ?><p>Pagamento por token exige módulo atualizado e permissões de faturas, clientes e pagamentos.</p><?php } ?>
<p>Use HTTPS. Timeout ou resposta incerta exige consulta e revisão; trocar a chave do formulário não autoriza outro envio. Captura de cartão, tokenização, 3D Secure, parcelamento, voucher e débito não estão habilitados por este fluxo.</p>
</div></section>
