<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyCreditMetadata.php';
require_once __DIR__.'/NativeCompanyCreditCatalogLookup.php';
require_once __DIR__.'/NativeCompanyClients.php';
require_once __DIR__.'/NativeCompanyFormatting.php';

/** Original creation controls mapped to optional immutable metadata; issued credits remain unchanged. */
final class NativeCompanyCreditForm
{
    public function __construct(private $db,private string $p){}
    private function authorize(int $issuer,int $actor):IssuerResourceAccess
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);
        foreach([['credit_notes','view'],['credit_notes','create'],['customers','view']]as[$feature,$cap])if(!$acl->can($issuer,$actor,$feature,$cap))throw new DomainException('native_credit_editor_denied');
        return$acl;
    }
    private function formatFrame(int $issuer):array
    {
        $store=new CompanySettingsStorage($this->db,$this->p);$values=[];
        foreach(['dateformat','time_format']as$key)$values[$key]=$store->exists($issuer,$key)?$store->get($issuer,$key):CompanySettingsCatalog::defaultValue('localization',$key);
        CompanySettingsFormats::validate($values);return $values;
    }
    public function data(int $issuer,int $actor):array
    {
        $acl=$this->authorize($issuer,$actor);$fields=new NativeCompanyCustomFields($this->db,$this->p);
        $headers=$fields->definitions($issuer,$actor,'credit_note',['active'=>1],'create');$lines=$fields->creditItemDefinitions($issuer,$actor,'create',['active'=>1]);
        $store=new CompanySettingsStorage($this->db,$this->p);$terms=$store->exists($issuer,'predefined_terms_credit_note')?$store->get($issuer,'predefined_terms_credit_note'):CompanySettingsCatalog::defaultValue('documents','predefined_terms_credit_note');
        $choices=(new NativeCompanyCreditCatalogLookup($this->db,$this->p,$issuer,$actor))->choices();
        $countries=IssuerStorage::query($this->db,'SELECT country_id,short_name FROM '.$this->p.'countries ORDER BY short_name,country_id')->result_array();
        $this->authorize($issuer,$actor);
        $base=(new NativeCompanyCatalogUi($this->db,$this->p,$issuer,$actor))->baseCurrency();
        return['credit_display_format'=>$this->formatFrame($issuer),'credit_base_currency'=>(int)$base['id'],'credit_header_fields'=>$headers,'credit_item_fields'=>$lines,'credit_header_hash'=>CompanyCreditMetadata::hash($headers),'credit_item_hash'=>CompanyCreditMetadata::hash($lines),
            'credit_terms'=>$terms,'credit_countries'=>$countries,'credit_can_project'=>$acl->can($issuer,$actor,'projects','view'),'credit_choices'=>$choices];
    }
    public function client(int $issuer,int $actor,$id):array
    {
        $acl=$this->authorize($issuer,$actor);$id=IssuerPolicy::id($id);$clients=new NativeCompanyClients($this->db,$this->p,$issuer,$actor);$details=$clients->details($id);
        if(!$details)throw new DomainException('company_client_access_denied');$owned=isset($details['native_profile']);$profile=$details['native_profile']??[];
        $billing=[];foreach(['billing','shipping']as$part)foreach(['street','city','state','zip','country']as$field)$billing[$part.'_'.$field]=$profile[$part.'_'.$field]??($field==='country'?0:'');
        $currency=(int)($profile['default_currency']??0);
        if($currency!==0&&count(IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.'currencies WHERE id=?',[$currency])->result_array())!==1)throw new DomainException('native_credit_editor_stale');
        $canProject=$acl->can($issuer,$actor,'projects','view');$rows=[];$projects=[];
        if($canProject){
            $rows=IssuerStorage::query($this->db,'SELECT id,name,revision FROM '.$this->p.'company_projects WHERE issuer_id=? AND client_id=? ORDER BY name,id',[$issuer,$id])->result_array();
            foreach($rows as$row){$current=(new CompanyProjects($this->db,$this->p))->get($issuer,$actor,$row['id']);
                if((int)$current['client_id']!==$id||$current['name']!==$row['name']||(int)$current['revision']!==(int)$row['revision'])throw new DomainException('native_credit_editor_stale');
                $projects[]=['id'=>NativeCompanyResourceId::encode('company_project',$row['id']),'name'=>$row['name'],'revision'=>(int)$row['revision']];
            }
            if(IssuerStorage::query($this->db,'SELECT id,name,revision FROM '.$this->p.'company_projects WHERE issuer_id=? AND client_id=? ORDER BY name,id',[$issuer,$id])->result_array()!==$rows)throw new DomainException('native_credit_editor_stale');
        }
        if($clients->details($id)!==$details||$acl->can($issuer,$actor,'projects','view')!==$canProject)throw new DomainException('native_credit_editor_stale');$this->authorize($issuer,$actor);
        return['client_id'=>$id,'billing_shipping'=>[$billing],'client_currency'=>$currency,'profile_available'=>$owned,'projects'=>$projects];
    }
    private static function customInput(array $values,array $definitions,NativeCompanyFormatting $format):array
    {
        foreach($definitions as$definition){$id=(string)$definition['id'];if(!isset($values[$id])||$values[$id]==='')continue;
            if(in_array($definition['type'],['date_picker','date_picker_time'],true))$values[$id]=$format->toSql($values[$id],$definition['type']==='date_picker_time');
        }
        return$values;
    }
    public function input(int $issuer,int $actor,array $input,array $displayFormat):array
    {
        $this->authorize($issuer,$actor);$metadata=$input['native_metadata']??null;$items=$input['items']??null;
        if(!is_array($metadata)||!is_array($items)||count($items)<1||count($items)>100||!is_array($metadata['items']??null)||array_keys($metadata['items'])!==array_keys($items))throw new DomainException('company_credit_metadata_invalid');
        $project=$metadata['project']??null;
        if(!is_array($project)||array_diff(array_keys($project),['source_kind','source_id','source_revision'])||($project['source_kind']??null)!=='company_project'||!is_string($project['source_id']??null))throw new DomainException('company_credit_metadata_invalid');
        if($project['source_id']===''){
            if(($project['source_revision']??'')!=='')throw new DomainException('company_credit_project_stale');$metadata['project']=null;
        }else{
            $reference=NativeCompanyResourceId::decode('project',$project['source_id']);if($reference['source_kind']!=='company_project')throw new DomainException('company_credit_project_denied');
            $metadata['project']=CompanyInvoiceProjectInput::normalize(['source_kind'=>'company_project','source_id'=>$reference['source_id'],'source_revision'=>$project['source_revision']??null]);
        }
        // The form transports its supported display convention. A settings change between GET and POST
        // must not reinterpret an ambiguous day/month or alter a retry; the writer hashes canonical dates.
        CompanySettingsFormats::validate($displayFormat);$format=new NativeCompanyFormatting(CompanySettingsFormats::dates()[$displayFormat['dateformat']],$displayFormat['time_format']);
        $fields=new NativeCompanyCustomFields($this->db,$this->p);$headers=$fields->definitions($issuer,$actor,'credit_note',['active'=>1],'create');$lines=$fields->creditItemDefinitions($issuer,$actor,'create',['active'=>1]);
        foreach(['custom_fields','item_fields']as$frame)if(!is_array($metadata[$frame]??null)||array_diff(array_keys($metadata[$frame]),['definitions_hash','values']))throw new DomainException('company_credit_metadata_invalid');
        if(isset($metadata['custom_fields']['values'])&&!is_array($metadata['custom_fields']['values']))throw new DomainException('company_credit_metadata_invalid');
        $metadata['custom_fields']['values']=self::customInput($metadata['custom_fields']['values']??[],$headers,$format);
        $lineValues=$metadata['item_fields']['values']??[];
        if(!is_array($lineValues)||array_diff(array_keys($lineValues),array_keys($items)))throw new DomainException('company_credit_metadata_invalid');
        $metadata['item_fields']['values']=[];
        foreach($items as$index=>$item){if(!is_array($lineValues[$index]??[]))throw new DomainException('company_credit_metadata_invalid');$metadata['item_fields']['values'][]=self::customInput($lineValues[$index]??[],$lines,$format);}
        $metadata['items']=array_values($metadata['items']);$input['items']=array_values($items);
        $input['native_metadata']=CompanyCreditMetadata::normalize($metadata,count($input['items']));
        $this->authorize($issuer,$actor);return$input;
    }
}
