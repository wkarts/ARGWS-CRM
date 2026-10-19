<?php

defined('BASEPATH') or exit('No direct script access allowed');
require_once APPPATH . 'services/CompanySettingsRuntime.php';

class Settings_model extends App_Model
{
    private $encrypted_fields = ['smtp_password', 'microsoft_mail_client_secret', 'google_mail_client_secret'];

    public function __construct()
    {
        if (class_exists('CompanyRequestScope', false) && CompanyRequestScope::sealedFor(get_instance())
            && CompanySettingsRuntime::active() && !CompanySettingsRuntime::isPrimary()) {
            // The sealed request scope already validated staff/company/core before autoload.
            // App_Model would dispatch global model_init hooks before this narrow renderer.
            CI_Model::__construct();
            return;
        }
        parent::__construct();
        $payment_gateways = $this->payment_modes_model->get_payment_gateways(true);

        foreach ($payment_gateways as $gateway) {
            $settings = $gateway['instance']->getSettings();

            foreach ($settings as $option) {
                if (isset($option['encrypted']) && $option['encrypted'] == true) {
                    array_push($this->encrypted_fields, $option['name']);
                }
            }
        }
    }

    /**
     * Update all settings
     *
     * @param array $data all settings
     *
     * @return int
     */
    public function update($data)
    {
        if (CompanySettingsRuntime::active() && !CompanySettingsRuntime::isPrimary()) { return $this->updateIsolated($data); }
        $isolated = CompanySettingsRuntime::active() && !CompanySettingsRuntime::isPrimary();
        if (CompanySettingsRuntime::active()) { CompanySettingsRuntime::assertCan('edit'); }
        if ($isolated && (isset($data['tags']) || isset($data['custom_fields']))) {
            throw new DomainException('company_settings_global_resource_unavailable');
        }
        $original_encrypted_fields = [];

        foreach ($this->encrypted_fields as $ef) {
            $original_encrypted_fields[$ef] = $this->read_option($ef);
        }
        $affectedRows = 0;
        $data         = $isolated ? $data : hooks()->apply_filters('before_settings_updated', $data);
        if (CompanySettingsRuntime::active()) {
            $affectedRows += $this->update_company_identity($data);
        }

        if (isset($data['tags'])) {
            $tagsExists = false;

            foreach ($data['tags'] as $id => $name) {
                $this->db->where('name', $name);
                $this->db->where('id !=', $id);
                $tag = $this->db->get('tags')->row();
                if (! $tag) {
                    $this->db->where('id', $id);
                    $this->db->update(db_prefix() . 'tags', ['name' => $name]);
                    $affectedRows += $this->db->affected_rows();
                } else {
                    $tagsExists = true;
                }
            }

            if ($tagsExists) {
                set_alert('warning', _l('tags_update_replace_warning'));

                return false;
            }

            return (bool) $affectedRows;
        }
        if (! isset($data['settings']['default_tax']) && isset($data['finance_settings'])) {
            $data['settings']['default_tax'] = [];
        }
        $all_settings_looped = [];

        foreach ($data['settings'] as $name => $val) {
            // Do not trim thousand separator option
            // There is an option of white space there and if will be trimmed wont work as configured
            if (is_string($val) && $name != 'thousand_separator' && !in_array($name, $this->encrypted_fields, true)) {
                $val = trim($val);
            }

            array_push($all_settings_looped, $name);

            $hook_data['name']  = $name;
            $hook_data['value'] = $val;
            $hook_data          = $isolated ? $hook_data : hooks()->apply_filters('before_single_setting_updated_in_loop', $hook_data);
            $name               = $hook_data['name'];
            $val                = $hook_data['value'];

            if ($name == 'default_contact_permissions') {
                $val = serialize($val);
            } elseif (in_array($name, ['lead_unique_validation', 'required_register_fields', 'enabled_languages'])) {
                $val = json_encode($val);
            } elseif ($name == 'visible_customer_profile_tabs') {
                if ($val == '') {
                    $val = 'all';
                } else {
                    $tabs           = get_customer_profile_tabs();
                    $newVisibleTabs = [];

                    foreach ($tabs as $tabKey => $tab) {
                        $newVisibleTabs[$tabKey] = in_array($tabKey, $val);
                    }
                    $val = serialize($newVisibleTabs);
                }
            } elseif ($name == 'email_signature') {
                $val = html_entity_decode($val);

                if ($val == strip_tags($val)) {
                    // not contains HTML, add break lines
                    $val = nl2br_save_html($val);
                }
            } elseif ($name == 'email_header' || $name == 'email_footer') {
                $val = html_entity_decode($val);
            } elseif ($name == 'default_tax') {
                $val = array_filter($val, function ($value) {
                    return $value !== '';
                });
                $val = serialize($val);
            } elseif ($name == 'company_info_format' || $name == 'customer_info_format' || $name == 'proposal_info_format' || strpos($name, 'sms_trigger_') !== false) {
                $val = strip_tags($val);
                $val = nl2br($val);
            } elseif (in_array($name, $this->encrypted_fields)) {
                // Check if not empty $val password
                // Get original
                // Decrypt original
                // Compare with $val password
                // If equal unset
                // If not encrypt and save
                if (! empty($val)) {
                    $or_decrypted = $this->encryption->decrypt($original_encrypted_fields[$name]);
                    if ($or_decrypted == $val) {
                        continue;
                    }
                    $val = $this->encryption->encrypt($val);
                    if (!is_string($val) || $val === '') { throw new DomainException('company_settings_encryption_failed'); }
                }
            } elseif ($name == 'staff_notify_completed_but_not_billed_tasks' || $name == 'reminder_for_completed_but_not_billed_tasks_days') {
                $val = json_encode($val);
            }

            if ($this->write_option($name, $val)) {
                $affectedRows++;
                if ($name == 'save_last_order_for_tables' && !$isolated) {
                    $this->db->query('DELETE FROM ' . db_prefix() . 'user_meta where meta_key like "%-table-last-order"');
                }
            }
        }

        // Contact permission default none
        if (! in_array('default_contact_permissions', $all_settings_looped)
                && in_array('customer_settings', $all_settings_looped)) {
            if ($this->write_option('default_contact_permissions', serialize([]))) {
                $affectedRows++;
            }
        }

        // Required register fields, nothing selected
        if (! in_array('required_register_fields', $all_settings_looped)
                && in_array('customer_settings', $all_settings_looped)) {
            if ($this->write_option('required_register_fields', json_encode([]))) {
                $affectedRows++;
            }
        }
        if (! in_array('enabled_languages', $all_settings_looped)
                && in_array('localization_settings', $all_settings_looped)) {
            if ($this->write_option('enabled_languages', json_encode([]))) {
                $affectedRows++;
            }
        }

        if (! in_array('visible_customer_profile_tabs', $all_settings_looped)
                && in_array('customer_settings', $all_settings_looped)) {
            if ($this->write_option('visible_customer_profile_tabs', 'all')) {
                $affectedRows++;
            }
        }

        if (! in_array('lead_unique_validation', $all_settings_looped)
                && in_array('_leads_settings', $all_settings_looped)) {
            if ($this->write_option('lead_unique_validation', json_encode([]))) {
                $affectedRows++;
            }
        }

        if (isset($data['custom_fields'])) {
            if (handle_custom_fields_post(0, $data['custom_fields'])) {
                $affectedRows++;
            }
        }

        return $affectedRows;
    }

