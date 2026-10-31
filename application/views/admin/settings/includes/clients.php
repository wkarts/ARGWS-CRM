<?php defined('BASEPATH') or exit('No direct script access allowed');
$clientsSettings=$native_settings_clients??null;
if($clientsSettings!==null)$contacts_permissions=NativeCompanyClientsSettings::contactPermissions(static fn($label)=>_l($label));
$clientsOption=static function(string$key)use($clientsSettings){if($clientsSettings!==null){if(!array_key_exists($key,$clientsSettings['values']))throw new DomainException('native_clients_settings_invalid');return $clientsSettings['values'][$key];}return get_option($key);};
?>
<?php echo form_hidden('settings[customer_settings]', 'true'); ?>
<div class="form-group">
	<label for="clients_default_theme" class="control-label"><?php echo _l('settings_clients_default_theme'); ?></label>
	<select name="settings[clients_default_theme]" id="clients_default_theme" class="form-control selectpicker" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">
		<?php foreach (($clientsSettings!==null?$clientsSettings['themes']:get_all_client_themes()) as $theme) { ?>
		<option value="<?php echo e($theme); ?>" <?php if (($clientsSettings!==null?$clientsOption('clients_default_theme'):active_clients_theme()) == $theme) {
    echo 'selected';
} ?>><?php echo e(ucfirst($theme)); ?></option>
		<?php } ?>
	</select>
</div>
<hr />
<?php echo render_select('settings[customer_default_country]', ($clientsSettings!==null?$clientsSettings['countries']:get_all_countries()), [ 'country_id', [ 'short_name']], 'customer_default_country', $clientsOption('customer_default_country')); ?>
<hr />
<?php
    $tabs = $clientsSettings!==null?array_map(static fn($label)=>['name'=>_l($label)],$clientsSettings['tabs']):get_customer_profile_tabs();
    $current_tabs = $clientsOption('visible_customer_profile_tabs');
    if ($current_tabs != 'all') {
        $current_tabs = $clientsSettings!==null?NativeCompanyClientsSettings::visibleTabs($current_tabs):unserialize($current_tabs,['allowed_classes'=>false]);
    }
?>
<div class="form-group">
	<label for="visible_customer_profile_tabs" class="control-label"><?php echo _l('visible_tabs'); ?> (<?php echo _l('client_add_edit_profile'); ?>)</label>
	<select name="settings[visible_customer_profile_tabs][]" id="visible_customer_profile_tabs" multiple class="form-control selectpicker" data-none-selected-text="<?php echo _l('all'); ?>" data-actions-box="true">
		<?php foreach ($tabs as $tabKey => $tab) {
    if ($tabKey == 'profile' || $tabKey == 'contacts') {
        continue;
    } ?>
			<option value="<?php echo e($tabKey); ?>"<?php if ((is_array($current_tabs)
            && array_key_exists($tabKey, $current_tabs) && $current_tabs[$tabKey] == true) || (is_array($current_tabs) && !array_key_exists($tabKey, $current_tabs))) {
        echo ' selected';
    } ?>>
				<?php echo e($tab['name']); ?>
			</option>
		<?php } ?>
	</select>
</div>
<hr />
<div class="form-group">
	<label for="required_register_fields" class="control-label"><?php echo _l('required_register_fields'); ?></label>
	<select name="settings[required_register_fields][]" id="required_register_fields" multiple class="form-control selectpicker" data-none-selected-text="<?php echo _l('all'); ?>" data-actions-box="true">
		<?php 
		$optionFields = $clientsSettings!==null?NativeCompanyClientsSettings::registrationFields($clientsSettings['values'],static fn($label)=>_l($label)):get_required_fields_for_registration();
		foreach($optionFields['contact'] as $key => $field) { ?>
			<option value="<?php echo $key; ?>" <?php echo $field['is_required'] ? 'selected' : '';?> <?php echo $field['disabled'] ? 'disabled' : ''; ?>>
			<?php echo $field['label'] . ' - ' . _l('contact'); ?>
		</option>
		<?php } ?> 
		<?php foreach($optionFields['company'] as $key => $field) { ?>
			<option value="<?php echo $key; ?>" <?php echo $field['is_required'] ? 'selected' : '';?> <?php echo $field['disabled'] ? 'disabled' : ''; ?>>
			<?php echo $field['label'] . ' - ' . _l('client_company'); ?>
		</option>
		<?php } ?> 
	</select>
