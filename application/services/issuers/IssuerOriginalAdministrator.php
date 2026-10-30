<?php

declare(strict_types=1);
require_once __DIR__.'/IssuerTransaction.php';
require_once __DIR__.'/IssuerPolicy.php';

/** Internal maintenance after the installation owner explicitly identifies the original administrator.
 * This service does not discover that identity, accept browser input, or authorize its own operator.
 */
final class IssuerOriginalAdministrator
{
    private $db;
    private string $prefix;
    public function __construct($db, string $prefix)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) { throw new InvalidArgumentException('Invalid table prefix'); }
        $this->db=$db; $this->prefix=$prefix;
    }
    private function rows(string $sql, array $params=[]): array
    {
        return IssuerStorage::query($this->db,$sql,$params)->result_array();
    }
    private function state(int $staff, bool $lock): array
    {
        $suffix=$lock?' FOR UPDATE':'';
        $identity=$this->rows('SELECT master_staff_id,primary_issuer_id,source FROM '.$this->prefix.'issuers_installation_identity WHERE id=1'.$suffix)[0]??null;
        $candidate=$this->rows('SELECT staffid,admin,active FROM '.$this->prefix.'staff WHERE staffid=?'.$suffix,[$staff])[0]??null;
        if (!$identity || $identity['primary_issuer_id']===null) { throw new DomainException('issuer_original_identity_missing'); }
        if (!$candidate || (int)$candidate['active']!==1 || (int)$candidate['admin']!==1) { throw new DomainException('issuer_original_active_admin_required'); }
        if ($identity['master_staff_id']!==null && (int)$identity['master_staff_id']!==$staff) { throw new DomainException('issuer_identity_already_resolved'); }
        return ['staff_id'=>$staff,'primary_issuer_id'=>(int)$identity['primary_issuer_id'],
            'status'=>$identity['master_staff_id']===null?'unresolved':'already_bound','source'=>(string)$identity['source']];
    }
    /** No writes, no password/credential reads, no identity inference. */
    public function inspect($staff): array
    {
        if (PHP_SAPI!=='cli') { throw new DomainException('issuer_original_maintenance_only'); }
        return $this->state(IssuerPolicy::id($staff),false);
    }
    /** Caller must hold explicit per-installation owner authorization, independently of this process.
     * expectedPrimary comes from the inspected installation, not a hidden automatic selection.
     */
    public function bind($staff, $expectedPrimary, string $confirmationReference): array
    {
        if (PHP_SAPI!=='cli') { throw new DomainException('issuer_original_maintenance_only'); }
        $staff=IssuerPolicy::id($staff); $expectedPrimary=IssuerPolicy::id($expectedPrimary);
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:\/-]{7,190}$/D',$confirmationReference)) {
            throw new DomainException('issuer_original_confirmation_reference_required');
        }
        return IssuerTransaction::run($this->db,$this->prefix,
            ['issuers_lock','issuers_installation_identity','staff','issuers_audit'],function()use($staff,$expectedPrimary,$confirmationReference){
                $state=$this->state($staff,true);
                if ($state['primary_issuer_id']!==$expectedPrimary) { throw new DomainException('issuer_original_installation_changed'); }
                if ($state['status']==='already_bound') { return $state; }
                $now=gmdate('Y-m-d H:i:s');
                IssuerStorage::query($this->db,'UPDATE '.$this->prefix."issuers_installation_identity SET master_staff_id=?,source='owner_attestation',resolved_at=? WHERE id=1 AND master_staff_id IS NULL AND primary_issuer_id=?",[$staff,$now,$expectedPrimary]);
                $confirmed=$this->state($staff,true);
                if ($confirmed['status']!=='already_bound' || $confirmed['source']!=='owner_attestation') { throw new RuntimeException('issuer_original_write_unconfirmed'); }
                IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix."issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(0,'original_admin_attested',NULL,?,?,?)",
                    [$staff,$now,json_encode(['proof'=>'installation_owner_attestation','confirmation_reference'=>$confirmationReference,'primary_issuer_id'=>$expectedPrimary],JSON_THROW_ON_ERROR)]);
                return $confirmed;
            });
    }
}
