<?php

declare(strict_types=1);
require_once __DIR__ . '/issuers/IssuerPolicy.php';
require_once __DIR__ . '/issuers/IssuerStorage.php';
require_once __DIR__ . '/issuers/IssuerTransaction.php';
require_once __DIR__ . '/issuers/IssuerResourceAccess.php';
require_once __DIR__ . '/CompanySettingsCommitGuard.php';

/** Canonical adapter shared by the company registry/wizard and Settings. */
final class CompanySettingsIdentity
{
    public const ALIASES = ['companyname' => 'legal_name', 'invoice_company_name' => 'legal_name', 'company_vat' => 'tax_id'];
    public const PROFILE_OPTIONS = ['invoice_company_address', 'invoice_company_city', 'company_state', 'invoice_company_country_code', 'invoice_company_postal_code', 'invoice_company_phonenumber'];
    private $db;
    private string $prefix;
    public function __construct($db, string $prefix)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) { throw new InvalidArgumentException('Invalid table prefix'); }
        $this->db = $db; $this->prefix = $prefix;
    }
    public static function handles(string $name): bool { return isset(self::ALIASES[$name]) || in_array($name, self::PROFILE_OPTIONS, true); }
    /** Explicit legacy-reader bridge; never called for a selected secondary company. */
    public static function legacyPrincipalOption($db, string $prefix, string $name): string
    {
        $primary = (new IssuerResourceAccess($db, $prefix))->primaryId();
        if ($primary === null) { throw new DomainException('company_settings_primary_unresolved'); }
        return (new self($db, $prefix))->get($primary, $name);
    }
    private function rows(string $sql, array $params = []): array { return IssuerStorage::query($this->db, $sql, $params)->result_array(); }
    private function record($issuerId): array
    {
        $row = $this->rows("SELECT * FROM {$this->prefix}issuers_registry WHERE id=? AND active=1", [IssuerPolicy::id($issuerId)])[0] ?? null;
        if (!$row) { throw new DomainException('company_settings_issuer_unavailable'); }
        return $row;
    }
    private function primary($issuerId): bool
    {
        $primary = (new IssuerResourceAccess($this->db, $this->prefix))->primaryId();
        if ($primary === null) { throw new DomainException('company_settings_primary_unresolved'); }
        return $primary === IssuerPolicy::id($issuerId);
    }
    private function document(array $record): object
    {
        $doc = json_decode($record['settings_json']);
        if (!$doc instanceof stdClass || json_last_error() !== JSON_ERROR_NONE) { throw new DomainException('company_settings_corrupt_storage'); }
        if (isset($doc->company_profile) && !$doc->company_profile instanceof stdClass) { throw new DomainException('company_settings_corrupt_storage'); }
        return $doc;
    }
    public function read($issuerId): array
    {
        $record = $this->record($issuerId); $primary = $this->primary($issuerId); $doc = $this->document($record);
        $result = ['legal_name' => (string) $record['legal_name'], 'tax_id' => (string) ($record['tax_id'] ?? ''), 'identity_status' => $record['identity_status'] ?? 'complete', 'profile' => []];
        if ($primary && $result['identity_status'] === 'pending') {
            $identity = $this->rows("SELECT legacy_identity_json FROM {$this->prefix}issuers_installation_identity WHERE id=1")[0] ?? [];
            $legacy = json_decode($identity['legacy_identity_json'] ?? '{}', true);
            if (!is_array($legacy)) { throw new DomainException('company_settings_corrupt_identity'); }
            if (empty($doc->company_identity_name_corrected)) {
                $result['legal_name'] = trim((string) ($legacy['invoice_company_name'] ?? '')) !== ''
                    ? (string) $legacy['invoice_company_name'] : (string) ($legacy['companyname'] ?? '');
            }
            if ($record['tax_id'] === null) { $result['tax_id'] = (string) ($legacy['company_vat'] ?? ''); }
        }
        foreach (self::PROFILE_OPTIONS as $name) {
            if ($primary) {
                $result['profile'][$name] = (string) ($this->rows("SELECT value FROM {$this->prefix}options WHERE name=?", [$name])[0]['value'] ?? '');
            } else {
                $value = $doc->company_profile->{$name} ?? '';
                if (!is_string($value)) { throw new DomainException('company_settings_corrupt_storage'); }
                $result['profile'][$name] = $value;
            }
        }
        return $result;
    }
    public function get($issuerId, string $name): string
    {
        if (!self::handles($name)) { throw new DomainException('company_settings_invalid_identity_field'); }
        $identity = $this->read($issuerId);
        return isset(self::ALIASES[$name]) ? $identity[self::ALIASES[$name]] : $identity['profile'][$name];
    }
    public function save($issuerId, $staffId, array $input): bool
    {
        $issuerId = IssuerPolicy::id($issuerId); $staffId = IssuerPolicy::id($staffId);
        $access = new IssuerResourceAccess($this->db, $this->prefix);
        if (!$access->can($issuerId, $staffId, 'settings', 'edit')) { throw new DomainException('company_settings_access_denied'); }
        $tables = ['issuers_lock', 'issuers_registry', 'issuers_staff', 'issuers_resource_permissions', 'issuers_installation_identity', 'issuers_audit', 'staff'];
        if ($this->primary($issuerId)) { $tables[] = 'options'; }
        return IssuerTransaction::run($this->db, $this->prefix, $tables, function () use ($issuerId, $staffId, $input, $access) {
            return $this->saveInOwnedTransaction($issuerId, $staffId, $input);
        });
    }
    /** Registry/wizard composition uses the same writer without nesting or committing its caller. */
    public function saveInOwnedTransaction($issuerId, $staffId, array $input): bool
    {
        $issuerId = IssuerPolicy::id($issuerId); $staffId = IssuerPolicy::id($staffId);
        $tables = ['issuers_lock', 'issuers_registry', 'issuers_staff', 'issuers_resource_permissions', 'issuers_installation_identity', 'issuers_audit', 'staff'];
        if ($this->primary($issuerId)) { $tables[] = 'options'; }
        IssuerTransaction::assertOwned($this->db, $this->prefix, $tables);
        $guard=new CompanySettingsCommitGuard($this->db,$this->prefix,$issuerId,$staffId,'edit');
        $changed = $this->apply($issuerId, $input,$guard);
        if ($changed) { $guard->audit('company_identity_updated'); }
        $guard->confirm();
        return $changed;
    }
    /** Internal operation used only inside the owning serialized transaction. */
    private function apply($issuerId, array $input,CompanySettingsCommitGuard $guard): bool
    {
        foreach ($input as $key => $unused) { if (!in_array($key, ['legal_name', 'tax_id', 'profile'], true)) { throw new DomainException('company_settings_invalid_identity_field'); } }
        $record = $this->record($issuerId); $doc = $this->document($record); $primary = $this->primary($issuerId);
        $name = $record['legal_name']; $tax = $record['tax_id'];
        if (array_key_exists('legal_name', $input)) {
            if (!is_string($input['legal_name'])) { throw new DomainException('issuer_invalid_name'); }
            $name = trim($input['legal_name']);
            if ($name === '' || strlen($name) > 191 || preg_match('/[\x00-\x1f]/', $name)) { throw new DomainException('issuer_invalid_name'); }
            $doc->company_identity_name_corrected = true;
        }
        if (array_key_exists('tax_id', $input)) { $tax = IssuerPolicy::taxId($input['tax_id']); }
        if ($tax !== null && $this->rows("SELECT id FROM {$this->prefix}issuers_registry WHERE tax_id=? AND id<>?", [$tax, $issuerId])) { throw new DomainException('issuer_tax_conflict'); }
        if (isset($input['profile']) && !is_array($input['profile'])) { throw new DomainException('company_settings_invalid_identity_field'); }
        $profile = $input['profile'] ?? [];
        foreach ($profile as $key => $value) {
            if (!in_array($key, self::PROFILE_OPTIONS, true) || !is_string($value) || strlen($value) > 2000 || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', $value)) { throw new DomainException('company_settings_invalid_identity_field'); }
        }
        $changed = $name !== $record['legal_name'] || $tax !== $record['tax_id'];
        foreach ($profile as $key => $value) {
            if ($primary) {
                $row = $this->rows("SELECT value FROM {$this->prefix}options WHERE name=?", [$key])[0] ?? null;
                if ($row !== null && (string) $row['value'] === $value) { continue; }
                $guard->writeOption('update',$key,$value);
                $confirmed = $this->rows("SELECT value FROM {$this->prefix}options WHERE name=?", [$key])[0] ?? null;
                if ($confirmed === null || (string) $confirmed['value'] !== $value) { throw new DomainException('company_settings_write_unconfirmed'); }
                $changed = true;
            } else {
                if (!isset($doc->company_profile)) { $doc->company_profile = new stdClass(); }
                if (($doc->company_profile->{$key} ?? null) !== $value) { $doc->company_profile->{$key} = $value; $changed = true; }
            }
        }
        $status = $record['identity_status'] ?? 'complete';
        // A placeholder without an explicit correction must never complete the fiscal identity.
        $candidateName = array_key_exists('legal_name', $input) ? $name : $this->read($issuerId)['legal_name'];
        $validName = trim($candidateName) !== '' && strlen(trim($candidateName)) <= 191
            && !preg_match('/[\x00-\x1f]/', $candidateName) && $name === trim($candidateName);
        if ($tax !== null && $validName) { $status = 'complete'; }
        $json = json_encode($doc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $changed = $changed || $status !== ($record['identity_status'] ?? 'complete') || $json !== $record['settings_json'];
        if (!$changed) { return false; }
        $guard->issuerFields(['legal_name'=>$name,'tax_id'=>$tax,'identity_status'=>$status,'settings_json'=>$json]);
        IssuerStorage::query($this->db, "UPDATE {$this->prefix}issuers_registry SET legal_name=?,tax_id=?,identity_status=?,settings_json=? WHERE id=?", [$name, $tax, $status, $json, $issuerId]);
        $confirmed = $this->record($issuerId);
        if ($confirmed['legal_name'] !== $name || $confirmed['tax_id'] !== $tax || $confirmed['identity_status'] !== $status || $confirmed['settings_json'] !== $json) { throw new DomainException('company_settings_write_unconfirmed'); }
        return true;
    }
}
