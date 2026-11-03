<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyStaffFormRead.php';
require_once __DIR__.'/NativeCompanyStaffFormCommand.php';
require_once __DIR__.'/NativeCompanyStaffEndpoints.php';
require_once __DIR__.'/NativeCompanyStaffTimesheets.php';
require_once __DIR__.'/NativeCompanyStaffTimerActions.php';
require_once __DIR__.'/NativeCompanyAuxiliaryUi.php';
require_once __DIR__.'/NativeCompanyCurrencyReference.php';
require_once __DIR__.'/NativeCompanyFormatting.php';
final class NativeCompanyStaffMember
{
    private static function context($ci):array{if(!CompanyRequestScope::nativeFor($ci))throw new DomainException('issuer_staff_denied');$c=CompanyRequestScope::context();if((int)get_staff_user_id()!==$c->staffId())throw new DomainException('issuer_staff_denied');return[$c->issuerId(),$c->staffId()];}
    public static function page($ci,$id=''):array
    {
        [$i,$a]=self::context($ci);$id=$id===''?null:IssuerPolicy::id($id);$read=new NativeCompanyStaffFormRead($ci->db,db_prefix());$data=$read->page($i,$a,$id);
        $data['native_staff_languages']=NativeAvailableLanguages::all();$data['native_staff_request_key']=bin2hex(random_bytes(32));$data['native_staff_avatar_request_key']=bin2hex(random_bytes(32));
        $data['native_staff_department_email_conflict']=false;if($id!==null){$data['native_staff_department_email_conflict']=(bool)IssuerStorage::query($ci->db,'SELECT id FROM '.db_prefix().'company_departments WHERE issuer_id=? AND email=? LIMIT 1',[$i,$data['member']->email])->row_array();}
        require_once __DIR__.'/CompanyEmailTemplates.php';$template=CompanyEmailTemplates::findForIssuer($ci->db,db_prefix(),$i,'new-staff-created','portuguese_br');$data['native_staff_welcome_available']=$template&&(int)$template->active===1;
        $data['base_currency']=(object)(new NativeCompanyCurrencyReference($ci->db,db_prefix(),$i,$a))->baseCurrency();$data['title']=$id===null?_l('add_new',_l('staff_member')):$data['member']->firstname.' '.$data['member']->lastname;
        if($id!==null){$filter=['this_month'=>true];if($ci->input->get('filter')){$range=$ci->input->get('range');if(!is_string($range)||!in_array($range,['this_month','last_month','this_week','last_week','period'],true))throw new DomainException('native_staff_timesheet_filter_invalid');if($range==='period'){$formatter=NativeCompanyFormatting::current();if(!$formatter)throw new DomainException('native_format_denied');$filter=['period-from'=>$formatter->toSql($ci->input->get('period-from')),'period-to'=>$formatter->toSql($ci->input->get('period-to'))];}else$filter=[$range=>true];}
            $data['logged_time']=(new NativeCompanyStaffTimesheets($ci->db,db_prefix(),date_default_timezone_get()))->read($i,$a,$id,$filter);$data['timesheets']=$data['logged_time']['timesheets'];$actions=new NativeCompanyStaffTimerActions($ci->db,db_prefix());foreach($data['timesheets'] as &$timer)$timer['native_stop']=$actions->forRow($i,$a,$timer);unset($timer);$data['user_notes']=(new NativeCompanyAuxiliaryUi($ci->db,db_prefix(),$i,$a,$ci))->notes('staff',$id);
        }
        $again=$read->page($i,$a,$id);foreach(['native_staff_revision','native_staff_form_revision','native_staff_roles_revision','native_staff_rate_revision','native_staff_can_edit','native_staff_profile_editable']as$key)if($data[$key]!==$again[$key])throw new DomainException('issuer_staff_stale');return$data;
    }
    public static function save($ci,$id=''):int
    {
        [$i,$a]=self::context($ci);CompanyRequestScope::assertNativeMutation($ci);$id=$id===''?null:IssuerPolicy::id($id);$input=$ci->input->post(null,false);if(!is_array($input))throw new DomainException('issuer_staff_invalid');if(isset($ci->security))unset($input[$ci->security->get_csrf_token_name()]);
        $ci->load->library('encryption');$avatar=NativeCompanyStaffEndpoints::uploadedAvatar($ci,$i,$a,$id);
        return(new NativeCompanyStaffFormCommand($ci->db,db_prefix()))->save($i,$a,$id,$input,(string)$ci->config->item('base_url'),(string)$ci->config->item('encryption_key'),fn($value)=>$ci->encryption->decrypt($value),$avatar);
    }
}