</div>
<hr />
<?php if($clientsSettings!==null){render_yes_no_option('company_is_required', 'company_is_required', '', '', '', '', '', $clientsOption('company_is_required'));}else{render_yes_no_option('company_is_required', 'company_is_required', '');} ?>
<hr />
<?php if($clientsSettings!==null){render_yes_no_option('company_requires_vat_number_field', 'company_requires_vat_number_field', '', '', '', '', '', $clientsOption('company_requires_vat_number_field'));}else{render_yes_no_option('company_requires_vat_number_field', 'company_requires_vat_number_field', '');} ?>
<hr />
<?php if($clientsSettings!==null){render_yes_no_option('allow_registration', 'settings_clients_allow_registration', '', '', '', '', '', $clientsOption('allow_registration'));}else{render_yes_no_option('allow_registration', 'settings_clients_allow_registration', '');} ?>
<hr />
<?php if($clientsSettings!==null){render_yes_no_option('customers_register_require_confirmation', 'customers_register_require_confirmation', '', '', '', '', '', $clientsOption('customers_register_require_confirmation'));}else{render_yes_no_option('customers_register_require_confirmation', 'customers_register_require_confirmation', '');} ?>
<hr />
<?php if($clientsSettings!==null){render_yes_no_option('allow_primary_contact_to_manage_other_contacts', 'allow_primary_contact_to_manage_other_contacts', '', '', '', '', '', $clientsOption('allow_primary_contact_to_manage_other_contacts'));}else{render_yes_no_option('allow_primary_contact_to_manage_other_contacts', 'allow_primary_contact_to_manage_other_contacts', '');} ?>
<hr />
<?php if($clientsSettings!==null){render_yes_no_option('enable_honeypot_spam_validation', 'enable_honeypot_spam_validation', '', '', '', '', '', $clientsOption('enable_honeypot_spam_validation'));}else{render_yes_no_option('enable_honeypot_spam_validation', 'enable_honeypot_spam_validation', '');} ?>
<hr />
<?php if($clientsSettings!==null){render_yes_no_option('allow_primary_contact_to_view_edit_billing_and_shipping', 'allow_primary_contact_to_view_edit_billing_and_shipping', '', '', '', '', '', $clientsOption('allow_primary_contact_to_view_edit_billing_and_shipping'));}else{render_yes_no_option('allow_primary_contact_to_view_edit_billing_and_shipping', 'allow_primary_contact_to_view_edit_billing_and_shipping', '');} ?>
<hr />
<?php if($clientsSettings!==null){render_yes_no_option('only_own_files_contacts', 'only_own_files_contacts', 'only_own_files_contacts_help', '', '', '', '', $clientsOption('only_own_files_contacts'));}else{render_yes_no_option('only_own_files_contacts', 'only_own_files_contacts', 'only_own_files_contacts_help');} ?>
<hr />
<?php if($clientsSettings!==null){render_yes_no_option('allow_contact_to_delete_files', 'allow_contact_to_delete_files', '', '', '', '', '', $clientsOption('allow_contact_to_delete_files'));}else{render_yes_no_option('allow_contact_to_delete_files', 'allow_contact_to_delete_files', '');} ?>
<hr />
<?php if($clientsSettings!==null){render_yes_no_option('use_knowledge_base', 'settings_general_use_knowledgebase', 'settings_general_use_knowledgebase_tooltip', '', '', '', '', $clientsOption('use_knowledge_base'));}else{render_yes_no_option('use_knowledge_base', 'settings_general_use_knowledgebase', 'settings_general_use_knowledgebase_tooltip');} ?>
<hr />
<?php if($clientsSettings!==null){render_yes_no_option('knowledge_base_without_registration', 'settings_clients_allow_kb_view_without_registration', '', '', '', '', '', $clientsOption('knowledge_base_without_registration'));}else{render_yes_no_option('knowledge_base_without_registration', 'settings_clients_allow_kb_view_without_registration', '');} ?>
<hr />
<div class="form-group">
	<?php
    if($clientsSettings!==null){$estimateRequestForms=$clientsSettings['estimate_request_forms'];}else{$this->load->model('estimate_request_model');$estimateRequestForms=$this->estimate_request_model->get_forms();}
    ?>
	<label for="show_estimate_request_in_customers_area"
		class="control-label">
		<?php echo _l('show_estimate_request_in_customers_area'); ?>
	</label>
	<select name="settings[show_estimate_request_in_customers_area]"
		id="show_estimate_request_in_customers_area"
		class="form-control selectpicker"
		data-none-selected-text="<?php echo _l('settings_no'); ?>">
			<option value="0"<?php if ($clientsOption('show_estimate_request_in_customers_area') == 0) {
        echo ' selected';
    } ?>>
				<?php echo _l('settings_no'); ?>
			</option>
		<?php foreach ($estimateRequestForms as $form) { ?>
			<option value="<?php echo e($form['id']); ?>"<?php if ($clientsOption('show_estimate_request_in_customers_area') == $form['id']) {
        echo ' selected';
    } ?>>
				<?php echo e($form['name']); ?>
			</option>
		<?php } ?>
	</select>
