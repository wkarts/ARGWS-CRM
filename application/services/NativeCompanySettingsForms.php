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
/** Writers for existing Settings forms. No new URL, UI, inferred issuer or global option fallback. */
final class NativeCompanySettingsForms
{
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    /** Groups whose existing Settings partial has a complete owned read/write adapter. */
    public static function availableGroups():array
    {
        return ['general','company','localization','email','sales_general','invoices','clients','tasks','tickets','google','calendar','misc'];
    }
    public function authorize(int $issuer,int $actor,string $cap):void
    {
        if(!in_array($cap,['view','edit'],true)||!(new IssuerResourceAccess($this->db,$this->p))->can($issuer,$actor,'settings',$cap))throw new DomainException('native_settings_denied');
    }
    public static function fields(string $group):array
    {
        return match($group){
            'invoices'=>NativeCompanyInvoicePreferences::fields(),
            'tickets'=>NativeCompanyTicketPreferences::fields(),
            'tasks'=>NativeCompanyTaskSettings::fields(),
            'misc'=>NativeCompanyMiscSettings::fields(),
            'clients'=>NativeCompanyClientsSettings::fields(),
            'email'=>NativeCompanyMailSettings::fields(),
            'calendar'=>array_keys(NativeCompanyCalendarPreferences::defaults()),
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
        $ticketPolicy=$group==='tickets'?(new NativeCompanyTicketPreferences($this->db,$this->p))->readInOwnedTransaction($issuer,$actor):null;
        if($ticketPolicy!==null)$values=$ticketPolicy['values'];
        $sequence=$group==='invoices'?(new NativeCompanyInvoiceSequencePreferences($this->db,$this->p))->readInOwnedTransaction($issuer,$actor):null;
        if($sequence!==null)$values['next_invoice_number']=$sequence['next_number'];
        $custom=$group==='company'?(new NativeCompanyCustomFields($this->db,$this->p))->storedInput($issuer,$actor,'company',$issuer):[];
        $definitions=$group==='company'?(new NativeCompanyCustomFields($this->db,$this->p))->definitionsForResource($issuer,$actor,'company',$issuer):[];
        $assets=$group==='general'?(new CompanyAssets($this->db,$this->p))->view($issuer,$actor):null;
        return ['ticket_policy'=>$ticketPolicy,'sequence'=>$sequence,'values'=>$values,'custom_