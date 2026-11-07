<?php defined('BASEPATH') or exit('No direct script access allowed');
$bankEscape=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); ?>
<fieldset class="panel_s"><div class="panel-body">
<legend>Banco do Brasil — boleto e remessa CNAB</legend>
<div class="checkbox checkbox-primary"><input type="checkbox" id="bank-issue-enabled" name="bank_issuance[enabled]" value="1"><label for="bank-issue-enabled">Gerar boleto CNAB ao emitir a fatura</label></div>
<p>Escolha o meio Banco do Brasil — CNAB e revise os dados abaixo. Na agenda, esta opção seleciona esse meio para cada emissão. O boleto entra em um lote aberto compatível; o primeiro download fecha o lote. Gerar não significa transmitir, registrar ou receber pagamento. Rascunhos só geram boleto quando forem emitidos.</p>
<div class="form-group"><label for="bank-issue-contract">Configuração bancária declarada</label><select class="form-control" id="bank-issue-contract" name="bank_issuance[contract_id]"><option value="">Selecione uma configuração</option><?php foreach($native_bank_contracts as $bankContract){ ?><option value="<?= (int)$bankContract['id']; ?>"><?= $bankEscape($bankContract['layout_id'].' · '.$bankContract['account_id'].' · Convênio '.$bankContract['agreement_number'].' · '.$bankContract['environment']); ?></option><?php } ?></select></div>
<div class="form-group"><label for="bank-issue-timezone">Fuso da empresa (IANA)</label><input class="form-control" id="bank-issue-timezone" name="bank_issuance[timezone]" placeholder="America/Sao_Paulo" maxlength="100"><p class="text-muted">Define a data local de geração do arquivo. Não altera o calendário da recorrência.</p></div>
<?php foreach(['payer_name'=>['Nome do pagador',40],'payer_address'=>['Endereço',40],'payer_district'=>['Bairro',15],'payer_zip'=>['CEP (8 dígitos)',8],'payer_city'=>['Cidade',15],'payer_state'=>['UF',2],'species'=>['Espécie conforme contrato (2 dígitos)',2],'acceptance'=>['Aceite conforme contrato (A ou N)',1]] as $bankField=>[$bankLabel,$bankLength]){ ?>
<div class="form-group"><label for="bank-issue-<?= $bankEscape($bankField); ?>"><?= $bankEscape($bankLabel); ?></label><input class="form-control" id="bank-issue-<?= $bankEscape($bankField); ?>" name="bank_issuance[payer][<?= $bankEscape($bankField); ?>]" maxlength="<?= $bankLength; ?>"></div>
<?php } ?>
<details><summary>Declarações adicionais para CNAB400</summary><p>Somente para configuração CNAB400. Informe as referências verificadas e confirme cada declaração; nenhuma opção vem marcada.</p>
<div class="checkbox checkbox-primary"><input type="checkbox" id="bank-issue-confirm400" name="bank_issuance[confirm400]" value="1"><label for="bank-issue-confirm400">Aplicar estas declarações à geração CNAB400</label></div>
<?php foreach(['currency'=>'Moeda do contrato','interest'=>'Condição de juros','homologation'=>'Homologação do contrato'] as $bankField=>$bankLabel){ ?>
<div class="form-group"><label for="bank-issue-<?= $bankField; ?>-reference"><?= $bankEscape($bankLabel); ?> — referência</label><input class="form-control" id="bank-issue-<?= $bankField; ?>-reference" name="bank_issuance[approval400][<?= $bankField; ?>_reference]" maxlength="191"></div>
<div class="checkbox checkbox-primary"><input type="checkbox" id="bank-issue-<?= $bankField; ?>-confirmed" name="bank_issuance[approval400][<?= $bankField; ?>_confirmed]" value="1"><label for="bank-issue-<?= $bankField; ?>-confirmed">Conferi: <?= $bankEscape($bankLabel); ?></label></div>
<?php } ?></details>
<p class="text-muted">A instrução fica vinculada à emissão. Alteração de contrato, saldo incompatível ou permissão revogada bloqueia a operação para revisão, sem emitir uma fatura com boleto incompleto.</p>
</div></fieldset>
