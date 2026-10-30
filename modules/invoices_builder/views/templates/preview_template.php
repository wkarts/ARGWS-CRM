<?php defined('BASEPATH') or exit('No direct script access allowed');?>
<?php init_head(); ?>
<input type="hidden" name="page_rotation" id="page_rotation" value="<?php echo ib_html_entity_decode($page_rotation); ?>">
<div id="wrapper">
	<div class="content">
		<div class="row">
		<div class="col-md-12">
			<div class="panel_s">
			<div class="panel-body">
				<h4><?php echo ib_html_entity_decode($title); ?></h4>
				<hr>
				<div id="preview_area" class="row preview_mode overflow-x">
					<div class="col-md-6 mx-auto preview-fr d-flex justify-content-center">
						<?php $this->load->view('templates/includes/preview'); ?>						
					</div>
				</div>
		</div>
		</div>
		</div>
		</div>
	</div>
</div>
<?php init_tail();?>
</body>
</html>