    private function updateIsolated($data): int
    {
        CompanySettingsRuntime::assertCan('edit');
        if(isset($data['tags'])||isset($data['custom_fields'])){throw new DomainException('company_settings_global_resource_unavailable');}
        if(!is_array($data['settings']??null)){throw new DomainException('company_settings_invalid_input');}
        $identity=false;$options=false;$prepared=[];
        foreach($data['settings'] as $name=>$value){
            CompanySettingsStorage::name($name);
            if(!is_string($value)){throw new DomainException('company_settings_invalid_input');}
            if(CompanySettingsIdentity::handles($name)){$identity=true;}else{$options=true;}
            if(in_array($name,$this->encrypted_fields,true)){
                if($value!==''){
                    $original=$this->read_option($name);
                    if($this->encryption->decrypt($original)===$value){$value=$original;}
                    else{$value=$this->encryption->encrypt($value);if(!is_string($value)||$value===''){throw new DomainException('company_settings_encryption_failed');}}
                }
            }else{$value=$name==='thousand_separator'?$value:trim($value);}
            $prepared[$name]=$value;
        }
        if($identity&&$options){throw new DomainException('company_settings_batch_identity_separate');}
        if($identity){$data['settings']=$prepared;return $this->update_company_identity($data);}
        return CompanySettingsRuntime::updateBatch($prepared);
    }

    private function read_option($name)
    {
        return CompanySettingsRuntime::active() ? CompanySettingsRuntime::get($name) : get_option($name);
    }

    private function write_option($name, $value)
    {
        return CompanySettingsRuntime::active() ? CompanySettingsRuntime::update($name, $value) : update_option($name, $value);
    }

    private function update_company_identity(array &$data): int
    {
        $identity = [];
        $seenAliases = [];
        foreach ($data['settings'] ?? [] as $name => $value) {
            if (!CompanySettingsIdentity::handles($name)) { continue; }
            if (!is_string($value)) { throw new DomainException('company_settings_invalid_identity_field'); }
            $value = trim($value);
            if (isset(CompanySettingsIdentity::ALIASES[$name])) {
                $key = CompanySettingsIdentity::ALIASES[$name];
                if (isset($seenAliases[$key]) && $seenAliases[$key] !== $value) { throw new DomainException('company_settings_identity_alias_conflict'); }
                $seenAliases[$key] = $value;
                // An unchanged legacy malformed document remains pending until explicitly corrected.
                if ($value !== trim(CompanySettingsRuntime::get($name))) { $identity[$key] = $value; }
            } else { $identity['profile'][$name] = $value; }
            unset($data['settings'][$name]);
        }
        return $identity !== [] && CompanySettingsRuntime::saveIdentity($identity) ? 1 : 0;
    }

    public function add_new_company_pdf_field($data)
    {
        $field = 'custom_company_field_' . trim($data['field']);
        $field = preg_replace('/\s+/', '_', $field);

        return CompanySettingsRuntime::active()
            ? CompanySettingsRuntime::add($field, $data['value'])
            : (bool) add_option($field, $data['value']);
    }
}
