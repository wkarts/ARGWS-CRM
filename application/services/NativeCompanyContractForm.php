<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyContracts.php';
require_once __DIR__.'/CompanyProjects.php';
require_once __DIR__.'/NativeCompanyFormatting.php';

/** Original information controls mapped to an owned draft, never to an emitted public snapshot. */
final class NativeCompanyContractForm
{
    public function __construct(private $db,private string $p){}
    private function authorize(int $issuer,int $actor,?int $contract):IssuerResourceAccess
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);
        foreach([['contracts','view'],['contracts',$contract===null?'create':'edit'],['customers','view']]as[$feature,$cap])if(!$acl->can($issuer,$actor,$feature,$cap))throw new DomainException('native_contract_form_denied');
        if(!CompanyContractMetadataSchema::available($this->db,$this->p))throw new DomainException('native_contract_metadata_unavailable');
        if($contract!==null){$row=(new CompanyContracts($this->db,$this->p))->getForAdmin($issuer,$actor,$contract);if($row['status']!=='draft'||$row['number']!==null)throw new DomainException('company_contract_immutable');}
        return$acl;
    }
    private function formats(int $issuer):array
    {
        $store=new CompanySettingsStorage($this->db,$this->p);$values=[];
        foreach(['dateformat','time_format']as$key)$values[$key]=$store->exists($issuer,$key)?$store->get($issuer,$key):CompanySettingsCatalog::defaultValue('localization',$key);
        CompanySettingsFormats::validate($values);return$values;
    }
    public function data(int $issuer,int $actor,?int $id):array
    {
        $acl=$this->authorize($issuer,$actor,$id);$contracts=new CompanyContracts($this->db,$this->p);$row=$id===null?null:$contracts->getForAdmin($issuer,$actor,$id);
        $definitions=(new NativeCompanyCustomFields($this->db,$this->p))->contractFormDefinitions($issuer,$actor,$id,$id===null?'create':'edit');
        $client=$row===null?null:$this->client($issuer,$actor,(int)$row['client_id'],$id);
        $base=IssuerStorage::query($this->db,'SELECT name FROM '.$this->p.'currencies WHERE isdefault=1')->result_array();
        if(count($base)!==1)throw new DomainException('company_contract_value_invalid');
        $project=$row['native_metadata']['project']??null;
        if($project!==null){
            $found=array_values(array_filter($client['projects'],static fn($choice)=>(string)$choice['id']===NativeCompanyResourceId::encode('company_project',$project['source_id'])));
            if(count($found)!==1)throw new DomainException('native_contract_project_stale');
            $project=['source_kind'=>'company_project','source_id'=>$found[0]['id'],'source_revision'=>$found[0]['revision']];
        }
        if($row!==null&&$contracts->getForAdmin($issuer,$actor,$id)!==$row)throw new DomainException('native_contract_form_stale');
        $this->authorize($issuer,$actor,$id);
        return['native_contract_form'=>true,'contract_form_fields'=>$definitions,'contract_form_fields_hash'=>CompanyContractMetadata::hash($definitions),
            'contract_form_format'=>$this->formats($issuer),'contract_form_client'=>$client,'contract_form_project'=>$project,
            'contract_form_base_currency'=>$base[0]['name'],'contract_form_can_projects'=>$acl->can($issuer,$actor,'projects','view')];
    }
    public function client(int $issuer,int $actor,$client,?int $contract=null):array
    {
        $acl=$this->authorize($issuer,$actor,$contract);$client=IssuerPolicy::id($client);$clients=new CompanyClients($this->db,$this->p);$row=$clients->get($issuer,$actor,$client);
        if(!$row||(int)$row['active']!==1)throw new DomainException('company_operation_reference_denied');
        $canProject=$acl->can($issuer,$actor,'projects','view');$rows=[];$projects=[];
        if($canProject){
            $query='SELECT id,name,revision FROM '.$this->p.'company_projects WHERE issuer_id=? AND client_id=? ORDER BY name,id';
            $rows=IssuerStorage::query($this->db,$query,[$issuer,$client])->result_array();
            foreach($rows as$project){
                $current=(new CompanyProjects($this->db,$this->p))->get($issuer,$actor,$project['id']);
                if((int)$current['client_id']!==$client||$current['name']!==$project['name']||(int)$current['revision']!==(int)$project['revision'])throw new DomainException('native_contract_project_stale');
                $projects[]=['id'=>NativeCompanyResourceId::encode('company_project',$project['id']),'name'=>$project['name'],'revision'=>(int)$project['revision']];
            }
            if(IssuerStorage::query($this->db,$query,[$issuer,$client])->result_array()!==$rows)throw new DomainException('native_contract_project_stale');
        }
        if($clients->get($issuer,$actor,$client)!==$row||$acl->can($issuer,$actor,'projects','view')!==$canProject)throw new DomainException('native_contract_form_stale');
        $this->authorize($issuer,$actor,$contract);
        return['client_id'=>$client,'client_name'=>$row['company'],'projects'=>$projects,'can_projects'=>$canProject];
    }
    public function input(int $issuer,int $actor,?int $id,array $post):array
    {
        // A replay may target a draft which has since been activated. The final writer owns
        // the durable receipt/revision check; normalization must not reinterpret that old form.
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if(!$acl->can($issuer,$actor,'contracts','view')||!$acl->can($issuer,$actor,'contracts',$id===null?'create':'edit'))throw new DomainException('native_contract_form_denied');
        $input=$post['contract']??null;$display=$post['native_contract_format']??null;$custom=$post['custom_fields']??[];$types=$post['native_contract_field_types']??[];
        if(($post['native_contract_form']??null)!=='1'||!is_array($input)||array_diff(array_keys($input),['client_id','name','body','start_date','due_date','contract_value','currency','contract_type','description','project_id','project_revision'])||!is_array($display)||!is_array($custom)||array_diff(array_keys($custom),['contracts'])||!is_array($custom['contracts']??[])||!is_array($types)||count($types)>256)throw new DomainException('native_contract_form_invalid');
        foreach(['client_id','name','body','start_date','due_date','contract_value','currency','contract_type','description']as$key)if(!array_key_exists($key,$input)||!is_string($input[$key]))throw new DomainException('native_contract_form_invalid');
        CompanySettingsFormats::validate($display);$format=new NativeCompanyFormatting(CompanySettingsFormats::dates()[$display['dateformat']],$display['time_format']);
        $input['start_date']=$format->toSql($input['start_date']);if($input['due_date']!=='')$input['due_date']=$format->toSql($input['due_date']);
        $values=$custom['contracts']??[];
        foreach($types as$field=>$type){
            NativeCompanyResourceId::decode('custom_field',$field);if(!in_array($type,['date_picker','date_picker_time'],true))throw new DomainException('native_contract_form_invalid');
            if(isset($values[$field])&&$values[$field]!=='')$values[$field]=$format->toSql($values[$field],$type==='date_picker_time');
        }
        $metadata=['version'=>1,'description'=>$input['description'],'clear_value'=>$input['contract_value']===''?1:0,
            'custom_fields'=>['definitions_hash'=>$post['native_contract_fields_hash']??null,'values'=>$values]];
        if(array_key_exists('project_id',$input)||array_key_exists('project_revision',$input)){
            if(!is_string($input['project_id']??null)||!is_string($input['project_revision']??null))throw new DomainException('native_contract_form_invalid');
            if($input['project_id']===''){
                if($input['project_revision']!=='')throw new DomainException('native_contract_project_stale');$metadata['project']=null;
            }else{
                $reference=NativeCompanyResourceId::decode('project',$input['project_id']);if($reference['source_kind']!=='company_project')throw new DomainException('native_contract_project_denied');
                $metadata['project']=CompanyInvoiceProjectInput::normalize(['source_kind'=>'company_project','source_id'=>$reference['source_id'],'source_revision'=>$input['project_revision']]);
            }
        }
        unset($input['description'],$input['project_id'],$input['project_revision']);$input['native_metadata']=CompanyContractMetadata::normalize($metadata);return$input;
    }
}
