<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content">
<?php if (!$can_create && is_admin()) { ?><p><a href="<?= html_escape(admin_url('company_master')); ?>">Resolver a responsabilidade da instalação legada</a></p><?php } ?>
<?php require APPPATH.'views/admin/companies/registry_grid.php'; ?>
</div></div>
<?php init_tail(); ?>
</body></html>
