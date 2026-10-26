<?php

declare(strict_types=1);
require_once __DIR__.'/NativeAdminMenuCatalog.php';
require_once __DIR__.'/CompanyShellPresentation.php';
require_once __DIR__.'/NativeCompanyPresentationOptions.php';
require_once __DIR__.'/NativeAdminQuickActionsCatalog.php';

/** Server-owned, per-request presentation projection. Never bootstraps legacy models/hooks. */
final class NativeCompanyAdminPresentation
{
    private static $owner = null;
    private static ?array $data = null;
    private static ?string $token = null;
    private static ?int $issuer = null;
    private static array $language = [];

    public static function prepare($ci): void
    {
        self::$owner=null; self::$data=null; self::$issuer=null; self::$token=null;
        if (!CompanyRequestScope::nativeFor($ci) || !CompanyRequestScope::sealedFor($ci)) {
            throw new LogicException('native_company_presentation_not_attested');
        }
        $ci->load->library('user_agent'); // Pure request user-agent parsing; no models/options/providers.
        $context=CompanyRequestScope::context();
        $issuer=$context->issuerId(); $actor=$context->staffId();
        if ($issuer === null) {
            if (strtolower((string)$ci->router->fetch_class()) !== 'dashboard' || strtolower((string)$ci->router->fetch_method()) !== 'index') { throw new LogicException('native_company_presentation_missing_issuer'); }
        } elseif (!is_int($issuer) || $issuer < 1) { throw new LogicException('native_company_presentation_missing_issuer'); }
        $acl=new IssuerResourceAccess($ci->db,db_prefix());
        $staff=IssuerStorage::query($ci->db,'SELECT staffid,firstname,lastname,email,profile_image FROM '.db_prefix().'staff WHERE staffid=? AND active=1',[$actor])->row_array();
        if (!$staff || (int)$staff['staffid'] !== $actor) { throw new DomainException('native_company_staff_unavailable'); }
        // Trusted product translation only; _l() runs global hooks and is intentionally not used.
        self::$language=(static function(){ $lang=[]; require APPPATH.'language/portuguese_br/portuguese_br_lang.php'; return $lang; })();
        if(!isset($ci->lang)||!is_object($ci->lang)||!isset($ci->lang->language)||!is_array($ci->lang->language))throw new LogicException('native_company_language_unavailable');
        // The shared native controllers and views call _l(); seed CI's core
        // language object from the same trusted catalog used by our own labels.
        $ci->lang->language=array_merge($ci->lang->language,self::$language);
        $menus=['sidebar'=>[], 'setup'=>[]]; $children=[];
        foreach(NativeAdminMenuCatalog::entries() as $entry) {
            if($issuer === null) { continue; }
            if ($entry['route'] !== null && !CompanyRequestScope::nativeRouteAllowed($ci,$entry['route'])) { continue; }
            // The original Departments controller is administrator-only. Do not
            // advertise its native route to ticket viewers who cannot enter it.
            if ($entry['slug'] === 'departments') {
                if (!$acl->administrative($issuer,$actor)) { continue; }
            } elseif ($entry['feature'] !== '' && !$acl->can($issuer,$actor,$entry['feature'],'view')) { continue; }
            $item=['slug'=>$entry['slug'],'name'=>self::label($entry['label']),'href'=>$entry['route'] === null ? '#' : CompanyRequestScope::adminUrl($ci,$entry['route']), 'icon'=>$entry['icon'],'position'=>$entry['position'],'children'=>[]];
            if($entry['collapse']) { $item['collapse']=true; }
            if($entry['kind']==='sidebar_children') { $children['sidebar'][$entry['parent']][]=$item; }
            elseif($entry['kind']==='setup_children') { $children['setup'][$entry['parent']][]=$item; }
            else { $menus[$entry['kind']==='setup_menu'?'setup':'sidebar'][$entry['slug']]=$item; }
        }
        foreach($menus as $type=>&$menu) {
            foreach($menu as $slug=>&$item) {
                $item['children']=$children[$type][$slug]??[];
                usort($item['children'],static fn($a,$b)=>$a['position']<=>$b['position']);
                if(!empty($item['collapse']) && !$item['children']) { unset($menu[$slug]); }
            } unset($item);
            uasort($menu,static fn($a,$b)=>$a['position']<=>$b['position']);
        } unset($menu);
        $routes=[];
        foreach(['','settings','profile','staff/timesheets','staff/edit_profile','authentication/logout','todo'] as $route) {
            if($route === 'settings' && ($issuer === null || !$acl->can($issuer,$actor,'settings','view'))) { continue; }
            if($route === 'todo' && ($issuer === null || !$acl->can($issuer,$actor,'tasks','view'))) { continue; }
            if(($issuer !== null || $route === '') && CompanyRequestScope::nativeRouteAllowed($ci,$route)) { $routes[$route]=CompanyRequestScope::adminUrl($ci,$route); }
        }
        $publicOptions=$issuer===null?null:NativeCompanyPresentationOptions::capture($issuer,$actor);
        $quickActions=$issuer===null?[]:self::quickActions($ci,$acl,$issuer,$actor,$publicOptions);
        $uploadPreferences=null;$calendarPreferences=null;
        $issuerRegistryRoute=$issuer!==null
            && strtolower((string)$ci->router->fetch_class())==='issuers'
            && in_array(strtolower((string)$ci->router->fetch_method()),['index','save'],true);
        if($issuer!==null){
            if(!class_exists('NativeCompanyUploadPreferences',false))require_once __DIR__.'/NativeCompanyUploadPreferences.php';
            $uploadPreferences=NativeCompanyUploadPreferences::read($ci->db,db_prefix(),$issuer,$actor);
            if(!class_exists('NativeCompanyCalendarPresentation',false))require_once __DIR__.'/NativeCompanyCalendarPresentation.php';
            $calendarPreferences=NativeCompanyCalendarPresentation::read($ci->db,db_prefix(),$issuer,$actor);
        }
        $branding=['logo'=>'','logo_dark'=>'','favicon'=>''];
        if($issuer!==null){
            if(!class_exists('CompanyAssets',false))require_once __DIR__.'/CompanyAssets.php';
            $branding=(new CompanyAssets($ci->db,db_prefix()))->brandingForStaff($issuer,$actor);
            if(array_keys($branding)!==['logo','logo_dark','favicon'])throw new LogicException('native_branding_projection_invalid');
            foreach($branding as $inline)if(!is_string($inline)||($inline!==''&&!preg_match('~^data:image/png;base64,[A-Za-z0-9+/]+={0,2}$~D',$inline)))throw new LogicException('native_branding_projection_invalid');
        }
        self::$owner=$ci; self::$issuer=$issuer; self::$token=CompanyRequestScope::token();
        self::$data=[
            'locale'=>'pt-BR','browser'=>strtolower((string)$ci->agent->browser()),'rtl'=>false,'is_mobile'=>(bool)$ci->agent->is_mobile(),'staff'=>$staff,'name'=>trim($staff['firstname'].' '.$staff['lastname']),
            'avatar'=>self::avatar($staff),'logo'=>$branding['logo_dark']!==''?$branding['logo_dark']:($branding['logo']!==''?$branding['logo']:base_url('assets/images/argws/logo-dark.png')),
            'favicon'=>$branding['favicon']!==''?$branding['favicon']:base_url('assets/images/argws/favicon.png'),
            'sidebar_menu'=>$menus['sidebar'],'setup_menu'=>$menus['setup'],'routes'=>$routes,
            'theme'=>str_replace('.company-context-shell ','.native-company-admin ',CompanyShellPresentation::read($ci->db,db_prefix())),
            'public_options'=>$publicOptions,'upload_preferences'=>$uploadPreferences,'calendar_preferences'=>$calendarPreferences,
            'setup_open'=>$ci->session->has_userdata('setup-menu-open')&&in_array($ci->session->userdata('setup-menu-open'),[true,1,'1'],true),
            'can_create_task'=>$issuer!==null && $acl->can($issuer,$actor,'tasks','create'),
            'quick_actions'=>$quickActions,
        ];
        if($issuer!==null && self::headerEndpointsAvailable('search')){
            require_once __DIR__.'/NativeCompanyHeaderEndpoints.php';
            self::$data['routes']['misc/search']=CompanyRequestScope::adminUrl($ci,'misc/search');
            self::$data['header_search_history']=NativeCompanyHeaderEndpoints::history($ci,$issuer,$actor);
        }
        if($issuer!==null && self::headerEndpointsAvailable('notifications'))self::refreshNotifications($ci);
        if($issuer!==null && self::timerEndpointsAvailable())self::$data=array_merge(self::$data,self::timerProjection($ci));
        if($issuer!==null&&!$issuerRegistryRoute){
            if(!class_exists('NativeCompanyWorkTags',false))require_once __DIR__.'/NativeCompanyWorkTags.php';
            $tags=(new NativeCompanyWorkTags($ci->db,db_prefix()))->catalog($issuer,$actor);
            if(!isset($tags['available_tags'],$tags['available_tags_ids'])||!is_array($tags['available_tags'])||!is_array($tags['available_tags_ids'])||count($tags['available_tags'])!==count($tags['available_tags_ids']))throw new LogicException('native_tags_projection_invalid');
            foreach($tags['available_tags'] as $label)if(!is_string($label)||!preg_match('//u',$label))throw new LogicException('native_tags_projection_invalid');
            foreach($tags['available_tags_ids'] as $id)if(!is_string($id)||!preg_match('/^[1-9][0-9]*$/D',$id))throw new LogicException('native_tags_projection_invalid');
            self::current($ci);self::$data['work_tags']=$tags;
        }
        if($issuer!==null && isset(self::$data['routes']['todo'])){
            if(!class_exists('NativeCompanyAuxiliaryUi',false))require_once __DIR__.'/NativeCompanyAuxiliaryUi.php';
            $counts=(new NativeCompanyAuxiliaryUi($ci->db,db_prefix(),$issuer,$actor))->todoCounts();
            if(!isset($counts[0],$counts[1])||!is_int($counts[0])||!is_int($counts[1])||min($counts)<0)throw new LogicException('native_todo_projection_invalid');
            self::current($ci);self::$data['total_unfinished_todos']=$counts[0];
        }
        $ci->load->vars(['locale'=>'pt-BR','sidebar_menu'=>$menus['sidebar'],'setup_menu'=>$menus['setup']]);
    }
    public static function current($ci): ?array
    {
        if (!class_exists('CompanyRequestScope',false) || !method_exists('CompanyRequestScope','nativeFor') || !CompanyRequestScope::nativeFor($ci)) { return null; }
        if (!CompanyRequestScope::sealedFor($ci) || self::$owner!==$ci || self::$data===null
            || self::$issuer!==CompanyRequestScope::context()->issuerId() || self::$token!==CompanyRequestScope::token()) {
            throw new LogicException('native_company_presentation_stale');
        }
        return self::$data;
    }
    private static function quickActions($ci,$acl,int $issuer,int $actor,array $options):array
    {
        $formats=NativeCompanyPresentationOptions::formats($options);
        $calendarDate=date(CompanySettingsFormats::dates()[$formats['dateformat']]);
        $out=[];
        foreach(NativeAdminQuickActionsCatalog::entries(false,$calendarDate) as $entry){
            $feature=$entry['permission']??'tickets';
            if($feature==='is_staff_member')$feature='leads';
            if($feature==='')$feature='calendar';
            if(!$acl->can($issuer,$actor,$feature,'create'))continue;
            $route=$entry['url'];
            if($entry['label']==='task')$route='tasks/task';
            elseif($entry['label']==='lead')$route='leads/lead';
            elseif($entry['label']==='calendar_event'){
                if($calendarDate==='')continue; // Missing persisted localization is explicit, not a global fallback.
                $route='utilities/calendar';
            }
            if(!CompanyRequestScope::nativeRouteAllowed($ci,$route))continue;
            $entry['name']=self::label($entry['label']);unset($entry['label']);$out[]=$entry;
        }
        return $out;
    }
    private static function timerEndpointsAvailable():bool
    {
        if(!class_exists('NativeCompanyRoutes',false))return false;
        foreach(['get_staff_started_timers'=>'GET','timer_action'=>'POST','delete_user_unfinished_timesheet'=>'POST'] as $method=>$verb)if(!NativeCompanyRoutes::admits('admin','','tasks',$method,$verb))return false;
        return true;
    }
    public static function timerProjection($ci):array
    {
        $data=self::current($ci);$context=CompanyRequestScope::context();$issuer=$context->issuerId();$actor=$context->staffId();
        if($data===null||$issuer===null)throw new DomainException('native_timer_context_missing');
        foreach(['NativeCompanyTasksRead','NativeCompanyTimerPolicy','NativeCompanyBlankTimers','NativeCompanyTimerPresentation'] as $dependency)if(!class_exists($dependency,false))require_once __DIR__.'/'.$dependency.'.php';
        $timezone=date_default_timezone_get();$clock=time();
        $reader=new NativeCompanyTasksRead($ci->db,db_prefix(),$timezone);$policyReader=new NativeCompanyTimerPolicy($ci->db,db_prefix());
        $timers=$reader->startedTimers($issuer,$actor,$clock);$policy=$policyReader->read($issuer,$actor);
        $hasBlank=(bool)array_filter($timers,static fn($timer)=>(string)$timer['task_id']==='0');
        $projection=$policy+['can_start_blank'=>NativeCompanyTimerPolicy::canStartBlank($policy,$timers),'policy_hash'=>NativeCompanyBlankTimers::policyHash($policy)];
        $formats=NativeCompanyPresentationOptions::formats($data['public_options']);$formatted=$timers;
        foreach($formatted as &$timer)$timer['started_at_label']=NativeCompanyTimerPresentation::startedLabel($timer,$formats,$timezone);unset($timer);
        if($policyReader->read($issuer,$actor)!==$policy||$reader->startedTimers($issuer,$actor,$clock)!==$timers)throw new DomainException('native_company_task_read_changed');
        self::current($ci);return ['started_timers'=>$formatted,'timer_policy'=>$projection];
    }
    private static function headerEndpointsAvailable(string $feature):bool
    {
        if(!class_exists('NativeCompanyRoutes',false))return false;
        $methods=$feature==='search'?['search'=>'POST','remove_recent_search'=>'POST']:['notifications_check'=>'GET','set_notifications_read'=>'POST','set_notification_read_inline'=>'POST','set_desktop_notification_read'=>'POST','mark_all_notifications_as_read_inline'=>'POST'];
        foreach($methods as $method=>$verb)if(!NativeCompanyRoutes::admits('admin','','misc',$method,$verb))return false;
        return true;
    }
    public static function browserConfig($ci):array
    {
        $data=self::current($ci);if($data===null)throw new LogicException('native_company_presentation_not_attested');
        $setupPersistence=class_exists('NativeCompanyRoutes',false)&&NativeCompanyRoutes::admits('admin','','misc','set_setup_menu_closed','POST')&&NativeCompanyRoutes::admits('admin','','misc','set_setup_menu_open','POST');
        $frameworkCsrf=null;
        if($ci->config->item('csrf_protection'))$frameworkCsrf=['name'=>$ci->security->get_csrf_token_name(),'value'=>$ci->security->get_csrf_hash()];
        return ['setupPersistence'=>$setupPersistence,'frameworkCsrf'=>$frameworkCsrf,'root'=>CompanyRequestScope::adminUrl($ci),'context'=>CompanyRequestScope::token(),'csrf'=>CompanyRequestScope::nativeCsrfToken($ci),
            'search'=>isset($data['header_search_history']),'history'=>$data['header_search_history']??[],
            'notifications'=>isset($data['notifications']),'options'=>$data['public_options'],'language'=>'portuguese_br','browser'=>in_array($data['browser'],['safari','firefox','chrome','edge'],true)?$data['browser']:'other',
            'newNotificationLabel'=>self::label('new_notification')];
    }
    /** Minimum public app contract for the actual shared native JavaScript.
     * Missing tags/file/calendar/table policies are deliberately not fabricated here.
     * Their consumers may be admitted only after their owned providers are composed.
     */
    public static function appConfig($ci):array
    {
        $data=self::current($ci);if($data===null)throw new LogicException('native_company_presentation_not_attested');
        $values=$data['public_options']['values']??[];
        $options=['has_permission_create_task'=>$data['can_create_task'],'decimal_places'=>2];
        if(self::ticketPage($ci)){$options['is_ai_provider_enabled']=false;$options['ai_provider_unavailable_reason']='company_scoped_provider_not_implemented';} // Native core sales precision; no global hook.
        if($data['public_options']!==null){$options['tables_pagination_limit']=NativeCompanyPresentationOptions::pagination($data['public_options']);$options['calendar_first_day']=$data['calendar_preferences']['calendar_first_day'];}
        foreach(['time_format','desktop_notifications'] as $name)if(($values[$name]??null)!==null)$options[$name]=$values[$name];
        if($data['public_options']!==null){$formats=NativeCompanyPresentationOptions::formats($data['public_options']);$options['date_format']=CompanySettingsFormats::dates()[$formats['dateformat']];$options['time_format']=$formats['time_format'];}
        if(($values['auto_dismiss_desktop_notifications_after']??null)!==null)$options['dismiss_desktop_not_after']=$values['auto_dismiss_desktop_notifications_after'];
        $lang=[];
        foreach(['something_went_wrong','warn_ticket_thread_too_long','dt_length_menu_all','dt_button_reload','dt_button_excel','dt_button_csv','dt_button_pdf','dt_button_print','dt_button_export','dt_button_column_visibility','options','confirm_action_prompt','create_reminder','save','calendar_expand','filter_by','note','search_tasks','task_stop_timer','currently_selected','media_files','not_results_found','search_ajax_empty','search_ajax_initialized','search_ajax_placeholder','search_ajax_searching','tag','drop_files_here_to_upload','browser_not_support_drag_and_drop','file_exceeds_maxfile_size_in_form','cancel_upload','remove_file','you_can_not_upload_any_more_files'] as $key)$lang[$key]=self::label($key);
        require_once APPPATH.'helpers/datatables_helper.php';
        $lang['datatables']=get_datatables_language_array([self::class,'label']);
        $app=['dtFilters'=>[],'version'=>ARGWS_VERSION,'locale'=>$data['locale'],'tinymce_lang'=>'pt_BR','user_language'=>'portuguese_br',
            'browser'=>in_array($data['browser'],['safari','firefox','chrome','edge'],true)?$data['browser']:'other',
            'user_id'=>(string)$data['staff']['staffid'],'is_mobile'=>$data['is_mobile']?'1':'0',
            'options'=>$options,'lang'=>$lang];
        if(isset($data['work_tags'])){$app['available_tags']=$data['work_tags']['available_tags'];$app['available_tags_ids']=$data['work_tags']['available_tags_ids'];}
        if($data['upload_preferences']!==null){
            require_once APPPATH.'helpers/files_helper.php'; // Native pure size parser; no model/options/hooks invoked.
            $limit=NativeCompanyUploadPreferences::maxBytes($data['upload_preferences']);$phpLimit=file_upload_max_size();
            $app['options']['allowed_files']=$data['upload_preferences']['allowed_files'];
            $app['max_php_ini_upload_size_bytes']=(string)($phpLimit>0?min($phpLimit,$limit):$limit);
        }
        return $app;
    }
    private static function avatar(array $staff): string
    {
        $fallback=base_url('assets/images/user-placeholder.jpg');
        $name=$staff['profile_image']??null;
        if(!is_string($name)||!preg_match('/^[A-Za-z0-9_.-]+$/D',$name)||$name==='.'||$name==='..')return $fallback;
        $relative='uploads/staff_profile_images/'.(int)$staff['staffid'].'/small_'.$name;
        $root=defined('FCPATH')?FCPATH:dirname(APPPATH).'/';
        $directory=realpath($root.'uploads/staff_profile_images/'.(int)$staff['staffid']);
        $file=realpath($root.$relative);
        if($directory===false||$file===false||!str_starts_with($file,$directory.DIRECTORY_SEPARATOR)||!is_file($file))return $fallback;
        return base_url($relative);
    }
    public static function refreshNotifications($ci): array
    {
        self::current($ci); // Require the same attested controller/context, never accept an arbitrary DTO.
        if(self::$issuer===null)throw new DomainException('native_company_presentation_missing_issuer');
        require_once __DIR__.'/NativeCompanyNotifications.php';
        $data=(new NativeCompanyNotifications($ci->db,db_prefix()))->read(self::$issuer,CompanyRequestScope::context()->staffId());
        self::current($ci);
        require_once __DIR__.'/NativeCompanyResourceId.php';
        foreach($data['items'] as &$row){$row['id']=NativeCompanyResourceId::encode('company_notification',(string)$row['id']);}unset($row);
        require_once __DIR__.'/NativeCompanyLegacyHeader.php';
        $legacy=new NativeCompanyLegacyHeader($ci->db,db_prefix());$actor=CompanyRequestScope::context()->staffId();
        if($legacy->available(self::$issuer,$actor)){
            $historical=$legacy->notifications(self::$issuer,$actor);
            foreach($historical['items'] as $row){
                $format=self::label($row['description']);$arguments=array_map(static fn($arg)=>(string)$arg,$row['arguments']);
                try{$title=$arguments?vsprintf($format,$arguments):$format;}catch(ValueError $invalid){throw new DomainException('native_company_legacy_notification_invalid');}
                $data['items'][]=['id'=>$row['id'],'url'=>$row['url'],'title'=>$title,'body'=>'','created_at'=>$row['date'],'read_at'=>$row['read_at'],'inline_read_at'=>$row['inline_read_at']];
            }
            $data['unread']+=$historical['unread'];
            usort($data['items'],static fn($a,$b)=>strcmp($b['created_at'],$a['created_at'])?:strcmp($b['id'],$a['id']));
            $data['items']=array_slice($data['items'],0,50);
        }
        self::$data['notifications']=$data;
        return $data;
    }
    public static function label(string $key): string { return (string)(self::$language[$key]??$key); }
    public static function css($ci=null): array { $assets=['assets/css/reset.min.css','assets/plugins/inter/inter.css','assets/builds/vendor-admin.css','assets/plugins/font-awesome/css/fontawesome.min.css','assets/plugins/font-awesome/css/brands.min.css','assets/plugins/font-awesome/css/solid.min.css','assets/plugins/font-awesome/css/regular.min.css','assets/builds/tailwind.css','assets/css/style.min.css'];if($ci!==null&&self::calendarPage($ci))$assets[]='assets/plugins/fullcalendar/lib/main.min.css';return $assets; }
    public static function scripts($ci=null): array { $assets=['assets/builds/vendor-admin.js','assets/plugins/datatables/datatables.min.js','assets/plugins/tinymce/tinymce.min.js','assets/js/native-company-transport.js','assets/js/native-admin-runtime.js','assets/builds/bootstrap-select.min.js','assets/plugins/jquery-validation/jquery.validate.min.js','assets/plugins/jquery-validation/localization/messages_pt_BR.min.js','assets/builds/common.js','assets/js/native-ui-tools.js','assets/js/native-admin-sales-form.js','assets/js/native-tasks.js','assets/js/native-admin-reminders.js','assets/js/native-admin-todos.js','assets/js/native-admin-notes.js','assets/js/native-admin-files.js','assets/js/native-admin-item-order.js','assets/js/native-admin-calendar.js','assets/js/native-admin-navigation.js','assets/js/native-admin-tables.js','assets/js/native-admin-ticket-table.js','assets/js/native-admin-staff.js','assets/js/native-admin-switch.js','assets/js/native-admin-imap.js','assets/js/native-admin-search.js','assets/js/native-admin-notifications.js','assets/js/native-company-admin.js'];if($ci!==null&&self::calendarPage($ci)){array_splice($assets,array_search('assets/js/native-admin-calendar.js',$assets,true),0,['assets/plugins/fullcalendar/lib/main.min.js','assets/plugins/fullcalendar/lib/locales/pt-br.js']);}if($ci!==null&&self::ticketPage($ci)){$assets[]='assets/plugins/Chart.js/Chart.bundle.min.js';$assets[]='assets/js/tickets.js';}return$assets; }
    private static function calendarPage($ci):bool
    {
        return self::current($ci)!==null&&self::$issuer!==null&&strtolower((string)$ci->router->fetch_class())==='utilities'&&strtolower((string)$ci->router->fetch_method())==='calendar';
    }
    private static function ticketPage($ci):bool
    {
        $data=self::current($ci);if($data===null||self::$issuer===null)return false;
        return strtolower((string)$ci->router->fetch_class())==='tickets'&&in_array(strtolower((string)$ci->router->fetch_method()),['index','add','ticket'],true);
    }
}
