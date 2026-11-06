<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <?php
                include_once(APPPATH . 'views/admin/invoices/recurring/filter_params.php');
                $this->load->view('admin/invoices/recurring/list_template');
            ?>
        </div>
    </div>
</div>
<?php if(!($native_recurring??false))$this->load->view('admin/includes/modals/sales_attach_file'); ?>
<script>
var hidden_columns = <?= ($native_recurring??false)?'[4, 5, 6, 7]':'[5, 7, 8, 9]'; ?>;
</script>
<?php init_tail(); ?>
<script>
$(function() {
    <?= ($native_recurring??false)?'init_recurring_schedule':'init_invoice'; ?>();
});
</script>
</body>

</html>