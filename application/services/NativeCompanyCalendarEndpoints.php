<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyCalendarFeed.php';require_once __DIR__.'/NativeCompanyEvents.php';require_once __DIR__.'/NativeCompanyGoogleCalendarPreferences.php';require_once __DIR__.'/CompanyEmailTemplates.php';require_once __DIR__.'/CompanySettingsFormats.php';
/** Adapters for the original Utilities routes and views, never a parallel calendar screen. */
final class NativeCompanyCalendarEndpoints
{
    private static function context($ci):array
    {
        if(!CompanyRequestScope::nativeFor($ci))throw new DomainException('native_calendar_context_required');$c=CompanyRequestScope::context();$i=$c->issuerId();$a=$c->staffId();CompanyCalendarPreferences::authorize($ci->db,db_prefix(),$i,$a);return[$i,$a];
    }
    public static function page($ci):array
    {
        [$i,$a]=self::context($ci);$prefs=(new NativeCompanyCalendarPreferences($ci->db,db_prefix()))->page($i,$a);$store=new CompanySettingsStorage($ci->db,db_prefix());$formats=['dateformat'=>$store->get($i,'dateformat'),'time_format'=>$store->get($i,'time_format')];CompanySettingsFormats::validate($formats);
        $timezone=$store->get($i,'default_timezone');if(!in_array($timezone,DateTimeZone::listIdentifiers(),true))throw new DomainException('native_calendar_configuration_invalid');
        $google=(new NativeCompanyGoogleCalendarPreferences($ci->db,db_prefix()))->browserSources($i,$a);
        $language=$store->get($i,'active_language');$template=CompanyEmailTemplates::findForIssuer($ci->db,db_prefix(),$i,'event-notification-to-staff',$language===''?'portuguese_br':$language);
        return['native_calendar'=>true,'native_calendar_values'=>$prefs['values'],'native_calendar_config'=>['options'=>$prefs['values']+['date_format'=>CompanySettingsFormats::dates()[$formats['dateformat']],'time_format'=>$formats['time_format'],'timezone'=>$timezone,'google_api'=>$google['api_key']],'calendar_ids'=>$google['calendar_ids']], 'native_calendar_email_active'=>$template!==null&&(int)$template->active===1,'native_calendar_actor'=>$a,'native_calendar_csrf'=>CompanyRequestScope::nativeCsrfToken($ci),'title'=>_l('calendar')];
    }
    public static function feed($ci):array{[$i,$a]=self::context($ci);return(new NativeCompanyCalendarFeed($ci->db,db_prefix()))->read($i,$a,$ci->input->get());}
    public static function event($ci,$id):array
    {
        [$i,$a]=self::context($ci);$data=self::page($ci);$row=(new NativeCompanyCalendar($ci->db,db_prefix()))->event($i,$a,$id);$acl=new IssuerResourceAccess($ci->db,db_prefix());
        if(!$acl->member($i,(int)$row['userid']))throw new DomainException('native_event_denied');$author=IssuerStorage::query($ci->db,'SELECT firstname,lastname FROM '.db_prefix().'staff WHERE staffid=? AND active=1',[$row['userid']])->row_array();if(!$author||!$acl->member($i,(int)$row['userid']))throw new DomainException('native_event_denied');
        $data['event']=(object)$row;$data['native_calendar_can_edit']=(int)$row['userid']===$a||$acl->administrative($i,$a);$data['native_calendar_author']=trim($author['firstname'].' '.$author['lastname']);$data['native_calendar_revision']=NativeCompanyEvents::revision($row,$row['source_kind']);return$data;
    }
    public static function save($ci):array
    {
        CompanyRequestScope::assertNativeMutation($ci);[$i,$a]=self::context($ci);$input=$ci->input->post();$id=$input['eventid']??null;$revision=$input['revision']??null;unset($input['eventid'],$input['revision']);
        if(array_diff(array_keys($input),['title','description','start','end','public','color','reminder_before','reminder_before_type']))throw new DomainException('native_event_invalid');
        foreach(['start','end']as$key){$v=$input[$key]??'';if(!is_string($v))throw new DomainException('native_event_invalid');$input[$key]=$v===''?'':to_sql_date($v,true);}
        if(array_key_exists('public',$input)&&!in_array($input['public'],['on','1',1,true],true))throw new DomainException('native_event_invalid');$input['public']=array_key_exists('public',$input)?1:0;$input['description']=$input['description']??'';$input['reminder_before']=$input['reminder_before']??'0';$input['reminder_before_type']=$input['reminder_before_type']??'minutes';
        if($revision!==null&&!is_string($revision))throw new DomainException('native_event_invalid');$saved=(new NativeCompanyEvents($ci->db,db_prefix()))->save($i,$a,$input,$id,$revision);return['success'=>true,'message'=>_l($id===null?'utility_calendar_event_added_successfully':'event_updated')]+$saved;
    }
    public static function delete($ci,$id):array
    {
        CompanyRequestScope::assertNativeMutation($ci);[$i,$a]=self::context($ci);$revision=$ci->input->post('revision');if(!is_string($revision))throw new DomainException('native_event_invalid');(new NativeCompanyEvents($ci->db,db_prefix()))->delete($i,$a,$id,$revision);return['success'=>true,'message'=>_l('utility_calendar_event_deleted_successfully')];
    }
}
