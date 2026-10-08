<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php 	
	$admin_login_bg_image 		= get_option(SI_CUSTOM_THEME_MODULE_NAME.'_bg_img_admin_login');
	$customer_login_bg_image 	= get_option(SI_CUSTOM_THEME_MODULE_NAME.'_bg_img_customer_login');
	$admin_menu_bg_image 	= get_option(SI_CUSTOM_THEME_MODULE_NAME.'_bg_img_admin_menu');  
	$admin_pages_bg_image 	= get_option(SI_CUSTOM_THEME_MODULE_NAME.'_bg_img_admin_pages');  
	$customer_pages_bg_image 	= get_option(SI_CUSTOM_THEME_MODULE_NAME.'_bg_img_customer_pages');  
?>
<?php echo form_open_multipart(admin_url('si_custom_theme/upload_bg'),array('id'=>'si_ct_bg_image_form')); ?>
<div class="row">
	<div class="col-md-6">
		<?php if($admin_login_bg_image != ''){ ?>
			<div class="row">
				<div class="col-md-9">
					<label for="si_custom_theme_bg_img_admin_login" class="control-label"><?php echo _l('si_ct_settings_bg_img_admin_login'); ?></label>
					<img src="<?php echo base_url('modules/si_custom_theme/uploads/'.$admin_login_bg_image); ?>" class="img img-responsive">
				</div>
				<div class="col-md-3 text-right">
					<a href="<?php echo admin_url('si_custom_theme/remove_bg_image/admin_login'); ?>" data-toggle="tooltip" title="<?php echo _l('delete')." "._l('si_ct_settings_bg_img_admin_login'); ?>" class="_delete text-danger"><i class="fa fa-remove"></i></a>
				</div>
			</div>
			<div class="clearfix"></div>
		<?php } else { ?>
			<div class="form-group">
				<label for="si_custom_theme_bg_img_admin_login" class="control-label"><?php echo _l('si_ct_settings_bg_img_admin_login'); ?></label>
				<input type="file" name="si_custom_theme_bg_img_admin_login" class="form-control" value="" data-toggle="tooltip" title="<?php echo _l('si_ct_settings_bg_img_admin_login'); ?>">
			</div>
		<?php } ?>
	</div>
	<div class="col-md-6">
		<?php 
		 if($customer_login_bg_image != ''){ ?>
			<div class="row">
				<div class="col-md-9">
					<label for="si_custom_theme_bg_img_customer_login" class="control-label"><?php echo _l('si_ct_settings_bg_img_customer_login'); ?></label>
					<img src="<?php echo base_url('modules/si_custom_theme/uploads/'.$customer_login_bg_image); ?>" class="img img-responsive">
				</div>
				<?php if(has_permission('settings','','delete')){ ?>
					<div class="col-md-3 text-right">
						<a href="<?php echo admin_url('si_custom_theme/remove_bg_image/customer_login'); ?>" data-toggle="tooltip" title="<?php echo _l('delete')." "._l('si_ct_settings_bg_img_customer_login'); ?>" class="_delete text-danger"><i class="fa fa-remove"></i></a>
					</div>
				<?php } ?>
			</div>
			<div class="clearfix"></div>
		<?php } else { ?>
			<div class="form-group">
				<label for="si_custom_theme_bg_img_customer_login" class="control-label"><?php echo _l('si_ct_settings_bg_img_customer_login'); ?></label>
				<input type="file" name="si_custom_theme_bg_img_customer_login" class="form-control" value="" data-toggle="tooltip" title="<?php echo _l('si_ct_settings_bg_img_customer_login'); ?>">
			</div>
		<?php } ?>
	</div>
