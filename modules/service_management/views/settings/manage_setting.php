<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
	<div class="content">
		<div class="row">

			<div class="col-md-3">
				<ul class="nav navbar-pills navbar-pills-flat nav-tabs nav-stacked">
					<?php
					$i = 0;
					foreach($tab as $gr){
						?>
						<li<?php if($i == 0){echo " class='active'"; } ?>>
						<a href="<?php echo admin_url('service_management/setting?group='.$gr); ?>" data-group="<?php echo new_html_entity_decode($gr); ?>">
							<?php
								$icon['category'] = '<span class="fa fa-area-chart"></span>';
								$icon['unit'] = '<span class="fa fa-certificate"></span>';
								$icon['status'] = '<span class="fa fa-list-alt"></span>';
								$icon['prefix_number'] = '<span class="fa fa-bars menu-icon"></span>';
								$icon['general'] = '<span class="fa fa-bars menu-icon"></span>';

								if($gr == 'prefix_number'){
									echo new_html_entity_decode($icon[$gr] .' '. _l('mrp_general_setting')); 

								}else{
									echo new_html_entity_decode($icon[$gr] .' '. _l('sm_'.$gr)); 
								}
							
							?>
						</a>
					</li>
					<?php $i++; } ?>
				</ul>
			</div>
			<div class="col-md-9">
				<div class="panel_s">
					<div class="panel-body">

						<?php $this->load->view($tabs['view']); ?>

					</div>
				</div>
			</div>
			<div class="clearfix"></div>
		</div>
		<?php echo form_close(); ?>
		<div class="btn-bottom-pusher"></div>
	</div>
</div>
<div id="new_version"></div>
<?php init_tail(); ?>

<?php 
$viewuri = $_SERVER['REQUEST_URI'];
 ?>

<?php if(!(strpos($viewuri,'admin/service_management/setting?group=category') === false)){ 
	require 'modules/service_management/assets/js/settings/categories/manage_js.php';
}elseif(!(strpos($viewuri,'admin/service_management/setting?group=unit') === false)){
	require 'modules/service_management/assets/js/settings/units/manage_js.php';
}elseif(!(strpos($viewuri,'admin/service_management/setting?group=status') === false)){
	require 'modules/service_management/assets/js/settings/status/manage_js.php';
}elseif(!(strpos($viewuri,'admin/service_management/setting?group=general') === false)){
	require 'modules/service_management/assets/js/settings/general/general_js.php';
}

 ?>
</body>
</html>
