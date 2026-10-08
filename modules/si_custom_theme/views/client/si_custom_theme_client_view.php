<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<style>
	.bg-info{background-color:#03a9f4;}
</style>
<link href="<?php echo module_dir_url('si_custom_theme','assets/css/si_custom_theme_settings_style.css'); ?>" rel="stylesheet" />
<div id="wrapper">
	<div class="content">
		<div class="row">
			<div class="col-md-12">
				<div class="panel_s">
					<div class="panel-body">
						<h4><?php echo _l('si_ct_select_theme');?></h4>
						<hr class="hr-panel-heading"/>
						<?php echo form_open($this->uri->uri_string());?>
							<?php foreach($theme_list as $theme){?>
							<div class="setting-color">
								<label>
									<input type="radio" name="client_theme" value="<?php echo ($theme['id'])?>" <?php if($selected_theme == $theme['id']) echo 'checked';?>>
									<span class="icon-check"><i class="fa fa-check-circle"></i></span>
									<span class="split">
										<span class="color <?php echo ($theme['class'][0])?>"></span>
										<span class="color <?php echo ($theme['class'][1])?>"></span>
									</span>
									<span class="color <?php echo ($theme['class'][2])?>"></span>
								</label>
								<p class="text-center"><?php echo ($theme['theme_name']);?></p>
							</div>
						<?php }?>
						<div class="col-md-12 text-right mtop20">
							<div class="form-group">
								<button class="btn btn-info"><?php echo _l('save'); ?></button>
							</div>
						</div>	
						<?php echo form_close();?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<script>
$('input[name="client_theme"]').on('change', function() {
	$.post(site_url + 'si_custom_theme/client_theme/get_theme', {
		theme:$('input[name="client_theme"]:checked').val(),
	}).done(function(append_data) {
		$('.si_ct_temp_loaded').remove();
		$('.si_ct_loaded').remove();
		$("<style />", {
				class: 'si_ct_temp_loaded',
				type: 'text/css',
				html: append_data
			}).appendTo("head");
	});
});
</script>
</body>
</html>