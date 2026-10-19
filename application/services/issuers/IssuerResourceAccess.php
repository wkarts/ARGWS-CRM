<?php

declare(strict_types=1);
require_once __DIR__.'/IssuerStorage.php';
require_once __DIR__.'/IssuerPolicy.php';

/** Explicit company/resource ACL. This never reads an ambient session or global role grants. */
final class IssuerResourceAccess
{
    private $db;
    private string $prefix;
    public function __construct($db, string $prefix)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)) { throw new InvalidArgumentException('Invalid table prefix'); }
        $this->db=$db; $this->prefix=$prefix;
    }
    private function rows(string $sql,array $params=[]): array
    {
        $result=IssuerStorage::query($this->db,$sql,$params);
        if ($result===false) { throw new RuntimeException('issuer_storage_failed'); }
        return $result->result_array();
    }
    /** Lock current authorization rows inside a core-owned financial transaction. */
    public function lockFinancialIdentity(int $issuer,int $staff):void
    {
        require_once __DIR__.'/IssuerTransaction.php';
        IssuerTransaction::assertOwned($this->db,$this->prefix,['issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff']);
        $this->rows('SELECT id FROM '.$this->prefix.'issuers_registry WHERE id=? FOR UPDATE',[$issuer]);
        $this->rows('SELECT staffid FROM '.$this->prefix.'staff WHERE staffid=? FOR UPDATE',[$staff]);
        $this->rows('SELECT issuer_id FROM '.$this->prefix.'issuers_staff WHERE issuer_id=? AND staff_id=? FOR UPDATE',[$issuer,$staff]);
        $this->rows('SELECT issuer_id FROM '.$this->prefix.'issuers_resource_permissions WHERE issuer_id=? AND staff_id=? FOR UPDATE',[$issuer,$staff]);
    }
    public static function key($value): string
    {
        if(!is_string($value)||strlen($value)>100||!preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,99}(?:\(\))?$/D',$value)) { throw new DomainException('issuer_invalid_permission'); }
        return $value;
    }
    private function present(string $table,string $field): bool { return IssuerStorage::fieldExists($this->db,$field,$this->prefix.$table); }
    public function primaryId(): ?int
    {
        if(!$this->present('issuers_installation_identity','primary_issuer_id')) { return null; }
        $row=$this->rows("SELECT primary_issuer_id FROM {$this->prefix}issuers_installation_identity WHERE id=1")[0]??[];
        return isset($row['primary_issuer_id'])?IssuerPolicy::id($row['primary_issuer_id']):null;
    }
    /** Preservation check also covers an already demoted/deactivated master; no grant is implied. */
    public function isInstallationMaster($staff): bool
    {
        try {
            $staff=IssuerPolicy::id($staff);
            if(!$this->present('issuers_installation_identity','master_staff_id')) { return false; }
            return count($this->rows("SELECT s.staffid FROM {$this->prefix}staff s JOIN {$this->prefix}issuers_installation_identity i ON i.master_staff_id=s.staffid WHERE i.id=1 AND s.staffid=?",[$staff]))===1;
        } catch(DomainException $error) { return false; }
    }
    public function isMaster($staff): bool
    {
        try {
            $staff=IssuerPolicy::id($staff);
            if(!$this->present('issuers_installation_identity','master_staff_id')) { return false; }
            return count($this->rows("SELECT s.staffid FROM {$this->prefix}staff s JOIN {$this->prefix}issuers_installation_identity i ON i.master_staff_id=s.staffid WHERE i.id=1 AND s.staffid=? AND s.active=1 AND s.admin=1",[$staff]))===1;
        } catch(DomainException $error) { return false; }
    }
    /** Historical administrator controls are preserved only for the installation company. */
    public function administrative($issuer, $staff): bool
    {
        if (!$this->member($issuer,$staff)) { return false; }
        if ($this->isMaster($staff)) { return true; }
        if ((int)$issuer !== $this->primaryId()) { return false; }
        if (!$this->rows("SELECT staffid FROM {$this->prefix}staff WHERE staffid=? AND active=1 AND admin=1",[$staff])) { return false; }
        return $this->can($issuer,$staff,'core_legacy_administration','access');
    }
    public function member($issuer,$staff): bool
    {
        try {
            $issuer=IssuerPolicy::id($issuer); $staff=IssuerPolicy::id($staff);
            if(!$this->rows("SELECT id FROM {$this->prefix}issuers_registry WHERE id=? AND active=1",[$issuer])||!$this->rows("SELECT staffid FROM {$this->prefix}staff WHERE staffid=? AND active=1",[$staff])) { return false; }
            if($this->isMaster($staff)) { return true; }
            return count($this->rows("SELECT issuer_id FROM {$this->prefix}issuers_staff WHERE issuer_id=? AND staff_id=? AND can_view=1",[$issuer,$staff]))===1;
        } catch(DomainException $error) { return false; }
    }
    public function can($issuer,$staff,string $feature,string $capability): bool
    {
        try {
            $issuer=IssuerPolicy::id($issuer); $staff=IssuerPolicy::id($staff);
            self::key($feature); self::key($capability);
            if(!$this->member($issuer,$staff)) { return false; }
            if($this->isMaster($staff)) { return true; }
            if(!$this->present('issuers_resource_permissions','capability')) { return false; }
            return count($this->rows("SELECT issuer_id FROM {$this->prefix}issuers_resource_permissions WHERE issuer_id=? AND staff_id=? AND feature=? AND capability=?",[$issuer,$staff,$feature,$capability]))===1;
        } catch(DomainException $error) { return false; }
    }
    /** Caller must already authorize editing this staff member. */
    public function rowsForStaff($staff): array
    {
        return $this->rows("SELECT issuer_id,feature,capability FROM {$this->prefix}issuers_resource_permissions WHERE staff_id=? ORDER BY issuer_id,feature,capability",[IssuerPolicy::id($staff)]);
    }
}
