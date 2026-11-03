<?php
declare(strict_types=1);
require_once __DIR__.'/CompanySettingsStorage.php';
require_once __DIR__.'/CompanySettingsCatalog.php';
/** The existing GDPR form contract, persisted through explicit issuer ownership. */
final class NativeCompanyGdprSettings
{
    private const PAGES = ['general'=>['enable_gdpr','show_gdpr_in_customers_menu','show_gdpr_link_in_footer','gdpr_page_top_information_block'],'portability'=>['gdpr_data_portability_contacts','gdpr_data_portability_leads','gdpr_contact_data_portability_allowed','gdpr_lead_data_portability_allowed'],'forgotten'=>['gdpr_contact_enable_right_to_be_forgotten','gdpr_on_forgotten_remove_invoices_credit_notes','gdpr_on_forgotten_remove_estimates','gdpr_lead_enable_right_to_be_forgotten','gdpr_after_lead_converted_delete'],'informed'=>['gdpr_enable_terms_and_conditions','gdpr_enable_terms_and_conditions_lead_form','gdpr_enable_terms_and_conditions_ticket_form','gdpr_show_terms_and_conditions_in_footer','gdpr_enable_terms_and_conditions_estimate_request_form','terms_and_conditions','privacy_policy'],'rectification'=>['allow_primary_contact_to_view_edit_billing_and_shipping','allow_contact_to_delete_files','gdpr_enable_lead_public_form','gdpr_show_lead_custom_fields_on_public_form','gdpr_lead_attachments_on_public_form'],'consent'=>['gdpr_enable_consent_for_contacts','gdpr_enable_consent_for_leads','gdpr_consent_public_page_top_block']];
    private const ARRAYS = ['gdpr_contact_data_portability_allowed'=>['profile_data','consent','tickets','customer_profile_data','profile_notes','contacts','invoices','invoices_notes','invoices_activity_log','estimates','estimates_notes','estimates_activity_log','projects','related_tasks','related_discussions','projects_activity_log','credit_notes','proposals','subscriptions','expenses','contracts'],'gdpr_lead_data_portability_allowed'=>['profile_data','custom_fields','notes','activity_log','proposals','integration_emails','consent']];
    private const HTML = ['terms_and_conditions','privacy_policy','gdpr_consent_public_page_top_block','gdpr_page_top_information_block'];
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public static function pages():array{return array_keys(self::PAGES);}
    public static function fields(string $page):array{return self::PAGES[$page]??throw new DomainException('native_gdpr_invalid');}
    public static function prepare(string $page,array $input):array
    {
        $fields=self::fields($page);if(array_diff(array_keys($input),$fields))throw new DomainException('native_gdpr_invalid');
        $out=[];
        foreach($input as $key=>$value){
            if(isset(self::ARRAYS[$key])){
                if(!is_array($value)||!array_is_list($value)||count($value)>count(self::ARRAYS[$key]))throw new DomainException('native_gdpr_invalid');
                foreach($value as$item)if(!is_string($item)||!in_array($item,self::ARRAYS[$key],true))throw new DomainException('native_gdpr_invalid');
                if(count(array_unique($value))!==count($value))throw new DomainException('native_gdpr_invalid');$out[$key]=serialize($value);
            }elseif(in_array($key,self::HTML,true)){
                if(!is_string($value)||strlen($value)>65535||preg_match('//u',$value)!==1)throw new DomainException('native_gdpr_invalid');
                $clean=CompanySettingsCatalog::htmlFragment($value);if(!is_string($clean)||strlen($clean)>65535)throw new DomainException('native_gdpr_invalid');$out[$key]=$clean;
            }else{if(!in_array($value,[0,1,'0','1'],true))throw new DomainException('native_gdpr_invalid');$out[$key]=(string)$value;}
        }
        if($page==='portability')foreach(self::ARRAYS as$key=>$unused)if(!array_key_exists($key,$out))$out[$key]=serialize([]);
        return$out;
    }
    public function authorize(int $issuer,int $actor):void
    {
        $access=new IssuerResourceAccess($this->db,$this->p);
        if(!$access->administrative($issuer,$actor)||!$access->can($issuer,$actor,'settings','view'))throw new DomainException('native_gdpr_denied');
    }
    public function save(int $issuer,int $actor,string $page,array $input):int
    {
        $this->authorize($issuer,$actor);$values=self::prepare($page,$input);
        $tables=['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','issuers_audit','staff'];
        if((new IssuerResourceAccess($this->db,$this->p))->primaryId()===$issuer)$tables[]='options';
        return IssuerTransaction::run($this->db,$this->p,$tables,function()use($issuer,$actor,$page,$values){
            (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor);
            $count=(new CompanySettingsStorage($this->db,$this->p))->updateNativeGdprInOwnedTransaction($issuer,$actor,$page,$values);
            $this->authorize($issuer,$actor);return$count;
        });
    }
    /** Already prepared wire values, revalidated without double purifying HTML or unserialize objects. */
    public static function assertPrepared(string $page,array $values):void
    {
        if(array_diff(array_keys($values),self::fields($page)))throw new DomainException('native_gdpr_invalid');
        foreach($values as$key=>$value){if(!is_string($value))throw new DomainException('native_gdpr_invalid');
            if(isset(self::ARRAYS[$key])){set_error_handler(static function(){throw new DomainException('native_gdpr_invalid');});try{$decoded=unserialize($value,['allowed_classes'=>false]);}finally{restore_error_handler();}if(!is_array($decoded)||serialize($decoded)!==$value)throw new DomainException('native_gdpr_invalid');self::prepare($page,[$key=>$decoded]);}
            elseif(in_array($key,self::HTML,true)){if(strlen($value)>65535||!preg_match('//u',$value)||CompanySettingsCatalog::htmlFragment($value)!==$value)throw new DomainException('native_gdpr_invalid');}
            elseif(!in_array($value,['0','1'],true))throw new DomainException('native_gdpr_invalid');
        }
    }
}
