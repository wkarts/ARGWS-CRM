<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if(!empty($native_company_sales_workspace)){init_head(); ?><div id="wrapper"><div class="content"><div class="row"><div class="col-md-12"><div class="panel_s"><div class="panel-body"><?php require APPPATH.'views/admin/includes/native_sales_workspace.php'; ?></div></div></div></div></div></div><?php init_tail(); ?></body></html><?php return;} ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="panel-table-full">
                <div id="vueApp">
                    <div class="col-md-12 tw-mb-3">
                        <h4 class="tw-my-0 tw-font-bold tw-text-xl"><?= _l('estimates'); ?></h4>
                        <a href="#" 
							class="estimates-total tw-text-neutral-500 hover:tw-text-neutral-700 focus:tw-text-neutral-700"
							onclick="slideToggle('#stats-top'); init_estimates_total(true); return false;">
								<?= _l('view_financial_stats'); ?>
						</a>
                    </div>                  
                    <div class="col-md-12">
                        <?php $this->load->view('admin/estimates/quick_stats'); ?>
                    </div>
                    <?php $this->load->view('admin/estimates/list_template'); ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $this->load->view('admin/includes/modals/sales_attach_file'); ?>
<script>
var hidden_columns = [2, 5, 6, 8, 9];
</script>
<?php init_tail(); ?>
<script>
$(function() {
    init_estimate();
});
</script>
</body>

</html>
