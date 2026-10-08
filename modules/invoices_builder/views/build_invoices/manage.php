<?php defined('BASEPATH') or exit('No direct script access allowed');?>
<?php init_head();?>
<div id="wrapper">
<div class="content">
    <div class="row">
        <div class="panel_s">
            <div class="panel-body">
				<div class="row">
					<div class="col-md-6">
						<h4><?php echo ib_html_entity_decode($title); ?></h4>
					</div>
					<div class="col-md-6">
						<div class="btn-group pull-right">
                          	<a href="<?php echo admin_url('invoices_builder/build_invoice'); ?>" class="btn btn-info" ><?php echo _l('ib_build_invoices'); ?></a>
                          	
                       </div>
					</div>
					<div class="col-md-12"><hr class="mtop5"></div>
					<div class="col-md-12">
						<div class="row">
							<div class="col-md-3 form-group">
								<select name="template[]" id="template[]" class="selectpicker"  data-width="100%" data-live-search="true" multiple="true" data-none-selected-text="<?php echo _l('ib_templates'); ?>" >
									
							      <?php foreach($templates as $template){ ?>
							        <option value="<?php echo ib_html_entity_decode($template['id']); ?>" ><?php echo ib_html_entity_decode($template['name']); ?></option>
							      <?php } ?>
							    </select>
							</div>
							<div class="col-md-3 form-group">
								<select name="clients[]" id="clients[]" class="selectpicker"  data-width="100%" data-live-search="true" multiple="true" data-none-selected-text="<?php echo _l('ib_customer'); ?>" >
									
							      <?php foreach($clients as $cli){ ?>
							        <option value="<?php echo ib_html_entity_decode($cli['userid']); ?>" ><?php echo get_company_name($cli['userid']); ?></option>
							      <?php } ?>
							    </select>
							</div>
							<div class="col-md-3 form-group">
								<select name="invoice[]" id="invoice[]" class="selectpicker" data-width="100%" data-live-search="true" multiple="true" required data-none-selected-text="<?php echo _l('ib_invoices'); ?>" >

						      <?php foreach($list_invoices as $invoice){ ?>
						        <option value="<?php echo ib_html_entity_decode($invoice['id']); ?>"><?php echo format_invoice_number($invoice['id']); ?></option>
						      <?php } ?>
						    </select> 
							</div>
							<div class="col-md-3 form-group">
								<select name="sale_agent[]" id="sale_agent[]" class="selectpicker" data-width="100%" data-live-search="true" multiple="true" required data-none-selected-text="<?php echo _l('ib_sale_agent'); ?>" >

						      <?php foreach($staff as $st){ ?>
						        <option value="<?php echo ib_html_entity_decode($st['staffid']); ?>"><?php echo get_staff_full_name($st['staffid']); ?></option>
						      <?php } ?>
						    </select> 
							</div>
	
						</div>

						<div id="table_view" class="">
							<?php render_datatable(array(
			                        _l('ib_invoice').' #',
			                        _l('ib_template'),
			                        _l('ib_customer'),
			                        _l('ib_invoice_status'),
			                        _l('ib_options'),
			                        ),'table_invoices_built'); ?>
			            </div>

					</div>
				</div>
			</div>
		</div>
	
	</div>
</div>
</div>

<div class="modal fade" id="send_invoice" tabindex="-1" role="dialog">
  <div class="modal-dialog">
      <?php echo form_open_multipart(admin_url('invoices_builder/send_invoice'),array('id'=>'send_invoice-form')); ?>
      <div class="modal-content modal_withd">
          <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
              <h4 class="modal-title">
                  <span><?php echo _l('ib_send_invoice'); ?></span>
              </h4>
          </div>
          <div class="modal-body">
              <div id="additional"></div>
              <div class="row">
                <div class="col-md-12 form-group">
                  <label for="send_to"><span class="text-danger">* </span><?php echo _l('ib_send_to'); ?></label>
                    <select name="send_to[]" id="send_to" class="selectpicker" required multiple="true"  data-live-search="true" data-width="100%" data-none-selected-text="<?php echo _l('ticket_settings_none_assigned'); ?>" >
                        
                    </select>
                    <br>
                </div>     
                <div class="col-md-12">
                  <div class="checkbox checkbox-primary">
                      <input type="checkbox" name="attach_pdf" id="attach_pdf" checked>
                      <label for="attach_pdf"><?php echo _l('ib_attach_pdf'); ?></label>
                  </div>
                </div>

                <div class="col-md-12">
                 <?php echo render_textarea('content','additional_content','',array('rows'=>6,'data-task-ae-editor'=>true, !is_mobile() ? 'onclick' : 'onfocus'=>(!isset($routing) || isset($routing) && $routing->description == '' ? 'routing_init_editor(\'.tinymce-task\', {height:200, auto_focus: true});' : '')),array(),'no-mbot','tinymce-task'); ?> 
                </div>     
        
              </div>
          </div>
          <div class="modal-footer">
              <button type=""class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
              <button id="sm_btn" type="submit" data-loading-text="<?php echo _l('wait_text'); ?>" class="btn btn-info"><?php echo _l('pur_send'); ?></button>
          </div>
      </div><!-- /.modal-content -->
          <?php echo form_close(); ?>
      </div><!-- /.modal-dialog -->
</div><!-- /.modal -->

<?php init_tail();?>
</body>
</html>