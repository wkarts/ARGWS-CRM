<?php

if (!isset($document) || !$document instanceof InvoiceIssuerDocumentData) {
    throw new DomainException('issuer_document_authorized_dto_required');
}
$data = $document->data();
$escape = static fn ($value): string => htmlspecialchars((string)$value,ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8');
$lines = static fn ($value): string => nl2br($escape($value));
$money = static function (string $value) use ($data, $escape): string {
    $negative = strpos($value,'-') === 0;
    $parts = explode('.',ltrim($value,'-'));
    $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $parts[0]);
    return ($negative ? '-' : '') . $escape($data['currency']) . ' ' . $whole . ',' . $parts[1];
};
$address = static function (array $value) use ($escape, $lines): string {
    return implode('<br />',array_map($lines,array_values(array_filter($value,static fn ($line)=>$line !== ''))));
};
$date = static function (string $value): string { $parts = explode('-', $value); return $parts[2] . '/' . $parts[1] . '/' . $parts[0]; };
$statuses = [1=>'Em aberto',2=>'Paga',3=>'Parcialmente paga',4=>'Vencida',5=>'Cancelada',6=>'Rascunho'];
$identity = $data['identity'];
$layout=CompanyInvoicePresentation::layout($identity,'invoices');
?>
<?php if ($identity['logo_inline'] !== '') { ?><img width="<?= $layout['logo_width']; ?>" src="<?= $escape($identity['logo_inline']); ?>"><br /><?php } ?>
<h1>FATURA <?= $escape($data['number']); ?></h1>
<?php if($layout['show_status']){ ?><?= $escape($statuses[$data['status']]); ?><br /><?php } ?>
Emissão: <?= $escape($date($data['date'])); ?><br />
<?php if($data['due_date']!==null){ ?>Vencimento: <?= $escape($date($data['due_date'])); ?><br /><?php } ?>
<?php
$sender='<b>'.$escape($identity['legal_name']).'</b><br />'.$escape($identity['tax_id']).'<br />'.$address(array_intersect_key($identity,array_flip(['address','city','state','postcode','country_code','phone','email'])));
$recipient='<b>Destinatário</b><br />'.$escape($data['recipient']['name']).($data['recipient']['tax_id']!==''?'<br />'.$escape($data['recipient']['tax_id']):'').'<br />'.$address($data['recipient']['billing']);
if($data['recipient']['shipping']!==null)$recipient.='<br /><b>Entrega</b><br />'.$address($data['recipient']['shipping']);
echo CompanyPdfLayout::parties($sender,$recipient,$layout);
?>
<br />
<table cellpadding="5" cellspacing="0" border="0" width="100%">
<thead><tr style="background-color:<?= $escape($layout['heading_color']); ?>;color:<?= $escape($layout['heading_text_color']); ?>;font-weight:bold;">
<th width="43%">Descrição</th><th width="13%" align="right"><?= $escape([1=>'Qtd.',2=>'Horas',3=>'Qtd./Horas'][$data['quantity_mode']]) ?></th><th width="22%" align="right">Valor unitário</th><th width="22%" align="right">Valor</th>
</tr></thead><tbody>
<?php foreach ($data['items'] as $item) { ?>
<tr>
<td width="43%"><b><?= $lines($item['description']) ?></b>
<?php if ($item['details'] !== '') { ?><br /><?= $lines($item['details']) ?><?php } ?>
<?php foreach ($item['taxes'] as $tax) { ?><br /><span style="font-size:8pt;color:#525252"><?= $escape($tax['name']) ?>: <?= $escape(str_replace('.', ',', $tax['rate'])) ?>%</span><?php } ?>
</td>
<td width="13%" align="right"><?= $escape(str_replace('.', ',', $item['quantity'])) ?> <?= $escape($item['unit']) ?></td>
<td width="22%" align="right"><?= $money($item['rate']) ?></td>
<td width="22%" align="right"><?= $money($item['amount']) ?></td>
</tr>
<?php } ?>
</tbody></table>
<br />
<table cellpadding="5" width="100%">
<?php foreach (['subtotal'=>'Subtotal','total_tax'=>'Tributos','discount_total'=>'Desconto','adjustment'=>'Ajuste','total'=>'Total'] as $field=>$label) { if($field==='receivable'&&$data['status']===5)$label='Principal residual histórico (fatura cancelada; sem cobrança vigente)'; ?>
<tr<?= $field === 'total' ? ' style="background-color:#e8edf2;font-weight:bold;"' : '' ?>>
<td width="72%" align="right"><?= $label ?></td><td width="28%" align="right"><?= $field === 'discount_total' && $data['totals'][$field] !== '0.00' ? '-' : '' ?><?= $money($data['totals'][$field]) ?></td>
</tr>
<?php } ?>
</table>
<?php if (($data['position']['noncash_reductions'] ?? '0.00') !== '0.00' || ($data['position']['received_external_reversed'] ?? '0.00') !== '0.00' || ($data['position']['settlement_discount_reversed'] ?? '0.00') !== '0.00' || ($data['position']['settlement_abatement_reversed'] ?? '0.00') !== '0.00') { ?>
<br /><b>Posição do principal na geração deste documento</b>
<table cellpadding="5" width="100%">
<?php foreach (['received_original'=>'Principal recebido originalmente','received_reversed'=>'Principal revertido','received'=>'Principal recebido vigente','applied_credit'=>'Crédito aplicado','settlement_discount'=>'Desconto na liquidação vigente (sem recebimento)','settlement_abatement'=>'Abatimento na liquidação vigente (sem recebimento)','settlement_discount_reversed'=>'Desconto restabelecido por reversão','settlement_abatement_reversed'=>'Abatimento restabelecido por reversão','receivable'=>'Principal em aberto'] as $field=>$label) { if($field==='receivable'&&$data['status']===5)$label='Principal residual histórico (fatura cancelada; sem cobrança vigente)'; ?>
<tr><td width="72%" align="right"><?= $escape($label) ?></td><td width="28%" align="right"><?= $money($data['position'][$field]) ?></td></tr>
<?php } ?></table>
<?php if(($data['position']['received_external_reversed']??'0.00')!=='0.00'){ ?><p>Reversão externa declarada com evidência conferida; esta declaração não executa estorno no banco.</p><?php } ?>
<?php } ?>
<?php if(!empty($data['position']['bank_tariffs'])){ ?><br /><b>Recebimento integral com tarifa bancária separada</b><?php foreach($data['position']['bank_tariffs']as$tariff){ ?><p>Principal recebido do cliente: <?= $money($tariff['principal_received']); ?> · Tarifa deduzida observada: <?= $money($tariff['tariff_deducted']); ?> · Líquido bancário observado: <?= $money($tariff['bank_net']); ?>.</p><?php } ?><p>A tarifa não reduz o principal recebido e não cria despesa fiscal automaticamente.</p><?php } ?>
<?php if ($data['note'] !== '') { ?><br /><b>Observações</b><br /><?= $lines($data['note']) ?><?php } ?>
<?php if ($data['terms'] !== '') { ?><br /><br /><b>Condições</b><br /><?= $lines($data['terms']) ?><?php } ?>

