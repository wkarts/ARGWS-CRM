<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php $nativeBrand=isset($native_settings_assets)&&is_array($native_settings_assets)?$native_settings_assets:null; $nativeAssetCanEdit=$nativeBrand!==null?!empty($native_settings_can_edit):staff_can('delete','settings'); $nativeAssetUrl=static function(string $kind)use($nativeBrand):string{return $nativeBrand===null?'':CompanyRequestScope::adminUrl(get_instance(),'settings/company_asset/'.$kind).'?revision='.rawurlencode((string)$nativeBrand['revision']);}; ?>
<div class="row"<?php if($nativeBrand!==null){ ?> data-native-assets-revision="<?= e($nativeBrand['revision']); ?>"<?php } ?>>
	<div class="col-md-12">
			<?php $company_logo_light = $nativeBrand!==null?($nativeBrand['assets']['logo']??''):get_option('company_logo'); ?>
		<?php $company_logo_dark  = $nativeBrand!==null?($nativeBrand['assets']['logo_dark']??''):get_option('company_logo_dark'); ?>
		<?php if ($company_logo_light != '') { ?>
		<div class="row">
			<div class="col-md-9">
				<img src="<?= e($nativeBrand!==null?$company_logo_light:base_url('uploads/company/' . $company_logo_light)); ?>"
					class="img img-responsive">
				<?php if($nativeBrand!==null){ ?><p><a href="<?= e($nativeAssetUrl('logo')); ?>"><?= _l('download'); ?></a></p><?php } ?>
			</div>
			<?php if ($nativeAssetCanEdit) { ?>
			<div class="col-md-3 text-right">
				<a href="<?= admin_url('settings/remove_company_logo'); ?>"
					data-toggle="tooltip"
					title="<?= _l('settings_general_company_remove_logo_tooltip'); ?>"
					class="_delete text-danger" <?php if($nativeBrand!==null){ ?>data-native-revision="<?= e($nativeBrand['revision']); ?>"<?php } ?>><i class="fa fa-remove"></i></a>
			</div>
			<?php } ?>
		</div>
		<div class="clearfix"></div>
		<?php } else { ?>
		<div class="form-group">
			<label for="company_logo"
				class="control-label"><?= _l('company_logo_light'); ?></label>
			<input type="file" name="company_logo" class="form-control" value="" data-toggle="tooltip"
				title="<?= _l('settings_general_company_logo_tooltip'); ?>">
		</div>
		<?php } ?>
		<hr />
		<?php if ($company_logo_dark != '') { ?>
		<div class="row">
			<div class="col-md-9">
				<img src="<?= e($nativeBrand!==null?$company_logo_dark:base_url('uploads/company/' . $company_logo_dark)); ?>"
					class="img img-responsive">
				<?php if($nativeBrand!==null){ ?><p><a href="<?= e($nativeAssetUrl('logo_dark')); ?>"><?= _l('download'); ?></a></p><?php } ?>
			</div>
			<?php if ($nativeAssetCanEdit) { ?>
			<div class="col-md-3 text-right">
				<a href="<?= admin_url('settings/remove_company_logo/dark'); ?>"
					data-toggle="tooltip"
					title="<?= _l('settings_general_company_remove_logo_tooltip'); ?>"
					class="_delete text-danger" <?php if($nativeBrand!==null){ ?>data-native-revision="<?= e($nativeBrand['revision']); ?>"<?php } ?>><i class="fa fa-remove"></i></a>
			</div>
			<?php } ?>
		</div>
		<div class="clearfix"></div>
		<?php } else { ?>
		<div class="form-group">
			<label for="company_logo_dark"
				class="control-label"><?= _l('company_logo_dark'); ?></label>
			<input type="file" name="company_logo_dark" class="form-control" value="" data-toggle="tooltip"
				title="<?= _l('settings_general_company_logo_tooltip'); ?>">
		</div>
		<?php } ?>
		<hr />
		<?php $favicon = $nativeBrand!==null?($nativeBrand['assets']['favicon']??''):get_option('favicon'); ?>
		<?php if ($favicon != '') { ?>
		<div class="form-group favicon">
			<div class="row">
				<div class="col-md-9">
					<img src="<?= e($nativeBrand!==null?$favicon:base_url('uploads/company/' . $favicon)); ?>"
						class="img img-responsive">
					<?php if($nativeBrand!==null){ ?><p><a href="<?= e($nativeAssetUrl('favicon')); ?>"><?= _l('download'); ?></a></p><?php } ?>
				</div>
				<?php if ($nativeAssetCanEdit) { ?>
				<div class="col-md-3 text-right">
					<a href="<?= admin_url('settings/remove_fv'); ?>"
						class="_delete text-danger" <?php if($nativeBrand!==null){ ?>data-native-revision="<?= e($nativeBrand['revision']); ?>"<?php } ?>><i class="fa fa-remove"></i></a>
				</div>
				<?php } ?>
			</div>
			<div class="clearfix"></div>
		</div>
		<?php } else { ?>
		<div class="form-group favicon_upload">
			<label for="favicon"
				class="control-label"><?= _l('settings_general_favicon'); ?></label>
			<input type="file" name="favicon" class="form-control">
		</div>
		<?php } ?>
		<hr />
		<?php if($nativeBrand!==null){$company_signature=$nativeBrand['assets']['signature']??''; ?>
		<?php if($company_signature!==''){ ?>
		<div class="row" id="native-company-signature">
			<div class="col-md-9">
				<label><?= _l('signature_image'); ?></label>
				<img src="<?= e($company_signature); ?>" class="img img-responsive" alt="<?= e(_l('signature_image')); ?>">
				<p><a href="<?= e($nativeAssetUrl('signature')); ?>"><?= _l('download'); ?></a></p>
			</div>
			<?php if($nativeAssetCanEdit){ ?><div class="col-md-3 text-right"><a href="<?= admin_url('settings/remove_signature_image'); ?>" class="_delete text-danger" data-native-revision="<?= e($nativeBrand['revision']); ?>"><i class="fa fa-remove"></i></a></div><?php } ?>
		</div>
		<?php }else{ ?>
		<div class="form-group"><label for="signature_image"><?= _l('signature_image'); ?></label><input type="file" id="signature_image" name="signature_image" class="form-control" accept="image/png,image/jpeg"></div>
		<?php } ?><hr />
		<?php } ?>
		<?php $attrs = (get_option('companyname') != '' ? [] : ['autofocus' => true]); ?>
		<?= render_input('settings[companyname]', 'settings_general_company_name', get_option('companyname'), 'text', $attrs); ?>
		<hr />
		<?= render_input('settings[main_domain]', 'settings_general_company_main_domain', get_option('main_domain')); ?>
		<hr />
		<?php render_yes_no_option('rtl_support_admin', 'settings_rtl_support_admin'); ?>
		<hr />
		<?php render_yes_no_option('rtl_support_client', 'settings_rtl_support_client'); ?>
		<hr />
		<?= render_input('settings[allowed_files]', 'settings_allowed_upload_file_types', $native_settings_general_values['allowed_files']??get_option('allowed_files')); ?>
	</div>
</div>
