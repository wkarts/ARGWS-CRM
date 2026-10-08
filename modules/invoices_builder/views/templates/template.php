<?php defined('BASEPATH') or exit('No direct script access allowed');?>
<?php init_head();
$font_styles = [
	0 => ['id' => 'inherit', 'name' => _l('ib_inherit')],
	1 => ['id' => 'initial', 'name' => _l('ib_initial')],
	2 => ['id' => 'italic', 'name' => _l('ib_italic')],
	3 => ['id' => 'normal', 'name' => _l('ib_normal')],
	4 => ['id' => 'oblique', 'name' => _l('ib_oblique')],
	5 => ['id' => 'revert', 'name' => _l('ib_revert')],
	5 => ['id' => 'unset', 'name' => _l('ib_unset')],
];
$text_align = [
	0 => ['id' => 'left', 'name' => _l('ib_left')],
	1 => ['id' => 'right', 'name' => _l('ib_right')],
	2 => ['id' => 'center', 'name' => _l('ib_center')],
];
$default_font_size = '13';
$default_font_style = 'normal';
$default_text_color = '#000';
$default_text_alignment = 'left';
$default_background_color = '#fff';
$order_column_attr = ['min' => 1, 'max' => 4];
?>
<div id="wrapper">
	<div class="content template_builder_content">
		<div class="row">
			<?php echo form_open($this->uri->uri_string(),array('id'=>'form_add_edit_template')); ?>	            
			<input type="hidden" name="id" value="<?php echo ib_html_entity_decode($id); ?>">
			<div id="design_area" class="col-md-6 pleft-0 build-section">
				<div class="panel_s">
					<div class="panel-body">
						<div class="row">
							<div class="col-md-12">
								<textarea name="capture_image" class="d-none" id="" cols="30" rows="10"></textarea>
								<textarea name="capture_html" class="d-none" id="" cols="30" rows="10"></textarea>
								<h4><?php echo ib_html_entity_decode($title); ?></h4>
								<hr class="hr-panel-heading-dashboard">

								<div class="panel panel-info general_setting_panel" data-panel="general_setting_panel">
									<div class="panel-heading"><?php echo _l('ib_general_setting'); ?></div>
									<div class="panel-body">
										<div class="row">
											<div class="col-md-12">
												<?php echo render_input('name', 'name', (isset($template->name) ? $template->name : ''), 'text', ['required' => true]) ?>
											</div>
											<div class="col-md-12">
												<?php echo render_color_picker('background_color', _l('ib_background_color'), (isset($template->name) ? $template->background_color : $default_background_color), ['required' => true]) ; ?>
											</div>
											<div class="col-md-12">
												<?php $rotation = [
													0 => ['id' => 'portrait', 'name' => _l('ib_portrait')],
													1 => ['id' => 'landcape', 'name' => _l('ib_landcape')]
												];
												echo render_select('page_rotation',$rotation,array('id','name'),'ib_page_rotation',(isset($template->name) ? $template->page_rotation : 'portrait'), ['required' => true]); ?>
											</div>

										</div>
									</div>
								</div>

								<div class="panel panel-info header_panel" data-panel="header_panel">
									<div class="panel-heading"><?php echo _l('ib_header_section'); ?></div>
									<div class="panel-body">
										<div class="row">
											<div class="list_header_row">

												<div class="row_item">
													<div class="col-md-12">
														<p class="bold row-infor-color"><?php echo _l('ib_row_setting'); ?></p>
														<hr class="row-infor-hr">
													</div>
													<div class="col-md-6">
														<?php echo render_color_picker('header[row_color]', _l('ib_row_color'),(isset($template->header->row_color) ? $template->header->row_color : $default_background_color), ['required' => true]) ; ?>
													</div>
													<div class="col-md-6">
														<?php echo render_input('header[col_number]', 'ib_column_number_of_row', (isset($template->header->col_number) ? $template->header->col_number : ''), 'number', ['required' => true, 'max' => 4, 'min' => 1]) ?>
													</div>

													<div class="col-md-12">
														<p class="bold row-infor-color"><?php echo _l('ib_information_displayed'); ?></p>
														<hr class="row-infor-hr">
													</div>
													<div class="col-md-12">
														<div class="checkbox_row">
															<div class="checkbox checkbox-primary">
																<input type="checkbox" id="header_logo" name="header[logo][active]" value="1" <?php echo (isset($template->header->logo->active) ? 'checked' : '') ?> onclick="header_logo_setting(this);"><label for="header_logo"><?php echo _l('ib_logo'); ?></label>
															</div>
															<div id="logo_setting" class="<?php echo (isset($template->header->logo->active) ? '' : 'hide') ?>">
																<div class="col-md-4">
																	<?php
																	$attr = [];
																	$attr['max'] = 4;
																	$attr['min'] = 1;
																	if(isset($template->header->logo->active)){
																		$attr['required'] = true;
																	}
																	echo render_input('header[logo][show_on_column]', 'ib_show_on_column', (isset($template->header->logo->show_on_column) ? $template->header->logo->show_on_column : ''), 'number', $attr, [], '', 'order_column') ?>
																</div>
																<div class="col-md-4">
																	<?php echo render_select('header[logo][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->header->logo->text_align) ? $template->header->logo->text_align : $default_text_alignment)); ?>
																</div>
																<div class="col-md-4">
																	<?php
																	$attr['max'] = 500;
																	echo render_input('header[logo][width]', 'ib_logo_width', (isset($template->header->logo->width) ? $template->header->logo->width : ''), 'number', $attr) ?>
																</div>
																<div class="col-md-6 d-none">
																	<?php echo render_input('header[logo][height]', 'ib_logo_height', (isset($template->header->logo->height) ? $template->header->logo->height : ''), 'number', $attr) ?>
																</div>
															</div>
														</div>

														<div class="checkbox_row">
															<div class="checkbox checkbox-primary">
																<input type="checkbox" id="header_qrcode" name="header[qrcode][active]" value="1" <?php echo (isset($template->header->qrcode->active) ? 'checked' : '') ?> onclick="header_qr_setting(this);"><label for="header_qrcode"><?php echo _l('ib_qr_code'); ?></label>
															</div>
															<div id="qrcode_setting" class="<?php echo (isset($template->header->qrcode->active) ? '' : 'hide') ?>">
																<div class="col-md-6">
																	<?php
																	$attr = [];
																	if(isset($template->header->qrcode->active)){
																		$attr['required'] = true;
																	}
																	$attr['max'] = 4;
																	$attr['min'] = 1;
																	echo render_input('header[qrcode][show_on_column]', 'ib_show_on_column', (isset($template->header->qrcode->show_on_column) ? $template->header->qrcode->show_on_column : ''), 'number', $attr, [], '', 'order_column') ?>
																</div>
																<div class="col-md-6">
																	<?php echo render_select('header[qrcode][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->header->qrcode->text_align) ? $template->header->qrcode->text_align : $default_text_alignment)); ?>
																</div>
																<div class="col-md-6">
																	<?php
																	$attr['max'] = 500;
																	echo render_input('header[qrcode][width]', 'ib_logo_width', (isset($template->header->qrcode->width) ? $template->header->qrcode->width : ''), 'number', $attr) ?>
																</div>
																<div class="col-md-6">
																	<?php echo render_input('header[qrcode][height]', 'ib_logo_height', (isset($template->header->qrcode->height) ? $template->header->qrcode->height : ''), 'number', $attr) ?>
																</div>
															</div>
														</div>

														<div class="checkbox_row">
															<div class="checkbox checkbox-primary">
																<input type="checkbox" id="header_title" name="header[title][active]" value="1" <?php echo (isset($template->header->title->active) ? 'checked' : '') ?> onclick="header_title_setting(this);"><label for="header_title"><?php echo _l('ib_title'); ?></label>
															</div>
															<div id="title_setting" class="<?php echo (isset($template->header->title->active) ? '' : 'hide') ?>">
																<div class="col-md-4">
																	<?php
																	$attr = [];
																	if(isset($template->header->title->active)){
																		$attr['required'] = true;
																	}
																	$attr['max'] = 4;
																	$attr['min'] = 1;
																	echo render_input('header[title][show_on_column]', 'ib_show_on_column', (isset($template->header->title->show_on_column) ? $template->header->title->show_on_column : ''), 'number', $attr, [], '', 'order_column') ?>
																</div>
																<div class="col-md-4">
																	<?php
																	unset($attr['max']);
																	unset($attr['min']);
																	echo render_color_picker('header[title][text_color]', _l('ib_text_color'), (isset($template->header->title->text_color) ? $template->header->title->text_color : $default_text_color), $attr) ; ?>
																</div>
																<div class="col-md-4">
																	<?php echo render_input('header[title][font_size]', 'ib_font_size', (isset($template->header->title->font_size) ? $template->header->title->font_size : $default_font_size), 'number', $attr) ?>
																</div>
																<div class="col-md-6">
																	<?php 
																	echo render_select('header[title][font_style]',$font_styles,array('id','name'),'ib_font_style',(isset($template->header->title->font_style) ? $template->header->title->font_style : $default_font_style), $attr); ?>
																</div>

																<div class="col-md-6">
																	<?php 
																	echo render_select('header[title][text_align]',$text_align,array('id','name'),'ib_text_align', (isset($template->header->title->text_align) ? $template->header->title->text_align : $default_text_alignment), $attr); ?>
																</div>
															</div>
														</div>
														<div class="checkbox_row">
															<div class="checkbox checkbox-primary">
																<input type="checkbox" id="header_invoice_infor" name="header[invoice_infor][active]" value="1" <?php echo (isset($template->header->invoice_infor->active) ? 'checked' : '') ?> onclick="header_invoice_infor_setting(this);"><label for="header_invoice_infor"><?php echo _l('ib_invoice_information'); ?></label>
															</div>
															<div id="invoice_infor_setting" class="<?php echo (isset($template->header->invoice_infor->active) ? '' : 'hide') ?>">
																<div class="col-md-12">
																	<div class="row" id="header_invoice_setting">
																		<div class="col-md-4">
																			<div class="checkbox checkbox-primary">
																				<input type="checkbox" id="header_invoice_number" name="header[invoice_infor][invoice_number]" <?php echo (isset($template->header->invoice_infor->invoice_number) ? 'checked' : '') ?> value="1"><label for="header_invoice_number"><?php echo _l('ib_invoice_number'); ?></label>
																			</div>
																		</div>
																		<div class="col-md-4">
																			<div class="checkbox checkbox-primary">
																				<input type="checkbox" id="header_invoice_date" name="header[invoice_infor][invoice_date]" <?php echo (isset($template->header->invoice_infor->invoice_date) ? 'checked' : '') ?> value="1"><label for="header_invoice_date"><?php echo _l('ib_invoice_date'); ?></label>
																			</div>
																		</div>
																		<div class="col-md-4">
																			<div class="checkbox checkbox-primary">
																				<input type="checkbox" id="header_invoice_due_date" name="header[invoice_infor][invoice_due_date]" <?php echo (isset($template->header->invoice_infor->invoice_due_date) ? 'checked' : '') ?> value="1"><label for="header_invoice_due_date"><?php echo _l('ib_invoice_due_date'); ?></label>
																			</div>
																		</div>
																		<div class="col-md-4">
																			<div class="checkbox checkbox-primary">
																				<input type="checkbox" id="header_invoice_amount" name="header[invoice_infor][invoice_amount]" <?php echo (isset($template->header->invoice_infor->invoice_amount) ? 'checked' : '') ?> value="1"><label for="header_invoice_amount"><?php echo _l('header_invoice_amount'); ?></label>
																			</div>
																		</div>
																		<div class="col-md-4">
																			<div class="checkbox checkbox-primary">
																				<input type="checkbox" id="header_invoice_status" name="header[invoice_infor][invoice_status]" <?php echo (isset($template->header->invoice_infor->invoice_status) ? 'checked' : '') ?> value="1"><label for="header_invoice_status"><?php echo _l('ib_header_invoice_status'); ?></label>
																			</div>
																		</div>
																		<?php 
																		if(isset($template->header->invoice_infor->custom_field) && is_object($template->header->invoice_infor->custom_field) && $field_list = (array)$template->header->invoice_infor->custom_field){
																			foreach ($field_list as $id => $field) { 
																				$field_data = $this->invoices_builder_model->get_custom_field($id);
																				if($field_data){
																					$field->type = 'header[invoice_infor]';
																					$field->id = $id;
																					$field->field_label = $field_data->label;
																					if(!isset($field->active)){
																						$field->active = 0;
																					}											
																					?>
																					<div class="col-md-4 custom_field_item">
																						<?php $this->load->view('templates/includes/custom_field_item', (array)$field);	?>
																					</div>
																					<?php 
																				}
																			}
																		} 
																		?>

																	</div>
																</div>
																<div class="col-md-4">
																	<?php echo render_input('header[invoice_infor][show_on_column]', 'ib_show_on_column', (isset($template->header->invoice_infor->show_on_column) ? $template->header->invoice_infor->show_on_column : ''), 'number', ['max' => 4, 'min' => 1], [], '', 'order_column') ?>
																</div>
																<div class="col-md-4">
																	<?php echo render_color_picker('header[invoice_infor][text_color]', _l('ib_text_color'), (isset($template->header->invoice_infor->text_color) ? $template->header->invoice_infor->text_color : $default_text_color)) ?>
																</div>
																<div class="col-md-4">
																	<?php echo render_input('header[invoice_infor][font_size]', 'ib_font_size', (isset($template->header->invoice_infor->font_size) ? $template->header->invoice_infor->font_size : $default_font_size), 'number', []) ?>
																</div>
																<div class="col-md-6">
																	<?php 
																	echo render_select('header[invoice_infor][font_style]',$font_styles,array('id','name'),'ib_font_style',(isset($template->header->invoice_infor->font_style) ? $template->header->invoice_infor->font_style : $default_font_style)); ?>
																</div>
																<div class="col-md-6">
																	<?php 
																	echo render_select('header[invoice_infor][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->header->invoice_infor->text_align) ? $template->header->invoice_infor->text_align : $default_text_alignment)); ?>
																</div>


															</div>


														</div>
													</div>
													<div class="col-md-12">
														<div class="col-md-12" id="header_invoice_info_setting">
															<div class="row">
																<div class="col-md-8">
																	<div class="dropup">

																		<button type="button" id="header_invoice_info_btn" class="btn btn-icon btn-success mtop10" onclick="header_invoice_info_add_field(); return false;"><i class="fa fa-plus"></i><?php echo ' '._l('add_field'); ?></button>

																		<ul class="setting_popup hide" id="header_invoice_add_field_setting">
																			<button type="button" class="close mright10" data-dismiss="header_invoice_add_field_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																			<li role="presentation">
																				<div class="col-md-12">
																					<div class="checkbox checkbox-primary">
																						<input type="checkbox" id="select_an_available_field2" name="select_an_available_field" value="1" ><label for="select_an_available_field2"><?php echo _l('ib_select_an_available_field'); ?></label>
																					</div>
																				</div>
																				<div class="col-md-12 fr2 hide">
																					<?php
																					$list_fields = $this->invoices_builder_model->get_custom_field('','type = "header[invoice_infor]"', 'id, label', false, 'label');
																					echo render_select('cf_header_invoice_field_available', $list_fields, array('id', 'label'), 'select_field', '', []) 
																					?>
																				</div>
																				<div class="col-md-6 fr1">
																					<?php echo render_input('cf_header_invoice_field_name', 'ib_field_name', '', 'text', []) ?>
																				</div>
																				<div class="col-md-6 fr1">
																					<?php echo render_input('cf_header_invoice_field_label', 'ib_field_label', '', 'text', []) ?>
																				</div>
																				<div class="col-md-12">
																					<span class="text-danger error_add_customfield hide" id="cf_header_invoice_error_formula"></span>
																				</div>
																				<button type="button" onclick="add_header_invoice_field(this); return false;" class="btn btn-sm btn-success pull-right mright10 mbot5"><?php echo _l('ib_add'); ?></button>
																			</li>
																		</ul>
																	</div>
																</div>
															</div>
														</div>
													</div>
												</div>
											</div>
										</div>
									</div>
								</div>

								<div class="panel panel-info sender_receiver_panel" data-panel="sender_receiver_panel">
									<div class="panel-heading"><?php echo _l('ib_sender_recriver_information_section'); ?></div>
									<div class="panel-body">
										<div class="row">
											<div class="list_sender_recreiver_row">
												<div class="row_item">

													<div class="col-md-12">
														<p class="bold row-infor-color"><?php echo _l('ib_row_setting'); ?></p>
														<hr class="row-infor-hr">
													</div>
													<div class="col-md-6">
														<?php echo render_color_picker('sender_receiver[row_color]', _l('ib_row_color'), (isset($template->sender_receiver->row_color) ? $template->sender_receiver->row_color : $default_background_color), ['required' => true]) ; ?>
													</div>
													<div class="col-md-6">
														<?php echo render_input('sender_receiver[col_number]', 'ib_column_number_of_row', (isset($template->sender_receiver->col_number) ? $template->sender_receiver->col_number : ''), 'number', ['required' => true, 'max' => 4, 'min' => 1]) ?>
													</div>

													<div class="col-md-12">
														<p class="bold row-infor-color"><?php echo _l('ib_information_displayed'); ?></p>
														<hr class="row-infor-hr">
													</div>
													<div class="col-md-12 sender_receiver_row_item">
														<div class="checkbox checkbox-primary">
															<input type="checkbox" id="sender_infor" name="sender_receiver[sender_infor][active]" value="1" <?php echo (isset($template->sender_receiver->sender_infor->active) ? 'checked' : '') ?> onclick="sender_infor_setting(this);"><label for="sender_infor"><?php echo _l('ib_sender_information'); ?></label>
														</div>

														<div id="sender_infor_setting" class="<?php echo (isset($template->sender_receiver->sender_infor->active) ? '' : 'hide') ?>">
															<div class="col-md-6">
																<?php echo render_input('sender_receiver[sender_infor][show_on_column]', 'ib_show_on_column', (isset($template->sender_receiver->sender_infor->show_on_column) ? $template->sender_receiver->sender_infor->show_on_column : ''), 'number', ['max' => 4, 'min' => 1], [], '', 'order_column') ?>
															</div>
															<div class="col-md-6">
																<?php echo render_color_picker('sender_receiver[sender_infor][column_color]', _l('ib_column_color'), (isset($template->sender_receiver->sender_infor->column_color) ? $template->sender_receiver->sender_infor->column_color : $default_text_color)) ?>
															</div>

															<div class="col-md-4">
																<div class="dropup">
																	<div class="checkbox_row">
																		<div class="checkbox checkbox-primary">
																			<input type="checkbox" id="sender_infor_name" name="sender_receiver[sender_infor][name][active]" <?php echo (isset($template->sender_receiver->sender_infor->name->active) ? 'checked' : '') ?> value="1" onclick="column_setting(this);" data-btn-name="sender_infor_name_setting_btn" data-div-name="sender_name_setting" ><label for="sender_infor_name"><?php echo _l('ib_name'); ?></label>

																			<button id="sender_infor_name_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->sender_receiver->sender_infor->name->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="sender_name_setting"><i class="fa fa-cog"></i></button>
																		</div>

																		<ul class="setting_popup hide" id="sender_name_setting">
																			<button type="button" class="close mright10" data-dismiss="sender_name_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																			<li role="presentation">
																				<div class="col-md-12">
																					<?php echo render_color_picker('sender_receiver[sender_infor][name][text_color]', _l('ib_text_color'), (isset($template->sender_receiver->sender_infor->name->text_color) ? $template->sender_receiver->sender_infor->name->text_color : $default_text_color)) ; ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_input('sender_receiver[sender_infor][name][font_size]', 'ib_font_size', (isset($template->sender_receiver->sender_infor->name->font_size) ? $template->sender_receiver->sender_infor->name->font_size : $default_font_size), 'number', []) ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[sender_infor][name][font_style]',$font_styles,array('id','name'),'ib_font_style',(isset($template->sender_receiver->sender_infor->name->font_style) ? $template->sender_receiver->sender_infor->name->font_style : $default_font_style)); ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[sender_infor][name][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->sender_receiver->sender_infor->name->text_align) ? $template->sender_receiver->sender_infor->name->text_align : $default_text_alignment)); ?>
																				</div>
																			</li>
																		</ul>
																	</div>
																</div>
															</div>

															<div class="col-md-4">
																<div class="dropup">
																	<div class="checkbox_row">

																		<div class="checkbox checkbox-primary">
																			<input type="checkbox" id="sender_infor_phone" name="sender_receiver[sender_infor][phone][active]" <?php echo (isset($template->sender_receiver->sender_infor->phone->active) ? 'checked' : '') ?> value="1" onclick="column_setting(this);" data-btn-name="sender_infor_phone_setting_btn" data-div-name="sender_phone_setting" ><label for="sender_infor_phone"><?php echo _l('ib_phone'); ?></label>

																			<button id="sender_infor_phone_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->sender_receiver->sender_infor->phone->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="sender_phone_setting"><i class="fa fa-cog"></i></button>
																		</div>
																		<ul class="setting_popup hide" id="sender_phone_setting">
																			<button type="button" class="close mright10" data-dismiss="sender_phone_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																			<li role="presentation">
																				<div class="col-md-12">
																					<?php echo render_color_picker('sender_receiver[sender_infor][phone][text_color]', _l('ib_text_color'),(isset($template->sender_receiver->sender_infor->phone->text_color) ? $template->sender_receiver->sender_infor->phone->text_color : $default_text_color)) ; ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_input('sender_receiver[sender_infor][phone][font_size]', 'ib_font_size', (isset($template->sender_receiver->sender_infor->phone->font_size) ? $template->sender_receiver->sender_infor->phone->font_size : $default_font_size), 'number', []) ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[sender_infor][phone][font_style]',$font_styles,array('id','name'),'ib_font_style',(isset($template->sender_receiver->sender_infor->phone->font_style) ? $template->sender_receiver->sender_infor->phone->font_style : $default_font_style)); ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[sender_infor][phone][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->sender_receiver->sender_infor->phone->text_align) ? $template->sender_receiver->sender_infor->phone->text_align : $default_text_alignment)); ?>
																				</div>
																			</li>
																		</ul>
																	</div>
																</div>
															</div>

															<div class="col-md-4">
																<div class="dropup">
																	<div class="checkbox_row">

																		<div class="checkbox checkbox-primary">
																			<input type="checkbox" id="sender_infor_email" name="sender_receiver[sender_infor][email][active]" <?php echo (isset($template->sender_receiver->sender_infor->email->active) ? 'checked' : '') ?> value="1" onclick="column_setting(this);" data-btn-name="sender_infor_email_setting_btn" data-div-name="sender_email_setting" ><label for="sender_infor_email"><?php echo _l('ib_email'); ?></label>

																			<button id="sender_infor_email_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->sender_receiver->sender_infor->email->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="sender_email_setting"><i class="fa fa-cog"></i></button>
																		</div>
																		<ul class="setting_popup hide" id="sender_email_setting">
																			<button type="button" class="close mright10" data-dismiss="sender_email_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																			<li role="presentation">
																				<div class="col-md-12">
																					<?php echo render_color_picker('sender_receiver[sender_infor][email][text_color]', _l('ib_text_color'),(isset($template->sender_receiver->sender_infor->email->text_color) ? $template->sender_receiver->sender_infor->email->text_color : $default_text_color)) ; ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_input('sender_receiver[sender_infor][email][font_size]', 'ib_font_size', (isset($template->sender_receiver->sender_infor->email->font_size) ? $template->sender_receiver->sender_infor->email->font_size : $default_font_size), 'number', []) ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[sender_infor][email][font_style]',$font_styles,array('id','name'),'ib_font_style',(isset($template->sender_receiver->sender_infor->email->font_style) ? $template->sender_receiver->sender_infor->email->font_style : $default_font_style)); ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[sender_infor][email][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->sender_receiver->sender_infor->email->text_align) ? $template->sender_receiver->sender_infor->email->text_align : $default_text_alignment)); ?>
																				</div>
																			</li>
																		</ul>
																	</div>
																</div>
															</div>

															<div class="col-md-4">
																<div class="dropup">
																	<div class="checkbox_row">

																		<div class="checkbox checkbox-primary">
																			<input type="checkbox" id="sender_infor_website" name="sender_receiver[sender_infor][website][active]" <?php echo (isset($template->sender_receiver->sender_infor->website->active) ? 'checked' : '') ?> value="1" onclick="column_setting(this);" data-btn-name="sender_infor_website_setting_btn" data-div-name="sender_website_setting" ><label for="sender_infor_website"><?php echo _l('ib_website'); ?></label>

																			<button id="sender_infor_website_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->sender_receiver->sender_infor->website->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="sender_website_setting"><i class="fa fa-cog"></i></button>
																		</div>
																		<ul class="setting_popup hide" id="sender_website_setting">
																			<button type="button" class="close mright10" data-dismiss="sender_website_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																			<li role="presentation">
																				<div class="col-md-12">
																					<?php echo render_color_picker('sender_receiver[sender_infor][website][text_color]', _l('ib_text_color'),(isset($template->sender_receiver->sender_infor->website->text_color) ? $template->sender_receiver->sender_infor->website->text_color : $default_text_color)) ; ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_input('sender_receiver[sender_infor][website][font_size]', 'ib_font_size', (isset($template->sender_receiver->sender_infor->website->font_size) ? $template->sender_receiver->sender_infor->website->font_size : $default_font_size), 'number', []) ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[sender_infor][website][font_style]',$font_styles,array('id','name'),'ib_font_style',(isset($template->sender_receiver->sender_infor->website->font_style) ? $template->sender_receiver->sender_infor->website->font_style : $default_font_style)); ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[sender_infor][website][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->sender_receiver->sender_infor->website->text_align) ? $template->sender_receiver->sender_infor->website->text_align : $default_text_alignment)); ?>
																				</div>
																			</li>
																		</ul>
																	</div>
																</div>
															</div>

															<div class="col-md-4">
																<div class="dropup">
																	<div class="checkbox_row">

																		<div class="checkbox checkbox-primary">
																			<input type="checkbox" id="sender_infor_street" name="sender_receiver[sender_infor][street][active]" <?php echo (isset($template->sender_receiver->sender_infor->street->active) ? 'checked' : '') ?> value="1" onclick="column_setting(this);" data-btn-name="sender_infor_street_setting_btn" data-div-name="sender_street_setting" ><label for="sender_infor_street"><?php echo _l('ib_street_name'); ?></label>

																			<button id="sender_infor_street_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->sender_receiver->sender_infor->street->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="sender_street_setting"><i class="fa fa-cog"></i></button>
																		</div>
																		<ul class="setting_popup hide" id="sender_street_setting">
																			<button type="button" class="close mright10" data-dismiss="sender_street_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																			<li role="presentation">
																				<div class="col-md-12">
																					<?php echo render_color_picker('sender_receiver[sender_infor][street][text_color]', _l('ib_text_color'),(isset($template->sender_receiver->sender_infor->street->text_color) ? $template->sender_receiver->sender_infor->street->text_color : $default_text_color)) ; ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_input('sender_receiver[sender_infor][street][font_size]', 'ib_font_size', (isset($template->sender_receiver->sender_infor->street->font_size) ? $template->sender_receiver->sender_infor->street->font_size : $default_font_size), 'number', []) ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[sender_infor][street][font_style]',$font_styles,array('id','name'),'ib_font_style',(isset($template->sender_receiver->sender_infor->street->font_style) ? $template->sender_receiver->sender_infor->street->font_style : $default_font_style)); ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[sender_infor][street][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->sender_receiver->sender_infor->street->text_align) ? $template->sender_receiver->sender_infor->street->text_align : $default_text_alignment)); ?>
																				</div>
																			</li>
																		</ul>
																	</div>
																</div>
															</div>

															<div class="col-md-4">
																<div class="dropup">
																	<div class="checkbox_row">

																		<div class="checkbox checkbox-primary">
																			<input type="checkbox" id="sender_infor_city" name="sender_receiver[sender_infor][city][active]" <?php echo (isset($template->sender_receiver->sender_infor->city->active) ? 'checked' : '') ?> value="1" onclick="column_setting(this);" data-btn-name="sender_infor_city_setting_btn" data-div-name="sender_city_setting" ><label for="sender_infor_city"><?php echo _l('ib_city'); ?></label>

																			<button id="sender_infor_city_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->sender_receiver->sender_infor->city->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="sender_city_setting"><i class="fa fa-cog"></i></button>
																		</div>
																		<ul class="setting_popup hide" id="sender_city_setting">
																			<button type="button" class="close mright10" data-dismiss="sender_city_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																			<li role="presentation">
																				<div class="col-md-12">
																					<?php echo render_color_picker('sender_receiver[sender_infor][city][text_color]', _l('ib_text_color'),(isset($template->sender_receiver->sender_infor->city->text_color) ? $template->sender_receiver->sender_infor->city->text_color : $default_text_color)) ; ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_input('sender_receiver[sender_infor][city][font_size]', 'ib_font_size', (isset($template->sender_receiver->sender_infor->city->font_size) ? $template->sender_receiver->sender_infor->city->font_size : $default_font_size), 'number', []) ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[sender_infor][city][font_style]',$font_styles,array('id','name'),'ib_font_style',(isset($template->sender_receiver->sender_infor->city->font_style) ? $template->sender_receiver->sender_infor->city->font_style : $default_font_style)); ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[sender_infor][city][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->sender_receiver->sender_infor->city->text_align) ? $template->sender_receiver->sender_infor->city->text_align : $default_text_alignment)); ?>
																				</div>
																			</li>
																		</ul>
																	</div>
																</div>
															</div>

															<div class="col-md-4">
																<div class="dropup">
																	<div class="checkbox_row">

																		<div class="checkbox checkbox-primary">
																			<input type="checkbox" id="sender_infor_state" name="sender_receiver[sender_infor][state][active]" <?php echo (isset($template->sender_receiver->sender_infor->state->active) ? 'checked' : '') ?> value="1" onclick="column_setting(this);" data-btn-name="sender_infor_state_setting_btn" data-div-name="sender_state_setting" ><label for="sender_infor_state"><?php echo _l('ib_state'); ?></label>

																			<button id="sender_infor_state_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->sender_receiver->sender_infor->state->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="sender_state_setting"><i class="fa fa-cog"></i></button>
																		</div>
																		<ul class="setting_popup hide" id="sender_state_setting">
																			<button type="button" class="close mright10" data-dismiss="sender_state_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																			<li role="presentation">
																				<div class="col-md-12">
																					<?php echo render_color_picker('sender_receiver[sender_infor][state][text_color]', _l('ib_text_color'),(isset($template->sender_receiver->sender_infor->state->text_color) ? $template->sender_receiver->sender_infor->state->text_color : $default_text_color)) ; ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_input('sender_receiver[sender_infor][state][font_size]', 'ib_font_size', (isset($template->sender_receiver->sender_infor->state->font_size) ? $template->sender_receiver->sender_infor->state->font_size : $default_font_size), 'number', []) ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[sender_infor][state][font_style]',$font_styles,array('id','name'),'ib_font_style',(isset($template->sender_receiver->sender_infor->state->font_style) ? $template->sender_receiver->sender_infor->state->font_style : $default_font_style)); ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[sender_infor][state][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->sender_receiver->sender_infor->state->text_align) ? $template->sender_receiver->sender_infor->state->text_align : $default_text_alignment)); ?>
																				</div>
																			</li>
																		</ul>
																	</div>
																</div>
															</div>

															<div class="col-md-4">
																<div class="dropup">
																	<div class="checkbox_row">

																		<div class="checkbox checkbox-primary">
																			<input type="checkbox" id="sender_infor_zipcode" name="sender_receiver[sender_infor][zipcode][active]" <?php echo (isset($template->sender_receiver->sender_infor->zipcode->active) ? 'checked' : '') ?> value="1" onclick="column_setting(this);" data-btn-name="sender_infor_zipcode_setting_btn" data-div-name="sender_zipcode_setting" ><label for="sender_infor_zipcode"><?php echo _l('ib_zipcode'); ?></label>

																			<button id="sender_infor_zipcode_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->sender_receiver->sender_infor->zipcode->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="sender_zipcode_setting"><i class="fa fa-cog"></i></button>
																		</div>
																		<ul class="setting_popup hide" id="sender_zipcode_setting">
																			<button type="button" class="close mright10" data-dismiss="sender_zipcode_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																			<li role="presentation">
																				<div class="col-md-12">
																					<?php echo render_color_picker('sender_receiver[sender_infor][zipcode][text_color]', _l('ib_text_color'),(isset($template->sender_receiver->sender_infor->zipcode->text_color) ? $template->sender_receiver->sender_infor->zipcode->text_color : $default_text_color)) ; ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_input('sender_receiver[sender_infor][zipcode][font_size]', 'ib_font_size',(isset($template->sender_receiver->sender_infor->zipcode->font_size) ? $template->sender_receiver->sender_infor->zipcode->font_size : $default_font_size), 'number', []) ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[sender_infor][zipcode][font_style]',$font_styles,array('id','name'),'ib_font_style',(isset($template->sender_receiver->sender_infor->zipcode->font_style) ? $template->sender_receiver->sender_infor->zipcode->font_style : $default_font_style)); ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[sender_infor][zipcode][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->sender_receiver->sender_infor->zipcode->text_align) ? $template->sender_receiver->sender_infor->zipcode->text_align : $default_text_alignment)); ?>
																				</div>
																			</li>
																		</ul>
																	</div>
																</div>
															</div>

															<div class="col-md-4">
																<div class="dropup">
																	<div class="checkbox_row">

																		<div class="checkbox checkbox-primary">
																			<input type="checkbox" id="sender_infor_country" name="sender_receiver[sender_infor][country][active]" <?php echo (isset($template->sender_receiver->sender_infor->country->active) ? 'checked' : '') ?> value="1" onclick="column_setting(this);" data-btn-name="sender_infor_country_setting_btn" data-div-name="sender_country_setting" ><label for="sender_infor_country"><?php echo _l('ib_country'); ?></label>

																			<button id="sender_infor_country_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->sender_receiver->sender_infor->country->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="sender_country_setting"><i class="fa fa-cog"></i></button>
																		</div>
																		<ul class="setting_popup hide" id="sender_country_setting">
																			<button type="button" class="close mright10" data-dismiss="sender_country_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																			<li role="presentation">
																				<div class="col-md-12">
																					<?php echo render_color_picker('sender_receiver[sender_infor][country][text_color]', _l('ib_text_color'),(isset($template->sender_receiver->sender_infor->country->text_color) ? $template->sender_receiver->sender_infor->country->text_color : $default_text_color)) ; ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_input('sender_receiver[sender_infor][country][font_size]', 'ib_font_size', (isset($template->sender_receiver->sender_infor->country->font_size) ? $template->sender_receiver->sender_infor->country->font_size : $default_font_size), 'number', []) ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[sender_infor][country][font_style]',$font_styles,array('id','name'),'ib_font_style',(isset($template->sender_receiver->sender_infor->country->font_style) ? $template->sender_receiver->sender_infor->country->font_style : $default_font_style)); ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[sender_infor][country][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->sender_receiver->sender_infor->country->text_align) ? $template->sender_receiver->sender_infor->country->text_align : $default_text_alignment)); ?>
																				</div>
																			</li>
																		</ul>
																	</div>
																</div>
															</div>

															<div class="col-md-4">
																<div class="dropup">
																	<div class="checkbox_row">

																		<div class="checkbox checkbox-primary">
																			<input type="checkbox" id="sender_infor_vat" name="sender_receiver[sender_infor][vat][active]" value="1" <?php echo (isset($template->sender_receiver->sender_infor->vat->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="sender_infor_vat_setting_btn" data-div-name="sender_vat_setting" ><label for="sender_infor_vat"><?php echo _l('ib_vat_number'); ?></label>

																			<button id="sender_infor_vat_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->sender_receiver->sender_infor->vat->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="sender_vat_setting"><i class="fa fa-cog"></i></button>
																		</div>
																		<ul class="setting_popup hide" id="sender_vat_setting">
																			<button type="button" class="close mright10" data-dismiss="sender_vat_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																			<li role="presentation">
																				<div class="col-md-12">
																					<?php echo render_color_picker('sender_receiver[sender_infor][vat][text_color]', _l('ib_text_color'),(isset($template->sender_receiver->sender_infor->vat->text_color) ? $template->sender_receiver->sender_infor->vat->text_color : $default_text_color)) ; ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_input('sender_receiver[sender_infor][vat][font_size]', 'ib_font_size', (isset($template->sender_receiver->sender_infor->vat->font_size) ? $template->sender_receiver->sender_infor->vat->font_size : $default_font_size), 'number', []) ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[sender_infor][vat][font_style]',$font_styles,array('id','name'),'ib_font_style',(isset($template->sender_receiver->sender_infor->vat->font_style) ? $template->sender_receiver->sender_infor->vat->font_style : $default_font_style)); ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[sender_infor][vat][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->sender_receiver->sender_infor->vat->text_align) ? $template->sender_receiver->sender_infor->vat->text_align : $default_text_alignment)); ?>
																				</div>
																			</li>
																		</ul>
																	</div>
																</div>
															</div>

															<div class="col-md-4">
																<div class="dropup">
																	<div class="checkbox_row">

																		<div class="checkbox checkbox-primary">
																			<input type="checkbox" id="sender_infor_sales_person" name="sender_receiver[sender_infor][sales_person][active]" <?php echo (isset($template->sender_receiver->sender_infor->sales_person->active) ? 'checked' : '') ?> value="1" onclick="column_setting(this);" data-btn-name="sender_infor_sales_person_setting_btn" data-div-name="sender_sales_person_setting" ><label for="sender_infor_sales_person"><?php echo _l('ib_sales_person'); ?></label>

																			<button id="sender_infor_sales_person_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->sender_receiver->sender_infor->sales_person->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="sender_sales_person_setting"><i class="fa fa-cog"></i></button>
																		</div>
																		<ul class="setting_popup hide" id="sender_sales_person_setting">
																			<button type="button" class="close mright10" data-dismiss="sender_sales_person_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																			<li role="presentation">
																				<div class="col-md-12">
																					<?php echo render_color_picker('sender_receiver[sender_infor][sales_person][text_color]', _l('ib_text_color'),(isset($template->sender_receiver->sender_infor->sales_person->text_color) ? $template->sender_receiver->sender_infor->sales_person->text_color : $default_text_color)) ; ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_input('sender_receiver[sender_infor][sales_person][font_size]', 'ib_font_size', (isset($template->sender_receiver->sender_infor->sales_person->font_size) ? $template->sender_receiver->sender_infor->sales_person->font_size : $default_font_size), 'number', []) ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[sender_infor][sales_person][font_style]',$font_styles,array('id','name'),'ib_font_style',(isset($template->sender_receiver->sender_infor->sales_person->font_style) ? $template->sender_receiver->sender_infor->sales_person->font_style : $default_font_style)); ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[sender_infor][sales_person][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->sender_receiver->sender_infor->sales_person->text_align) ? $template->sender_receiver->sender_infor->sales_person->text_align : $default_text_alignment)); ?>
																				</div>
																			</li>
																		</ul>
																	</div>
																</div>
															</div>

															<?php 
															if(isset($template->sender_receiver->sender_infor->custom_field) && is_object($template->sender_receiver->sender_infor->custom_field) && $field_list = (array)$template->sender_receiver->sender_infor->custom_field){
																foreach ($field_list as $id => $field) { 
																	$field_data = $this->invoices_builder_model->get_custom_field($id);
																	if($field_data){
																		$field->type = 'sender_receiver[sender_infor]';
																		$field->id = $id;
																		$field->field_label = $field_data->label;
																		if(!isset($field->active)){
																			$field->active = 0;
																		}											
																		?>
																		<div class="col-md-4 custom_field_item">
																			<?php $this->load->view('templates/includes/custom_field_item', (array)$field);	?>
																		</div>
																		<?php 
																	}
																}
															} ?>


														</div>

														<div class="col-md-12" id="sender_infor_add_field_setting">
															<div class="row">
																<div class="col-md-8">
																	<div class="dropup">

																		<button type="button" id="sender_infor_add_field_btn" class="btn btn-icon btn-success mtop10<?php echo (isset($template->sender_receiver->sender_infor->active) ? '' : ' hide') ?>" onclick="sender_infor_add_field(); return false;"><i class="fa fa-plus"></i><?php echo ' '._l('add_field'); ?></button>

																		<ul class="setting_popup hide" id="sender_add_field_setting">
																			<button type="button" class="close mright10" data-dismiss="sender_add_field_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																			<li role="presentation">
																				<div class="col-md-12">
																					<div class="checkbox checkbox-primary">
																						<input type="checkbox" id="select_an_available_field2" name="select_an_available_field" value="1" ><label for="select_an_available_field2"><?php echo _l('ib_select_an_available_field'); ?></label>
																					</div>
																				</div>
																				<div class="col-md-12 fr2 hide">
																					<?php
																					$list_fields = $this->invoices_builder_model->get_custom_field('','type = "sender_receiver[sender_infor]"', 'id, label', false, 'label');
																					echo render_select('cf_sender_field_available', $list_fields, array('id', 'label'), 'select_field', '', []) ?>
																				</div>
																				<div class="col-md-6 fr1">
																					<?php echo render_input('cf_sender_field_name', 'ib_field_name', '', 'text', []) ?>
																				</div>
																				<div class="col-md-6 fr1">
																					<?php echo render_input('cf_sender_field_label', 'ib_field_label', '', 'text', []) ?>
																				</div>
																				<div class="col-md-12">
																					<span class="text-danger error_add_customfield hide" id="cf_sender_error_formula"></span>
																				</div>
																				<button type="button" onclick="add_sender_field(this); return false;" class="btn btn-sm btn-success pull-right mright10 mbot5"><?php echo _l('ib_add'); ?></button>
																			</li>
																		</ul>
																	</div>
																</div>
															</div>
														</div>
													</div>

													<div class="col-md-12 sender_receiver_row_item">
														<hr>
														<div class="checkbox checkbox-primary">
															<input type="checkbox" id="receiver_infor" name="sender_receiver[receiver_infor][active]" value="1" <?php echo (isset($template->sender_receiver->receiver_infor->active) ? 'checked' : '') ?> onclick="receiver_infor_setting(this);"><label for="receiver_infor"><?php echo _l('ib_receiver_information'); ?></label>
														</div>

														<div id="receiver_infor_setting" class="<?php echo (isset($template->sender_receiver->receiver_infor->active) ? '' : 'hide') ?>">
															<div class="col-md-6">
																<?php echo render_input('sender_receiver[receiver_infor][show_on_column]', 'ib_show_on_column', (isset($template->sender_receiver->receiver_infor->show_on_column) ? $template->sender_receiver->receiver_infor->show_on_column : ''), 'number', ['max' => 4, 'min' => 1], [], '', 'order_column') ?>
															</div>
															<div class="col-md-6">
																<?php echo render_color_picker('sender_receiver[receiver_infor][column_color]', _l('ib_column_color'), (isset($template->sender_receiver->receiver_infor->column_color) ? $template->sender_receiver->receiver_infor->column_color : $default_text_color)) ; ?>
															</div>

															<div class="col-md-4">
																<div class="dropup">
																	<div class="checkbox_row">

																		<div class="checkbox checkbox-primary">
																			<input type="checkbox" id="receiver_infor_name" name="sender_receiver[receiver_infor][name][active]" <?php echo (isset($template->sender_receiver->receiver_infor->name->active) ? 'checked' : '') ?> value="1" onclick="column_setting(this);" data-btn-name="receiver_infor_name_setting_btn" data-div-name="receiver_name_setting" ><label for="receiver_infor_name"><?php echo _l('ib_name'); ?></label>

																			<button id="receiver_infor_name_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->sender_receiver->receiver_infor->name->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="receiver_name_setting"><i class="fa fa-cog"></i></button>
																		</div>
																		<ul class="setting_popup hide" id="receiver_name_setting">
																			<button type="button" class="close mright10" data-dismiss="receiver_name_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																			<li role="presentation">
																				<div class="col-md-12">
																					<?php echo render_color_picker('sender_receiver[receiver_infor][name][text_color]', _l('ib_text_color'), (isset($template->sender_receiver->receiver_infor->name->text_color) ? $template->sender_receiver->receiver_infor->name->text_color : $default_text_color)) ; ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_input('sender_receiver[receiver_infor][name][font_size]', 'ib_font_size', (isset($template->sender_receiver->receiver_infor->name->font_size) ? $template->sender_receiver->receiver_infor->name->font_size : $default_font_size), 'number', []) ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[receiver_infor][name][font_style]',$font_styles,array('id','name'),'ib_font_style',(isset($template->sender_receiver->receiver_infor->name->font_style) ? $template->sender_receiver->receiver_infor->name->font_style : $default_font_style)); ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[receiver_infor][name][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->sender_receiver->receiver_infor->name->text_align) ? $template->sender_receiver->receiver_infor->name->text_align : $default_text_alignment)); ?>
																				</div>
																			</li>
																		</ul>
																	</div>
																</div>
															</div>

															<div class="col-md-4">
																<div class="dropup">
																	<div class="checkbox_row">

																		<div class="checkbox checkbox-primary">
																			<input type="checkbox" id="receiver_infor_phone" name="sender_receiver[receiver_infor][phone][active]" value="1" <?php echo (isset($template->sender_receiver->receiver_infor->phone->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="receiver_infor_phone_setting_btn" data-div-name="receiver_phone_setting" ><label for="receiver_infor_phone"><?php echo _l('ib_phone'); ?></label>

																			<button id="receiver_infor_phone_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->sender_receiver->receiver_infor->phone->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="receiver_phone_setting"><i class="fa fa-cog"></i></button>
																		</div>
																		<ul class="setting_popup hide" id="receiver_phone_setting">
																			<button type="button" class="close mright10" data-dismiss="receiver_phone_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																			<li role="presentation">
																				<div class="col-md-12">
																					<?php echo render_color_picker('sender_receiver[receiver_infor][phone][text_color]', _l('ib_text_color'), (isset($template->sender_receiver->receiver_infor->phone->text_color) ? $template->sender_receiver->receiver_infor->phone->text_color : $default_text_color)) ; ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_input('sender_receiver[receiver_infor][phone][font_size]', 'ib_font_size', (isset($template->sender_receiver->receiver_infor->phone->font_size) ? $template->sender_receiver->receiver_infor->phone->font_size : $default_font_size), 'number', []) ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[receiver_infor][phone][font_style]',$font_styles,array('id','name'),'ib_font_style', (isset($template->sender_receiver->receiver_infor->phone->font_style) ? $template->sender_receiver->receiver_infor->phone->font_style : $default_font_style)); ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[receiver_infor][phone][text_align]',$text_align,array('id','name'),'ib_text_align', (isset($template->sender_receiver->receiver_infor->phone->text_align) ? $template->sender_receiver->receiver_infor->phone->text_align : $default_text_alignment)); ?>
																				</div>
																			</li>
																		</ul>
																	</div>
																</div>
															</div>

															<div class="col-md-4">
																<div class="dropup">
																	<div class="checkbox_row">

																		<div class="checkbox checkbox-primary">
																			<input type="checkbox" id="receiver_infor_email" name="sender_receiver[receiver_infor][email][active]" value="1" <?php echo (isset($template->sender_receiver->receiver_infor->email->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="receiver_infor_email_setting_btn" data-div-name="receiver_email_setting" ><label for="receiver_infor_email"><?php echo _l('ib_email'); ?></label>

																			<button id="receiver_infor_email_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->sender_receiver->receiver_infor->email->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="receiver_email_setting"><i class="fa fa-cog"></i></button>
																		</div>
																		<ul class="setting_popup hide" id="receiver_email_setting">
																			<button type="button" class="close mright10" data-dismiss="receiver_email_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																			<li role="presentation">
																				<div class="col-md-12">
																					<?php echo render_color_picker('sender_receiver[receiver_infor][email][text_color]', _l('ib_text_color'), (isset($template->sender_receiver->receiver_infor->email->text_color) ? $template->sender_receiver->receiver_infor->email->text_color : $default_text_color)) ; ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_input('sender_receiver[receiver_infor][email][font_size]', 'ib_font_size', (isset($template->sender_receiver->receiver_infor->email->font_size) ? $template->sender_receiver->receiver_infor->email->font_size : $default_font_size), 'number', []) ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[receiver_infor][email][font_style]',$font_styles,array('id','name'),'ib_font_style', (isset($template->sender_receiver->receiver_infor->email->font_style) ? $template->sender_receiver->receiver_infor->email->font_style : $default_font_style)); ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[receiver_infor][email][text_align]',$text_align,array('id','name'),'ib_text_align', (isset($template->sender_receiver->receiver_infor->email->text_align) ? $template->sender_receiver->receiver_infor->email->text_align : $default_text_alignment)); ?>
																				</div>
																			</li>
																		</ul>
																	</div>
																</div>
															</div>

															<div class="col-md-4">
																<div class="dropup">
																	<div class="checkbox_row">

																		<div class="checkbox checkbox-primary">
																			<input type="checkbox" id="receiver_infor_website" name="sender_receiver[receiver_infor][website][active]" value="1" <?php echo (isset($template->sender_receiver->receiver_infor->website->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="receiver_infor_website_setting_btn" data-div-name="receiver_website_setting" ><label for="receiver_infor_website"><?php echo _l('ib_website'); ?></label>

																			<button id="receiver_infor_website_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->sender_receiver->receiver_infor->website->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="receiver_website_setting"><i class="fa fa-cog"></i></button>
																		</div>
																		<ul class="setting_popup hide" id="receiver_website_setting">
																			<button type="button" class="close mright10" data-dismiss="receiver_website_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																			<li role="presentation">
																				<div class="col-md-12">
																					<?php echo render_color_picker('sender_receiver[receiver_infor][website][text_color]', _l('ib_text_color'), (isset($template->sender_receiver->receiver_infor->website->text_color) ? $template->sender_receiver->receiver_infor->website->text_color : $default_text_color)) ; ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_input('sender_receiver[receiver_infor][website][font_size]', 'ib_font_size', (isset($template->sender_receiver->receiver_infor->website->font_size) ? $template->sender_receiver->receiver_infor->website->font_size : $default_font_size), 'number', []) ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[receiver_infor][website][font_style]',$font_styles,array('id','name'),'ib_font_style', (isset($template->sender_receiver->receiver_infor->website->font_style) ? $template->sender_receiver->receiver_infor->website->font_style : $default_font_style)); ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[receiver_infor][website][text_align]',$text_align,array('id','name'),'ib_text_align', (isset($template->sender_receiver->receiver_infor->website->text_align) ? $template->sender_receiver->receiver_infor->website->text_align : $default_text_alignment)); ?>
																				</div>
																			</li>
																		</ul>
																	</div>
																</div>
															</div>

															<div class="col-md-4">
																<div class="dropup">
																	<div class="checkbox_row">

																		<div class="checkbox checkbox-primary">
																			<input type="checkbox" id="receiver_infor_street" name="sender_receiver[receiver_infor][street][active]" value="1" <?php echo (isset($template->sender_receiver->receiver_infor->street->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="receiver_infor_street_setting_btn" data-div-name="receiver_street_setting" ><label for="receiver_infor_street"><?php echo _l('ib_street_name'); ?></label>

																			<button id="receiver_infor_street_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->sender_receiver->receiver_infor->street->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="receiver_street_setting"><i class="fa fa-cog"></i></button>
																		</div>
																		<ul class="setting_popup hide" id="receiver_street_setting">
																			<button type="button" class="close mright10" data-dismiss="receiver_street_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																			<li role="presentation">
																				<div class="col-md-12">
																					<?php echo render_color_picker('sender_receiver[receiver_infor][street][text_color]', _l('ib_text_color'), (isset($template->sender_receiver->receiver_infor->street->text_color) ? $template->sender_receiver->receiver_infor->street->text_color : $default_text_color)) ; ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_input('sender_receiver[receiver_infor][street][font_size]', 'ib_font_size', (isset($template->sender_receiver->receiver_infor->street->font_size) ? $template->sender_receiver->receiver_infor->street->font_size : $default_font_size), 'number', []) ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[receiver_infor][street][font_style]',$font_styles,array('id','name'),'ib_font_style', (isset($template->sender_receiver->receiver_infor->street->font_style) ? $template->sender_receiver->receiver_infor->street->font_style : $default_font_style)); ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[receiver_infor][street][text_align]',$text_align,array('id','name'),'ib_text_align', (isset($template->sender_receiver->receiver_infor->street->text_align) ? $template->sender_receiver->receiver_infor->street->text_align : $default_text_alignment)); ?>
																				</div>
																			</li>
																		</ul>
																	</div>
																</div>
															</div>

															<div class="col-md-4">
																<div class="dropup">
																	<div class="checkbox_row">

																		<div class="checkbox checkbox-primary">
																			<input type="checkbox" id="receiver_infor_city" name="sender_receiver[receiver_infor][city][active]" value="1" <?php echo (isset($template->sender_receiver->receiver_infor->city->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="receiver_infor_city_setting_btn" data-div-name="receiver_city_setting" ><label for="receiver_infor_city"><?php echo _l('ib_city'); ?></label>

																			<button id="receiver_infor_city_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->sender_receiver->receiver_infor->city->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="receiver_city_setting"><i class="fa fa-cog"></i></button>
																		</div>
																		<ul class="setting_popup hide" id="receiver_city_setting">
																			<button type="button" class="close mright10" data-dismiss="receiver_city_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																			<li role="presentation">
																				<div class="col-md-12">
																					<?php echo render_color_picker('sender_receiver[receiver_infor][city][text_color]', _l('ib_text_color'), (isset($template->sender_receiver->receiver_infor->city->text_color) ? $template->sender_receiver->receiver_infor->city->text_color : $default_text_color)) ; ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_input('sender_receiver[receiver_infor][city][font_size]', 'ib_font_size', (isset($template->sender_receiver->receiver_infor->city->font_size) ? $template->sender_receiver->receiver_infor->city->font_size : $default_font_size), 'number', []) ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[receiver_infor][city][font_style]',$font_styles,array('id','name'),'ib_font_style', (isset($template->sender_receiver->receiver_infor->city->font_style) ? $template->sender_receiver->receiver_infor->city->font_style : $default_font_style)); ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[receiver_infor][city][text_align]',$text_align,array('id','name'),'ib_text_align', (isset($template->sender_receiver->receiver_infor->city->text_align) ? $template->sender_receiver->receiver_infor->city->text_align : $default_text_alignment)); ?>
																				</div>
																			</li>
																		</ul>
																	</div>
																</div>
															</div>

															<div class="col-md-4">
																<div class="dropup">
																	<div class="checkbox_row">

																		<div class="checkbox checkbox-primary">
																			<input type="checkbox" id="receiver_infor_state" name="sender_receiver[receiver_infor][state][active]" value="1" <?php echo (isset($template->sender_receiver->receiver_infor->state->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="receiver_infor_state_setting_btn" data-div-name="receiver_state_setting" ><label for="receiver_infor_state"><?php echo _l('ib_state'); ?></label>

																			<button id="receiver_infor_state_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->sender_receiver->receiver_infor->state->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="receiver_state_setting"><i class="fa fa-cog"></i></button>
																		</div>
																		<ul class="setting_popup hide" id="receiver_state_setting">
																			<button type="button" class="close mright10" data-dismiss="receiver_state_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																			<li role="presentation">
																				<div class="col-md-12">
																					<?php echo render_color_picker('sender_receiver[receiver_infor][state][text_color]', _l('ib_text_color'), (isset($template->sender_receiver->receiver_infor->state->text_color) ? $template->sender_receiver->receiver_infor->state->text_color : $default_text_color)) ; ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_input('sender_receiver[receiver_infor][state][font_size]', 'ib_font_size', (isset($template->sender_receiver->receiver_infor->state->font_size) ? $template->sender_receiver->receiver_infor->state->font_size : $default_font_size), 'number', []) ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[receiver_infor][state][font_style]',$font_styles,array('id','name'),'ib_font_style', (isset($template->sender_receiver->receiver_infor->state->font_style) ? $template->sender_receiver->receiver_infor->state->font_style : $default_font_style)); ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[receiver_infor][state][text_align]',$text_align,array('id','name'),'ib_text_align', (isset($template->sender_receiver->receiver_infor->state->text_align) ? $template->sender_receiver->receiver_infor->state->text_align : $default_text_alignment)); ?>
																				</div>
																			</li>
																		</ul>
																	</div>
																</div>
															</div>

															<div class="col-md-4">
																<div class="dropup">
																	<div class="checkbox_row">

																		<div class="checkbox checkbox-primary">
																			<input type="checkbox" id="receiver_infor_zipcode" name="sender_receiver[receiver_infor][zipcode][active]" value="1" <?php echo (isset($template->sender_receiver->receiver_infor->zipcode->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="receiver_infor_zipcode_setting_btn" data-div-name="receiver_zipcode_setting" ><label for="receiver_infor_zipcode"><?php echo _l('ib_zipcode'); ?></label>

																			<button id="receiver_infor_zipcode_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->sender_receiver->receiver_infor->zipcode->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="receiver_zipcode_setting"><i class="fa fa-cog"></i></button>
																		</div>
																		<ul class="setting_popup hide" id="receiver_zipcode_setting">
																			<button type="button" class="close mright10" data-dismiss="receiver_zipcode_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																			<li role="presentation">
																				<div class="col-md-12">
																					<?php echo render_color_picker('sender_receiver[receiver_infor][zipcode][text_color]', _l('ib_text_color'), (isset($template->sender_receiver->receiver_infor->zipcode->text_color) ? $template->sender_receiver->receiver_infor->zipcode->text_color : $default_text_color)) ; ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_input('sender_receiver[receiver_infor][zipcode][font_size]', 'ib_font_size', (isset($template->sender_receiver->receiver_infor->zipcode->font_size) ? $template->sender_receiver->receiver_infor->zipcode->font_size : $default_font_size), 'number', []) ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[receiver_infor][zipcode][font_style]',$font_styles,array('id','name'),'ib_font_style', (isset($template->sender_receiver->receiver_infor->zipcode->font_style) ? $template->sender_receiver->receiver_infor->zipcode->font_style : $default_font_style)); ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[receiver_infor][zipcode][text_align]',$text_align,array('id','name'),'ib_text_align', (isset($template->sender_receiver->receiver_infor->zipcode->text_align) ? $template->sender_receiver->receiver_infor->zipcode->text_align : $default_text_alignment)); ?>
																				</div>
																			</li>
																		</ul>
																	</div>
																</div>
															</div>

															<div class="col-md-4">
																<div class="dropup">
																	<div class="checkbox_row">

																		<div class="checkbox checkbox-primary">
																			<input type="checkbox" id="receiver_infor_country" name="sender_receiver[receiver_infor][country][active]" value="1" <?php echo (isset($template->sender_receiver->receiver_infor->country->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="receiver_infor_country_setting_btn" data-div-name="receiver_country_setting" ><label for="receiver_infor_country"><?php echo _l('ib_country'); ?></label>

																			<button id="receiver_infor_country_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->sender_receiver->receiver_infor->country->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="receiver_country_setting"><i class="fa fa-cog"></i></button>
																		</div>
																		<ul class="setting_popup hide" id="receiver_country_setting">
																			<button type="button" class="close mright10" data-dismiss="receiver_country_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																			<li role="presentation">
																				<div class="col-md-12">
																					<?php echo render_color_picker('sender_receiver[receiver_infor][country][text_color]', _l('ib_text_color'), (isset($template->sender_receiver->receiver_infor->country->text_color) ? $template->sender_receiver->receiver_infor->country->text_color : $default_text_color)) ; ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_input('sender_receiver[receiver_infor][country][font_size]', 'ib_font_size', (isset($template->sender_receiver->receiver_infor->country->font_size) ? $template->sender_receiver->receiver_infor->country->font_size : $default_font_size), 'number', []) ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[receiver_infor][country][font_style]',$font_styles,array('id','name'),'ib_font_style', (isset($template->sender_receiver->receiver_infor->country->font_style) ? $template->sender_receiver->receiver_infor->country->font_style : $default_font_style)); ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[receiver_infor][country][text_align]',$text_align,array('id','name'),'ib_text_align', (isset($template->sender_receiver->receiver_infor->country->text_align) ? $template->sender_receiver->receiver_infor->country->text_align : $default_text_alignment)); ?>
																				</div>
																			</li>
																		</ul>
																	</div>
																</div>
															</div>

															<div class="col-md-4">
																<div class="dropup">
																	<div class="checkbox_row">

																		<div class="checkbox checkbox-primary">
																			<input type="checkbox" id="receiver_infor_vat" name="sender_receiver[receiver_infor][vat][active]" value="1" <?php echo (isset($template->sender_receiver->receiver_infor->vat->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="receiver_infor_vat_setting_btn" data-div-name="receiver_vat_setting" ><label for="receiver_infor_vat"><?php echo _l('ib_vat_number'); ?></label>

																			<button id="receiver_infor_vat_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->sender_receiver->receiver_infor->vat->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="receiver_vat_setting"><i class="fa fa-cog"></i></button>
																		</div>
																		<ul class="setting_popup hide" id="receiver_vat_setting">
																			<button type="button" class="close mright10" data-dismiss="receiver_vat_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																			<li role="presentation">
																				<div class="col-md-12">
																					<?php echo render_color_picker('sender_receiver[receiver_infor][vat][text_color]', _l('ib_text_color'), (isset($template->sender_receiver->receiver_infor->vat->text_color) ? $template->sender_receiver->receiver_infor->vat->text_color : $default_text_color)) ; ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_input('sender_receiver[receiver_infor][vat][font_size]', 'ib_font_size', (isset($template->sender_receiver->receiver_infor->vat->font_size) ? $template->sender_receiver->receiver_infor->vat->font_size : $default_font_size), 'number', []) ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[receiver_infor][vat][font_style]',$font_styles,array('id','name'),'ib_font_style', (isset($template->sender_receiver->receiver_infor->vat->font_style) ? $template->sender_receiver->receiver_infor->vat->font_style : $default_font_style)); ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[receiver_infor][vat][text_align]',$text_align,array('id','name'),'ib_text_align', (isset($template->sender_receiver->receiver_infor->vat->text_align) ? $template->sender_receiver->receiver_infor->vat->text_align : $default_text_alignment)); ?>
																				</div>
																			</li>
																		</ul>
																	</div>
																</div>
															</div>


															<div class="col-md-4">
																<div class="dropup">
																	<div class="checkbox_row">

																		<div class="checkbox checkbox-primary">
																			<input type="checkbox" id="receiver_infor_billing_to" name="sender_receiver[receiver_infor][billing_to][active]" value="1" <?php echo (isset($template->sender_receiver->receiver_infor->billing_to->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="receiver_infor_billing_to_setting_btn" data-div-name="receiver_billing_to_setting" ><label for="receiver_infor_billing_to"><?php echo _l('ib_billing_to'); ?></label>

																			<button id="receiver_infor_billing_to_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->sender_receiver->receiver_infor->billing_to->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="receiver_billing_to_setting"><i class="fa fa-cog"></i></button>
																		</div>
																		<ul class="setting_popup hide" id="receiver_billing_to_setting">
																			<button type="button" class="close mright10" data-dismiss="receiver_billing_to_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																			<li role="presentation">
																				<div class="col-md-12">
																					<?php echo render_color_picker('sender_receiver[receiver_infor][billing_to][text_color]', _l('ib_text_color'), (isset($template->sender_receiver->receiver_infor->billing_to->text_color) ? $template->sender_receiver->receiver_infor->billing_to->text_color : $default_text_color)) ; ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_input('sender_receiver[receiver_infor][billing_to][font_size]', 'ib_font_size', (isset($template->sender_receiver->receiver_infor->billing_to->font_size) ? $template->sender_receiver->receiver_infor->billing_to->font_size : $default_font_size), 'number', []) ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[receiver_infor][billing_to][font_style]',$font_styles,array('id','name'),'ib_font_style', (isset($template->sender_receiver->receiver_infor->billing_to->font_style) ? $template->sender_receiver->receiver_infor->billing_to->font_style : $default_font_style)); ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[receiver_infor][billing_to][text_align]',$text_align,array('id','name'),'ib_text_align', (isset($template->sender_receiver->receiver_infor->billing_to->text_align) ? $template->sender_receiver->receiver_infor->billing_to->text_align : $default_text_alignment)); ?>
																				</div>
																			</li>
																		</ul>
																	</div>
																</div>
															</div>

															<div class="col-md-4">
																<div class="dropup">
																	<div class="checkbox_row">

																		<div class="checkbox checkbox-primary">
																			<input type="checkbox" id="receiver_infor_shipping_to" name="sender_receiver[receiver_infor][shipping_to][active]" value="1" <?php echo (isset($template->sender_receiver->receiver_infor->shipping_to->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="receiver_infor_shipping_to_setting_btn" data-div-name="receiver_shipping_to_setting" ><label for="receiver_infor_shipping_to"><?php echo _l('ib_shipping_to'); ?></label>

																			<button id="receiver_infor_shipping_to_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->sender_receiver->receiver_infor->shipping_to->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="receiver_shipping_to_setting"><i class="fa fa-cog"></i></button>
																		</div>
																		<ul class="setting_popup hide" id="receiver_shipping_to_setting">
																			<button type="button" class="close mright10" data-dismiss="receiver_shipping_to_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																			<li role="presentation">
																				<div class="col-md-12">
																					<?php echo render_color_picker('sender_receiver[receiver_infor][shipping_to][text_color]', _l('ib_text_color'), (isset($template->sender_receiver->receiver_infor->shipping_to->text_color) ? $template->sender_receiver->receiver_infor->shipping_to->text_color : $default_text_color)) ; ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_input('sender_receiver[receiver_infor][shipping_to][font_size]', 'ib_font_size', (isset($template->sender_receiver->receiver_infor->shipping_to->font_size) ? $template->sender_receiver->receiver_infor->shipping_to->font_size : $default_font_size), 'number', []) ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[receiver_infor][shipping_to][font_style]',$font_styles,array('id','name'),'ib_font_style', (isset($template->sender_receiver->receiver_infor->shipping_to->font_style) ? $template->sender_receiver->receiver_infor->shipping_to->font_style : $default_font_style)); ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('sender_receiver[receiver_infor][shipping_to][text_align]',$text_align,array('id','name'),'ib_text_align', (isset($template->sender_receiver->receiver_infor->shipping_to->text_align) ? $template->sender_receiver->receiver_infor->shipping_to->text_align : $default_text_alignment)); ?>
																				</div>
																			</li>
																		</ul>
																	</div>
																</div>
															</div>

															<?php 
															if(isset($template->sender_receiver->receiver_infor->custom_field) && is_object($template->sender_receiver->receiver_infor->custom_field) && $field_list = (array)$template->sender_receiver->receiver_infor->custom_field){
																foreach ($field_list as $id => $field) { 
																	$field_data = $this->invoices_builder_model->get_custom_field($id);
																	if($field_data){
																		$field->type = 'sender_receiver[receiver_infor]';;
																		$field->id = $id;
																		$field->field_label = $field_data->label;
																		if(!isset($field->active)){
																			$field->active = 0;
																		}											
																		?>
																		<div class="col-md-4 custom_field_item">
																			<?php $this->load->view('templates/includes/custom_field_item', (array)$field);	?>
																		</div>
																		<?php 
																	}
																}
															} ?>
														</div>

														<div class="col-md-12" id="receiver_infor_add_field_setting">
															<div class="row">
																<div class="col-md-8">
																	<div class="dropup">
																		<button type="button" id="receiver_infor_add_field_btn" class="btn btn-icon btn-success mtop10<?php echo (isset($template->sender_receiver->receiver_infor->active) ? '' : ' hide') ?>" onclick="receiver_infor_add_field(); return false;"><i class="fa fa-plus"></i><?php echo ' '._l('add_field'); ?></button>

																		<ul class="setting_popup hide" id="receiver_add_field_setting">
																			<button type="button" class="close mright10" data-dismiss="receiver_add_field_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																			<li role="presentation">
																				<div class="col-md-12">
																					<div class="checkbox checkbox-primary">
																						<input type="checkbox" id="select_an_available_field3" name="select_an_available_field" value="1" ><label for="select_an_available_field3"><?php echo _l('ib_select_an_available_field'); ?></label>
																					</div>
																				</div>
																				<div class="col-md-12 fr2 hide">
																					<?php
																					$list_fields = $this->invoices_builder_model->get_custom_field('','type = "sender_receiver[receiver_infor]"', 'id, label', false, 'label');
																					echo render_select('cf_receiver_field_available', $list_fields, array('id', 'label'), 'select_field', '', []) ?>
																				</div>
																				<div class="col-md-6 fr1">
																					<?php echo render_input('cf_receiver_field_name', 'ib_field_name', '', 'text', []) ?>
																				</div>
																				<div class="col-md-6 fr1">
																					<?php echo render_input('cf_receiver_field_label', 'ib_field_label', '', 'text', []) ?>
																				</div>
																				<div class="col-md-12">
																					<span class="text-danger error_add_customfield hide" id="cf_receiver_error_formula"></span>
																				</div>
																				<button type="button" onclick="add_receiver_field(this); return false;" class="btn btn-sm btn-success pull-right mright10 mbot5"><?php echo _l('ib_add'); ?></button>
																			</li>
																		</ul>
																	</div>
																</div>
															</div>
														</div>

													</div>

												</div>
											</div>
										</div>
									</div>
								</div>

								<div class="panel panel-info item_table_panel" data-panel="item_table_panel">
									<div class="panel-heading"><?php echo _l('ib_item_table_section'); ?></div>
									<div class="panel-body">
										<div class="row">
											<div class="list_item_table_row">
												<div class="row_item">

													<div class="col-md-12">
														<p class="bold row-infor-color"><?php echo _l('ib_table_setting'); ?></p>
														<hr class="row-infor-hr">
													</div>

													<div class="col-md-6">
														<?php echo render_color_picker('item_table[thead_color]', _l('ib_thead_color'), (isset($template->item_table->thead_color) ? $template->item_table->thead_color : $default_background_color), ['required' => true]) ; ?>
													</div>
													<div class="col-md-6">
														<?php echo render_color_picker('item_table[text_color]', _l('ib_text_color'), (isset($template->item_table->text_color) ? $template->item_table->text_color : $default_text_color)) ; ?>
													</div>
													<div class="col-md-4">
														<?php echo render_input('item_table[font_size]', 'ib_font_size', (isset($template->item_table->font_size) ? $template->item_table->font_size : $default_font_size), 'number', ['required' => true]) ?>
													</div>
													<div class="col-md-4">
														<?php echo render_select('item_table[font_style]',$font_styles,array('id','name'),'ib_font_style', (isset($template->item_table->font_style) ? $template->item_table->font_style : $default_font_style),['required' => true]); ?>
													</div>
													<div class="col-md-4">
														<?php echo render_select('item_table[text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->item_table->text_align) ? $template->item_table->text_align : $default_text_alignment),['required' => true]); ?>
													</div>

													<div class="col-md-12">
														<div class="checkbox_row">

															<div class="checkbox checkbox-primary">
																<input type="checkbox" id="striped_row" name="item_table[striped_row][active]" value="1" <?php echo (isset($template->item_table->striped_row->active) ? 'checked' : '') ?> onclick="striped_row_option(this);" data-div-name="striped_row_setting"><label for="striped_row"><?php echo _l('ib_striped_row'); ?></label>
															</div>
															<div id="striped_row_setting" class="<?php echo (isset($template->item_table->striped_row->active) ? '' : 'hide') ?>">
																<div class="col-md-6">
																	<?php echo render_color_picker('item_table[striped_row][odd_row_color]', _l('ib_odd_row_color'), (isset($template->item_table->striped_row->odd_row_color) ? $template->item_table->striped_row->odd_row_color : '')) ; ?>
																</div>
																<div class="col-md-6">
																	<?php echo render_color_picker('item_table[striped_row][even_row_color]', _l('ib_even_row_color'), (isset($template->item_table->striped_row->even_row_color) ? $template->item_table->striped_row->even_row_color : '')) ; ?>
																</div>
															</div>	
														</div>
													</div>


													<div class="col-md-12">
														<p class="bold row-infor-color"><?php echo _l('ib_column_setting'); ?></p>
														<hr class="row-infor-hr">
													</div>

													<div id="item_table_field">

														<div class="col-md-6">
															<div class="dropup">
																<div class="checkbox_row">
																	<div class="checkbox checkbox-primary">
																		<input type="checkbox" id="tb_order" name="item_table[item_order][active]" value="1" <?php echo (isset($template->item_table->item_order->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="tb_order_setting_btn" data-div-name="tb_order_setting" ><label for="tb_order"><?php echo _l('ib_item_order'); ?></label>

																		<button id="tb_order_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->item_table->item_order->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="tb_order_setting"><i class="fa fa-cog"></i></button>
																	</div>

																	<ul class="setting_popup hide" id="tb_order_setting">
																		<button type="button" class="close mright10" data-dismiss="tb_order_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<?php

																				echo render_input('item_table[item_order][order_column]', 'ib_order_column', (isset($template->item_table->item_order->order_column) ? $template->item_table->item_order->order_column : ''), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_color_picker('item_table[item_order][text_color]', _l('ib_text_color'), (isset($template->item_table->item_order->text_color) ? $template->item_table->item_order->text_color : $default_text_color)) ; ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('item_table[item_order][font_size]', 'ib_font_size', (isset($template->item_table->item_order->font_size) ? $template->item_table->item_order->font_size : $default_font_size), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('item_table[item_order][font_style]',$font_styles,array('id','name'),'ib_font_style', (isset($template->item_table->item_order->font_style) ? $template->item_table->item_order->font_style : $default_font_style)); ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('item_table[item_order][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->item_table->item_order->text_align) ? $template->item_table->item_order->text_align : $default_text_alignment)); ?>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>

														<div class="col-md-6">
															<div class="dropup">
																<div class="checkbox_row">
																	<div class="checkbox checkbox-primary">
																		<input type="checkbox" id="tb_item_name" name="item_table[item_name][active]" value="1" <?php echo (isset($template->item_table->item_name->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="tb_item_name_setting_btn" data-div-name="tb_item_name_setting" ><label for="tb_item_name"><?php echo _l('ib_item_name'); ?></label>

																		<button id="tb_item_name_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->item_table->item_name->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="tb_item_name_setting"><i class="fa fa-cog"></i></button>
																	</div>

																	<ul class="setting_popup hide" id="tb_item_name_setting">
																		<button type="button" class="close mright10" data-dismiss="tb_item_name_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<?php echo render_input('item_table[item_name][order_column]', 'ib_order_column', (isset($template->item_table->item_name->order_column) ? $template->item_table->item_name->order_column : ''), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_color_picker('item_table[item_name][text_color]', _l('ib_text_color'), (isset($template->item_table->item_name->text_color) ? $template->item_table->item_name->text_color : $default_text_color)) ; ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('item_table[item_name][font_size]', 'ib_font_size', (isset($template->item_table->item_name->font_size) ? $template->item_table->item_name->font_size : $default_font_size), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('item_table[item_name][font_style]',$font_styles,array('id','name'),'ib_font_style', (isset($template->item_table->item_name->font_style) ? $template->item_table->item_name->font_style : $default_font_style)); ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('item_table[item_name][text_align]',$text_align,array('id','name'),'ib_text_align', (isset($template->item_table->item_name->text_align) ? $template->item_table->item_name->text_align : $default_text_alignment)); ?>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>

														<div class="col-md-6">
															<div class="dropup">
																<div class="checkbox_row">
																	<div class="checkbox checkbox-primary">
																		<input type="checkbox" id="tb_item_description" name="item_table[description][active]" value="1" <?php echo (isset($template->item_table->description->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="tb_item_description_setting_btn" data-div-name="tb_item_description_setting" ><label for="tb_item_description"><?php echo _l('ib_item_description'); ?></label>

																		<button id="tb_item_description_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->item_table->description->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="tb_item_description_setting"><i class="fa fa-cog"></i></button>
																	</div>

																	<ul class="setting_popup hide" id="tb_item_description_setting">
																		<button type="button" class="close mright10" data-dismiss="tb_item_description_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<?php echo render_input('item_table[description][order_column]', 'ib_order_column', (isset($template->item_table->description->order_column) ? $template->item_table->description->order_column : ''), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_color_picker('item_table[description][text_color]', _l('ib_text_color'), (isset($template->item_table->description->text_color) ? $template->item_table->description->text_color : $default_text_color)) ; ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('item_table[description][font_size]', 'ib_font_size', (isset($template->item_table->description->font_size) ? $template->item_table->description->font_size : $default_font_size), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('item_table[description][font_style]',$font_styles,array('id','name'),'ib_font_style', (isset($template->item_table->description->font_style) ? $template->item_table->description->font_style : $default_font_style)); ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('item_table[description][text_align]',$text_align,array('id','name'),'ib_text_align', (isset($template->item_table->description->text_align) ? $template->item_table->description->text_align : $default_text_alignment)); ?>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>

														<div class="col-md-6">
															<div class="dropup">
																<div class="checkbox_row">
																	<div class="checkbox checkbox-primary">
																		<input type="checkbox" id="tb_quantity" name="item_table[quantity][active]" value="1" <?php echo (isset($template->item_table->quantity->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="tb_quantity_setting_btn" data-div-name="tb_quantity_setting" ><label for="tb_quantity"><?php echo _l('ib_quantity'); ?></label>

																		<button id="tb_quantity_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->item_table->quantity->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="tb_quantity_setting"><i class="fa fa-cog"></i></button>
																	</div>

																	<ul class="setting_popup hide" id="tb_quantity_setting">
																		<button type="button" class="close mright10" data-dismiss="tb_quantity_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<?php echo render_input('item_table[quantity][order_column]', 'ib_order_column', (isset($template->item_table->quantity->order_column) ? $template->item_table->quantity->order_column : ''), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_color_picker('item_table[quantity][text_color]', _l('ib_text_color'), (isset($template->item_table->quantity->text_color) ? $template->item_table->quantity->text_color : $default_text_color)) ; ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('item_table[quantity][font_size]', 'ib_font_size', (isset($template->item_table->quantity->font_size) ? $template->item_table->quantity->font_size : $default_font_size), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('item_table[quantity][font_style]',$font_styles,array('id','name'),'ib_font_style', (isset($template->item_table->quantity->font_style) ? $template->item_table->quantity->font_style : $default_font_style)); ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('item_table[quantity][text_align]',$text_align,array('id','name'),'ib_text_align', (isset($template->item_table->quantity->text_align) ? $template->item_table->quantity->text_align : $default_text_alignment)); ?>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>

														<div class="col-md-6">
															<div class="dropup">
																<div class="checkbox_row">
																	<div class="checkbox checkbox-primary">
																		<input type="checkbox" id="tb_unit" name="item_table[unit][active]" value="1" <?php echo (isset($template->item_table->unit->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="tb_unit_setting_btn" data-div-name="tb_unit_setting" ><label for="tb_unit"><?php echo _l('ib_unit'); ?></label>

																		<button id="tb_unit_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->item_table->unit->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="tb_unit_setting"><i class="fa fa-cog"></i></button>
																	</div>

																	<ul class="setting_popup hide" id="tb_unit_setting">
																		<button type="button" class="close mright10" data-dismiss="tb_unit_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<?php echo render_input('item_table[unit][order_column]', 'ib_order_column', (isset($template->item_table->unit->order_column) ? $template->item_table->unit->order_column : ''), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_color_picker('item_table[unit][text_color]', _l('ib_text_color'), (isset($template->item_table->unit->text_color) ? $template->item_table->unit->text_color : $default_text_color)) ; ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('item_table[unit][font_size]', 'ib_font_size', (isset($template->item_table->unit->font_size) ? $template->item_table->unit->font_size : $default_font_size), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('item_table[unit][font_style]',$font_styles,array('id','name'),'ib_font_style', (isset($template->item_table->unit->font_style) ? $template->item_table->unit->font_style : $default_font_style)); ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('item_table[unit][text_align]',$text_align,array('id','name'),'ib_text_align', (isset($template->item_table->unit->text_align) ? $template->item_table->unit->text_align : $default_text_alignment)); ?>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>

														<div class="col-md-6">
															<div class="dropup">
																<div class="checkbox_row">
																	<div class="checkbox checkbox-primary">
																		<input type="checkbox" id="tb_unit_price" name="item_table[unit_price][active]" value="1" <?php echo (isset($template->item_table->unit_price->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="tb_unit_price_setting_btn" data-div-name="tb_unit_price_setting" ><label for="tb_unit_price"><?php echo _l('ib_unit_price'); ?></label>

																		<button id="tb_unit_price_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->item_table->unit_price->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="tb_unit_price_setting"><i class="fa fa-cog"></i></button>
																	</div>

																	<ul class="setting_popup hide" id="tb_unit_price_setting">
																		<button type="button" class="close mright10" data-dismiss="tb_unit_price_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<?php echo render_input('item_table[unit_price][order_column]', 'ib_order_column', (isset($template->item_table->unit_price->order_column) ? $template->item_table->unit_price->order_column : ''), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_color_picker('item_table[unit_price][text_color]', _l('ib_text_color'), (isset($template->item_table->unit_price->text_color) ? $template->item_table->unit_price->text_color : $default_text_color)) ; ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('item_table[unit_price][font_size]', 'ib_font_size', (isset($template->item_table->unit_price->font_size) ? $template->item_table->unit_price->font_size : $default_font_size), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('item_table[unit_price][font_style]',$font_styles,array('id','name'),'ib_font_style', (isset($template->item_table->unit_price->font_style) ? $template->item_table->unit_price->font_style : $default_font_style)); ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('item_table[unit_price][text_align]',$text_align,array('id','name'),'ib_text_align', (isset($template->item_table->unit_price->text_align) ? $template->item_table->unit_price->text_align : $default_text_alignment)); ?>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>

														<div class="col-md-6">
															<div class="dropup">
																<div class="checkbox_row">
																	<div class="checkbox checkbox-primary">
																		<input type="checkbox" id="tb_tax_rate" name="item_table[tax_rate][active]" value="1" <?php echo (isset($template->item_table->tax_rate->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="tb_tax_rate_setting_btn" data-div-name="tb_tax_rate_setting" ><label for="tb_tax_rate"><?php echo _l('ib_tax_rate'); ?></label>

																		<button id="tb_tax_rate_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->item_table->tax_rate->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="tb_tax_rate_setting"><i class="fa fa-cog"></i></button>
																	</div>

																	<ul class="setting_popup hide" id="tb_tax_rate_setting">
																		<button type="button" class="close mright10" data-dismiss="tb_tax_rate_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<?php echo render_input('item_table[tax_rate][order_column]', 'ib_order_column', (isset($template->item_table->tax_rate->order_column) ? $template->item_table->tax_rate->order_column : ''), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_color_picker('item_table[tax_rate][text_color]', _l('ib_text_color'), (isset($template->item_table->tax_rate->text_color) ? $template->item_table->tax_rate->text_color : $default_text_color)) ; ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('item_table[tax_rate][font_size]', 'ib_font_size', (isset($template->item_table->tax_rate->font_size) ? $template->item_table->tax_rate->font_size : $default_font_size), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('item_table[tax_rate][font_style]',$font_styles,array('id','name'),'ib_font_style', (isset($template->item_table->tax_rate->font_style) ? $template->item_table->tax_rate->font_style : $default_font_style)); ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('item_table[tax_rate][text_align]',$text_align,array('id','name'),'ib_text_align', (isset($template->item_table->tax_rate->text_align) ? $template->item_table->tax_rate->text_align : $default_text_alignment)); ?>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>

														<div class="col-md-6">
															<div class="dropup">
																<div class="checkbox_row">
																	<div class="checkbox checkbox-primary">
																		<input type="checkbox" id="tb_tax_amount" name="item_table[tax_amount][active]" value="1" <?php echo (isset($template->item_table->tax_amount->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="tb_tax_amount_setting_btn" data-div-name="tb_tax_amount_setting" ><label for="tb_tax_amount"><?php echo _l('ib_tax_amount'); ?></label>

																		<button id="tb_tax_amount_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->item_table->tax_amount->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="tb_tax_amount_setting"><i class="fa fa-cog"></i></button>
																	</div>

																	<ul class="setting_popup hide" id="tb_tax_amount_setting">
																		<button type="button" class="close mright10" data-dismiss="tb_tax_amount_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<?php echo render_input('item_table[tax_amount][order_column]', 'ib_order_column', (isset($template->item_table->tax_amount->order_column) ? $template->item_table->tax_amount->order_column : ''), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_color_picker('item_table[tax_amount][text_color]', _l('ib_text_color'), (isset($template->item_table->tax_amount->text_color) ? $template->item_table->tax_amount->text_color : $default_text_color)) ; ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('item_table[tax_amount][font_size]', 'ib_font_size', (isset($template->item_table->tax_amount->font_size) ? $template->item_table->tax_amount->font_size : $default_font_size), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('item_table[tax_amount][font_style]',$font_styles,array('id','name'),'ib_font_style', (isset($template->item_table->tax_amount->font_style) ? $template->item_table->tax_amount->font_style : $default_font_style)); ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('item_table[tax_amount][text_align]',$text_align,array('id','name'),'ib_text_align', (isset($template->item_table->tax_amount->text_align) ? $template->item_table->tax_amount->text_align : $default_text_alignment)); ?>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>

														<div class="col-md-6">
															<div class="dropup">
																<div class="checkbox_row">
																	<div class="checkbox checkbox-primary">
																		<input type="checkbox" id="tb_subtotal" name="item_table[subtotal][active]" value="1" <?php echo (isset($template->item_table->subtotal->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="tb_subtotal_setting_btn" data-div-name="tb_subtotal_setting" ><label for="tb_subtotal"><?php echo _l('ib_subtotal'); ?></label>

																		<button id="tb_subtotal_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->item_table->subtotal->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="tb_subtotal_setting"><i class="fa fa-cog"></i></button>
																	</div>

																	<ul class="setting_popup hide" id="tb_subtotal_setting">
																		<button type="button" class="close mright10" data-dismiss="tb_subtotal_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<?php echo render_input('item_table[subtotal][order_column]', 'ib_order_column', (isset($template->item_table->subtotal->order_column) ? $template->item_table->subtotal->order_column : ''), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_color_picker('item_table[subtotal][text_color]', _l('ib_text_color'), (isset($template->item_table->subtotal->text_color) ? $template->item_table->subtotal->text_color : $default_text_color)) ; ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('item_table[subtotal][font_size]', 'ib_font_size', (isset($template->item_table->subtotal->font_size) ? $template->item_table->subtotal->font_size : $default_font_size), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('item_table[subtotal][font_style]',$font_styles,array('id','name'),'ib_font_style', (isset($template->item_table->subtotal->font_style) ? $template->item_table->subtotal->font_style : $default_font_style)); ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('item_table[subtotal][text_align]',$text_align,array('id','name'),'ib_text_align', (isset($template->item_table->subtotal->text_align) ? $template->item_table->subtotal->text_align : $default_text_alignment)); ?>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>
														<?php if(ib_get_status_modules('warehouse') || ib_get_status_modules('purchase')){ ?>
															<div class="col-md-6">
																<div class="dropup">
																	<div class="checkbox_row">
																		<div class="checkbox checkbox-primary">
																			<input type="checkbox" id="tb_sku" name="item_table[sku][active]" value="1" <?php echo (isset($template->item_table->sku->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="tb_sku_setting_btn" data-div-name="tb_sku_setting" ><label for="tb_sku"><?php echo _l('ib_sku'); ?></label>

																			<button id="tb_sku_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->item_table->sku->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="tb_sku_setting"><i class="fa fa-cog"></i></button>
																		</div>

																		<ul class="setting_popup hide" id="tb_sku_setting">
																			<button type="button" class="close mright10" data-dismiss="tb_sku_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																			<li role="presentation">
																				<div class="col-md-12">
																					<?php echo render_input('item_table[sku][order_column]', 'ib_order_column', (isset($template->item_table->sku->order_column) ? $template->item_table->sku->order_column : ''), 'number', []) ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_color_picker('item_table[sku][text_color]', _l('ib_text_color'), (isset($template->item_table->sku->text_color) ? $template->item_table->sku->text_color : $default_text_color)) ; ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_input('item_table[sku][font_size]', 'ib_font_size', (isset($template->item_table->sku->font_size) ? $template->item_table->sku->font_size : $default_font_size), 'number', []) ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('item_table[sku][font_style]',$font_styles,array('id','name'),'ib_font_style', (isset($template->item_table->sku->font_style) ? $template->item_table->sku->font_style : $default_font_style)); ?>
																				</div>
																				<div class="col-md-12">
																					<?php echo render_select('item_table[sku][text_align]',$text_align,array('id','name'),'ib_text_align', (isset($template->item_table->sku->text_align) ? $template->item_table->sku->text_align : $default_text_alignment)); ?>
																				</div>
																			</li>
																		</ul>
																	</div>
																</div>
															</div>
											
														<div class="col-md-6">
															<div class="dropup">
																<div class="checkbox_row">
																	<div class="checkbox checkbox-primary">
																		<input type="checkbox" id="tb_image" name="item_table[image][active]" value="1" <?php echo (isset($template->item_table->image->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="tb_image_setting_btn" data-div-name="tb_image_setting" ><label for="tb_image"><?php echo _l('ib_image'); ?></label>

																		<button id="tb_image_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->item_table->image->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="tb_image_setting"><i class="fa fa-cog"></i></button>
																	</div>

																	<ul class="setting_popup hide" id="tb_image_setting">
																		<button type="button" class="close mright10" data-dismiss="tb_image_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<?php echo render_input('item_table[image][order_column]', 'ib_order_column', (isset($template->item_table->image->order_column) ? $template->item_table->image->order_column : ''), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('item_table[image][width]', 'ib_width', (isset($template->item_table->image->width) ? $template->item_table->image->width : ''), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('item_table[image][height]', 'ib_height', (isset($template->item_table->image->height) ? $template->item_table->image->height : ''), 'number', []) ?>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>
														<div class="col-md-6">
															<div class="dropup">
																<div class="checkbox_row">
																	<div class="checkbox checkbox-primary">
																		<input type="checkbox" id="tb_barcode" name="item_table[barcode][active]" value="1" <?php echo (isset($template->item_table->barcode->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="tb_barcode_setting_btn" data-div-name="tb_barcode_setting" ><label for="tb_barcode"><?php echo _l('ib_barcode'); ?></label>

																		<button id="tb_barcode_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->item_table->barcode->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="tb_barcode_setting"><i class="fa fa-cog"></i></button>
																	</div>

																	<ul class="setting_popup hide" id="tb_barcode_setting">
																		<button type="button" class="close mright10" data-dismiss="tb_barcode_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<?php echo render_input('item_table[barcode][order_column]', 'ib_order_column', (isset($template->item_table->barcode->order_column) ? $template->item_table->barcode->order_column : ''), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_color_picker('item_table[barcode][text_color]', _l('ib_text_color'), (isset($template->item_table->barcode->text_color) ? $template->item_table->barcode->text_color : $default_text_color)) ; ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('item_table[barcode][font_size]', 'ib_font_size', (isset($template->item_table->barcode->font_size) ? $template->item_table->barcode->font_size : $default_font_size), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('item_table[barcode][font_style]',$font_styles,array('id','name'),'ib_font_style', (isset($template->item_table->barcode->font_style) ? $template->item_table->barcode->font_style : $default_font_style)); ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('item_table[barcode][text_align]',$text_align,array('id','name'),'ib_text_align', (isset($template->item_table->barcode->text_align) ? $template->item_table->barcode->text_align : $default_text_alignment)); ?>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>
													<?php }else{ ?>
														<input type="hidden" name="item_table[barcode][active]" value="0">
														<input type="hidden" name="item_table[sku][active]" value="0">
														<input type="hidden" name="item_table[image][active]" value="0">
														<input type="hidden" name="item_table[barcode][order_column]" value="0">
														<input type="hidden" name="item_table[sku][order_column]" value="0">
														<input type="hidden" name="item_table[image][order_column]" value="0">
													<?php } ?>
														<?php 
														if(isset($template->item_table->custom_field) && is_object($template->item_table->custom_field) && $field_list = (array)$template->item_table->custom_field){
															foreach ($field_list as $id => $field) { 
																$field_data = $this->invoices_builder_model->get_custom_field($id);
																if($field_data){
																	$field->type = 'item_table';
																	$field->id = $id;
																	$field->field_label = $field_data->label;
																	if(!isset($field->active)){
																		$field->active = 0;
																	} 
																	?>
																	<div class="col-md-6 custom_field_item">
																		<?php $this->load->view('templates/includes/custom_field_item', (array)$field);	?>
																	</div>
																	<?php 
																}
															}
														} ?>
													</div>

													<div class="col-md-12">
														<div class="row">
															<div class="col-md-8">
																<div class="dropup">
																	<button type="button" id="item_table_add_field_btn" class="btn btn-icon btn-success mtop10" onclick="item_table_add_field(); return false;"><i class="fa fa-plus"></i><?php echo ' '._l('add_field'); ?></button>

																	<ul class="setting_popup hide" id="item_table_add_field_setting">
																		<button type="button" class="close mright10" data-dismiss="item_table_add_field_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<div class="checkbox checkbox-primary">
																					<input type="checkbox" id="select_an_available_field1" name="select_an_available_field" value="1" ><label for="select_an_available_field1"><?php echo _l('ib_select_an_available_field'); ?></label>
																				</div>
																			</div>
																			<div class="col-md-12 fr2 hide">
																				<?php
																				$list_fields = $this->invoices_builder_model->get_custom_field('','type = "item_table"', 'id, label', false, 'label');
																				echo render_select('cf_item_table_field_available', $list_fields, array('id', 'label'), 'select_field', '', []) ?>
																			</div>
																			<div class="col-md-6 fr1">
																				<?php echo render_input('cf_item_table_field_name', 'ib_field_name', '', 'text', []) ?>
																			</div>
																			<div class="col-md-6 fr1">
																				<?php echo render_input('cf_item_table_field_label', 'ib_field_label', '', 'text', []) ?>
																			</div>
																			<div class="col-md-12 fr1">
																				<?php
																				$tooltip = ' <i class="fa fa-exclamation-circle" aria-hidden="true" data-toggle="tooltip" data-title="'._l('ib_this_field_is_not_required_if_the_input_value_is_numeric').'" data-original-title="" title=""></i>';
																				echo render_input('cf_item_table_field_value', _l('ib_value').$tooltip, '', 'text', []) ?>
																			</div>
																			<div class="col-md-12 fr1 formula" data-type="item_table">
																				<?php
																				$formular_control_list = formular_control_list('item_table');
																				echo render_select('add_expression_slug', $formular_control_list, array('id', 'label'), 'ib_field_formula', '', [], [], '', 'field_formula add_expression_slug item_table') ?>
																				<?php echo render_textarea('cf_item_table_field_formula', '', '', []) ?>
																			</div>
																			<div class="col-md-12">
																				<span class="text-danger error_add_customfield hide" id="cf_item_table_error_formula"></span>
																			</div>
																			<button type="button" onclick="add_item_table_field(this); return false;" class="btn btn-sm btn-success pull-right mright10 mbot5"><?php echo _l('ib_add'); ?></button>
																		</li>
																	</ul>
																</div>
															</div>
														</div>
													</div>

												</div>
											</div>
										</div>
									</div>
								</div>

								<div class="panel panel-info invoice_total_panel" data-panel="invoice_total_panel">
									<div class="panel-heading"><?php echo _l('ib_invoice_total_information_section'); ?></div>
									<div class="panel-body">
										<div class="row">
											<div class="list_item_table_row">
												<div class="row_item">
													<div class="col-md-12">
														<p class="bold row-infor-color"><?php echo _l('ib_row_setting'); ?></p>
														<hr class="row-infor-hr">
													</div>
													<div class="col-md-6">
														<?php echo render_color_picker('invoice_total[row_color]', _l('ib_row_color'), (isset($template->invoice_total->row_color) ? $template->invoice_total->row_color : $default_background_color), ['required' => true]) ; ?>
													</div>
													<div class="col-md-6">
														<?php echo render_input('invoice_total[col_number]', 'ib_column_number_of_row', (isset($template->invoice_total->col_number) ? $template->invoice_total->col_number : ''), 'number', ['required' => true, 'max' => 4, 'min' => 1]) ?>
													</div>

													<div class="col-md-12">
														<p class="bold row-infor-color"><?php echo _l('ib_information_displayed'); ?></p>
														<hr class="row-infor-hr">
													</div>

													<div id="invoice_total_field_setting">
														<div class="col-md-6">
															<div class="dropup">
																<div class="checkbox_row">
																	<div class="checkbox checkbox-primary">
																		<input type="checkbox" id="total_infor_subtotal_label" name="invoice_total[subtotal_label][active]" value="1" <?php echo (isset($template->invoice_total->subtotal_label->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="total_infor_subtotal_label_setting_btn" data-div-name="total_infor_subtotal_label_setting" ><label for="total_infor_subtotal_label"><?php echo _l('ib_subtotal_label'); ?></label>

																		<button id="total_infor_subtotal_label_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo (isset($template->invoice_total->subtotal_label->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="total_infor_subtotal_label_setting"><i class="fa fa-cog"></i></button>
																	</div>

																	<ul class="setting_popup hide" id="total_infor_subtotal_label_setting">
																		<button type="button" class="close mright10" data-dismiss="total_infor_subtotal_label_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<?php echo render_input('invoice_total[subtotal_label][label_content]', 'ib_label_content', (isset($template->invoice_total->subtotal_label->label_content) ? $template->invoice_total->subtotal_label->label_content : ''), 'text', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('invoice_total[subtotal_label][order_column]', 'ib_order_column', (isset($template->invoice_total->subtotal_label->order_column) ? $template->invoice_total->subtotal_label->order_column : ''), 'number', $order_column_attr, [], '', 'order_column') ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_color_picker('invoice_total[subtotal_label][text_color]', _l('ib_text_color'), (isset($template->invoice_total->subtotal_label->text_color) ? $template->invoice_total->subtotal_label->text_color : $default_text_color)) ; ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('invoice_total[subtotal_label][font_size]', 'ib_font_size', (isset($template->invoice_total->subtotal_label->font_size) ? $template->invoice_total->subtotal_label->font_size : $default_font_size), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('invoice_total[subtotal_label][font_style]',$font_styles,array('id','name'),'ib_font_style', (isset($template->invoice_total->subtotal_label->font_style) ? $template->invoice_total->subtotal_label->font_style : $default_font_style)); ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('invoice_total[subtotal_label][text_align]',$text_align,array('id','name'),'ib_text_align', (isset($template->invoice_total->subtotal_label->text_align) ? $template->invoice_total->subtotal_label->text_align : $default_text_alignment)); ?>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>

														<div class="col-md-6">
															<div class="dropup">
																<div class="checkbox_row">
																	<div class="checkbox checkbox-primary">
																		<input type="checkbox" id="total_infor_subtotal_value" name="invoice_total[subtotal_value][active]" value="1" <?php echo (isset($template->invoice_total->subtotal_value->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="total_infor_subtotal_value_setting_btn" data-div-name="total_infor_subtotal_value_setting" ><label for="total_infor_subtotal_value"><?php echo _l('ib_subtotal_value'); ?></label>

																		<button id="total_infor_subtotal_value_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5 <?php echo (isset($template->invoice_total->subtotal_value->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="total_infor_subtotal_value_setting"><i class="fa fa-cog"></i></button>
																	</div>

																	<ul class="setting_popup hide" id="total_infor_subtotal_value_setting">
																		<button type="button" class="close mright10" data-dismiss="total_infor_subtotal_value_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<?php echo render_input('invoice_total[subtotal_value][order_column]', 'ib_order_column', (isset($template->invoice_total->subtotal_value->order_column) ? $template->invoice_total->subtotal_value->order_column : ''), 'number', $order_column_attr, [], '', 'order_column') ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_color_picker('invoice_total[subtotal_value][text_color]', _l('ib_text_color'), (isset($template->invoice_total->subtotal_value->text_color) ? $template->invoice_total->subtotal_value->text_color : $default_text_color)) ; ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('invoice_total[subtotal_value][font_size]', 'ib_font_size', (isset($template->invoice_total->subtotal_value->font_size) ? $template->invoice_total->subtotal_value->font_size : $default_font_size), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('invoice_total[subtotal_value][font_style]',$font_styles,array('id','name'),'ib_font_style',(isset($template->invoice_total->subtotal_value->font_style) ? $template->invoice_total->subtotal_value->font_style : $default_font_style)); ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('invoice_total[subtotal_value][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->invoice_total->subtotal_value->text_align) ? $template->invoice_total->subtotal_value->text_align : $default_text_alignment)); ?>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>

														<div class="col-md-6">
															<div class="dropup">
																<div class="checkbox_row">
																	<div class="checkbox checkbox-primary">
																		<input type="checkbox" id="total_infor_taxes_label" name="invoice_total[taxes_label][active]" value="1" <?php echo (isset($template->invoice_total->taxes_label->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="total_infor_taxes_label_setting_btn" data-div-name="total_infor_taxes_label_setting" ><label for="total_infor_taxes_label"><?php echo _l('ib_taxes_label'); ?></label>

																		<button id="total_infor_taxes_label_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5 <?php echo (isset($template->invoice_total->taxes_label->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="total_infor_taxes_label_setting"><i class="fa fa-cog"></i></button>
																	</div>

																	<ul class="setting_popup hide" id="total_infor_taxes_label_setting">
																		<button type="button" class="close mright10" data-dismiss="total_infor_taxes_label_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<?php echo render_input('invoice_total[taxes_label][label_content]', 'ib_label_content', (isset($template->invoice_total->taxes_label->label_content) ? $template->invoice_total->taxes_label->label_content : ''), 'text', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('invoice_total[taxes_label][order_column]', 'ib_order_column', (isset($template->invoice_total->taxes_label->order_column) ? $template->invoice_total->taxes_label->order_column : ''), 'number', $order_column_attr, [], '', 'order_column') ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_color_picker('invoice_total[taxes_label][text_color]', _l('ib_text_color'), (isset($template->invoice_total->taxes_label->text_color) ? $template->invoice_total->taxes_label->text_color : $default_text_color)) ; ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('invoice_total[taxes_label][font_size]', 'ib_font_size', (isset($template->invoice_total->taxes_label->font_size) ? $template->invoice_total->taxes_label->font_size : $default_font_size), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('invoice_total[taxes_label][font_style]',$font_styles,array('id','name'),'ib_font_style',(isset($template->invoice_total->taxes_label->font_style) ? $template->invoice_total->taxes_label->font_style : $default_font_style)); ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('invoice_total[taxes_label][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->invoice_total->taxes_label->text_align) ? $template->invoice_total->taxes_label->text_align : $default_text_alignment)); ?>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>

														<div class="col-md-6">
															<div class="dropup">
																<div class="checkbox_row">
																	<div class="checkbox checkbox-primary">
																		<input type="checkbox" id="total_infor_taxes_value" name="invoice_total[taxes_value][active]" value="1" <?php echo (isset($template->invoice_total->taxes_value->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="total_infor_taxes_value_setting_btn" data-div-name="total_infor_taxes_value_setting" ><label for="total_infor_taxes_value"><?php echo _l('ib_taxes_value'); ?></label>

																		<button id="total_infor_taxes_value_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5 <?php echo (isset($template->invoice_total->taxes_value->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="total_infor_taxes_value_setting"><i class="fa fa-cog"></i></button>
																	</div>

																	<ul class="setting_popup hide" id="total_infor_taxes_value_setting">
																		<button type="button" class="close mright10" data-dismiss="total_infor_taxes_value_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<?php echo render_input('invoice_total[taxes_value][order_column]', 'ib_order_column', (isset($template->invoice_total->taxes_value->order_column) ? $template->invoice_total->taxes_value->order_column : ''), 'number', $order_column_attr, [], '', 'order_column') ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_color_picker('invoice_total[taxes_value][text_color]', _l('ib_text_color'), (isset($template->invoice_total->taxes_value->text_color) ? $template->invoice_total->taxes_value->text_color : $default_text_color)) ; ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('invoice_total[taxes_value][font_size]', 'ib_font_size', (isset($template->invoice_total->taxes_value->font_size) ? $template->invoice_total->taxes_value->font_size : $default_font_size), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('invoice_total[taxes_value][font_style]',$font_styles,array('id','name'),'ib_font_style',(isset($template->invoice_total->taxes_value->font_style) ? $template->invoice_total->taxes_value->font_style : $default_font_style)); ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('invoice_total[taxes_value][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->invoice_total->taxes_value->text_align) ? $template->invoice_total->taxes_value->text_align : $default_text_alignment)); ?>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>

														<div class="col-md-6">
															<div class="dropup">
																<div class="checkbox_row">
																	<div class="checkbox checkbox-primary">
																		<input type="checkbox" id="total_infor_total_tax_label" name="invoice_total[total_tax_label][active]" value="1" <?php echo (isset($template->invoice_total->total_tax_label->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="total_infor_total_tax_label_setting" data-div-name="total_infor_total_tax_label_setting" ><label for="total_infor_total_tax_label"><?php echo _l('ib_total_tax_label'); ?></label>

																		<button id="total_infor_total_tax_label_setting" type="button" class="btn btn-icon-ib btn-success mleft5 <?php echo (isset($template->invoice_total->total_tax_label->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="total_infor_total_tax_label_setting"><i class="fa fa-cog"></i></button>
																	</div>

																	<ul class="setting_popup hide" id="total_infor_total_tax_label_setting">
																		<button type="button" class="close mright10" data-dismiss="total_infor_total_tax_label_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<?php echo render_input('invoice_total[total_tax_label][label_content]', 'ib_label_content', (isset($template->invoice_total->total_tax_label->label_content) ? $template->invoice_total->total_tax_label->label_content : ''), 'text', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('invoice_total[total_tax_label][order_column]', 'ib_order_column', (isset($template->invoice_total->total_tax_label->order_column) ? $template->invoice_total->total_tax_label->order_column : ''), 'number', $order_column_attr, [], '', 'order_column') ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_color_picker('invoice_total[total_tax_label][text_color]', _l('ib_text_color'), (isset($template->invoice_total->total_tax_label->text_color) ? $template->invoice_total->total_tax_label->text_color : $default_text_color)) ; ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('invoice_total[total_tax_label][font_size]', 'ib_font_size', (isset($template->invoice_total->total_tax_label->font_size) ? $template->invoice_total->total_tax_label->font_size : $default_font_size), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('invoice_total[total_tax_label][font_style]',$font_styles,array('id','name'),'ib_font_style',(isset($template->invoice_total->total_tax_label->font_style) ? $template->invoice_total->total_tax_label->font_style : $default_font_style)); ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('invoice_total[total_tax_label][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->invoice_total->total_tax_label->text_align) ? $template->invoice_total->total_tax_label->text_align : $default_text_alignment)); ?>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>

														<div class="col-md-6">
															<div class="dropup">
																<div class="checkbox_row">
																	<div class="checkbox checkbox-primary">
																		<input type="checkbox" id="total_infor_total_tax_value" name="invoice_total[total_tax_value][active]" value="1" <?php echo (isset($template->invoice_total->total_tax_value->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="total_infor_total_tax_value_setting_btn" data-div-name="total_infor_total_tax_value_setting" ><label for="total_infor_total_tax_value"><?php echo _l('ib_total_tax_value'); ?></label>

																		<button id="total_infor_total_tax_value_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5 <?php echo (isset($template->invoice_total->total_tax_value->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="total_infor_total_tax_value_setting"><i class="fa fa-cog"></i></button>
																	</div>

																	<ul class="setting_popup hide" id="total_infor_total_tax_value_setting">
																		<button type="button" class="close mright10" data-dismiss="total_infor_total_tax_value_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<?php echo render_input('invoice_total[total_tax_value][order_column]', 'ib_order_column', (isset($template->invoice_total->total_tax_value->order_column) ? $template->invoice_total->total_tax_value->order_column : ''), 'number', $order_column_attr, [], '', 'order_column') ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_color_picker('invoice_total[total_tax_value][text_color]', _l('ib_text_color'), (isset($template->invoice_total->total_tax_value->text_color) ? $template->invoice_total->total_tax_value->text_color : $default_text_color)) ; ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('invoice_total[total_tax_value][font_size]', 'ib_font_size', (isset($template->invoice_total->total_tax_value->font_size) ? $template->invoice_total->total_tax_value->font_size : $default_font_size), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('invoice_total[total_tax_value][font_style]',$font_styles,array('id','name'),'ib_font_style',(isset($template->invoice_total->total_tax_value->font_style) ? $template->invoice_total->total_tax_value->font_style : $default_font_style)); ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('invoice_total[total_tax_value][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->invoice_total->total_tax_value->text_align) ? $template->invoice_total->total_tax_value->text_align : $default_text_alignment)); ?>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>

														<div class="col-md-6">
															<div class="dropup">
																<div class="checkbox_row">
																	<div class="checkbox checkbox-primary">
																		<input type="checkbox" id="total_infor_discount_label" name="invoice_total[discount_label][active]" value="1" <?php echo (isset($template->invoice_total->discount_label->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="total_infor_discount_label_setting_btn" data-div-name="total_infor_discount_label_setting" ><label for="total_infor_discount_label"><?php echo _l('ib_discount_label'); ?></label>

																		<button id="total_infor_discount_label_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5 <?php echo (isset($template->invoice_total->discount_label->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="total_infor_discount_label_setting"><i class="fa fa-cog"></i></button>
																	</div>

																	<ul class="setting_popup hide" id="total_infor_discount_label_setting">
																		<button type="button" class="close mright10" data-dismiss="total_infor_discount_label_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<?php echo render_input('invoice_total[discount_label][label_content]', 'ib_label_content', (isset($template->invoice_total->discount_label->label_content) ? $template->invoice_total->discount_label->label_content : ''), 'text', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('invoice_total[discount_label][order_column]', 'ib_order_column', (isset($template->invoice_total->discount_label->order_column) ? $template->invoice_total->discount_label->order_column : ''), 'number', $order_column_attr, [], '', 'order_column') ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_color_picker('invoice_total[discount_label][text_color]', _l('ib_text_color'), (isset($template->invoice_total->discount_label->text_color) ? $template->invoice_total->discount_label->text_color : $default_text_color)) ; ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('invoice_total[discount_label][font_size]', 'ib_font_size', (isset($template->invoice_total->discount_label->font_size) ? $template->invoice_total->discount_label->font_size : $default_font_size), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('invoice_total[discount_label][font_style]',$font_styles,array('id','name'),'ib_font_style',(isset($template->invoice_total->discount_label->font_style) ? $template->invoice_total->discount_label->font_style : $default_font_style)); ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('invoice_total[discount_label][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->invoice_total->discount_label->text_align) ? $template->invoice_total->discount_label->text_align : $default_text_alignment)); ?>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>

														<div class="col-md-6">
															<div class="dropup">
																<div class="checkbox_row">
																	<div class="checkbox checkbox-primary">
																		<input type="checkbox" id="total_infor_discount_value" name="invoice_total[discount_value][active]" value="1" <?php echo (isset($template->invoice_total->discount_value->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="total_infor_discount_value_setting_btn" data-div-name="total_infor_discount_value_setting" ><label for="total_infor_discount_value"><?php echo _l('ib_discount_value'); ?></label>

																		<button id="total_infor_discount_value_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5 <?php echo (isset($template->invoice_total->discount_value->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="total_infor_discount_value_setting"><i class="fa fa-cog"></i></button>
																	</div>

																	<ul class="setting_popup hide" id="total_infor_discount_value_setting">
																		<button type="button" class="close mright10" data-dismiss="total_infor_discount_value_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<?php echo render_input('invoice_total[discount_value][order_column]', 'ib_order_column', (isset($template->invoice_total->discount_value->order_column) ? $template->invoice_total->discount_value->order_column : ''), 'number', $order_column_attr, [], '', 'order_column') ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_color_picker('invoice_total[discount_value][text_color]', _l('ib_text_color'), (isset($template->invoice_total->discount_value->text_color) ? $template->invoice_total->discount_value->text_color : $default_text_color)) ; ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('invoice_total[discount_value][font_size]', 'ib_font_size', (isset($template->invoice_total->discount_value->font_size) ? $template->invoice_total->discount_value->font_size : $default_font_size), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('invoice_total[discount_value][font_style]',$font_styles,array('id','name'),'ib_font_style',(isset($template->invoice_total->discount_value->font_style) ? $template->invoice_total->discount_value->font_style : $default_font_style)); ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('invoice_total[discount_value][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->invoice_total->discount_value->text_align) ? $template->invoice_total->discount_value->text_align : $default_text_alignment)); ?>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>

														<div class="col-md-6">
															<div class="dropup">
																<div class="checkbox_row">
																	<div class="checkbox checkbox-primary">
																		<input type="checkbox" id="total_infor_grand_total_label" name="invoice_total[grand_total_label][active]" value="1" <?php echo (isset($template->invoice_total->grand_total_label->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="total_infor_grand_total_label_setting_btn" data-div-name="total_infor_grand_total_label_setting" ><label for="total_infor_grand_total_label"><?php echo _l('ib_grand_total_label'); ?></label>

																		<button id="total_infor_grand_total_label_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5 <?php echo (isset($template->invoice_total->grand_total_label->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="total_infor_grand_total_label_setting"><i class="fa fa-cog"></i></button>
																	</div>

																	<ul class="setting_popup hide" id="total_infor_grand_total_label_setting">
																		<button type="button" class="close mright10" data-dismiss="total_infor_grand_total_label_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<?php echo render_input('invoice_total[grand_total_label][label_content]', 'ib_label_content', (isset($template->invoice_total->grand_total_label->label_content) ? $template->invoice_total->grand_total_label->label_content : ''), 'text', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('invoice_total[grand_total_label][order_column]', 'ib_order_column', (isset($template->invoice_total->grand_total_label->order_column) ? $template->invoice_total->grand_total_label->order_column : ''), 'number', $order_column_attr, [], '', 'order_column') ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_color_picker('invoice_total[grand_total_label][text_color]', _l('ib_text_color'), (isset($template->invoice_total->grand_total_label->text_color) ? $template->invoice_total->grand_total_label->text_color : $default_text_color)) ; ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('invoice_total[grand_total_label][font_size]', 'ib_font_size', (isset($template->invoice_total->grand_total_label->font_size) ? $template->invoice_total->grand_total_label->font_size : $default_font_size), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('invoice_total[grand_total_label][font_style]',$font_styles,array('id','name'),'ib_font_style',(isset($template->invoice_total->grand_total_label->font_style) ? $template->invoice_total->grand_total_label->font_style : $default_font_style)); ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('invoice_total[grand_total_label][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->invoice_total->grand_total_label->text_align) ? $template->invoice_total->grand_total_label->text_align : $default_text_alignment)); ?>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>

														<div class="col-md-6">
															<div class="dropup">
																<div class="checkbox_row">
																	<div class="checkbox checkbox-primary">
																		<input type="checkbox" id="total_infor_grand_total_value" name="invoice_total[grand_total_value][active]" value="1" <?php echo (isset($template->invoice_total->grand_total_value->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="total_infor_grand_total_value_setting_btn" data-div-name="total_infor_grand_total_value_setting" ><label for="total_infor_grand_total_value"><?php echo _l('ib_grand_total_value'); ?></label>

																		<button id="total_infor_grand_total_value_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5 <?php echo (isset($template->invoice_total->grand_total_value->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="total_infor_grand_total_value_setting"><i class="fa fa-cog"></i></button>
																	</div>

																	<ul class="setting_popup hide" id="total_infor_grand_total_value_setting">
																		<button type="button" class="close mright10" data-dismiss="total_infor_grand_total_value_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<?php echo render_input('invoice_total[grand_total_value][order_column]', 'ib_order_column', (isset($template->invoice_total->grand_total_value->order_column) ? $template->invoice_total->grand_total_value->order_column : ''), 'number', $order_column_attr, [], '', 'order_column') ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_color_picker('invoice_total[grand_total_value][text_color]', _l('ib_text_color'), (isset($template->invoice_total->grand_total_value->text_color) ? $template->invoice_total->grand_total_value->text_color : $default_text_color)) ; ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('invoice_total[grand_total_value][font_size]', 'ib_font_size', (isset($template->invoice_total->grand_total_value->font_size) ? $template->invoice_total->grand_total_value->font_size : $default_font_size), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('invoice_total[grand_total_value][font_style]',$font_styles,array('id','name'),'ib_font_style',(isset($template->invoice_total->grand_total_value->font_style) ? $template->invoice_total->grand_total_value->font_style : $default_font_style)); ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('invoice_total[grand_total_value][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->invoice_total->grand_total_value->text_align) ? $template->invoice_total->grand_total_value->text_align : $default_text_alignment)); ?>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>
														<div class="col-md-6">
															<div class="dropup">
																<div class="checkbox_row">
																	<div class="checkbox checkbox-primary">
																		<input type="checkbox" id="total_infor_payment_amount_label" name="invoice_total[payment_amount_label][active]" value="1" <?php echo (isset($template->invoice_total->payment_amount_label->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="total_infor_payment_amount_label_setting_btn" data-div-name="total_infor_payment_amount_label_setting" ><label for="total_infor_payment_amount_label"><?php echo _l('ib_payment_amount_label'); ?></label>

																		<button id="total_infor_payment_amount_label_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5 <?php echo (isset($template->invoice_total->payment_amount_label->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="total_infor_payment_amount_label_setting"><i class="fa fa-cog"></i></button>
																	</div>

																	<ul class="setting_popup hide" id="total_infor_payment_amount_label_setting">
																		<button type="button" class="close mright10" data-dismiss="total_infor_payment_amount_label_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<?php echo render_input('invoice_total[payment_amount_label][label_content]', 'ib_label_content', (isset($template->invoice_total->payment_amount_label->label_content) ? $template->invoice_total->payment_amount_label->label_content : ''), 'text', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('invoice_total[payment_amount_label][order_column]', 'ib_order_column', (isset($template->invoice_total->payment_amount_label->order_column) ? $template->invoice_total->payment_amount_label->order_column : ''), 'number', $order_column_attr, [], '', 'order_column') ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_color_picker('invoice_total[payment_amount_label][text_color]', _l('ib_text_color'), (isset($template->invoice_total->payment_amount_label->text_color) ? $template->invoice_total->payment_amount_label->text_color : $default_text_color)) ; ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('invoice_total[payment_amount_label][font_size]', 'ib_font_size', (isset($template->invoice_total->payment_amount_label->font_size) ? $template->invoice_total->payment_amount_label->font_size : $default_font_size), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('invoice_total[payment_amount_label][font_style]',$font_styles,array('id','name'),'ib_font_style',(isset($template->invoice_total->payment_amount_label->font_style) ? $template->invoice_total->payment_amount_label->font_style : $default_font_style)); ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('invoice_total[payment_amount_label][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->invoice_total->payment_amount_label->text_align) ? $template->invoice_total->payment_amount_label->text_align : $default_text_alignment)); ?>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>

														<div class="col-md-6">
															<div class="dropup">
																<div class="checkbox_row">
																	<div class="checkbox checkbox-primary">
																		<input type="checkbox" id="total_infor_payment_amount_value" name="invoice_total[payment_amount_value][active]" value="1" <?php echo (isset($template->invoice_total->payment_amount_value->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="total_infor_payment_amount_value_setting_btn" data-div-name="total_infor_payment_amount_value_setting" ><label for="total_infor_payment_amount_value"><?php echo _l('ib_payment_amount_value'); ?></label>

																		<button id="total_infor_payment_amount_value_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5 <?php echo (isset($template->invoice_total->payment_amount_value->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="total_infor_payment_amount_value_setting"><i class="fa fa-cog"></i></button>
																	</div>

																	<ul class="setting_popup hide" id="total_infor_payment_amount_value_setting">
																		<button type="button" class="close mright10" data-dismiss="total_infor_payment_amount_value_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<?php echo render_input('invoice_total[payment_amount_value][order_column]', 'ib_order_column', (isset($template->invoice_total->payment_amount_value->order_column) ? $template->invoice_total->payment_amount_value->order_column : ''), 'number', $order_column_attr, [], '', 'order_column') ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_color_picker('invoice_total[payment_amount_value][text_color]', _l('ib_text_color'), (isset($template->invoice_total->payment_amount_value->text_color) ? $template->invoice_total->payment_amount_value->text_color : $default_text_color)) ; ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('invoice_total[payment_amount_value][font_size]', 'ib_font_size', (isset($template->invoice_total->payment_amount_value->font_size) ? $template->invoice_total->payment_amount_value->font_size : $default_font_size), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('invoice_total[payment_amount_value][font_style]',$font_styles,array('id','name'),'ib_font_style',(isset($template->invoice_total->payment_amount_value->font_style) ? $template->invoice_total->payment_amount_value->font_style : $default_font_style)); ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('invoice_total[payment_amount_value][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->invoice_total->payment_amount_value->text_align) ? $template->invoice_total->payment_amount_value->text_align : $default_text_alignment)); ?>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>

														<?php 
														if(isset($template->invoice_total->custom_field) && is_object($template->invoice_total->custom_field) && $field_list = (array)$template->invoice_total->custom_field){
															foreach ($field_list as $id => $field) { 
																$field_data = $this->invoices_builder_model->get_custom_field($id);
																if($field_data){
																	$field->type = 'invoice_total';
																	$field->id = $id;
																	$field->field_label = $field_data->label;
																	if(!isset($field->active)){
																		$field->active = 0;
																	}											
																	?>
																	<div class="col-md-6 custom_field_item">
																		<?php $this->load->view('templates/includes/custom_field_item', (array)$field);	?>
																	</div>
																	<?php 
																}
															}
														} ?>

													</div>

													<div class="col-md-12">
														<div class="row">
															<div class="col-md-8">
																<div class="dropup">
																	<button type="button" id="invoice_total_add_field_btn" class="btn btn-icon btn-success mtop10" onclick="invoice_total_add_field(); return false;"><i class="fa fa-plus"></i><?php echo ' '._l('add_field'); ?></button>

																	<ul class="setting_popup hide" id="invoice_total_add_field_setting">
																		<button type="button" class="close mright10" data-dismiss="invoice_total_add_field_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<div class="checkbox checkbox-primary">
																					<input type="checkbox" id="select_an_available_field4" name="select_an_available_field" value="1" ><label for="select_an_available_field4"><?php echo _l('ib_select_an_available_field'); ?></label>
																				</div>
																			</div>
																			<div class="col-md-12 fr2 hide">
																				<?php
																				$list_fields = $this->invoices_builder_model->get_custom_field('','type = "invoice_total"', 'id, label', false, 'label');
																				echo render_select('cf_invoice_field_available', $list_fields, array('id', 'label'), 'select_field', '', []) ?>
																			</div>
																			<div class="col-md-6 fr1">
																				<?php echo render_input('cf_invoice_total_field_name', 'ib_field_name', '', 'text', []) ?>
																			</div>
																			<div class="col-md-6 fr1">
																				<?php echo render_input('cf_invoice_total_field_label', 'ib_field_label', '', 'text', []) ?>
																			</div>
																			<div class="col-md-12 fr1">
																				<?php
																				$tooltip = ' <i class="fa fa-exclamation-circle" aria-hidden="true" data-toggle="tooltip" data-title="'._l('ib_this_field_is_not_required_if_the_input_value_is_numeric').'" data-original-title="" title=""></i>';
																				echo render_input('cf_invoice_total_field_value', _l('ib_value').$tooltip, '', 'text', []) ?>
																			</div>
																			<div class="col-md-12 fr1 formula" data-type="invoice_total">
																				<?php
																				$formular_control_list = formular_control_list('invoice_total');
																				echo render_select('add_expression_slug', $formular_control_list, array('id', 'label'), 'ib_field_formula', '', [], [], '', 'field_formula add_expression_slug invoice_total') ?>
																				<?php echo render_textarea('cf_invoice_total_field_formula', '', '', []) ?>
																			</div>
																			<div class="col-md-12">
																				<span class="text-danger error_add_customfield hide" id="cf_invoice_total_error_formula"></span>
																			</div>
																			<button type="button" onclick="add_invoice_total_field(this); return false;" class="btn btn-sm btn-success pull-right mright10 mbot5"><?php echo _l('ib_add'); ?></button>
																		</li>
																	</ul>
																</div>
															</div>
														</div>
													</div>

												</div>
											</div>
										</div>
									</div>
								</div>

								<div class="panel panel-info bottom_information_panel" data-panel="bottom_information_panel">
									<div class="panel-heading"><?php echo _l('ib_bottom_information_section'); ?></div>
									<div class="panel-body">
										<div class="row">
											<div class="list_item_table_row">
												<div class="row_item">
													<div class="col-md-12">
														<p class="bold row-infor-color"><?php echo _l('ib_row_setting'); ?></p>
														<hr class="row-infor-hr">
													</div>
													<div class="col-md-6">
														<?php echo render_color_picker('bottom_information[row_color]', _l('ib_row_color'), (isset($template->bottom_information->row_color) ? $template->bottom_information->row_color : $default_background_color), ['required' => true]) ; ?>
													</div>
													<div class="col-md-6">
														<?php echo render_input('bottom_information[col_number]', 'ib_column_number_of_row', (isset($template->bottom_information->col_number) ? $template->bottom_information->col_number : ''), 'number', ['required' => true, 'max' => 4, 'min' => 1]) ?>
													</div>
													<div class="col-md-12">
														<p class="bold row-infor-color"><?php echo _l('ib_information_displayed'); ?></p>
														<hr class="row-infor-hr">
													</div>

													<div id="bottom_field_setting">

														<div class="col-md-4">
															<div class="dropup">
																<div class="checkbox_row">
																	<div class="checkbox checkbox-primary">
																		<input type="checkbox" id="bottom_payment_method" name="bottom_information[payment_method][active]" value="1" <?php echo (isset($template->bottom_information->payment_method->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="bottom_payment_method_setting_btn" data-div-name="bottom_payment_method_setting" ><label for="bottom_payment_method"><?php echo _l('ib_payment_method'); ?></label>

																		<button id="bottom_payment_method_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5 <?php echo (isset($template->bottom_information->payment_method->active) ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="bottom_payment_method_setting"><i class="fa fa-cog"></i></button>
																	</div>

																	<ul class="setting_popup hide" id="bottom_payment_method_setting">
																		<button type="button" class="close mright10" data-dismiss="bottom_payment_method_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<?php echo render_input('bottom_information[payment_method][label_content]', 'ib_label_content', (isset($template->bottom_information->payment_method->label_content) ? $template->bottom_information->payment_method->label_content : ''), 'text', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('bottom_information[payment_method][order_column]', 'ib_order_column', (isset($template->bottom_information->payment_method->order_column) ? $template->bottom_information->payment_method->order_column : ''), 'number', $order_column_attr, [], '', 'order_column') ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_color_picker('bottom_information[payment_method][text_color]', _l('ib_text_color'), (isset($template->bottom_information->payment_method->text_color) ? $template->bottom_information->payment_method->text_color : $default_text_color)) ; ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('bottom_information[payment_method][font_size]', 'ib_font_size', (isset($template->bottom_information->payment_method->font_size) ? $template->bottom_information->payment_method->font_size : $default_font_size), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('bottom_information[payment_method][font_style]',$font_styles,array('id','name'),'ib_font_style',(isset($template->bottom_information->payment_method->font_style) ? $template->bottom_information->payment_method->font_style : $default_font_style)); ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('bottom_information[payment_method][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->bottom_information->payment_method->text_align) ? $template->bottom_information->payment_method->text_align : $default_text_alignment)); ?>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>

														<div class="col-md-4">
															<div class="dropup">
																<div class="checkbox_row">
																	<div class="checkbox checkbox-primary">
																		<input type="checkbox" id="bottom_bank_infor" name="bottom_information[bank_infor][active]" value="1" <?php echo (isset($template->bottom_information->bank_infor->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="bottom_bank_infor_setting_btn" data-div-name="bottom_bank_infor_setting" ><label for="bottom_bank_infor"><?php echo _l('ib_bank_infor'); ?></label>

																		<button id="bottom_bank_infor_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5 <?php echo (isset($template->bottom_information->bank_infor->active) ? '' : ' hide') ?>" onclick="edit_setting(this);"  data-div-name="bottom_bank_infor_setting"><i class="fa fa-cog"></i></button>
																	</div>

																	<ul class="setting_popup hide" id="bottom_bank_infor_setting">
																		<button type="button" class="close mright10" data-dismiss="bottom_bank_infor_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<?php echo render_input('bottom_information[bank_infor][label_content]', 'ib_label_content', (isset($template->bottom_information->bank_infor->label_content) ? $template->bottom_information->bank_infor->label_content : ''), 'text', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('bottom_information[bank_infor][order_column]', 'ib_order_column', (isset($template->bottom_information->bank_infor->order_column) ? $template->bottom_information->bank_infor->order_column : ''), 'number', $order_column_attr, [], '', 'order_column') ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_color_picker('bottom_information[bank_infor][text_color]', _l('ib_text_color'), (isset($template->bottom_information->bank_infor->text_color) ? $template->bottom_information->bank_infor->text_color : $default_text_color)) ; ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('bottom_information[bank_infor][font_size]', 'ib_font_size', (isset($template->bottom_information->bank_infor->font_size) ? $template->bottom_information->bank_infor->font_size : $default_font_size), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('bottom_information[bank_infor][font_style]',$font_styles,array('id','name'),'ib_font_style',(isset($template->bottom_information->bank_infor->font_style) ? $template->bottom_information->bank_infor->font_style : $default_font_style)); ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('bottom_information[bank_infor][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->bottom_information->bank_infor->text_align) ? $template->bottom_information->bank_infor->text_align : $default_text_alignment)); ?>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>

														<div class="col-md-4">
															<div class="dropup">
																<div class="checkbox_row">
																	<div class="checkbox checkbox-primary">
																		<input type="checkbox" id="bottom_signature" name="bottom_information[signature][active]" value="1" <?php echo (isset($template->bottom_information->signature->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="bottom_signature_setting_btn" data-div-name="bottom_signature_setting" ><label for="bottom_signature"><?php echo _l('ib_signature'); ?></label>

																		<button id="bottom_signature_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5 <?php echo (isset($template->bottom_information->signature->active) ? '' : ' hide') ?>" onclick="edit_setting(this);"  data-div-name="bottom_signature_setting"><i class="fa fa-cog"></i></button>
																	</div>

																	<ul class="setting_popup hide" id="bottom_signature_setting">
																		<button type="button" class="close mright10" data-dismiss="bottom_signature_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<?php echo render_input('bottom_information[signature][label_content]', 'ib_label_content', (isset($template->bottom_information->signature->label_content) ? $template->bottom_information->signature->label_content : ''), 'text', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('bottom_information[signature][order_column]', 'ib_order_column', (isset($template->bottom_information->signature->order_column) ? $template->bottom_information->signature->order_column : ''), 'number', $order_column_attr, [], '', 'order_column') ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('bottom_information[signature][width]', 'ib_width', (isset($template->bottom_information->signature->width) ? $template->bottom_information->signature->width : ''), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('bottom_information[signature][height]', 'ib_height', (isset($template->bottom_information->signature->height) ? $template->bottom_information->signature->height : ''), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('bottom_information[signature][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->bottom_information->signature->text_align) ? $template->bottom_information->signature->text_align : $default_text_alignment)); ?>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>

														<div class="col-md-4">
															<div class="dropup">
																<div class="checkbox_row">
																	<div class="checkbox checkbox-primary">
																		<input type="checkbox" id="bottom_qrcode" name="bottom_information[qrcode][active]" value="1" <?php echo (isset($template->bottom_information->qrcode->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="bottom_qrcode_setting_btn" data-div-name="bottom_qrcode_setting" ><label for="bottom_qrcode"><?php echo _l('ib_qr_code'); ?></label>

																		<button id="bottom_qrcode_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5 <?php echo (isset($template->bottom_information->qrcode->active) ? '' : ' hide') ?>" onclick="edit_setting(this);"  data-div-name="bottom_qrcode_setting"><i class="fa fa-cog"></i></button>
																	</div>

																	<ul class="setting_popup hide" id="bottom_qrcode_setting">
																		<button type="button" class="close mright10" data-dismiss="bottom_qrcode_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<?php echo render_input('bottom_information[qrcode][label_content]', 'ib_label_content', (isset($template->bottom_information->qrcode->label_content) ? $template->bottom_information->qrcode->label_content : ''), 'text', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('bottom_information[qrcode][order_column]', 'ib_order_column', (isset($template->bottom_information->qrcode->order_column) ? $template->bottom_information->qrcode->order_column : ''), 'number', $order_column_attr, [], '', 'order_column') ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('bottom_information[qrcode][width]', 'ib_width', (isset($template->bottom_information->qrcode->width) ? $template->bottom_information->qrcode->width : ''), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('bottom_information[qrcode][height]', 'ib_height', (isset($template->bottom_information->qrcode->height) ? $template->bottom_information->qrcode->height : ''), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('bottom_information[qrcode][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->bottom_information->qrcode->text_align) ? $template->bottom_information->qrcode->text_align : $default_text_alignment)); ?>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>

														<div class="col-md-4">
															<div class="dropup">
																<div class="checkbox_row">
																	<div class="checkbox checkbox-primary">
																		<input type="checkbox" id="bottom_messages" name="bottom_information[messages][active]" value="1" <?php echo (isset($template->bottom_information->messages->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="bottom_messages_setting_btn" data-div-name="bottom_messages_setting" ><label for="bottom_messages"><?php echo _l('ib_messages'); ?></label>

																		<button id="bottom_messages_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5 <?php echo (isset($template->bottom_information->messages->active) ? '' : ' hide') ?>" onclick="edit_setting(this);"  data-div-name="bottom_messages_setting"><i class="fa fa-cog"></i></button>
																	</div>

																	<ul class="setting_popup hide" id="bottom_messages_setting">
																		<button type="button" class="close mright10" data-dismiss="bottom_messages_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<?php echo render_input('bottom_information[messages][content]', 'ib_content', (isset($template->bottom_information->messages->content) ? $template->bottom_information->messages->content : ''), 'text', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('bottom_information[messages][order_column]', 'ib_order_column', (isset($template->bottom_information->messages->order_column) ? $template->bottom_information->messages->order_column : ''), 'number', $order_column_attr, [], '', 'order_column') ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_color_picker('bottom_information[messages][text_color]', _l('ib_text_color'), (isset($template->bottom_information->messages->text_color) ? $template->bottom_information->messages->text_color : $default_text_color)) ; ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('bottom_information[messages][font_size]', 'ib_font_size', (isset($template->bottom_information->messages->font_size) ? $template->bottom_information->messages->font_size : $default_font_size), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('bottom_information[messages][font_style]',$font_styles,array('id','name'),'ib_font_style',(isset($template->bottom_information->messages->font_style) ? $template->bottom_information->messages->font_style : $default_font_style)); ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('bottom_information[messages][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->bottom_information->messages->text_align) ? $template->bottom_information->messages->text_align : $default_text_alignment)); ?>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>

														<div class="col-md-4">
															<div class="dropup">
																<div class="checkbox_row">
																	<div class="checkbox checkbox-primary">
																		<input type="checkbox" id="bottom_delivery_note" name="bottom_information[delivery_note][active]" value="1" <?php echo (isset($template->bottom_information->delivery_note->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="bottom_delivery_note_setting_btn" data-div-name="bottom_delivery_note_setting" ><label for="bottom_delivery_note"><?php echo _l('ib_delivery_note'); ?></label>

																		<button id="bottom_delivery_note_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5 <?php echo (isset($template->bottom_information->delivery_note->active) ? '' : ' hide') ?>" onclick="edit_setting(this);"  data-div-name="bottom_delivery_note_setting"><i class="fa fa-cog"></i></button>
																	</div>

																	<ul class="setting_popup hide" id="bottom_delivery_note_setting">
																		<button type="button" class="close mright10" data-dismiss="bottom_delivery_note_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<?php echo render_input('bottom_information[delivery_note][content]', 'ib_content', (isset($template->bottom_information->delivery_note->content) ? $template->bottom_information->delivery_note->content : ''), 'text', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('bottom_information[delivery_note][order_column]', 'ib_order_column', (isset($template->bottom_information->delivery_note->order_column) ? $template->bottom_information->delivery_note->order_column : ''), 'number', $order_column_attr, [], '', 'order_column') ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_color_picker('bottom_information[delivery_note][text_color]', _l('ib_text_color'), (isset($template->bottom_information->delivery_note->text_color) ? $template->bottom_information->delivery_note->text_color : $default_text_color)) ; ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('bottom_information[delivery_note][font_size]', 'ib_font_size', (isset($template->bottom_information->delivery_note->font_size) ? $template->bottom_information->delivery_note->font_size : $default_font_size), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('bottom_information[delivery_note][font_style]',$font_styles,array('id','name'),'ib_font_style',(isset($template->bottom_information->delivery_note->font_style) ? $template->bottom_information->delivery_note->font_style : $default_font_style)); ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('bottom_information[delivery_note][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->bottom_information->delivery_note->text_align) ? $template->bottom_information->delivery_note->text_align : $default_text_alignment)); ?>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>

														<div class="col-md-4">
															<div class="dropup">
																<div class="checkbox_row">
																	<div class="checkbox checkbox-primary">
																		<input type="checkbox" id="bottom_client_note" name="bottom_information[client_note][active]" value="1" <?php echo (isset($template->bottom_information->client_note->active) ? 'checked' : '') ?> onclick="column_setting(this);" data-btn-name="bottom_client_note_setting_btn" data-div-name="bottom_client_note_setting" ><label for="bottom_client_note"><?php echo _l('ib_client_note'); ?></label>

																		<button id="bottom_client_note_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5 <?php echo (isset($template->bottom_information->client_note->active) ? '' : ' hide') ?>" onclick="edit_setting(this);"  data-div-name="bottom_client_note_setting"><i class="fa fa-cog"></i></button>
																	</div>

																	<ul class="setting_popup hide" id="bottom_client_note_setting">
																		<button type="button" class="close mright10" data-dismiss="bottom_client_note_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<?php echo render_input('bottom_information[client_note][content]', 'ib_content', (isset($template->bottom_information->client_note->content) ? $template->bottom_information->client_note->content : ''), 'text', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('bottom_information[client_note][order_column]', 'ib_order_column', (isset($template->bottom_information->client_note->order_column) ? $template->bottom_information->client_note->order_column : ''), 'number', $order_column_attr, [], '', 'order_column') ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_color_picker('bottom_information[client_note][text_color]', _l('ib_text_color'), (isset($template->bottom_information->client_note->text_color) ? $template->bottom_information->client_note->text_color : $default_text_color)) ; ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('bottom_information[client_note][font_size]', 'ib_font_size', (isset($template->bottom_information->client_note->font_size) ? $template->bottom_information->client_note->font_size : $default_font_size), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('bottom_information[client_note][font_style]',$font_styles,array('id','name'),'ib_font_style',(isset($template->bottom_information->client_note->font_style) ? $template->bottom_information->client_note->font_style : $default_font_style)); ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('bottom_information[client_note][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->bottom_information->client_note->text_align) ? $template->bottom_information->client_note->text_align : $default_text_alignment)); ?>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>
														<?php 
														if(isset($template->bottom_information->custom_field) && is_object($template->bottom_information->custom_field) && $field_list = (array)$template->bottom_information->custom_field){
															foreach ($field_list as $id => $field) { 
																$field_data = $this->invoices_builder_model->get_custom_field($id);
																if($field_data){
																	$field->type = 'bottom_information';
																	$field->id = $id;
																	$field->field_label = $field_data->label;
																	if(!isset($field->active)){
																		$field->active = 0;
																	} ?>
																	<div class="col-md-4 custom_field_item">
																		<?php $this->load->view('templates/includes/custom_field_item', (array)$field);	?>
																	</div>
																	<?php 
																}
															}
														} ?>

													</div>

													<div class="col-md-12">
														<div class="row">
															<div class="col-md-8">
																<div class="dropup">
																	<button type="button" id="bottom_add_field_btn" class="btn btn-icon btn-success mtop10" onclick="bottom_add_field(); return false;"><i class="fa fa-plus"></i><?php echo ' '._l('add_field'); ?></button>

																	<ul class="setting_popup hide" id="bottom_add_field_setting">
																		<button type="button" class="close mright10" data-dismiss="bottom_add_field_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<div class="checkbox checkbox-primary">
																					<input type="checkbox" id="select_an_available_field10" name="select_an_available_field" value="1" ><label for="select_an_available_field10"><?php echo _l('ib_select_an_available_field'); ?></label>
																				</div>
																			</div>
																			<div class="col-md-12 fr2 hide">
																				<?php
																				$list_fields = $this->invoices_builder_model->get_custom_field('','type = "bottom"', 'id, label', false, 'label');
																				echo render_select('cf_bottom_field_available', $list_fields, array('id', 'label'), 'select_field', '', []) ?>
																			</div>
																			<div class="col-md-6 fr1">
																				<?php echo render_input('cf_bottom_field_name', 'ib_field_name', '', 'text', []) ?>
																			</div>
																			<div class="col-md-6 fr1">
																				<?php echo render_input('cf_bottom_field_label', 'ib_field_label', '', 'text', []) ?>
																			</div>
																			<div class="col-md-12">
																				<span class="text-danger error_add_customfield hide" id="cf_bottom_error_formula"></span>
																			</div>
																			<button type="button" onclick="add_bottom_field(this); return false;" class="btn btn-sm btn-success pull-right mright10 mbot5"><?php echo _l('ib_add'); ?></button>
																		</li>
																	</ul>
																</div>
															</div>
														</div>
													</div>

												</div>
											</div>
										</div>
									</div>
								</div>

								<div class="panel panel-info footer_panel" data-panel="footer_panel">
									<div class="panel-heading"><?php echo _l('ib_footer_section'); ?></div>
									<div class="panel-body">
										<div class="row">
											<div class="list_item_table_row">
												<div class="row_item">
													<div class="col-md-12">
														<p class="bold row-infor-color"><?php echo _l('ib_row_setting'); ?></p>
														<hr class="row-infor-hr">
													</div>
													<div class="col-md-6">
														<?php
														echo render_color_picker('footer[row_color]', _l('ib_row_color'), (isset($template->footer->row_color) ? $template->footer->row_color : $default_background_color), ['required' => true]) ; ?>
													</div>
													<div class="col-md-6">
														<?php echo render_input('footer[col_number]', 'ib_column_number_of_row', (isset($template->footer->col_number) ? $template->footer->col_number : ''), 'number', ['required' => true, 'max' => 4, 'min' => 1]) ?>
													</div>
													<div class="col-md-12">
														<p class="bold row-infor-color"><?php echo _l('ib_information_displayed'); ?></p>
														<hr class="row-infor-hr">
													</div>

													<div id="footer_field_setting">
														<div class="col-md-4">
															<div class="dropup">
																<div class="checkbox_row">
																	<div class="checkbox checkbox-primary">
																		<input type="checkbox" id="footer_term_condition" name="footer[term_condition][active]" value="1" <?php echo (isset($template->footer->term_condition->active) ? 'checked' : '') ?> onclick="column_setting(this);"  data-btn-name="footer_term_condition_setting_btn" data-div-name="footer_term_condition_setting" ><label for="footer_term_condition"><?php echo _l('ib_term_condition'); ?></label> 

																		<button id="footer_term_condition_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft2 <?php echo (isset($template->footer->term_condition->active) ? '' : ' hide') ?>" onclick="edit_setting(this);"  data-div-name="footer_term_condition_setting"><i class="fa fa-cog"></i></button>
																	</div>

																	<ul class="setting_popup hide" id="footer_term_condition_setting">
																		<button type="button" class="close mright10" data-dismiss="footer_term_condition_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<?php echo render_input('footer[term_condition][content]', 'ib_content', (isset($template->footer->term_condition->content) ? $template->footer->term_condition->content : ''), 'text', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('footer[term_condition][order_column]', 'ib_order_column', (isset($template->footer->term_condition->order_column) ? $template->footer->term_condition->order_column : ''), 'number', $order_column_attr, [], '', 'order_column') ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_color_picker('footer[term_condition][text_color]', _l('ib_text_color'), (isset($template->footer->term_condition->text_color) ? $template->footer->term_condition->text_color : $default_text_color)) ; ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_input('footer[term_condition][font_size]', 'ib_font_size', (isset($template->footer->term_condition->font_size) ? $template->footer->term_condition->font_size : $default_font_size), 'number', []) ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('footer[term_condition][font_style]',$font_styles,array('id','name'),'ib_font_style',(isset($template->footer->term_condition->font_style) ? $template->footer->term_condition->font_style : $default_font_style)); ?>
																			</div>
																			<div class="col-md-12">
																				<?php echo render_select('footer[term_condition][text_align]',$text_align,array('id','name'),'ib_text_align',(isset($template->footer->term_condition->text_align) ? $template->footer->term_condition->text_align : $default_text_alignment)); ?>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>
														<?php 
														if(isset($template->footer->custom_field) && is_object($template->footer->custom_field) && $field_list = (array)$template->footer->custom_field){
															foreach ($field_list as $id => $field) { 
																$field_data = $this->invoices_builder_model->get_custom_field($id);
																if($field_data){
																	$field->type = 'footer';
																	$field->id = $id;
																	$field->field_label = $field_data->label;
																	if(!isset($field->active)){
																		$field->active = 0;
																	} ?>
																	<div class="col-md-4 custom_field_item">
																		<?php $this->load->view('templates/includes/custom_field_item', (array)$field);	?>
																	</div>
																	<?php 
																}
															}
														} ?>

													</div>

													<div class="col-md-12">
														<div class="row">
															<div class="col-md-8">
																<div class="dropup">
																	<button type="button" id="footer_add_field_btn" class="btn btn-icon btn-success mtop10" onclick="footer_add_field(); return false;"><i class="fa fa-plus"></i><?php echo ' '._l('add_field'); ?></button>

																	<ul class="setting_popup hide" id="footer_add_field_setting">
																		<button type="button" class="close mright10" data-dismiss="footer_add_field_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
																		<li role="presentation">
																			<div class="col-md-12">
																				<div class="checkbox checkbox-primary">
																					<input type="checkbox" id="select_an_available_field7" name="select_an_available_field" value="1" ><label for="select_an_available_field7"><?php echo _l('ib_select_an_available_field'); ?></label>
																				</div>
																			</div>
																			<div class="col-md-12 fr2 hide">
																				<?php
																				$list_fields = $this->invoices_builder_model->get_custom_field('','type = "footer"', 'id, label', false, 'label');
																				echo render_select('cf_footer_field_available', $list_fields, array('id', 'label'), 'select_field', '', []) ?>
																			</div>
																			<div class="col-md-6 fr1">
																				<?php echo render_input('cf_footer_field_name', 'ib_field_name', '', 'text', []) ?>
																			</div>
																			<div class="col-md-6 fr1">
																				<?php echo render_input('cf_footer_field_label', 'ib_field_label', '', 'text', []) ?>
																			</div>
																			<div class="col-md-12">
																				<span class="text-danger error_add_customfield hide" id="cf_footer_error_formula"></span>
																			</div>
																			<button type="button" onclick="add_footer_field(this); return false;" class="btn btn-sm btn-success pull-right mright10 mbot5"><?php echo _l('ib_add'); ?></button>
																		</li>
																	</ul>
																</div>
															</div>
														</div>
													</div>

												</div>
											</div>
										</div>
										<div class="btn-bottom-toolbar text-right">
											<input type="submit" id="save_template_btn" class="hide">
											<button type="button" class="btn btn-info save_template"><?php echo _l('save'); ?></button>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div id="preview_area" class="col-md-6 pleft-0 pright-0 build-section">
				<div class="panel_s">
					<?php $this->load->view('templates/includes/preview'); ?>
				</div>
			</div>

			<?php echo form_close(); ?>	 


		</div>
	</div>
</div>
<?php init_tail();?>
<?php require 'modules/invoices_builder/assets/js/templates/template_js.php';?>
</body>
</html>