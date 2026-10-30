<?php

declare(strict_types=1);

require_once __DIR__.'/IssuerStorage.php';
require_once __DIR__ . '/IssuerPolicy.php';
require_once __DIR__ . '/IssuerResourceAccess.php';
require_once __DIR__ . '/IssuerTransaction.php';

/** All mutations enter one transaction and serialize on a module-owned InnoDB row. */
final class IssuerRegistry
{
    private $db;
    private string $prefix;
    private string $tables;

    public function __construct($db, string $prefix)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) {
            throw new InvalidArgumentException('Invalid table prefix');
        }
        $this->db = $db;
        $this->prefix = $prefix;
        $this->tables = $prefix . 'issuers_';
    }

    private function rows(string $sql, array $params = []): array
    {
        $result = IssuerStorage::query($this->db,$sql,$params);
        if ($result === false) {
            throw new RuntimeException('issuer_storage_failed');
        }
        return $result->result_array();
    }

    private function one(string $sql, array $params = []): ?array
    {
        return $this->rows($sql, $params)[0] ?? null;
    }

    private function write(string $sql, array $params = []): void
    {
        if (IssuerStorage::query($this->db,$sql,$params) === false) {
            throw new RuntimeException('issuer_storage_failed');
        }
    }

    private function transaction(callable $callback, array $extraTables = [])
    {
        return IssuerTransaction::run($this->db, $this->prefix,
            array_unique(array_merge(['issuers_lock', 'issuers_registry', 'issuers_staff', 'issuers_staff_defaults', 'issuers_audit', 'staff'], $extraTables)),
            $callback);
    }

    private function audit(int $actor, string $event, ?int $issuer, ?int $subject = null): void
    {
        $this->write("INSERT INTO {$this->tables}audit (actor_id,event,issuer_id,subject_id,created_at) VALUES (?,?,?,?,?)",
            [$actor, $event, $issuer, $subject, date('Y-m-d H:i:s')]);
    }

    private function staff(int $id): array
    {
        $row = $this->one("SELECT staffid,admin,active FROM {$this->prefix}staff WHERE staffid = ?", [$id]);
        if (!$row || (int) $row['active'] !== 1) {
            throw new DomainException('issuer_access_denied');
        }
        return $row;
    }

    public function requireAdmin($actor): int
    {
        $actor = IssuerPolicy::id($actor);
        if ((int) $this->staff($actor)['admin'] !== 1) {
            throw new DomainException('issuer_access_denied');
        }
        return $actor;
    }

    private function nativeCompanies(): bool
    {
        return IssuerStorage::fieldExists($this->db, 'primary_issuer_id', $this->tables.'installation_identity');
    }
    public function requireAccessAdministrator($actor): int
    {
        $actor=$this->requireAdmin($actor);
        if ($this->nativeCompanies() && !(new IssuerResourceAccess($this->db,$this->prefix))->isMaster($actor)) {
            throw new DomainException('issuer_access_denied');
        }
        return $actor;
    }
    public function canCreate($actor): bool
    {
        try {
            $actor=IssuerPolicy::id($actor);$this->staff($actor);
            return $this->nativeCompanies()
                ? (new IssuerResourceAccess($this->db,$this->prefix))->isMaster($actor)
                : (int)$this->staff($actor)['admin']===1;
        } catch (DomainException $error) { return false; }
    }
    public function canManage($issuer, $actor): bool
    {
        if (!$this->nativeCompanies()) { try { $this->requireAdmin($actor); return true; } catch(DomainException $error) { return false; } }
        $access=new IssuerResourceAccess($this->db,$this->prefix);
        return $access->isMaster($actor) || $access->can($issuer,$actor,'settings','edit');
    }
    private function requireCompanyMutation(int $actor, ?int $issuer): void
    {
        if (!($issuer===null ? $this->canCreate($actor) : $this->canManage($issuer,$actor))) {
            throw new DomainException('issuer_access_denied');
        }
    }
    /** Public identity projection never transports settings, credentials or future columns. */
    private function identityColumns(string $alias=''): string
    {
        $fields=['id','legal_name','tax_id','active','created_by','created_at'];
        if(IssuerStorage::fieldExists($this->db,'identity_status',$this->tables.'registry'))$fields[]='identity_status';
        return implode(',',array_map(static fn($field)=>$alias.$field,$fields));
    }

    private function issuer(int $id, bool $active = true): array
    {
        $row = $this->one("SELECT ".$this->identityColumns()." FROM {$this->tables}registry WHERE id = ?", [$id]);
        if (!$row || ($active && (int) $row['active'] !== 1)) {
            throw new DomainException('issuer_unavailable');
        }
        return $row;
    }

    /** Compatibility identity lookup only. Resource operations use issuer_resource_can. */
    public function canAccess($issuer, $actor, string $capability = 'view'): bool
    {
        if ($capability !== 'view') {
            return false; // Historical generic flags cannot authorize a resource operation.
        }
        return (new IssuerResourceAccess($this->db, $this->prefix))->member($issuer, $actor);
    }

    private function requireAccess(int $issuer, int $actor, string $capability = 'view'): void
    {
        if (!$this->canAccess($issuer, $actor, $capability)) {
            throw new DomainException('issuer_access_denied');
        }
    }

    private function principalFirst(array $rows): array
    {
        $primary=(new IssuerResourceAccess($this->db,$this->prefix))->primaryId();
        if($primary!==null){usort($rows,static fn($a,$b)=>((int)$a['id']===$primary?-1:0)<=>((int)$b['id']===$primary?-1:0));}
        return $rows;
    }

    public function catalog($actor): array
    {
        if ($this->nativeCompanies() && !(new IssuerResourceAccess($this->db,$this->prefix))->isMaster($actor)) { return $this->available($actor); }
        $this->requireAdmin($actor);
        return $this->principalFirst($this->rows("SELECT ".$this->identityColumns()." FROM {$this->tables}registry ORDER BY legal_name,id"));
    }

    public function available($actor): array
    {
        $actor = IssuerPolicy::id($actor);
        $this->staff($actor);
        if ((new IssuerResourceAccess($this->db,$this->prefix))->isMaster($actor)) { return $this->principalFirst($this->rows("SELECT ".$this->identityColumns()." FROM {$this->tables}registry WHERE active=1 ORDER BY legal_name,id")); }
        return $this->principalFirst($this->rows("SELECT ".$this->identityColumns('r.')." FROM {$this->tables}registry r JOIN {$this->tables}staff s ON s.issuer_id=r.id
            WHERE s.staff_id=? AND s.can_view=1 AND r.active=1 ORDER BY r.legal_name,r.id", [$actor]));
    }

    public function get($issuer, $actor): array
    {
        $issuer = IssuerPolicy::id($issuer);
        $this->requireAccess($issuer, IssuerPolicy::id($actor));
        return $this->issuer($issuer);
    }

    /** Status-only grid action: never rewrites identity, profiles, settings or financial data. */
    public function setActive($actor, $issuer, $active): void
    {
        $actor = IssuerPolicy::id($actor);
        $issuer = IssuerPolicy::id($issuer);
        if (!in_array($active, [0, 1, '0', '1'], true)) { throw new DomainException('issuer_invalid_status'); }
        $this->requireCompanyMutation($actor, $issuer);
        $this->transaction(function () use ($actor, $issuer, $active) {
            $this->staff($actor);
            $this->requireCompanyMutation($actor, $issuer);
            $this->issuer($issuer, false);
            if ((int)$active === 0 && (new IssuerResourceAccess($this->db, $this->prefix))->primaryId() === $issuer) {
                throw new DomainException('issuer_primary_deactivation_denied');
            }
            $this->write("UPDATE {$this->tables}registry SET active=? WHERE id=?", [(int)$active, $issuer]);
            if ((int)$active === 0) { $this->write("DELETE FROM {$this->tables}staff_defaults WHERE issuer_id=?", [$issuer]); }
            $this->audit($actor, 'issuer_status_changed', $issuer);
        }, $this->nativeCompanies() ? ['issuers_resource_permissions', 'issuers_installation_identity'] : []);
    }

    public function saveIssuer($actor, array $input, $issuer = null): int
    {
        return $this->persistIssuer($actor,$input,$issuer,false);
    }

    /** Atomic wizard composition; only an attested IssuerTransaction owner may call this. */
    public function createInOwnedTransaction($actor, array $input): int
    {
        return $this->persistIssuer($actor,$input,null,true);
    }

    /** Existing-company editor: never create an issuer or accept a client status change. */
    public function identityRevision($actor, $issuer): string
    {
        $actor=IssuerPolicy::id($actor);$issuer=IssuerPolicy::id($issuer);
        $this->requireCompanyMutation($actor,$issuer);
        if(!$this->nativeCompanies()){throw new DomainException('company_settings_issuer_unavailable');}
        require_once dirname(__DIR__).'/CompanySettingsIdentity.php';
        return hash('sha256',json_encode([$issuer,(new CompanySettingsIdentity($this->db,$this->prefix))->read($issuer)],JSON_THROW_ON_ERROR));
    }

    public function updateExistingIdentity($actor, array $input, $issuer, string $revision): int
    {
        $issuer=IssuerPolicy::id($issuer);
        if(!preg_match('/^[a-f0-9]{64}$/D',$revision)){throw new DomainException('company_identity_revision_invalid');}
        $input['active']=1;
        return $this->persistIssuer($actor,$input,$issuer,false,$revision);
    }

    private function persistIssuer($actor, array $input, $issuer, bool $owned, ?string $expectedIdentity=null): int
    {
        $actor = IssuerPolicy::id($actor);
        $id = $issuer === null ? null : IssuerPolicy::id($issuer);
        $this->requireCompanyMutation($actor, $id);
        $native = $this->nativeCompanies();
        $profile = $input['profile'] ?? [];
        if (!is_array($profile)) { throw new DomainException('company_settings_invalid_identity_field'); }
        if ($native) { require_once dirname(__DIR__).'/CompanySettingsIdentity.php'; }
        $name = trim(is_string($input['legal_name'] ?? null) ? $input['legal_name'] : '');
        if ($name === '' || strlen($name) > 191 || preg_match('/[\x00-\x1f]/', $name)) {
            throw new DomainException('issuer_invalid_name');
        }
        $tax = IssuerPolicy::taxId($input['tax_id'] ?? '');
        $active = ($input['active'] ?? '1');
        if (!in_array($active, [0, 1, '0', '1'], true)) {
            throw new DomainException('issuer_invalid_status');
        }
        $ticketPlan=null;if($native&&$id===null){require_once dirname(__DIR__).'/NativeCompanyTicketBootstrap.php';$ticketPlan=NativeCompanyTicketBootstrap::plan($this->db,$this->prefix);}
        $seedTemplates=$native && $id===null && $this->db->table_exists($this->prefix.'emailtemplates');
        $operation = function () use ($actor, $id, $name, $tax, $active, $native, $profile, $seedTemplates, $expectedIdentity, $ticketPlan) {
            $this->requireCompanyMutation($actor, $id);
            if($expectedIdentity!==null&&!hash_equals($expectedIdentity,$this->identityRevision($actor,$id))){throw new DomainException('company_identity_revision_stale');}
            if($id!==null&&(int)$active!==1&&(new IssuerResourceAccess($this->db,$this->prefix))->primaryId()===$id){throw new DomainException('issuer_primary_deactivation_denied');}
            $duplicate = $this->one("SELECT id FROM {$this->tables}registry WHERE tax_id=?", [$tax]);
            if ($duplicate && (int) $duplicate['id'] !== $id) {
                throw new DomainException('issuer_tax_conflict');
            }
            if ($id !== null) {
                $this->issuer($id, false);
                $this->write("UPDATE {$this->tables}registry SET legal_name=?,tax_id=?,active=? WHERE id=?", [$name, $tax, $native ? 1 : (int) $active, $id]);
                if ((int) $active !== 1) {
                    $this->write("DELETE FROM {$this->tables}staff_defaults WHERE issuer_id=?", [$id]);
                }
            } else {
                $this->write("INSERT INTO {$this->tables}registry (legal_name,tax_id,active,settings_json,created_by,created_at) VALUES (?,?,?,?,?,?)",
                    [$name, $tax, $native ? 1 : (int) $active, '{}', $actor, date('Y-m-d H:i:s')]);
                $id = (int) $this->db->insert_id();
            }
            if(IssuerStorage::fieldExists($this->db,'identity_status',$this->tables.'registry')){$this->write("UPDATE {$this->tables}registry SET identity_status='complete' WHERE id=?",[$id]);}
            if ($native) {
                (new CompanySettingsIdentity($this->db,$this->prefix))->saveInOwnedTransaction($id,$actor,['legal_name'=>$name,'tax_id'=>$tax,'profile'=>$profile]);
                $this->write("UPDATE {$this->tables}registry SET active=? WHERE id=?",[(int)$active,$id]);
            }
            if ($seedTemplates) {
                require_once dirname(__DIR__).'/CompanyEmailTemplates.php';
                CompanyEmailTemplates::seedOwned($this->db,$this->prefix,$id,CompanyEmailTemplates::defaults());
            }
            if($ticketPlan!==null)NativeCompanyTicketBootstrap::initializeInOwnedTransaction($this->db,$this->prefix,$id,$actor,$ticketPlan);
            $this->audit($actor, 'issuer_saved', $id);
            return $id;
        };
        $extra = $native ? array_merge(['issuers_resource_permissions','issuers_installation_identity','options'],$seedTemplates?['emailtemplates']:[]) : [];
        if($ticketPlan!==null)$extra=array_merge($extra,$ticketPlan['tables']);
        if ($owned) {
            IssuerTransaction::assertOwned($this->db,$this->prefix,array_unique(array_merge(
                ['issuers_lock','issuers_registry','issuers_staff','issuers_staff_defaults','issuers_audit','staff'],$extra)));
            return $operation();
        }
        return $this->transaction($operation,$extra);
    }

    public function defaultForStaff($staff): ?int
    {
        $staff = IssuerPolicy::id($staff);
        $row = $this->one("SELECT issuer_id FROM {$this->tables}staff_defaults WHERE staff_id=?", [$staff]);
        return $row && $this->canAccess($row['issuer_id'], $staff) ? (int) $row['issuer_id'] : null;
    }

    public function setDefault($issuer, $actor): void
    {
        $issuer = IssuerPolicy::id($issuer);
        $actor = IssuerPolicy::id($actor);
        $this->transaction(function () use ($issuer, $actor) {
            $this->requireAccess($issuer, $actor);
            $this->write("DELETE FROM {$this->tables}staff_defaults WHERE staff_id=?", [$actor]);
            $this->write("INSERT INTO {$this->tables}staff_defaults (staff_id,issuer_id) VALUES (?,?)", [$actor, $issuer]);
            $this->audit($actor, 'default_selected', $issuer);
        });
    }

    /** Legacy adoption is closed until native private-client readers/writers are composed. */
    public function registerClient($issuer, $client, $actor): void
    {
        throw new DomainException('issuer_client_adoption_blocked');
    }

    public function shareClient($owner, $client, array $targets, $actor): void
    {
        throw new DomainException('issuer_client_adoption_blocked');
    }
}
