<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div> 

	<div class="row">
		<div class="col-md-12">
			<h4 class="h4-color"><i class="fa fa-area-chart" aria-hidden="true"></i> <?php echo _l('sm_item_category'); ?></h4>
		</div>
	</div>
	<hr class="hr-color">

	<?php if(has_permission('service_management', '', 'create')){ ?>
		<a href="#" onclick="new_category(); return false;" class="btn btn-info pull-left display-block">
			<?php echo _l('sm_add'); ?>
		</a>
	<?php } ?>
	<br>
	<br>

	<table class="table dt-table border table-striped">
		<thead>
			<th class="hide"><?php echo _l('id'); ?></th>
			<th><?php echo _l('sm_category_name'); ?></th>
			<th><?php echo _l('description'); ?></th>
			<th><?php echo _l('sm_status_label'); ?></th>
			<th><?php echo _l('options'); ?></th>
		</thead>
		<tbody>
			<?php foreach($item_categories as $category){ ?>

				<tr>
					<td class="hide"><?php echo new_html_entity_decode($category['id']); ?></td>
					<td><?php echo new_html_entity_decode($category['name']); ?></td>
					<td><?php echo new_html_entity_decode($category['note']); ?></td>
					<td>
						<?php  
						$checked = '';
						if ($category['display'] == 1) {
							$checked = 'checked';
						}

						echo '<div class="onoffswitch">
						<input type="checkbox" ' . (((is_admin() || !has_permission('service_management', '', 'edit')) && !is_admin()) ? 'disabled' : '') . ' data-switch-url="' . admin_url() . 'service_management/change_category_status" name="onoffswitch" class="onoffswitch-checkbox" id="c_' . $category['id'] . '" data-id="' . $category['id'] . '" ' . $checked . '>
						<label class="onoffswitch-label" for="c_' . $category['id'] . '"></label>
						</div>';

						?>

					</td>
					<td>
						<?php if (has_permission('service_management', '', 'edit') || is_admin()) { ?>
							<a href="#" onclick="edit_category(this,<?php echo new_html_entity_decode($category['id']); ?>); return false;" data-name="<?php echo new_html_entity_decode($category['name']); ?>" data-commodity_group_code="<?php echo new_html_entity_decode($category['commodity_group_code']); ?>" data-note="<?php echo new_html_entity_decode($category['note']); ?>" data-display="<?php echo new_html_entity_decode($category['display']); ?>" class="btn btn-default btn-icon"><i class="fa-regular fa-pen-to-square"></i>
							</a>
						<?php } ?>

						<?php if (has_permission('service_management', '', 'delete') || is_admin()) { ?> 
							<a href="<?php echo admin_url('service_management/delete_category/'.$category['id']); ?>" class="btn btn-danger btn-icon _delete"><i class="fa fa-remove"></i></a>
						<?php } ?>
					</td>
				</tr>
			<?php } ?>
		</tbody>
	</table>

	<div class="modal fade" id="measure_category" tabindex="-1" role="dialog">
		<div class="modal-dialog setting-handsome-table">
			<?php echo form_open_multipart(admin_url('service_management/categories'), array('id'=>'add_edit_category')); ?>

			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title">
						<span class="add-title"><?php echo _l('sm_add_category'); ?></span>
						<span class="edit-title"><?php echo _l('sm_update_category'); ?></span>
					</h4>
				</div>

				<div class="modal-body">
					<div class="row">
						<div class="col-md-12">
							<div id="categories_id"></div>   
							<div class="form"> 
								<div class="col-md-6">
									<?php echo render_input('name', 'sm_category_name'); ?>
								</div>
								<div class="col-md-6">
									<?php echo render_input('commodity_group_code', 'sm_category_code'); ?>
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