<?php if($layout['show_transactions']){if(!array_key_exists('transactions',$data))throw new DomainException('issuer_document_invalid_content'); ?>
<br /><br /><b>Pagamentos registrados</b>
<table cellpadding="5" width="100%"><thead><tr><th>Data / referência</th><th>Original</th><th>Revertido</th><th>Vigente</th></tr></thead><tbody>
<?php foreach($data['transactions']as$payment){ ?><tr><td><?= $escape($date($payment['date'])); ?><br />#<?= $payment['id']; ?> <?= $escape($payment['mode']==='company_manual'?'Registro manual':$payment['mode']); ?><br /><?= $escape($payment['reference']); ?></td><td><?= $money($payment['original']); ?></td><td><?= $money($payment['reversed']); ?></td><td><?= $money($payment['effective']); ?></td></tr><?php } ?>
</tbody></table><?php if(!$data['transactions']){ ?><p>Nenhum pagamento registrado.</p><?php } ?>
<?php } ?>
<?php if($layout['show_pay_link']&&isset($data['portal_path'])){require_once __DIR__.'/../../services/CompanyInvoicePaymentPresentation.php'; ?><p><a href="<?= $escape(CompanyInvoicePaymentPresentation::portalUrl($data['portal_path'])); ?>">Consultar fatura e opções de pagamento no portal</a>. O acesso e as opções disponíveis dependem da autenticação e das permissões do contato.</p><?php } ?>

<?php if($layout['show_signature']&&!empty($identity['signature_inline'])){ ?><br /><br /><img width="140" src="<?= $escape($identity['signature_inline']); ?>"><br />Assinatura do emitente<?php } ?>
