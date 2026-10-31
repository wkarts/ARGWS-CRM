<?php defined('BASEPATH') or exit('No direct script access allowed');
$itemPrefix=($editing?'edit-item-':'invoice-item-').$index.'-';
?>
<fieldset data-item-index="<?= (int)$index; ?>"><legend>Item <?= $index+1; ?></legend>
<?php foreach(['description'=>['Descrição',$item['description']],'qty'=>['Quantidade',$item['qty']??$item['quantity']],'rate'=>['Preço unitário',$item['rate']]] as $field=>[$label,$value]){ ?>
<label for="<?= $itemPrefix.$field; ?>"><?= $esc($label); ?></label><input class="form-control" id="<?= $itemPrefix.$field; ?>" name="invoice[items][<?= (int)$index; ?>][<?= $field; ?>]" value="<?= $esc($value); ?>" required <?= $field!=='description'?'inputmode="decimal" pattern="[0-9]+(\.[0-9]{1,2})?"':''; ?>>
<?php } ?>
<label for="<?= $itemPrefix.'details'; ?>">Descrição complementar literal (até20.000 bytes UTF-8)</label><textarea class="form-control" id="<?= $itemPrefix.'details'; ?>" name="invoice[items][<?= (int)$index; ?>][long_description]" maxlength="20000"><?= $esc($item['long_description']??$item['details']??''); ?></textarea>
<label for="<?= $itemPrefix.'unit'; ?>">Unidade (até40 caracteres)</label><input class="form-control" id="<?= $itemPrefix.'unit'; ?>" name="invoice[items][<?= (int)$index; ?>][unit]" maxlength="40" value="<?= $esc($item['unit']??''); ?>">
<?php $optional=(int)($item['is_optional']??0);$selected=(int)($item['is_selected']??1);$choice=$optional===0?'required':($selected===1?'chosen':'unselected'); ?>
<label for="<?= $itemPrefix.'choice'; ?>">Participação nos valores do documento</label><select class="form-control" id="<?= $itemPrefix.'choice'; ?>" data-invoice-line-choice><option value="required" <?= $choice==='required'?'selected':''; ?>>Item obrigatório — incluído no total</option><option value="chosen" <?= $choice==='chosen'?'selected':''; ?>>Item opcional escolhido — incluído no total</option><option value="unselected" <?= $choice==='unselected'?'selected':''; ?>>Item opcional não escolhido — fora do total e do PDF</option></select><input type="hidden" name="invoice[items][<?= (int)$index; ?>][is_optional]" value="<?= $optional; ?>"><input type="hidden" name="invoice[items][<?= (int)$index; ?>][is_selected]" value="<?= $selected; ?>">
<div data-taxes>
<?php foreach($item['taxes']?:[['name'=>'','rate'=>'0.00']] as $taxIndex=>$tax){ ?>
<div data-tax-row>
<label for="<?= $itemPrefix.'tax-'.$taxIndex.'-name'; ?>">Nome do tributo</label><input class="form-control" id="<?= $itemPrefix.'tax-'.$taxIndex.'-name'; ?>" name="invoice[items][<?= (int)$index; ?>][taxes][<?= (int)$taxIndex; ?>][name]" value="<?= $esc($tax['name']); ?>" maxlength="100">
<label for="<?= $itemPrefix.'tax-'.$taxIndex.'-rate'; ?>">Alíquota % (0 para remover)</label><input class="form-control" id="<?= $itemPrefix.'tax-'.$taxIndex.'-rate'; ?>" name="invoice[items][<?= (int)$index; ?>][taxes][<?= (int)$taxIndex; ?>][rate]" value="<?= $esc($tax['rate']); ?>" inputmode="decimal" pattern="[0-9]+(\.[0-9]{1,2})?" required>
</div><?php } ?></div><button class="btn btn-default" type="button" data-add-tax>Adicionar tributo</button>
</fieldset>
