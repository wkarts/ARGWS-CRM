<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!-- Client send file modal -->
<div class="modal fade" id="send_file" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <?php echo form_open('admin/misc/send_file/'); ?>
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="myModalLabel"><?php echo _l('send_file'); ?></h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        <?php echo render_input('send_file_subject', 'send_file_subject'); ?>
                        <?php echo render_input('send_file_email', 'send_file_email'); ?>
                        <?php echo render_textarea('send_file_message', 'send_file_message', !empty($native_customers)?$native_file_signature:get_option('email_signature')); ?>
                        <?php if(!empty($native_customers)){echo form_hidden('_native_company_csrf',$company_clients_csrf);echo form_hidden('native_file_source','customer');echo form_hidden('native_file_id','');echo form_hidden('native_file_revision','');echo form_hidden('request_key','');} ?>
                        <?php echo form_hidden('file_path', ''); ?>
                        <?php echo form_hidden('filetype', ''); ?>
                        <?php echo form_hidden('file_name', ''); ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo _l('send_file'); ?></button>
            </div>
            <?php echo form_close(); ?>
        </div>
    </div>
</div>
