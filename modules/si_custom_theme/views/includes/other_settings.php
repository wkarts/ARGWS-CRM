<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<hr />
<div class="row">
	<div class="col-md-6">
		<?php echo render_yes_no_option(SI_CUSTOM_THEME_MODULE_NAME.'_customer_login_header','si_ct_settings_customer_login_header'); ?>
	</div>
	<div class="col-md-6">
		<?php echo render_yes_no_option(SI_CUSTOM_THEME_MODULE_NAME.'_customer_login_footer','si_ct_settings_customer_login_footer'); ?>
	</div>
</div>
<hr />
<div class="row">
	<div class="col-md-6">
		<?php echo render_yes_no_option(SI_CUSTOM_THEME_MODULE_NAME.'_enable_staff_theme','si_ct_settings_enable_staff_theme'); ?>
	</div>
	<div class="col-md-6">
		<?php echo render_yes_no_option(SI_CUSTOM_THEME_MODULE_NAME.'_enable_client_theme','si_ct_settings_enable_client_theme'); ?>
	</div>
</div>	