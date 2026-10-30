<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if(!empty($native_company_sales_workspace)){init_head(); ?><div id="wrapper"><div class="content"><div class="row"><div class="col-md-12"><div class="panel_s"><div class="panel-body"><?php require APPPATH.'views/admin/includes/native_sales_workspace.php'; ?></div></div></div></div></div></div><?php init_tail(); ?></body></html><?php return;} ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="panel-table-full">
                <?php $this->load->view('admin/proposals/list_template'); ?>
            </div>
        </div>
    </div>
</div>
<?php $this->load->view('admin/includes/modals/sales_attach_file'); ?>
<script>
    var hidden_columns = [4, 5, 6, 7, 8];
</script>
<?php init_tail(); ?>
<div id="convert_helper"></div>
<script>
    var proposal_id;
    $(function() {
        var Proposals_ServerParams = {};
        $.each($('._hidden_inputs._filters input'), function() {
            Proposals_ServerParams[$(this).attr('name')] = '[name="' + $(this).attr('name') + '"]';
        });
        initDataTable('.table-proposals', admin_url + 'proposals/table', ['undefined'], ['undefined'],
            Proposals_ServerParams, [8, 'desc']);
        init_proposal();
    });
</script>
</body>

</html>
