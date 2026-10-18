<?php

declare(strict_types=1);
require_once __DIR__.'/IssuerStorage.php';
require_once __DIR__ . '/IssuerRegistry.php';
require_once __DIR__ . '/IssuerResourceAccess.php';

/** Native staff form boundary. An issuer grant is never implied by global administrator status. */
final class IssuerStaffAccess
{
    private $db;
    private string $prefix;
    private int $actor;
    private IssuerRegistry $registry;
    private IssuerResourceAccess $access;
    private array $resources;
    public function __construct($db, string $prefix, $actor, array $resources=[])
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) { throw new InvalidArgumentException('Invalid table prefix'); }
        $this->db=$db; $this->prefix=$prefix; $this->actor=IssuerPolicy::id($actor);
        $this->registry=new IssuerRegistry($db,$prefix);
        $this->access=new IssuerResourceAccess($db,$prefix); $this->resources=$resources;
    }
    private function query(string $sql,array $params=[])
    {
        $result=IssuerStorage::query($this->db,$sql,$params);
        if ($result===false) { throw new RuntimeException('issuer_storage_failed'); }
        return $result;
    }
    private function rows(string $sql,array $params=[]): array { return $this->query($sql,$params)->result_array(); }
    private function staff(int $id): void
    {
        $row=$this->rows("SELECT staffid FROM {$this->prefix}staff WHERE staffid=? AND active=1",[$id]);
        if (!$row) { throw new DomainException('issuer_access_denied'); }
    }
    public function read($staff): array
    {
        $this->registry->requireAccessAdministrator($this->actor);
        $staff=$staff===null?null:IssuerPolicy::id($staff);
        $members=[];
        if ($staff!==null) {
            $this->staff($staff);
            foreach ($this->rows("SELECT * FROM {$this->prefix}issuers_staff WHERE staff_id=?",[$staff]) as $row) { $members[(int)$row['issuer_id']]=$row; }
        }
        return ['catalog'=>$this->registry->catalog($this->actor),'members'=>$members,'default'=>$staff===null?null:$this->registry->defaultForStaff($staff),'master'=>$staff!==null&&$this->access->isMaster($staff),'grants'=>$staff===null?[]:$this->access->rowsForStaff($staff)];
    }
    /** Prevalidation is repeated under the module lock immediately before persistence. */
    public function prepare($staff, $memberships, $default, $resources=[]): array
    {
        $this->registry->requireAccessAdministrator($this->actor);
        if ($staff!==null) { $staff=IssuerPolicy::id($staff); $this->staff($staff); }
        $master=$staff!==null&&$this->access->isMaster($staff);
        if (!is_array($memberships)||count($memberships)>1000||!is_array($resources)||count($resources)>1000) { throw new DomainException('issuer_invalid_permission'); }
        $normalized=[]; $active=[]; $grants=[];
        foreach ($this->registry->catalog($this->actor) as $issuer) { if ((int)$issuer['active']===1) { $active[(int)$issuer['id']]=true; } }
        if($master&&($memberships||$resources)) { throw new DomainException('issuer_master_access_automatic'); }
        foreach ($memberships as $issuer=>$enabled) {
            $issuer=IssuerPolicy::id($issuer);
            if (!isset($active[$issuer])||!in_array($enabled,[1,'1'],true)) { throw new DomainException('issuer_invalid_permission'); }
            $normalized[$issuer]=1;
        }
        foreach($resources as $issuer=>$features) {
            $issuer=IssuerPolicy::id($issuer);
            if(!isset($normalized[$issuer])||!is_array($features)||count($features)>1000) { throw new DomainException('issuer_invalid_permission'); }
            foreach($features as $feature=>$capabilities) {
                IssuerResourceAccess::key($feature);
                if(!isset($this->resources[$feature])||!is_array($capabilities)||count($capabilities)>100) { throw new DomainException('issuer_invalid_permission'); }
                foreach($capabilities as $capability) {
                    IssuerResourceAccess::key($capability);
                    if(!in_array($capability,$this->resources[$feature],true)) { throw new DomainException('issuer_invalid_permission'); }
                    $grants[$issuer][$feature][$capability]=true;
                }
            }
        }
        $default=$default===null||$default===''?null:IssuerPolicy::id($default);
        if ($default!==null && (!isset($active[$default])||(!$master&&!isset($normalized[$default])))) { throw new DomainException('issuer_invalid_default'); }
        ksort($normalized,SORT_NUMERIC);
        return ['memberships'=>$normalized,'default'=>$default,'resources'=>$grants,'master'=>$master];
    }
    private function transaction(int $staff, callable $operation): void
    {
        IssuerTransaction::run($this->db, $this->prefix,
            ['staff','issuers_registry','issuers_staff','issuers_staff_defaults','issuers_lock','issuers_audit','issuers_resource_permissions','issuers_installation_identity'],
            function () use ($staff, $operation): void {
                $this->rows("SELECT staffid FROM {$this->prefix}staff WHERE staffid IN (?,?) FOR UPDATE", [$this->actor, $staff]);
                $operation();
            });
    }
    private function auditSnapshot(int $staff): array
    {
        $members=$this->rows("SELECT issuer_id FROM {$this->prefix}issuers_staff WHERE staff_id=? AND can_view=1 ORDER BY issuer_id",[$staff]);
        $default=$this->rows("SELECT issuer_id FROM {$this->prefix}issuers_staff_defaults WHERE staff_id=?",[$staff]);
        $grants=$this->access->rowsForStaff($staff);
        return ['companies'=>array_map('intval',array_column($members,'issuer_id')),'default'=>$default?(int)$default[0]['issuer_id']:null,'resources'=>array_map(static fn($row)=>['issuer_id'=>(int)$row['issuer_id'],'feature'=>$row['feature'],'capability'=>$row['capability']],$grants)];
    }
    private function audit(int $staff,string $event,array $before): void
    {
        $json=json_encode(['before'=>$before,'after'=>$this->auditSnapshot($staff)],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $this->query("INSERT INTO {$this->prefix}issuers_audit (actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES (?,?,NULL,?,?,?)",[$this->actor,$event,$staff,date('Y-m-d H:i:s'),$json]);
    }
    public function save($staff, array $memberships, $default, array $resources=[]): void
    {
        $staff=IssuerPolicy::id($staff);
        $this->transaction($staff,function()use($staff,$memberships,$default,$resources):void {
            $data=$this->prepare($staff,$memberships,$default,$resources);
            $before=$this->auditSnapshot($staff);
            $primary=$this->access->primaryId();
            // Retain an existing migration snapshot only while the principal membership and
            // legacy admin flag remain explicit. This path never creates a new privilege.
            $preserveLegacyAdmin=$primary!==null && isset($data['memberships'][$primary])
                && $this->access->administrative($primary,$staff) && !$this->access->isMaster($staff);

            $this->query("DELETE FROM {$this->prefix}issuers_staff_defaults WHERE staff_id=?",[$staff]);
            $this->query("DELETE FROM {$this->prefix}issuers_resource_permissions WHERE staff_id=?",[$staff]);
            $this->query("DELETE FROM {$this->prefix}issuers_staff WHERE staff_id=?",[$staff]);
            if($data['master']&&$data['default']!==null) { $data['memberships'][$data['default']]=1; }
            foreach($data['memberships'] as $issuer=>$enabled) {
                $this->query("INSERT INTO {$this->prefix}issuers_staff (issuer_id,staff_id,can_view,can_manage_clients,can_share_clients,can_manage_integrations) VALUES (?,?,1,0,0,0)",[$issuer,$staff]);
            }
            if($preserveLegacyAdmin){$this->query("INSERT INTO {$this->prefix}issuers_resource_permissions (issuer_id,staff_id,feature,capability) VALUES (?,?,?,?)",[$primary,$staff,'core_legacy_administration','access']);}
            foreach($data['resources'] as $issuer=>$features) { foreach($features as $feature=>$capabilities) { foreach($capabilities as $capability=>$enabled) {
                $this->query("INSERT INTO {$this->prefix}issuers_resource_permissions (issuer_id,staff_id,feature,capability) VALUES (?,?,?,?)",[$issuer,$staff,$feature,$capability]);
            } } }
            if($data['default']!==null) { $this->query("INSERT INTO {$this->prefix}issuers_staff_defaults (staff_id,issuer_id) VALUES (?,?)",[$staff,$data['default']]); }
            $this->audit($staff,'staff_issuer_access_saved',$before);
        });
    }
    public function ownOptions(): array { return ['catalog'=>$this->registry->available($this->actor),'default'=>$this->registry->defaultForStaff($this->actor),'master'=>$this->access->isMaster($this->actor)]; }
    public function validateOwnDefault($default): ?int
    {
        $this->staff($this->actor); $default=$default===null||$default===''?null:IssuerPolicy::id($default);
        if($default!==null&&!$this->registry->canAccess($default,$this->actor)) { throw new DomainException('issuer_invalid_default'); }
        return $default;
    }
    public function saveOwnDefault($default): void
    {
        $this->transaction($this->actor,function()use($default):void {
            $default=$this->validateOwnDefault($default);
            $before=$this->auditSnapshot($this->actor);
            $this->query("DELETE FROM {$this->prefix}issuers_staff_defaults WHERE staff_id=?",[$this->actor]);
            if($default!==null&&$this->access->isMaster($this->actor)&&!$this->rows("SELECT issuer_id FROM {$this->prefix}issuers_staff WHERE issuer_id=? AND staff_id=?",[$default,$this->actor])) { $this->query("INSERT INTO {$this->prefix}issuers_staff(issuer_id,staff_id,can_view,can_manage_clients,can_share_clients,can_manage_integrations) VALUES(?,?,1,0,0,0)",[$default,$this->actor]); }
            if($default!==null) { $this->query("INSERT INTO {$this->prefix}issuers_staff_defaults (staff_id,issuer_id) VALUES (?,?)",[$this->actor,$default]); }
            $this->audit($this->actor,'staff_default_selected',$before);
        });
    }
}
