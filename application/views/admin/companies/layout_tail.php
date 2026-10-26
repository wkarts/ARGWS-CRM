<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php // Minimal UI dependencies for the same select used in the native header. No legacy app bootstrap.
foreach (['assets/plugins/jquery/jquery.min.js', 'assets/plugins/bootstrap/js/bootstrap.min.js', 'assets/plugins/bootstrap-select/js/bootstrap-select.min.js'] as $companyLayoutScript) { ?>
<script src="<?= $companyLayoutEscape(base_url($companyLayoutScript).'?v='.$companyLayoutVersion); ?>"></script>
<?php } ?>
<script src="<?= $companyLayoutEscape(base_url('assets/js/native-admin-tabs.js').'?v='.$companyLayoutVersion); ?>"></script>
<script src="<?= $companyLayoutEscape(base_url('assets/js/company-shell.js').'?v='.$companyLayoutVersion); ?>"></script>
<script src="<?= $companyLayoutEscape(base_url('assets/js/company-workspace.js').'?v='.$companyLayoutVersion); ?>"></script>
</main></div></body></html>
