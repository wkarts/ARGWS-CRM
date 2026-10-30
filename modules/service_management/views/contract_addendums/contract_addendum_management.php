<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
	<div class="content">
		<div class="row">
			<div class="col-md-12">
				<div class="panel_s">
					<?php echo form_hidden('custom_view'); ?>
					<div class="panel-body">
						<?php if(has_permission('contracts','','create')){ ?>
							<a href="<?php echo admin_url('service_management/contract_addendum'); ?>" class="btn btn-info pull-left display-block"><?php echo _l('new_contract_addendum'); ?></a>
						<?php } ?>
						<div class="clearfix"></div>
						<hr class="hr-panel-heading" />

						<?php $this->load->view('service_management/contract_addendums/table_html'); ?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<?php init_tail(); ?>
<?php require 'modules/service_management/assets/js/contract_addendums/contract_addendum_management_js.php'; ?>

</body>
</html>
