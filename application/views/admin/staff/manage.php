<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php $nativeStaff=!empty($native_staff_manage);init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <?php if ($nativeStaff?$native_staff_can_create:staff_can('create',  'staff')) { ?>
                <div class="tw-mb-2">
                    <a href="<?php echo admin_url('staff/member'); ?>" class="btn btn-primary">
                        <i class="fa-regular fa-plus tw-mr-1"></i>
                        <?php echo _l('new_staff'); ?>
                    </a>
                </div>
                <?php } ?>
                <?php if ($nativeStaff && !empty($native_staff_can_link)) { ?>
                <div class="panel_s tw-mb-4">
                    <div class="panel-body">
                        <details id="native-staff-link-identity">
                            <summary class="tw-font-semibold"><?= e(_l('issuer_staff_link_existing')); ?></summary>
                            <p class="text-muted tw-mt-2"><?= e(_l('issuer_staff_link_existing_help')); ?></p>
                            <?= form_open(admin_url('staff/member'), ['class'=>'tw-mt-3','id'=>'native-staff-link']); ?>
                            <?= form_hidden('native_staff_operation','link'); ?>
                            <?= form_hidden('request_key',$native_staff_link_request_key); ?>
                            <?= render_input('existing_email','staff_add_edit_email','','email',['required'=>true,'autocomplete'=>'off']); ?>
                            <div class="row">
                            <?php foreach($native_staff_link_catalog as $feature=>$definition){$allowed=$native_staff_link_assignable[$feature]??[];if(!$allowed)continue; ?>
                                <div class="col-md-4"><fieldset><legend class="tw-text-sm tw-font-semibold"><?= e($definition['name']); ?></legend>
                                <?php foreach($definition['capabilities'] as $capability=>$label){if(!in_array($capability,$allowed,true))continue;$inputId='native-link-'.preg_replace('/[^a-z0-9_-]/i','-',$feature.'-'.$capability); ?>
                                    <div class="checkbox checkbox-primary"><input id="<?= e($inputId); ?>" type="checkbox" name="permissions[<?= e($feature); ?>][]" value="<?= e($capability); ?>"><label for="<?= e($inputId); ?>"><?= e($label); ?></label></div>
                                <?php } ?></fieldset></div>
                            <?php } ?>
                            </div>
                            <div class="checkbox checkbox-primary"><input id="native-link-default" type="checkbox" name="make_default" value="1"><label for="native-link-default"><?= e(_l('issuer_staff_make_company_default')); ?></label></div>
                            <button type="submit" class="btn btn-primary"><?= e(_l('issuer_staff_link_existing')); ?></button>
                            <?= form_close(); ?>
                        </details>
                    </div>
                </div>
                <?php } ?>
                <div class="panel_s">
                    <div class="panel-body panel-table-full">
                        <?php
                        $table_data = [
                            _l('staff_dt_name'),
                            _l('staff_dt_email'),
                            _l('role'),
                            _l('staff_dt_last_Login'),
                            _l('staff_dt_active'),
                        ];
                        $custom_fields = $nativeStaff?$native_staff_table_fields:get_custom_fields('staff', ['show_on_table' => 1]);
                        foreach ($custom_fields as $field) {
                            array_push($table_data, [
                                'name'     => $field['name'],
                                'th_attrs' => ['data-type' => $field['type'], 'data-custom-field' => 1],
                            ]);
                        }
                        render_datatable($table_data, 'staff');
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php if(!$nativeStaff){ ?><div class="modal fade" id="delete_staff" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <?php echo form_open(admin_url('staff/delete', ['delete_staff_form'])); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?php echo _l('delete_staff'); ?></h4>
            </div>
            <div class="modal-body">
                <div class="delete_id">
                    <?php echo form_hidden('id'); ?>
                </div>
                <p><?php echo _l('delete_staff_info'); ?></p>
                <?php
                echo render_select('transfer_data_to', $staff_members, ['staffid', ['firstname', 'lastname']], 'staff_member', get_staff_user_id(), [], [], '', '', false);
                ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                <button type="submit" class="btn btn-danger _delete"><?php echo _l('confirm'); ?></button>
            </div>
        </div><!-- /.modal-content -->
        <?php echo form_close(); ?>
    </div><!-- /.modal-dialog -->
</div><!-- /.modal --><?php } ?>
<?php init_tail(); ?>
<script>
$(function() {
    initDataTable('.table-staff', window.location.href);
});

function delete_staff_member(id) {
    $('#delete_staff').modal('show');
    $('#transfer_data_to').find('option').prop('disabled', false);
    $('#transfer_data_to').find('option[value="' + id + '"]').prop('disabled', true);
    $('#delete_staff .delete_id input').val(id);
    $('#transfer_data_to').selectpicker('refresh');
}
</script>
</body>

</html>
