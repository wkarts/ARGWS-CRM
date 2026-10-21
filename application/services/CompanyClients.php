<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';
require_once __DIR__.'/CompanyClientProfile.php';

/** Native private customer operations. No session, hooks, mail, contacts or finance hydration.
 * The isolated directory exposes only this projection; legacy readers remain closed.
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
    public function page($issuer,$actor,$after=0,int $limit=50,string $search=""): array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);
        if ($after!==0) $after=IssuerPolicy::id($after);
        if ($limit<1||$limit>100) throw new DomainException('company_client_invalid_page');
        if(strlen($search)>191||preg_match('/[\x00-\x1f]/',$search))throw new DomainException('company_client_invalid_input');
        $this->authorize($issuer,$actor,'view');
        // Escape LIKE metacharacters with a portable explicit escape character.
        $needle='%'.str_replace(['!','%','_'],['!!','!%','!_'],trim($search)).'%';
        $rows=$this->rows('SELECT '.$this->projection().' FROM '.$this->prefix.'clients c JOIN '.$this->prefix.'issuers_clients o ON o.client_id=c.userid WHERE c.userid>? AND '.$this->visibility()." AND (c.company LIKE ? ESCAPE '!' OR c.vat LIKE ? ESCAPE '!') ORDER BY c.userid LIMIT ".($limit+1),[$after,$issuer,$issuer,$needle,$needle]);
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
        if (array_diff(array_keys($input),['company','vat','profile'])) throw new DomainException('company_client_invalid_input');
        $name=$input['company']??null;
        if(!is_string($name)||trim($name)===''||strlen(trim($name))>191||preg_match('/[\x00-\x1f]/',$name)) throw new DomainException('company_client_invalid_input');
        $tax=IssuerPolicy::taxId($input['vat']??'',false);$profile=null;if(array_key_exists('profile',$input)){if(!is_array($input['profile']))throw new DomainException('company_client_invalid_input');$profile=CompanyClientProfile::normalize($input['profile'],$tax);}
        return [trim($name),$tax,$profile];
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
    /** Participants for the same native writer, including durable create receipts when requested. */
    public function tables(bool $receipts=true):array
    {
        return array_merge(['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','issuers_clients','issuers_client_links','issuers_audit','staff','clients'],$receipts?['company_client_requests']:[],CompanyClientProfileSchema::ready($this->db,$this->prefix)?['company_client_profiles']:[]);
    }
    private function transaction(callable $operation,bool $receipts=false)
    {
        return IssuerTransaction::run($this->db,$this->prefix,$this->tables($receipts),$operation);
    }
    /** Veto-only preview: uses exactly the writer's name/VAT policy, without adopting existing identities. */
    public function previewInOwnedTransaction($issuer,$actor,array $input):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);IssuerTransaction::assertOwned($this->db,$this->prefix,$this->tables(false));$this->authorize($issuer,$actor,'create');[$name,$tax,$profile]=$this->input($input);$this->conflict($tax,null);$this->authorize($issuer,$actor,'create');return ['company'=>$name,'vat'=>$tax??'']+($profile===null?[]:['profile'=>$profile]);
    }
    public function create($issuer,$actor,array $input,?string $requestKey=null): int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);[$name,$tax,$profile]=$this->input($input);
        if($requestKey!==null&&!preg_match('/^[a-f0-9]{64}$/D',$requestKey))throw new DomainException('company_client_invalid_input');
        return $this->transaction(fn():int=>$this->createPreparedInOwned($issuer,$actor,$name,$tax,$requestKey,$profile),$requestKey!==null);
    }
    /** A bounded import composes this exact writer; raw outer transactions remain forbidden. */
    public function createInOwnedTransaction($issuer,$actor,array $input,string $requestKey):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);[$name,$tax,$profile]=$this->input($input);if(!preg_match('/^[a-f0-9]{64}$/D',$requestKey))throw new DomainException('company_client_invalid_input');return $this->createPreparedInOwned($issuer,$actor,$name,$tax,$requestKey,$profile);
    }
    private function createPreparedInOwned(int $issuer,int $actor,string $name,?string $tax,?string $requestKey,?array $profile=null):int
    {
        IssuerTransaction::assertOwned($this->db,$this->prefix,$this->tables($requestKey!==null));$hash=hash('sha256',json_encode($profile===null?[$name,$tax]:[$name,$tax,$profile],JSON_THROW_ON_ERROR));
            $this->authorize($issuer,$actor,'create');
            if($requestKey!==null){
                $receipt=$this->rows('SELECT issuer_id,actor_id,input_hash,client_id FROM '.$this->prefix.'company_client_requests WHERE request_key=? FOR UPDATE',[$requestKey])[0]??null;
                if($receipt){
                    if((int)$receipt['issuer_id']!==$issuer||(int)$receipt['actor_id']!==$actor||!hash_equals($receipt['input_hash'],$hash))throw new DomainException('company_client_request_conflict');
                    if(!$this->rows('SELECT client_id FROM '.$this->prefix.'issuers_clients WHERE client_id=? AND owner_issuer_id=?',[$receipt['client_id'],$issuer]))throw new DomainException('company_client_request_conflict');
                    return (int)$receipt['client_id'];
                }
            }
            $this->conflict($tax,null);$now=date('Y-m-d H:i:s');
            IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'clients(company,vat,datecreated,addedfrom,active) VALUES(?,?,?,?,1)',[$name,$tax,$now,$actor]);
            $id=(int)$this->db->insert_id();if($id<1)throw new RuntimeException('issuer_client_write_unconfirmed');
            IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'issuers_clients(client_id,owner_issuer_id,tax_id,created_by,created_at) VALUES(?,?,?,?,?)',[$id,$issuer,$tax,$actor,$now]);
            if($profile!==null)(new CompanyClientProfile($this->db,$this->prefix))->write($issuer,$actor,$id,$profile,'create');
            $this->confirm($issuer,$id,$name,$tax);
            $this->audit($actor,$issuer,$id,'company_client_created');
            if($requestKey!==null){
                IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'company_client_requests(request_key,issuer_id,actor_id,input_hash,client_id,created_at) VALUES(?,?,?,?,?,?)',[$requestKey,$issuer,$actor,$hash,$id,$now]);
                $receipt=$this->rows('SELECT client_id,input_hash FROM '.$this->prefix.'company_client_requests WHERE request_key=?',[$requestKey])[0]??[];
                if((int)($receipt['client_id']??0)!==$id||($receipt['input_hash']??null)!==$hash)throw new RuntimeException('issuer_client_write_unconfirmed');
            }
            $this->authorize($issuer,$actor,'create');
            return $id;
    }
    public function update($issuer,$actor,$client,array $input,?string $revision=null): void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$client=IssuerPolicy::id($client);[$name,$tax,$profile]=$this->input($input);
        $this->transaction(function()use($issuer,$actor,$client,$name,$tax,$revision,$profile):void{
            $this->authorize($issuer,$actor,'edit');
            if(!$this->rows('SELECT client_id FROM '.$this->prefix.'issuers_clients WHERE client_id=? AND owner_issuer_id=? FOR UPDATE',[$client,$issuer]))throw new DomainException('company_client_access_denied');
            $this->assertRevision($issuer,$client,$revision);
            $this->conflict($tax,$client);
            IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'clients SET company=?,vat=? WHERE userid=?',[$name,$tax,$client]);
            IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'issuers_clients SET tax_id=? WHERE client_id=? AND owner_issuer_id=?',[$tax,$client,$issuer]);
            if($profile!==null)(new CompanyClientProfile($this->db,$this->prefix))->write($issuer,$actor,$client,$profile);
            $this->confirm($issuer,$client,$name,$tax);
            $this->audit($actor,$issuer,$client,'company_client_updated');
            $this->authorize($issuer,$actor,'edit');
        });
    }
    /** Explicit replacement of identity-sharing links. Never copies contacts or financial ownership. */
    public function setSharing($issuer,$actor,$client,array $targets,?string $revision=null): void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$client=IssuerPolicy::id($client);
        if(count($targets)>100)throw new DomainException('company_client_invalid_input');
        $targets=array_values(array_unique(array_map([IssuerPolicy::class,'id'],$targets)));sort($targets,SORT_NUMERIC);
        if(in_array($issuer,$targets,true))throw new DomainException('company_client_invalid_input');
        $this->transaction(function()use($issuer,$actor,$client,$targets,$revision):void{
            $this->authorize($issuer,$actor,'share');
            if(!$this->rows('SELECT client_id FROM '.$this->prefix.'issuers_clients WHERE client_id=? AND owner_issuer_id=? FOR UPDATE',[$client,$issuer]))throw new DomainException('company_client_access_denied');
            $this->assertRevision($issuer,$client,$revision);
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
    /** Owner-only sharing state. A destination sees no list of other recipients. */
    public function details($issuer,$actor,$client): ?array
    {
        $row=$this->get($issuer,$actor,$client);if(!$row)return null;
        $owner=$row['owner_issuer_id']===(int)$issuer;
        $links=$owner?$this->links((int)$client):[];
        $this->authorize((int)$issuer,(int)$actor,'view');
        return ['client'=>$row,'targets'=>$links,'revision'=>$owner?$this->revision($row,$links):null,'profile'=>$owner?(new CompanyClientProfile($this->db,$this->prefix))->read((int)$issuer,(int)$actor,(int)$client):[]];
    }
    public function sharingOptions($issuer,$actor): array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$this->authorize($issuer,$actor,'share');
        $result=[];
        foreach($this->rows('SELECT id,legal_name FROM '.$this->prefix.'issuers_registry WHERE active=1 AND id<>? ORDER BY id',[$issuer]) as $row){
            if($this->access->can($row['id'],$actor,'customers','create'))$result[]=['id'=>(int)$row['id'],'legal_name'=>$row['legal_name']];
        }
        $this->authorize($issuer,$actor,'share');return $result;
    }
    private function links(int $client): array
    {
        return array_map(static fn(array $row):int=>(int)$row['issuer_id'],$this->rows('SELECT issuer_id FROM '.$this->prefix.'issuers_client_links WHERE client_id=? ORDER BY issuer_id',[$client]));
    }
    private function revision(array $row,array $links): string
    {
        $parts=[$this->project($row),$links];
        if(CompanyClientProfileSchema::ready($this->db,$this->prefix)){$parts[]=$this->rows('SELECT '.implode(',',array_keys(CompanyClientProfile::NATIVE)).' FROM '.$this->prefix.'clients WHERE userid=?',[$row['userid']]);$parts[]=$this->rows('SELECT profile_json FROM '.$this->prefix.'company_client_profiles WHERE issuer_id=? AND client_id=?',[$row['owner_issuer_id'],$row['userid']]);}
        return hash('sha256',json_encode($parts,JSON_THROW_ON_ERROR));
    }
    private function assertRevision(int $issuer,int $client,?string $revision): void
    {
        if($revision===null)return; // Existing internal callers retain their explicit transaction contract.
        $row=$this->rows('SELECT '.$this->projection().' FROM '.$this->prefix.'clients c JOIN '.$this->prefix.'issuers_clients o ON o.client_id=c.userid WHERE c.userid=? AND o.owner_issuer_id=? FOR UPDATE',[$client,$issuer])[0]??null;
        if(!$row||!preg_match('/^[a-f0-9]{64}$/D',$revision)||!hash_equals($this->revision($row,$this->links($client)),$revision))throw new DomainException('company_client_stale');
    }
    private function confirm(int $issuer,int $client,string $name,?string $tax): void
    {
        $row=$this->rows('SELECT c.company,c.vat,o.tax_id FROM '.$this->prefix.'clients c JOIN '.$this->prefix.'issuers_clients o ON o.client_id=c.userid WHERE c.userid=? AND o.owner_issuer_id=?',[$client,$issuer])[0]??null;
        if(!$row||$row['company']!==$name||$row['vat']!==$tax||$row['tax_id']!==$tax)throw new RuntimeException('issuer_client_write_unconfirmed');
    }
    private function audit(int $actor,int $issuer,int $client,string $event): void
    {
        IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,?,?)',[$actor,$event,$issuer,$client,date('Y-m-d H:i:s')]);
        $id=(int)$this->db->insert_id();if(!$this->rows('SELECT id FROM '.$this->prefix.'issuers_audit WHERE id=? AND actor_id=? AND event=? AND issuer_id=? AND subject_id=?',[$id,$actor,$event,$issuer,$client]))throw new RuntimeException('issuer_client_write_unconfirmed');
    }
}
