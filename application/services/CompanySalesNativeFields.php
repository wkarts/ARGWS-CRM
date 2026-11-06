<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyCustomFields.php';
require_once __DIR__.'/NativeCompanyContentHtml.php';

/** Additive original-form metadata on canonical sales snapshots, without changing recurring invoices. */
trait CompanySalesNativeFields
{
    public static function input(array $input):array
    {
        $extra=array_intersect_key($input,array_flip(['native_proposal','native_status','native_item_fields']));
        foreach(array_keys($extra)as$key)unset($input[$key]);
        // The original proposal has an optional expiry date, as does the original estimate.
        $optional=isset($extra['native_proposal'])&&!array_key_exists('native_fields',$input)&&in_array($input['due_date']??null,[null,''],true);
        if($optional){$input['native_fields']=[];$input['due_date']=null;}
        if(array_key_exists('native_fields',$input)&&($input['due_date']??null)==='')$input['due_date']=null;
        $payload=parent::input($input);if($optional)unset($payload['native_fields']);
        if(array_key_exists('native_proposal',$extra))$payload['native_proposal']=self::proposalFields($extra['native_proposal']);
        if(array_key_exists('native_status',$extra)){
            if(!is_int($extra['native_status'])||$extra['native_status']<1||$extra['native_status']>6)throw new DomainException('native_sales_status_invalid');
            $payload['native_status']=$extra['native_status'];
        }
        if(array_key_exists('native_item_fields',$extra)){
            $frame=$extra['native_item_fields'];
            if(!is_array($frame)||array_keys($frame)!==['definitions_hash','values']||!is_string($frame['definitions_hash'])||!preg_match('/^[a-f0-9]{64}$/D',$frame['definitions_hash'])||!is_array($frame['values'])||!array_is_list($frame['values'])||count($frame['values'])!==count($payload['items']))throw new DomainException('native_sales_item_fields_invalid');
            foreach($frame['values']as$values){if(!is_array($values)||count($values)>256)throw new DomainException('native_sales_item_fields_invalid');foreach($values as$id=>$value){NativeCompanyResourceId::decode('custom_field',$id);if(!is_string($value)&&!is_array($value))throw new DomainException('native_sales_item_fields_invalid');}}
            if(strlen(self::encode($frame))>262144)throw new DomainException('native_sales_item_fields_invalid');
            $payload['native_item_fields']=$frame;
        }
        return$payload;
    }
    public static function proposalFields($fields):array
    {
        $sizes=['subject'=>191,'proposal_to'=>191,'address'=>1000,'city'=>100,'state'=>100,'zip'=>50,'email'=>150,'phone'=>50,'content'=>65535];
        if(!is_array($fields)||array_diff(array_keys($fields),array_merge(array_keys($sizes),['country','assigned','allow_comments','show_quantity_as'])))throw new DomainException('native_proposal_form_invalid');
        $out=[];
        foreach($sizes as$key=>$max){$value=$fields[$key]??($key==='content'?'{proposal_items}':'');if(!is_string($value)||strlen($value)>$max||preg_match('//u',$value)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$value))throw new DomainException('native_proposal_form_invalid');$out[$key]=$value;}
        if(trim($out['subject'])===''||trim($out['proposal_to'])===''||($out['email']!==''&&filter_var($out['email'],FILTER_VALIDATE_EMAIL)===false))throw new DomainException('native_proposal_form_invalid');
        $out['content']=NativeCompanyContentHtml::purify($out['content']);
        foreach(['country','assigned']as$key){$value=$fields[$key]??0;if($value===''||$value==='0')$value=0;$out[$key]=$value===0?0:IssuerPolicy::id($value);}
        foreach(['allow_comments'=>[0,1],'show_quantity_as'=>[1,2,3]]as$key=>$allowed){$value=$fields[$key]??($key==='allow_comments'?1:1);if(!in_array($value,array_merge($allowed,array_map('strval',$allowed)),true))throw new DomainException('native_proposal_form_invalid');$out[$key]=(int)$value;}
        return$out;
    }
    protected function customTablesForPayload(array $payload):array
    {
        $tables=parent::customTablesForPayload($payload);
        if(array_key_exists('native_item_fields',$payload))$tables=array_merge($tables,(new NativeCompanyCustomFields($this->db,$this->prefix))->tableNames());
        return array_values(array_unique($tables));
    }
    protected function assertNativeFields(int $issuer,int $actor,string $kind,array $payload):void
    {
        if(isset($payload['native_status'])&&!in_array($payload['native_status'],$kind==='estimates'?[1,2,5]:[1,4,5,6],true))throw new DomainException('native_sales_status_requires_decision');
        if($kind==='estimates'){
            if(isset($payload['native_proposal']))throw new DomainException('native_proposal_form_invalid');
            parent::assertNativeFields($issuer,$actor,$kind,$payload);return;
        }
        if($kind!=='proposals'||isset($payload['native_fields']))throw new DomainException('company_sales_invalid');
        if(isset($payload['native_proposal'])){
            $fields=self::proposalFields($payload['native_proposal']);$acl=new IssuerResourceAccess($this->db,$this->prefix);
            if($fields['assigned']!==0&&(!$acl->member($issuer,$fields['assigned'])||!$acl->can($issuer,$fields['assigned'],'proposals','view')))throw new DomainException('native_proposal_agent_denied');
            if($fields['country']!==0&&count($this->rows('SELECT country_id FROM '.$this->prefix.'countries WHERE country_id=?',[$fields['country']]))!==1)throw new DomainException('native_proposal_country_denied');
        }
        if(($project=$payload['native_project']??null)!==null){
            require_once __DIR__.'/CompanyProjects.php';$project=CompanyInvoiceProjectInput::normalize($project);
            $row=(new CompanyProjects($this->db,$this->prefix))->getInOwnedTransaction($issuer,$actor,$project['source_id']);
            if((int)$row['client_id']!==$payload['client_id'])throw new DomainException('native_proposal_project_denied');
            if((int)$row['revision']!==$project['source_revision'])throw new DomainException('native_proposal_project_stale');
        }
    }
    private function assertNativeItemFields(int $issuer,int $actor,string $kind,array $payload,string $cap):void
    {
        if(!array_key_exists('native_item_fields',$payload))return;
        $fields=new NativeCompanyCustomFields($this->db,$this->prefix);$definitions=$fields->salesItemDefinitions($issuer,$actor,$kind,$cap,['active'=>1]);
        if(!hash_equals($payload['native_item_fields']['definitions_hash'],self::hash($definitions)))throw new DomainException('native_sales_item_fields_stale');
        foreach($payload['native_item_fields']['values']as$values)$fields->validateSalesItemValues($issuer,$actor,$kind,$cap,$values);
        if($fields->salesItemDefinitions($issuer,$actor,$kind,$cap,['active'=>1])!==$definitions)throw new DomainException('native_sales_item_fields_stale');
    }
}
