<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Native private customer operations. No session, hooks, mail, contacts or finance hydration.
 * Deliberately not routed until all legacy readers/portal boundaries are validated.
 */
final class CompanyClients
{
    private IssuerResourceAccess $access;
    public function __construct(private $db, private string $prefix)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)) throw new InvalidArgumentException('Invalid table prefix');
        $this->access=new IssuerResourceAccess($db,$prefix);
    }
    private function rows(string $sql,array $params=[]): array
    {
        return IssuerStorage::query($this->db,$sql,$params)->result_array();
    }
    private function authorize(int $issuer,int $actor,string $capability): void
    {
        if (!$this->access->can($issuer,$actor,'customers',$capability)) throw new DomainException('company_client_access_denied');
    }
    private function projection(): string { return 'c.userid,c.company,c.vat,c.active,o.owner_issuer_id'; }
    private function visibility(): string
    {
        return '(o.owner_issuer_id=? OR EXISTS (SELECT 1 FROM '.$this->prefix.'issuers_client_links l WHERE l.client_id=c.userid AND l.issuer_id=?))';
    }
    public function get($issuer,$actor,$client): ?array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$client=IssuerPolicy::id($client);
        $this->authorize($issuer,$actor,'view');
        $rows=$this->rows('SELECT '.$this->projection().' FROM '.$this->prefix.'clients c JOIN '.$this->prefix.'issuers_clients o ON o.client_id=c.userid WHERE c.userid=? AND '.$this->visibility(),[$client,$issuer,$issuer]);
        $this->authorize($issuer,$actor,'view');
        return isset($rows[0])?$this->project($rows[0]):null;
    }
    /** Stable keyset pagination; no total or contact/financial count from other companies. */
    public function page($issuer,$actor,$after=0,int $limit=50): array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);
        if ($after!==0) $after=IssuerPolicy::id($after);
        if ($limit<1||$limit>100) throw new DomainException('company_client_invalid_page');
        $this->authorize($issuer,$actor,'view');
        $rows=$this->rows('SELECT '.$this->projection().' FROM '.$this->prefix.'clients c JOIN '.$this->prefix.'issuers_clients o ON o.client_id=c.userid WHERE c.userid>? AND '.$this->visibility().' ORDER BY c.userid LIMIT '.($limit+1),[$after,$issuer,$issuer]);
        $this->authorize($issuer,$actor,'view');
        $more=count($rows)>$limit;if($more)array_pop($rows);
        return ['items'=>array_map(fn(array $row):array=>$this->project($row),$rows),'next'=>$more?(int)$rows[count($rows)-1]['userid']:null];
    }
    private function project(array $row): array
    {
        foreach(['userid','active','owner_issuer_id'] as $field)$row[$field]=(int)$row[$field];
        return $row;
    }
    private function input(array $input): array
    {
        if (array_diff(array_keys($input),['company','vat'])) throw new DomainException('company_client_invalid_input');
        $name=$input['company']??null;
        if(!is_string($name)||trim($name)===''||strlen(trim($name))>191||preg_match('/[\x00-\x1f]/',$name)) throw new DomainException('company_client_invalid_input');
        return [trim($name),IssuerPolicy::taxId($input['vat']??'',false)];
    }
    private function conflict(?string $tax,?int $except): void
    {
        if($tax===null)return;
        // Existing legacy records are checked before adoption; no identity is disclosed on conflict.
        $normalized='UPPER(vat)';
        foreach(["' '","'.'","'/'","'-'",'CHAR(9)','CHAR(10)','CHAR(13)'] as $character) $normalized="REPLACE($normalized,$character,'')";
        if($this->rows('SELECT userid FROM '.$this->prefix.'clients WHERE '.$normalized.'=? AND userid<>? LIMIT 1',[$tax,$except??0])
            || $this->rows('SELECT client_id FROM '.$this->prefix.'issuers_clients WHERE tax_id=? AND client_id<>? LIMIT 1',[$tax,$except??0])) {
            throw new DomainException('company_client_identity_conflict');
        }
    }
    private function transaction(callable $operation)
    {
        return IssuerTransaction::run($this->db,$this->prefix,['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','issuers_clients','issuers_client_links','issuers_audit','staff','clients'],$operation);
    }
    public function create($issuer,$actor,array $input): int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);[$name,$tax]=$this->input($input);
        return $this->transaction(function()use($issuer,$actor,$name,$tax):int{
            $this->authorize($issuer,$actor,'create');$this->conflict($tax,null);$now=date('Y-m-d H:i:s');
            IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'clients(company,vat,datecreated,addedfrom,active) VALUES(?,?,?,?,1)',[$name,$tax,$now,$actor]);
            $id=(int)$this->db->insert_id();if($id<1)throw new RuntimeException('issuer_client_write_unconfirmed');
            IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'issuers_clients(client_id,owner_issuer_id,tax_id,created_by,created_at) VALUES(?,?,?,?,?)',[$id,$issuer,$tax,$actor,$now]);
            $this->confirm($issuer,$id,$name,$tax);
            $this->audit($actor,$issuer,$id,'company_client_created');
            $this->authorize($issuer,$actor,'create');
            return $id;
        });
    }
    public function update($issuer,$actor,$client,array $input): void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$client=IssuerPolicy::id($client);[$name,$tax]=$this->input($input);
        $this->transaction(function()use($issuer,$actor,$client,$name,$tax):void{
            $this->authorize($issuer,$actor,'edit');
            if(!$this->rows('SELECT client_id FROM '.$this->prefix.'issuers_clients WHERE client_id=? AND owner_issuer_id=? FOR UPDATE',[$client,$issuer]))throw new DomainException('company_client_access_denied');
            $this->conflict($tax,$client);
            IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'clients SET company=?,vat=? WHERE userid=?',[$name,$tax,$client]);
            IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'issuers_clients SET tax_id=? WHERE client_id=? AND owner_issuer_id=?',[$tax,$client,$issuer]);
            $this->confirm($issuer,$client,$name,$tax);
            $this->audit($actor,$issuer,$client,'company_client_updated');
            $this->authorize($issuer,$actor,'edit');
        });
    }
    /** Explicit replacement of identity-sharing links. Never copies contacts or financial ownership. */
    public function setSharing($issuer,$actor,$client,array $targets): void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$client=IssuerPolicy::id($client);
        if(count($targets)>100)throw new DomainException('company_client_invalid_input');
        $targets=array_values(array_unique(array_map([IssuerPolicy::class,'id'],$targets)));sort($targets,SORT_NUMERIC);
        if(in_array($issuer,$targets,true))throw new DomainException('company_client_invalid_input');
        $this->transaction(function()use($issuer,$actor,$client,$targets):void{
            $this->authorize($issuer,$actor,'share');
            if(!$this->rows('SELECT client_id FROM '.$this->prefix.'issuers_clients WHERE client_id=? AND owner_issuer_id=? FOR UPDATE',[$client,$issuer]))throw new DomainException('company_client_access_denied');
            $existing=array_map(static fn(array $row):int=>(int)$row['issuer_id'],$this->rows('SELECT issuer_id FROM '.$this->prefix.'issuers_client_links WHERE client_id=? ORDER BY issuer_id FOR UPDATE',[$client]));
            // An owner may always revoke a link; adding one needs permission at the destination too.
            $added=array_diff($targets,$existing);
            foreach($added as $target)$this->authorize($target,$actor,'create');
            if($existing===$targets)return;
            foreach(array_diff($existing,$targets) as $target)IssuerStorage::query($this->db,'DELETE FROM '.$this->prefix.'issuers_client_links WHERE client_id=? AND issuer_id=?',[$client,$target]);
            foreach($added as $target)IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'issuers_client_links(client_id,issuer_id,linked_by,created_at) VALUES(?,?,?,?)',[$client,$target,$actor,date('Y-m-d H:i:s')]);
            $confirmed=array_map(static fn(array $row):int=>(int)$row['issuer_id'],$this->rows('SELECT issuer_id FROM '.$this->prefix.'issuers_client_links WHERE client_id=? ORDER BY issuer_id',[$client]));
            if($confirmed!==$targets)throw new RuntimeException('issuer_client_write_unconfirmed');
            $this->audit($actor,$issuer,$client,'company_client_sharing_changed');
            $this->authorize($issuer,$actor,'share');foreach($added as $target)$this->authorize($target,$actor,'create');
        });
    }
    private function confirm(int $issuer,int $client,string $name,?string $tax): void
    {
        $row=$this->rows('SELECT c.company,c.vat,o.tax_id FROM '.$this->prefix.'clients c JOIN '.$this->prefix.'issuers_clients o ON o.client_id=c.userid WHERE c.userid=? AND o.owner_issuer_id=?',[$client,$issuer])[0]??null;
        if(!$row||$row['company']!==$name||$row['vat']!==$tax||$row['tax_id']!==$tax)throw new RuntimeException('issuer_client_write_unconfirmed');
    }
    private function audit(int $actor,int $issuer,int $client,string $event): void
    {
        IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,?,?)',[$actor,$event,$issuer,$client,date('Y-m-d H:i:s')]);
    }
}
