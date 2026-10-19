<?php defined('BASEPATH') or exit('No direct script access allowed');
$company_page_title='Faturas da empresa';require APPPATH.'views/admin/companies/layout_head.php';
$esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$url=fn($q='')=>CompanyRequestScope::adminUrl($this,'company_invoices'.$q);
$hidden=function($action)use($esc,$company_invoices_csrf){echo '<input type="hidden" name="action" value="'.$esc($action).'"><input type="hidden" name="company_invoices_csrf" value="'.$esc($company_invoices_csrf).'">';};
$labels=[1=>'Em aberto',2=>'Paga',3=>'Parcialmente paga',4=>'Vencida',5=>'Cancelada',6=>'Rascunho'];
?>
<section class="company-settings-card" aria-labelledby="invoice-title"><h1 id="invoice-title">Faturas da empresa</h1>
<?php if($error){?><p role="alert" class="alert alert-danger"><?= $esc($error); ?></p><p><a href="<?= $esc($url()); ?>">Reabrir a lista</a></p><?php } ?>
<?php if($document){$id=$document['invoice_id'];?>
<h2><?= $esc($document['number']); ?> — <?= $esc($labels[$document['status']]); ?></h2>
<p><?= $esc($document['identity']['legal_name']); ?> → <?= $esc($document['recipient']['name']); ?></p>
<p>Emissão <?= $esc($document['date']); ?> · Vencimento <?= $esc($document['due_date']); ?> · <?= $esc($document['currency'].' '.$document['totals']['total']); ?></p>
<p><a href="<?= $esc($url('?invoice='.$id.'&pdf=1')); ?>">Baixar PDF da fatura</a></p>
<div class="table-responsive"><table class="table"><thead><tr><th>Descrição</th><th>Quantidade</th><th>Preço unitário</th><th>Valor</th></tr></thead><tbody>
<?php foreach($document['items'] as $item){?><tr><td><?= $esc($item['description']); ?></td><td><?= $esc($item['quantity']); ?></td><td><?= $esc($item['rate']); ?></td><td><?= $esc($item['amount']); ?></td></tr><?php } ?></tbody></table></div>
<p>Subtotal <?= $esc($document['totals']['subtotal']); ?> · Tributos <?= $esc($document['totals']['total_tax']); ?> · Total <?= $esc($document['totals']['total']); ?></p>
<h3>Recebimentos</h3>
<?php if(!$payments){?><p>Nenhum recebimento registrado nesta fatura.</p><?php }else{?><table class="table"><thead><tr><th>Data</th><th>Valor</th><th>Referência</th></tr></thead><tbody><?php foreach($payments as $payment){?><tr><td><?= $esc($payment['date']); ?></td><td><?= $esc($payment['amount']); ?></td><td><?= $esc($payment['transactionid']); ?></td></tr><?php } ?></tbody></table><?php } ?>
<?php if($can_payment&&in_array($document['status'],[1,3,4],true)){?>
<h3>Registrar recebimento manual</h3><p>Registre somente um recebimento comprovado por você. Esta ação não consulta o banco. Fatura com origem bancária exige a revisão da integração de origem.</p>
<?= form_open($url());$hidden('payment'); ?><input type="hidden" name="invoice_id" value="<?= $id; ?>"><input type="hidden" name="request_key" value="<?= $esc($request_key); ?>">
<label for="payment-amount">Valor recebido (decimal com ponto)</label><input class="form-control" id="payment-amount" name="payment[amount]" inputmode="decimal" required pattern="[0-9]+(\.[0-9]{1,2})?">
<label for="payment-date">Data do recebimento</label><input class="form-control" id="payment-date" type="date" name="payment[date]" value="<?= date('Y-m-d'); ?>" required>
<label for="payment-reference">Referência do comprovante</label><input class="form-control" id="payment-reference" name="payment[reference]" maxlength="191" required>
<label for="payment-note">Motivo / evidência</label><textarea class="form-control" id="payment-note" name="payment[note]" maxlength="4000"></textarea><button type="submit">Registrar recebimento</button><?= form_close(); ?>
<?php } ?>
<?php if($can_edit&&in_array($document['status'],[1,4],true)&&!$payments){?>
<h3>Editar fatura sem movimento</h3><p>O cliente, a moeda, o número e as identidades emitidas são preservados. Uma origem bancária ou movimento financeiro impede esta alteração.</p>
<?= form_open($url());$hidden('update'); ?><input type="hidden" name="invoice_id" value="<?= $id; ?>"><input type="hidden" name="revision" value="<?= $esc($revision); ?>"><input type="hidden" name="invoice[client_id]" value="<?= $document['recipient']['client_id']; ?>">
<?php $currencyId=null;foreach($currencies as $currency)if($currency['name']===$document['currency'])$currencyId=$currency['id']; ?>
<input type="hidden" name="invoice[currency]" value="<?= (int)$currencyId; ?>">
<label for="edit-invoice-date">Emissão</label><input id="edit-invoice-date" class="form-control" type="date" name="invoice[date]" value="<?= $esc($document['date']); ?>" required><label for="edit-invoice-due">Vencimento</label><input id="edit-invoice-due" class="form-control" type="date" name="invoice[due_date]" value="<?= $esc($document['due_date']); ?>" required>
<?php foreach($document['items'] as $index=>$item){?><fieldset><legend>Item <?= $index+1; ?></legend>
<?php foreach(['description'=>['Descrição',$item['description']],'qty'=>['Quantidade',$item['quantity']],'rate'=>['Preço unitário',$item['rate']],'taxname'=>['Nome do tributo',$item['taxes'][0]['name']??''],'taxrate'=>['Alíquota %',$item['taxes'][0]['rate']??'0.00']] as $field=>[$label,$value]){?><label for="edit-item-<?= $index.'-'.$field; ?>"><?= $esc($label); ?></label><input id="edit-item-<?= $index.'-'.$field; ?>" class="form-control" name="invoice[items][<?= $index; ?>][<?= $field; ?>]" value="<?= $esc($value); ?>" <?= $field!=='taxname'?'required':''; ?>><?php } ?></fieldset><?php } ?>
<label for="edit-invoice-note">Observação</label><textarea id="edit-invoice-note" class="form-control" name="invoice[note]" maxlength="20000"><?= $esc($document['note']); ?></textarea><button type="submit">Salvar fatura</button><?= form_close(); ?>
<?= form_open($url());$hidden('cancel'); ?><input type="hidden" name="invoice_id" value="<?= $id; ?>"><input type="hidden" name="revision" value="<?= $esc($revision); ?>"><p>Cancelar conserva o registro e a numeração. Não solicita baixa bancária.</p><button type="submit">Cancelar fatura sem movimento</button><?= form_close(); ?>
<?php } ?>
<p><a href="<?= $esc($url()); ?>">Voltar à lista</a></p><?php } ?>
<h2>Lista da empresa</h2><div class="table-responsive"><table class="table"><thead><tr><th>Número</th><th>Emissão</th><th>Vencimento</th><th>Total</th><th>Estado</th></tr></thead><tbody>
<?php foreach($directory['items'] as $row){?><tr><td><a href="<?= $esc($url('?invoice='.(int)$row['id'])); ?>"><?= $esc($row['formatted_number']); ?></a></td><td><?= $esc($row['date']); ?></td><td><?= $esc($row['duedate']); ?></td><td><?= $esc($row['currency'].' '.$row['total']); ?></td><td><?= $esc($labels[(int)$row['status']]??'Revisão'); ?></td></tr><?php } ?></tbody></table></div>
<?php if($directory['next']!==null){?><a href="<?= $esc($url('?after='.$directory['next'])); ?>">Próxima página</a><?php } ?>
<?php if($can_create){?>
<h2>Emitir fatura</h2><p>Complete antes a identidade e o endereço da empresa nas Definições. Este formulário emite fatura com itens e tributo por item; não gera boleto ou transmite cobrança bancária.</p>
<?= form_open($url());$hidden('create'); ?><input type="hidden" name="request_key" value="<?= $esc($request_key); ?>">
<label for="invoice-client">Cliente autorizado</label><select class="form-control" id="invoice-client" name="invoice[client_id]" required><option value="">Selecione</option><?php foreach($clients as $client){?><option value="<?= $client['userid']; ?>"><?= $esc($client['company']); ?></option><?php } ?></select>
<label for="invoice-currency">Moeda</label><select class="form-control" id="invoice-currency" name="invoice[currency]" required><?php foreach($currencies as $currency){?><option value="<?= (int)$currency['id']; ?>"><?= $esc($currency['name']); ?></option><?php } ?></select>
<label for="invoice-date">Emissão</label><input class="form-control" id="invoice-date" type="date" name="invoice[date]" value="<?= date('Y-m-d'); ?>" required><label for="invoice-due">Vencimento</label><input class="form-control" id="invoice-due" type="date" name="invoice[due_date]" value="<?= date('Y-m-d',time()+30*86400); ?>" required>
<div id="invoice-items"><fieldset data-item-index="0"><legend>Item 1</legend>
<?php foreach(['description'=>['Descrição','',true],'qty'=>['Quantidade','1.00',true],'rate'=>['Preço unitário','',true],'taxname'=>['Nome do tributo','',false],'taxrate'=>['Alíquota %','0.00',true]] as $field=>[$label,$value,$required]){?><label for="invoice-item-0-<?= $field; ?>"><?= $esc($label); ?></label><input class="form-control" id="invoice-item-0-<?= $field; ?>" name="invoice[items][0][<?= $field; ?>]" value="<?= $esc($value); ?>" <?= $required?'required':''; ?>><?php } ?></fieldset></div>
<button type="button" id="invoice-add-item">Adicionar item</button><label for="invoice-note">Observação</label><textarea id="invoice-note" class="form-control" name="invoice[note]" maxlength="20000"></textarea><button type="submit">Emitir fatura da empresa</button><?= form_close(); ?>
<?php } ?>
</section><script>
(()=>{const add=document.getElementById('invoice-add-item'),items=document.getElementById('invoice-items');if(!add||!items)return;add.addEventListener('click',()=>{const index=items.children.length;if(index>=100){add.disabled=true;return;}const copy=items.firstElementChild.cloneNode(true);copy.dataset.itemIndex=index;copy.querySelector('legend').textContent='Item '+(index+1);copy.querySelectorAll('input').forEach(input=>{const old=input.id;input.id=old.replace('item-0-','item-'+index+'-');input.name=input.name.replace('[0]','['+index+']');copy.querySelector('label[for="'+old+'"]').htmlFor=input.id;input.value=input.name.includes('[qty]')?'1.00':(input.name.includes('[taxrate]')?'0.00':'');});items.appendChild(copy);copy.querySelector('input').focus();});})();
</script><?php require APPPATH.'views/admin/companies/layout_tail.php'; ?>
