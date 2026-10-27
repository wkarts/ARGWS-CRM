<?php
declare(strict_types=1);
require_once __DIR__.'/CompanySettingsCatalog.php';require_once __DIR__.'/NativeCompanyCalendarFeed.php';require_once __DIR__.'/NativeCompanyEvents.php';require_once __DIR__.'/NativeCompanyGoogleCalendarPreferences.php';require_once __DIR__.'/CompanyEmailTemplates.php';require_once __DIR__.'/CompanySettingsFormats.php';
/** Adapters for the original Utilities routes and views, never a parallel calendar screen. */
final class NativeCompanyCalendarEndpoints
{
    private static function context($ci):array
    {
        if(!CompanyRequestScope::nativeFor($ci))throw new DomainException('native_calendar_context_required');$c=CompanyRequestScope::context();$i=$c->issuerId();$a=$c->staffId();CompanyCalendarPreferences::authorize($ci->db,db_prefix(),$i,$a);return[$i,$a];
    }
    public static function page($ci):array
    {
        [$i,$a]=self::context($ci);$prefs=(new NativeCompanyCalendarPreferences($ci->db,db_prefix()))->page($i,$a);$store=new CompanySettingsStorage($ci->db,db_prefix());$formats=[];
        foreach(['dateformat','time_format']as$key)$formats[$key]=$store->exists($i,$key)?$store->get($i,$key):CompanySettingsCatalog::defaultValue('localization',$key);
        CompanySettingsFormats::validate($formats);
        // UTC is an explicit product default for an unconfigured company. Never
        // inherit another company's timezone or the PHP process's mutable timezone.
        $timezone=$store->get($i,'default_timezone');if($timezone==='')$timezone='UTC';
        if(!in_array($timezone,DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC),true))throw new DomainException('native_calendar_configuration_invalid');
        $google=(new NativeCompanyGoogleCalendarPreferences($ci->db,db_prefix()))->browserSources($i,$a);
        $language=$store->get($i,'active_language');$template=CompanyEmailTemplates::findForIssuer($ci->db,db_prefix(),$i,'event-notification-to-staff',$language===''?'portuguese_br':$language);
        return['native_calendar'=>true,'native_calendar_values'=>$prefs['values'],'native_calendar_config'=>['options'=>$prefs['values']+['date_format'=>CompanySettingsFormats::dates()[$formats['dateformat']],'time_format'=>$formats['time_format'],'timezone'=>$timezone,'google_api'=>$google['api_key']],'calendar_ids'=>$google['calendar_ids']], 'native_calendar_email_active'=>$template!==null&&(int)$template->active===1,'native_calendar_actor'=>$a,'native_calendar_csrf'=>CompanyRequestScope::nativeCsrfToken($ci),'title'=>_l('calendar')];
    }
    /** Read the origin-form HTTP target, not an ambient framework GET bag. */
    private static function feedQuery($ci):array
    {
        $target=$ci->input->server('REQUEST_URI',false);
        if(!is_string($target)||strlen($target)>8192||!str_starts_with($target,'/')||str_starts_with($target,'//')||str_contains($target,'#')||preg_match('/[\\x00-\\x20\\x7f]/',$target))throw new DomainException('native_calendar_request_target_invalid');
        $query=parse_url($target,PHP_URL_QUERY);if($query===false||($query!==null&&substr_count($query,'&')+substr_count($query,';')>=64))throw new DomainException('native_calendar_request_target_invalid');
        parse_str($query??'',$input);return $input;
    }
    public static function feed($ci):array{[$i,$a]=self::context($ci);return(new NativeCompanyCalendarFeed($ci->db,db_prefix()))->read($i,$a,self::feedQuery($ci));}
    /** Reauthorize the buffered original view before any private HTML is emitted. */
    public static function confirm($ci,array $prepared,$eventId=null):void
    {
        $current=$eventId===null?self::page($ci):self::event($ci,$eventId);
        // Event DTOs contain stdClass values; compare their typed content, not
        // the identity of separately hydrated PHP objects.
        if(json_encode($prepared,JSON_THROW_ON_ERROR)!==json_encode($current,JSON_THROW_ON_ERROR))throw new DomainException('native_calendar_stale');
    }
    public static function event($ci,$id):array
    {
        [$i,$a]=self::context($ci);$data=self::page($ci);$row=(new NativeCompanyCalendar($ci->db,db_prefix()))->event($i,$a,$id);$acl=new IssuerResourceAccess($ci->db,db_prefix());
        if(!$acl->member($i,(int)$row['userid']))throw new DomainException('native_event_denied');$author=IssuerStorage::query($ci->db,'SELECT staffid,firstname,lastname,profile_image FROM '.db_prefix().'staff WHERE staffid=? AND active=1',[$row['userid']])->row_array();if(!$author||!$acl->member($i,(int)$row['userid']))throw new DomainException('native_event_denied');
        $data['event']=(object)$row;$data['native_calendar_can_edit']=(int)$row['userid']===$a||$acl->administrative($i,$a);$data['native_calendar_author']=trim($author['firstname'].' '.$author['lastname']);$data['native_calendar_author_data']=$author;$data['native_calendar_revision']=NativeCompanyEvents::revision($row,$row['source_kind']);return$data;
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
