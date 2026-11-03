<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Mandatory authorization for the native web upgrade, independent of global CSRF. */
final class AdminDatabaseUpgrade
{
    private const SUCCESS_KEY = 'admin_database_upgrade_success';
    private const SUCCESS_TTL = 300;

    /** Feedback survives bootstrap/background requests until the authorized update page. */
    public static function recordSuccess($session, int $actor, string $version, ?int $now = null): void
    {
        if ($actor < 1 || !preg_match('/^[0-9]+$/D', $version)) {
            throw new InvalidArgumentException('Invalid native upgrade feedback identity.');
        }
        $session->set_userdata(self::SUCCESS_KEY, [
            'actor' => $actor,
            'version' => $version,
            'expires_at' => ($now ?? time()) + self::SUCCESS_TTL,
        ]);
    }

    /** Call only after authenticating/authorizing a GET of Settings/update. */
    public static function consumeSuccess($session, int $actor, string $version, ?int $now = null): bool
    {
        $receipt = $session->userdata(self::SUCCESS_KEY);
        if ($receipt === null) { return false; }
        $session->unset_userdata(self::SUCCESS_KEY);
        return is_array($receipt)
            && ($receipt['actor'] ?? null) === $actor && $actor > 0
            && ($receipt['version'] ?? null) === $version
            && is_int($receipt['expires_at'] ?? null)
            && $receipt['expires_at'] > ($now ?? time());
    }

    /** The company selector attests active staff and membership before any feedback. */
    public static function consumeContextSuccess($ci, string $version): bool
    {
        if ($ci->input->method(true) !== 'GET' || $ci->input->is_ajax_request()
            || !class_exists('CompanyRequestScope', false) || !CompanyRequestScope::sealedFor($ci)) {
            return false;
        }
        return self::consumeSuccess($ci->session, CompanyRequestScope::context()->staffId(), $version);
    }

    /** Persisted migration metadata, read only after the exact request owner was attested. */
    public static function contextVersion($ci): string
    {
        if (!class_exists('CompanyRequestScope', false) || !CompanyRequestScope::sealedFor($ci)) {
            throw new DomainException('native_upgrade_context_denied');
        }
        require_once __DIR__.'/issuers/IssuerStorage.php';
        $rows = IssuerStorage::query($ci->db, 'SELECT version FROM '.db_prefix().'migrations LIMIT 2')->result_array();
        if (count($rows) !== 1 || (!is_int($rows[0]['version'] ?? null) && !is_string($rows[0]['version'] ?? null))
            || !preg_match('/^[0-9]+$/D', (string)$rows[0]['version'])) {
            throw new RuntimeException('native_upgrade_version_unconfirmed');
        }
        return (string)$rows[0]['version'];
    }

    /** Read-only preview: failed rendering or final ACL recheck must not consume feedback. */
    public static function peekContextSuccess($ci, string $version): bool
    {
        if ($ci->input->method(true) !== 'GET' || $ci->input->is_ajax_request()
            || !class_exists('CompanyRequestScope', false) || !CompanyRequestScope::sealedFor($ci)) {
            return false;
        }
        $receipt = $ci->session->userdata(self::SUCCESS_KEY);
        $actor = CompanyRequestScope::context()->staffId();
        return is_array($receipt) && $actor > 0 && ($receipt['actor'] ?? null) === $actor
            && ($receipt['version'] ?? null) === $version
            && is_int($receipt['expires_at'] ?? null) && $receipt['expires_at'] > time();
    }

    public static function token($session): string
    {
        $token = $session->userdata('admin_database_upgrade_csrf');
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) {
            $token = bin2hex(random_bytes(32));
            $session->set_userdata('admin_database_upgrade_csrf', $token);
        }
        return $token;
    }

    public static function authorize($ci): bool
    {
        if ($ci->input->method(true) !== 'POST' || !is_staff_logged_in() || !is_admin()) {
            return false;
        }
        $ci->load->model('staff_model');
        $staff = $ci->staff_model->get(get_staff_user_id());
        if (!$staff || (int) $staff->active !== 1 || (int) $staff->admin !== 1) {
            return false;
        }
        $token = $ci->session->userdata('admin_database_upgrade_csrf');
        $provided = $ci->input->post('admin_database_upgrade_csrf');
        return is_string($token) && preg_match('/^[a-f0-9]{64}$/D', $token)
            && is_string($provided) && hash_equals($token, $provided);
    }
}
