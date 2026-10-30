<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyEstimateFormInput.php';
require_once __DIR__.'/NativeCompanySalesSequencePreferences.php';
require_once __DIR__.'/CompanySales.php';
require_once __DIR__.'/CompanySettingsFormats.php';
require_once __DIR__.'/CompanySettingsCatalog.php';

/**
 * Server-owned original estimate form compositor. This partial writer is not
 * admitted by HTTP while the remaining original controls have no writers.
 */
final class NativeCompanyEstimateForm
{
    public function __construct(private $db,private string $prefix){}
    public function create(int $issuer,int $actor,array $wire):array
    {
        $acl=new IssuerResourceAccess($this->db,$this->prefix);
        if($acl->primaryId()===$issuer||!$acl->can($issuer,$actor,'estimates','view')||!$acl->can($issuer,$actor,'estimates','create'))throw new DomainException('native_estimate_form_denied');
        $store=new CompanySettingsStorage($this->db,$this->prefix);
        $dateFormat=$store->exists($issuer,'dateformat')?$store->get($issuer,'dateformat'):CompanySettingsCatalog::defaultValue('localization','dateformat');
        $parsed=NativeCompanyEstimateFormInput::create($wire,$dateFormat);$meta=$parsed['metadata'];
        if($parsed['action']!=='save')throw new DomainException('native_estimate_form_mail_required');
        if($meta['status']!=='1'||$meta['estimate_request_id']!==''||array_filter($meta['line_fields']))throw new DomainException('native_estimate_form_feature_required');
        $payload=$parsed['payload'];$payload['native_reference']=$meta['reference_no'];if($meta['tags']!==[])$payload['native_tags']=$meta['tags'];
        if($meta['estimate_fields']!==[]||$this->db->table_exists($this->prefix.'company_custom_fields'))$payload['native_custom_fields']=$meta['estimate_fields'];
        if($meta['project']!==''){
            $reference=NativeCompanyResourceId::decode('project',$meta['project']);
            if($reference['source_kind']!=='company_project')throw new DomainException('native_estimate_form_project_denied');
            require_once __DIR__.'/CompanyProjects.php';
            $project=(new CompanyProjects($this->db,$this->prefix))->get($issuer,$actor,$reference['source_id']);
            if((int)$project['client_id']!==(int)$payload['client_id'])throw new DomainException('native_estimate_form_project_denied');
            $payload['native_project']=['source_kind'=>'company_project','source_id'=>(int)$project['id'],'source_revision'=>(int)$project['revision']];
        }
        $guard=static function()use($store,$issuer,$dateFormat):void{
            $current=$store->exists($issuer,'dateformat')?$store->get($issuer,'dateformat'):CompanySettingsCatalog::defaultValue('localization','dateformat');
            if($dateFormat!==$current)throw new DomainException('native_estimate_form_stale');
        };
        $id=(new CompanySales($this->db,$this->prefix))->create($issuer,$actor,'estimates',$payload,$parsed['request_key'],$meta['number_selection'],$guard);
        return ['id'=>NativeCompanyResourceId::encode('company_estimate',$id),'source_id'=>$id,'action'=>$parsed['action']];
    }
}
