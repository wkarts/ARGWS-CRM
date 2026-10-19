<?php

declare(strict_types=1);
require_once __DIR__.'/IssuerTransaction.php';
require_once __DIR__.'/IssuerResourceAccess.php';

/** Explicit legacy designation; never claims to recover an unproven historical identity. */
final class IssuerMasterResolution
{
    private $db;
    private string $prefix;
    public function __construct($db, string $prefix)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) { throw new InvalidArgumentException('Invalid table prefix'); }
        $this->db = $db; $this->prefix = $prefix;
    }
    private function rows(string $sql, array $params = []): array
    {
        return IssuerStorage::query($this->db, $sql, $params)->result_array();
    }
    private function transaction(callable $operation)
    {
        return IssuerTransaction::run($this->db, $this->prefix,
            ['issuers_lock','issuers_master_authorizations','issuers_installation_identity','issuers_registry',
             'issuers_staff','issuers_resource_permissions','issuers_audit','staff'], $operation);
    }
    public function unresolved(): bool
    {
        $identity = $this->rows("SELECT master_staff_id FROM {$this->prefix}issuers_installation_identity WHERE id=1")[0] ?? null;
        return $identity !== null && $identity['master_staff_id'] === null;
    }
    private function candidate(int $staff): array
    {
        $row = $this->rows("SELECT staffid,active,admin,password FROM {$this->prefix}staff WHERE staffid=? FOR UPDATE", [$staff])[0] ?? null;
        $access = new IssuerResourceAccess($this->db, $this->prefix);
        $primary = $access->primaryId();
        if (!$row || (int)$row['active'] !== 1 || (int)$row['admin'] !== 1 || $primary === null
            || !$access->administrative($primary, $staff)) { throw new DomainException('issuer_master_candidate_denied'); }
        return $row;
    }
    private function audit(int $staff, string $event, array $metadata): void
    {
        IssuerStorage::query($this->db, "INSERT INTO {$this->prefix}issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,NULL,?,?,?)",
            [$event==='master_designation_authorized'?0:$staff,$event,$staff,gmdate('Y-m-d H:i:s'),json_encode($metadata,JSON_THROW_ON_ERROR)]);
    }
    /** Installation-console possession authorizes a new designation, not a historical assertion. */
    public function authorizeDesignation($staff, string $reason): string
    {
        if (PHP_SAPI !== 'cli') { throw new DomainException('issuer_master_console_required'); }
        $staff = IssuerPolicy::id($staff); $reason = trim($reason);
        if (strlen($reason) < 12 || strlen($reason) > 500 || preg_match('/[\x00-\x1f]/', $reason)) {
            throw new DomainException('issuer_master_reason_required');
        }
        return $this->transaction(function () use ($staff, $reason): string {
            if (!$this->unresolved()) { throw new DomainException('issuer_identity_already_resolved'); }
            $this->candidate($staff);
            $code = bin2hex(random_bytes(32)); $now = time();
            // A reissue revokes every outstanding authorization for this installation.
            IssuerStorage::query($this->db, "DELETE FROM {$this->prefix}issuers_master_authorizations WHERE consumed_at IS NULL");
            IssuerStorage::query($this->db, "INSERT INTO {$this->prefix}issuers_master_authorizations(token_hash,staff_id,expires_at,consumed_at,reason,attempts) VALUES(?,?,?,NULL,?,0)",
                [hash('sha256',$code),$staff,$now+900,$reason]);
            $this->audit($staff,'master_designation_authorized',['proof'=>'installation_console','reason'=>$reason]);
            return $code;
        });
    }
    /** The authenticated subject cannot nominate another staff ID. Password is checked under lock. */
    public function complete($staff, $code, $password, callable $verify): void
    {
        $staff = IssuerPolicy::id($staff);
        if (!is_string($code) || !preg_match('/^[a-f0-9]{64}$/D',$code)
            || !is_string($password) || $password === '' || strlen($password)>4096) {
            throw new DomainException('issuer_master_confirmation_denied');
        }
        $accepted = $this->transaction(function () use ($staff,$code,$password,$verify): bool {
            $candidate = $this->candidate($staff);
            $authorization = $this->rows("SELECT staff_id,expires_at,consumed_at,reason,attempts FROM {$this->prefix}issuers_master_authorizations WHERE token_hash=? FOR UPDATE",[hash('sha256',$code)])[0] ?? null;
            if (!$authorization || (int)$authorization['staff_id'] !== $staff || (int)$authorization['attempts'] >= 5) { return false; }
            if (!$verify($password,(string)$candidate['password'])) {
                IssuerStorage::query($this->db,"UPDATE {$this->prefix}issuers_master_authorizations SET attempts=attempts+1 WHERE token_hash=?",[hash('sha256',$code)]);
                $this->audit($staff,'master_designation_confirmation_failed',[]);
                return false; // Commit the attempt counter before returning a generic denial.
            }
            $identity = $this->rows("SELECT master_staff_id FROM {$this->prefix}issuers_installation_identity WHERE id=1 FOR UPDATE")[0] ?? null;
            if ($authorization['consumed_at'] !== null) {
                if ($identity !== null && (int)$identity['master_staff_id'] === $staff) { return true; }
                throw new DomainException('issuer_master_confirmation_denied');
            }
            if (!$identity || $identity['master_staff_id'] !== null) { throw new DomainException('issuer_identity_already_resolved'); }
            if ((int)$authorization['expires_at'] <= time()) { throw new DomainException('issuer_master_confirmation_denied'); }
            $now = gmdate('Y-m-d H:i:s');
            IssuerStorage::query($this->db, "UPDATE {$this->prefix}issuers_installation_identity SET master_staff_id=?,source='legacy_designation',resolved_at=? WHERE id=1 AND master_staff_id IS NULL",[$staff,$now]);
            $confirmed = $this->rows("SELECT master_staff_id FROM {$this->prefix}issuers_installation_identity WHERE id=1")[0] ?? [];
            if ((int)($confirmed['master_staff_id'] ?? 0) !== $staff) { throw new RuntimeException('issuer_identity_write_unconfirmed'); }
            IssuerStorage::query($this->db, "UPDATE {$this->prefix}issuers_master_authorizations SET consumed_at=? WHERE token_hash=? AND consumed_at IS NULL",[$now,hash('sha256',$code)]);
            $consumed = $this->rows("SELECT consumed_at FROM {$this->prefix}issuers_master_authorizations WHERE token_hash=?",[hash('sha256',$code)])[0] ?? [];
            if (($consumed['consumed_at']??null) !== $now) { throw new RuntimeException('issuer_identity_write_unconfirmed'); }
            $this->audit($staff,'installation_master_designated',['proof'=>'installation_console_and_password','historical_original_proven'=>false,'reason'=>$authorization['reason']]);
            return true;
        });
        if (!$accepted) { throw new DomainException('issuer_master_confirmation_denied'); }
    }
}
