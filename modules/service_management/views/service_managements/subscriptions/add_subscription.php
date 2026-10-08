<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php init_head(); ?>
<div id="wrapper">
	<div class="content">
		<div class="row">
			<div class="col-md-12">
				<div class="panel_s">
					<div class="panel-body accounting-template">
						<div class="row">
							<div class="col-md-12">
								<h4 class="no-margin font-bold "><i class="fa fa-object-ungroup menu-icon" aria-hidden="true"></i> <?php echo new_html_entity_decode($title); ?></h4>
								<hr>
							</div>
						</div>
						<?php if(isset($subscription_error)) { ?>
							<div class="alert alert-warning">
								<?php echo new_html_entity_decode($subscription_error); ?>
							</div>
						<?php } ?>
						<?php echo form_open('', array('id'=>'subscriptionForm','class'=>'_transaction_form')); ?>
						<div class="row">
							<div class="col-md-12">
								<div class="">
									<?php echo render_select('product_id', $subscription_services, array('id', array('commodity_code', 'description')), 'sm_subscription_service'); ?>

									<div class="form-group select-placeholder hide">
										<label for="stripe_plan_id"><?php echo _l('billing_plan'); ?></label>
										<select id="stripe_plan_id"
										name="stripe_plan_id"
										class="selectpicker"
										data-live-search="true"
										data-width="100%"
										data-none-selected-text="<?php echo _l('stripe_subscription_select_plan'); ?>">
										<option value=""></option>
										<?php if(isset($plans->data)){ ?>
											<?php foreach($plans->data as $plan) {

												if(!$plan->active) {
													if(!isset($subscription)) {
														continue;
													} else {
														if($subscription->stripe_plan_id != $plan->id) {
															continue;
														}
													}
												}

												$selected = '';
												if(isset($subscription) && $subscription->stripe_plan_id == $plan->id) {
													$selected = ' selected';
												}
												$subtext = app_format_money(strcasecmp($plan->currency, 'JPY') == 0 ? $plan->amount : $plan->amount / 100, strtoupper($plan->currency));
												if($plan->interval_count == 1) {
													$subtext .= ' / ' . $plan->interval;
												} else {
													$subtext .= ' (every '.$plan->interval_count.' '.$plan->interval.'s)';
												}
												?>
												<option value="<?php echo new_html_entity_decode($plan->id); ?>" data-interval-count="<?php echo new_html_entity_decode($plan->interval_count); ?>" data-interval="<?php echo new_html_entity_decode($plan->interval); ?>" data-amount="<?php echo new_html_entity_decode($plan->amount); ?>" data-subtext="<?php echo new_html_entity_decode($subtext); ?>"<?php echo new_html_entity_decode($selected); ?>>
													<?php
													if(!empty($plan->nickname)) {
														echo new_html_entity_decode($plan->nickname);
													} else if(isset($plan->product->name)) {
														echo new_html_entity_decode($plan->product->name);
													} else {
														echo '[Plan Name Not Set in Stripe, ID:'.$plan->id.']';
													}
													?>
												</option>
											<?php } ?>
										<?php } ?>
									</select>
								</div>
								<?php echo render_input('quantity', _l('item_quantity_placeholder'), isset($subscription) ? $subscription->quantity : 1, 'number', [], [], 'hide', ''); ?>
								<?php
								$params = array('data-lazy'=>'false', 'data-date-min-date' => date('Y-m-d', strtotime('+1 days', strtotime(date('Y-m-d')))));
								if(isset($subscription) && !empty($subscription->stripe_subscription_id)){
									$params['disabled'] = true;
								}
								echo '<div class="hide"><div id="first_billing_date_wrapper" class="hide">';
								if(!isset($params['disabled'])){
									echo '<i class="fa fa-question-circle pull-left" data-toggle="tooltip" data-placement="right" data-title="'._l('subscription_first_billing_date_info').'"></i>';
								}
								echo render_date_input('date', 'first_billing_date', isset($subscription) ? _d($subscription->date) : '', $params, [], '', );
								echo '</div></div>';
								if(isset($subscription) && !empty($subscription->stripe_subscription_id) && $subscription->status != 'canceled' && $subscription->status != 'future') { ?>
									<div class="checkbox checkbox-info hide" id="prorateWrapper">
										<input type="checkbox" id="prorate" class="ays-ignore" checked name="prorate">
										<label for="prorate"><a href="https://stripe.com/docs/billing/subscriptions/prorations" target="_blank"><i class="fa fa-link"></i></a> Prorate</label>
									</div>
								<?php } ?>
							</div>
							<?php $value = (isset($subscription) ? $subscription->name : ''); ?>
							<?php echo render_input('name','subscription_name',$value,'text',[],[],'hide','ays-ignore'); ?>
							<?php $value = (isset($subscription) ? $subscription->description : ''); ?>
							<?php echo render_textarea('description','subscriptions_description',$value,[],[],'hide','ays-ignore'); ?>
							<div class="form-group hide">
								<div class="checkbox checkbox-primary">
									<input type="checkbox" id="description_in_item" class="ays-ignore" name="description_in_item"<?php if(isset($subscription) && $subscription->description_in_item == '1'){echo ' checked';} ?>>
									<label for="description_in_item"><i class="fa fa-question-circle" data-toggle="tooltip" data-title="<?php echo _l('description_in_invoice_item_help'); ?>"></i> <?php echo _l('description_in_invoice_item'); ?></label>
								</div>
							</div>
							<div class="form-group select-placeholder f_client_id">
								<label for="clientid" class="control-label"><?php echo _l('client'); ?></label>
								<select id="clientid" name="clientid" data-live-search="true" data-width="100%" class="ajax-search ays-ignore" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>"<?php if(isset($subscription) && !empty($subscription->stripe_subscription_id)){echo ' disabled'; } ?>>
									<?php $selected = (isset($subscription) ? $subscription->clientid : '');
									if($selected == ''){
										$selected = (isset($customer_id) ? $customer_id: '');
									}
									if($selected != ''){
										$rel_data = get_relation_data('customer',$selected);
										$rel_val = get_relation_values($rel_data,'customer');
										echo '<option value="'.$rel_val['id'].'" selected>'.$rel_val['name'].'</option>';
									} ?>
								</select>
							</div>
							<div class="hide">
							<div class="hide form-group select-placeholder projects-wrapper<?php if((!isset($subscription)) || (isset($subscription) && !customer_has_projects($subscription->clientid))){ echo ' hide';} ?>">
								<label for="project_id"><?php echo _l('project'); ?></label>
								<div id="project_ajax_search_wrapper">
									<select name="project_id" id="project_id" class="projects ajax-search ays-ignore" data-live-search="true" data-width="100%" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">
										<?php
										if(isset($subscription) && $subscription->project_id != 0){
											echo '<option value="'.$subscription->project_id.'" selected>'.get_project_name_by_id($subscription->project_id).'</option>';
										}
										?>
									</select>
								</div>
							</div>
							</div>

							<?php
							$s_attrs = array('disabled'=>true, 'data-show-subtext'=>true);
							foreach($currencies as $currency){
								if($currency['isdefault'] == 1){
									$s_attrs['data-base'] = $currency['id'];
								}
								if(isset($subscription)){
									if($currency['id'] == $subscription->currency){
										$selected = $currency['id'];
									}
								} else {
									if($currency['isdefault'] == 1){
										$selected = $currency['id'];
									}
								}
							}
							?>
							<?php if(isset($subscription) && isset($stripeSubscription)) { ?>
								<?php
								if(strtolower($subscription->currency_name) != strtolower($stripeSubscription->plan->currency)) {  ?>
									<div class="alert alert-warning">
										<?php echo _l('subscription_plan_currency_does_not_match'); ?>
									</div>
								<?php } ?>
							<?php } ?>
							<?php echo render_select('currency', $currencies, array('id', 'name', 'symbol'), 'currency', $selected,  $s_attrs, [], 'hide', 'ays-ignore'); ?>

							<div class="row hide">
								<div class="col-md-6">
									<div class="form-group select-placeholder">
										<label class="control-label" for="tax"><?php echo _l('tax_1'); ?> (Stripe)</label>
										<select class="selectpicker" data-width="100%" name="stripe_tax_id" data-none-selected-text="<?php echo _l('no_tax'); ?>">
											<option value=""></option>
											<?php foreach($stripe_tax_rates->data as $tax){
												if($tax->inclusive) {
													continue;
												}
												if(!$tax->active) {
													if(!isset($subscription)) {
														continue;
													} else {
														if($subscription->stripe_tax_id != $tax->id) {
															continue;
														}
													}
												}
												?>
												<option value="<?php echo new_html_entity_decode($tax->id); ?>"
													data-subtext="<?php echo !empty($tax->country) ? $tax->country : ''; ?>"
													<?php if(isset($subscription) && $subscription->stripe_tax_id == $tax->id){echo ' selected';} ?>>
													<?php echo new_html_entity_decode($tax->display_name); ?>
													<?php echo !empty($tax->jurisdiction) ? ' - ' . $tax->jurisdiction.' ' : ''; ?> (<?php echo new_html_entity_decode($tax->percentage); ?>%)
												</option>
											<?php } ?>
										</select>
									</div>
								</div>
								<div class="col-md-6">

									<div class="form-group select-placeholder">
										<label class="control-label" for="tax"><?php echo _l('tax_2'); ?> (Stripe)</label>
										<select class="selectpicker" data-width="100%" name="stripe_tax_id_2" data-none-selected-text="<?php echo _l('no_tax'); ?>">
											<option value=""></option>
											<?php foreach($stripe_tax_rates->data as $tax){
												if($tax->inclusive) {
													continue;
												}
												if(!$tax->active) {
													if(!isset($subscription)) {
														continue;
													} else {
														if($subscription->stripe_tax_id_2 != $tax->id) {
															continue;
														}
													}
												}
												?>
												<option value="<?php echo new_html_entity_decode($tax->id); ?>"
													data-subtext="<?php echo !empty($tax->country) ? $tax->country : ''; ?>"
													<?php if(isset($subscription) && $subscription->stripe_tax_id_2 == $tax->id){echo ' selected';} ?>>
													<?php echo new_html_entity_decode($tax->display_name); ?>
													<?php echo !empty($tax->jurisdiction) ? ' - ' . $tax->jurisdiction.' ' : ''; ?> (<?php echo new_html_entity_decode($tax->percentage); ?>%)
												</option>
											<?php } ?>
										</select>
									</div>
								</div>
							</div>

							<?php $value = (isset($subscription) ? $subscription->terms : ''); ?>
							<?php echo render_textarea('terms', 'terms_and_conditions', $value, [ 'placeholder'=> _l('subscriptions_terms_info') ], [], 'hide','ays-ignore'); ?>
						</div>
					</div>
						<div class="btn-bottom-toolbar text-right">
					<a href="<?php echo admin_url('service_management/service_managements'); ?>"class="btn btn-default text-right mright5"><?php echo _l('close'); ?></a>

					<?php if((isset($subscription) && has_permission('service_management','','edit')) || !isset($subscription)){ ?>
							<button type="submit" class="btn btn-info" data-loading-text="<?php echo _l('wait_text'); ?>" data-form="#subscriptionForm">
								<?php echo _l('save'); ?>
							</button>
					<?php } ?>
						</div>
					<?php echo form_close(); ?>

				</div>
			</div>
		</div>
	</div>
</div>
</div>
<?php init_tail(); ?>
	<?php require 'modules/service_management/assets/js/service_managements/subscriptions/add_subscription_js.php';?>
