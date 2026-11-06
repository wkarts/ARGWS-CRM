<?php defined('BASEPATH') or exit('No direct script access allowed');
// Definitions and values come from the credit creation authority, never a global item reader.
$fieldId='credit-cf-'.substr(hash('sha256',$fieldName),0,16);
$fieldValue=(string)($field['default_value']??'');
if(in_array($field['type'],['date_picker','date_picker_time'],true)&&$fieldValue!==''){
    $stamp=strtotime($fieldValue);$fieldValue=$stamp===false?'':($field['type']==='date_picker'?_d(date('Y-m-d',$stamp)):_dt(date('Y-m-d H:i:s',$stamp)));
}
$required=(int)$field['required']===1;
$fieldOptions=array_values(array_filter(array_map('trim',explode(',',$field['options'])),static fn($option)=>$option!==''));
$chosen=array_map('trim',explode(',',$fieldValue));
?>
<div class="form-group<?= $field['type']==='checkbox'?' chk':''; ?>" data-credit-field="<?= $esc($field['id']); ?>" data-credit-field-type="<?= $esc($field['type']); ?>"<?= $required?' data-credit-required-field="1"':''; ?>>
<label for="<?= $fieldId; ?>" class="control-label"><?= $required?'<small class="req text-danger">* </small>':''; ?><?= $esc($field['name']); ?></label>
<?php if($field['type']==='textarea'){ ?>
<textarea id="<?= $fieldId; ?>" class="form-control" name="<?= $esc($fieldName); ?>" rows="4" maxlength="65535"<?= $required?' required':''; ?>><?= $esc($fieldValue); ?></textarea>
<?php }elseif(in_array($field['type'],['select','multiselect'],true)){ $multi=$field['type']==='multiselect'; ?>
<select id="<?= $fieldId; ?>" name="<?= $esc($fieldName.($multi?'[]':'')); ?>" class="form-control"<?= $multi?' multiple':''; ?><?= $required?' required':''; ?>><option value=""></option><?php foreach($fieldOptions as$option){?><option value="<?= $esc($option); ?>"<?= ($multi?in_array($option,$chosen,true):$option===$fieldValue)?' selected':''; ?>><?= $esc($option); ?></option><?php } ?></select>
<?php }elseif($field['type']==='checkbox'){foreach($fieldOptions as$optionIndex=>$option){$optionId=$fieldId.'-'.$optionIndex; ?>
<div class="checkbox<?= (int)$field['display_inline']===1?' checkbox-inline':''; ?>"><input id="<?= $optionId; ?>" type="checkbox" name="<?= $esc($fieldName); ?>[]" value="<?= $esc($option); ?>"<?= in_array($option,$chosen,true)?' checked':''; ?>><label for="<?= $optionId; ?>"><?= $esc($option); ?></label></div>
<?php }}elseif($field['type']==='link'){ ?>
<input id="<?= $fieldId; ?>" type="hidden" name="<?= $esc($fieldName); ?>" data-credit-link-value value=""><button type="button" class="btn btn-link no-padding" data-credit-link-toggle><?= _l('cf_translate_input_link_tip'); ?></button>
<div class="hide mtop10" data-credit-link-editor><label class="control-label"><?= _l('cf_translate_input_link_title'); ?></label><input type="text" class="form-control" data-credit-link-title maxlength="1000"><label class="control-label mtop5"><?= _l('cf_translate_input_link_url'); ?></label><input type="text" class="form-control" data-credit-link-url maxlength="2000"></div>
<?php }else{ $type=$field['type']==='number'?'number':'text';$class=match($field['type']){'date_picker'=>' datepicker','date_picker_time'=>' datetimepicker','colorpicker'=>' colorpicker-input',default=>''}; ?>
<input id="<?= $fieldId; ?>" name="<?= $esc($fieldName); ?>" type="<?= $type; ?>" class="form-control<?= $class; ?>" value="<?= $esc($fieldValue); ?>" maxlength="65535"<?= $type==='number'?' step="any"':''; ?><?= $field['type']==='colorpicker'?' pattern="#[a-fA-F0-9]{6}" placeholder="#000000"':''; ?><?= $required?' required':''; ?>>
<?php } ?>
</div>
