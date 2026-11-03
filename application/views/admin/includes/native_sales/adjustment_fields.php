<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<fieldset><legend>Desconto e ajuste</legend>
<p>O desconto percentual incide sobre o subtotal. Antes dos tributos, o desconto é distribuído entre os itens por centavos; depois dos tributos, as bases permanecem integrais. O ajuste altera apenas o total.</p>
<label for="<?= $fieldPrefix; ?>discount-mode">Modalidade do desconto</label><select class="form-control" id="<?= $fieldPrefix; ?>discount-mode" name="invoice[discount_mode]">
<?php foreach(['fixed'=>'Valor fixo','percent'=>'Percentual'] as $value=>$label){ ?><option value="<?= $value; ?>" <?= ($adjustments['discount_mode']??'fixed')===$value?'selected':''; ?>><?= $label; ?></option><?php } ?></select>
<label for="<?= $fieldPrefix; ?>discount-value">Desconto (decimal com ponto)</label><input class="form-control" id="<?= $fieldPrefix; ?>discount-value" name="invoice[discount_value]" value="<?= $esc($adjustments['discount_value']??'0.00'); ?>" inputmode="decimal" required pattern="[0-9]+(\.[0-9]{1,2})?">
<label for="<?= $fieldPrefix; ?>discount-type">Aplicação</label><select class="form-control" id="<?= $fieldPrefix; ?>discount-type" name="invoice[discount_type]">
<?php foreach(['before_tax'=>'Antes dos tributos','after_tax'=>'Depois dos tributos'] as $value=>$label){ ?><option value="<?= $value; ?>" <?= ($adjustments['discount_type']??'after_tax')===$value?'selected':''; ?>><?= $label; ?></option><?php } ?></select>
<label for="<?= $fieldPrefix; ?>adjustment">Ajuste (positivo ou negativo)</label><input class="form-control" id="<?= $fieldPrefix; ?>adjustment" name="invoice[adjustment]" value="<?= $esc($adjustments['adjustment']??'0.00'); ?>" inputmode="decimal" required pattern="-?[0-9]+(\.[0-9]{1,2})?">
</fieldset>
