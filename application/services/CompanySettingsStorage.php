<?php

declare(strict_types=1);
require_once __DIR__ . '/issuers/IssuerPolicy.php';
require_once __DIR__ . '/issuers/IssuerStorage.php';
require_once __DIR__ . '/issuers/IssuerTransaction.php';
require_once __DIR__ . '/issuers/IssuerResourceAccess.php';
require_once __DIR__ . '/CompanySettingsIdentity.php';
require_once __DIR__ . '/CompanySettingsCommitGuard.php';

/**
 * Internal explicit-company storage. No session, inferred principal or shared cache.
 * Background callers must first validate the job/event's persisted company ownership.
 * Values retain the existing options wire format, including caller-encrypted secrets.
 */
final class CompanySettingsStorage
{
    // Installation machinery only. Unknown keys are company-private, never global.
    public const INFRASTRUCTURE_OPTIONS = ['cron_has_run_from_cli', 'last_cron_run', 'last_updated_date', 'update_info_message'];
    private const JSON_KEY = 'company_options';
    private $db;
    private string $prefix;

    public function __construct($db, string $prefix)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) { throw new InvalidArgumentException('Invalid table prefix'); }
        $this->db = $db;
        $this->prefix = $prefix;
    }

    public static function name($name): string
    {
        if (!is_string($name) || !preg_match('/^[A-Za-z0-9_][A-Za-z0-9_.:-]{0,190}$/D', $name)) {
            throw new DomainException('company_settings_invalid_name');
        }
        return $name;
    }

    public static function value($value): string
    {
        if (!is_scalar($value) && $value !== null) { throw new DomainException('company_settings_invalid_value'); }
        return $value === null ? '' : (string) $value;
    }

    public static function infrastructure($name): bool
    {
        return in_array(self::name($name), self::INFRASTRUCTURE_OPTIONS, true);
    }

    private function rows(string $sql, array $params = []): array
    {
        return IssuerStorage::query($this->db, $sql, $params)->result_array();
    }

    private function issuer($issuerId): array
    {
        $issuerId = IssuerPolicy::id($issuerId);
        $row = $this->rows("SELECT id,active,settings_json FROM {$this->prefix}issuers_registry WHERE id=?", [$issuerId])[0] ?? null;
        if (!$row || (int) $row['active'] !== 1) { throw new DomainException('company_settings_issuer_unavailable'); }
        return $row;
    }

    public function isPrimary($issuerId): bool
    {
        $issuerId = IssuerPolicy::id($issuerId);
        $primary = (new IssuerResourceAccess($this->db, $this->prefix))->primaryId();
        if ($primary === null) { throw new DomainException('company_settings_primary_unresolved'); }
        return $issuerId === $primary;
    }

    private function document(array $issuer): object
    {
        $document = json_decode($issuer['settings_json']);
        if (!$document instanceof stdClass || json_last_error() !== JSON_ERROR_NONE
            || (property_exists($document, self::JSON_KEY) && !$document->{self::JSON_KEY} instanceof stdClass)) {
            throw new DomainException('company_settings_corrupt_storage');
        }
        foreach (get_object_vars($document->{self::JSON_KEY} ?? new stdClass()) as $name => $value) {
            self::name($name);
            if (!is_string($value)) { throw new DomainException('company_settings_corrupt_storage'); }
        }
        return $document;
    }

    private function lookup($issuerId, string $name): ?string
    {
        $issuer = $this->issuer($issuerId);
        if (CompanySettingsIdentity::handles($name)) { return (new CompanySettingsIdentity($this->db, $this->prefix))->get($issuerId, $name); }
        if ($this->isPrimary($issuerId) || self::infrastructure($name)) {
            $row = $this->rows("SELECT value FROM {$this->prefix}options WHERE name=?", [$name])[0] ?? null;
            return $row === null ? null : (string) $row['value'];
        }
        $options = $this->document($issuer)->{self::JSON_KEY} ?? new stdClass();
        return property_exists($options, $name) ? $options->{$name} : null;
    }

    /** Internal settings snapshot; never returned by public company DTOs. */
    public function all($issuerId): array
    {
        $issuer = $this->issuer($issuerId);
        $values = [];
        if ($this->isPrimary($issuerId)) {
            foreach ($this->rows("SELECT name,value FROM {$this->prefix}options") as $row) { $values[$row['name']] = (string) $row['value']; }
        } else {
            $values = get_object_vars($this->document($issuer)->{self::JSON_KEY} ?? new stdClass());
            foreach (self::INFRASTRUCTURE_OPTIONS as $name) { $values[$name] = $this->get($issuerId, $name); }
        }
        foreach (array_merge(array_keys(CompanySettingsIdentity::ALIASES), CompanySettingsIdentity::PROFILE_OPTIONS) as $name) {
            $values[$name] = $this->get($issuerId, $name);
        }
        return $values;
    }

    /** Own typography, including explicit principal; never global font options. */
    public function documentTypography(int $issuer,string $kind):?array
    {
        require_once __DIR__.'/CompanyPdfTypography.php';
        return CompanyPdfTypography::capture($this->db,$this->prefix,$issuer,$kind);
    }

    public function get($issuerId, $name): string { return $this->lookup($issuerId, self::name($name)) ?? ''; }
    public function exists($issuerId, $name): bool { return $this->lookup($issuerId, self::name($name)) !== null; }

    /** ACL and active membership are rechecked inside the write transaction. */
    public function mutate($issuerId, $staffId, string $operation, $name, $value = '', $autoload = null): bool
    {
        $issuerId = IssuerPolicy::id($issuerId);
        $staffId = IssuerPolicy::id($staffId);
        $name = self::name($name);
        $value = self::value($value);
        if (!in_array($operation, ['add', 'update', 'delete'], true)) { throw new DomainException('company_settings_invalid_operation'); }
        if ($autoload !== null && !in_array($autoload, [0, 1, '0', '1', false, true], true)) { throw new DomainException('company_settings_invalid_autoload'); }
        $access = new IssuerResourceAccess($this->db, $this->prefix);
        $capability = $operation === 'delete' ? 'delete' : 'edit';
        if (!$access->can($issuerId, $staffId, 'settings', $capability)) { throw new DomainException('company_settings_access_denied'); }
        if (CompanySettingsIdentity::handles($name)) {
            if ($operation === 'delete') { throw new DomainException('company_settings_identity_delete_denied'); }
            if ($operation === 'add') { return false; }
            $input = isset(CompanySettingsIdentity::ALIASES[$name])
                ? [CompanySettingsIdentity::ALIASES[$name] => $value] : ['profile' => [$name => $value]];
            return (new CompanySettingsIdentity($this->db, $this->prefix))->save($issuerId, $staffId, $input);
        }
        $tables = ['issuers_lock', 'issuers_registry', 'issuers_staff', 'issuers_resource_permissions', 'issuers_installation_identity', 'issuers_audit', 'staff'];
        if ($this->isPrimary($issuerId)) { $tables[] = 'options'; }
        return IssuerTransaction::run($this->db, $this->prefix, $tables, function () use ($issuerId, $staffId, $operation, $name, $value, $autoload, $access, $capability) {
            $guard = new CompanySettingsCommitGuard($this->db,$this->prefix,$issuerId,$staffId,$capability);
            $issuer = $this->issuer($issuerId);
            $primary = $this->isPrimary($issuerId);
            if (self::infrastructure($name) && (!$primary || !$access->administrative($issuerId,$staffId))) {
                throw new DomainException('company_settings_infrastructure_write_denied');
            }
            $original = $this->lookup($issuerId, $name);
            if (($operation === 'add' && $original !== null) || ($operation === 'delete' && $original === null)
                || ($operation === 'update' && $original === $value && $autoload === null)) { return false; }
            if ($primary) {
                $guard->writeOption($operation,$name,$value,$autoload);
            } else {
                $document = $this->document($issuer);
                $options = $document->{self::JSON_KEY} ?? new stdClass();
                if ($operation === 'delete') { unset($options->{$name}); } else { $options->{$name} = $value; }
                $document->{self::JSON_KEY} = $options;
                $json = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $guard->issuerFields(['settings_json'=>$json]);
                IssuerStorage::query($this->db, "UPDATE {$this->prefix}issuers_registry SET settings_json=? WHERE id=?", [$json, $issuerId]);
            }
            $confirmed = $this->lookup($issuerId, $name);
            if (($operation === 'delete' && $confirmed !== null) || ($operation !== 'delete' && $confirmed !== $value)) {
                throw new DomainException('company_settings_write_unconfirmed');
            }
            // Neither option names nor values/secrets are written to the audit trail.
            $guard->audit('company_settings_'.$operation);
            $guard->confirm();
            return true;
        });
    }

    /** Atomic replacement of submitted private options, without joining caller transactions. */
    public function updateBatch($issuerId,$staffId,array $values): int
    {
        return $this->persistBatch($issuerId,$staffId,$values,false);
    }
    public function updateBatchInOwnedTransaction($issuerId,$staffId,array $values): int
    {
        return $this->persistBatch($issuerId,$staffId,$values,true);
    }
    /** Exact public-document keys only; never opens generic principal batch/infrastructure writes. */
    public function updateSalesDocumentPreferences($issuerId,$staffId,array $values): int
    {
        require_once __DIR__.'/CompanySettingsCatalog.php';
        return $this->persistBatch($issuerId,$staffId,CompanySettingsCatalog::prepare('sales_documents',$values),false,true);
    }
    public function updateDocumentPdfPreferences($issuerId,$staffId,array $values): int
    {
        require_once __DIR__.'/CompanySettingsCatalog.php';
        return $this->persistBatch($issuerId,$staffId,CompanySettingsCatalog::prepare('document_pdf',$values),false,true);
    }
    public function updateSalesPdfPreferences($issuerId,$staffId,array $values): int
    {
        require_once __DIR__.'/CompanySettingsCatalog.php';
        return $this->persistBatch($issuerId,$staffId,CompanySettingsCatalog::prepare('sales_pdf',$values),false,true);
    }
    private function persistBatch($issuerId,$staffId,array $values,bool $owned,bool $salesOnly=false): int
    {
        $issuerId=IssuerPolicy::id($issuerId);$staffId=IssuerPolicy::id($staffId);
        $primary=$this->isPrimary($issuerId);
        if($primary&&!$salesOnly){throw new DomainException('company_settings_batch_scope_required');}
        $prepared=[];
        foreach($values as $name=>$value){$name=self::name($name);$prepared[$name]=self::value($value);
            if(self::infrastructure($name)||CompanySettingsIdentity::handles($name)){throw new DomainException('company_settings_batch_identity_separate');}}
        $access=new IssuerResourceAccess($this->db,$this->prefix);
        if(!$access->can($issuerId,$staffId,'settings','edit')){throw new DomainException('company_settings_access_denied');}
        $tables=['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','issuers_audit','staff'];
        if($primary)$tables[]='options';
        $operation=function()use($issuerId,$staffId,$prepared,$access,$primary):int{
                $guard=new CompanySettingsCommitGuard($this->db,$this->prefix,$issuerId,$staffId,'edit');
                if($primary){
                    $count=0;
                    foreach($prepared as $name=>$value){$original=$this->lookup($issuerId,$name);if($original!==$value){$guard->writeOption('update',$name,$value);$count++;}}
                    if($count===0)return 0;
                    foreach($prepared as $name=>$value)if($this->lookup($issuerId,$name)!==$value)throw new DomainException('company_settings_write_unconfirmed');
                    $guard->audit('company_settings_batch_updated');
                    $guard->confirm();
                    return $count;
                }
                $document=$this->document($this->issuer($issuerId));$options=$document->{self::JSON_KEY}??new stdClass();$count=0;
                foreach($prepared as $name=>$value){if(!property_exists($options,$name)||$options->{$name}!==$value){$options->{$name}=$value;$count++;}}
                if($count===0){return 0;}
                $document->{self::JSON_KEY}=$options;
                $json=json_encode($document,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
                $guard->issuerFields(['settings_json'=>$json]);
                IssuerStorage::query($this->db,"UPDATE {$this->prefix}issuers_registry SET settings_json=? WHERE id=?",[$json,$issuerId]);
                foreach($prepared as $name=>$value){if($this->lookup($issuerId,$name)!==$value){throw new DomainException('company_settings_write_unconfirmed');}}
                $guard->audit('company_settings_batch_updated');
                $guard->confirm();
                return $count;
            };
        if($owned){IssuerTransaction::assertOwned($this->db,$this->prefix,$tables);return $operation();}
        return IssuerTransaction::run($this->db,$this->prefix,$tables,$operation);
    }

    public function saveIdentity($issuerId, $staffId, array $input): bool
    {
        return (new CompanySettingsIdentity($this->db, $this->prefix))->save($issuerId, $staffId, $input);
    }

}
