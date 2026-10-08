<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
	<div class="content">
		<div class="row">
			<div class="col-md-5 left-column">
				<div class="panel_s">
					<div class="panel-body">
						<?php echo form_open(admin_url('service_management/contract_addendum/'.$id), array('id'=>'contract-form')); ?>

						<div class="form-group">
							<div class="checkbox checkbox-primary no-mtop checkbox-inline">
								<input type="checkbox" id="trash" name="trash"<?php if(isset($contract_addendum)){if($contract_addendum->trash == 1){echo ' checked';}}; ?>>
								<label for="trash"><i class="fa fa-question-circle" data-toggle="tooltip" data-placement="left" title="<?php echo _l('contract_trash_tooltip'); ?>" ></i> <?php echo _l('contract_trash'); ?></label>
							</div>
							<div class="checkbox checkbox-primary checkbox-inline">
								<input type="checkbox" name="not_visible_to_client" id="not_visible_to_client" <?php if(isset($contract_addendum)){if($contract_addendum->not_visible_to_client == 1){echo 'checked';}}; ?>>
								<label for="not_visible_to_client"><?php echo _l('contract_not_visible_to_client'); ?></label>
							</div>
						</div>

						<?php $value = (isset($contract_addendum) ? $contract_addendum->subject : ''); ?>
						<i class="fa fa-question-circle pull-left" data-toggle="tooltip" title="<?php echo _l('contract_subject_tooltip'); ?>"></i>
						<?php echo render_input('subject','contract_subject',$value); ?>

						<?php 
						$contract_id = (isset($contract_addendum) ? $contract_addendum->contract_id : '');

						?>
						<?php echo render_select('contract_id',$contracts,array('id', 'subject'), 'sm_contract', $contract_id, [], [], '', '' , true); ?>  

						<div class="row">
							<div class="col-md-12">
								<?php $value = (isset($contract_addendum) ? _d($contract_addendum->datestart) : _d(date('Y-m-d'))); ?>
								<?php echo render_date_input('datestart','contract_start_date',$value); ?>
							</div>
						</div>
						<?php $value = (isset($contract_addendum) ? $contract_addendum->description : ''); ?>
						<?php echo render_textarea('description','contract_description',$value,array('rows'=>10)); ?>
						<div class="btn-bottom-toolbar text-right">
							<button type="submit" class="btn btn-info"><?php echo _l('submit'); ?></button>
						</div>
						<?php echo form_close(); ?>
					</div>
				</div>
			</div>
			<?php if(isset($contract_addendum)) { ?>
				<div class="col-md-7 right-column">
					<div class="panel_s">
						<div class="panel-body">
							<h4 class="no-margin"><?php echo new_html_entity_decode($contract_addendum->subject); ?></h4>
							<a href="<?php echo site_url('service_management/'.$contract_addendum->id.'/'.$contract_addendum->hash); ?>" class="hide" target="_blank">
								<?php echo _l('view_contract'); ?>
							</a>
							<hr class="hr-panel-heading" />
							<?php if($contract_addendum->trash > 0){
								echo '<div class="ribbon default"><span>'._l('contract_trash').'</span></div>';
							} ?>
							<div class="horizontal-scrollable-tabs preview-tabs-top">
								<div class="scroller arrow-left"><i class="fa fa-angle-left"></i></div>
								<div class="scroller arrow-right"><i class="fa fa-angle-right"></i></div>
								<div class="horizontal-tabs">
									<ul class="nav nav-tabs tabs-in-body-no-margin contract-tab nav-tabs-horizontal mbot15" role="tablist">
										<li role="presentation" class="<?php if(!$this->input->get('tab') || $this->input->get('tab') == 'tab_content'){echo 'active';} ?>">
											<a href="#tab_content" aria-controls="tab_content" role="tab" data-toggle="tab">
												<?php echo _l('sm_contract_addendum'); ?>
											</a>
										</li>
										<li role="presentation" class="<?php if($this->input->get('tab') == 'attachments'){echo 'active';} ?>">
											<a href="#attachments" aria-controls="attachments" role="tab" data-toggle="tab">
												<?php echo _l('contract_attachments'); ?>
												<?php if($totalAttachments = count($contract_addendum->attachments)) { ?>
													<span class="badge attachments-indicator"><?php echo new_html_entity_decode($totalAttachments); ?></span>
												<?php } ?>
											</a>
										</li>
										<li role="presentation" class="hide">
											<a href="#tab_comments" aria-controls="tab_comments" role="tab" data-toggle="tab" onclick="get_contract_comments(); return false;">
												<?php echo _l('contract_comments'); ?>
												<?php
												$totalComments = total_rows(db_prefix().'contract_comments','contract_id='.$contract_addendum->id)
												?>
												<span class="badge comments-indicator<?php echo new_html_entity_decode($totalComments == 0 ? ' hide' : ''); ?>"><?php echo new_html_entity_decode($totalComments); ?></span>
											</a>
										</li>
										
										<li role="presentation" class="tab-separator hide">
											<a href="#tab_tasks" aria-controls="tab_tasks" role="tab" data-toggle="tab" onclick="init_rel_tasks_table(<?php echo new_html_entity_decode($contract_addendum->id); ?>,'contract'); return false;">
												<?php echo _l('tasks'); ?>
											</a>
										</li>
										<li role="presentation" class="tab-separator hide">
											<a href="#tab_notes" onclick="get_sales_notes(<?php echo new_html_entity_decode($contract_addendum->id); ?>,'contracts'); return false" aria-controls="tab_notes" role="tab" data-toggle="tab">
												<?php echo _l('contract_notes'); ?>
												<span class="notes-total">
													<?php if($totalNotes > 0){ ?>
														<span class="badge"><?php echo new_html_entity_decode($totalNotes); ?></span>
													<?php } ?>
												</span>
											</a>
										</li>
										<li role="presentation" class="tab-separator hide">
											<a href="#tab_templates" onclick="get_templates('contracts', <?php echo new_html_entity_decode($contract_addendum->id) ?>); return false" aria-controls="tab_templates" role="tab" data-toggle="tab">
												<?php echo _l('templates'); ?>
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
											<?php if($contract_addendum->signed == 1){ ?>
												<div class="col-md-12">
													<div class="alert alert-success">
														<?php echo _l('document_signed_info',array(
															'<b>'.$contract_addendum->acceptance_firstname . ' ' . $contract_addendum->acceptance_lastname . '</b> (<a href="mailto:'.$contract_addendum->acceptance_email.'">'.$contract_addendum->acceptance_email.'</a>)',
															'<b>'. _dt($contract_addendum->acceptance_date).'</b>',
															'<b>'.$contract_addendum->acceptance_ip.'</b>')
														); ?>
													</div>
												</div>
											<?php } else if($contract_addendum->marked_as_signed == 1) { ?>
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
														<li class="hidden-xs"><a href="<?php echo admin_url('service_management/contract_addendum_pdf/'.$contract_addendum->id.'?output_type=I'); ?>"><?php echo _l('view_pdf'); ?></a></li>
														<li class="hidden-xs"><a href="<?php echo admin_url('service_management/contract_addendum_pdf/'.$contract_addendum->id.'?output_type=I'); ?>" target="_blank"><?php echo _l('view_pdf_in_new_window'); ?></a></li>
														<li><a href="<?php echo admin_url('service_management/contract_addendum_pdf/'.$contract_addendum->id); ?>"><?php echo _l('download'); ?></a></li>
														<li>
															<a href="<?php echo admin_url('service_management/contract_addendum_pdf/'.$contract_addendum->id.'?print=true'); ?>" target="_blank">
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
														<li class="hide">
															<a href="<?php echo site_url('service_management/'.$contract_addendum->id.'/'.$contract_addendum->hash); ?>" target="_blank">
																<?php echo _l('view_contract'); ?>
															</a>
														</li>
														<?php
														if($contract_addendum->signed == 0 && $contract_addendum->marked_as_signed == 0 && staff_can('edit', 'contracts')) { ?>
															<li class="hide">
																<a href="<?php echo admin_url('service_management/mark_as_signed/'.$contract_addendum->id); ?>">
																	<?php echo _l('mark_as_signed'); ?>
																</a>
															</li>
														<?php } else if($contract_addendum->signed == 0 && $contract_addendum->marked_as_signed == 1 && staff_can('edit', 'contracts')) { ?>
															<li class="hide">
																<a href="<?php echo admin_url('service_management/unmark_as_signed/'.$contract_addendum->id); ?>">
																	<?php echo _l('unmark_as_signed'); ?>
																</a>
															</li>
														<?php } ?>
														<?php if(has_permission('service_management','','create')){ ?>
															<li class="hide">
																<a href="<?php echo admin_url('service_management/copy/'.$contract_addendum->id); ?>">
																	<?php echo _l('contract_copy'); ?>
																</a>
															</li>
														<?php } ?>
														<?php if($contract_addendum->signed == 1 && has_permission('service_management','','delete')){ ?>
															<li class="hide">
																<a href="<?php echo admin_url('service_management/clear_signature/'.$contract_addendum->id); ?>" class="_delete">
																	<?php echo _l('clear_signature'); ?>
																</a>
															</li>
														<?php } ?>
														<?php if(has_permission('service_management','','delete')){ ?>
															<li>
																<a href="<?php echo admin_url('service_management/delete_contract_addendum/'.$contract_addendum->id); ?>" class="_delete">
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
											<div class=" sm-contract-boder tc-content<?php if(staff_can('edit','contracts')){echo ' editable';} ?>"
												>
												<?php
												if(empty($contract_addendum->content) && staff_can('edit','contracts')){
													echo hooks()->apply_filters('new_contract_default_content', '<span class="text-danger text-uppercase mtop15 editor-add-content-notice"> ' . _l('click_to_add_content') . '</span>');
												} else {
													echo new_html_entity_decode($contract_addendum->content);
												}
												?>
											</div>
											<?php if(!empty($contract_addendum->signature)) { ?>
												<div class="row mtop25">
													<div class="col-md-6 col-md-offset-6 text-right">
														<div class="bold">
															<p class="no-mbot"><?php echo _l('contract_signed_by') . ": {$contract_addendum->acceptance_firstname} {$contract_addendum->acceptance_lastname}"?></p>
															<p class="no-mbot"><?php echo _l('contract_signed_date') . ': ' . _dt($contract_addendum->acceptance_date) ?></p>
															<p class="no-mbot"><?php echo _l('contract_signed_ip') . ": {$contract_addendum->acceptance_ip}"?></p>
														</div>
														<p class="bold"><?php echo _l('document_customer_signature_text'); ?>
														<?php if($contract_addendum->signed == 1 && has_permission('service_management','','delete')){ ?>
															<a href="<?php echo admin_url('service_management/clear_signature/'.$contract_addendum->id); ?>" data-toggle="tooltip" title="<?php echo _l('clear_signature'); ?>" class="_delete text-danger">
																<i class="fa fa-remove"></i>
															</a>
														<?php } ?>
													</p>
													<div class="pull-right">
														<img src="<?php echo site_url('download/preview_image?path='.protected_file_url_by_path(get_upload_path_by_type('contract').$contract_addendum->id.'/'.$contract_addendum->signature)); ?>" class="img-responsive" alt="">
													</div>
												</div>
											</div>
										<?php } ?>
									</div>
									<div role="tabpanel" class="tab-pane" id="tab_notes">
										<?php echo form_open(admin_url('service_management/add_note/'.$contract_addendum->id),array('id'=>'sales-notes','class'=>'contract-notes-form')); ?>
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
										<?php echo form_open(admin_url('service_management/add_contract_addendum_attachment/'.$contract_addendum->id),array('id'=>'contract-attachments-form','class'=>'dropzone')); ?>
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
											foreach($contract_addendum->attachments as $attachment) {
												$href_url = admin_url('service_management/contract_file/contract_addendum/'.$attachment['attachment_key']);
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

									
								<div role="tabpanel" class="tab-pane" id="tab_tasks">
									<?php init_relation_tasks_table(array('data-new-rel-id'=>$contract_addendum->id,'data-new-rel-type'=>'contract')); ?>
								</div>
								<div role="tabpanel" class="tab-pane" id="tab_templates">
									<div class="row contract-templates">
										<div class="col-md-12">
											<button type="button" class="btn btn-info" onclick="add_template('contracts', <?php echo new_html_entity_decode($contract_addendum->id) ?>);"><?php echo _l('add_template'); ?></button>
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
<?php init_tail(); ?>

<?php require 'modules/service_management/assets/js/contract_addendums/add_edit_contract_addendum_js.php'; ?>

</body>
</html>
