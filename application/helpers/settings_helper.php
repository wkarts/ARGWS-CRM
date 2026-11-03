<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Add option
 *
 * @since  Version 1.0.1
 *
 * @param string $name     Option name (required|unique)
 * @param string $value    Option value
 * @param int    $autoload Whether to autoload this option
 */
function add_option($name, $value = '', $autoload = 1)
{
    if (class_exists('CompanySettingsRuntime', false) && CompanySettingsRuntime::active()) {
        if (CompanySettingsRuntime::exists($name)) { return false; }
        return CompanySettingsRuntime::add($name, $value, $autoload);
    }
    if (! option_exists($name)) {
        $CI = &get_instance();

        $newData = [
            'name'  => $name,
            'value' => $value,
        ];

        if ($CI->db->field_exists('autoload', db_prefix() . 'options')) {
            $newData['autoload'] = $autoload;
        }

        $CI->db->insert(db_prefix() . 'options', $newData);

        $insert_id = $CI->db->insert_id();

        return (bool) ($insert_id);
    }

    return false;
}

/**
 * Get option value
 *
 * @param string $name Option name
 *
 * @return mixed
 */
function get_option($name)
{
    if (class_exists('CompanySettingsRuntime', false) && CompanySettingsRuntime::active()) { return CompanySettingsRuntime::get($name); }
    $CI = &get_instance();

    if (! class_exists('app', false)) {
        $CI->load->library('app');
    }

    return $CI->app->get_option($name);
}

/**
 * Updates option by name
 *
 * @param string $name     Option name
 * @param string $value    Option Value
 * @param mixed  $autoload Whether to update the autoload
 *
 * @return bool
 */
function update_option($name, $value, $autoload = null)
{
    if (class_exists('CompanySettingsRuntime', false) && CompanySettingsRuntime::active()) { return CompanySettingsRuntime::update($name, $value, $autoload); }
    /**
     * Create the option if not exists
     *
     * @since  2.3.3
     */
    if (! option_exists($name)) {
        return add_option($name, $value, $autoload === null ? 1 : 0);
    }

    $CI = &get_instance();

    $CI->db->where('name', $name);
    $data = ['value' => $value];

    if ($autoload) {
        $data['autoload'] = $autoload;
    }

    $CI->db->update(db_prefix() . 'options', $data);

    return (bool) ($CI->db->affected_rows() > 0);
}

/**
 * Delete option
 *
 * @since  Version 1.0.4
 *
 * @param mixed $name option name
 *
 * @return bool
 */
function delete_option($name)
{
    if (class_exists('CompanySettingsRuntime', false) && CompanySettingsRuntime::active()) { return CompanySettingsRuntime::delete($name); }
    $CI = &get_instance();
    $CI->db->where('name', $name);
    $CI->db->delete(db_prefix() . 'options');

    return (bool) $CI->db->affected_rows();
}

/**
 * @since  2.3.3
 * Check whether an option exists
 *
 * @param string $name option name
 *
 * @return bool
 */
function option_exists($name)
{
    if (class_exists('CompanySettingsRuntime', false) && CompanySettingsRuntime::active()) { return CompanySettingsRuntime::exists($name); }
    return total_rows(db_prefix() . 'options', [
        'name' => $name,
    ]) > 0;
}

function app_init_settings_tabs()
{
    $CI = &get_instance();
    require_once APPPATH.'services/NativeSettingsSections.php';
    NativeSettingsSections::register($CI->app);
}
