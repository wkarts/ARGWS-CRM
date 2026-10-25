<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content">
<?php if (!$can_create && is_admin()) { ?><p class="alert alert-info" role="status">A criação de empresas está disponível ao administrador original confirmado desta instalação. Seu usuário ainda não possui esse vínculo; suas permissões existentes permanecem disponíveis.</p><?php } ?>
<?php require APPPATH.'views/admin/companies/registry_grid.php'; ?>
</div></div>
<?php init_tail(); ?>
</body></html>
