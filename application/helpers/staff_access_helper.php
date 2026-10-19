<?php

defined('BASEPATH') or exit('No direct script access allowed');
require_once APPPATH.'services/issuers/IssuerStaffAccess.php';

function issuers_staff_resource_catalog(): array
{
    $catalog=[];
    foreach(get_available_staff_permissions() as $feature=>$definition) {
        try { IssuerResourceAccess::key($feature); } catch(DomainException $error) { continue; }
        foreach(($definition['capabilities']??[]) as $capability=>$label) {
            try { IssuerResourceAccess::key($capability); } catch(DomainException $error) { continue; }
            if(is_array($label)&&!empty($label['not_applicable'])) { continue; }
            $catalog[$feature]['name']=$catalog[$feature]['name']??$definition['name'];
            $catalog[$feature]['capabilities'][$capability]=is_array($label)?$label['name']:$label;
        }
    }
    require_once APPPATH.'services/CompanyResourceCatalog.php';
    foreach(CompanyResourceCatalog::definitions() as $feature=>$definition){
        $catalog[$feature]['name']=$catalog[$feature]['name']??$definition['name'];
        $catalog[$feature]['capabilities']=array_merge($catalog[$feature]['capabilities']??[],$definition['capabilities']);
    }
    return $catalog;
}
function issuers_staff_access_service(): IssuerStaffAccess
{
    $CI=&get_instance();
    return new IssuerStaffAccess($CI->db,db_prefix(),get_staff_user_id(),array_map(fn($definition)=>array_keys($definition['capabilities']),issuers_staff_resource_catalog()));
}
function issuers_staff_form_token(): string
{
    $CI=&get_instance(); $token=$CI->session->userdata('issuers_staff_csrf');
    if(!is_string($token)||strlen($token)!==64) { $token=bin2hex(random_bytes(32)); $CI->session->set_userdata('issuers_staff_csrf',$token); }
    return $token;
}
function issuers_render_staff_access($staff=null,bool $personal=false): void
{
    $CI=&get_instance();
    try {
        $data=$personal?issuers_staff_access_service()->ownOptions():issuers_staff_access_service()->read($staff);
        $CI->load->view('admin/issuers/staff_access',array_merge($data,['personal'=>$personal,'issuers_staff_csrf'=>issuers_staff_form_token(),'resources'=>issuers_staff_resource_catalog()]));
    } catch(DomainException $error) {
        echo '<p class="text-muted">'.html_escape(_l('issuer_staff_access_admin_only')).'</p>';
    } catch(Throwable $error) {
        log_message('error','Issuer staff access form unavailable; no staff values logged.');
        echo '<p class="text-danger">'.html_escape(_l('issuer_storage_failed')).'</p>';
    }
}
/** Remove module-only fields before the native staff writer sees them. */
function issuers_prepare_staff_access(array &$data,$staff=null,bool $personal=false): ?array
{
    $keys=['issuers_access_present','issuers_access_csrf','issuer_memberships','issuer_default','issuer_resource_permissions'];
    $provided=false; $input=[];
    foreach($keys as $key) { if(array_key_exists($key,$data)) { $provided=true; $input[$key]=$data[$key]; unset($data[$key]); } }
    if(!$provided) { return null; }
    $CI=&get_instance(); $token=$CI->session->userdata('issuers_staff_csrf'); $submitted=$input['issuers_access_csrf']??null;
    if(($input['issuers_access_present']??null)!=='1'||!is_string($token)||strlen($token)!==64||!is_string($submitted)||!hash_equals($token,$submitted)) { throw new DomainException('issuer_invalid_csrf'); }
    if($personal) {
        if(array_key_exists('issuer_memberships',$input)||array_key_exists('issuer_resource_permissions',$input)) { throw new DomainException('issuer_access_denied'); }
        return ['personal'=>true,'default'=>issuers_staff_access_service()->validateOwnDefault($input['issuer_default']??null)];
    }
    $prepared=issuers_staff_access_service()->prepare($staff,$input['issuer_memberships']??[],$input['issuer_default']??null,$input['issuer_resource_permissions']??[]);
    return array_merge($prepared,['personal'=>false]);
}
/** An active new employee must not be silently created without any usable company. */
function issuers_require_new_staff_access(?array $prepared): void
{
    if (!issuer_core_ready()) { return; }
    if ($prepared === null || !empty($prepared['personal']) || empty($prepared['memberships']) || $prepared['default'] === null) {
        throw new DomainException('issuer_staff_company_access_required');
    }
}
/** Staff profile and issuer access are separate units; never report grants saved after a failure. */
function issuers_apply_staff_access(?array $prepared,$staff): bool
{
    if($prepared===null) { return true; }
    try {
        if($prepared['personal']) { issuers_staff_access_service()->saveOwnDefault($prepared['default']); }
        else { issuers_staff_access_service()->save($staff,$prepared['memberships'],$prepared['default'],array_map(fn($features)=>array_map(fn($caps)=>array_keys($caps),$features),$prepared['resources'])); }
        set_alert('success',_l('issuer_staff_access_saved'));
        return true;
    } catch(Throwable $error) {
        log_message('error','Issuer staff access write failed after native profile processing; no staff values logged.');
        set_alert('warning',_l('issuer_staff_access_not_saved'));
        return false;
    }
}
