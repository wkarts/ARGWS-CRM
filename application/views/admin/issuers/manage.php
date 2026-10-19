<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content">
    <h1 class="tw-text-xl"><?php echo _l('issuers_title'); ?></h1>
    <div class="alert alert-warning" role="alert"><?php echo _l('issuer_foundation_warning'); ?></div>
    <?php if (!$can_create && is_admin()) { ?><p><a href="<?php echo admin_url('company_master'); ?>">Resolver a responsabilidade da instalação legada</a></p><?php } ?>
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
        <?php if ($can_create) { ?><a class="btn btn-default" href="<?php echo admin_url('company_onboarding'); ?>"><?php echo _l('issuer_new'); ?></a><?php } ?>
    </div></div></div><div class="col-md-8">
    <?php if ($can_manage) { ?>
        <div class="panel_s"><div class="panel-body">
            <h2 class="tw-text-lg"><?php echo $selected ? _l('issuer_edit') : _l('issuer_new'); ?></h2>
            <?php if ($selected && ($selected['identity_status'] ?? 'complete') === 'pending') { ?><p class="alert alert-warning"><?php echo _l('issuer_primary_identity_pending'); ?></p><?php } ?>
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
            <fieldset <?php echo $selected && (int)$selected['active']!==1 ? 'disabled' : ''; ?>><legend>Informações da empresa</legend>
            <?php foreach (['invoice_company_address'=>'Endereço','invoice_company_city'=>'Cidade','company_state'=>'Estado','invoice_company_country_code'=>'País','invoice_company_postal_code'=>'CEP','invoice_company_phonenumber'=>'Telefone'] as $field=>$label) { ?>
                <div class="form-group"><label for="company_profile_<?php echo html_escape($field); ?>"><?php echo html_escape($label); ?></label>
                    <input class="form-control" id="company_profile_<?php echo html_escape($field); ?>" name="company_profile[<?php echo html_escape($field); ?>]" maxlength="2000" value="<?php echo html_escape($profile[$field] ?? ''); ?>"></div>
            <?php } ?>
            </fieldset>
            <button class="btn btn-primary" type="submit"><?php echo _l('issuer_save'); ?></button>
            <?php echo form_close(); ?>
        </div></div>
    <?php } ?>
    <?php if ($selected) { $id = (int) $selected['id']; ?>
        <?php if (is_admin()) { ?><p><a href="<?php echo admin_url('staff'); ?>"><?php echo _l('issuer_staff_access_location'); ?></a></p><?php } ?>
        <p class="text-muted"><?php echo _l('issuer_client_adoption_blocked'); ?></p>
    <?php } ?>
    </div></div>
</div></div>
<?php init_tail(); ?>
</body></html>
