<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * @property-read CI_DB_mysql_driver $db
 */
class App_Model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();

        // The existing native model remains in use. Its sealed company request
        // already owns the database connection and settings lease: reconnecting
        // here can silently discard an active transaction, and model_init hooks
        // are not evidence of tenant-safe access.
        $ci = &get_instance();
        if (class_exists('CompanyRequestScope', false) && CompanyRequestScope::nativeFor($ci)) {
            if (!class_exists('CompanySettingsRuntime', false) || !CompanySettingsRuntime::active()) {
                throw new LogicException('native_model_settings_scope_required');
            }
            $context = CompanyRequestScope::context();
            if ($context->issuerId() !== CompanySettingsRuntime::issuerId()
                || $context->staffId() !== CompanySettingsRuntime::staffId()) {
                throw new LogicException('native_model_settings_scope_mismatch');
            }
            $timezone = CompanySettingsRuntime::get('default_timezone');
            if ($timezone !== '') {
                if (!in_array($timezone, timezone_identifiers_list(DateTimeZone::ALL_WITH_BC), true)) {
                    throw new DomainException('native_model_timezone_invalid');
                }
                date_default_timezone_set($timezone);
            }
            return;
        }

        $this->db->reconnect();

        $timezone = get_option('default_timezone');

        if ($timezone != '') {
            date_default_timezone_set($timezone);
        }

        hooks()->do_action('model_init', $this);
    }
}
