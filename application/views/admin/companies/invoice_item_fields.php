<?php defined('BASEPATH') or exit('No direct script access allowed');
$itemPrefix=($editing?'edit-item-':'invoice-item-').$index.'-';
?>
<fieldset data-item-index="<?= (int)$index; ?>"><legend>Item <?= $index+1; ?></legend>
<?php foreach(['description'=>['Descrição',$item['description']],'qty'=>['Quantidade',$item['quantity']],'rate'=>['Preço unitário',$item['rate']]] as $field=>[$label,$value]){ ?>
<label for="<?= $itemPrefix.$field; ?>"><?= $esc($label); ?></label><input class="form-control" id="<?= $itemPrefix.$field; ?>" name="invoice[items][<?= (int)$index; ?>][<?= $field; ?>]" value="<?= $esc($value); ?>" required <?= $field!=='description'?'inputmode="decimal" pattern="[0-9]+(\.[0-9]{1,2})?"':''; ?>>
<?php } ?>
<div data-taxes>
<?php foreach($item['taxes']?:[['name'=>'','rate'=>'0.00']] as $taxIndex=>$tax){ ?>
<div data-tax-row>
<label for="<?= $itemPrefix.'tax-'.$taxIndex.'-name'; ?>">Nome do tributo</label><input class="form-control" id="<?= $itemPrefix.'tax-'.$taxIndex.'-name'; ?>" name="invoice[items][<?= (int)$index; ?>][taxes][<?= (int)$taxIndex; ?>][name]" value="<?= $esc($tax['name']); ?>" maxlength="100">
<label for="<?= $itemPrefix.'tax-'.$taxIndex.'-rate'; ?>">Alíquota % (0 para remover)</label><input class="form-control" id="<?= $itemPrefix.'tax-'.$taxIndex.'-rate'; ?>" name="invoice[items][<?= (int)$index; ?>][taxes][<?= (int)$taxIndex; ?>][rate]" value="<?= $esc($tax['rate']); ?>" inputmode="decimal" pattern="[0-9]+(\.[0-9]{1,2})?" required>
</div><?php } ?></div><button type="button" data-add-tax>Adicionar tributo</button>
</fieldset>
