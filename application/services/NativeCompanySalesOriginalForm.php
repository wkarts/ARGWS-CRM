<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanySalesOriginalInput.php';
require_once __DIR__.'/NativeCompanySalesEditorItems.php';
require_once __DIR__.'/NativeCompanySalesSequencePreferences.php';
require_once __DIR__.'/NativeCompanySalesSettings.php';
require_once __DIR__.'/NativeCompanyClients.php';
require_once __DIR__.'/NativeCompanyFormatting.php';
require_once __DIR__.'/NativeCompanySalesTable.php';

/** Data and writers for the original estimate/proposal forms, using the existing company services. */
final class NativeCompanySalesOriginalForm
{
    public function __construct(private $db,private string $p,private string $kind)
    {if(!in_array($kind,['estimates','proposals'],true))throw new DomainException('native_sales_editor_invalid');}
    private function type():string{return$this->kind==='estimates'?'estimate':'proposal';}
    private function authorize(int $issuer,int $actor,string $cap):IssuerResourceAccess
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if(!$acl->can($issuer,$actor,$this->kind,'view')||!$acl->can($issuer,$actor,$this->kind,$cap))throw new DomainException('native_sales_editor_denied');
        return$acl;
    }
    private function options(int $issuer,array $selection):array
    {
        $store=new CompanySettingsStorage($this->db,$this->p);$out=[];
        foreach(NativeCompanySalesSettings::defaults($this->kind)as$key=>$default)if($key!=='next_estimate_number')$out[$key]=$store->exists($issuer,$key)?$store->get($issuer,$key):$default;
        NativeCompanySalesSettings::validate($this->kind,$out,false);
        foreach(['number_padding_prefixes'=>'6','automatically_set_logged_in_staff_sales_agent'=>'1','default_tax'=>'a:0:{}']as$key=>$default)$out[$key]=$store->exists($issuer,$key)?$store->get($issuer,$key):$default;
        if(!preg_match('/^(?:0|[1-9][0-9]?)$/D',$out['number_padding_prefixes'])||!in_array($out['automatically_set_logged_in_staff_sales_agent'],['0','1'],true))throw new DomainException('native_sales_editor_options_invalid');
        $out['next_estimate_number']=$selection['next_number'];return$out;
    }
    private function staff(int $issuer,int $actor,string $cap):array
    {
        $acl=$this->authorize($issuer,$actor,$cap);$out=[];
        foreach(IssuerStorage::query($this->db,'SELECT staffid FROM '.$this->p.'staff WHERE active=1 ORDER BY staffid')->result_array()as$row){
            $id=IssuerPolicy::id($row['staffid']);if(!$acl->member($issuer,$id)||!$acl->can($issuer,$id,$this->kind,'view'))continue;
            $name=IssuerStorage::query($this->db,'SELECT firstname,lastname FROM '.$this->p.'staff WHERE staffid=? AND active=1',[$id])->row_array();
            if(!$name)throw new DomainException('native_sales_editor_changed');$out[]=['staffid'=>$id,'firstname'=>(string)$name['firstname'],'lastname'=>(string)$name['lastname']];
        }
        $this->authorize($issuer,$actor,$cap);return$out;
    }
    private static function customInput(array $values,array $definitions,NativeCompanyFormatting $formatting):array
    {
        foreach($definitions as$definition){
            $id=(string)$definition['id'];if(!array_key_exists($id,$values)||$values[$id]==='')continue;
            if(in_array($definition['type'],['date_picker','date_picker_time'],true))$values[$id]=$formatting->toSql($values[$id],$definition['type']==='date_picker_time');
        }
        return$values;
    }
    public function data(int $issuer,int $actor,string $id='',$customer=null):array
    {
        $cap=$id===''?'create':'edit';$acl=$this->authorize($issuer,$actor,$cap);$review=null;$document=null;$type=$this->type();
        if($id!==''){
            $ref=NativeCompanyResourceId::decode($type,$id);if($ref['source_kind']!=='company_'.$type)throw new DomainException('native_sales_editor_denied');
            $sales=new CompanySales($this->db,$this->p);$review=$sales->review($issuer,$actor,$ref['source_id'],'edit');$document=$sales->document($issuer,$actor,$ref['source_id']);
            if($review['kind']!==$this->kind)throw new DomainException('native_sales_editor_denied');$id=$ref['canonical_id'];$customer=$review['payload']['client_id'];
        }
        $selection=$review===null?(new NativeCompanySalesSequencePreferences($this->db,$this->p))->readForCreation($issuer,$actor,$this->kind):['next_number'=>(string)$review['number'],'revision'=>$review['source_revision']];
        $catalog=new NativeCompanyCatalogUi($this->db,$this->p,$issuer,$actor);$options=$this->options($issuer,$selection);
        $taxes=@unserialize($options['default_tax'],['allowed_classes'=>false]);if(!is_array($taxes)||!array_is_list($taxes))throw new DomainException('native_sales_editor_options_invalid');
        $options['default_tax']=serialize($catalog->validateTaxSelections($taxes));$choices=$catalog->salesEditorChoices($this->kind);
        foreach($choices['taxes']as&$tax)$tax['taxrate']=NativeCompanyCatalog::simpleFields(['name'=>(string)$tax['name'],'taxrate'=>(string)$tax['taxrate']],true)['taxrate'];unset($tax);
        $editor=NativeCompanySalesEditorItems::load($this->db,$this->p,$issuer,$actor,$this->kind,$id);
        $data=$choices+['native_sales_form'=>true,'native_sales_kind'=>$this->kind,'native_sales_id'=>$id,'native_sales_revision'=>$review['source_revision']??'',
            'native_sales_number_selection'=>$selection,'native_sales_request_key'=>bin2hex(random_bytes(32)),'native_sales_metadata_revision'=>$review['metadata_revision']??'',
            'native_sales_options'=>$options,'native_sales_editor_items'=>$editor,'native_sales_tags'=>implode(',',$review['payload']['native_tags']??[]),
            'currencies'=>$catalog->currencies(),'base_currency'=>(object)$catalog->baseCurrency(),'staff'=>$this->staff($issuer,$actor,$cap),
            'estimate_statuses'=>range(1,5),'statuses'=>range(1,6),'bodyclass'=>$type,'title'=>_l($id===''?($type==='estimate'?'create_new_estimate':'new_proposal'):($type==='estimate'?'edit_estimate':'edit_proposal')),
            'native_sales_customer_has_projects'=>false,'native_sales_project_name'=>'','native_sales_customer'=>null,'native_sales_can_create_item'=>$acl->can($issuer,$actor,'items','view')&&$acl->can($issuer,$actor,'items','create')];
        if($customer!==null&&$customer!==''){
            $customer=IssuerPolicy::id($customer);$details=(new CompanyClients($this->db,$this->p))->details($issuer,$actor,$customer);
            if(!$details)throw new DomainException('company_client_access_denied');$data['customer_id']=$customer;$data['native_sales_customer']=['id'=>$customer,'name'=>$details['client']['company']];
            if($acl->can($issuer,$actor,'projects','view')&&$this->db->table_exists($this->p.'company_projects'))$data['native_sales_customer_has_projects']=(bool)IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.'company_projects WHERE issuer_id=? AND client_id=? LIMIT 1',[$issuer,$customer])->row_array();
        }
        if($review!==null){
            $payload=$review['payload'];$amounts=CompanyInvoiceLines::calculate($payload['items'],array_intersect_key($payload,array_flip(['discount_mode','discount_value','discount_type','adjustment','calculation_contract'])));
            $native=CompanyInvoiceNativeFields::fromRecord($payload['native_fields']??[]);$prefs=$review['context']['sales_preferences'];
            $row=['id'=>$id,'clientid'=>$payload['client_id'],'currency'=>$payload['currency'],'date'=>$payload['date'],'expirydate'=>$payload['due_date'],'open_till'=>$payload['due_date'],
                'number'=>$review['number'],'prefix'=>$prefs['prefix'],'number_format'=>$prefs['number_format']??1,'status'=>$payload['native_status']??1,
                'project_id'=>0,'estimate_id'=>null,'invoice_id'=>$document['invoice_id']?:null,'reference_no'=>$payload['native_reference']??'','clientnote'=>$payload['note'],'items'=>$editor->rows(),'show_shipping_on_estimate'=>$native['show_shipping_on_invoice']??0,
                'subtotal'=>$amounts['subtotal'],'total'=>$amounts['total'],'total_tax'=>$amounts['tax'],'discount_total'=>$amounts['discount_total'],'discount_percent'=>$amounts['discount_percent'],
                'discount_type'=>$amounts['discount_type'],'adjustment'=>$amounts['adjustment']]+$native;
            if(($project=$payload['native_project']??null)!==null){$row['project_id']=NativeCompanyResourceId::encode('company_project',$project['source_id']);$data['native_sales_project_name']=NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,'project',$row['project_id'])['name'];}
            if($type==='proposal')$row=array_replace($row,CompanySales::proposalFields($payload['native_proposal']??['subject'=>$document['formatted_number'],'proposal_to'=>$review['context']['recipient']['name']]),['rel_id'=>$payload['client_id'],'rel_type'=>'customer']);
            $data[$type]=(object)$row;if($type==='proposal'){$data['estimate']=$data[$type];$data['is_proposal']=true;}$data['native_sales_formatted_number']=$document['formatted_number'];
        }
        $this->authorize($issuer,$actor,$cap);$editor->assertCurrent();return$data;
    }
    public function save(int $issuer,int $actor,array $wire):array
    {
        $editing=is_string($wire['_native_sales_id']??null)&&$wire['_native_sales_id']!=='';$cap=$editing?'edit':'create';$this->authorize($issuer,$actor,$cap);
        $store=new CompanySettingsStorage($this->db,$this->p);$readFormat=function()use($store,$issuer):array{$values=[];foreach(['dateformat','time_format']as$key)$values[$key]=$store->exists($issuer,$key)?$store->get($issuer,$key):CompanySettingsCatalog::defaultValue('localization',$key);CompanySettingsFormats::validate($values);return$values;};$formatFrame=$readFormat();$format=$formatFrame['dateformat'];$formatting=new NativeCompanyFormatting(CompanySettingsFormats::dates()[$format],$formatFrame['time_format']);
        $request=NativeCompanySalesOriginalInput::parse($this->kind,$wire,$format);$meta=$request['metadata'];$payload=$request['payload'];
        if($request['action']!=='save')throw new DomainException('native_sales_form_delivery_requires_acceptance');
        if($meta['estimate_request_id']!=='')throw new DomainException('native_sales_form_request_conversion_required');
        if(!in_array($payload['native_status'],$this->kind==='estimates'?[1,2,5]:[1,4,5,6],true))throw new DomainException('native_sales_status_requires_decision');
        $sales=new CompanySales($this->db,$this->p);$existing=null;
        if($editing){$ref=NativeCompanyResourceId::decode($this->type(),$request['id']);$existing=$sales->document($issuer,$actor,$ref['source_id']);if($existing['kind']!==$this->kind)throw new DomainException('native_sales_editor_denied');if((string)$existing['number']!==$meta['number_selection']['next_number'])throw new DomainException('native_sales_form_number_immutable');}
        $fields=new NativeCompanyCustomFields($this->db,$this->p);$headerDefinitions=$editing?$fields->definitionsForResource($issuer,$actor,$this->type(),$request['id'],['active'=>1],'edit'):$fields->definitions($issuer,$actor,$this->type(),['active'=>1],'create');
        $payload['native_reference']=$meta['reference_no'];$payload['native_tags']=$meta['tags'];$payload['native_custom_fields']=self::customInput($meta['estimate_fields'],$headerDefinitions,$formatting);$payload['native_project']=null;
        if($meta['project']!==''){
            require_once __DIR__.'/CompanyProjects.php';$project=NativeCompanyResourceId::decode('project',$meta['project']);if($project['source_kind']!=='company_project')throw new DomainException('native_sales_form_project_denied');
            $row=(new CompanyProjects($this->db,$this->p))->get($issuer,$actor,$project['source_id']);if((int)$row['client_id']!==$payload['client_id'])throw new DomainException('native_sales_form_project_denied');
            $payload['native_project']=['source_kind'=>'company_project','source_id'=>$project['source_id'],'source_revision'=>(int)$row['revision']];
        }
        $definitions=$fields->salesItemDefinitions($issuer,$actor,$this->kind,$cap,['active'=>1]);
        foreach($meta['line_fields']as&$values){$values=self::customInput($values,$definitions,$formatting);$fields->validateSalesItemValues($issuer,$actor,$this->kind,$cap,$values);}unset($values);
        if($definitions!==[]||array_filter($meta['line_fields']))$payload['native_item_fields']=['definitions_hash'=>hash('sha256',json_encode($definitions,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)),'values'=>$meta['line_fields']];
        $catalog=new NativeCompanyCatalogUi($this->db,$this->p,$issuer,$actor);$historical=[];
        foreach($existing['payload']['items']??[]as$line)foreach($line['taxes']as$tax)$historical[$tax['name'].'|'.NativeCompanyTaxRate::exact($tax['rate'])]=true;
        $pending=[];foreach($payload['items']as$line)foreach($line['taxes']as$tax){$pair=$tax['name'].'|'.NativeCompanyTaxRate::exact($tax['rate']);if(!isset($historical[$pair]))$pending[$pair]=true;}
        $catalog->validateTaxSelections(array_keys($pending));
        $guard=function()use($readFormat,$formatFrame,$issuer,$actor,$cap,$definitions,$fields,$pending,$catalog,$headerDefinitions,$editing,$request):void{
            $currentHeader=$editing?$fields->definitionsForResource($issuer,$actor,$this->type(),$request['id'],['active'=>1],'edit'):$fields->definitions($issuer,$actor,$this->type(),['active'=>1],'create');
            if($readFormat()!==$formatFrame||$fields->salesItemDefinitions($issuer,$actor,$this->kind,$cap,['active'=>1])!==$definitions||$currentHeader!==$headerDefinitions)throw new DomainException('native_sales_editor_changed');$catalog->validateTaxSelections(array_keys($pending));$this->authorize($issuer,$actor,$cap);
        };
        $guard();
        $source=$editing?$sales->edit($issuer,$actor,$ref['source_id'],$payload,$request['review'],$request['request_key'],$request['metadata_revision'],$guard):$sales->create($issuer,$actor,$this->kind,$payload,$request['request_key'],$meta['number_selection'],$guard);
        return['id'=>NativeCompanyResourceId::encode('company_'.$this->type(),$source),'source_id'=>$source,'action'=>'save'];
    }
}
