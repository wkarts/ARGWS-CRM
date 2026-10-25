<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyAuxiliarySchema.php';
require_once __DIR__.'/NativeCompanyCurrencyReference.php';
require_once __DIR__.'/NativeCompanyResourceId.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Native item/tax models backed by explicitly owned catalogues; no global adoption. */
final class NativeCompanyCatalog
{
    private function assertInserted():void
    {
        $connection=$this->db->conn_id??$this->db->pdo??null;
        $sqlite=$connection instanceof PDO&&$connection->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        $row=IssuerStorage::query($this->db,$sqlite?'SELECT changes() AS affected':'SELECT ROW_COUNT() AS affected')->row_array();
        if((string)($row['affected']??'')!=='1')throw new RuntimeException('issuer_native_insert_unconfirmed');
    }

    private IssuerResourceAccess $access;
    private ?int $createdItem=null;
    public function __construct(private $db,private string $p,private int $issuer,private int $actor)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        IssuerPolicy::id($issuer);IssuerPolicy::id($actor);$this->access=new IssuerResourceAccess($db,$p);$this->member();
    }
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function member():void{if(!$this->access->member($this->issuer,$this->actor))throw new DomainException('company_catalog_denied');}
    private function can(string $feature,string $cap):void{if(!$this->access->can($this->issuer,$this->actor,$feature,$cap))throw new DomainException('company_catalog_denied');}
    public function allowed(string $feature,string $cap):bool{return $this->access->can($this->issuer,$this->actor,$feature,$cap);}
    private function tx(callable $operation)
    {
        if($this->access->primaryId()===$this->issuer)throw new DomainException('company_catalog_primary_native_required');
        NativeCompanyAuxiliarySchema::assertValid($this->db,$this->p);
        require_once __DIR__.'/NativeCompanyCustomFields.php';
        $tables=array_values(array_unique(array_merge(['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','issuers_audit','company_items','company_taxes','company_item_groups','currencies','company_currency_units'],(new NativeCompanyCustomFields($this->db,$this->p))->tableNames())));
        return IssuerTransaction::run($this->db,$this->p,$tables,function()use($operation){
            $this->access->lockFinancialIdentity($this->issuer,$this->actor);$this->member();try{$result=$operation();$this->member();return$result;}finally{$this->createdItem=null;}
        });
    }
    public function assertCreatedInOwned($db,string $prefix,int $issuer,int $actor,string $kind,int $id):void
    {
        if($db!==$this->db||$prefix!==$this->p||$issuer!==$this->issuer||$actor!==$this->actor||$kind!=='company_item'||$this->createdItem!==$id)throw new DomainException('native_custom_field_creation_denied');
        IssuerTransaction::assertOwned($db,$prefix,['company_items','issuers_audit','issuers_registry']);$this->can('items','create');
        if(!$this->rows('SELECT id FROM '.$this->p.'company_items WHERE issuer_id=? AND id=? AND revision=1',[$issuer,$id]))throw new DomainException('native_custom_field_creation_denied');
    }
    private static function text($value,int $max,bool $empty=false):string
    {
        if(!is_string($value)||strlen($value)>$max||preg_match('//u',$value)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$value)||(!$empty&&trim($value)===''))throw new DomainException('company_catalog_invalid');
        return trim($value);
    }
    private static function decimal($value,int $digits=11):string
    {
        if(!is_string($value)&&!is_int($value))throw new DomainException('company_catalog_invalid');
        if(!preg_match('/^(0|[1-9][0-9]{0,'.($digits-1).'})(?:\.([0-9]{1,4}))?$/D',(string)$value,$m))throw new DomainException('company_catalog_invalid');
        return $m[1].'.'.str_pad($m[2]??'',4,'0');
    }
    private static function revision($value):int
    {
        if((!is_int($value)&&!is_string($value))||!preg_match('/^[1-9][0-9]{0,9}$/D',(string)$value)||(int)$value>4294967294)throw new DomainException('company_catalog_stale');return(int)$value;
    }
    private function audit(string $event,int $id):void
    {
        $meta=json_encode(['version'=>1,'source'=>'native_catalog','operation_nonce'=>bin2hex(random_bytes(16))],JSON_THROW_ON_ERROR);
        $date=gmdate('Y-m-d H:i:s');IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(issuer_id,actor_id,subject_id,event,metadata_json,created_at) VALUES(?,?,?,?,?,?)',[$this->issuer,$this->actor,$id,$event,$meta,$date]);$audit=(int)$this->db->insert_id();
        if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=?',[$audit,$this->issuer,$this->actor,$id,$event,$meta]))throw new RuntimeException('issuer_catalog_write_unconfirmed');
    }
    /** Currency definitions are public reference data, not accounts, balances or rates. */
    private function currencyReference():NativeCompanyCurrencyReference{return new NativeCompanyCurrencyReference($this->db,$this->p,$this->issuer,$this->actor);}
    public function currencies():array{return$this->currencyReference()->currencies();}
    public function currency($id):array{return$this->currencyReference()->currency($id);}
    public function taxes():array{$this->member();return$this->rows('SELECT id,name,taxrate,revision FROM '.$this->p.'company_taxes WHERE issuer_id=? ORDER BY name,id',[$this->issuer]);}
    public function groups():array{$this->can('items','view');return$this->rows('SELECT id,name,revision FROM '.$this->p.'company_item_groups WHERE issuer_id=? ORDER BY name,id',[$this->issuer]);}
    private function reference(string $table,$id):?int
    {
        if($id===null||$id===''||$id==='0'||$id===0)return null;
        $id=IssuerPolicy::id($id);if(!$this->rows('SELECT id FROM '.$this->p.$table.' WHERE issuer_id=? AND id=?',[$this->issuer,$id]))throw new DomainException('company_catalog_denied');return$id;
    }
    public function item($id):array
    {
        $this->can('items','view');$id=IssuerPolicy::id($id);
        $row=$this->rows('SELECT i.*,i.id AS itemid,t.name AS taxname,t.taxrate,t.id AS taxid,u.name AS taxname_2,u.taxrate AS taxrate_2,u.id AS taxid_2,g.name AS group_name FROM '.$this->p.'company_items i LEFT JOIN '.$this->p.'company_taxes t ON t.issuer_id=i.issuer_id AND t.id=i.tax LEFT JOIN '.$this->p.'company_taxes u ON u.issuer_id=i.issuer_id AND u.id=i.tax2 LEFT JOIN '.$this->p.'company_item_groups g ON g.issuer_id=i.issuer_id AND g.id=i.group_id WHERE i.issuer_id=? AND i.id=?',[$this->issuer,$id])[0]??null;
        if(!$row)throw new DomainException('company_catalog_denied');
        $rates=json_decode($row['currency_rates_json'],true,16,JSON_THROW_ON_ERROR);if(!is_array($rates))throw new RuntimeException('issuer_catalog_data_invalid');
        foreach($rates as$currency=>$rate){$live=$this->currency($currency);require_once __DIR__.'/CompanyCurrencyUnits.php';if((new CompanyCurrencyUnits($this->db,$this->p))->resolveRead($currency)['iso_code']!==$live['name'])throw new DomainException('company_currency_catalog_changed');$row['rate_currency_'.$currency]=self::decimal($rate);}
        unset($row['currency_rates_json'],$row['issuer_id']);$this->can('items','view');return$row;
    }
    public function search(string $query):array
    {
        $this->can('items','view');$query=self::text($query,191,true);$q='%'.str_replace(['!','%','_'],['!!','!%','!_'],$query).'%';
        $rows=$this->rows("SELECT id,rate,description AS name,long_description AS subtext FROM ".$this->p."company_items WHERE issuer_id=? AND description LIKE ? ESCAPE '!' ORDER BY description,id LIMIT 100",[$this->issuer,$q]);$this->can('items','view');return$rows;
    }
    public function itemTable(int $start,int $length,string $query=''):array
    {
        $this->can('items','view');if($start<0||$start>100000||$length<1||$length>100)throw new DomainException('company_catalog_invalid');
        $where='issuer_id=?';$args=[$this->issuer];$total=(int)$this->rows('SELECT COUNT(*) AS n FROM '.$this->p.'company_items WHERE '.$where,$args)[0]['n'];
        if($query!==''){$query=self::text($query,191);$where.=" AND description LIKE ? ESCAPE '!'";$args[]='%'.str_replace(['!','%','_'],['!!','!%','!_'],$query).'%';}
        $filtered=(int)$this->rows('SELECT COUNT(*) AS n FROM '.$this->p.'company_items WHERE '.$where,$args)[0]['n'];$rows=[];
        foreach($this->rows('SELECT id FROM '.$this->p.'company_items WHERE '.$where.' ORDER BY description,id LIMIT '.$length.' OFFSET '.$start,$args)as$row)$rows[]=$this->item($row['id']);
        return['total'=>$total,'filtered'=>$filtered,'rows'=>$rows];
    }
    public function baseCurrency():array{return$this->currencyReference()->baseCurrency();}
    public function validateItemInput(array $input):array
    {
        $this->can('items','create');$fields=$input['custom_fields']??[];if(!is_array($fields)||array_diff(array_keys($fields),['items','items_pr'])||isset($fields['items'],$fields['items_pr']))throw new DomainException('company_catalog_invalid');
        $custom=$fields['items']??$fields['items_pr']??[];if(!is_array($custom))throw new DomainException('company_catalog_invalid');unset($input['custom_fields']);
        foreach($input as$key=>$value){if(preg_match('/^rate_currency_([1-9][0-9]*)$/D',$key,$m)){$this->currency($m[1]);if($value!==''&&$value!==null)$input[$key]=self::decimal($value);}elseif(!in_array($key,['description','long_description','rate','unit','tax','tax2','group_id'],true))throw new DomainException('company_catalog_invalid');}
        $input['description']=self::text($input['description']??null,191);$input['long_description']=self::text($input['long_description']??'',64000,true);$input['unit']=self::text($input['unit']??'',40,true);$input['rate']=self::decimal($input['rate']??null);
        foreach(['tax'=>'company_taxes','tax2'=>'company_taxes','group_id'=>'company_item_groups']as$key=>$table)$input[$key]=$this->reference($table,$input[$key]??null);
        require_once __DIR__.'/NativeCompanyCustomFields.php';(new NativeCompanyCustomFields($this->db,$this->p))->validateValues($this->issuer,$this->actor,'items_pr',$custom);$input['custom_fields']['items_pr']=$custom;return$input;
    }
    public function saveItem(array $input,$id=null,$revision=null,?array $copySource=null):int
    {
        $fields=$input['custom_fields']??[];
        if(!is_array($fields)||array_diff(array_keys($fields),['items','items_pr'])||isset($fields['items'],$fields['items_pr']))throw new DomainException('company_catalog_invalid');
        $custom=$fields['items']??$fields['items_pr']??[];
        if(!is_array($custom))throw new DomainException('company_catalog_invalid');
        unset($input['custom_fields']);
        $allowed=['description','long_description','rate','unit','tax','tax2','group_id'];$rates=[];
        foreach($input as$key=>$value){if(preg_match('/^rate_currency_([1-9][0-9]*)$/D',$key,$m)){$currency=$this->currency($m[1]);if($value!==''&&$value!==null)$rates[(string)$currency['id']]=self::decimal($value);}elseif(!in_array($key,$allowed,true))throw new DomainException('company_catalog_invalid');}
        $description=self::text($input['description']??null,191);$long=self::text($input['long_description']??'',64000,true);$unit=self::text($input['unit']??'',40,true);$rate=self::decimal($input['rate']??null);ksort($rates,SORT_NUMERIC);$json=json_encode($rates,JSON_THROW_ON_ERROR);
        $id=$id===null||$id===''?null:IssuerPolicy::id($id);$revision=$id===null?null:self::revision($revision);
        return$this->tx(function()use($copySource,$custom,$input,$description,$long,$unit,$rate,$json,$rates,$id,$revision):int{
            if($copySource!==null && (count($copySource)!==2 || (int)$this->item($copySource[0])['revision']!==$copySource[1]))throw new DomainException('company_catalog_stale');
            $this->can('items',$id===null?'create':'edit');require_once __DIR__.'/CompanyCurrencyUnits.php';foreach(array_keys($rates)as$currencyId)(new CompanyCurrencyUnits($this->db,$this->p))->assertWrite($currencyId);
            $tax=$this->reference('company_taxes',$input['tax']??null);$tax2=$this->reference('company_taxes',$input['tax2']??null);$group=$this->reference('company_item_groups',$input['group_id']??null);
            if($id===null){IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_items(issuer_id,description,long_description,rate,currency_rates_json,unit,tax,tax2,group_id,revision) VALUES(?,?,?,?,?,?,?,?,?,1)',[$this->issuer,$description,$long,$rate,$json,$unit,$tax,$tax2,$group]);$this->assertInserted();$id=(int)$this->db->insert_id();}
            else{$owned=$this->item($id);if((int)$owned['revision']!==$revision)throw new DomainException('company_catalog_stale');IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_items SET description=?,long_description=?,rate=?,currency_rates_json=?,unit=?,tax=?,tax2=?,group_id=?,revision=revision+1 WHERE issuer_id=? AND id=? AND revision=?',[$description,$long,$rate,$json,$unit,$tax,$tax2,$group,$this->issuer,$id,$revision]);}
            $row=$this->item($id);if($row['description']!==$description||self::decimal($row['rate'])!==$rate||(int)$row['revision']!==($revision===null?1:$revision+1))throw new RuntimeException('issuer_catalog_write_unconfirmed');if($revision===null){$this->createdItem=$id;(new NativeCompanyCustomFields($this->db,$this->p))->saveCreatedInOwnedTransaction($this->issuer,$this->actor,'items_pr',$id,$custom,$this);}else{(new NativeCompanyCustomFields($this->db,$this->p))->saveInOwnedTransaction($this->issuer,$this->actor,'items_pr',NativeCompanyResourceId::encode('company_item',$id),$custom);}$this->audit('native_item_saved',$id);return$id;
        });
    }
    public function saveGroup(array $input,$id=null,$revision=null):int{return$this->saveSimple('company_item_groups','items',$input,$id,$revision);}
    public function saveTax(array $input,$id=null,$revision=null):int{return$this->saveSimple('company_taxes','taxes',$input,$id,$revision);}
    public static function simpleFields(array $input,bool $tax):array
    {
        if(array_diff(array_keys($input),$tax?['name','taxrate']:['name']))throw new DomainException('company_catalog_invalid');
        $name=self::text($input['name']??null,191);if($tax&&str_contains($name,'|'))throw new DomainException('company_catalog_invalid');
        return$tax?['name'=>$name,'taxrate'=>self::decimal($input['taxrate']??null,6)]:['name'=>$name];
    }
    private function saveSimple(string $table,string $feature,array $input,$id,$revision):int
    {
        $tax=$table==='company_taxes';$fields=self::simpleFields($input,$tax);$name=$fields['name'];$rate=$fields['taxrate']??null;
        $id=$id===null||$id===''?null:IssuerPolicy::id($id);$revision=$id===null?null:self::revision($revision);
        return$this->tx(function()use($table,$feature,$tax,$name,$rate,$id,$revision):int{
            $this->can($feature,$id===null?'create':'edit');
            if($id===null){IssuerStorage::query($this->db,'INSERT INTO '.$this->p.$table.'(issuer_id,name,'.($tax?'taxrate,':'').'revision) VALUES(?,?,'.($tax?'?,':'').'1)',array_merge([$this->issuer,$name],$tax?[$rate]:[]));$this->assertInserted();$id=(int)$this->db->insert_id();}
            else{if(!$this->rows('SELECT id FROM '.$this->p.$table.' WHERE issuer_id=? AND id=? AND revision=?',[$this->issuer,$id,$revision]))throw new DomainException('company_catalog_stale');IssuerStorage::query($this->db,'UPDATE '.$this->p.$table.' SET name=?,'.($tax?'taxrate=?,':'').'revision=revision+1 WHERE issuer_id=? AND id=? AND revision=?',array_merge([$name],$tax?[$rate]:[],[$this->issuer,$id,$revision]));}
            $row=$this->rows('SELECT * FROM '.$this->p.$table.' WHERE issuer_id=? AND id=?',[$this->issuer,$id])[0]??[];if(($row['name']??null)!==$name||(int)($row['revision']??0)!==($revision===null?1:$revision+1)||($tax&&self::decimal($row['taxrate'],6)!==$rate))throw new RuntimeException('issuer_catalog_write_unconfirmed');$this->audit($tax?'native_tax_saved':'native_item_group_saved',$id);return$id;
        });
    }
    public function delete(string $kind,$id,$revision):void
    {
        [$table,$feature]=match($kind){'item'=>['company_items','items'],'group'=>['company_item_groups','items'],'tax'=>['company_taxes','taxes'],default=>throw new DomainException('company_catalog_invalid')};$id=IssuerPolicy::id($id);$revision=self::revision($revision);
        $this->tx(function()use($table,$feature,$id,$revision):void{$this->can($feature,'delete');if(!$this->rows('SELECT id FROM '.$this->p.$table.' WHERE issuer_id=? AND id=? AND revision=?',[$this->issuer,$id,$revision]))throw new DomainException('company_catalog_stale');IssuerStorage::query($this->db,'DELETE FROM '.$this->p.$table.' WHERE issuer_id=? AND id=? AND revision=?',[$this->issuer,$id,$revision]);if($this->rows('SELECT id FROM '.$this->p.$table.' WHERE issuer_id=? AND id=?',[$this->issuer,$id]))throw new RuntimeException('issuer_catalog_write_unconfirmed');$this->audit('native_catalog_deleted',$id);});
    }
    public function deleteItems(array $revisions):void
    {
        if(count($revisions)>200||!$revisions)throw new DomainException('company_catalog_invalid');$ids=[];
        foreach($revisions as$id=>$revision)$ids[IssuerPolicy::id($id)]=self::revision($revision);
        $this->tx(function()use($ids):void{
            $this->can('items','delete');foreach($ids as$id=>$revision)if((int)$this->item($id)['revision']!==$revision)throw new DomainException('company_catalog_stale');
            foreach($ids as$id=>$revision){IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'company_items WHERE issuer_id=? AND id=? AND revision=?',[$this->issuer,$id,$revision]);if($this->rows('SELECT id FROM '.$this->p.'company_items WHERE issuer_id=? AND id=?',[$this->issuer,$id]))throw new RuntimeException('issuer_catalog_write_unconfirmed');$this->audit('native_catalog_deleted',$id);}
        });
    }
    /** Shared explicit boundary used by mixed historical/owned native bulk deletion. */
    public function deleteItemInOwned($id,$revision):void
    {
        if($this->access->primaryId()===$this->issuer)throw new DomainException('company_catalog_primary_native_required');
        IssuerTransaction::assertOwned($this->db,$this->p,['company_items','company_custom_field_values','issuers_audit','issuers_registry','issuers_resource_permissions']);
        $this->access->lockFinancialIdentity($this->issuer,$this->actor);$this->can('items','delete');$id=IssuerPolicy::id($id);$revision=self::revision($revision);
        if(!$this->rows('SELECT id FROM '.$this->p.'company_items WHERE issuer_id=? AND id=? AND revision=? FOR UPDATE',[$this->issuer,$id,$revision]))throw new DomainException('company_catalog_stale');
        IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'company_custom_field_values WHERE issuer_id=? AND source_kind=? AND source_id=?',[$this->issuer,'company_item',$id]);
        IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'company_items WHERE issuer_id=? AND id=? AND revision=?',[$this->issuer,$id,$revision]);
        if($this->rows('SELECT id FROM '.$this->p.'company_items WHERE issuer_id=? AND id=?',[$this->issuer,$id]))throw new RuntimeException('issuer_catalog_write_unconfirmed');
        $this->audit('native_catalog_deleted',$id);$this->can('items','delete');
    }
    public function copyItem($id,$revision):int
    {
        $item=$this->item($id);if((int)$item['revision']!==self::revision($revision))throw new DomainException('company_catalog_stale');
        $input=['description'=>mb_substr($item['description'],0,180).' - Copy','long_description'=>$item['long_description'],'rate'=>$item['rate'],'unit'=>$item['unit'],'tax'=>$item['tax'],'tax2'=>$item['tax2'],'group_id'=>$item['group_id']];
        foreach($item as$key=>$value)if(str_starts_with($key,'rate_currency_'))$input[$key]=$value;
        require_once __DIR__.'/NativeCompanyCustomFields.php';$input['custom_fields']['items_pr']=(new NativeCompanyCustomFields($this->db,$this->p))->storedInput($this->issuer,$this->actor,'items_pr',NativeCompanyResourceId::encode('company_item',$id));
        return $this->saveItem($input,null,null,[(int)$id,(int)$revision]);
    }
    public function taxDropdown(string $name,$selected,array $ownedHistoricalTaxes=[]):string
    {
        if(!preg_match('/^[A-Za-z][A-Za-z0-9_\[\]-]{0,190}$/D',$name))throw new DomainException('company_catalog_invalid');
        $selected=$selected===null?[]:(is_array($selected)?$selected:[$selected]);foreach($selected as&$value){if(!is_string($value))throw new DomainException('company_catalog_invalid');if($value==='')continue;$parts=explode('|',$value);if(count($parts)!==2)throw new DomainException('company_catalog_invalid');$value=self::text($parts[0],191).'|'.self::decimal($parts[1],6);}unset($value);
        $escape=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $html='<select class="selectpicker display-block tax" data-width="100%" name="'.$escape($name).'" multiple data-none-selected-text="'.$escape(_l('no_tax')).'">';
        $taxes=$this->taxes();$seen=[];foreach($taxes as&$tax){$tax['name']=self::text($tax['name'],191);$tax['taxrate']=self::decimal($tax['taxrate'],6);$seen[$tax['name'].'|'.$tax['taxrate']]=true;}unset($tax);
        foreach($ownedHistoricalTaxes as$tax){
            if(!is_array($tax)||!isset($tax['taxname'],$tax['taxrate']))throw new DomainException('company_catalog_invalid');
            $taxName=self::text($tax['taxname'],191);$rate=self::decimal($tax['taxrate'],6);
            if(str_contains($taxName,'|')){$parts=explode('|',$taxName);if(count($parts)!==2||self::decimal($parts[1],6)!==$rate)throw new DomainException('company_catalog_invalid');$taxName=$parts[0];}
            $key=$taxName.'|'.$rate;if(!isset($seen[$key])){$taxes[]=['name'=>$taxName,'taxrate'=>$rate];$seen[$key]=true;}
        }
        foreach($taxes as$tax){$value=$tax['name'].'|'.$tax['taxrate'];$html.='<option value="'.$escape($value).'" '.(in_array($value,$selected,true)?'selected ':'').'data-taxrate="'.$escape($tax['taxrate']).'" data-taxname="'.$escape($value).'" data-subtext="'.$escape($value).'">'.$escape($tax['taxrate']).'%</option>';}
        return$html.'</select>';
    }
}
