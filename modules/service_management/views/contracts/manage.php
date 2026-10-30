<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                <div class="panel-body _buttons">
                    <?php if(has_permission('contracts','','create')){ ?>
                    <a href="<?php echo admin_url('service_management/contract'); ?>" class="btn btn-info pull-left display-block"><?php echo _l('new_contract'); ?></a>
                    <?php } ?>
                    <?php $this->load->view('service_management/contracts/filters'); ?>
                    <div class="clearfix"></div>
                    <hr class="hr-panel-heading" />
                    <div class="row" id="contract_summary">
                        <div class="col-md-12">
                            <h4 class="no-margin text-success"><?php echo _l('contract_summary_heading'); ?></h4>
                        </div>
                        <div class="col-md-2 col-xs-6 border-right">
                            <h3 class="bold"><?php echo new_html_entity_decode($count_active); ?></h3>
                            <span class="text-info"><?php echo _l('contract_summary_active'); ?></span>
                        </div>
                        <div class="col-md-2 col-xs-6 border-right">
                            <h3 class="bold"><?php echo new_html_entity_decode($count_expired); ?></h3>
                            <span class="text-danger"><?php echo _l('contract_summary_expired'); ?></span>
                        </div>
                        <div class="col-md-2 col-xs-6 border-right">
                            <h3 class="bold"><?php echo count($expiring); ?></h3>
                                <span class="text-warning"><?php echo _l('contract_summary_about_to_expire'); ?></span>
                            </div>
                            <div class="col-md-2 col-xs-6 border-right">
                                <h3 class="bold"><?php echo new_html_entity_decode($count_recently_created); ?></h3>
                                    <span class="text-success"><?php echo _l('contract_summary_recently_added'); ?></span>
                                </div>
                                <div class="col-md-2 col-xs-6">
                                    <h3 class="bold"><?php echo new_html_entity_decode($count_trash); ?></h3>
                                    <span class="text-muted"><?php echo _l('contract_summary_trash'); ?></span>
                                </div>
                                <div class="clearfix"></div>
                                <hr class="hr-panel-heading" />
                                <div class="col-md-6 border-right">
                                    <h4><?php echo _l('contract_summary_by_type'); ?></h4>
                                    <div class="relative max-height-400" >
                                        <canvas class="chart" height="400" id="contracts-by-type-chart"></canvas>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h4>
                                        <?php echo _l('contract_summary_by_type_value'); ?>
                                        (<span data-toggle="tooltip"
                                            data-title="<?php echo _l('base_currency_string'); ?>"
                                            class="text-has-action">
                                        <?php echo new_html_entity_decode($base_currency->name); ?></span>)
                                    </h4>
                                    <div class="relative max-height-400" >
                                        <canvas class="chart" height="400" id="contracts-value-by-type-chart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="panel_s">
                        <?php echo form_hidden('custom_view'); ?>
                        <div class="panel-body">
                           <?php $this->load->view('service_management/contracts/table_html'); ?>
                       </div>
                   </div>
               </div>
           </div>
       </div>
   </div>
   <?php init_tail(); ?>
   <?php require 'modules/service_management/assets/js/contracts/manage_js.php';?>

</body>
</html>
