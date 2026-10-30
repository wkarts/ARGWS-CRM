<?php 
$font_style_list = [
	0 => ['id' => 'inherit', 'name' => _l('ib_inherit')],
	1 => ['id' => 'initial', 'name' => _l('ib_initial')],
	2 => ['id' => 'italic', 'name' => _l('ib_italic')],
	3 => ['id' => 'normal', 'name' => _l('ib_normal')],
	4 => ['id' => 'oblique', 'name' => _l('ib_oblique')],
	5 => ['id' => 'revert', 'name' => _l('ib_revert')],
	5 => ['id' => 'unset', 'name' => _l('ib_unset')],
];
$text_align_list = [
	0 => ['id' => 'left', 'name' => _l('ib_left')],
	1 => ['id' => 'right', 'name' => _l('ib_right')],
	2 => ['id' => 'center', 'name' => _l('ib_center')],
];
$formular_control_list = formular_control_list($type);
$default_font_size = '13';
$default_font_style = 'normal';
$default_text_color = '#000';
$default_text_alignment = 'left';
$default_background_color = '#fff';
?>


<div class="dropup">
<div class="checkbox_row">
	<div class="checkbox checkbox-primary">
		<?php 
			$input_id = $type.'[custom_field]['.$id.']';
			$name = $input_id.'[active]';
		 ?>
		<input type="checkbox" id="<?php echo ib_html_entity_decode($input_id) ?>" <?php echo ($active == 1 ? 'checked' : '') ?> name="<?php echo ib_html_entity_decode($name) ?>" value="1" onclick="column_setting(this);" data-btn-name="<?php echo ib_html_entity_decode($input_id) ?>_setting_btn" data-div-name="<?php echo ib_html_entity_decode($input_id) ?>_setting" ><label for="<?php echo ib_html_entity_decode($input_id) ?>"><?php echo ib_html_entity_decode($field_label); ?></label>

		<button id="<?php echo ib_html_entity_decode($input_id) ?>_setting_btn" type="button" class="btn btn-icon-ib btn-success mleft5<?php echo ($active == 1 ? '' : ' hide') ?>" onclick="edit_setting(this);" data-div-name="<?php echo ib_html_entity_decode($input_id) ?>_setting"><i class="fa fa-cog"></i></button>
		<button id="<?php echo ib_html_entity_decode($input_id) ?>_remove_btn" type="button" class="btn btn-icon-ib btn-warning remove-field-btn" onclick="remove_setting(this);" data-type="<?php echo ib_html_entity_decode($type) ?>" data-id="<?php echo ib_html_entity_decode($id) ?>"><i class="fa fa-trash"></i></button>
	</div>
	<ul class="setting_popup hide" id="<?php echo ib_html_entity_decode($type); ?>[custom_field][<?php echo ib_html_entity_decode($id); ?>]_setting">
		<button type="button" class="close mright10" data-dismiss="<?php echo ib_html_entity_decode($type); ?>[custom_field][<?php echo ib_html_entity_decode($id); ?>]_setting" onclick="close_setting(this);"><span aria-hidden="true">&times;</span></button>
		<li role="presentation">
			<?php 
			$display_control_col = 'col-md-12';
			
			if(isset($formula) && ($type == 'item_table' || $type == 'invoice_total')){
				
				?>
				<div class="col-md-12 formula" data-type="<?php echo ib_html_entity_decode($type); ?>">
					<?php echo render_select('add_expression_slug', $formular_control_list, array('id', 'label'), 'ib_field_formula', '', [], [], '', 'add_expression_slug '.$type) ?>
					<?php echo render_textarea($input_id.'[formula]', '', $formula, ['onkeyup' => 'keyup_formula(this)'], [], '', 'custom_field_formula '.$type); ?>
					<div class="formula-error text-danger mbot10 hide"></div>
				</div>
			<?php } ?>
			<?php if(!($type == 'sender_receiver[sender_infor]' || $type == 'sender_receiver[receiver_infor]' || $type == 'header[invoice_infor]')){ ?>
				<div class="col-md-12">
					<?php echo render_input($input_id.'[order_column]', 'ib_order_column', (isset($order_column) ? $order_column : ''), 'number', [], [], '', 'order_column') ?>
				</div>
			<?php } ?>
			<div class="col-md-12">
				<?php 
				$value_class = '';
				if($type != 'header[invoice_infor]') {
					$value_class = 'custom_field_value';
				}
				echo render_input($input_id.'[value]', 'ib_value', (isset($value) ? $value : ''), 'text', [], [], '', $value_class);
				?>
			</div>
			<?php 
			if($type != 'header[invoice_infor]'){
				?>
				<div class="<?php echo ib_html_entity_decode($display_control_col); ?>">
					<?php
					echo render_color_picker($input_id.'[text_color]', _l('ib_text_color'), (isset($text_color) ? $text_color : $default_text_color));
					?>
				</div>
				<div class="<?php echo ib_html_entity_decode($display_control_col); ?>">
					<?php
					echo render_input($input_id.'[font_size]', 'ib_font_size', (isset($font_size) ? $font_size : $default_font_size), 'number', []);

					?>
				</div>
				<div class="<?php echo ib_html_entity_decode($display_control_col); ?>">
					<?php
					echo render_select($input_id.'[font_style]',$font_style_list,array('id','name'),'ib_font_style',(isset($font_style) ? $font_style : $default_font_style));

					?>
				</div>
				<div class="<?php echo ib_html_entity_decode($display_control_col); ?>">
					<?php
					echo render_select($input_id.'[text_align]',$text_align_list,array('id','name'),'ib_text_align',(isset($text_align) ? $text_align : $default_text_alignment));
					?>
				</div>
			<?php } ?>
		</li>
	</ul>
</div>
</div>