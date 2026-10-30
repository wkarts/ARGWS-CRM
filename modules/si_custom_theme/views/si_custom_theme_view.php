<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$tags = si_custom_theme_get_styling_areas('tags');
?>
<link href="<?php echo module_dir_url('si_custom_theme','assets/css/si_custom_theme_settings_style.css'); ?>" rel="stylesheet" />
<div id="wrapper">
	<div class="content">
		<div class="row">
			<div class="col-md-12">
				<div class="panel_s">
					<div class="panel-body">
						<h4 class="text-center"><?php echo _l('si_custom_theme_title');?></h4>
						<div class=" btn-bottom-toolbar text-right">
							<a href="#" id="si_ct_save_theme" class="btn btn-info"><?php echo _l('save'); ?></a>
							<a href="<?php echo admin_url('si_custom_theme/reset_theme'); ?>" data-toggle="tooltip" data-title="<?php echo _l('si_ct_reset_info'); ?>" class="btn btn-default"><?php echo _l('reset'); ?></a>
						</div>
						<div class="clearfix"></div>
						<div class="horizontal-scrollable-tabs">
							<div class="scroller arrow-left"><i class="fa fa-angle-left"></i></div>
							<div class="scroller arrow-right"><i class="fa fa-angle-right"></i></div>
							<div class="horizontal-tabs">
								<ul class="nav nav-tabs no-margin nav-tabs-horizontal" id="si_ct_theme_tabs">
									<li class="active" role="presentation">
										<a href="#tab_select_theme" aria-controls="tab_select_theme" role="tab" data-toggle="tab">
											<span><?php echo _l('si_ct_select_theme')?></span>
										</a>
									</li>
									<li role="presentation">
										<a href="#tab_create_theme" aria-controls="tab_create_theme" role="tab" data-toggle="tab">
											<span><?php echo _l('si_ct_create_theme')?></span>
										</a>
									</li>
									<li role="presentation">
										<a href="#tab_custom_css" aria-controls="tab_custom_css" role="tab" data-toggle="tab">
											<span><?php echo _l('si_ct_custom_css')?></span>
										</a>
									</li>
									<li role="presentation">
										<a href="#tab_settings" aria-controls="tab_settings" role="tab" data-toggle="tab">
											<span><?php echo _l('si_ct_settings')?></span>
										</a>
									</li>
								</ul>
							</div>
						</div><!-- main horizontal-scrollable-tabs end-->
					</div>
				</div>
			</div>
			
			<div class="col-md-12">
				<div class="panel_s">
					<div class="panel-body pickers">
						<div class="tab-content">
							<!--tab select theme start-->
							<div role="tabpanel" class="tab-pane ptop10 active" id="tab_select_theme">
								<p class="text-muted"> <?php echo _l('si_ct_select_theme_tab_info');?></p>
								<div class="panel_s">
									<div class="panel-body">
										<?php foreach($theme_list as $theme){?>
										<div class="setting-color">
											<?php if($theme['theme_type']=='custom' && $selected_theme != $theme['id']){?>
											<div class="si_ct_delete pull-right">
											<a class="text-danger _delete" href="<?php echo admin_url('si_custom_theme/delete/' . $theme['id']) ?>"><i class="fa fa-close"></i></a>
											</div>
											<?php } ?>
											<label>
												<input type="radio" name="default_theme" value="<?php echo ($theme['id'])?>" <?php if($selected_theme == $theme['id']) echo 'checked';?>>
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
									</div>
								</div>
								<p class="text-muted"> <?php echo _l('si_ct_select_clients_theme_tab_info');?></p>
								<div class="panel_s">
									<div class="panel-body">
										<?php foreach($theme_list as $theme){?>
										<div class="setting-color">
											<label>
												<input type="radio" name="default_clients_theme" value="<?php echo ($theme['id'])?>" <?php if($selected_client_theme == $theme['id']) echo 'checked';?>>
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
									</div>
								</div>
							</div>
							<!--tab select theme end-->
							<!--tab create theme start-->
							<div role="tabpanel" class="tab-pane ptop10" id="tab_create_theme">
								<p class="text-muted"> <?php echo _l('si_ct_create_theme_tab_info');?></p>
								<div class="panel_s">
									<div class="panel-body">
										<div class="col-md-4">
											<?php 
											$copy_themes = $theme_list;
											unset($copy_themes[1]);
											echo render_select('copy_theme',$copy_themes,array('id','theme_name'),'si_ct_select_theme_to_copy');?>
										</div>
										<div class="col-md-4">
											<label><?php echo _l('actions');?></label><br/>
											<div class="radio radio-inline radio-primary">
												<input type="radio" id="new_theme_0" name="new_theme" value="" checked>
												<label for="new_theme_0"><?php echo _l('si_ct_edit_theme'); ?></label>
											</div>
											<div class="radio radio-inline radio-primary">
												<input type="radio" id="new_theme_1" name="new_theme" value="1">
												<label for="new_theme_1"><?php echo _l('si_ct_save_new_theme'); ?></label>
											</div>
										</div>
										<div class="col-md-4 hide" id="theme_name_wrapper">
											<?php echo render_input('theme_name','si_ct_new_theme_name','','text',array('required'=>true,'maxlength'=>200));?>
										</div>
									</div>
								</div>	
								<div class="panel_s">
									<div class="panel-body pickers">
										<div class="horizontal-scrollable-tabs">
											<div class="scroller arrow-left"><i class="fa fa-angle-left"></i></div>
											<div class="scroller arrow-right"><i class="fa fa-angle-right"></i></div>
											<div class="horizontal-tabs">
												<ul class="nav nav-tabs no-margin nav-tabs-horizontal">
													<li role="presentation" class="active">
														<a href="#tab_admin_menu" aria-controls="tab_admin_menu" role="tab" data-toggle="tab">
														<?php echo _l('si_ct_admin_menu'); ?>
														</a>
													</li>
													<li role="presentation">
														<a href="#tab_customers_styling" aria-controls="tab_customers_styling" role="tab" data-toggle="tab">
														<?php echo _l('si_ct_customers'); ?>
														</a>
													</li>
													<li role="presentation">
														<a href="#tab_buttons_styling" aria-controls="tab_buttons_styling" role="tab" data-toggle="tab">
														<?php echo _l('si_ct_buttons'); ?>
														</a>
													</li>
													<li role="presentation">
														<a href="#tab_tabs_styling" aria-controls="tab_tabs_styling" role="tab" data-toggle="tab">
														<?php echo _l('si_ct_tabs'); ?>
														</a>
													</li>
													<li role="presentation">
														<a href="#tab_modals_styling" aria-controls="tab_modals_styling" role="tab" data-toggle="tab">
														<?php echo _l('si_ct_modals'); ?>
														</a>
													</li>
													<li role="presentation">
														<a href="#tab_texts_styling" aria-controls="tab_texts_styling" role="tab" data-toggle="tab">
														<?php echo _l('si_ct_texts'); ?>
														</a>
													</li>
													<li role="presentation">
														<a href="#tab_general_styling" aria-controls="tab_general_styling" role="tab" data-toggle="tab">
														<?php echo _l('si_ct_general'); ?>
														</a>
													</li>
													<?php if(count($tags) > 0){ ?>
													<li role="presentation">
														<a href="#tab_styling_tags" aria-controls="tab_styling_tags" role="tab" data-toggle="tab">
														<?php echo _l('tags'); ?>
														</a>
													</li>
													<?php } ?>
												</ul>
											</div>
										</div><!--inner horizontal-scrollable-tabs end-->
										<div class="tab-content">
											<div role="tabpanel" class="tab-pane ptop10 active" id="tab_admin_menu">
												<div class="row">
													<?php
													foreach(si_custom_theme_get_styling_areas('admin') as $area){ ?>
													<div class="col-md-4">
														<label class="bold mbot10 inline-block"><?php echo ($area['name']); ?></label>
														<?php si_custom_theme_render_theme_styling_picker($area['id'],'',$area['target'],$area['css'],$area['additional_selectors']);
														?>
													</div>
													<?php  } ?>
												</div>
											</div>
											<div role="tabpanel" class="tab-pane ptop10" id="tab_customers_styling">
												<div class="row">
													<?php foreach(si_custom_theme_get_styling_areas('customers') as $area){ ?>
													<div class="col-md-4">
														<label class="bold mbot10 inline-block"><?php echo ($area['name']); ?></label>
														<?php si_custom_theme_render_theme_styling_picker($area['id'],'',$area['target'],$area['css'],$area['additional_selectors']);
														?>
													</div>
													<?php  } ?>
												</div>
											</div>
											<div role="tabpanel" class="tab-pane ptop10" id="tab_buttons_styling">
												<div class="row">
													<?php foreach(si_custom_theme_get_styling_areas('buttons') as $area){ ?>
													<div class="col-md-3">
														<label class="bold mbot10 inline-block"><?php echo ($area['name']); ?></label>
														<?php si_custom_theme_render_theme_styling_picker($area['id'],'',$area['target'],$area['css'],$area['additional_selectors']);
														?>
														<?php if(isset($area['example'])){echo ($area['example']);} ?>
														<div class="clearfix"></div>
													</div>
													<?php  } ?>
												</div>
											</div>
											<div role="tabpanel" class="tab-pane ptop10" id="tab_tabs_styling">
												<div class="row">
													<?php foreach(si_custom_theme_get_styling_areas('tabs') as $area){ ?>
													<div class="col-md-3">
														<label class="bold mbot10 inline-block"><?php echo ($area['name']); ?></label>
														<?php si_custom_theme_render_theme_styling_picker($area['id'],'',$area['target'],$area['css'],$area['additional_selectors']);
														?>
													</div>
													<?php  } ?>
													
												</div>
											</div>
											<div role="tabpanel" class="tab-pane ptop10" id="tab_modals_styling">
												<div class="row">
													<?php foreach(si_custom_theme_get_styling_areas('modals') as $area){ ?>
													<div class="col-md-3">
														<label class="bold mbot10 inline-block"><?php echo ($area['name']); ?></label>
														<?php si_custom_theme_render_theme_styling_picker($area['id'],'',$area['target'],$area['css'],$area['additional_selectors']);
														?>
													</div>
													<?php  } ?>
													<div class="col-md-12">
														<div class="modal-content theme_style_modal_example">
															<div class="modal">
																<div class="modal-header">
																	<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
																	<h4 class="modal-title"><?php echo _l('si_ct_example_modal_heading'); ?></h4>
																	<span class="color-white"><?php echo _l('si_ct_sample_text'); ?></span>
																</div>
																<div class="modal-body">
																	<?php echo _l('si_ct_modal_body'); ?>
																</div>
															</div>
														</div>
													</div>
												</div>
											</div>
											<div role="tabpanel" class="tab-pane ptop10" id="tab_texts_styling">
												<div class="row">
													<?php foreach(si_custom_theme_get_styling_areas('texts') as $area){ ?>
													<div class="col-md-3">
														<label class="bold mbot10 inline-block"><?php echo ($area['name']); ?></label>
														<?php si_custom_theme_render_theme_styling_picker($area['id'],
														'',
														$area['target'],$area['css'],
														$area['additional_selectors']);
														?>
														<?php if(isset($area['example'])){echo ($area['example']);} ?>
													</div>
													<?php  } ?>
												</div>
											</div>
											<div role="tabpanel" class="tab-pane ptop10" id="tab_general_styling">
												<div class="row">
													<?php foreach(si_custom_theme_get_styling_areas('general') as $area){ ?>
													<div class="col-md-<?php echo (isset($area['example'])?6:4);?>">
														<label class="bold mbot10 inline-block"><?php echo ($area['name']); ?></label>
														<?php si_custom_theme_render_theme_styling_picker($area['id'],
														'',
														$area['target'],$area['css'],
														$area['additional_selectors']);
														?>
														<?php if(isset($area['example'])){echo ($area['example']);} ?>
													</div>
													<?php  } ?>
												</div>
											</div>
											
											<?php if(count($tags) > 0){ ?>
											<div role="tabpanel" class="tab-pane ptop10" id="tab_styling_tags">
												<div class="row">
												<?php foreach($tags as $area){ ?>
													<div class="col-md-6">
														<label class="bold mbot10 inline-block">
														<strong><?php echo ($area['name']); ?></strong>
														</label>
														<?php si_custom_theme_render_theme_styling_picker($area['id'],
														'',
														$area['target'],$area['css'],
														$area['additional_selectors']
														);
														if(isset($area['example'])){
															echo ($area['example']);
														}
														?>
														<hr />
													</div>
												<?php  } ?>
												</div>
											</div>
											<?php  } ?>
										</div><!--inner tab-content end-->
									</div>
								</div>
								<?php echo _l('si_ct_note_for_version'); ?>
							</div>
							<!--tab create theme end-->
							<!--tab custom css start-->
							<div role="tabpanel" class="tab-pane ptop10" id="tab_custom_css">
								<div class="form-group">
									<label class="bold" for="si_ct_custom_clients_and_admin_area">
									<i class="fa fa-question-circle" data-toggle="tooltip" data-title="<?php echo _l('si_ct_ca_info'); ?>"></i>
									<?php echo _l('si_ct_customers_and_admin'); ?>
									</label>
									<textarea name="si_ct_custom_clients_and_admin_area"
									id="si_ct_custom_clients_and_admin_area"
									rows="15"
									class="form-control"><?php echo clear_textarea_breaks(get_option(SI_CUSTOM_THEME_MODULE_NAME.'_custom_clients_and_admin_area')); ?></textarea>
								</div>
								<div class="form-group">
									<label class="bold" for="si_ct_custom_admin_area">
										<?php echo _l('si_ct_admin'); ?>
									</label>
									<textarea name="si_ct_custom_admin_area"
									id="si_ct_custom_admin_area"
									rows="15"
									class="form-control"><?php echo clear_textarea_breaks(get_option(SI_CUSTOM_THEME_MODULE_NAME.'_custom_admin_area')); ?></textarea>
								</div>
								<div class="form-group">
									<label class="bold" for="si_ct_custom_clients_area">
										<?php echo _l('si_ct_customers'); ?>
									</label>
									<textarea name="si_ct_custom_clients_area"
									id="si_ct_custom_clients_area"
									rows="15"
									class="form-control"><?php echo clear_textarea_breaks(get_option(SI_CUSTOM_THEME_MODULE_NAME.'_custom_clients_area')); ?></textarea>
								</div>
							</div>
							<!--tab custom css end-->
							<!--tab settings start-->
							<div role="tabpanel" class="tab-pane ptop10" id="tab_settings">
								<?php $this->load->view('includes/bg_image_upload');?>
							</div>
							<!--tab settings end-->
						</div><!-- main tab-content end-->
					</div>
				</div>
			</div><!--end col-md-12-->
		</div>
	</div>
</div>
<?php init_tail(); ?>
<script>
var si_pickers = $('.si-ct-colorpickers');
</script>
<script src="<?php echo module_dir_url('si_custom_theme','assets/js/si_custom_theme_view_settings.js'); ?>"></script>
</body>
</html>