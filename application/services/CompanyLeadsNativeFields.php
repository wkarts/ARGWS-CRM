<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyLeadMetadata.php';
require_once __DIR__.'/CompanyLeadMetadataSchema.php';
require_once __DIR__.'/NativeCompanyCustomFields.php';
require_once __DIR__.'/NativeCompanyResourceTags.php';
require_once __DIR__.'/NativeCompanyCurrencyReference.php';
require_once __DIR__.'/NativeCompanyFormatting.php';

/** Composes optional original fields within CompanyLeads' existing request/transaction boundary. */
trait CompanyLeadsNativeFields
{
    private ?array $nativeLeadCreation=null;
    private function nativeFieldsTables():array
    {
        if(!CompanyLeadMetadataSchema::available($this->db,$this->p))throw new RuntimeException('company_lead_metadata_schema_missing');
        return array_values(array_unique(array_merge([CompanyLeadMetadataSchema::TABLE,'countries','currencies','options','customfields','customfieldsvalues'],
            (new NativeCompanyCustomFields($this->db,$this->p))->tableNames(),NativeCompanyResourceTags::tableNames())));
    }
    private function nativeMetadataRow(int $issuer,int $id):?array
    {
        if(!CompanyLeadMetadataSchema::available($this->db,$this->p))return null;
        IssuerTransaction::assertOwned($this->db,$this->p,[CompanyLeadMetadataSchema::TABLE,'company_leads']);
        $rows=$this->rows('SELECT m.* FROM '.$this->p.CompanyLeadMetadataSchema::TABLE.' m JOIN '.$this->p.'company_leads l ON l.issuer_id=m.issuer_id AND l.id=m.lead_id WHERE m.lead_id=? AND m.issuer_id=?',[$id,$issuer]);
        if(count($rows)>1)throw new DomainException('company_lead_metadata_corrupt');$row=$rows[0]??null;if($row!==null)CompanyLeadMetadata::fromRow($row);return$row;
    }
    private function nativeFieldFormats(int $issuer):array
    {
        $store=new CompanySettingsStorage($this->db,$this->p);$out=[];foreach(['dateformat','time_format']as$key)$out[$key]=$store->exists($issuer,$key)?$store->get($issuer,$key):CompanySettingsCatalog::defaultValue('localization',$key);
        CompanySettingsFormats::validate($out);return$out;
    }
    private function nativeDefaultCountry(int $issuer):int
    {
        $store=new CompanySettingsStorage($this->db,$this->p);$value=$store->exists($issuer,'customer_default_country')?$store->get($issuer,'customer_default_country'):'';
        if(!preg_match('/^(?:|0|[1-9][0-9]{0,8})$/D',$value))throw new DomainException('company_lead_country_denied');$country=$value===''?0:(int)$value;
        if($country!==0&&count($this->rows('SELECT country_id FROM '.$this->p.'countries WHERE country_id=?',[$country]))!==1)throw new DomainException('company_lead_country_denied');return$country;
    }
    /** No clock/random data in the frame: an unchanged form keeps the same review commitment. */
    private function nativeFieldFrame(int $issuer,int $actor,?array $lead):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->nativeFieldsTables());$this->authorize($issuer,$actor,$lead===null?'create':'view');
        CompanyLeadMetadataSchema::assertValid($this->db,$this->p);
        $id=$lead===null?null:(int)$lead['id'];$canonical=$id===null?null:NativeCompanyResourceId::encode('company_lead',$id);$metadata=$id===null?null:$this->nativeMetadataRow($issuer,$id);
        $fields=new NativeCompanyCustomFields($this->db,$this->p);$definitions=$fields->definitions($issuer,$actor,'leads',['active'=>1],$id===null?'create':'view');
        $values=$canonical===null?[]:$fields->values($issuer,$actor,'leads',$canonical);$active=array_fill_keys(array_map(static fn($r)=>(string)$r['id'],$definitions),true);$values=array_intersect_key($values,$active);
        $tags=$canonical===null?['tags'=>[],'revision'=>null]:(new NativeCompanyResourceTags($this->db,$this->p))->read($issuer,$actor,'lead',$canonical);
        $stored=$metadata===null?null:CompanyLeadMetadata::fromRow($metadata);
        $currency=$stored['currency']??CompanyLeadMetadata::currency((new NativeCompanyCurrencyReference($this->db,$this->p,$issuer,$actor))->baseCurrency());
        $formats=$this->nativeFieldFormats($issuer);
        $frame=['issuer'=>$issuer,'actor'=>$actor,'lead'=>$id,'lead_revision'=>$lead===null?null:(int)$lead['revision'],'row'=>$metadata,'definitions'=>$definitions,'custom_fields'=>$values,'tags'=>$tags,'currency'=>$currency,'formats'=>$formats,'default_country'=>$this->nativeDefaultCountry($issuer)];
        $frame['revision']=CompanyLeadMetadata::hash($frame);$frame['values']=$stored;return$frame;
    }
    /** Administrative form data only; API/get/page DTOs do not receive private custom-field values. */
    public function nativeFieldForm($issuer,$actor,?int $id=null):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);if($id!==null)$id=IssuerPolicy::id($id);
        return$this->transaction($issuer,$actor,$id===null?'create':'view','original_fields',null,function()use($issuer,$actor,$id):array{
            $lead=$id===null?null:$this->lead($issuer,$id);$frame=$this->nativeFieldFrame($issuer,$actor,$lead);
            if($lead!==null)$this->remember('company_leads',['id'=>$id],$lead);
            $this->validators[]=function()use($issuer,$actor,$id,$frame):void{if($this->nativeFieldFrame($issuer,$actor,$id===null?null:$this->lead($issuer,$id))['revision']!==$frame['revision'])throw new DomainException('company_lead_metadata_stale');};
            $countries=$this->rows('SELECT country_id,short_name FROM '.$this->p.'countries ORDER BY short_name,country_id');$languages=NativeAvailableLanguages::all();
            return['revision'=>$frame['revision'],'lead_revision'=>$frame['lead_revision'],'values'=>$frame['values'],'definitions'=>$frame['definitions'],'custom_fields'=>$frame['custom_fields'],'tags'=>$frame['tags'],'currency'=>$frame['currency'],'formats'=>$frame['formats'],'default_country'=>$frame['default_country'],'countries'=>$countries,'languages'=>$languages];
        },$this->nativeFieldsTables());
    }
    private function nativeFieldPreparation(int $issuer,int $actor,?array $lead,array $input):array
    {
        $input=CompanyLeadMetadata::normalize($input);$frame=$this->nativeFieldFrame($issuer,$actor,$lead);
        if(!hash_equals($frame['revision'],$input['form_revision'])||$input['formats']!==$frame['formats'])throw new DomainException('company_lead_metadata_stale');
        if($lead!==null&&$input['contacted_today']===1)throw new DomainException('company_lead_metadata_invalid');
        if($input['country']!==0){$country=$this->rows('SELECT country_id,short_name FROM '.$this->p.'countries WHERE country_id=?',[$input['country']]);if(count($country)!==1)throw new DomainException('company_lead_country_denied');$this->validators[]=function()use($input,$country):void{if($this->rows('SELECT country_id,short_name FROM '.$this->p.'countries WHERE country_id=?',[$input['country']])!==$country)throw new DomainException('company_lead_metadata_stale');};}
        $formats=$frame['formats'];$formatting=new NativeCompanyFormatting(CompanySettingsFormats::dates()[$formats['dateformat']],$formats['time_format']);$submitted=$input['custom_fields'];
        foreach($frame['definitions']as$definition){$field=(string)$definition['id'];if(!array_key_exists($field,$submitted)||$submitted[$field]==='')continue;if(in_array($definition['type'],['date_picker','date_picker_time'],true))$submitted[$field]=$formatting->toSql($submitted[$field],$definition['type']==='date_picker_time');}
        (new NativeCompanyCustomFields($this->db,$this->p))->validateValues($issuer,$actor,'leads',$submitted,$lead===null?'create':'edit');
        return['input'=>$input,'frame'=>$frame,'custom_fields'=>$submitted];
    }
    public function assertCreatedInOwned($db,string $p,int $issuer,int $actor,string $kind,$id):void
    {
        $id=IssuerPolicy::id($id);if($db!==$this->db||$p!==$this->p||$kind!=='company_lead'||$this->nativeLeadCreation!==[$issuer,$actor,$id])throw new DomainException('native_custom_field_creation_denied');
        IssuerTransaction::assertOwned($db,$p,array_merge($this->tables(),$this->nativeFieldsTables()));$this->authorize($issuer,$actor,'create');$this->assertFrames();
        $lead=$this->lead($issuer,$id);if((int)$lead['created_by']!==$actor||(int)$lead['revision']!==1||$lead['converted_client_id']!==null||(int)$lead['archived']!==0)throw new DomainException('native_custom_field_creation_denied');
    }
    private function persistNativeFields(int $issuer,int $actor,int $id,array $prepared,bool $created):void
    {
        $input=$prepared['input'];$before=$prepared['frame'];$now=self::now();$values=CompanyLeadMetadata::values($input,$before['currency'],$now);$columns=CompanyLeadMetadata::columns($values);
        $old=$before['row'];if($old!==null&&(int)$old['revision']>=2147483647)throw new DomainException('company_lead_revision_limit');
        $columns+=['revision'=>$old===null?1:(int)$old['revision']+1,'updated_at'=>$now];
        if($old===null)$this->insert(CompanyLeadMetadataSchema::TABLE,['lead_id'=>$id,'issuer_id'=>$issuer]+$columns,'lead_id');
        else $this->updateRow(CompanyLeadMetadataSchema::TABLE,$old,$columns,'lead_id');
        $fields=new NativeCompanyCustomFields($this->db,$this->p);$tags=new NativeCompanyResourceTags($this->db,$this->p);$canonical=NativeCompanyResourceId::encode('company_lead',$id);
        if($created){
            if($this->nativeLeadCreation!==null)throw new DomainException('native_custom_field_creation_denied');$this->nativeLeadCreation=[$issuer,$actor,$id];
            try{$fields->saveCreatedInOwnedTransaction($issuer,$actor,'leads',$id,$prepared['custom_fields'],$this);$tags->createLeadTagsInOwnedTransaction($issuer,$actor,$id,$input['tags'],$this);}
            finally{$this->nativeLeadCreation=null;}
        }else{
            $fields->saveInOwnedTransaction($issuer,$actor,'leads',$canonical,$prepared['custom_fields']);
            $tags->replaceInOwnedTransaction($issuer,$actor,'lead',$canonical,$before['tags']['revision'],$input['tags']);
        }
        $after=$this->nativeFieldFrame($issuer,$actor,$this->lead($issuer,$id));
        if($after['values']!==$values||array_column($after['tags']['tags'],'name')!==$input['tags']||$after['definitions']!==$before['definitions']||$after['formats']!==$before['formats']||$after['default_country']!==$before['default_country'])throw new RuntimeException('issuer_lead_write_unconfirmed');
        $this->validators[]=function()use($issuer,$actor,$id,$after):void{if($this->nativeFieldFrame($issuer,$actor,$this->lead($issuer,$id))['revision']!==$after['revision'])throw new DomainException('company_lead_metadata_stale');};
    }
}
