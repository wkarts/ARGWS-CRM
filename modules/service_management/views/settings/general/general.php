<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<div class="row">
	<div class="col-md-12">
		<h4 class="h4-color"><i class="fa fa-bars menu-icon" aria-hidden="true"></i> <?php echo _l('sm_general_setting'); ?></h4>
	</div>
</div>
<hr class="hr-color">

<?php echo form_open_multipart(admin_url('manufacturing/prefix_number'),array('class'=>'prefix_number','autocomplete'=>'off')); ?>

<div class="row">
	<div class="col-md-12">
		<h5 class="no-margin font-bold h5-color" ><?php echo _l('sm_service_management')?></h5>
		<hr class="hr-color" >
	</div>
</div>
<div class="row">
	<div class="col-md-12">
		<div class="form-group">
			<div class="checkbox checkbox-primary">
				<input onchange="auto_create_change_setting(this); return false" type="checkbox" id="service_management_display_on_portal" name="purchase_setting[service_management_display_on_portal]" <?php if(get_option('service_management_display_on_portal') == 1 ){ echo 'checked';} ?> value="service_management_display_on_portal">
				<label for="service_management_display_on_portal"><?php echo _l('sm_display_service_management_on_client_portal'); ?>
				<a href="#" class="pull-right display-block input_method"><i class="fa fa-question-circle i_tooltip" data-toggle="tooltip" title="" data-original-title="<?php echo _l('sm_display_service_management_on_client_portal_tooltip'); ?>"></i></a>
			</label>
		</div>
	</div>
</div>
</div>


</body>
</html>


