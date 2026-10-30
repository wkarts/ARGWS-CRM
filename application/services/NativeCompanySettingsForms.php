<?php

declare(strict_types=1);
require_once __DIR__.'/CompanySettingsStorage.php';
require_once __DIR__.'/CompanySettingsCatalog.php';
require_once __DIR__.'/NativeCompanyCustomFields.php';
require_once __DIR__.'/CompanyAssets.php';
require_once __DIR__.'/NativeCompanyInvoicePreferences.php';
require_once __DIR__.'/NativeCompanyTicketPreferences.php';
require_once __DIR__.'/NativeCompanyTaskSettings.php';
require_once __DIR__.'/NativeCompanyMiscSettings.php';
require_once __DIR__.'/NativeCompanyClientsSettings.php';
require_once __DIR__.'/NativeCompanyMailSettings.php';
require_once __DIR__.'/NativeCompanyCalendarPreferences.php';
require_once __DIR__.'/NativeCompanyGoogleSettings.php';
require_once __DIR__.'/NativeCompanyInvoiceSequencePreferences.php';
require_once __DIR__.'/NativeCompanySalesSettings.php';
require_once __DIR__.'/CompanyProjectFileSettings.php';
require_once __DIR__.'/CompanyPdfTypography.php';
require_once __DIR__.'/NativeCompanyPdfSettings.php';
require_once __DIR__.'/CompanyTicketPreferences.php';
require_once __DIR__.'/CompanyTaskPreferences.php';
require_once __DIR__.'/CompanyTaskTimerPreferences.php';
require_once __DIR__.'/CompanyTaskTimerStopPreferences.php';
require_once __DIR__.'/CompanyTaskOperationPreferences.php';
/** Writers for existing Settings forms. No new URL, UI, inferred issuer or global option fallback. */
final class NativeCompanySettingsForms
{
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    /** Groups whose existing Settings partial has a complete owned read/write adapter. */
    public static function availableGroups():array
    {
        return ['general','company','localization','certificates','email','sales_general','invoices','estimates','proposals','clients','tasks','tickets','google','calendar','misc',
            'task_timer_stop','task_timers','task_operations','task_defaults','ticket_preferences','pdf_typography','project_files','pdf','tags'];
    }
    public function authorize(int $issuer,int $actor,string $cap):void
    {
        if(!in_array($cap,['view','edit'],true)||!(new IssuerResourceAccess($this->db,$this->p))->can($issuer,$actor,'settings',$cap))throw new DomainException('native_settings_denied');
    }
    public static function fields(string $group):array
    {
        return match($group){
            'invoices'=>NativeCompanyInvoicePreferences::fields(),
            'estimates','proposals'=>NativeCompanySalesSettings::fields($group),
            'tickets'=>NativeCompanyTicketPreferences::fields(),
            'tasks'=>NativeCompanyTaskSettings::fields(),
            'misc'=>NativeCompanyMiscSettings::fields(),
            'clients'=>NativeCompanyClientsSettings::fields(),
            'email'=>NativeCompanyMailSettings::fields(),
            'calendar'=>array_keys(NativeCompanyCalendarPreferences::defaults()),
            'certificates'=>[],
            'task_timer_stop'=>[CompanyTaskTimerStopPreferences::AUTO_STOP],
            'task_timers'=>[CompanyTaskTimerPreferences::TRANSITION],
            'task_operations'=>[CompanyTaskOperationPreferences::PRIORITY,CompanyTaskOperationPreferences::FOLLOW],
            'task_defaults'=>[CompanyTaskPreferences::STATUS,CompanyTaskPreferences::ASSIGN],
            'ticket_preferences'=>[CompanyTicketPreferences::KEY],
            'pdf'=>NativeCompanyPdfSettings::fields(),
            'pdf_typography'=>CompanyPdfTypography::FIELDS,
            'project_files'=>[CompanyProjectFileSettings::KEY],
            'google'=>NativeCompanyGoogleSettings::FIELDS,
            'sales_general'=>['decimal_separator','thousand_separator','number_padding_prefixes','automatically_set_logged_in_staff_sales_agent','show_tax_per_item','remove_tax_name_from_item_table','items_table_amounts_exclude_currency_symbol','default_tax','remove_decimals_on_zero','total_to_words_enabled','total_to_words_lowercase'],
            'general'=>['companyname','main_domain','rtl_support_admin','rtl_support_client','allowed_files'],
            'company'=>array_merge(CompanySettingsCatalog::fields('company'),['company_info_format']),
            'localization'=>['dateformat','time_format','default_timezone','active_language','enabled_languages','disable_language','localization_settings'],
            default=>throw new DomainException('native_settings_group_unavailable'),
        };
    }
    private function tables(string $group):array{return array_values(array_unique(array_merge(array_merge($group==='tickets'?(new NativeCompanyTicketPreferences($this->db,$this->p))->tables():[],$group==='invoices'?(new NativeCompanyInvoiceSequencePreferences($this->db,$this->p))->tables():[],$group==='sales_general'?['company_taxes','company_items','company_item_groups','taxes']:[],['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','issuers_audit','staff','options']),$group==='company'?(new NativeCompanyCustomFields($this->db,$this->p))->tableNames():[])));}
    private function capture(int $issuer,int $actor,string $group):array
    {
        $this->authorize($issuer,$actor,'view');$storage=new CompanySettingsStorage($this->db,$this->p);$values=[];
        foreach(self::fields($group)as$name)$values[$name]=$group==='invoices'&&!$storage->exists($issuer,$name)?NativeCompanyInvoicePreferences::defaults()[$name]:$storage->get($issuer,$name);
        // A new company has no stored upload preference yet. The existing
        // general form must display the same bounded default used by uploads;
        // an explicitly stored empty/invalid value must still be rejected.
        if($group==='general'&&!$storage->exists($issuer,'allowed_files'))$values['allowed_files']=NativeCompanyUploadPreferences::defaultValue('allowed_files');
        $ticketPolicy=$group==='tickets'?(new NativeCompanyTicketPreferences($this->db,$this->p))->readInOwnedTransaction($issuer,$actor):null;
        if($ticketPolicy!==null)$values=$ticketPolicy['values'];
        $sequence=$group==='invoices'?(new NativeCompanyInvoiceSequencePreferences($this->db,$this->p))->readInOwnedTransaction($issuer,$actor):null;
        if($sequence!==null)$values['next_invoice_number']=$sequence['next_number'];
        $custom=$group==='company'?(new NativeCompanyCustomFields($this->db,$this->p))->storedInput($issuer,$actor,'company',$issuer):[];
        $definitions=$group==='company'?(new NativeCompanyCustomFields($this->db,$this->p))->definitionsForResource($issuer,$actor,'company',$issuer):[];
        $assets=$group==='general'?(new CompanyAssets($this->db,$this->p))->view($issuer,$actor):null;
        return ['ticket_policy'=>$ticketPolicy,'sequence'=>$sequence,'values'=>$values,'custom_fields'=>$custom,'custom_field_definitions'=>$definitions,'assets'=>$assets,'revision'=>hash('sha256',json_encode([$issuer,$group,$values,$custom,$definitions,$assets,$sequence,$ticketPolicy],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR))];
    }
    public function page(int $issuer,int $actor,string $group):array
    {
        self::fields($group);$this->authorize($issuer,$actor,'view');
        if($group==='pdf')return(new NativeCompanyPdfSettings($this->db,$this->p))->page($issuer,$actor);
        if(self::isPolicyGroup($group))return$this->policyPage($issuer,$actor,$group);
        if(in_array($group,['estimates','proposals'],true))return(new NativeCompanySalesSettings($this->db,$this->p))->page($issuer,$actor,$group)+['assets'=>null,'custom_fields'=>[]];
        if($group==='clients')return(new NativeCompanyClientsSettings($this->db,$this->p))->page($issuer,$actor);
        if($group==='misc')return(new NativeCompanyMiscSettings($this->db,$this->p))->page($issuer,$actor);
        if($group==='tasks')return(new NativeCompanyTaskSettings($this->db,$this->p))->page($issuer,$actor);
        if($group==='google')return(new NativeCompanyGoogleSettings($this->db,$this->p))->page($issuer,$actor);
        if($group==='calendar')return(new NativeCompanyCalendarPreferences($this->db,$this->p))->page($issuer,$actor)+['assets'=>null,'custom_fields'=>[]];
        if($group==='email')return(new NativeCompanyMailSettings($this->db,$this->p))->page($issuer,$actor)+['assets'=>null,'custom_fields'=>[]];
        return IssuerTransaction::run($this->db,$this->p,$this->tables($group),fn()=>$this->capture($issuer,$actor,$group));
    }
    public function save(int $issuer,int $actor,string $group,array $settings,array $custom,string $revision,array $uploads=[],?callable $encrypt=null,?callable $decrypt=null):array
    {
        $this->authorize($issuer,$actor,'edit');
        if($group==='pdf'){if($custom!==[])throw new DomainException('native_settings_invalid');return(new NativeCompanyPdfSettings($this->db,$this->p))->save($issuer,$actor,$revision,$settings,$uploads);}
        if(self::isPolicyGroup($group)){
            if($uploads!==[]||$custom!==[]||!preg_match('/^(?:0|[1-9][0-9]{0,9})$/D',$revision)||(int)$revision>2147483647||array_keys($settings)!==self::fields($group))throw new DomainException('native_settings_invalid');
            $service=$this->policyService($group);$service->save($issuer,$actor,(int)$revision,$settings);return$this->policyPage($issuer,$actor,$group);
        }
        if(in_array($group,['estimates','proposals'],true)){if($uploads!==[]||$custom!==[])throw new DomainException('native_settings_invalid');return(new NativeCompanySalesSettings($this->db,$this->p))->save($issuer,$actor,$group,$revision,$settings)+['assets'=>null,'custom_fields'=>[]];}
        if($group==='clients'){if($uploads!==[]||$custom!==[])throw new DomainException('native_settings_invalid');return(new NativeCompanyClientsSettings($this->db,$this->p))->save($issuer,$actor,$revision,$settings);}
        if($group==='misc'){if($uploads!==[]||$custom!==[])throw new DomainException('native_settings_invalid');return(new NativeCompanyMiscSettings($this->db,$this->p))->save($issuer,$actor,$revision,$settings);}
        if($group==='tasks'){if($uploads!==[]||$custom!==[])throw new DomainException('native_settings_invalid');return(new NativeCompanyTaskSettings($this->db,$this->p))->save($issuer,$actor,$revision,$settings);}
        if($group==='google'){if($uploads!==[]||$custom!==[]||$encrypt===null)throw new DomainException('native_settings_invalid');return(new NativeCompanyGoogleSettings($this->db,$this->p))->save($issuer,$actor,$revision,$settings,$encrypt);}
        if($group==='calendar'){
            if($uploads!==[]||$custom!==[])throw new DomainException('native_settings_invalid');
            $calendar=new NativeCompanyCalendarPreferences($this->db,$this->p);
            return IssuerTransaction::run($this->db,$this->p,$calendar->tables(),function()use($calendar,$issuer,$actor,$revision,$settings){
                $this->authorize($issuer,$actor,'edit');(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);
                $calendar->saveInOwnedTransaction($issuer,$actor,$revision,$settings);
                $saved=$calendar->valuesInOwnedTransaction($issuer,$actor);return['values'=>$saved['values'],'revision'=>$saved['revision'],'assets'=>null,'custom_fields'=>[]];
            });
        }
        if($group==='email'){if($uploads!==[]||$custom!==[]||$encrypt===null||$decrypt===null)throw new DomainException('native_settings_invalid');return(new NativeCompanyMailSettings($this->db,$this->p))->save($issuer,$actor,$revision,$settings,$encrypt,$decrypt)+['assets'=>null,'custom_fields'=>[]];}
        if(!preg_match('/^[a-f0-9]{64}$/D',$revision)||array_diff(array_keys($settings),self::fields($group)))throw new DomainException('native_settings_invalid');
        if($uploads!==[]&&($group!=='general'||array_diff(array_keys($uploads),['logo','logo_dark','favicon','signature'])))throw new DomainException('native_settings_invalid');
        if($group!=='company'&&$custom!==[])throw new DomainException('native_settings_invalid');
        foreach($settings as$name=>$value){
            if($group==='sales_general'&&$name==='default_tax'){if(!is_array($value))throw new DomainException('native_settings_invalid');continue;}
            if($name==='enabled_languages'){if($value!==['portuguese_br'])throw new DomainException('native_settings_invalid');continue;}
            if(!is_string($value)||strlen($value)>10000||preg_match('//u',$value)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$value))throw new DomainException('native_settings_invalid');
        }
        if($group==='general')self::general($settings);
        if($group==='invoices')NativeCompanyInvoicePreferences::validate($settings);
        if($group==='tickets')NativeCompanyTicketPreferences::prepare($settings);
        if($group==='sales_general'){if(array_diff(array_diff(self::fields($group),['default_tax']),array_keys($settings)))throw new DomainException('native_settings_invalid');self::sales($settings,false);$settings['default_tax']=$settings['default_tax']??[];}
        if($group==='localization'){
            if(array_diff(self::fields($group),array_keys($settings)))throw new DomainException('native_settings_invalid');
            CompanySettingsFormats::validate(array_intersect_key($settings,array_flip(['dateformat','time_format'])));
            if(!in_array($settings['default_timezone'],DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC),true)||$settings['active_language']!=='portuguese_br'||$settings['disable_language']!=='1'||$settings['localization_settings']!=='true')throw new DomainException('native_settings_invalid');
            $settings['enabled_languages']=serialize($settings['enabled_languages']);unset($settings['localization_settings']);
        }
        return IssuerTransaction::run($this->db,$this->p,$this->tables($group),function()use($issuer,$actor,$group,$settings,$custom,$revision,$uploads){
            $this->authorize($issuer,$actor,'edit');(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$current=$this->capture($issuer,$actor,$group);
            if(!hash_equals($current['revision'],$revision))throw new DomainException('native_settings_stale');
            $values=$settings;
            if($group==='tickets'){(new NativeCompanyTicketPreferences($this->db,$this->p))->saveInOwnedTransaction($issuer,$actor,$current['ticket_policy']['revision'],$values);return$this->capture($issuer,$actor,$group);}
            if($group==='invoices'){(new NativeCompanyInvoiceSequencePreferences($this->db,$this->p))->saveInOwnedTransaction($issuer,$actor,$values['next_invoice_number'],$current['sequence']['revision']);unset($values['next_invoice_number']);}
            if($group==='sales_general'){
                require_once __DIR__.'/NativeCompanyCatalogUi.php';
                $values['default_tax']=serialize((new NativeCompanyCatalogUi($this->db,$this->p,$issuer,$actor))->validateTaxSelections($values['default_tax']));
            }
            if(in_array($group,['company','general'],true)){
                $identity=[];$profile=[];
                foreach($values as$name=>$value){
                    if(isset(CompanySettingsIdentity::ALIASES[$name])){$key=CompanySettingsIdentity::ALIASES[$name];if(isset($identity[$key])&&$identity[$key]!==$value)throw new DomainException('native_settings_invalid');$identity[$key]=$value;unset($values[$name]);}
                    elseif(in_array($name,CompanySettingsIdentity::PROFILE_OPTIONS,true)){$profile[$name]=$value;unset($values[$name]);}
                }
                if($profile!==[])$identity['profile']=$profile;
                if($identity!==[])(new CompanySettingsIdentity($this->db,$this->p))->saveInOwnedTransaction($issuer,$actor,$identity);
                if($group==='company')(new NativeCompanyCustomFields($this->db,$this->p))->saveInOwnedTransaction($issuer,$actor,'company',$issuer,$custom);
            }
            (new CompanySettingsStorage($this->db,$this->p))->updateNativeSettingsInOwnedTransaction($issuer,$actor,$group,$values);
            if($uploads!==[])(new CompanyAssets($this->db,$this->p))->saveBatchInOwnedTransaction($issuer,$actor,$uploads,$current['assets']['revision']);
            $this->authorize($issuer,$actor,'edit');return$this->capture($issuer,$actor,$group);
        });
    }
    private static function isPolicyGroup(string $group):bool
    {
        return in_array($group,['task_timer_stop','task_timers','task_operations','task_defaults','ticket_preferences','pdf_typography','project_files'],true);
    }
    private function policyService(string $group):object
    {
        return match($group){
            'task_timer_stop'=>new CompanyTaskTimerStopPreferences($this->db,$this->p),
            'task_timers'=>new CompanyTaskTimerPreferences($this->db,$this->p),
            'task_operations'=>new CompanyTaskOperationPreferences($this->db,$this->p),
            'task_defaults'=>new CompanyTaskPreferences($this->db,$this->p),
            'ticket_preferences'=>new CompanyTicketPreferences($this->db,$this->p),
            'pdf_typography'=>new CompanyPdfTypography($this->db,$this->p),
            'project_files'=>new CompanyProjectFileSettings($this->db,$this->p),
            default=>throw new DomainException('native_settings_group_unavailable'),
        };
    }
    private function policyPage(int $issuer,int $actor,string $group):array
    {
        $page=$this->policyService($group)->page($issuer,$actor);$values=[];
        foreach(self::fields($group)as$field)$values[$field]=$page[$field];
        return['values'=>$values,'revision'=>(string)$page['revision'],'assets'=>null,'custom_fields'=>[]];
    }
    private static function sales(array $values,bool $stored):void
    {
        foreach($values as$key=>$value){
            if($key==='default_tax'){
                if($stored){set_error_handler(static function(){throw new DomainException('native_settings_invalid');});try{$decoded=unserialize($value,['allowed_classes'=>false]);}finally{restore_error_handler();}if(!is_array($decoded)||!array_is_list($decoded)||serialize($decoded)!==$value)throw new DomainException('native_settings_invalid');foreach($decoded as$item)if(!is_string($item)||strlen($item)>220)throw new DomainException('native_settings_invalid');}
                elseif(!is_array($value)||!array_is_list($value))throw new DomainException('native_settings_invalid');
            }elseif($key==='decimal_separator'){if(!in_array($value,[',','.'],true))throw new DomainException('native_settings_invalid');}
            elseif($key==='thousand_separator'){if(!in_array($value,[',','.',"'",'',' '],true))throw new DomainException('native_settings_invalid');}
            elseif($key==='number_padding_prefixes'){if(!preg_match('/^(?:0|[1-9][0-9]?)$/D',$value))throw new DomainException('native_settings_invalid');}
            elseif(!in_array($value,['0','1'],true))throw new DomainException('native_settings_invalid');
        }
        if(isset($values['decimal_separator'],$values['thousand_separator'])&&$values['decimal_separator']===$values['thousand_separator'])throw new DomainException('native_settings_invalid');
    }
    private static function general(array $values):void
    {
        foreach(['rtl_support_admin','rtl_support_client']as$key)if(isset($values[$key])&&!in_array($values[$key],['0','1'],true))throw new DomainException('native_settings_invalid');
        if(isset($values['allowed_files']))NativeCompanyUploadPreferences::prepare(['allowed_files'=>$values['allowed_files']]);
        if(isset($values['main_domain'])&&$values['main_domain']!==''){
            $url=$values['main_domain'];$parts=parse_url(str_contains($url,'://')?$url:'https://'.$url);
            if(!is_array($parts)||!in_array($parts['scheme']??'', ['http','https'],true)||empty($parts['host'])||isset($parts['user'])||isset($parts['pass'])||preg_match('/[\x00-\x20\x7f]/',$url))throw new DomainException('native_settings_invalid');
        }
    }
    /** Narrow validation for the storage-owned transaction, not a generic principal batch bypass. */
    public static function assertStorage(string $group,array $values):void
    {
        if($group==='pdf'){if(array_diff(array_keys($values),array_merge(NativeCompanyPdfSettings::FORMATS,array_keys(CompanyPdfLayout::DEFAULTS))))throw new DomainException('native_settings_invalid');NativeCompanyPdfSettings::validate($values);return;}
        if(in_array($group,['estimates','proposals'],true)){NativeCompanySalesSettings::validate($group,$values,false);return;}
        if($group==='misc'){NativeCompanyMiscSettings::validateExtras($values);return;}
        if($group==='tasks'){NativeCompanyTaskSettings::validateExtras($values);return;}
        $allowed=$group==='invoices'?array_values(array_diff(self::fields($group),['next_invoice_number'])):($group==='company'?['company_info_format']:($group==='general'?['main_domain','rtl_support_admin','rtl_support_client','allowed_files']:array_values(array_diff(self::fields($group),['localization_settings']))));
        if(array_diff(array_keys($values),$allowed))throw new DomainException('native_settings_invalid');
        foreach($values as$v)if(!is_string($v)||strlen($v)>10000||preg_match('//u',$v)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$v))throw new DomainException('native_settings_invalid');
        if($group==='general')self::general($values);
        if($group==='invoices')NativeCompanyInvoicePreferences::validate($values,false);
        if($group==='sales_general')self::sales($values,true);
        if($group==='localization'){
            if(array_diff($allowed,array_keys($values)))throw new DomainException('native_settings_invalid');
            CompanySettingsFormats::validate(array_intersect_key($values,array_flip(['dateformat','time_format'])));
            if(!in_array($values['default_timezone'],DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC),true)||$values['active_language']!=='portuguese_br'||$values['disable_language']!=='1'||$values['enabled_languages']!==serialize(['portuguese_br']))throw new DomainException('native_settings_invalid');
        }
    }
}
