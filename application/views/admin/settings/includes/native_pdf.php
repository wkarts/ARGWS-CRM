<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div data-native-settings-pdf>
<h4><?= e(_l('settings_pdf_font')); ?></h4>
<div class="row"><div class="col-md-8 form-group"><label for="native-pdf-font"><?= e(_l('settings_pdf_font')); ?></label><select class="selectpicker" data-width="100%" name="settings[pdf_font]" id="native-pdf-font"><?php foreach(CompanyPdfTypography::fonts()as$font=>$label){ ?><option value="<?= e($font); ?>" <?= $values['pdf_font']===$font?'selected':''; ?>><?= e($label); ?></option><?php } ?></select></div><div class="col-md-4 form-group"><label for="native-pdf-size"><?= e(_l('settings_pdf_font_size')); ?></label><input type="number" min="6" max="24" class="form-control" name="settings[pdf_font_size]" id="native-pdf-size" value="<?= e($values['pdf_font_size']); ?>"></div></div>
<hr><h4><?= e(_l('pdf_formats')); ?></h4>
<?php foreach(['invoice'=>'invoice','estimate'=>'estimate','proposal'=>'proposal','credit_note'=>'credit_note','contract'=>'contract']as$type=>$label){$field='pdf_format_'.$type; ?><div class="form-group"><label for="<?= e($field); ?>"><?= e(_l($label)); ?></label><select class="selectpicker" data-width="100%" id="<?= e($field); ?>" name="settings[<?= e($field); ?>]"><?php foreach(['A4-PORTRAIT'=>'A4 · Retrato','A4-LANDSCAPE'=>'A4 · Paisagem','LETTER-PORTRAIT'=>'Carta · Retrato','LETTER-LANDSCAPE'=>'Carta · Paisagem']as$choice=>$title){ ?><option value="<?= e($choice); ?>" <?= $values[$field]===$choice?'selected':''; ?>><?= e($title); ?></option><?php } ?></select></div><?php } ?>
<hr><h4><?= e(_l('signature_image')); ?></h4>
<?php if(!empty($assets['assets']['signature'])){ ?><img src="<?= e($assets['assets']['signature']); ?>" class="img-responsive" alt="<?= e(_l('signature_image')); ?>"><?php } ?>
<div class="form-group"><label for="native-pdf-signature"><?= e(_l('signature_image')); ?></label><input type="file" name="signature_image" id="native-pdf-signature" class="form-control" accept="image/png,image/jpeg"></div>
<p class="text-muted">As preferências serão aplicadas aos novos documentos. Os documentos já emitidos preservam a apresentação registrada.</p>
<a href="<?= e(admin_url('settings?group=general')); ?>">Logotipo e identidade visual</a>
</div>