</div>
<?php $default_contact_permissions = $clientsSettings!==null?NativeCompanyContactPreferences::permissions($clientsOption('default_contact_permissions')):unserialize($clientsOption('default_contact_permissions'),['allowed_classes'=>false]); ?>
<div class="form-group">
	<label for="" class="control-label"><?php echo _l('default_contact_permissions'); ?></label>
	<?php foreach ($contacts_permissions as $p) { ?>
	<div class="checkbox checkbox-primary">
		<input type="checkbox" name="settings[default_contact_permissions][]" <?php if (is_array($default_contact_permissions) && in_array($p['id'], $default_contact_permissions)) {
        echo 'checked';
    } ?> id="dcp_<?php echo e($p['id']); ?>" value="<?php echo e($p['id']); ?>">
		<label for="dcp_<?php echo e($p['id']); ?>"><?php echo e($p['name']); ?></label>
	</div>
	<?php } ?>
</div>
<hr />
<i class="fa-regular fa-circle-question pull-left tw-mt-0.5 tw-mr-1" data-toggle="tooltip" data-title="<?php echo _l('invoices') . ', ' . _l('estimates') . ', ' . _l('payments') . ', ' . _l('customer_statement'); ?>"></i>
<?php echo render_textarea('settings[customer_info_format]', 'customer_info_format', ($clientsSettings!==null?e($clientsOption('customer_info_format')):clear_textarea_breaks($clientsOption('customer_info_format'))), ['rows' => 8, 'style' => 'line-height:20px;']); ?>
<p>
	<a href="#" class="settings-textarea-merge-field" data-to="customer_info_format">{company_name}</a>,
	<a href="#" class="settings-textarea-merge-field" data-to="customer_info_format">{customer_id}</a>,
	<a href="#" class="settings-textarea-merge-field" data-to="customer_info_format">{street}</a>,
	<a href="#" class="settings-textarea-merge-field" data-to="customer_info_format">{city}</a>,
	<a href="#" class="settings-textarea-merge-field" data-to="customer_info_format">{state}</a>,
	<a href="#" class="settings-textarea-merge-field" data-to="customer_info_format">{zip_code}</a>,
	<a href="#" class="settings-textarea-merge-field" data-to="customer_info_format">{country_code}</a>,
	<a href="#" class="settings-textarea-merge-field" data-to="customer_info_format">{country_name}</a>,
	<a href="#" class="settings-textarea-merge-field" data-to="customer_info_format">{phone}</a>,
	<a href="#" class="settings-textarea-merge-field" data-to="customer_info_format">{vat_number}</a>,
	<a href="#" class="settings-textarea-merge-field" data-to="customer_info_format">{vat_number_with_label}</a>

</p>
<?php $custom_fields = $clientsSettings!==null?$clientsSettings['customer_field_definitions']:get_custom_fields('customers');
if (count($custom_fields) > 0) {
    echo '<hr />';
    echo '<p class="no-mbot font-medium"><b>' . _l('custom_fields') . '</b></p>';
    if (($clientsSettings!==null?count(array_filter($custom_fields,static fn($field)=>(int)$field['show_on_client_portal']===1)):total_rows(db_prefix() . 'customfields', ['fieldto' => 'customers', 'show_on_client_portal' => 1])) == 0) {
        echo '<p>' . _l('custom_field_pdf_html_help') . '</p>';
        echo '<hr />';
    }
    echo '<ul class="list-group">';
    foreach ($custom_fields as $field) {
        echo '<li class="list-group-item"><b>' . e($field['name']) . '</b>: ' . '<a href="#" class="settings-textarea-merge-field" data-to="customer_info_format">{cf_' . $field['id'] . '}</a></li>';
    }
    echo '</ul>';
    echo '<hr />';
}
?>

<?php if(class_exists('CompanySettingsRuntime',false)&&CompanySettingsRuntime::active()&&CompanySettingsRuntime::can('view')){?><p><a href="<?= htmlspecialchars(CompanyRequestScope::adminUrl($this,'settings?group=project_files'),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); ?>">Política própria de arquivos de projetos desta empresa</a></p><p>A exclusão de arquivos de projetos também respeita as permissões de publicação e acesso do projeto. Alterar esta opção não concede acesso a outros clientes ou empresas.</p><?php }?>
