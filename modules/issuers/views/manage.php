<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content">
    <h1 class="tw-text-xl"><?php echo _l('issuers_title'); ?></h1>
    <div class="alert alert-warning" role="alert"><?php echo _l('issuer_foundation_warning'); ?></div>
    <div class="row"><div class="col-md-4"><div class="panel_s"><div class="panel-body">
        <h2 class="tw-text-lg"><?php echo _l('issuer_catalog'); ?></h2>
        <ul class="list-unstyled">
        <?php foreach ($catalog as $entry) { ?>
            <li class="tw-mb-2"><a href="<?php echo admin_url('issuers/index/' . (int) $entry['id']); ?>"><?php echo html_escape($entry['legal_name']); ?></a>
                <span class="label label-default"><?php echo (int) $entry['active'] === 1 ? _l('issuer_active') : _l('issuer_inactive'); ?></span>
                <?php if ((int) $entry['id'] === $default_issuer) { ?><span class="label label-info"><?php echo _l('issuer_default'); ?></span><?php } ?>
            </li>
        <?php } ?>
        </ul>
        <?php if (is_admin()) { ?><a class="btn btn-default" href="<?php echo admin_url('issuers'); ?>"><?php echo _l('issuer_new'); ?></a><?php } ?>
    </div></div></div><div class="col-md-8">
    <?php if (is_admin()) { ?>
        <div class="panel_s"><div class="panel-body">
            <h2 class="tw-text-lg"><?php echo $selected ? _l('issuer_edit') : _l('issuer_new'); ?></h2>
            <?php echo form_open(admin_url('issuers/save')); ?>
            <input type="hidden" name="issuers_csrf" value="<?php echo html_escape($issuers_csrf); ?>">
            <input type="hidden" name="action" value="issuer">
            <input type="hidden" name="issuer_id" value="<?php echo $selected ? (int) $selected['id'] : ''; ?>">
            <div class="form-group"><label for="legal_name"><?php echo _l('issuer_legal_name'); ?></label>
                <input class="form-control" id="legal_name" name="legal_name" required maxlength="191" value="<?php echo html_escape($selected['legal_name'] ?? ''); ?>"></div>
            <div class="form-group"><label for="tax_id"><?php echo _l('issuer_tax_id'); ?></label>
                <input class="form-control" id="tax_id" name="tax_id" required maxlength="20" value="<?php echo html_escape($selected['tax_id'] ?? ''); ?>"></div>
            <div class="form-group"><label for="active"><?php echo _l('issuer_status'); ?></label>
                <select class="form-control" id="active" name="active">
                    <option value="1" <?php echo !$selected || (int) $selected['active'] === 1 ? 'selected' : ''; ?>><?php echo _l('issuer_active'); ?></option>
                    <option value="0" <?php echo $selected && (int) $selected['active'] === 0 ? 'selected' : ''; ?>><?php echo _l('issuer_inactive'); ?></option>
                </select></div>
            <button class="btn btn-primary" type="submit"><?php echo _l('issuer_save'); ?></button>
            <?php echo form_close(); ?>
        </div></div>
    <?php } ?>
    <?php if ($selected) { $id = (int) $selected['id']; ?>
        <?php if (issuers_can_access($id, get_staff_user_id())) { ?>
        <div class="panel_s"><div class="panel-body">
            <h2 class="tw-text-lg"><?php echo html_escape($selected['legal_name']); ?></h2>
            <p><?php echo _l('issuer_default_explanation'); ?></p>
            <?php echo form_open(admin_url('issuers/save')); ?>
            <input type="hidden" name="issuers_csrf" value="<?php echo html_escape($issuers_csrf); ?>"><input type="hidden" name="action" value="default"><input type="hidden" name="issuer_id" value="<?php echo $id; ?>">
            <button class="btn btn-default" type="submit"><?php echo _l('issuer_choose_default'); ?></button><?php echo form_close(); ?>
        </div></div>
        <?php } ?>
        <?php if (is_admin()) { ?>
        <div class="panel_s"><div class="panel-body">
            <h2 class="tw-text-lg"><?php echo _l('issuer_members'); ?></h2>
            <p><?php echo _l('issuer_permissions_explanation'); ?></p>
            <ul class="list-unstyled"><?php foreach ($members as $member) { ?>
                <li class="tw-mb-3"><?php echo html_escape($member['firstname'] . ' ' . $member['lastname']); ?>
                    <small><?php foreach (IssuerPolicy::CAPABILITIES as $cap) { if ((int) $member['can_' . $cap] === 1) { echo html_escape(_l('issuer_permission_' . $cap)) . ' · '; } } ?></small>
                    <?php echo form_open(admin_url('issuers/save')); ?>
                    <input type="hidden" name="issuers_csrf" value="<?php echo html_escape($issuers_csrf); ?>"><input type="hidden" name="action" value="remove_member"><input type="hidden" name="issuer_id" value="<?php echo $id; ?>"><input type="hidden" name="staff_id" value="<?php echo (int) $member['staff_id']; ?>">
                    <button class="btn btn-danger btn-xs" type="submit"><?php echo _l('issuer_remove_member'); ?></button><?php echo form_close(); ?>
                </li>
            <?php } ?></ul>
            <?php echo form_open(admin_url('issuers/save')); ?>
            <input type="hidden" name="issuers_csrf" value="<?php echo html_escape($issuers_csrf); ?>"><input type="hidden" name="action" value="member"><input type="hidden" name="issuer_id" value="<?php echo $id; ?>">
            <label for="staff_id"><?php echo _l('issuer_staff'); ?></label><select class="form-control" name="staff_id" id="staff_id" required>
                <?php foreach ($staff as $person) { ?><option value="<?php echo (int) $person['staffid']; ?>"><?php echo html_escape($person['firstname'] . ' ' . $person['lastname']); ?></option><?php } ?>
            </select>
            <?php foreach (IssuerPolicy::CAPABILITIES as $cap) { ?><div class="checkbox"><label><input type="checkbox" name="permissions[]" value="<?php echo $cap; ?>" <?php echo $cap === 'view' ? 'checked' : ''; ?>><?php echo _l('issuer_permission_' . $cap); ?></label></div><?php } ?>
            <button class="btn btn-primary" type="submit"><?php echo _l('issuer_save_member'); ?></button><?php echo form_close(); ?>
        </div></div>
        <?php } ?>
        <?php if (issuers_can_access($id, get_staff_user_id())) { ?>
        <div class="panel_s"><div class="panel-body">
            <h2 class="tw-text-lg"><?php echo _l('issuer_clients'); ?></h2>
            <p><?php echo _l('issuer_clients_explanation'); ?></p>
            <?php if (is_admin() && issuers_can_access($id, get_staff_user_id(), 'manage_clients')) { ?>
                <?php echo form_open(admin_url('issuers/save')); ?>
                <input type="hidden" name="issuers_csrf" value="<?php echo html_escape($issuers_csrf); ?>"><input type="hidden" name="action" value="register_client"><input type="hidden" name="issuer_id" value="<?php echo $id; ?>">
                <label for="client_id"><?php echo _l('issuer_existing_client_id'); ?></label><input class="form-control" id="client_id" name="client_id" type="number" min="1" step="1" required>
                <button class="btn btn-default tw-mt-2" type="submit"><?php echo _l('issuer_register_private'); ?></button><?php echo form_close(); ?>
            <?php } ?>
            <hr>
            <?php foreach ($clients as $client) { $clientId = (int) $client['userid']; ?>
                <div class="tw-mb-4"><strong><?php echo html_escape($client['company']); ?></strong> (#<?php echo $clientId; ?>)
                <p><?php echo html_escape($client['vat']); ?></p>
                <?php if (isset($targets[$clientId])) { ?>
                    <p class="text-muted"><?php echo _l('issuer_current_target_ids') . ' ' . html_escape(implode(', ', $targets[$clientId])); ?></p>
                    <?php echo form_open(admin_url('issuers/save')); ?>
                    <input type="hidden" name="issuers_csrf" value="<?php echo html_escape($issuers_csrf); ?>"><input type="hidden" name="action" value="share_client"><input type="hidden" name="issuer_id" value="<?php echo $id; ?>"><input type="hidden" name="client_id" value="<?php echo $clientId; ?>">
                    <label for="targets_<?php echo $clientId; ?>"><?php echo _l('issuer_share_targets'); ?></label>
                    <select class="form-control" name="targets[]" id="targets_<?php echo $clientId; ?>" multiple>
                    <?php foreach ($available as $target) { if ((int) $target['id'] !== $id && issuers_can_access($target['id'], get_staff_user_id(), 'manage_clients')) { ?>
                        <option value="<?php echo (int) $target['id']; ?>" <?php echo in_array((int) $target['id'], $targets[$clientId], true) ? 'selected' : ''; ?>><?php echo html_escape($target['legal_name']); ?></option>
                    <?php } } ?>
                    </select><p class="text-muted"><?php echo _l('issuer_share_explanation'); ?></p>
                    <button class="btn btn-default" type="submit"><?php echo _l('issuer_save_sharing'); ?></button><?php echo form_close(); ?>
                <?php } else { ?><p class="text-muted"><?php echo _l('issuer_readonly_client'); ?></p><?php } ?>
                </div>
            <?php } ?>
        </div></div>
        <?php } ?>
    <?php } ?>
    </div></div>
</div></div>
<?php init_tail(); ?>
</body></html>
