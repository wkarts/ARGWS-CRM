<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content">
    <h1 class="tw-text-xl"><?php echo _l('issuers_title'); ?></h1>
    <div class="alert alert-warning" role="alert"><?php echo _l('issuer_foundation_warning'); ?></div>
    <?php if (!$can_create && is_admin()) { ?><p><a href="<?php echo admin_url('company_master'); ?>">Resolver a responsabilidade da instalação legada</a></p><?php } ?>
    <?php if(class_exists('CompanySettingsRuntime',false)&&CompanySettingsRuntime::active()){ ?>
    <nav aria-label="Operações da empresa selecionada">
    <?php if(CompanySettingsRuntime::canResource('customers','view')){ ?><p><a href="<?php echo admin_url('company_clients'); ?>">Clientes da empresa selecionada</a></p><?php } ?>
    <?php if(CompanySettingsRuntime::canResource('invoices','view')){ ?><p><a href="<?php echo admin_url('company_invoices'); ?>">Faturas da empresa selecionada</a></p><?php } ?>
    </nav><?php } ?>
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
    <?php if ($can_manage && $selected) { ?>
        <div class="panel_s"><div class="panel-body">
            <h2 class="tw-text-lg"><?php echo $selected ? _l('issuer_edit') : _l('issuer_new'); ?></h2>
            <?php if ($selected && ($selected['identity_status'] ?? 'complete') === 'pending') { ?><p class="alert alert-warning"><?php echo _l('issuer_primary_identity_pending'); ?></p><?php } ?>
            <?php echo form_open(admin_url('issuers/save')); ?>
            <input type="hidden" name="issuers_csrf" value="<?php echo html_escape($issuers_csrf); ?>">
            <input type="hidden" name="action" value="issuer">
            <input type="hidden" name="issuer_id" value="<?php echo $selected ? (int) $selected['id'] : ''; ?>">
            <?php $identity_mode='registry';$identity_values=['legal_name'=>$selected['legal_name']??'','tax_id'=>$selected['tax_id']??'','profile'=>$profile];$lookup_url=admin_url('issuers/lookup');$lookup_token=$issuers_csrf;$identity_profile_disabled=$selected&&(int)$selected['active']!==1;require APPPATH.'views/admin/companies/identity_fields.php'; ?>
            <div class="form-group"><label for="active">Situação</label><select class="form-control" id="active" name="active"><option value="1" <?= !$selected||(int)$selected['active']===1?'selected':''; ?>>Ativa</option><option value="0" <?= $selected&&(int)$selected['active']===0?'selected':''; ?>>Inativa</option></select></div>
            <button class="btn btn-primary" type="submit"><?php echo _l('issuer_save'); ?></button>
            <?php echo form_close(); ?>
<?php require APPPATH.'views/admin/issuers/certificate_link.php'; ?>
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
