<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div role="tabpanel" class="tab-pane" id="company_info">
    <?php
    $settingsCertificateContext = class_exists('CompanyRequestScope', false) ? CompanyRequestScope::context() : null;
    if ($settingsCertificateContext && $settingsCertificateContext->issuerId() !== null
        && class_exists('CompanySettingsRuntime', false) && CompanySettingsRuntime::active() && CompanySettingsRuntime::can('edit')) {
    ?>
    <p><a class="btn btn-default" href="<?= htmlspecialchars(CompanyRequestScope::adminUrl($this, 'settings?group=certificates'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Certificado A1</a></p>
    <?php } ?>
    <div class="alert alert-info">
        <?php echo _l('settings_sales_company_info_note'); ?>
    </div>
    <?php
    $nativeCompanySettings=!empty($native_settings);
    if($nativeCompanySettings){
        if(!is_array($native_settings_company_values??null)||!is_array($native_settings_company_field_definitions??null))throw new LogicException('Native company Settings values are required');
        $companyIdentityOption=static fn($name)=>$native_settings_company_values[$name]??'';
        $companyIdentityNative=true;
    }else{$companyIdentityOption=static fn($name)=>get_option($name);}
    require __DIR__.'/identity_fields.php'; unset($companyIdentityOption,$companyIdentityNative);
    ?>
    <?php echo render_custom_fields('company', 0); ?>
    <hr />
    <?php $companyInfoFormat=$nativeCompanySettings?$native_settings_company_values['company_info_format']:get_option('company_info_format'); echo render_textarea('settings[company_info_format]', 'company_info_format', clear_textarea_breaks($companyInfoFormat), ['rows' => 8, 'style' => 'line-height:20px;']); ?>
    <p>
        <a href="#" class="settings-textarea-merge-field" data-to="company_info_format">{company_name}</a>
        <a href="#" class="settings-textarea-merge-field" data-to="company_info_format">{address}</a>,
        <a href="#" class="settings-textarea-merge-field" data-to="company_info_format">{city}</a>,
        <a href="#" class="settings-textarea-merge-field" data-to="company_info_format">{state}</a>,
        <a href="#" class="settings-textarea-merge-field" data-to="company_info_format">{zip_code}</a>,
        <a href="#" class="settings-textarea-merge-field" data-to="company_info_format">{country_code}</a>,
        <a href="#" class="settings-textarea-merge-field" data-to="company_info_format">{phone}</a>,
        <a href="#" class="settings-textarea-merge-field" data-to="company_info_format">{vat_number}</a>,
        <a href="#" class="settings-textarea-merge-field" data-to="company_info_format">{vat_number_with_label}</a>
    </p>
    <?php $custom_company_fields = $nativeCompanySettings ? $native_settings_company_field_definitions : get_company_custom_fields();
    if (count($custom_company_fields) > 0) {
        echo '<hr />';
        echo '<p class="font-medium"><b>' . _l('custom_fields') . '</b></p>';
        echo '<ul class="list-group">';
        foreach ($custom_company_fields as $field) {
            $fieldId=$nativeCompanySettings?(int)$field['source_field_id']:(int)$field['id'];
            $fieldName=htmlspecialchars((string)$field['name'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
            echo '<li class="list-group-item"><b>' . $fieldName . '</b>: ' . '<a href="#" class="settings-textarea-merge-field" data-to="company_info_format">{cf_' . $fieldId . '}</a></li>';
        }
        echo '</ul>';
        echo '<hr />';
    }
    ?>
</div>
