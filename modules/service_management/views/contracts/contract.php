<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
	<div class="content">
		<div class="row">
			<div class="col-md-5 left-column">
				<div class="panel_s">
					<div class="panel-body">
						<?php echo form_open(admin_url('service_management/contract/'.$id), array('id'=>'contract-form')); ?>

						<div class="form-group">
							<div class="checkbox checkbox-primary no-mtop checkbox-inline">
								<input type="checkbox" id="trash" name="trash"<?php if(isset($contract)){if($contract->trash == 1){echo ' checked';}}; ?>>
								<label for="trash"><i class="fa fa-question-circle" data-toggle="tooltip" data-placement="left" title="<?php echo _l('contract_trash_tooltip'); ?>" ></i> <?php echo _l('contract_trash'); ?></label>
							</div>
							<div class="checkbox checkbox-primary checkbox-inline">
								<input type="checkbox" name="not_visible_to_client" id="not_visible_to_client" <?php if(isset($contract)){if($contract->not_visible_to_client == 1){echo 'checked';}}; ?>>
								<label for="not_visible_to_client"><?php echo _l('contract_not_visible_to_client'); ?></label>
							</div>
						</div>
						<div class="form-group select-placeholder f_client_id">
							<label for="clientid" class="control-label"><span class="text-danger">* </span><?php echo _l('contract_client_string'); ?></label>
							<select id="clientid" name="client" data-live-search="true" data-width="100%" class="ajax-search" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">
								<?php $selected = (isset($contract) ? $contract->client : '');
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
						<div class="form-group select-placeholder projects-wrapper<?php if((!isset($contract)) || (isset($contract) && !customer_has_projects($contract->client))){ echo ' hide';} ?>">
							<label for="project_id"><?php echo _l('project'); ?></label>
							<div id="project_ajax_search_wrapper">
								<select name="project_id" id="project_id" class="projects ajax-search ays-ignore" data-live-search="true" data-width="100%" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">
									<?php
									if(isset($contract) && $contract->project_id != 0){
										echo '<option value="'.$contract->project_id.'" selected>'.get_project_name_by_id($contract->project_id).'</option>';
									}
									?>
								</select>
							</div>
						</div>

						<?php $value = (isset($contract) ? $contract->subject : ''); ?>
						<i class="fa fa-question-circle pull-left" data-toggle="tooltip" title="<?php echo _l('contract_subject_tooltip'); ?>"></i>
						<?php echo render_input('subject','contract_subject',$value); ?>
						<div class="form-group">
							<label for="contract_value"><?php echo _l('contract_value'); ?></label>
							<div class="input-group" data-toggle="tooltip" title="<?php echo _l('contract_value_tooltip'); ?>">
								<input type="number" class="form-control" name="contract_value" value="<?php if(isset($contract)){echo new_html_entity_decode($contract->contract_value); }?>">
								<div class="input-group-addon">
									<?php echo new_html_entity_decode($base_currency->symbol); ?>
								</div>
							</div>
						</div>
						<?php
						$selected = (isset($contract) ? $contract->contract_type : '');
						if(is_admin() || get_option('staff_members_create_inline_contract_types') == '1'){
							echo render_select_with_input_group('contract_type',$types,array('id','name'),'contract_type',$selected,'<div class="input-group-btn"><a href="#" class="btn btn-default" onclick="new_type();return false;"><i class="fa fa-plus"></i></a></div>');
						} else {
							echo render_select('contract_type',$types,array('id','name'),'contract_type',$selected);
						}
						?>

						<?php 
						$order_id = (isset($contract) ? $contract->order_id : '');

						?>
						<?php echo render_select('order_id',$orders,array('id', 'order_code'), 'sm_order', $order_id, [], [], '', '' , true); ?>  

						<div class="row">
							<div class="col-md-6">
								<?php $value = (isset($contract) ? _d($contract->datestart) : _d(date('Y-m-d'))); ?>
								<?php echo render_date_input('datestart','contract_start_date',$value); ?>
							</div>
							<div class="col-md-6">
								<?php $value = (isset($contract) ? _d($contract->dateend) : ''); ?>
								<?php echo render_date_input('dateend','contract_end_date',$value); ?>
							</div>
						</div>
						<?php $value = (isset($contract) ? $contract->description : ''); ?>
						<?php echo render_textarea('description','contract_description',$value,array('rows'=>10)); ?>
						<?php $rel_id = (isset($contract) ? $contract->id : false); ?>
						<?php echo render_custom_fields('contracts',$rel_id); ?>
						<div class="btn-bottom-toolbar text-right">
							<button type="submit" class="btn btn-info"><?php echo _l('submit'); ?></button>
						</div>
						<?php echo form_close(); ?>
					</div>
				</div>
			</div>
			<?php if(isset($contract)) { ?>
				<div class="col-md-7 right-column">
					<div class="panel_s">
						<div class="panel-body">
							<h4 class="no-margin"><?php echo new_html_entity_decode($contract->subject); ?></h4>
							<a href="<?php echo  site_url('service_management/service_management_client/client_contract/' . $contract->id . '/' . $contract->hash); ?>" target="_blank">
								<?php echo _l('view_contract'); ?>
							</a>
							<hr class="hr-panel-heading" />
							<?php if($contract->trash > 0){
								echo '<div class="ribbon default"><span>'._l('contract_trash').'</span></div>';
							} ?>
							<div class="horizontal-scrollable-tabs preview-tabs-top">
								<div class="scroller arrow-left"><i class="fa fa-angle-left"></i></div>
								<div class="scroller arrow-right"><i class="fa fa-angle-right"></i></div>
								<div class="horizontal-tabs">
									<ul class="nav nav-tabs tabs-in-body-no-margin contract-tab nav-tabs-horizontal mbot15" role="tablist">
										<li role="presentation" class="<?php if(!$this->input->get('tab') || $this->input->get('tab') == 'tab_content'){echo 'active';} ?>">
											<a href="#tab_content" aria-controls="tab_content" role="tab" data-toggle="tab">
												<?php echo _l('contract_content'); ?>
											</a>
										</li>
										<li role="presentation" class="<?php if($this->input->get('tab') == 'tab_contract_addendum'){echo 'active';} ?>">
											<a href="#tab_contract_addendum" aria-controls="tab_contract_addendum" role="tab" data-toggle="tab">
												<?php echo _l('sm_contract_addendum'); ?>
											</a>
										</li>

										<li role="presentation" class="<?php if($this->input->get('tab') == 'attachments'){echo 'active';} ?>">
											<a href="#attachments" aria-controls="attachments" role="tab" data-toggle="tab">
												<?php echo _l('contract_attachments'); ?>
												<?php if($totalAttachments = count($contract->attachments)) { ?>
													<span class="badge attachments-indicator"><?php echo new_html_entity_decode($totalAttachments); ?></span>
												<?php } ?>
											</a>
										</li>
										<li role="presentation">
											<a href="#tab_comments" aria-controls="tab_comments" role="tab" data-toggle="tab" onclick="get_contract_comments(); return false;">
												<?php echo _l('contract_comments'); ?>
												<?php
												$totalComments = total_rows(db_prefix().'contract_comments','contract_id='.$contract->id)
												?>
												<span class="badge comments-indicator<?php echo new_html_entity_decode($totalComments == 0 ? ' hide' : ''); ?>"><?php echo new_html_entity_decode($totalComments); ?></span>
											</a>
										</li>
										<li role="presentation" class="<?php if($this->input->get('tab') == 'renewals'){echo 'active';} ?>">
											<a href="#renewals" aria-controls="renewals" role="tab" data-toggle="tab">
												<?php echo _l('no_contract_renewals_history_heading'); ?>
												<?php if($totalRenewals = count($contract_renewal_history)) { ?>
													<span class="badge"><?php echo new_html_entity_decode($totalRenewals); ?></span>
												<?php } ?>
											</a>
										</li>
										<li role="presentation" class="tab-separator hide">
											<a href="#tab_tasks" aria-controls="tab_tasks" role="tab" data-toggle="tab" onclick="init_rel_tasks_table(<?php echo new_html_entity_decode($contract->id); ?>,'contract'); return false;">
												<?php echo _l('tasks'); ?>
											</a>
										</li>
										<li role="presentation" class="tab-separator">
											<a href="#tab_notes" onclick="get_sales_notes(<?php echo new_html_entity_decode($contract->id); ?>,'contracts'); return false" aria-controls="tab_notes" role="tab" data-toggle="tab">
												<?php echo _l('contract_notes'); ?>
												<span class="notes-total">
													<?php if($totalNotes > 0){ ?>
														<span class="badge"><?php echo new_html_entity_decode($totalNotes); ?></span>
													<?php } ?>
												</span>
											</a>
										</li>
										<li role="presentation" class="tab-separator hide">
											<a href="#tab_templates" onclick="get_templates('contracts', <?php echo new_html_entity_decode($contract->id) ?>); return false" aria-controls="tab_templates" role="tab" data-toggle="tab">
												<?php echo _l('templates'); ?>
											</a>
										</li>
										<li role="presentation" data-toggle="tooltip" title="<?php echo _l('emails_tracking'); ?>" class="tab-separator">
											<a href="#tab_emails_tracking" aria-controls="tab_emails_tracking" role="tab" data-toggle="tab">
												<?php if(!is_mobile()){ ?>
													<i class="fa fa-envelope-open-o" aria-hidden="true"></i>
												<?php } else { ?>
													<?php echo _l('emails_tracking'); ?>
												<?php } ?>
											</a>
										</li>
										<li role="presentation" class="tab-separator toggle_view">
											<a href="#" onclick="contract_full_view(); return false;" data-toggle="tooltip" data-title="<?php echo _l('toggle_full_view'); ?>">
												<i class="fa fa-expand"></i></a>
											</li>
										</ul>
									</div>
								</div>
								<div class="tab-content">
									<div role="tabpanel" class="tab-pane<?php if(!$this->input->get('tab') || $this->input->get('tab') == 'tab_content'){echo ' active';} ?>" id="tab_content">
										<div class="row">
											<?php if($contract->signed == 1){ ?>
												<div class="col-md-12">
													<div class="alert alert-success">
														<?php echo _l('document_signed_info',array(
															'<b>'.$contract->acceptance_firstname . ' ' . $contract->acceptance_lastname . '</b> (<a href="mailto:'.$contract->acceptance_email.'">'.$contract->acceptance_email.'</a>)',
															'<b>'. _dt($contract->acceptance_date).'</b>',
															'<b>'.$contract->acceptance_ip.'</b>')
														); ?>
													</div>
												</div>
											<?php } else if($contract->marked_as_signed == 1) { ?>
												<div class="col-md-12">
													<div class="alert alert-info">
														<?php echo _l('contract_marked_as_signed_info'); ?>
													</div>
												</div>
											<?php } ?>
											<div class="col-md-12 text-right _buttons">
												<div class="btn-group">
													<a href="#" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fa fa-file-pdf-o"></i><?php if(is_mobile()){echo ' PDF';} ?> <span class="caret"></span></a>
													<ul class="dropdown-menu dropdown-menu-right">
														<li class="hidden-xs"><a href="<?php echo admin_url('service_management/pdf/'.$contract->id.'?output_type=I'); ?>"><?php echo _l('view_pdf'); ?></a></li>
														<li class="hidden-xs"><a href="<?php echo admin_url('service_management/pdf/'.$contract->id.'?output_type=I'); ?>" target="_blank"><?php echo _l('view_pdf_in_new_window'); ?></a></li>
														<li><a href="<?php echo admin_url('service_management/pdf/'.$contract->id); ?>"><?php echo _l('download'); ?></a></li>
														<li>
															<a href="<?php echo admin_url('service_management/pdf/'.$contract->id.'?print=true'); ?>" target="_blank">
																<?php echo _l('print'); ?>
															</a>
														</li>
													</ul>
												</div>
												<a href="#" class="btn btn-default hide" data-target="#contract_send_to_client_modal" data-toggle="modal"><span class="btn-with-tooltip" data-toggle="tooltip" data-title="<?php echo _l('contract_send_to_email'); ?>" data-placement="bottom">
													<i class="fa fa-envelope"></i></span>
												</a>
												<div class="btn-group">
													<button type="button" class="btn btn-default pull-left dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
														<?php echo _l('more'); ?> <span class="caret"></span>
													</button>
													<ul class="dropdown-menu dropdown-menu-right">
														<li >
															<a href="<?php echo site_url('service_management/service_management_client/client_contract/' . $contract->id . '/' . $contract->hash); ?>" target="_blank">
																<?php echo _l('view_contract'); ?>
															</a>
														</li>
														<?php
														if($contract->signed == 0 && $contract->marked_as_signed == 0 && staff_can('edit', 'contracts')) { ?>
															<li>
																<a href="<?php echo admin_url('service_management/mark_as_signed/'.$contract->id); ?>">
																	<?php echo _l('mark_as_signed'); ?>
																</a>
															</li>
														<?php } else if($contract->signed == 0 && $contract->marked_as_signed == 1 && staff_can('edit', 'contracts')) { ?>
															<li>
																<a href="<?php echo admin_url('service_management/unmark_as_signed/'.$contract->id); ?>">
																	<?php echo _l('unmark_as_signed'); ?>
																</a>
															</li>
														<?php } ?>

														<?php if(has_permission('service_management','','create')){ ?>
															<li  class="hide">
																<a href="<?php echo admin_url('service_management/copy/'.$contract->id); ?>">
																	<?php echo _l('contract_copy'); ?>
																</a>
															</li>
														<?php } ?>
														<?php if($contract->signed == 1 && has_permission('service_management','','delete')){ ?>
															<li>
																<a href="<?php echo admin_url('service_management/clear_signature/'.$contract->id); ?>" class="_delete">
																	<?php echo _l('clear_signature'); ?>
																</a>
															</li>
														<?php } ?>
														<?php if(has_permission('service_management','','delete')){ ?>
															<li>
																<a href="<?php echo admin_url('service_management/delete/'.$contract->id); ?>" class="_delete">
																	<?php echo _l('delete'); ?></a>
																</li>
															<?php } ?>
														</ul>
													</div>
												</div>
												<div class="col-md-12">
													<?php if(isset($contract_merge_fields)){ ?>
														<hr class="hr-panel-heading" />
														<p class="bold mtop10 text-right"><a href="#" onclick="slideToggle('.avilable_merge_fields'); return false;"><?php echo _l('available_merge_fields'); ?></a></p>
														<div class=" avilable_merge_fields mtop15 hide">
															<ul class="list-group">
																<?php
																foreach($contract_merge_fields as $field){
																	foreach($field as $f){
																		if($f['key'] != '{logo_image_with_url}' && $f['key'] != '{dark_logo_image_with_url}' ){

																			echo '<li class="list-group-item"><b>'.$f['name'].'</b>  <a href="#" class="pull-right" onclick="insert_merge_field(this); return false">'.$f['key'].'</a></li>';
																		}
																	}
																}
																?>
															</ul>
														</div>
													<?php } ?>
												</div>
											</div>
											<hr class="hr-panel-heading" />
											<?php if(!staff_can('edit','contracts')) { ?>
												<div class="alert alert-warning contract-edit-permissions">
													<?php echo _l('contract_content_permission_edit_warning'); ?>
												</div>
											<?php } ?>
											<div class="sm-contract-boder  tc-content<?php if(staff_can('edit','contracts')){echo ' editable';} ?>"
												>
												<?php
												if(empty($contract->content) && staff_can('edit','contracts')){
													echo hooks()->apply_filters('new_contract_default_content', '<span class="text-danger text-uppercase mtop15 editor-add-content-notice"> ' . _l('click_to_add_content') . '</span>');
												} else {
													echo new_html_entity_decode($contract->content);
												}
												?>
											</div>
											<?php if(!empty($contract->signature)) { ?>
												<div class="row mtop25">
													<div class="col-md-6 col-md-offset-6 text-right">
														<div class="bold">
															<p class="no-mbot"><?php echo _l('contract_signed_by') . ": {$contract->acceptance_firstname} {$contract->acceptance_lastname}"?></p>
															<p class="no-mbot"><?php echo _l('contract_signed_date') . ': ' . _dt($contract->acceptance_date) ?></p>
															<p class="no-mbot"><?php echo _l('contract_signed_ip') . ": {$contract->acceptance_ip}"?></p>
														</div>
														<p class="bold"><?php echo _l('document_customer_signature_text'); ?>
														<?php if($contract->signed == 1 && has_permission('service_management','','delete')){ ?>
															<a href="<?php echo admin_url('service_management/clear_signature/'.$contract->id); ?>" data-toggle="tooltip" title="<?php echo _l('clear_signature'); ?>" class="_delete text-danger">
																<i class="fa fa-remove"></i>
															</a>
														<?php } ?>
													</p>
													<div class="pull-right">
														<img src="<?php echo site_url('download/preview_image?path='.protected_file_url_by_path(SM_CONTRACTS_UPLOADS_FOLDER.$contract->id.'/'.$contract->signature)); ?>" class="img-responsive" alt="">
													</div>
												</div>
											</div>
										<?php } ?>
									</div>

									<div role="tabpanel" class="tab-pane<?php if($this->input->get('tab') == 'tab_contract_addendum'){echo ' active';} ?>" id="tab_contract_addendum">

										<?php defined('BASEPATH') or exit('No direct script access allowed');

										$table_data = array(
											_l('the_number_sign'),
											_l('contract_list_subject'),
											_l('sm_contract'),
											_l('client'),
											_l('contract_list_start_date'),
										);

										$table_data = hooks()->apply_filters('contract_addendums_table_columns', $table_data);

										render_datatable($table_data, (isset($class) ? $class : 'contracts'),[],[
											'data-last-order-identifier' => 'contracts',
											'data-default-order'         => get_table_last_order('contracts'),
										]);

										?>

									</div>

									<div role="tabpanel" class="tab-pane" id="tab_notes">
										<?php echo form_open(admin_url('service_management/add_note/'.$contract->id),array('id'=>'sales-notes','class'=>'contract-notes-form')); ?>
										<?php echo render_textarea('description'); ?>
										<div class="text-right">
											<button type="submit" class="btn btn-info mtop15 mbot15"><?php echo _l('contract_add_note'); ?></button>
										</div>
										<?php echo form_close(); ?>
										<hr />
										<div class="panel_s mtop20 no-shadow" id="sales_notes_area">
										</div>
									</div>
									<div role="tabpanel" class="tab-pane" id="tab_comments">
										<div class="row contract-comments mtop15">
											<div class="col-md-12">
												<div id="contract-comments"></div>
												<div class="clearfix"></div>
												<textarea name="content" id="comment" rows="4" class="form-control mtop15 contract-comment"></textarea>
												<button type="button" class="btn btn-info mtop10 pull-right" onclick="add_contract_comment();"><?php echo _l('proposal_add_comment'); ?></button>
											</div>
										</div>
									</div>
									<div role="tabpanel" class="tab-pane<?php if($this->input->get('tab') == 'attachments'){echo ' active';} ?>" id="attachments">
										<?php echo form_open(admin_url('service_management/add_contract_attachment/'.$contract->id),array('id'=>'contract-attachments-form','class'=>'dropzone')); ?>
										<?php echo form_close(); ?>
										<div class="text-right mtop15">
											<button class="gpicker" data-on-pick="contractGoogleDriveSave">
												<i class="fa fa-google" aria-hidden="true"></i>
												<?php echo _l('choose_from_google_drive'); ?>
											</button>
											<div id="dropbox-chooser"></div>
											<div class="clearfix"></div>
										</div>

										<div id="contract_attachments" class="mtop30">
											<?php
											$data = '<div class="row">';
											foreach($contract->attachments as $attachment) {
												$href_url = admin_url('service_management/contract_file/sm_contract/'.$attachment['attachment_key']);

												if(!empty($attachment['external'])){
													$href_url = $attachment['external_link'];
												}
												$data .= '<div class="display-block contract-attachment-wrapper">';
												$data .= '<div class="col-md-10">';
												$data .= '<div class="pull-left"><i class="'.get_mime_class($attachment['filetype']).'"></i></div>';
												$data .= '<a href="'.$href_url.'"'.(!empty($attachment['external']) ? ' target="_blank"' : '').'>'.$attachment['file_name'].'</a>';
												$data .= '<p class="text-muted">'.$attachment["filetype"].'</p>';
												$data .= '</div>';
												$data .= '<div class="col-md-2 text-right">';
												if($attachment['staffid'] == get_staff_user_id() || is_admin()){
													$data .= '<a href="#" class="text-danger" onclick="delete_contract_attachment(this,'.$attachment['id'].'); return false;"><i class="fa fa fa-times"></i></a>';
												}
												$data .= '</div>';
												$data .= '<div class="clearfix"></div><hr/>';
												$data .= '</div>';
											}
											$data .= '</div>';
											echo new_html_entity_decode($data);
											?>
										</div>
									</div>
									<div role="tabpanel" class="tab-pane<?php if($this->input->get('tab') == 'renewals'){echo ' active';} ?>" id="renewals">
										<?php if(has_permission('service_management', '', 'create') || has_permission('service_management', '', 'edit')){ ?>
											<div class="_buttons">
												<a href="#" class="btn btn-default" data-toggle="modal" data-target="#renew_contract_modal">
													<i class="fa fa-refresh"></i> <?php echo _l('contract_renew_heading'); ?>
												</a>
											</div>
											<hr />
										<?php } ?>
										<div class="clearfix"></div>
										<?php
										if(count($contract_renewal_history) == 0){
											echo _l('no_contract_renewals_found');
										}
										foreach($contract_renewal_history as $renewal){ ?>
											<div class="display-block">
												<div class="media-body">
													<div class="display-block">
														<b>
															<?php
															echo _l('contract_renewed_by',$renewal['renewed_by']);
															?>
														</b>
														<?php if($renewal['renewed_by_staff_id'] == get_staff_user_id() || is_admin()){ ?>
															<a href="<?php echo admin_url('service_management/delete_renewal/'.$renewal['id'] . '/'.$renewal['contractid']); ?>" class="pull-right _delete text-danger"><i class="fa fa-remove"></i></a>
															<br />
														<?php } ?>
														<small class="text-muted"><?php echo _dt($renewal['date_renewed']); ?></small>
														<hr class="hr-10" />
														<span class="text-success bold" data-toggle="tooltip" title="<?php echo _l('contract_renewal_old_start_date',_d($renewal['old_start_date'])); ?>">
															<?php echo _l('contract_renewal_new_start_date',_d($renewal['new_start_date'])); ?>
														</span>
														<br />
														<?php if(is_date($renewal['new_end_date'])){
															$tooltip = '';
															if(is_date($renewal['old_end_date'])){
																$tooltip = _l('contract_renewal_old_end_date',_d($renewal['old_end_date']));
															}
															?>
															<span class="text-success bold" data-toggle="tooltip" title="<?php echo new_html_entity_decode($tooltip); ?>">
																<?php echo _l('contract_renewal_new_end_date',_d($renewal['new_end_date'])); ?>
															</span>
															<br/>
														<?php } ?>
														<?php if($renewal['new_value'] > 0){
															$contract_renewal_value_tooltip = '';
															if($renewal['old_value'] > 0){
																$contract_renewal_value_tooltip = ' data-toggle="tooltip" data-title="'._l('contract_renewal_old_value', app_format_money($renewal['old_value'], $base_currency)).'"';
															} ?>
															<span class="text-success bold"<?php echo new_html_entity_decode($contract_renewal_value_tooltip); ?>>
																<?php echo _l('contract_renewal_new_value', app_format_money($renewal['new_value'], $base_currency)); ?>
															</span>
															<br />
														<?php } ?>
													</div>
												</div>
												<hr />
											</div>
										<?php } ?>
									</div>
									<div role="tabpanel" class="tab-pane" id="tab_emails_tracking">
										<?php
										$this->load->view('admin/includes/emails_tracking',array(
											'tracked_emails'=>
											get_tracked_emails($contract->id, 'contract'))
									);
									?>
								</div>
								<div role="tabpanel" class="tab-pane" id="tab_tasks">
									<?php init_relation_tasks_table(array('data-new-rel-id'=>$contract->id,'data-new-rel-type'=>'contract')); ?>
								</div>
								<div role="tabpanel" class="tab-pane" id="tab_templates">
									<div class="row contract-templates">
										<div class="col-md-12">
											<button type="button" class="btn btn-info" onclick="add_template('contracts', <?php echo new_html_entity_decode($contract->id) ?>);"><?php echo _l('add_template'); ?></button>
											<hr>
										</div>
										<div class="col-md-12">
											<div id="contract-templates" class="contract-templates-wrapper"></div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			<?php } ?>
		</div>
	</div>
</div>
<div id="modal-wrapper"></div>
<input type="hidden" name="contract_id" value="<?php echo new_html_entity_decode(isset($contract) ? $contract->id : '') ?>">

<?php init_tail(); ?>
<?php if(isset($contract)){ ?>
	<!-- init table tasks -->
	<script>
		var contract_id = '<?php echo new_html_entity_decode($contract->id); ?>';
	</script>
	<?php $this->load->view('service_management/contracts/send_to_client'); ?>
	<?php $this->load->view('service_management/contracts/renew_contract'); ?>
<?php } ?>
<?php $this->load->view('service_management/contracts/contract_type'); ?>

<?php require 'modules/service_management/assets/js/contracts/manage_contract_js.php';?>

</body>
</html>
