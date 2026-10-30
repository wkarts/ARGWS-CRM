<?php defined('BASEPATH') or exit('No direct script access allowed');?>
<?php init_head();?>
<div id="wrapper">
<div class="content">
    <div class="row">
        <div class="panel_s">
            <div class="panel-body">
            	<div class="row">
            		<div class="col-sm-6">
            			<h4><?php echo ib_html_entity_decode($title); ?></h4>
            		</div>
            		<div class="col-sm-6">
            			<a href="<?php echo admin_url('invoices_builder/template'); ?>" class="btn btn-info pull-right"><?php echo _l('ib_add_template') ?></a>
            		</div>
            	</div>
				<div class="row">
					<div class="col-md-12"><hr class="mtop5"></div>
					<div class="col-md-12">
						<div id="table_view" class="">
							<?php render_datatable(array(
			                        _l('ib_id'),
			                        _l('ib_image'),
			                        _l('ib_name'),
			                        _l('ib_created_by'),
			                        _l('ib_created_at'),
			                        _l('ib_active'),
			                        _l('ib_options'),
			                        ),'table_templates'); ?>
			            </div>
		

					</div>
				</div>
			</div>
		</div>
	
	</div>
</div>
</div>
<div class="modal fade preview_template" id="preview_modal" tabindex="-1" role="dialog">
  <div class="modal-dialog">
    <div class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title">
                <span class="add-title"></span>
            </h4>
        </div>
        <div class="modal-body">
            <div class="row">
                <div class="col-md-12">
                	<img class="w-100" src="" alt="">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
        </div>
      </div><!-- /.modal-content -->
   </div><!-- /.modal-dialog -->
</div><!-- /.modal -->
<?php init_tail();?>
<input type="hidden" name="confirm_text" value="<?php echo _l('ib_are_you_sure_you_want_to_disable_this'); ?>">
</body>
</html>