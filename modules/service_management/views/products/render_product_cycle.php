<?php if(isset($product) && count($product->item_billing_plan) > 0){ ?>
	<!-- update -->
	<?php 
	foreach ($product->item_billing_plan as $key => $value) { ?>

		<div id="item_approve">
			<div class="col-md-11">
				<div class="hide">
					<?php echo render_input('cycle_id['.$key.']','cycle_id', $value['id'], 'text'); ?>
					<?php echo render_input('item_id['.$key.']','product_id', $value['item_id'], 'text'); ?>
					<?php echo render_input('unit_value['.$key.']','unit_value', $value['unit_value'], 'text'); ?>
					<?php echo render_input('unit_type['.$key.']','unit_type', $value['unit_type'], 'text'); ?>
				</div>

				<div class="col-md-4">
					<?php echo render_select('unit_id['.$key.']', $units , array('id', array('unit_value', 'unit_type')), 'sm_item_unit', $value['unit_id']); ?>
				</div>
				<div class="col-md-2">
					<?php echo render_input('item_rate['.$key.']', 'sm_rate', $value['item_rate'], 'text'); ?>
				</div>

				<div class="col-md-2">
					<?php $sm_extend_cycle_value = sm_extend_cycle_value(); ?>
					<?php echo render_select('extend_value['.$key.']', $sm_extend_cycle_value , array('name', 'label'), 'sm_extend_value', $value['extend_value']); ?>
				</div>
				<div class="col-md-2">
					<?php echo render_input('promotion_extended_percent['.$key.']', 'sm_promotion_extended_percent', $value['promotion_extended_percent'], 'text'); ?>
				</div>
				<div class="col-md-2 mtop30">
					<div class="onoffswitch">
						<input type="checkbox"  name="status_cycles[<?php echo new_html_entity_decode($key) ?>]" class="onoffswitch-checkbox" id="status_cycles[<?php echo new_html_entity_decode($key) ?>]" <?php if($value['status_cycles'] == 'active'){ echo "checked";} ?>>
						<label class="onoffswitch-label" for="status_cycles[<?php echo new_html_entity_decode($key) ?>]"></label>
					</div>
				</div>
			</div>
			<div class="col-md-1 new_vendor_requests_button">
				<button name="add" class="btn   <?php if($key == 0){ echo " new_wh_approval btn-success" ;}else{ echo " remove_wh_approval btn-danger" ;}; ?>" data-ticket="true" type="button"><?php if($key == 0 ){ echo '<i class="fa fa-plus"></i>';}else{ echo '<i class="fa fa-minus"></i>';} ?>
				</button>
			</div>
		</div>

	<?php } ?>


<?php }else{ ?>
	<!-- add new -->
	<div id="item_approve">
		<div class="col-md-11">
			<div class="hide">
				<?php echo render_input('cycle_id[0]','cycle_id', 0, 'text'); ?>
				<?php echo render_input('item_id[0]','product_id','', 'text'); ?>
				<?php echo render_input('unit_value[0]','unit_value','', 'text'); ?>
				<?php echo render_input('unit_type[0]','unit_type','', 'text'); ?>
			</div>

			<div class="col-md-4">
				<?php echo render_select('unit_id[0]', $units , array('id', array('unit_value', 'unit_type')), 'sm_item_unit'); ?>
			</div>
			<div class="col-md-2">
				<?php echo render_input('item_rate[0]', 'sm_rate', '1.0', 'text'); ?>
			</div>

			<div class="col-md-2">
				<?php $sm_extend_cycle_value = sm_extend_cycle_value(); ?>
				<?php echo render_select('extend_value[0]', $sm_extend_cycle_value , array('name', 'label'), 'sm_extend_value'); ?>
			</div>
			<div class="col-md-2">
				<?php echo render_input('promotion_extended_percent[0]', 'sm_promotion_extended_percent', '0', 'text'); ?>
			</div>
			<div class="col-md-2 mtop30">
				<div class="onoffswitch">
					<input type="checkbox"  name="status_cycles[0]" class="onoffswitch-checkbox" id="status_cycles[0]">
					<label class="onoffswitch-label" for="status_cycles[0]"></label>
				</div>
			</div>
		</div>
		<div class="col-md-1 new_vendor_requests_button">
			<span class="pull-bot">
				<button name="add" class="btn new_wh_approval btn-success" data-ticket="true" type="button"><i class="fa fa-plus"></i></button>
			</span>
		</div>
	</div>
	<?php } ?>
