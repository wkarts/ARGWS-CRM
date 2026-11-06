<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyContractsStorage.php';
require_once __DIR__.'/CompanySettingsStorage.php';
require_once __DIR__.'/CompanyInvoicePresentation.php';
require_once __DIR__.'/CompanyContractValue.php';
require_once __DIR__.'/NativeCompanyContractTypes.php';
require_once __DIR__.'/CompanyAssets.php';
require_once __DIR__.'/CompanyContractMetadata.php';
final class CompanyContracts extends CompanyContractsStorage
{
    protected string $feature='contracts';
    private ?array $metadataFrame=null;
    private ?array $operationExpectations=null;
    protected function extraTables():array{return CompanyContractMetadata::tables($this->db,$this->p);}
    protected function operation(int $issuer,int $actor,string $cap,string $key,string $op,array $payload,callable $work):int
    {
        if($this->operationExpectations!==null)throw new RuntimeException('issuer_owned_transaction_required');$this->operationExpectations=[];
        try{return parent::operation($issuer,$actor,$cap,$key,$op,$payload,$work);}finally{$this->operationExpectations=null;}
    }
    private function expectContract(array $row):void
    {
        if($this->operationExpectations===null)throw new RuntimeException('issuer_owned_transaction_required');
        $this->operationExpectations[(int)$row['id']]=['row'=>$row,'metadata'=>(new CompanyContractMetadata($this->db,$this->p))->readInOwned($row)];
    }
    protected function operationConfirmation(int $issuer,int $actor,int $id):?callable
    {
        $this->expectContract($this->row($issuer,$id));$expected=$this->operationExpectations;
        return function()use($issuer,$expected):void{
            $metadata=new CompanyContractMetadata($this->db,$this->p);
            foreach($expected as$id=>$state){$row=$this->row($issuer,$id);if($row!==$state['row']||$metadata->readInOwned($row)!==$state['metadata'])throw new RuntimeException('issuer_operation_write_unconfirmed');}
        };
    }
    private static function input(array $input):array
    {
        if(array_diff(array_keys($input),['client_id','name','body','start_date','due_date','contract_value','currency','contract_type','native_metadata']))throw new DomainException('company_operation_invalid_input');
        $native=array_key_exists('native_metadata',$input)?CompanyContractMetadata::normalize($input['native_metadata']):null;
        $data=['client_id'=>IssuerPolicy::id($input['client_id']??null),'name'=>self::text($input['name']??null,191),
            'body'=>self::text($input['body']??($native!==null?'':null),40000,$native===null),'start_date'=>self::date($input['start_date']??null),
            'due_date'=>$native!==null&&in_array($input['due_date']??null,[null,''],true)?null:self::date($input['due_date']??null)];
        if($data['due_date']!==null&&$data['due_date']<$data['start_date'])throw new DomainException('company_operation_invalid_input');
        if(isset($input['contract_type'])&&$input['contract_type']!==''){$ref=NativeCompanyResourceId::decode('contract_type',$input['contract_type']);if($ref['source_kind']!=='company_contract_type')throw new DomainException('native_contract_type_denied');$data['contract_type']=$ref['canonical_id'];}
        elseif($native!==null&&array_key_exists('contract_type',$input))$data['contract_type']=null;
        $data+=CompanyContractValue::input($input);
        if(($native['clear_value']??0)===1&&isset($data['contract_value']))throw new DomainException('native_contract_metadata_invalid');
        if($native!==null)$data['native_metadata']=$native;return$data;
    }
    private function client(int $issuer,int $actor,int $client):array
    {
        $row=(new CompanyClients($this->db,$this->p))->get($issuer,$actor,$client);if(!$row||$row['active']!==1)throw new DomainException('company_operation_reference_denied');return $row;
    }
    private function row(int $issuer,int $id):array
    {
        $row=$this->rows('SELECT * FROM '.$this->p.'company_contracts WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$id])[0]??null;if(!$row)throw new DomainException('company_operation_access_denied');return $row;
    }
    private function insert(int $issuer,int $actor,array $data,?array $preservedMetadata=null):int
    {
        $this->client($issuer,$actor,$data['client_id']);$value=CompanyContractValue::input($data);CompanyContractValue::verifyCurrency($this->db,$this->p,$value);
        $type=isset($data['contract_type'])?['contract_type'=>(new NativeCompanyContractTypes($this->db,$this->p))->referenceInOwned($issuer,$actor,$data['contract_type'])]:[];
        $metadata=$preservedMetadata??(isset($data['native_metadata'])?(new CompanyContractMetadata($this->db,$this->p))->capture($issuer,$actor,$data['client_id'],$data['native_metadata'],'create'):null);
        $draftSnapshot=$value||$type?json_encode($value+$type,JSON_THROW_ON_ERROR):'{}';$now=date('Y-m-d H:i:s');
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_contracts(issuer_id,client_id,number,name,body,status,start_date,due_date,snapshot_json,revision,created_at,updated_at) VALUES(?,?,NULL,?,?,?, ?,?,?,1,?,?)',[$issuer,$data['client_id'],$data['name'],$data['body'],'draft',$data['start_date'],$data['due_date'],$draftSnapshot,$now,$now]);$id=(int)$this->db->insert_id();$r=$this->row($issuer,$id);
        if($r['snapshot_json']!==$draftSnapshot)throw new RuntimeException('issuer_operation_write_unconfirmed');
        foreach(array_diff_key($data,$value,['contract_type'=>true,'native_metadata'=>true])as$k=>$v)if($v===null?$r[$k]!==null:(string)$r[$k]!== (string)$v)throw new RuntimeException('issuer_operation_write_unconfirmed');
        if($metadata!==null)$this->persistMetadata($issuer,$actor,$id,1,'create',$metadata);
        return$id;
    }
    private function persistMetadata(int $issuer,int $actor,int $id,int $revision,string $cap,array $data):void
    {
        if($this->metadataFrame!==null)throw new DomainException('native_custom_field_creation_denied');
        $this->metadataFrame=['issuer'=>$issuer,'actor'=>$actor,'id'=>$id,'revision'=>$revision,'cap'=>$cap,'fields'=>$data['custom_fields'],'phase'=>'write'];
        try{(new CompanyContractMetadata($this->db,$this->p))->save($issuer,$actor,$id,$revision,$data,$this);}finally{$this->metadataFrame=null;}
    }
    /** Proof is live only inside this final writer, never obtained by starting another owned transaction. */
    public function assertNativeMetadataWriteInOwned($db,string $p,int $issuer,int $actor,int $id,int $revision,string $phase='write'):array
    {
        $frame=$this->metadataFrame;
        if($db!==$this->db||$p!==$this->p||$frame===null||$frame['issuer']!==$issuer||$frame['actor']!==$actor||$frame['id']!==$id||$frame['revision']!==$revision||$frame['phase']!==$phase)throw new DomainException('native_custom_field_creation_denied');
        IssuerTransaction::assertOwned($this->db,$this->p,$this->extraTables());$this->authorize($issuer,$actor,$frame['cap']);$row=$this->row($issuer,$id);
        if((int)$row['revision']!==$revision||$row['status']!==($phase==='write'?'draft':'active')||($phase==='write'&&$row['number']!==null))throw new DomainException('native_custom_field_creation_denied');
        return['contract'=>$row,'cap'=>$frame['cap'],'fields'=>$frame['fields']];
    }
    public function create($issuer,$actor,array $input,string $key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$data=self::input($input);return $this->operation($issuer,$actor,'create',$key,'contract_created',$data,fn()=>$this->insert($issuer,$actor,$data));
    }
    public function update($issuer,$actor,$id,$revision,array $input,string $key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$revision=self::revision($revision);$data=self::input($input);
        return$this->operation($issuer,$actor,'edit',$key,'contract_updated',[$id,$revision,$data],function()use($issuer,$actor,$id,$revision,$data):int{
            $old=$this->row($issuer,$id);if((int)$old['revision']!==$revision)throw new DomainException('company_operation_stale');
            if($old['status']!=='draft'||(!isset($data['native_metadata'])&&(int)$old['client_id']!==$data['client_id']))throw new DomainException('company_contract_immutable');
            $this->client($issuer,$actor,$data['client_id']);$native=new CompanyContractMetadata($this->db,$this->p);$previous=$native->readInOwned($old);
            $metadata=isset($data['native_metadata'])?$native->capture($issuer,$actor,$data['client_id'],$data['native_metadata'],'edit',$previous,$id):$previous;
            $value=($data['native_metadata']['clear_value']??0)===1?[]:(CompanyContractValue::input($data)?:CompanyContractValue::stored(json_decode($old['snapshot_json'],true,32,JSON_THROW_ON_ERROR)));CompanyContractValue::verifyCurrency($this->db,$this->p,$value);
            $type=array_key_exists('contract_type',$data)?($data['contract_type']===null?[]:['contract_type'=>(new NativeCompanyContractTypes($this->db,$this->p))->referenceInOwned($issuer,$actor,$data['contract_type'])]):NativeCompanyContractTypes::stored(json_decode($old['snapshot_json'],true,32,JSON_THROW_ON_ERROR));
            $draftSnapshot=$value||$type?json_encode($value+$type,JSON_THROW_ON_ERROR):'{}';
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_contracts SET client_id=?,name=?,body=?,start_date=?,due_date=?,snapshot_json=?,revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[$data['client_id'],$data['name'],$data['body'],$data['start_date'],$data['due_date'],$draftSnapshot,date('Y-m-d H:i:s'),$issuer,$id,$revision]);
            $stored=$this->row($issuer,$id);if((int)$stored['revision']!==$revision+1||$stored['snapshot_json']!==$draftSnapshot)throw new RuntimeException('issuer_operation_write_unconfirmed');
            foreach(array_diff_key($data,$value,['contract_type'=>true,'native_metadata'=>true])as$k=>$v)if($v===null?$stored[$k]!==null:(string)$stored[$k]!== (string)$v)throw new RuntimeException('issuer_operation_write_unconfirmed');
            if($metadata!==null)$this->persistMetadata($issuer,$actor,$id,$revision+1,'edit',$metadata);return$id;
        });
    }
    public function transition($issuer,$actor,$id,$revision,string $action,string $key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$revision=self::revision($revision);if(!in_array($action,['activate','cancel','copy'],true))throw new DomainException('company_operation_invalid_input');
        return $this->operation($issuer,$actor,$action==='copy'?'create':'edit',$key,'contract_'.$action,[$id,$revision,$action],function()use($issuer,$actor,$id,$revision,$action):int{
            $row=$this->row($issuer,$id);if((int)$row['revision']!==$revision)throw new DomainException('company_operation_stale');
            if($row['status']==='deleted')throw new DomainException('company_contract_immutable');
            if($action==='copy'){
                $this->expectContract($row);
                $this->authorize($issuer,$actor,'view');$data=[];foreach(['client_id','name','body','start_date','due_date']as$k)$data[$k]=$row[$k];
                $metadata=(new CompanyContractMetadata($this->db,$this->p))->copyData($issuer,$actor,$row,(int)$row['client_id']);
                if($metadata!==null)$data['native_metadata']=['version'=>1];
                $type=NativeCompanyContractTypes::stored(json_decode($row['snapshot_json'],true,32,JSON_THROW_ON_ERROR));if($type)$data['contract_type']=$type['contract_type']['id'];
                return$this->insert($issuer,$actor,self::input($data)+CompanyContractValue::stored(json_decode($row['snapshot_json'],true,32,JSON_THROW_ON_ERROR)),$metadata);
            }
            $snapshot=$row['snapshot_json'];$number=$row['number'];
            if($action==='activate'){
                if($row['status']!=='draft')throw new DomainException('company_contract_immutable');if(trim($row['body'])==='')throw new DomainException('company_contract_content_required');$client=$this->client($issuer,$actor,(int)$row['client_id']);$identity=$this->rows('SELECT legal_name,tax_id FROM '.$this->p.'issuers_registry WHERE id=? AND active=1 FOR UPDATE',[$issuer])[0]??null;if(!$identity||trim($identity['legal_name'])===''||!is_string($identity['tax_id'])||$identity['tax_id']==='')throw new DomainException('company_contract_identity_required');
                $counter=$this->rows('SELECT next_number FROM '.$this->p.'company_contract_counters WHERE issuer_id=? FOR UPDATE',[$issuer])[0]??null;if(!$counter){IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_contract_counters(issuer_id,next_number) VALUES(?,1)',[$issuer]);$counter=['next_number'=>1];}$number=(int)$counter['next_number'];if($number<1||$number>2147483646)throw new DomainException('company_operation_limit');IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_contract_counters SET next_number=? WHERE issuer_id=?',[$number+1,$issuer]);if(!$this->rows('SELECT issuer_id FROM '.$this->p.'company_contract_counters WHERE issuer_id=? AND next_number=?',[$issuer,$number+1]))throw new RuntimeException('issuer_operation_write_unconfirmed');
                $value=CompanyContractValue::stored(json_decode($row['snapshot_json'],true,32,JSON_THROW_ON_ERROR));CompanyContractValue::verifyCurrency($this->db,$this->p,$value);$store=new CompanySettingsStorage($this->db,$this->p);$get=fn($key)=>$store->get($issuer,$key);$identity+=['address'=>CompanySettingsIdentity::documentAddress($get),'city'=>$get('invoice_company_city'),'state'=>$get('company_state'),'postcode'=>$get('invoice_company_postal_code'),'country_code'=>$get('invoice_company_country_code'),'phone'=>$get('invoice_company_phonenumber'),'email'=>$get('smtp_email')]+(new CompanyAssets($this->db,$this->p))->contractProfileInOwnedTransaction($issuer,$actor);
                $type=NativeCompanyContractTypes::stored(json_decode($row['snapshot_json'],true,32,JSON_THROW_ON_ERROR));$snapshot=json_encode(['issuer_id'=>$issuer,'identity'=>$identity,'client'=>['id'=>(int)$client['userid'],'name'=>$client['company'],'tax_id'=>$client['vat']],'number'=>$number,'name'=>$row['name'],'body'=>$row['body'],'start_date'=>$row['start_date'],'due_date'=>$row['due_date'],'pdf_preferences'=>CompanyInvoicePresentation::capture(new CompanySettingsStorage($this->db,$this->p),$issuer,'contracts')]+$value+$type,JSON_THROW_ON_ERROR);
            }elseif(!in_array($row['status'],['draft','active'],true))throw new DomainException('company_contract_immutable');
            $native=new CompanyContractMetadata($this->db,$this->p);$metadata=$native->readInOwned($row);
            $status=$action==='activate'?'active':'cancelled';IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_contracts SET number=?,snapshot_json=?,status=?,revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[$number,$snapshot,$status,date('Y-m-d H:i:s'),$issuer,$id,$revision]);$r=$this->row($issuer,$id);if($r['status']!==$status||(int)$r['revision']!==$revision+1||$r['snapshot_json']!==$snapshot)throw new RuntimeException('issuer_operation_write_unconfirmed');
            if($action==='activate'&&$metadata!==null){
                if($this->metadataFrame!==null)throw new DomainException('native_custom_field_creation_denied');
                $this->metadataFrame=['issuer'=>$issuer,'actor'=>$actor,'id'=>$id,'revision'=>$revision+1,'cap'=>'edit','fields'=>$metadata['custom_fields'],'phase'=>'freeze'];
                try{$native->freeze($issuer,$actor,$row,$r,$this);}finally{$this->metadataFrame=null;}
            }
            return$id;
        });
    }
    /** Renewal creates a reviewed successor; the issued contract and its frozen PDF remain intact. */
    public function renew($issuer,$actor,$id,$revision,string $start,string $end,string $key,?string $newValue=null,?string $currency=null):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$revision=self::revision($revision);$start=self::date($start);$end=$end===''?null:self::date($end);if($end!==null&&$end<$start)throw new DomainException('company_operation_invalid_input');
        $replacement=$newValue===null||$newValue===''?[]:CompanyContractValue::input(['contract_value'=>$newValue,'currency'=>$currency]);
        $payload=[$id,$revision,$start,$end];if($replacement)$payload[]=$replacement;
        return $this->operation($issuer,$actor,'edit',$key,'contract_renewed',$payload,function()use($issuer,$actor,$id,$revision,$start,$end,$key,$replacement):int{
            $this->authorize($issuer,$actor,'create');$old=$this->row($issuer,$id);if((int)$old['revision']!==$revision)throw new DomainException('company_operation_stale');if($old['status']!=='active')throw new DomainException('company_contract_immutable');
            $this->expectContract($old);
            $value=$replacement?:CompanyContractValue::stored(json_decode($old['snapshot_json'],true,32,JSON_THROW_ON_ERROR));
            $native=new CompanyContractMetadata($this->db,$this->p);$metadata=$native->copyData($issuer,$actor,$old,(int)$old['client_id']);
            $type=NativeCompanyContractTypes::stored(json_decode($old['snapshot_json'],true,32,JSON_THROW_ON_ERROR));$type=$type?['contract_type'=>$type['contract_type']['id']]:[];
            $data=['client_id'=>(int)$old['client_id'],'name'=>$old['name'],'body'=>$old['body'],'start_date'=>$start,'due_date'=>$end]+$value+$type;
            if($end===null||$metadata!==null)$data['native_metadata']=['version'=>1];
            $new=$this->insert($issuer,$actor,$data,$metadata);
            $metadata=json_encode(['request_key'=>$key,'source_id'=>$id,'source_revision'=>$revision,'successor_id'=>$new,'start_date'=>$start,'due_date'=>$end]+$value,JSON_THROW_ON_ERROR);
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
        $this->get($issuer,$actor,$id);$rows=$this->rows('SELECT id,event,actor_id,created_at,metadata_json FROM '.$this->p.'issuers_audit WHERE issuer_id=? AND subject_id=? AND (event LIKE ? OR event LIKE ?) AND event NOT IN (?,?) ORDER BY id DESC LIMIT 200',[$issuer,$id,'company_contract_%','native_contract_%','native_contract_metadata_saved','native_contract_metadata_frozen']);$this->authorize($issuer,$actor,'view');return$rows;
    }
    /** A staff declaration is separate from a customer's signature and the issued text. */
    public function signatureState($issuer,$actor,$id):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);
        return $this->transaction(function()use($issuer,$actor,$id):array{
            (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,'view');$this->row($issuer,$id);
            $row=$this->rows('SELECT actor_id,created_at,metadata_json FROM '.$this->p.'issuers_audit WHERE issuer_id=? AND subject_id=? AND event=? ORDER BY id DESC LIMIT 1',[$issuer,$id,'native_contract_signature_changed'])[0]??null;
            if($row===null)return ['marked'=>false,'actor_id'=>null,'recorded_at'=>null];
            $data=json_decode($row['metadata_json'],true,32,JSON_THROW_ON_ERROR);if(!is_bool($data['marked']??null))throw new RuntimeException('issuer_operation_write_unconfirmed');
            $this->authorize($issuer,$actor,'view');return ['marked'=>$data['marked'],'actor_id'=>(int)$row['actor_id'],'recorded_at'=>$row['created_at']];
        });
    }
    public function markSignature($issuer,$actor,$id,$revision,string $action,string $key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$revision=self::revision($revision);
        if(!in_array($action,['mark_as_signed','unmark_as_signed','clear_signature'],true))throw new DomainException('company_operation_invalid_input');
        return $this->operation($issuer,$actor,$action==='clear_signature'?'delete':'edit',$key,'contract_'.$action,[$id,$revision,$action],function()use($issuer,$actor,$id,$revision,$action):int{
            $before=$this->row($issuer,$id);if((int)$before['revision']!==$revision)throw new DomainException('company_operation_stale');if($before['status']!=='active')throw new DomainException('company_contract_immutable');
            $metadata=json_encode(['marked'=>$action==='mark_as_signed','action'=>$action,'request_source'=>'staff_declaration','revision'=>$revision],JSON_THROW_ON_ERROR);
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$actor,'native_contract_signature_changed',$issuer,$id,gmdate('Y-m-d H:i:s'),$metadata]);$audit=(int)$this->db->insert_id();
            if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND subject_id=? AND metadata_json=?',[$audit,$issuer,$id,$metadata]))throw new RuntimeException('issuer_operation_write_unconfirmed');
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_contracts SET revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[gmdate('Y-m-d H:i:s'),$issuer,$id,$revision]);$after=$this->row($issuer,$id);
            if((int)$after['revision']!==$revision+1||$after['snapshot_json']!==$before['snapshot_json']||$after['body']!==$before['body'])throw new RuntimeException('issuer_operation_write_unconfirmed');return$id;
        });
    }
    /** The original content action edits a draft; an issued agreement is revised by renewal. */
    public function saveContent($issuer,$actor,$id,$revision,string $content,string $key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$revision=self::revision($revision);$content=self::text($content,40000);
        return $this->operation($issuer,$actor,'edit',$key,'contract_content_saved',[$id,$revision,$content],function()use($issuer,$id,$revision,$content):int{
            $before=$this->row($issuer,$id);if((int)$before['revision']!==$revision)throw new DomainException('company_operation_stale');if($before['status']!=='draft')throw new DomainException('company_contract_immutable');
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_contracts SET body=?,revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[$content,gmdate('Y-m-d H:i:s'),$issuer,$id,$revision]);$after=$this->row($issuer,$id);
            if($after['body']!==$content||(int)$after['revision']!==$revision+1||$after['snapshot_json']!==$before['snapshot_json'])throw new RuntimeException('issuer_operation_write_unconfirmed');return$id;
        });
    }
    /** Cancel only the unchanged renewal draft; keep the link, draft and all audit records. */
    public function cancelRenewal($issuer,$actor,$id,$revision,$renewal,$renewalRevision,string $key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$revision=self::revision($revision);$renewal=IssuerPolicy::id($renewal);$renewalRevision=self::revision($renewalRevision);
        return $this->operation($issuer,$actor,'delete',$key,'contract_renewal_cancelled',[$id,$revision,$renewal,$renewalRevision],function()use($issuer,$actor,$id,$revision,$renewal,$renewalRevision):int{
            $parent=$this->row($issuer,$id);if((int)$parent['revision']!==$revision)throw new DomainException('company_operation_stale');
            $link=$this->rows('SELECT metadata_json FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND subject_id=? AND event=? FOR UPDATE',[$renewal,$issuer,$id,'native_contract_renewal_linked'])[0]??null;
            if(!$link)throw new DomainException('company_operation_reference_denied');$data=json_decode($link['metadata_json'],true,32,JSON_THROW_ON_ERROR);$successor=IssuerPolicy::id($data['successor_id']??null);$before=$this->row($issuer,$successor);
            if((int)$before['revision']!==$renewalRevision)throw new DomainException('company_operation_stale');if($before['status']!=='draft'||$before['number']!==null)throw new DomainException('company_contract_immutable');
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_contracts SET status=?,revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',['cancelled',gmdate('Y-m-d H:i:s'),$issuer,$successor,$renewalRevision]);$after=$this->row($issuer,$successor);
            if($after['status']!=='cancelled'||(int)$after['revision']!==$renewalRevision+1||$after['snapshot_json']!==$before['snapshot_json']||$this->row($issuer,$id)!==$parent)throw new RuntimeException('issuer_operation_write_unconfirmed');
            $metadata=json_encode(['renewal_id'=>$renewal,'successor_id'=>$successor,'before'=>$before,'after'=>$after],JSON_THROW_ON_ERROR);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$actor,'native_contract_renewal_cancelled',$issuer,$id,gmdate('Y-m-d H:i:s'),$metadata]);$audit=(int)$this->db->insert_id();
            if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND subject_id=? AND metadata_json=?',[$audit,$issuer,$id,$metadata]))throw new RuntimeException('issuer_operation_write_unconfirmed');return$id;
        });
    }
    public function get($issuer,$actor,$id):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);return $this->transaction(function()use($issuer,$actor,$id):array{(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,'view');$row=$this->row($issuer,$id);$this->authorize($issuer,$actor,'view');return $row;});
    }
    /** Administrative companion is never part of get(), its API DTO, the public snapshot or PDF. */
    public function getForAdmin($issuer,$actor,$id):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);
        return$this->transaction(function()use($issuer,$actor,$id):array{
            (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,'view');$row=$this->row($issuer,$id);
            $native=new CompanyContractMetadata($this->db,$this->p);$metadata=$native->present($issuer,$actor,$native->readInOwned($row));
            $this->authorize($issuer,$actor,'view');if($this->row($issuer,$id)!==$row)throw new DomainException('company_operation_stale');return$row+['native_metadata'=>$metadata];
        });
    }

    /** External attachments are stored references, never fetched by the application or mail worker. */
    private function externalFileState(int $issuer,int $id):array
    {
        require_once __DIR__.'/NativeCompanyFileContent.php';$files=[];
        foreach($this->rows('SELECT event,metadata_json FROM '.$this->p.'issuers_audit WHERE issuer_id=? AND subject_id=? AND event IN (?,?) ORDER BY id',[$issuer,$id,'native_contract_external_added','native_contract_external_hidden'])as$event){
            $data=json_decode($event['metadata_json'],true,32,JSON_THROW_ON_ERROR);if(!is_array($data)||array_keys($data)!==($event['event']==='native_contract_external_added'?['files']:['file_key','before'])||($event['event']==='native_contract_external_added'&&(!is_array($data['files'])||!array_is_list($data['files'])||!$data['files']||count($data['files'])>20)))throw new RuntimeException('native_contract_external_corrupt');
            if($event['event']==='native_contract_external_added')foreach($data['files']??[]as$file){
                if(!is_array($file)||array_keys($file)!==['file_key','name','provider','url','active']||!is_string($file['file_key'])||!preg_match('/^[a-f0-9]{64}$/D',$file['file_key'])||isset($files[$file['file_key']])||$file['active']!==true||!is_string($file['name'])||self::text($file['name'],191)!==$file['name']||NativeCompanyFileContent::external($file['provider'],$file['url'])!==$file['url'])throw new RuntimeException('native_contract_external_corrupt');
                $files[$file['file_key']]=$file;
            }else{$key=$data['file_key']??null;if(!is_string($key)||!isset($files[$key])||!$files[$key]['active'])throw new RuntimeException('native_contract_external_corrupt');$files[$key]['active']=false;}
        }
        return$files;
    }
    private function externalAudit(int $issuer,int $actor,int $id,string $event,array $data):void
    {
        $json=json_encode($data,JSON_THROW_ON_ERROR);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(issuer_id,actor_id,subject_id,event,metadata_json,created_at) VALUES(?,?,?,?,?,?)',[$issuer,$actor,$id,$event,$json,gmdate('Y-m-d H:i:s')]);$audit=(int)$this->db->insert_id();
        if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=?',[$audit,$issuer,$actor,$id,$event,$json]))throw new RuntimeException('issuer_operation_write_unconfirmed');
    }
    public function externalFiles(int $issuer,int $actor,int $id,bool $activeOnly=true):array
    {
        return$this->transaction(function()use($issuer,$actor,$id,$activeOnly):array{(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,'view');$row=$this->row($issuer,$id);if($row['status']==='deleted')throw new DomainException('company_operation_access_denied');$files=$this->externalFileState($issuer,$id);$this->authorize($issuer,$actor,'view');return array_values($activeOnly?array_filter($files,static fn($file)=>$file['active']):$files);});
    }
    public function attachExternal(int $issuer,int $actor,int $id,int $revision,string $provider,array $input,string $key):int
    {
        require_once __DIR__.'/NativeCompanyFileContent.php';if(!array_is_list($input)||!$input||count($input)>20)throw new DomainException('company_operation_invalid_input');$files=[];
        foreach($input as$n=>$file){if(!is_array($file)||array_diff(array_keys($file),['name','link'])||!is_string($file['link']??null))throw new DomainException('company_operation_invalid_input');$name=self::text($file['name']??null,191);$url=NativeCompanyFileContent::external($provider,$file['link']);$files[]=['file_key'=>hash('sha256','contract-external|'.$issuer.'|'.$id.'|'.$key.'|'.$n),'name'=>$name,'provider'=>$provider,'url'=>$url,'active'=>true];}
        return$this->operation($issuer,$actor,'edit',$key,'contract_external_added',[$id,$revision,$files],function()use($issuer,$actor,$id,$revision,$files):int{
            $before=$this->row($issuer,$id);if((int)$before['revision']!==$revision)throw new DomainException('company_operation_stale');if(!in_array($before['status'],['draft','active'],true))throw new DomainException('company_contract_immutable');$old=$this->externalFileState($issuer,$id);if(count($old)+count($files)>100)throw new DomainException('company_operation_limit');
            $this->externalAudit($issuer,$actor,$id,'native_contract_external_added',['files'=>$files]);$expected=$old;foreach($files as$file)$expected[$file['file_key']]=$file;if($this->externalFileState($issuer,$id)!==$expected)throw new RuntimeException('issuer_operation_write_unconfirmed');
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_contracts SET revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[gmdate('Y-m-d H:i:s'),$issuer,$id,$revision]);$after=$this->row($issuer,$id);if((int)$after['revision']!==$revision+1||$after['snapshot_json']!==$before['snapshot_json'])throw new RuntimeException('issuer_operation_write_unconfirmed');return$id;
        });
    }
    public function hideExternal(int $issuer,int $actor,int $id,int $revision,string $file,string $key):int
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$file))throw new DomainException('company_operation_invalid_input');
        return$this->operation($issuer,$actor,'edit',$key,'contract_external_hidden',[$id,$revision,$file],function()use($issuer,$actor,$id,$revision,$file):int{
            $before=$this->row($issuer,$id);if((int)$before['revision']!==$revision)throw new DomainException('company_operation_stale');if($before['status']==='deleted')throw new DomainException('company_operation_access_denied');$old=$this->externalFileState($issuer,$id);if(!isset($old[$file])||!$old[$file]['active'])throw new DomainException('company_operation_access_denied');
            $this->externalAudit($issuer,$actor,$id,'native_contract_external_hidden',['file_key'=>$file,'before'=>$old[$file]]);$old[$file]['active']=false;if($this->externalFileState($issuer,$id)!==$old)throw new RuntimeException('issuer_operation_write_unconfirmed');
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_contracts SET revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[gmdate('Y-m-d H:i:s'),$issuer,$id,$revision]);$after=$this->row($issuer,$id);if((int)$after['revision']!==$revision+1||$after['snapshot_json']!==$before['snapshot_json'])throw new RuntimeException('issuer_operation_write_unconfirmed');return$id;
        });
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
