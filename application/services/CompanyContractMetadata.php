<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyContractMetadataSchema.php';
require_once __DIR__.'/CompanyInvoiceProjectInput.php';
require_once __DIR__.'/CompanyProjects.php';
require_once __DIR__.'/NativeCompanyCustomFields.php';

/** Administrative form fields live outside the snapshot returned by the API, portal and PDF. */
final class CompanyContractMetadata
{
    public function __construct(private $db,private string $p){CompanyContractMetadataSchema::definitions($p);}
    private static function text($value,int $max):string
    {
        if(!is_string($value)||strlen($value)>$max||preg_match('//u',$value)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$value))throw new DomainException('native_contract_metadata_invalid');
        return $value;
    }
    public static function hash(array $value):string{return hash('sha256',json_encode($value,JSON_THROW_ON_ERROR));}
    public static function normalize($input):array
    {
        if(!is_array($input)||array_diff(array_keys($input),['version','description','project','custom_fields','clear_value'])||!in_array($input['version']??null,[1,'1'],true))throw new DomainException('native_contract_metadata_invalid');
        $out=['version'=>1];
        // A form can explicitly clear an optional draft amount. Older callers that omit an amount
        // still preserve the existing value; this command flag never enters private/public snapshots.
        if(array_key_exists('clear_value',$input)){
            if(!in_array($input['clear_value'],[0,1,'0','1'],true))throw new DomainException('native_contract_metadata_invalid');
            $out['clear_value']=(int)$input['clear_value'];
        }
        if(array_key_exists('description',$input))$out['description']=self::text($input['description'],20000);
        if(array_key_exists('project',$input))$out['project']=CompanyInvoiceProjectInput::normalize($input['project']);
        if(array_key_exists('custom_fields',$input)){
            $frame=$input['custom_fields'];
            if(!is_array($frame)||array_diff(array_keys($frame),['definitions_hash','values'])||!is_string($frame['definitions_hash']??null)||!preg_match('/^[a-f0-9]{64}$/D',$frame['definitions_hash'])||!is_array($frame['values']??null)||count($frame['values'])>256)throw new DomainException('native_contract_metadata_invalid');
            foreach($frame['values'] as$id=>$value){NativeCompanyResourceId::decode('custom_field',$id);
                if(is_array($value)){if(!array_is_list($value)||count($value)>256)throw new DomainException('native_contract_metadata_invalid');foreach($value as$part)self::text($part,65535);}
                else self::text($value,65535);
            }
            ksort($frame['values'],SORT_STRING);$out['custom_fields']=['definitions_hash'=>$frame['definitions_hash'],'values'=>$frame['values']];
        }
        if(strlen(json_encode($out,JSON_THROW_ON_ERROR))>524288)throw new DomainException('native_contract_metadata_invalid');return$out;
    }
    public static function tables($db,string $p):array
    {
        if(!CompanyContractMetadataSchema::available($db,$p))return[];
        $tables=[CompanyContractMetadataSchema::TABLE,'company_contracts','company_contract_requests','issuers_audit','company_custom_fields','company_custom_field_values'];
        foreach(['customfields','customfieldsvalues']as$table)if($db->table_exists($p.$table))$tables[]=$table;
        if($db->table_exists($p.'company_projects'))$tables=array_merge($tables,CompanyProjects::ownedReferenceTables());
        return array_values(array_unique($tables));
    }
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function assertOwned():void
    {
        if(!CompanyContractMetadataSchema::available($this->db,$this->p))throw new DomainException('native_contract_metadata_unavailable');
        CompanyContractMetadataSchema::assertValid($this->db,$this->p);IssuerTransaction::assertOwned($this->db,$this->p,self::tables($this->db,$this->p));
    }
    private static function fields(array $definitions,array $values):array
    {
        $out=[];
        foreach($definitions as$definition){$id=(string)$definition['id'];if(!array_key_exists($id,$values))continue;
            $normalized=NativeCompanyCustomFields::definition(array_intersect_key($definition,array_flip(['fieldto','name','type','options','field_order','bs_column','default_value','required','display_inline','active','show_on_pdf','show_on_ticket_form','only_admin','show_on_table','show_on_client_portal','disalow_client_to_edit'])));
            $out[$id]=['id'=>$id,'field_source_kind'=>$definition['field_source_kind'],'source_field_id'=>(int)$definition['source_field_id'],'definition'=>$normalized,'value'=>$values[$id]];
        }
        return$out;
    }
    private function project(int $issuer,int $actor,int $client,?array $reference,bool $fresh=false):?array
    {
        if($reference===null)return null;
        $project=(new CompanyProjects($this->db,$this->p))->getInOwnedTransaction($issuer,$actor,$reference['source_id']);
        if((int)$project['client_id']!==$client)throw new DomainException('native_contract_project_denied');
        if(!$fresh&&(int)$project['revision']!==$reference['source_revision'])throw new DomainException('native_contract_project_stale');
        return ['source_kind'=>'company_project','source_id'=>(int)$project['id'],'source_revision'=>(int)$project['revision'],'name'=>self::text($project['name'],191)];
    }
    /** Capture only explicit fields; omitted input preserves existing private draft metadata. */
    public function capture(int $issuer,int $actor,int $client,array $input,string $cap,?array $previous=null,?int $contract=null):array
    {
        $this->assertOwned();$acl=new IssuerResourceAccess($this->db,$this->p);
        if(!in_array($cap,['create','edit'],true)||!$acl->can($issuer,$actor,'contracts','view')||!$acl->can($issuer,$actor,'contracts',$cap))throw new DomainException('company_operation_access_denied');
        $data=$previous??['version'=>1,'description'=>'','project'=>null,'custom_fields'=>[]];
        if($previous===null&&$contract!==null)$data['custom_fields']=$this->existingDraftFields($issuer,$actor,$contract);
        if(isset($input['description']))$data['description']=$input['description'];
        if(array_key_exists('project',$input))$data['project']=$this->project($issuer,$actor,$client,$input['project']);
        elseif($data['project']!==null){
            // An edit without Projects permission may preserve, but never retarget, a proven existing link.
            $project=$this->rows('SELECT client_id,issuer_id FROM '.$this->p.'company_projects WHERE issuer_id=? AND id=?',[$issuer,$data['project']['source_id']])[0]??null;
            if(!$project||(int)$project['client_id']!==$client)throw new DomainException('native_contract_project_denied');
        }
        if(isset($input['custom_fields'])||$previous===null){
            $fields=new NativeCompanyCustomFields($this->db,$this->p);$definitions=$fields->contractFormDefinitions($issuer,$actor,$contract,$cap);
            if(isset($input['custom_fields'])&&!hash_equals(self::hash($definitions),$input['custom_fields']['definitions_hash']))throw new DomainException('native_contract_fields_stale');
            $submitted=$input['custom_fields']['values']??[];
            foreach($definitions as$definition){$id=(string)$definition['id'];if(!array_key_exists($id,$submitted))$submitted[$id]=in_array($definition['type'],['multiselect','checkbox'],true)?[]:'';}
            $values=$fields->validateContractFormValues($issuer,$actor,$contract,$cap,$submitted);
            $captured=[];foreach($data['custom_fields']as$field)$captured[$field['id']]=$field;
            foreach(self::fields($definitions,$values)as$id=>$field){
                // A definition becoming public does not publish a formerly private value automatically.
                if(isset($captured[$id])&&(int)$captured[$id]['definition']['only_admin']===1)$field['definition']['only_admin']=1;
                $captured[$id]=$field;
            }
            ksort($captured,SORT_STRING);$data['custom_fields']=array_values($captured);
            if($fields->contractFormDefinitions($issuer,$actor,$contract,$cap)!==$definitions)throw new DomainException('native_contract_fields_stale');
        }
        if(strlen(json_encode($data,JSON_THROW_ON_ERROR))>1048576)throw new DomainException('native_contract_metadata_invalid');
        return$data;
    }
    /** Adopt only this owned draft's existing projections; hidden values survive the first full form edit. */
    private function existingDraftFields(int $issuer,int $actor,int $contract):array
    {
        $this->assertOwned();$row=$this->rows('SELECT status FROM '.$this->p.'company_contracts WHERE issuer_id=? AND id=?',[$issuer,$contract])[0]??null;
        if(!$row||$row['status']!=='draft'||!(new IssuerResourceAccess($this->db,$this->p))->can($issuer,$actor,'contracts','edit'))throw new DomainException('company_contract_immutable');
        $values=$this->rows('SELECT field_source_kind,field_id,value FROM '.$this->p."company_custom_field_values WHERE issuer_id=? AND source_kind='company_contract' AND source_id=? ORDER BY field_source_kind,field_id",[$issuer,$contract]);$fields=[];
        foreach($values as$value){
            if($value['field_source_kind']==='company_custom_field'){
                $definition=$this->rows('SELECT definition_json FROM '.$this->p."company_custom_fields WHERE issuer_id=? AND id=? AND fieldto='contracts'",[$issuer,$value['field_id']])[0]??null;
                if(!$definition)throw new DomainException('native_contract_metadata_unconfirmed');$definition=json_decode($definition['definition_json'],true,32,JSON_THROW_ON_ERROR);
            }elseif($value['field_source_kind']==='custom_field'&&(new IssuerResourceAccess($this->db,$this->p))->primaryId()===$issuer){
                $definition=$this->rows('SELECT * FROM '.$this->p."customfields WHERE id=? AND fieldto='contracts'",[$value['field_id']])[0]??null;
                if(!$definition)throw new DomainException('native_contract_metadata_unconfirmed');
                $definition=array_intersect_key($definition,array_flip(['fieldto','name','type','options','field_order','bs_column','default_value','required','display_inline','active','show_on_pdf','show_on_ticket_form','only_admin','show_on_table','show_on_client_portal','disalow_client_to_edit']));
            }else throw new DomainException('native_contract_metadata_unconfirmed');
            $definition=NativeCompanyCustomFields::definition($definition);$id=NativeCompanyResourceId::encode($value['field_source_kind'],$value['field_id']);
            $fields[]=['id'=>$id,'field_source_kind'=>$value['field_source_kind'],'source_field_id'=>(int)$value['field_id'],'definition'=>$definition,'value'=>self::text($value['value'],65535)];
        }
        return$fields;
    }
    public function readInOwned(array $contract):?array
    {
        if(!CompanyContractMetadataSchema::available($this->db,$this->p))return null;$this->assertOwned();
        $issuer=(int)$contract['issuer_id'];$id=(int)$contract['id'];
        $row=$this->rows('SELECT * FROM '.$this->p.CompanyContractMetadataSchema::TABLE.' WHERE issuer_id=? AND contract_id=? FOR UPDATE',[$issuer,$id])[0]??null;
        if($row===null)return null;
        try{
            $data=json_decode($row['data_json'],true,32,JSON_THROW_ON_ERROR);
            if(!is_array($data)||array_keys($data)!==['version','description','project','custom_fields']||$data['version']!==1||self::text($data['description'],20000)!==$data['description']||!is_array($data['custom_fields'])||!array_is_list($data['custom_fields'])||(int)$row['revision']<1||(int)$row['revision']>(int)$contract['revision']||!hash_equals($row['content_hash'],self::hash($data))||!in_array((int)$row['frozen'],[0,1],true)||($contract['number']!==null)!==((int)$row['frozen']===1))throw new RuntimeException();
            if($data['project']!==null){$ref=$data['project'];if(!is_array($ref)||array_keys($ref)!==['source_kind','source_id','source_revision','name']||CompanyInvoiceProjectInput::normalize(array_diff_key($ref,['name'=>true]))!==array_diff_key($ref,['name'=>true])||self::text($ref['name'],191)!==$ref['name'])throw new RuntimeException();}
            $seen=[];$expected=[];
            foreach($data['custom_fields']as$field){
                if(!is_array($field)||array_keys($field)!==['id','field_source_kind','source_field_id','definition','value']||!is_string($field['id'])||isset($seen[$field['id']])||NativeCompanyCustomFields::definition($field['definition'])!==$field['definition']||$field['definition']['fieldto']!=='contracts')throw new RuntimeException();
                $reference=NativeCompanyResourceId::decode('custom_field',$field['id']);if($reference['source_kind']!==$field['field_source_kind']||$reference['source_id']!==$field['source_field_id'])throw new RuntimeException();
                self::text($field['value'],65535);$seen[$field['id']]=true;$expected[]=['field_source_kind'=>$field['field_source_kind'],'field_id'=>$field['source_field_id'],'value'=>$field['value']];
            }
            usort($expected,static fn($a,$b)=>[$a['field_source_kind'],$a['field_id']]<=>[$b['field_source_kind'],$b['field_id']]);
            $actual=$this->rows('SELECT field_source_kind,field_id,value FROM '.$this->p."company_custom_field_values WHERE issuer_id=? AND source_kind='company_contract' AND source_id=? ORDER BY field_source_kind,field_id",[$issuer,$id]);
            foreach($actual as&$field)$field['field_id']=(int)$field['field_id'];unset($field);if($actual!==$expected)throw new RuntimeException();
            $event=(int)$row['frozen']===1?'native_contract_metadata_frozen':'native_contract_metadata_saved';
            $audit=$this->rows('SELECT metadata_json FROM '.$this->p.'issuers_audit WHERE issuer_id=? AND subject_id=? AND event=? ORDER BY id DESC LIMIT 1',[$issuer,$id,$event])[0]??null;
            if(!$audit||json_decode($audit['metadata_json'],true,32,JSON_THROW_ON_ERROR)!==['version'=>1,'revision'=>(int)$row['revision'],'content_hash'=>$row['content_hash']])throw new RuntimeException();
        }catch(Throwable $error){throw new DomainException('native_contract_metadata_unconfirmed');}
        return$data;
    }
    private function audit(int $issuer,int $actor,int $id,int $revision,string $hash,bool $frozen):void
    {
        $event=$frozen?'native_contract_metadata_frozen':'native_contract_metadata_saved';$json=json_encode(['version'=>1,'revision'=>$revision,'content_hash'=>$hash],JSON_THROW_ON_ERROR);
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(issuer_id,actor_id,subject_id,event,metadata_json,created_at) VALUES(?,?,?,?,?,?)',[$issuer,$actor,$id,$event,$json,gmdate('Y-m-d H:i:s')]);$audit=(int)$this->db->insert_id();
        if($audit<1||!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=?',[$audit,$issuer,$actor,$id,$event,$json]))throw new RuntimeException('issuer_operation_write_unconfirmed');
    }
    /** Called exclusively while the final contract writer attests this exact draft revision. */
    public function save(int $issuer,int $actor,int $id,int $revision,array $data,CompanyContracts $writer):void
    {
        $this->assertOwned();$frame=$writer->assertNativeMetadataWriteInOwned($this->db,$this->p,$issuer,$actor,$id,$revision);
        $fields=new NativeCompanyCustomFields($this->db,$this->p);$fields->saveContractMetadataInOwned($issuer,$actor,$id,$revision,$data['custom_fields'],$writer);
        $json=json_encode($data,JSON_THROW_ON_ERROR);$hash=self::hash($data);
        $old=$this->rows('SELECT revision,frozen FROM '.$this->p.CompanyContractMetadataSchema::TABLE.' WHERE issuer_id=? AND contract_id=? FOR UPDATE',[$issuer,$id])[0]??null;
        if($old){if((int)$old['frozen']!==0||(int)$old['revision']>=$revision)throw new DomainException('company_contract_immutable');IssuerStorage::query($this->db,'UPDATE '.$this->p.CompanyContractMetadataSchema::TABLE.' SET revision=?,data_json=?,content_hash=?,updated_at=? WHERE issuer_id=? AND contract_id=? AND revision=?',[$revision,$json,$hash,gmdate('Y-m-d H:i:s'),$issuer,$id,$old['revision']]);}
        else IssuerStorage::query($this->db,'INSERT INTO '.$this->p.CompanyContractMetadataSchema::TABLE.'(issuer_id,contract_id,revision,data_json,content_hash,frozen,updated_at) VALUES(?,?,?,?,?,0,?)',[$issuer,$id,$revision,$json,$hash,gmdate('Y-m-d H:i:s')]);
        $this->audit($issuer,$actor,$id,$revision,$hash,false);
        $contract=$writer->assertNativeMetadataWriteInOwned($this->db,$this->p,$issuer,$actor,$id,$revision)['contract'];
        if($this->readInOwned($contract)!==$data)throw new RuntimeException('issuer_operation_write_unconfirmed');
    }
    public function copyData(int $issuer,int $actor,array $source,int $client):?array
    {
        $data=$this->readInOwned($source);if($data===null)return null;
        if($data['project']!==null)$data['project']=$this->project($issuer,$actor,$client,array_diff_key($data['project'],['name'=>true]),true);
        return$data;
    }
    /** The public snapshot is unchanged; only the private companion becomes frozen. */
    public function freeze(int $issuer,int $actor,array $before,array $after,CompanyContracts $writer):void
    {
        $writer->assertNativeMetadataWriteInOwned($this->db,$this->p,$issuer,$actor,(int)$after['id'],(int)$after['revision'],'freeze');
        $data=$this->readInOwned($before);if($data===null)return;
        if($before['status']!=='draft'||$after['status']!=='active'||(int)$after['revision']!==(int)$before['revision']+1)throw new DomainException('company_contract_immutable');
        if($data['project']!==null)$this->project($issuer,$actor,(int)$before['client_id'],array_diff_key($data['project'],['name'=>true]));
        $hash=self::hash($data);
        IssuerStorage::query($this->db,'UPDATE '.$this->p.CompanyContractMetadataSchema::TABLE.' SET frozen=1,revision=?,updated_at=? WHERE issuer_id=? AND contract_id=? AND frozen=0',[(int)$after['revision'],gmdate('Y-m-d H:i:s'),$issuer,$before['id']]);
        $this->audit($issuer,$actor,(int)$before['id'],(int)$after['revision'],$hash,true);
        if($this->readInOwned($after)!==$data)throw new RuntimeException('issuer_operation_write_unconfirmed');
        $writer->assertNativeMetadataWriteInOwned($this->db,$this->p,$issuer,$actor,(int)$after['id'],(int)$after['revision'],'freeze');
    }
    public function present(int $issuer,int $actor,?array $data):?array
    {
        if($data===null)return null;$acl=new IssuerResourceAccess($this->db,$this->p);
        if(!$acl->can($issuer,$actor,'contracts','view'))throw new DomainException('company_operation_access_denied');
        $definitions=(new NativeCompanyCustomFields($this->db,$this->p))->definitions($issuer,$actor,'contracts');$allowed=array_fill_keys(array_map(static fn($d)=>(string)$d['id'],$definitions),true);$admin=$acl->administrative($issuer,$actor);
        $data['custom_fields']=array_values(array_filter($data['custom_fields'],static fn($field)=>isset($allowed[$field['id']])&&($admin||(int)$field['definition']['only_admin']===0)));
        if(!$acl->can($issuer,$actor,'projects','view'))$data['project']=null;
        return$data;
    }
    /** Generic field readers must also respect privacy captured when the value was stored. */
    public function visibleDefinitions(int $issuer,int $actor,int $contract,array $definitions):array
    {
        if(!CompanyContractMetadataSchema::available($this->db,$this->p)||(new IssuerResourceAccess($this->db,$this->p))->administrative($issuer,$actor))return$definitions;
        $row=$this->rows('SELECT data_json,content_hash,revision,frozen FROM '.$this->p.CompanyContractMetadataSchema::TABLE.' WHERE issuer_id=? AND contract_id=?',[$issuer,$contract])[0]??null;
        if($row===null)return$definitions;$data=json_decode($row['data_json'],true,32,JSON_THROW_ON_ERROR);
        if(!is_array($data)||!hash_equals($row['content_hash'],self::hash($data))||!is_array($data['custom_fields']??null))throw new DomainException('native_contract_metadata_unconfirmed');
        $event=(int)$row['frozen']===1?'native_contract_metadata_frozen':'native_contract_metadata_saved';
        $audit=$this->rows('SELECT metadata_json FROM '.$this->p.'issuers_audit WHERE issuer_id=? AND subject_id=? AND event=? ORDER BY id DESC LIMIT 1',[$issuer,$contract,$event])[0]??null;
        if(!$audit||json_decode($audit['metadata_json'],true,32,JSON_THROW_ON_ERROR)!==['version'=>1,'revision'=>(int)$row['revision'],'content_hash'=>$row['content_hash']])throw new DomainException('native_contract_metadata_unconfirmed');
        $private=[];foreach($data['custom_fields']as$field)if((int)($field['definition']['only_admin']??1)===1)$private[$field['id']]=true;
        return array_values(array_filter($definitions,static fn($field)=>!isset($private[(string)$field['id']])));
    }
}
