<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div> 

	<div class="row">
		<div class="col-md-12">
			<h4 class="h4-color"><i class="fa fa-certificate" aria-hidden="true"></i> <?php echo _l('sm_item_unit'); ?></h4>
		</div>
	</div>
	<hr class="hr-color">

	<?php if(has_permission('service_management', '', 'create')){ ?>
		<a href="#" onclick="new_unit(); return false;" class="btn btn-info pull-left display-block">
			<?php echo _l('sm_add'); ?>
		</a>
	<?php } ?>
	<br>
	<br>

	<table class="table dt-table border table-striped">
		<thead>
			<th class="hide"><?php echo _l('id'); ?></th>
			<th><?php echo _l('sm_unit_name'); ?></th>
			<th><?php echo _l('description'); ?></th>
			<th><?php echo _l('sm_status_label'); ?></th>
			<th><?php echo _l('options'); ?></th>
		</thead>
		<tbody>
			<?php foreach($item_units as $unit){ ?>

				<tr>
					<td class="hide"><?php echo new_html_entity_decode($unit['id']); ?></td>
					<td><?php echo new_html_entity_decode($unit['unit_name']); ?></td>
					<td><?php echo new_html_entity_decode($unit['note']); ?></td>
					<td>
						<?php  
						$checked = '';
						if ($unit['display'] == 1) {
							$checked = 'checked';
						}

						echo '<div class="onoffswitch">
						<input type="checkbox" ' . (((is_admin() || !has_permission('service_management', '', 'edit')) && !is_admin()) ? 'disabled' : '') . ' data-switch-url="' . admin_url() . 'service_management/change_unit_status" name="onoffswitch" class="onoffswitch-checkbox" id="c_' . $unit['id'] . '" data-id="' . $unit['id'] . '" ' . $checked . '>
						<label class="onoffswitch-label" for="c_' . $unit['id'] . '"></label>
						</div>';

						?>

					</td>

					<td>
						<?php if (has_permission('service_management', '', 'edit') || is_admin()) { ?>
							<a href="#" onclick="edit_unit(this,<?php echo new_html_entity_decode($unit['id']); ?>); return false;" data-id="<?php echo new_html_entity_decode($unit['id']); ?>" data-unit_code="<?php echo new_html_entity_decode($unit['unit_code']); ?>" data-unit_name="<?php echo new_html_entity_decode($unit['unit_name']); ?>" data-unit_value="<?php echo new_html_entity_decode($unit['unit_value']); ?>" data-unit_type="<?php echo new_html_entity_decode($unit['unit_type']); ?>" data-display="<?php echo new_html_entity_decode($unit['display']); ?>" data-note="<?php echo new_html_entity_decode($unit['note']); ?>" class="btn btn-default btn-icon"><i class="fa-regular fa-pen-to-square"></i>
							</a>
						<?php } ?>

						<?php if (has_permission('service_management', '', 'delete') || is_admin()) { ?> 
							<a href="<?php echo admin_url('service_management/delete_unit/'.$unit['id']); ?>" class="btn btn-danger btn-icon _delete"><i class="fa fa-remove"></i></a>
						<?php } ?>
					</td>
				</tr>
			<?php } ?>
		</tbody>
	</table>

	<div class="modal fade" id="item_unit" tabindex="-1" role="dialog">
		<div class="modal-dialog setting-handsome-table">
			<?php echo form_open_multipart(admin_url('service_management/units'), array('id'=>'add_edit_unit')); ?>

			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title">
						<span class="add-title"><?php echo _l('sm_add_unit'); ?></span>
						<span class="edit-title"><?php echo _l('sm_update_unit'); ?></span>
					</h4>
				</div>

				<div class="modal-body">
					<div class="row">
						<div class="col-md-12">
							<div id="unit_id"></div>   
							<div class="form"> 
								<div class="col-md-6">
									<?php echo render_input('unit_name', 'sm_unit_name'); ?>
								</div>
								<div class="col-md-6">
									<?php echo render_input('unit_code', 'sm_unit_code'); ?>
								</div>
								<div class="col-md-6">
									<?php echo render_input('unit_value', 'sm_unit_value', '', 'number', ['step' => 1]); ?>
								</div>
								<div class="col-md-6">
									<?php 
									$sm_unit_type_value = sm_unit_type_value();
									 ?>
									<?php echo render_select('unit_type', $sm_unit_type_value, array('name', 'label'), 'sm_unit_type'); ?>
								</div>
								
								<div class="col-md-12">
									<?php echo render_textarea('note', 'sm_category_description'); ?>
								</div>

								<div class="col-md-6">
									<div class="form-group">
										<div class="checkbox checkbox-primary">
											<input type="checkbox" id="display" name="display" value="1" checked>
											<label for="display"><?php echo _l('sm_active'); ?></label>
										</div>
									</div>
								</div>
								
							</div>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
					<?php if (has_permission('service_management', '', 'create') || has_permission('service_management', '', 'edit') || is_admin()) { ?> 
						<button type="submit" class="btn btn-info intext-btn"><?php echo _l('submit'); ?></button>
					<?php } ?>
				</div>
			</div>
			<?php echo form_close(); ?>
		</div>
	</div>   

</body>
</html>
