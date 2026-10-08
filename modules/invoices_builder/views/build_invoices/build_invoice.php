<?php defined('BASEPATH') or exit('No direct script access allowed');?>
<?php init_head();?>
<div id="wrapper">
<div class="content">
    <div class="row">
    	<?php echo form_open($this->uri->uri_string(),array('id'=>'build_invoice_form')); ?>
    	<div id="design_area" class="col-md-6 pleft-0 pright-sm-0">
	        <div class="panel_s">
	            <div class="panel-body">
					<div class="row">
						<h4 class="mleft5"><?php echo _l('ib_building_invoices'); ?></h4>
						<hr class="hr-panel-heading-dashboard">

						<?php $default_template = get_template_default(); ?>


						<div class="col-md-12 form-group">
							<label for="template_id"><span class="text-danger">* </span><?php echo _l('ib_template'); ?></label>
							<select name="template_id" id="template_id" class="selectpicker"  data-width="100%" onchange="template_change(this); return false;" data-live-search="true" required data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>" >
								<option value=""></option>
						      <?php foreach($templates as $template){ ?>
						        <option value="<?php echo ib_html_entity_decode($template['id']); ?>" <?php if(isset($default_template->id) && $default_template->id == $template['id']){ echo 'selected'; } ?>><?php echo ib_html_entity_decode($template['name']); ?></option>
						      <?php } ?>
						    </select> 
						</div>

						<div class="col-md-6 form-group">
							<label for="customer"><?php echo _l('ib_customer'); ?></label>
							<select name="customer" id="customer" class="selectpicker"  data-width="100%" onchange="customer_change(this); return false;" data-live-search="true" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>" >
								<option value=""></option>
						      <?php foreach($customers as $client){ ?>
						        <option value="<?php echo ib_html_entity_decode($client['userid']); ?>" ><?php echo ib_html_entity_decode($client['company']); ?></option>
						      <?php } ?>
						    </select> 
						</div>

						<div class="col-md-6 form-group">
							<label for="project"><?php echo _l('ib_project'); ?></label>
							<select name="project" id="project" class="selectpicker"  data-width="100%" onchange="project_change(this); return false;" data-live-search="true" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>" >
								<option value=""></option>
						      <?php foreach($projects as $project){ ?>
						        <option value="<?php echo ib_html_entity_decode($project['id']); ?>" ><?php echo ib_html_entity_decode($project['name']); ?></option>
						      <?php } ?>
						    </select> 
						</div>

						<div class="col-md-6 form-group">
							<label for="sale_agent"><?php echo _l('ib_sale_agent'); ?></label>
							<select name="sale_agent" id="sale_agent" class="selectpicker"  data-width="100%" onchange="sale_agent_change(this); return false;" data-live-search="true" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>" >
								<option value=""></option>
						      <?php foreach($staff as $st){ ?>
						        <option value="<?php echo ib_html_entity_decode($st['staffid']); ?>" ><?php echo ib_html_entity_decode($st['firstname'].' '.$st['lastname']); ?></option>
						      <?php } ?>
						    </select> 
						</div>

						<div class="col-md-6 form-group">
							<label for="invoice_id"><span class="text-danger">* </span><?php echo _l('ib_invoice'); ?></label>
							<select name="invoice_id[]" id="invoice_id" class="selectpicker" data-width="100%" data-live-search="true" multiple="true" required data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>" >

						      <?php foreach($list_invoices as $invoice){ ?>
						        <option value="<?php echo ib_html_entity_decode($invoice['id']); ?>"><?php echo format_invoice_number($invoice['id']); ?></option>
						      <?php } ?>
						    </select> 
						</div>

						<div class="col-md-12">
							<?php echo render_textarea('description', 'ib_description'); ?>
						</div>

					</div>

					<hr>
		            <button type="submit" data-form="#build_invoice_form" class="btn btn-info pull-right" autocomplete="off" data-loading-text="<?php echo _l('wait_text'); ?>"><?php echo _l('submit'); ?></button>
		            
				</div>
			</div>
		</div>
		<div id="preview_area" class="col-md-6 pleft-0 pright-0 build-section">
		</div>
		<?php echo form_close(); ?>

	</div>
</div>
</div>
<?php init_tail();?>

<?php require 'modules/invoices_builder/assets/js/build_invoices/_build_invoice_js.php';?>
</body>
</html>