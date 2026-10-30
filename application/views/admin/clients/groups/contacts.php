<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php $native_customers=$native_customers??false;$nativeIndividual=$native_customers?($native_contact_metadata['company']===''):false; ?>
<?php if (isset($client)) { ?>
<h4 class="customer-profile-group-heading">
    <?= e(($native_customers?$nativeIndividual:is_empty_customer_company($client->userid)) ? _l('contact') : _l('customer_contacts')); ?>
</h4>
<?php if ($this->session->flashdata('gdpr_delete_warning')) { ?>
<div class="alert alert-warning">
    [GDPR] The contact you removed has associated proposals using the email address of the contact and other personal
    information. You may want to re-check all proposals related to this customer and remove any personal data from
    proposals linked to this contact.
</div>
<?php } ?>
<?php if (($native_customers?$native_contact_create:(staff_can('create', 'customers') || is_customer_admin($client->userid))) && $client->registration_confirmed == '1') {
    $disable_new_contacts = false;
    if (($native_customers?$nativeIndividual:is_empty_customer_company($client->userid)) && ($native_customers?$native_contact_metadata['total']:total_rows(db_prefix() . 'contacts', ['userid' => $client->userid])) == 1) {
        $disable_new_contacts = true;
    } ?>
<div class="inline-block new-contact-wrapper"
    data-title="<?= _l('customer_contact_person_only_one_allowed'); ?>"
    <?php if ($disable_new_contacts) { ?> data-toggle="tooltip"
    <?php } ?>>
    <a href="#"
        onclick="contact(<?= e($client->userid); ?>); return false;"
        class="btn btn-primary new-contact mbot15<?php if ($disable_new_contacts) {
            echo ' disabled';
        } ?>">
        <i class="fa-regular fa-plus tw-mr-1"></i>
        <?= _l('new_contact'); ?>
    </a>
</div>
<?php
} ?>
<?php
    $table_data = [_l('clients_list_full_name')];
    if ($native_customers?$native_contact_consent_enabled:(is_gdpr() && get_option('gdpr_enable_consent_for_contacts') == '1')) {
        array_push($table_data, [
            'name'     => _l('gdpr_consent') . ' (' . _l('gdpr_short') . ')',
            'th_attrs' => ['id' => 'th-consent', 'class' => 'not-export'],
        ]);
    }
    $table_data    = array_merge($table_data, [_l('client_email'), _l('contact_position'), _l('client_phonenumber'), _l('contact_active'), _l('clients_list_last_login')]);
    $custom_fields = $native_customers?$native_contact_table_fields:get_custom_fields('contacts', ['show_on_table' => 1]);

    foreach ($custom_fields as $field) {
        array_push($table_data, [
            'name'     => $field['name'],
            'th_attrs' => ['data-type' => $field['type'], 'data-custom-field' => 1],
        ]);
    }
    echo render_datatable($table_data, 'contacts'); ?>
<?php } ?>
<div id="contact_data"></div>
<div id="consent_data"></div>
<?php if($native_customers&&!empty($native_contact_invoice_rows)){ ?>
<div class="panel_s tw-mt-4" id="native-contact-invoice-access"><div class="panel-body">
<h4 class="tw-font-semibold tw-text-base"><?= e(_l('issuer_contact_invoice_access')); ?></h4>
<p class="text-muted"><?= e(_l('issuer_contact_invoice_access_help')); ?></p>
<div class="table-responsive"><table class="table"><thead><tr><th><?= e(_l('contact')); ?></th><th><?= e(_l('client_email')); ?></th><th><?= e(_l('status')); ?></th><th><?= e(_l('options')); ?></th></tr></thead><tbody>
<?php foreach($native_contact_invoice_rows as$contact){$grant=$native_contact_invoice_access[(int)$contact['id']]??null;if(!$grant)continue; ?>
<tr><td><?= e(trim($contact['firstname'].' '.$contact['lastname'])); ?></td><td><?= e($contact['email']); ?></td><td><?= e(_l($grant['allowed']?'active':'inactive')); ?></td><td>
<?php if($native_contact_invoice_manage){ ?><?= form_open(admin_url('clients/contact_invoice_access/'.$client->userid.'/'.(int)$contact['id']),['class'=>'inline-block']); ?><?= form_hidden('_native_company_csrf',$company_clients_csrf); ?><?= form_hidden('revision',$grant['revision']); ?><?= form_hidden('allowed',$grant['allowed']?'0':'1'); ?><button type="submit" class="btn btn-default btn-sm" <?= !$grant['allowed']&&(int)$contact['active']!==1?'disabled':''; ?>><?= e(_l($grant['allowed']?'issuer_contact_invoice_revoke':'issuer_contact_invoice_grant')); ?></button><?= form_close(); ?><?php }else{ ?>—<?php } ?>
</td></tr><?php } ?>
</tbody></table></div></div></div>
<?php } ?>
