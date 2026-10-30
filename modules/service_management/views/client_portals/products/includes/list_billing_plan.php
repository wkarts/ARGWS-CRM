<?php 
if(count($item_billing_plan) > 0){
	?>
	<div class="row variation-row ">
		<div class="col-md-12 variation-items">
			<label><?php echo _l('sm_product_cycle'); ?></label><br>
			<?php foreach ($item_billing_plan as $key => $billing_plan) { ?>
				<?php if($billing_plan['status_cycles'] == 'active'){ ?>
					<button class="label label-default product-variation" data-billing_plan_id="<?php echo new_html_entity_decode($billing_plan['id']) ?>">
						<?php echo new_html_entity_decode(app_format_money((float)$billing_plan['item_rate'], $base_currency).' ('. $billing_plan['unit_value'].' '. _l($billing_plan['unit_type']) . ')'); ?>
					</button>
				<?php } ?>
			<?php } ?>
		</div>	
	</div><br>
	<?php  } ?>