<?php defined('BASEPATH') or exit('No direct script access allowed');?>
<?php init_head();?>
<div id="wrapper">
<div class="content">
    <div class="row">
    	<?php echo form_open($this->uri->uri_string(),array('id'=>'build_invoice_form')); ?>
    	<div id="design_area" class="col-md-12 pleft-0">
	        <div class="panel_s">
	            <div class="panel-body">
					<div class="row">
						<h4 class="mleft5"><?php echo format_invoice_number($built_invoice->invoice_id); ?></h4>
						<hr class="hr-panel-heading-dashboard">

						<?php echo form_hidden('invoice_id', $built_invoice->invoice_id ) ?>

						<?php $default_template = get_template_default(); ?>
						
						<div class="col-md-12 form-group">
							<label for="template_id"><span class="text-danger">* </span><?php echo _l('ib_template'); ?></label>
							<select name="template_id" id="template_id" class="selectpicker" onchange="template_change(this); return false;" data-width="100%" data-live-search="true" required data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>" >
								<option value=""></option>
						      <?php foreach($templates as $template){ ?>
						        <option value="<?php echo ib_html_entity_decode($template['id']); ?>" <?php if(isset($built_invoice->template_id) && $built_invoice->template_id == $template['id']){ echo 'selected'; } ?>><?php echo ib_html_entity_decode($template['name']); ?></option>
						      <?php } ?>
						    </select> 
						</div>

						<div class="col-md-12" id="additional_infor_title">
							<p class="bold"><?php echo _l('ib_additional_infor'); ?></p>
							<hr class="mtop5" />
						</div>
					
						<div id="additional_infor">
							
						</div>

					</div>

					<div class="btn-bottom-toolbar text-right">
		                <button type="submit" data-form="#build_invoice_form" class="btn btn-info" autocomplete="off" data-loading-text="<?php echo _l('wait_text'); ?>"><?php echo _l('submit'); ?></button>
		            </div>
				</div>
			</div>
		</div>
		<?php echo form_close(); ?>

	</div>
</div>
</div>
<?php init_tail();?>

<?php require 'modules/invoices_builder/assets/js/build_invoices/build_invoice_js.php';?>
</body>
</html>