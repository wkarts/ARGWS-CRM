<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$taskModalClass=isset($nativeCompany)&&$nativeCompany
    ? ($nativeCompany['public_options']['values']['task_modal_class']??'modal-lg')
    : get_option('task_modal_class');
?>
<!-- Shared original task detail and add/edit mounts; no data or authority in the shell. -->
<div class="modal fade task-modal-single" id="task-modal" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel">
    <div class="modal-dialog <?= e($taskModalClass); ?>"><div class="modal-content data"></div></div>
</div>
<div id="_task"></div>
