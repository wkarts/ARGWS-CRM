<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyAuxiliaryLegacy.php';

/** Proven-principal catalogue lineage. Never invoked for a named company's guessed raw ID. */
final class NativeCompanyCatalogLegacy
{
    private IssuerResourceAccess $access;
    private ?int $createdItem=null;
    public function __construct(private $db,private string $p,private int $issuer,private int $actor)
    {if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');$this->access=new IssuerResourceAccess($db,$p);$this->owner();}
    private function owner():void{if(!NativeCompanyAuxiliaryLegacy::eligible($this->db,$this->p,$this->issuer,$this->actor))throw new DomainException('company_catalog_denied');}
    private function can(string $feature,string $cap):void{$this->owner();if(!$this->access->can($this->issuer,$this->actor,$feature,$cap))throw new DomainException('company_catalog_denied');}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private static function dto(string $type,array $row):array{$row['revision']=NativeCompanyAuxiliaryLegacy::fingerprint($row);$row['source_kind']=$type;$row['source_id']=(int)$row['id'];$row['id']=(string)$row['id'];return$row;}
    public function taxes():array{$this->owner();return array_map(static fn($r)=>self::dto('tax',$r),$this->rows('SELECT * FROM '.$this->p.'taxes ORDER BY name,id'));}
    public function groups():array{$this->can('items','view');return array_map(static fn($r)=>self::dto('item_group',$r),$this->rows('SELECT * FROM '.$this->p.'items_groups ORDER BY name,id'));}
    public function item($id):array
    {
        $this->can('items','view');$id=IssuerPolicy::id($id);$raw=$this->rows('SELECT * FROM '.$this->p.'items WHERE id=?',[$id])[0]??null;if(!$raw)throw new DomainException('company_catalog_denied');
        $row=self::dto('item',$raw);$row['revision']=$this->itemRevision($raw,'view');$row['itemid']=$row['id'];
        foreach(['tax'=>['taxid','taxname','taxrate'],'tax2'=>['taxid_2','taxname_2','taxrate_2']]as$key=>$fields){
            $tax=empty($raw[$key])?null:($this->rows('SELECT id,name,taxrate FROM '.$this->p.'taxes WHERE id=?',[$raw[$key]])[0]??null);
            if(!empty($raw[$key])&&!$tax)throw new DomainException('company_catalog_denied');foreach($fields as$index=>$field)$row[$field]=$tax===null?null:$tax[['id','name','taxrate'][$index]];
        }
        $group=empty($raw['group_id'])?null:($this->rows('SELECT name FROM '.$this->p.'items_groups WHERE id=?',[$raw['group_id']])[0]??null);
        if(!empty($raw['group_id'])&&!$group)throw new DomainException('company_catalog_denied');$row['group_name']=$group['name']??null;$this->can('items','view');return$row;
    }
    public function search(string $query):array
    {
        $this->can('items','view');if(strlen($query)>191||preg_match('//u',$query)!==1)throw new DomainException('company_catalog_invalid');$q='%'.str_replace(['!','%','_'],['!!','!%','!_'],$query).'%';
        $rows=$this->rows("SELECT id,rate,description AS name,long_description AS subtext FROM ".$this->p."items WHERE description LIKE ? ESCAPE '!' ORDER BY description,id LIMIT 100",[$q]);foreach($rows as&$row){$row['id']=(string)$row['id'];$row['source_kind']='item';}unset($row);return$rows;
    }
    public function itemTable(int $start,int $length,string $query=''):array
    {
        $this->can('items','view');if($start<0||$start>100000||$length<1||$length>100||strlen($query)>191||preg_match('//u',$query)!==1)throw new DomainException('company_catalog_invalid');
        $where='1=1';$args=[];$total=(int)$this->rows('SELECT COUNT(*) n FROM '.$this->p.'items')[0]['n'];if($query!==''){$where.=" AND description LIKE ? ESCAPE '!'";$args[]='%'.str_replace(['!','%','_'],['!!','!%','!_'],$query).'%';}
        $filtered=(int)$this->rows('SELECT COUNT(*) n FROM '.$this->p.'items WHERE '.$where,$args)[0]['n'];$rows=[];
        foreach($this->rows('SELECT id FROM '.$this->p.'items WHERE '.$where.' ORDER BY description,id LIMIT '.$length.' OFFSET '.$start,$args)as$row)$rows[]=$this->item($row['id']);return['total'=>$total,'filtered'=>$filtered,'rows'=>$rows];
    }
    private function controller($ci):void
    {
        $this->owner();if(!is_object($ci)||!CompanyRequestScope::nativeFor($ci)||!CompanyRequestScope::sealedFor($ci)||CompanyRequestScope::context()->issuerId()!==$this->issuer||CompanyRequestScope::context()->staffId()!==$this->actor||IssuerPolicy::id(get_staff_user_id())!==$this->actor||$ci->db!==$this->db)throw new DomainException('company_catalog_denied');
    }
    private function audit(string $event,int $id):void
    {
        $meta=json_encode(['source'=>'native_legacy_catalogue','operation_nonce'=>bin2hex(random_bytes(16))],JSON_THROW_ON_ERROR);
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(issuer_id,actor_id,subject_id,event,metadata_json,created_at) VALUES(?,?,?,?,?,?)',[$this->issuer,$this->actor,$id,$event,$meta,gmdate('Y-m-d H:i:s')]);$audit=(int)$this->db->insert_id();
        if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND metadata_json=?',[$audit,$this->issuer,$this->actor,$meta]))throw new RuntimeException('issuer_catalog_write_unconfirmed');
    }
    public function createSimple($ci,string $kind,array $input):int
    {
        if(!in_array($kind,['tax','group'],true))throw new DomainException('company_catalog_invalid');$this->controller($ci);
        $tax=$kind==='tax';$feature=$tax?'taxes':'items';$this->can($feature,'create');$fields=NativeCompanyCatalog::simpleFields($input,$tax);$table=$tax?'taxes':'items_groups';
        if($tax)foreach(['before_tax_created','after_tax_created']as$tag)if(hooks()->has_filter($tag)!==false)throw new DomainException('native_legacy_hook_review_required');
        $tables=['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','taxes','items_groups','activity_log','issuers_audit'];
        return IssuerTransaction::run($this->db,$this->p,$tables,function()use($ci,$tax,$feature,$fields,$table):int{
            $this->access->lockFinancialIdentity($this->issuer,$this->actor);$this->can($feature,'create');$this->controller($ci);
            $max=(int)($this->rows('SELECT MAX(id) n FROM '.$this->p.$table)[0]['n']??0);
            $model=$tax?'taxes_model':'invoice_items_model';$class=$tax?'Taxes_model':'Invoice_items_model';$ci->load->model($model);if(get_class($ci->$model)!==$class)throw new DomainException('native_legacy_model_review_required');
            $result=$tax?$ci->$model->add($fields):$ci->$model->add_group($fields);
            $rows=$this->rows('SELECT * FROM '.$this->p.$table.' WHERE id>? ORDER BY id',[$max]);
            if($result===false||count($rows)!==1||NativeCompanyCatalog::simpleFields(array_intersect_key($rows[0],$fields),$tax)!==$fields)throw new RuntimeException('issuer_catalog_write_unconfirmed');
            $id=IssuerPolicy::id($rows[0]['id']);$this->audit('native_legacy_catalog_created',$id);$this->controller($ci);return$id;
        });
    }
    public function mutateSimple($ci,string $kind,$id,$revision,?array $input):void
    {
        if(!in_array($kind,['tax','group'],true))throw new DomainException('company_catalog_invalid');$this->controller($ci);$id=IssuerPolicy::id($id);
        if(!is_string($revision)||!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new DomainException('company_catalog_stale');
        $tax=$kind==='tax';$fields=$input===null?null:NativeCompanyCatalog::simpleFields($input,$tax);$table=$tax?'taxes':'items_groups';$feature=$tax?'taxes':'items';$cap=$input===null?'delete':'edit';
        if($tax)foreach(['before_update_tax','after_update_tax']as$tag)if(hooks()->has_filter($tag)!==false)throw new DomainException('native_legacy_hook_review_required');
        $tables=['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','taxes','items_groups','items','expenses','subscriptions','options','activity_log','issuers_audit'];
        IssuerTransaction::run($this->db,$this->p,$tables,function()use($ci,$id,$revision,$fields,$table,$feature,$cap,$tax):void{
            $this->access->lockFinancialIdentity($this->issuer,$this->actor);$this->can($feature,$cap);$this->controller($ci);
            $old=$this->rows('SELECT * FROM '.$this->p.$table.' WHERE id=? FOR UPDATE',[$id])[0]??null;if(!$old||!hash_equals(NativeCompanyAuxiliaryLegacy::fingerprint($old),$revision))throw new DomainException('company_catalog_stale');
            $model=$tax?'taxes_model':'invoice_items_model';$class=$tax?'Taxes_model':'Invoice_items_model';$ci->load->model($model);if(get_class($ci->$model)!==$class)throw new DomainException('native_legacy_model_review_required');
            if($fields===null){$result=$tax?$ci->$model->delete($id):$ci->$model->delete_group($id);if(is_array($result)&&!empty($result['referenced']))throw new DomainException('company_catalog_referenced');if($this->rows('SELECT id FROM '.$this->p.$table.' WHERE id=?',[$id]))throw new RuntimeException('issuer_catalog_write_unconfirmed');}
            else{$result=$tax?$ci->$model->edit($fields+['taxid'=>$id]):$ci->$model->edit_group($fields,$id);if(is_array($result)&&!empty($result['tax_is_using_expenses']))throw new DomainException('company_catalog_referenced');$saved=$this->rows('SELECT * FROM '.$this->p.$table.' WHERE id=?',[$id])[0]??null;if(!$saved||NativeCompanyCatalog::simpleFields(array_intersect_key($saved,$fields),$tax)!==$fields)throw new RuntimeException('issuer_catalog_write_unconfirmed');}
            $this->controller($ci);$this->audit('native_legacy_catalog_'.$cap,$id);
        });
    }

    private function itemRevision(array $row,string $cap):string
    {
        require_once __DIR__.'/NativeCompanyCustomFields.php';$values=(new NativeCompanyCustomFields($this->db,$this->p))->values($this->issuer,$this->actor,'items_pr',(string)$row['id'],$cap);ksort($values,SORT_STRING);
        return hash('sha256',NativeCompanyAuxiliaryLegacy::fingerprint($row).'|'.json_encode($values,JSON_THROW_ON_ERROR));
    }
    public function itemTables():array
    {
        require_once __DIR__.'/NativeCompanyCustomFields.php';return array_values(array_unique(array_merge((new NativeCompanyCustomFields($this->db,$this->p))->tableNames(),['items','taxes','items_groups','currencies','company_currency_units','customfields','customfieldsvalues','options','activity_log'])));
    }
    public function assertCreatedInOwned($db,string $prefix,int $issuer,int $actor,string $kind,int $id):void
    {
        if($db!==$this->db||$prefix!==$this->p||$issuer!==$this->issuer||$actor!==$this->actor||$kind!=='item'||$this->createdItem!==$id)throw new DomainException('native_custom_field_creation_denied');
        IssuerTransaction::assertOwned($db,$prefix,$this->itemTables());$this->can('items','create');if(!$this->rows('SELECT id FROM '.$this->p.'items WHERE id=?',[$id]))throw new DomainException('native_custom_field_creation_denied');
    }
    private static function nativeDecimal($value,int $scale):string
    {
        if(!is_string($value)&&!is_int($value))throw new DomainException('company_catalog_invalid');
        if(!preg_match('/^(0|[1-9][0-9]{0,'.max(0,14-$scale).'})(?:\.([0-9]{1,15}))?$/D',(string)$value,$parts))throw new DomainException('company_catalog_invalid');
        if($scale===15&&$parts[1]!=='0')throw new DomainException('company_catalog_invalid');$fraction=$parts[2]??'';if(strlen($fraction)>$scale&&trim(substr($fraction,$scale),'0')!=='')throw new DomainException('company_catalog_invalid');
        return$parts[1].($scale?'.'.str_pad(substr($fraction,0,$scale),$scale,'0'):'');
    }
    public function validateItemInput(array $input,string $cap='create'):array
    {
        $this->can('items',$cap);require_once __DIR__.'/NativeCompanyCatalogCurrencySchema.php';$precision=NativeCompanyCatalogCurrencySchema::installedPrecision($this->db,$this->p);
        $container=$input['custom_fields']??[];if(!is_array($container)||array_diff(array_keys($container),['items','items_pr'])||isset($container['items'],$container['items_pr']))throw new DomainException('company_catalog_invalid');$custom=$container['items_pr']??$container['items']??[];if(!is_array($custom))throw new DomainException('company_catalog_invalid');unset($input['custom_fields']);
        $allowed=['description','long_description','rate','unit','tax','tax2','group_id'];$currencies=[];
        foreach($input as$key=>$value){if(preg_match('/^rate_currency_([1-9][0-9]*)$/D',$key,$match)){$currencies[]=IssuerPolicy::id($match[1]);$input[$key]=$value===null||$value===''?null:self::nativeDecimal($value,$precision);}elseif(!in_array($key,$allowed,true))throw new DomainException('company_catalog_invalid');}
        NativeCompanyCatalogCurrencySchema::assertReady($this->db,$this->p,$currencies);
        foreach(['description'=>191,'long_description'=>64000,'unit'=>40]as$key=>$max){$value=$input[$key]??'';if(!is_string($value)||strlen($value)>$max||preg_match('//u',$value)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$value)||($key==='description'&&trim($value)===''))throw new DomainException('company_catalog_invalid');$input[$key]=trim($value);}
        $input['rate']=self::nativeDecimal($input['rate']??null,$precision);
        foreach(['tax'=>'tax','tax2'=>'tax','group_id'=>'item_group']as$key=>$type){$value=$input[$key]??null;if($value===null||$value===''||$value===0||$value==='0'){$input[$key]=$key==='group_id'?0:null;continue;}$ref=NativeCompanyResourceId::decode($type,$value);if($ref['source_kind']!==$type)throw new DomainException('native_legacy_catalog_reference_required');$table=$type==='tax'?'taxes':'items_groups';if(!$this->rows('SELECT id FROM '.$this->p.$table.' WHERE id=?',[$ref['source_id']]))throw new DomainException('company_catalog_denied');$input[$key]=$ref['source_id'];}
        (new NativeCompanyCustomFields($this->db,$this->p))->validateValues($this->issuer,$this->actor,'items_pr',$custom,$cap);$input['custom_fields']=['items_pr'=>$custom];return$input;
    }
    public function saveItem($ci,array $input,$id=null,$revision=null,?array $copySource=null):int
    {
        $this->controller($ci);$id=$id===null||$id===''?null:IssuerPolicy::id($id);$cap=$id===null?'create':'edit';$input=$this->validateItemInput($input,$cap);$custom=$input['custom_fields']['items_pr'];unset($input['custom_fields']);
        foreach(['before_item_created','item_created','before_update_item','item_updated','after_item_updated','item_coppied']as$tag)if(hooks()->has_filter($tag)!==false)throw new DomainException('native_legacy_hook_review_required');
        if($id!==null&&(!is_string($revision)||!preg_match('/^[a-f0-9]{64}$/D',$revision)))throw new DomainException('company_catalog_stale');
        return IssuerTransaction::run($this->db,$this->p,$this->itemTables(),function()use($ci,$input,$custom,$id,$revision,$cap,$copySource):int{
            try{
                $this->access->lockFinancialIdentity($this->issuer,$this->actor);$this->controller($ci);$this->can('items',$cap);
                if($copySource!==null){$source=$this->rows('SELECT * FROM '.$this->p.'items WHERE id=? FOR UPDATE',[$copySource[0]])[0]??null;if(!$source||!hash_equals($this->itemRevision($source,'view'),$copySource[1]))throw new DomainException('company_catalog_stale');}
                if($id!==null){$old=$this->rows('SELECT * FROM '.$this->p.'items WHERE id=? FOR UPDATE',[$id])[0]??null;if(!$old||!hash_equals($this->itemRevision($old,'edit'),$revision))throw new DomainException('company_catalog_stale');}
                $checked=$this->validateItemInput($input+['custom_fields'=>['items_pr'=>$custom]],$cap);unset($checked['custom_fields']);if($checked!==$input)throw new DomainException('company_catalog_stale');
                $ci->load->model('invoice_items_model');if(get_class($ci->invoice_items_model)!=='Invoice_items_model')throw new DomainException('native_legacy_model_review_required');
                if($id===null){$max=(int)($this->rows('SELECT MAX(id) n FROM '.$this->p.'items')[0]['n']??0);$id=IssuerPolicy::id($ci->invoice_items_model->add($input+['custom_fields'=>[]]));if($id<=$max)throw new RuntimeException('issuer_catalog_write_unconfirmed');}
                else{$ci->invoice_items_model->edit($input+['itemid'=>$id,'custom_fields'=>[]]);}
                $saved=$this->rows('SELECT * FROM '.$this->p.'items WHERE id=?',[$id])[0]??null;if(!$saved)throw new RuntimeException('issuer_catalog_write_unconfirmed');
                $precision=NativeCompanyCatalogCurrencySchema::installedPrecision($this->db,$this->p);foreach($input as$key=>$value){$actual=$saved[$key]??null;if($key==='rate'||str_starts_with($key,'rate_currency_')){if($actual!==null)$actual=self::nativeDecimal((string)$actual,$precision);}if($value===null?$actual!==null:(string)$actual!==(string)$value)throw new RuntimeException('issuer_catalog_write_unconfirmed');}
                $fields=new NativeCompanyCustomFields($this->db,$this->p);
                if($revision===null){$this->createdItem=$id;$fields->saveCreatedInOwnedTransaction($this->issuer,$this->actor,'items_pr',$id,$custom,$this);}else{$fields->saveInOwnedTransaction($this->issuer,$this->actor,'items_pr',(string)$id,$custom);}
                $this->controller($ci);$this->audit($copySource===null?'native_legacy_item_saved':'native_legacy_item_copied',$id);return$id;
            }finally{$this->createdItem=null;}
        });
    }
    public function deleteItemInOwned($ci,$id,$revision):void
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->itemTables());$this->access->lockFinancialIdentity($this->issuer,$this->actor);$this->controller($ci);$this->can('items','delete');$id=IssuerPolicy::id($id);
        if(hooks()->has_filter('item_deleted')!==false)throw new DomainException('native_legacy_hook_review_required');
        $row=$this->rows('SELECT * FROM '.$this->p.'items WHERE id=? FOR UPDATE',[$id])[0]??null;
        if(!$row||!is_string($revision)||!hash_equals($this->itemRevision($row,'view'),$revision))throw new DomainException('company_catalog_stale');
        $ci->load->model('invoice_items_model');if(get_class($ci->invoice_items_model)!=='Invoice_items_model')throw new DomainException('native_legacy_model_review_required');
        if($ci->invoice_items_model->delete($id)!==true||$this->rows('SELECT id FROM '.$this->p.'items WHERE id=?',[$id]))throw new RuntimeException('issuer_catalog_write_unconfirmed');
        IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'company_custom_field_values WHERE issuer_id=? AND source_kind=? AND source_id=?',[$this->issuer,'item',$id]);
        $this->audit('native_legacy_item_deleted',$id);$this->controller($ci);$this->can('items','delete');
    }
    public function copyItem($ci,$id,$revision):int
    {
        $item=$this->item($id);if(!is_string($revision)||!hash_equals($item['revision'],$revision))throw new DomainException('company_catalog_stale');
        $input=array_intersect_key($item,array_flip(['description','long_description','rate','unit','tax','tax2','group_id']));foreach($input as$key=>$value)if($key==='rate'&&$value!==null)$input[$key]=(string)$value;$input['description']=mb_substr($input['description'],0,180).' - Copy';foreach($item as$key=>$value)if(str_starts_with($key,'rate_currency_'))$input[$key]=$value===null?null:(string)$value;
        $input['custom_fields']['items_pr']=(new NativeCompanyCustomFields($this->db,$this->p))->storedInput($this->issuer,$this->actor,'items_pr',(string)$id);
        return$this->saveItem($ci,$input,null,null,[(int)$id,$revision]);
    }

}
