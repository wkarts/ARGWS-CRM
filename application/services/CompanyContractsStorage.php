<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTicketVisibility.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/CompanyContractsSchema.php';
require_once __DIR__.'/CompanyClients.php';
/** Shared owned transaction and durable operation receipts for the isolated vertical. */
abstract class CompanyContractsStorage
{
    protected string $feature;
    protected $originGuard;
    public function __construct(protected $db,protected string $p,?callable $originGuard=null){$this->originGuard=$originGuard;if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');}
    protected function rows(string $sql,array $params=[]):array{return IssuerStorage::query($this->db,$sql,$params)->result_array();}
    protected function authorize(int $issuer,int $actor,string $cap):void
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if(!$acl->can($issuer,$actor,$this->feature,'view')||!$acl->can($issuer,$actor,$this->feature,$cap))throw new DomainException('company_operation_access_denied');
    }
    protected function apiTables():array
    {
        if($this->originGuard===null)return [];require_once __DIR__.'/CompanyApiSchema.php';CompanyApiSchema::assertValid($this->db,$this->p);return ['company_api_tokens','company_api_token_requests'];
    }
    protected function guardedWork(callable $work):callable
    {
        return function()use($work){if($this->originGuard!==null&&($this->originGuard)()===false)throw new DomainException('company_api_scope_denied');$result=$work();if($this->originGuard!==null&&($this->originGuard)()===false)throw new DomainException('company_api_scope_denied');return $result;};
    }
    protected function transaction(callable $work)
    {
        CompanyContractsSchema::assertValid($this->db,$this->p);
        return IssuerTransaction::run($this->db,$this->p,array_merge(['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','options','issuers_clients','issuers_client_links','clients','issuers_audit'],array_keys(CompanyContractsSchema::definitions($this->p)),$this->apiTables(),$this->feature==='tickets'?NativeCompanyTicketVisibility::tables($this->db,$this->p):[]),$this->guardedWork($work));
    }
    protected static function text($value,int $max,bool $required=true):string
    {
        if(!is_string($value)||strlen($value)>$max||preg_match('//u',$value)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$value)||($required&&trim($value)===''))throw new DomainException('company_operation_invalid_input');return trim($value);
    }
    protected static function date($value):string{$v=self::text($value,10);$d=DateTimeImmutable::createFromFormat('!Y-m-d',$v);if(!$d||$d->format('Y-m-d')!==$v)throw new DomainException('company_operation_invalid_input');return $v;}
    protected function responsible(int $issuer,int $staff):void
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($issuer,$staff,$this->feature,'view'))throw new DomainException('company_operation_reference_denied');
    }
    public function staffOptions($issuer,$actor):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$this->authorize($issuer,$actor,'view');$rows=$this->rows('SELECT s.staffid,s.firstname,s.lastname FROM '.$this->p.'staff s WHERE s.active=1 AND (EXISTS (SELECT 1 FROM '.$this->p.'issuers_staff m WHERE m.issuer_id=? AND m.staff_id=s.staffid AND m.can_view=1) OR EXISTS (SELECT 1 FROM '.$this->p.'issuers_installation_identity i WHERE i.id=1 AND i.master_staff_id=s.staffid AND s.admin=1)) ORDER BY s.staffid LIMIT 501',[$issuer]);
        if(count($rows)>500)throw new DomainException('company_operation_limit');$rows=array_values(array_filter($rows,fn($r)=>(new IssuerResourceAccess($this->db,$this->p))->can($issuer,$r['staffid'],$this->feature,'view')));$this->authorize($issuer,$actor,'view');return $rows;
    }
    protected function audit(int $issuer,int $actor,int $id,string $event):void
    {
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,?,?)',[$actor,$event,$issuer,$id,date('Y-m-d H:i:s')]);$audit=(int)$this->db->insert_id();
        if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=? AND event=?',[$audit,$issuer,$actor,$id,$event]))throw new RuntimeException('issuer_operation_write_unconfirmed');
    }
    private function receiptTable():string{return $this->feature==='tickets'?'company_ticket_requests':'company_contract_requests';}
    protected function operation(int $issuer,int $actor,string $cap,string $key,string $op,array $payload,callable $work):int
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_operation_invalid_input');$this->authorize($issuer,$actor,$cap);$hash=hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR));
        return $this->transaction(function()use($issuer,$actor,$cap,$key,$op,$hash,$work):int{
            (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,$cap);
            $receipt=$this->rows('SELECT issuer_id,actor_id,operation,input_hash,result_id FROM '.$this->p.$this->receiptTable().' WHERE request_key=? FOR UPDATE',[$key])[0]??null;
            if($receipt){if((int)$receipt['issuer_id']!==$issuer||(int)$receipt['actor_id']!==$actor||$receipt['operation']!==$op||!hash_equals($receipt['input_hash'],$hash))throw new DomainException('company_operation_request_conflict');$table=$this->feature==='tickets'?'company_tickets':'company_contracts';if($this->feature==='tickets')NativeCompanyTicketVisibility::assertMutable($this->db,$this->p,$issuer,(int)$receipt['result_id']);if(!$this->rows('SELECT id FROM '.$this->p.$table.' WHERE issuer_id=? AND id=?',[$issuer,$receipt['result_id']]))throw new RuntimeException('issuer_operation_write_unconfirmed');return (int)$receipt['result_id'];}
            $id=$work();if($id<1||$id>2147483647)throw new RuntimeException('issuer_operation_write_unconfirmed');$this->audit($issuer,$actor,$id,'company_'.$op);
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.$this->receiptTable().'(request_key,issuer_id,actor_id,input_hash,operation,result_id,created_at) VALUES(?,?,?,?,?,?,?)',[$key,$issuer,$actor,$hash,$op,$id,date('Y-m-d H:i:s')]);
            if(!$this->rows('SELECT result_id FROM '.$this->p.$this->receiptTable().' WHERE request_key=? AND issuer_id=? AND actor_id=? AND input_hash=? AND operation=? AND result_id=?',[$key,$issuer,$actor,$hash,$op,$id]))throw new RuntimeException('issuer_operation_write_unconfirmed');$this->authorize($issuer,$actor,$cap);return $id;
        });
    }
    protected static function revision($revision):int{$v=IssuerPolicy::id($revision);if($v>2147483646)throw new DomainException('company_operation_stale');return $v;}
}
