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
?>
<table cellpadding="3" width="100%">
<tr>
<td width="58%">
<?php if ($identity['logo_inline'] !== '') { ?>
<img width="110" src="<?= $escape($identity['logo_inline']) ?>"><br />
<?php } ?>
<b><?= $escape($identity['legal_name']) ?></b><br />
<?= $escape($identity['tax_id']) ?><br />
<?= $escape($identity['address']) ?><br />
<?= $escape($identity['city']) ?> / <?= $escape($identity['state']) ?><br />
<?= $escape($identity['postcode']) ?> · <?= $escape($identity['country_code']) ?>
<?php if ($identity['phone'] !== '') { ?><br /><?= $escape($identity['phone']) ?><?php } ?>
<?php if ($identity['email'] !== '') { ?><br /><?= $escape($identity['email']) ?><?php } ?>
</td>
<td width="42%" align="right">
<span style="font-size:22pt"><b>FATURA</b></span><br />
<b><?= $escape($data['number']) ?></b><br />
<?= $escape($statuses[$data['status']]) ?><br />
Emissão: <?= $escape($date($data['date'])) ?><br />
<?php if ($data['due_date'] !== null) { ?>Vencimento: <?= $escape($date($data['due_date'])) ?><?php } ?>
</td>
</tr>
</table>
<br />
<table cellpadding="5" width="100%"><tr>
<td width="58%"><b>Destinatário</b><br /><?= $escape($data['recipient']['name']) ?>
<?php if ($data['recipient']['tax_id'] !== '') { ?><br /><?= $escape($data['recipient']['tax_id']) ?><?php } ?>
<br /><?= $address($data['recipient']['billing']) ?></td>
<td width="42%">
<?php if ($data['recipient']['shipping'] !== null) { ?><b>Entrega</b><br /><?= $address($data['recipient']['shipping']) ?><?php } ?>
</td></tr></table>
<br />
<table cellpadding="5" cellspacing="0" border="0" width="100%">
<thead><tr style="background-color:#e8edf2;font-weight:bold;">
<th width="43%">Descrição</th><th width="13%" align="right">Qtd.</th><th width="22%" align="right">Valor unitário</th><th width="22%" align="right">Valor</th>
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
<?php foreach (['subtotal'=>'Subtotal','total_tax'=>'Tributos','discount_total'=>'Desconto','adjustment'=>'Ajuste','total'=>'Total'] as $field=>$label) { ?>
<tr<?= $field === 'total' ? ' style="background-color:#e8edf2;font-weight:bold;"' : '' ?>>
<td width="72%" align="right"><?= $label ?></td><td width="28%" align="right"><?= $field === 'discount_total' && $data['totals'][$field] !== '0.00' ? '-' : '' ?><?= $money($data['totals'][$field]) ?></td>
</tr>
<?php } ?>
</table>
<?php if ($data['note'] !== '') { ?><br /><b>Observações</b><br /><?= $lines($data['note']) ?><?php } ?>
<?php if ($identity['terms'] !== '') { ?><br /><br /><b>Condições</b><br /><?= $lines($identity['terms']) ?><?php } ?>

<?php if(!empty($identity['signature_inline'])){ ?><br /><br /><img width="140" src="<?= $escape($identity['signature_inline']); ?>"><br />Assinatura do emitente<?php } ?>
