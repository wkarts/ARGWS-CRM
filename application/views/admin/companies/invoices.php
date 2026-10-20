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
<?php foreach($document['items'] as $item){?><tr><td><?= $esc($item['description']); ?><?php if(($item['details']??'')!==''){ ?><pre style="white-space:pre-wrap"><?= $esc($item['details']); ?></pre><?php } ?></td><td><?= $esc($item['quantity'].' '.($item['unit']??'')); ?></td><td><?= $esc($item['rate']); ?></td><td><?= $esc($item['amount']); ?></td></tr><?php } ?></tbody></table></div>
<p>Subtotal <?= $esc($document['totals']['subtotal']); ?> · Tributos <?= $esc($document['totals']['total_tax']); ?> · Desconto <?= $esc($document['totals']['discount_total']); ?> · Ajuste <?= $esc($document['totals']['adjustment']); ?> · Total <?= $esc($document['totals']['total']); ?></p>
<?php if($can_create&&!empty($clients)){ ?><p><a id="invoice-reviewed-copy" href="<?= $esc(CompanyRequestScope::adminUrl($this,'company_invoice_copies?source='.$id)); ?>">Revisar cópia para nova emissão numerada</a></p><?php } ?>
<h3>Anexos privados</h3>
<p>Downloads exigem acesso à fatura. A exibição no portal é uma escolha explícita por arquivo e ainda exige login e permissão do contato.</p>
<?php if($attachment_files){ ?><ul aria-label="Anexos da fatura"><?php foreach($attachment_files as $file){ ?><li><a href="<?= $esc($url('?invoice='.$id.'&attachment='.$file['file_key'])); ?>"><?= $esc($file['filename']); ?></a> · <?= (int)$file['byte_size']; ?> bytes · <?= (int)$file['portal_visible']===1?'Disponível no portal autorizado':'Somente equipe'; ?>
<?php if($file['mime']==='image/png'){ ?><a href="<?= $esc($url('?invoice='.$id.'&attachment='.$file['file_key'].'&preview=1')); ?>">Visualizar PNG</a><?php } ?>
<?php if($can_edit){ ?><?= form_open($url());$hidden('attachment_hide'); ?><input type="hidden" name="invoice_id" value="<?= $id; ?>"><input type="hidden" name="file_key" value="<?= $esc($file['file_key']); ?>"><input type="hidden" name="content_hash" value="<?= $esc($file['content_hash']); ?>"><button type="submit">Ocultar anexo; preservar histórico</button><?= form_close(); ?><?php } ?></li><?php } ?></ul><?php }else{ ?><p>Nenhum anexo ativo.</p><?php } ?>
<?php if($can_edit&&in_array($document['status'],[1,2,3,4],true)){ ?>
<?= form_open_multipart($url());$hidden('attachment_upload'); ?><input type="hidden" name="invoice_id" value="<?= $id; ?>"><input type="hidden" name="request_key" value="<?= $esc($request_key); ?>">
<label for="invoice-attachment">PDF (até2 MiB) ou PNG/JPEG (até512 KiB e1600 pixels)</label><input class="form-control" id="invoice-attachment" type="file" name="invoice_attachment" accept=".pdf,.png,.jpg,.jpeg" required>
<label><input type="checkbox" name="portal_visible" value="1"> Disponibilizar este arquivo no portal deste cliente</label><p>Até20 uploads e20 MiB por fatura no histórico, incluindo ocultos. Imagens são regravadas em PNG; PDFs são entregues como download. Ocultar revoga downloads e conserva a auditoria.</p><button type="submit">Adicionar anexo privado</button><?= form_close(); ?>
<?php } ?>
<?php if($can_send){ ?>
<h3>Notificar no portal</h3>
<p>O contato precisa pertencer a esta empresa e ter acesso a faturas no portal. A mensagem usa o template e o transporte da empresa. O link exige login e permissão; nenhum PDF público é criado.</p>
<?php if($delivery_blocked){ ?><p>Notificação indisponível para este destinatário. Compartilhamento de identidade não compartilha contatos.</p><?php }elseif($delivery){ ?>
<?php if($delivery['recipients']){ ?>
<?= form_open($url());$hidden('notify'); ?><input type="hidden" name="invoice_id" value="<?= $id; ?>"><input type="hidden" name="revision" value="<?= $esc($revision); ?>"><input type="hidden" name="request_key" value="<?= $esc($request_key); ?>">
<label for="invoice-notify-contact">Contato privado autorizado</label><select class="form-control" id="invoice-notify-contact" name="contact_id" required><option value="">Selecione</option><?php foreach($delivery['recipients'] as $recipient){ ?><option value="<?= (int)$recipient['id']; ?>"><?= $esc(trim($recipient['firstname'].' '.$recipient['lastname']).' — '.$recipient['email']); ?></option><?php } ?></select>
<p>Agendar confirma somente a gravação na fila. O transporte SMTP e a leitura pelo destinatário são etapas distintas. Mudança de fatura, contato, permissão ou funcionário antes do processamento impede o envio.</p><button type="submit">Agendar notificação da fatura</button><?= form_close(); ?>
<?php }else{ ?><p>Nenhum contato elegível. Confira a permissão de faturas no cadastro privado de contatos e o estado da fatura.</p><?php } ?>
<?php if($delivery['history']){ ?><ul aria-label="Estado das notificações"><?php $deliveryLabels=['pending'=>'Na fila','sending'=>'Em processamento','sent'=>'Aceita pelo transporte; leitura não confirmada','failed'=>'Falha ou revalidação recusada; confira antes de reagendar','archived'=>'Registro preservado; fila já removida'];foreach($delivery['history'] as $notification){ ?><li><?= $esc($notification['created_at'].' · '.($deliveryLabels[$notification['status']]??'Revisão necessária')); ?></li><?php } ?></ul><?php } ?>
<?php } ?>
<?php } ?>
<?php if(($document['position']['noncash_reductions']??'0.00')!=='0.00'){ ?><h3>Posição do principal</h3><p>Principal recebido <?= $esc($document['currency'].' '.$document['position']['received']); ?> · Crédito aplicado <?= $esc($document['position']['applied_credit']); ?> · Desconto na liquidação <?= $esc($document['position']['settlement_discount']); ?> · Abatimento na liquidação <?= $esc($document['position']['settlement_abatement']); ?> · <?= $document['status']===5?'Principal residual histórico (fatura cancelada; sem cobrança vigente)':'Principal em aberto'; ?> <?= $esc($document['position']['receivable']); ?></p><p>Desconto e abatimento extinguem principal sem recebimento. O total original da fatura é preservado.</p><?php } ?>
<?php if(($document['position']['received_external_reversed']??'0.00')!=='0.00'){ ?><h3>Reversões externas declaradas</h3><?php foreach(($document['position']['reversals']??[]) as $reversal){ if(($reversal['source_type']??'')==='asaas_full_declared'){ ?><p><a href="<?= $esc(CompanyRequestScope::adminUrl($this,'company_post_refund?invoice='.$id)); ?>">Revisar cancelamento local após reembolso integral Asaas</a> <a href="<?= $esc(CompanyRequestScope::adminUrl($this,'company_post_refund_manual?invoice='.$id)); ?>">Revisar novo recebimento manual independente</a></p><?php break; }} ?><p>Principal recebido originalmente <?= $esc($document['currency'].' '.$document['position']['received_original']); ?> · Principal revertido <?= $esc($document['position']['received_external_reversed']); ?> · Principal recebido vigente <?= $esc($document['position']['received']); ?> · Desconto restabelecido <?= $esc($document['position']['settlement_discount_reversed']); ?> · Abatimento restabelecido <?= $esc($document['position']['settlement_abatement_reversed']); ?> · <?= $document['status']===5?'Principal residual histórico (fatura cancelada; sem cobrança vigente)':'Principal em aberto'; ?> <?= $esc($document['position']['receivable']); ?></p><p>A declaração registra evidência externa conferida. Não executa estorno no banco nem altera os recibos originais.</p><?php } ?>
<h3>Recebimentos</h3>
<?php if(!$payments){?><p>Nenhum recebimento registrado nesta fatura.</p><?php }else{?><table class="table"><thead><tr><th>Data</th><th>Principal original / vigente</th><th>Referência e estornos</th></tr></thead><tbody><?php foreach($payments as $payment){?><tr><td><?= $esc($payment['date']); ?></td><td><?= $esc(($payment['original_amount']??$payment['amount']).' / '.($payment['effective_amount']??$payment['amount'])); ?></td><td><?= $esc($payment['transactionid']); ?>
<?php if(!empty($payment['bank_reversal'])){$external=$payment['bank_reversal']; ?><p><?= $esc('Reversão externa declarada '.$external['book_date'].' · principal '.$external['cash_principal_reversed'].' · '.$external['external_reference']); ?></p><?php } ?>
<?php foreach(($payment['reversals']??[]) as $reversal){?><p><?= $esc('Estorno '.$reversal['book_date'].' · '.$reversal['amount'].' · '.$reversal['reference'].' · '.$reversal['reason']); ?></p><?php } ?>
<?php if(($can_reverse_payment??false)&&$payment['paymentmode']==='company_manual'&&\CompanyInvoiceAmounts::minor((string)$payment['amount'])>0){ ?>
<?= form_open($url());$hidden('reverse_payment'); ?><input type="hidden" name="invoice_id" value="<?= $id; ?>"><input type="hidden" name="request_key" value="<?= $esc(hash('sha256',$request_key.'-reverse-'.$payment['id'])); ?>"><input type="hidden" name="reversal[payment_id]" value="<?= (int)$payment['id']; ?>">
<label>Valor do estorno<input name="reversal[amount]" inputmode="decimal" pattern="[0-9]+(\.[0-9]{1,2})?" required></label><label>Data<input type="date" name="reversal[date]" value="<?= date('Y-m-d'); ?>" required></label><label>Referência do estorno<input name="reversal[reference]" maxlength="191" required></label><label>Motivo<input name="reversal[reason]" maxlength="4000" required></label><p>Registra uma correção contábil local e conserva o lançamento original. Não movimenta dinheiro no banco.</p><button type="submit">Registrar estorno manual</button><?= form_close(); ?><?php } ?></td></tr><?php } ?></tbody></table><?php } ?>
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
<div class="company-invoice-items" id="edit-invoice-items">
<?php foreach(($editor_items??$document['items']) as $index=>$item){$editing=true;require APPPATH.'views/admin/companies/invoice_item_fields.php';} ?>
</div><button type="button" data-invoice-add="edit-invoice-items">Adicionar item</button>
<?php $fieldPrefix='edit-';require APPPATH.'views/admin/companies/invoice_adjustment_fields.php'; ?>
<label for="edit-invoice-note">Observação</label><textarea id="edit-invoice-note" class="form-control" name="invoice[note]" maxlength="20000"><?= $esc($document['note']); ?></textarea><button type="submit">Salvar fatura</button><?= form_close(); ?>
<?= form_open($url());$hidden('cancel'); ?><input type="hidden" name="invoice_id" value="<?= $id; ?>"><input type="hidden" name="revision" value="<?= $esc($revision); ?>"><p>Cancelar conserva o registro e a numeração. Não solicita baixa bancária.</p><button type="submit">Cancelar fatura sem movimento</button><?= form_close(); ?>
<?php } ?>
<p><a href="<?= $esc($url()); ?>">Voltar à lista</a></p><?php } ?>
<h2>Lista da empresa</h2><div class="table-responsive"><table class="table"><thead><tr><th>Número</th><th>Emissão</th><th>Vencimento</th><th>Total</th><th>Estado</th></tr></thead><tbody>
<?php foreach($directory['items'] as $row){?><tr><td><a href="<?= $esc($url('?invoice='.(int)$row['id'])); ?>"><?= $esc($row['formatted_number']); ?></a></td><td><?= $esc($row['date']); ?></td><td><?= $esc($row['duedate']); ?></td><td><?= $esc($row['currency'].' '.$row['total']); ?></td><td><?= $esc($labels[(int)$row['status']]??'Revisão'); ?></td></tr><?php } ?></tbody></table></div>
<?php if($directory['next']!==null){?><a href="<?= $esc($url('?after='.$directory['next'])); ?>">Próxima página</a><?php } ?>
<?php if($can_create){?>
<h2>Emitir fatura</h2><p>Complete antes a identidade e o endereço da empresa nas Definições. Este formulário emite fatura com itens, múltiplos tributos, descontos e ajustes; não gera boleto ou transmite cobrança bancária.</p>
<?= form_open($url());$hidden('create'); ?><input type="hidden" name="request_key" value="<?= $esc($request_key); ?>">
<label for="invoice-client">Cliente autorizado</label><select class="form-control" id="invoice-client" name="invoice[client_id]" required><option value="">Selecione</option><?php foreach($clients as $client){?><option value="<?= $client['userid']; ?>"><?= $esc($client['company']); ?></option><?php } ?></select>
<label for="invoice-currency">Moeda</label><select class="form-control" id="invoice-currency" name="invoice[currency]" required><?php foreach($currencies as $currency){?><option value="<?= (int)$currency['id']; ?>"><?= $esc($currency['name']); ?></option><?php } ?></select>
<label for="invoice-date">Emissão</label><input class="form-control" id="invoice-date" type="date" name="invoice[date]" value="<?= date('Y-m-d'); ?>" required><label for="invoice-due">Vencimento</label><input class="form-control" id="invoice-due" type="date" name="invoice[due_date]" value="<?= date('Y-m-d',time()+30*86400); ?>" required>
<div class="company-invoice-items" id="invoice-items"><?php $index=0;$item=['description'=>'','quantity'=>'1.00','rate'=>'','taxes'=>[]];$editing=false;require APPPATH.'views/admin/companies/invoice_item_fields.php'; ?></div>
<button type="button" id="invoice-add-item" data-invoice-add="invoice-items">Adicionar item</button>
<?php $fieldPrefix='';$adjustments=[];require APPPATH.'views/admin/companies/invoice_adjustment_fields.php'; ?>
<label for="invoice-note">Observação</label><textarea id="invoice-note" class="form-control" name="invoice[note]" maxlength="20000"></textarea><button type="submit">Emitir fatura da empresa</button><?= form_close(); ?>
<?php } ?>
</section><script>
(()=>{
  const renumber=(field,index)=>{
    const previous=field.dataset.itemIndex;field.dataset.itemIndex=index;
    field.querySelector('legend').textContent='Item '+(index+1);
    field.querySelectorAll('input,textarea,select').forEach(input=>{
      const old=input.id;input.id=old.replace('item-'+previous+'-','item-'+index+'-');
      input.name=input.name.replace('[items]['+previous+']','[items]['+index+']');
      field.querySelectorAll('label').forEach(label=>{if(label.htmlFor===old)label.htmlFor=input.id;});
    });
  };
  document.addEventListener('change',event=>{if(!event.target.matches('[data-invoice-line-choice]'))return;const field=event.target.closest('[data-item-index]'),choice=event.target.value;field.querySelector('input[name$="[is_optional]"]').value=choice==='required'?'0':'1';field.querySelector('input[name$="[is_selected]"]').value=choice==='unselected'?'0':'1';});
  document.addEventListener('click',event=>{
    const add=event.target.closest('[data-invoice-add]');
    if(add){
      const items=document.getElementById(add.dataset.invoiceAdd);if(items.children.length>=100)return;
      const copy=items.firstElementChild.cloneNode(true);renumber(copy,items.children.length);
      copy.querySelectorAll('input,textarea').forEach(input=>input.value=input.name.endsWith('[qty]')?'1.00':input.name.endsWith('[is_optional]')?'0':input.name.endsWith('[is_selected]')?'1':(input.name.endsWith('[rate]')&&input.name.includes('[taxes]')?'0.00':''));copy.querySelector('[data-invoice-line-choice]').value='required';
      copy.querySelectorAll('[data-tax-row]').forEach((row,i)=>{if(i>0)row.remove();});
      items.appendChild(copy);copy.querySelector('input').focus();return;
    }
    const taxAdd=event.target.closest('[data-add-tax]');
    if(taxAdd){
      const field=taxAdd.closest('[data-item-index]'),rows=field.querySelector('[data-taxes]');if(rows.children.length>=10)return;
      const copy=rows.firstElementChild.cloneNode(true),index=rows.children.length;
      copy.querySelectorAll('input').forEach(input=>{
        const old=input.id;input.id=old.replace(/tax-\d+-/,'tax-'+index+'-');
        input.name=input.name.replace(/\[taxes\]\[\d+\]/,'[taxes]['+index+']');
        copy.querySelectorAll('label').forEach(label=>{if(label.htmlFor===old)label.htmlFor=input.id;});
        input.value=input.name.endsWith('[rate]')?'0.00':'';
      });rows.appendChild(copy);copy.querySelector('input').focus();
    }
  });
})();
</script><?php require APPPATH.'views/admin/companies/layout_tail.php'; ?>
