<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyContractsStorage.php';
require_once __DIR__.'/CompanySettingsStorage.php';
require_once __DIR__.'/CompanyInvoicePresentation.php';
final class CompanyContracts extends CompanyContractsStorage
{
    protected string $feature='contracts';
    private static function input(array $input):array
    {
        if(array_diff(array_keys($input),['client_id','name','body','start_date','due_date']))throw new DomainException('company_operation_invalid_input');$data=['client_id'=>IssuerPolicy::id($input['client_id']??null),'name'=>self::text($input['name']??null,191),'body'=>self::text($input['body']??null,40000),'start_date'=>self::date($input['start_date']??null),'due_date'=>self::date($input['due_date']??null)];if($data['due_date']<$data['start_date'])throw new DomainException('company_operation_invalid_input');return $data;
    }
    private function client(int $issuer,int $actor,int $client):array
    {
        $row=(new CompanyClients($this->db,$this->p))->get($issuer,$actor,$client);if(!$row||$row['active']!==1)throw new DomainException('company_operation_reference_denied');return $row;
    }
    private function row(int $issuer,int $id):array
    {
        $row=$this->rows('SELECT * FROM '.$this->p.'company_contracts WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$id])[0]??null;if(!$row)throw new DomainException('company_operation_access_denied');return $row;
    }
    private function insert(int $issuer,int $actor,array $data):int
    {
        $this->client($issuer,$actor,$data['client_id']);$now=date('Y-m-d H:i:s');IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_contracts(issuer_id,client_id,number,name,body,status,start_date,due_date,snapshot_json,revision,created_at,updated_at) VALUES(?,?,NULL,?,?,?, ?,?,?,1,?,?)',[$issuer,$data['client_id'],$data['name'],$data['body'],'draft',$data['start_date'],$data['due_date'],'{}',$now,$now]);$id=(int)$this->db->insert_id();$r=$this->row($issuer,$id);foreach($data as $k=>$v)if((string)$r[$k]!== (string)$v)throw new RuntimeException('issuer_operation_write_unconfirmed');return $id;
    }
    public function create($issuer,$actor,array $input,string $key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$data=self::input($input);return $this->operation($issuer,$actor,'create',$key,'contract_created',$data,fn()=>$this->insert($issuer,$actor,$data));
    }
    public function update($issuer,$actor,$id,$revision,array $input,string $key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$revision=self::revision($revision);$data=self::input($input);return $this->operation($issuer,$actor,'edit',$key,'contract_updated',[$id,$revision,$data],function()use($issuer,$actor,$id,$revision,$data):int{$old=$this->row($issuer,$id);if((int)$old['revision']!==$revision)throw new DomainException('company_operation_stale');if($old['status']!=='draft'||(int)$old['client_id']!==$data['client_id'])throw new DomainException('company_contract_immutable');$this->client($issuer,$actor,$data['client_id']);IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_contracts SET name=?,body=?,start_date=?,due_date=?,revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[$data['name'],$data['body'],$data['start_date'],$data['due_date'],date('Y-m-d H:i:s'),$issuer,$id,$revision]);$stored=$this->row($issuer,$id);if((int)$stored['revision']!==$revision+1)throw new RuntimeException('issuer_operation_write_unconfirmed');foreach($data as $k=>$v)if((string)$stored[$k]!== (string)$v)throw new RuntimeException('issuer_operation_write_unconfirmed');return $id;});
    }
    public function transition($issuer,$actor,$id,$revision,string $action,string $key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$revision=self::revision($revision);if(!in_array($action,['activate','cancel','copy'],true))throw new DomainException('company_operation_invalid_input');
        return $this->operation($issuer,$actor,$action==='copy'?'create':'edit',$key,'contract_'.$action,[$id,$revision,$action],function()use($issuer,$actor,$id,$revision,$action):int{
            $row=$this->row($issuer,$id);if((int)$row['revision']!==$revision)throw new DomainException('company_operation_stale');
            if($row['status']==='deleted')throw new DomainException('company_contract_immutable');
            if($action==='copy'){$this->authorize($issuer,$actor,'view');$data=[];foreach(['client_id','name','body','start_date','due_date'] as $k)$data[$k]=$row[$k];return $this->insert($issuer,$actor,self::input($data));}
            $snapshot=$row['snapshot_json'];$number=$row['number'];
            if($action==='activate'){
                if($row['status']!=='draft')throw new DomainException('company_contract_immutable');$client=$this->client($issuer,$actor,(int)$row['client_id']);$identity=$this->rows('SELECT legal_name,tax_id FROM '.$this->p.'issuers_registry WHERE id=? AND active=1 FOR UPDATE',[$issuer])[0]??null;if(!$identity||trim($identity['legal_name'])===''||!is_string($identity['tax_id'])||$identity['tax_id']==='')throw new DomainException('company_contract_identity_required');
                $counter=$this->rows('SELECT next_number FROM '.$this->p.'company_contract_counters WHERE issuer_id=? FOR UPDATE',[$issuer])[0]??null;if(!$counter){IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_contract_counters(issuer_id,next_number) VALUES(?,1)',[$issuer]);$counter=['next_number'=>1];}$number=(int)$counter['next_number'];if($number<1||$number>2147483646)throw new DomainException('company_operation_limit');IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_contract_counters SET next_number=? WHERE issuer_id=?',[$number+1,$issuer]);if(!$this->rows('SELECT issuer_id FROM '.$this->p.'company_contract_counters WHERE issuer_id=? AND next_number=?',[$issuer,$number+1]))throw new RuntimeException('issuer_operation_write_unconfirmed');
                $snapshot=json_encode(['issuer_id'=>$issuer,'identity'=>$identity,'client'=>['id'=>(int)$client['userid'],'name'=>$client['company'],'tax_id'=>$client['vat']],'number'=>$number,'name'=>$row['name'],'body'=>$row['body'],'start_date'=>$row['start_date'],'due_date'=>$row['due_date'],'pdf_preferences'=>CompanyInvoicePresentation::capture(new CompanySettingsStorage($this->db,$this->p),$issuer,'contracts')],JSON_THROW_ON_ERROR);
            }elseif(!in_array($row['status'],['draft','active'],true))throw new DomainException('company_contract_immutable');
            $status=$action==='activate'?'active':'cancelled';IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_contracts SET number=?,snapshot_json=?,status=?,revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[$number,$snapshot,$status,date('Y-m-d H:i:s'),$issuer,$id,$revision]);$r=$this->row($issuer,$id);if($r['status']!==$status||(int)$r['revision']!==$revision+1||$r['snapshot_json']!==$snapshot)throw new RuntimeException('issuer_operation_write_unconfirmed');return $id;
        });
    }
    /** Renewal creates a reviewed successor; the issued contract and its frozen PDF remain intact. */
    public function renew($issuer,$actor,$id,$revision,string $start,string $end,string $key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$revision=self::revision($revision);$start=self::date($start);$end=self::date($end);if($end<$start)throw new DomainException('company_operation_invalid_input');
        return $this->operation($issuer,$actor,'edit',$key,'contract_renewed',[$id,$revision,$start,$end],function()use($issuer,$actor,$id,$revision,$start,$end,$key):int{
            $this->authorize($issuer,$actor,'create');$old=$this->row($issuer,$id);if((int)$old['revision']!==$revision)throw new DomainException('company_operation_stale');if($old['status']!=='active')throw new DomainException('company_contract_immutable');
            $new=$this->insert($issuer,$actor,['client_id'=>(int)$old['client_id'],'name'=>$old['name'],'body'=>$old['body'],'start_date'=>$start,'due_date'=>$end]);
            $metadata=json_encode(['request_key'=>$key,'source_id'=>$id,'source_revision'=>$revision,'successor_id'=>$new,'start_date'=>$start,'due_date'=>$end],JSON_THROW_ON_ERROR);
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$actor,'native_contract_renewal_linked',$issuer,$id,gmdate('Y-m-d H:i:s'),$metadata]);$audit=(int)$this->db->insert_id();
            if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND subject_id=? AND event=? AND metadata_json=?',[$audit,$issuer,$id,'native_contract_renewal_linked',$metadata])||$this->row($issuer,$id)!==$old)throw new RuntimeException('issuer_operation_write_unconfirmed');
            return $new;
        });
    }
    /** A removal is a retained tombstone, so files, comments, numbers and snapshots stay auditable. */
    public function remove($issuer,$actor,$id,$revision,string $key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$revision=self::revision($revision);
        return $this->operation($issuer,$actor,'delete',$key,'contract_deleted',[$id,$revision],function()use($issuer,$id,$revision):int{
            $before=$this->row($issuer,$id);if((int)$before['revision']!==$revision)throw new DomainException('company_operation_stale');if($before['status']==='deleted')throw new DomainException('company_contract_immutable');
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_contracts SET status=?,revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',['deleted',gmdate('Y-m-d H:i:s'),$issuer,$id,$revision]);$after=$this->row($issuer,$id);
            if($after['status']!=='deleted'||(int)$after['revision']!==$revision+1||$after['snapshot_json']!==$before['snapshot_json']||$after['number']!==$before['number'])throw new RuntimeException('issuer_operation_write_unconfirmed');return$id;
        });
    }
    public function history($issuer,$actor,$id):array
    {
        $this->get($issuer,$actor,$id);$rows=$this->rows('SELECT id,event,actor_id,created_at,metadata_json FROM '.$this->p.'issuers_audit WHERE issuer_id=? AND subject_id=? AND (event LIKE ? OR event LIKE ?) ORDER BY id DESC LIMIT 200',[$issuer,$id,'company_contract_%','native_contract_%']);$this->authorize($issuer,$actor,'view');return$rows;
    }
    public function get($issuer,$actor,$id):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);return $this->transaction(function()use($issuer,$actor,$id):array{(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,'view');$row=$this->row($issuer,$id);$this->authorize($issuer,$actor,'view');return $row;});
    }
    public function page($issuer,$actor,$after=0,string $search=''):array
    {
        $read=function()use($issuer,$actor,$after,$search){
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$after=$after===0?0:IssuerPolicy::id($after);$search=self::text($search,191,false);$this->authorize($issuer,$actor,'view');$needle='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';$rows=$this->rows('SELECT id,number,name,status,due_date,revision FROM '.$this->p.'company_contracts WHERE status<>\'deleted\' AND issuer_id=? AND id>? AND name LIKE ? ESCAPE \'!\' ORDER BY id LIMIT 51',[$issuer,$after,$needle]);$this->authorize($issuer,$actor,'view');return ['items'=>array_slice($rows,0,50),'next'=>count($rows)>50?(int)$rows[49]['id']:null];

        };return $this->originGuard===null?$read():$this->transaction($read);
    }
    /** Revalidated after rendering and before any PDF bytes leave the controller. */
    public function confirmDownload($issuer,$actor,$id,$revision):void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$revision=self::revision($revision);$this->transaction(function()use($issuer,$actor,$id,$revision):void{(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,'view');$row=$this->row($issuer,$id);if((int)$row['revision']!==$revision||$row['number']===null||!in_array($row['status'],['active','cancelled'],true))throw new DomainException('company_contract_pdf_denied');$this->audit($issuer,$actor,$id,'company_contract_pdf_downloaded');$this->authorize($issuer,$actor,'view');});
    }
}