</div>
<hr/>
<div class="row">
	<div class="col-md-6">
		<?php if($admin_pages_bg_image != ''){ ?>
			<div class="row">
				<div class="col-md-9">
					<label for="si_custom_theme_bg_img_admin_pages" class="control-label"><?php echo _l('si_ct_settings_bg_img_admin_pages'); ?></label>
					<img src="<?php echo base_url('modules/si_custom_theme/uploads/'.$admin_pages_bg_image); ?>" class="img img-responsive">
				</div>
				<?php if(has_permission('settings','','delete')){ ?>
					<div class="col-md-3 text-right">
						<a href="<?php echo admin_url('si_custom_theme/remove_bg_image/admin_pages'); ?>" data-toggle="tooltip" title="<?php echo _l('delete')." "._l('si_ct_settings_bg_img_admin_pages'); ?>" class="_delete text-danger"><i class="fa fa-remove"></i></a>
					</div>
				<?php } ?>
			</div>
			<div class="clearfix"></div>
		<?php } else { ?>
			<div class="form-group">
				<label for="si_custom_theme_bg_img_admin_pages" class="control-label"><?php echo _l('si_ct_settings_bg_img_admin_pages'); ?></label>
				<input type="file" name="si_custom_theme_bg_img_admin_pages" class="form-control" value="" data-toggle="tooltip" title="<?php echo _l('si_ct_settings_bg_img_admin_pages'); ?>">
			</div>
		<?php } ?>
	</div>
	<div class="col-md-6">
		<?php 
		 if($customer_pages_bg_image != ''){ ?>
			<div class="row">
				<div class="col-md-9">
					<label for="si_custom_theme_bg_img_customer_pages" class="control-label"><?php echo _l('si_ct_settings_bg_img_customer_pages'); ?></label>
					<img src="<?php echo base_url('modules/si_custom_theme/uploads/'.$customer_pages_bg_image); ?>" class="img img-responsive">
				</div>
				<?php if(has_permission('settings','','delete')){ ?>
					<div class="col-md-3 text-right">
						<a href="<?php echo admin_url('si_custom_theme/remove_bg_image/customer_pages'); ?>" data-toggle="tooltip" title="<?php echo _l('delete')." "._l('si_ct_settings_bg_img_customer_pages'); ?>" class="_delete text-danger"><i class="fa fa-remove"></i></a>
					</div>
				<?php } ?>
			</div>
			<div class="clearfix"></div>
		<?php } else { ?>
			<div class="form-group">
				<label for="si_custom_theme_bg_img_customer_pages" class="control-label"><?php echo _l('si_ct_settings_bg_img_customer_pages'); ?></label>
				<input type="file" name="si_custom_theme_bg_img_customer_pages" class="form-control" value="" data-toggle="tooltip" title="<?php echo _l('si_ct_settings_bg_img_customer_pages'); ?>">
			</div>
		<?php } ?>
	</div>
</div>
<hr/>
<div class="row">
	<div class="col-md-6">
		<?php if($admin_menu_bg_image != ''){ ?>
			<div class="row">
				<div class="col-md-9">
					<label for="si_custom_theme_bg_img_admin_menu" class="control-label"><?php echo _l('si_ct_settings_bg_img_admin_menu'); ?></label>
					<img src="<?php echo base_url('modules/si_custom_theme/uploads/'.$admin_menu_bg_image); ?>" class="img img-responsive">
				</div>
				<?php if(has_permission('settings','','delete')){ ?>
					<div class="col-md-3 text-right">
						<a href="<?php echo admin_url('si_custom_theme/remove_bg_image/admin_menu'); ?>" data-toggle="tooltip" title="<?php echo _l('delete')." "._l('si_ct_settings_bg_img_admin_menu'); ?>" class="_delete text-danger"><i class="fa fa-remove"></i></a>
					</div>
				<?php } ?>
			</div>
			<div class="clearfix"></div>
		<?php } else { ?>
			<div class="form-group">
				<label for="si_custom_theme_bg_img_admin_menu" class="control-label"><?php echo _l('si_ct_settings_bg_img_admin_menu'); ?></label>
				<input type="file" name="si_custom_theme_bg_img_admin_menu" class="form-control" value="" data-toggle="tooltip" title="<?php echo _l('si_ct_settings_bg_img_admin_menu'); ?>">
			</div>
		<?php } ?>
	</div>
</div>
<?php $this->load->view('includes/other_settings');?>
<div class="btn-bottom-toolbar text-right">
	<button class="btn btn-info" type="submit"><?php echo _l('save')?></button>
</div>
<?php echo form_close(); ?>	