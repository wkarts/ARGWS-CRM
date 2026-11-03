<?php
declare(strict_types=1);
require_once __DIR__.'/CompanySettingsStorage.php';
require_once __DIR__.'/CompanyTicketPreferences.php';
require_once __DIR__.'/NativeCompanyTicketCatalog.php';
require_once __DIR__.'/NativeCompanyUploadPreferences.php';
/** Native option names and public factory defaults; no plugin/provider/global option fallback. */
final class NativeCompanyTicketPreferences
{
    public const DEFAULTS=[
        'services'=>'1','disable_ticket_public_url'=>'0','staff_access_only_assigned_departments'=>'1',
        'staff_related_ticket_notification_to_assignee_only'=>'0','receive_notification_on_new_ticket'=>'1',
        'receive_notification_on_new_ticket_replies'=>'1','staff_members_open_tickets_to_all_contacts'=>'1',
        'automatically_assign_ticket_to_first_staff_responding'=>'0','access_tickets_to_none_staff_members'=>'0',
        'allow_non_admin_staff_to_delete_ticket_attachments'=>'0','allow_non_admin_members_to_delete_tickets_and_replies'=>'1',
        'allow_non_admin_members_to_edit_ticket_messages'=>'0','allow_customer_to_change_ticket_status'=>'0',
        'only_show_contact_tickets'=>'1','ticket_replies_order'=>'desc','enable_support_menu_badges'=>'0',
        'default_ticket_reply_status'=>'3','maximum_allowed_ticket_attachments'=>'4',
        'ticket_attachments_file_extensions'=>'.jpg,.jpeg,.png,.pdf,.doc,.zip,.rar',
        'email_piping_only_registered'=>'0','email_piping_only_replies'=>'0','ticket_import_reply_only'=>'0',
        'email_piping_default_priority'=>'2','staff_members_create_inline_ticket_services'=>'1',
        'staff_members_save_tickets_predefined_replies'=>'1','ai_enable_ticket_reply_suggestions'=>'0','ai_enable_ticket_summarization'=>'0',
    ];
    public static function fields():array{return array_keys(self::DEFAULTS);}
    public static function defaultValue(string $field):string{return self::DEFAULTS[$field]??throw new DomainException('native_ticket_preferences_invalid');}
    public static function prepare(array $values):array
    {
        if(array_diff(array_keys($values),self::fields()))throw new DomainException('native_ticket_preferences_invalid');
        foreach($values as$key=>$value){
            if(!is_string($value))throw new DomainException('native_ticket_preferences_invalid');
            if($key==='ticket_replies_order'){if(!in_array($value,['asc','desc'],true))throw new DomainException('native_ticket_preferences_invalid');}
            elseif($key==='ticket_attachments_file_extensions')NativeCompanyUploadPreferences::prepare(['allowed_files'=>$value]);
            elseif($key==='maximum_allowed_ticket_attachments'){if(!preg_match('/^(?:0|[1-9][0-9]{0,9})$/D',$value)||(float)$value>2147483647)throw new DomainException('native_ticket_preferences_invalid');}
            elseif(in_array($key,['default_ticket_reply_status','email_piping_default_priority'],true))NativeCompanyResourceId::decode($key==='default_ticket_reply_status'?'ticket_status':'ticket_priority',$value);
            elseif(!in_array($value,['0','1'],true))throw new DomainException('native_ticket_preferences_invalid');
        }return$values;
    }
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public function tables():array
    {
        return array_merge((new CompanyTicketPreferences($this->db,$this->p))->tables(),['options',NativeCompanyTicketCatalogSchema::CATALOG,'tickets_status','tickets_priorities']);
    }
    private function access(int $issuer,int $actor,bool $write=false):IssuerResourceAccess
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if(!$acl->member($issuer,$actor)||($write?!$acl->can($issuer,$actor,'settings','edit'):!($acl->can($issuer,$actor,'settings','view')||$acl->can($issuer,$actor,'tickets','view')||$acl->administrative($issuer,$actor))))throw new DomainException('native_ticket_preferences_denied');
        return$acl;
    }
    public function read(int $issuer,int $actor):array
    {
        $this->access($issuer,$actor);return IssuerTransaction::run($this->db,$this->p,$this->tables(),fn()=>$this->readInOwnedTransaction($issuer,$actor));
    }
    public function readInOwnedTransaction(int $issuer,int $actor):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$acl=$this->access($issuer,$actor);$primary=$acl->primaryId();
        $store=new CompanySettingsStorage($this->db,$this->p);$values=[];$sources=[];
        foreach(self::fields()as$key){
            if($key==='ticket_replies_order')continue;
            $present=$store->exists($issuer,$key);$values[$key]=$present?$store->get($issuer,$key):self::defaultValue($key);
            $sources[$key]=$present?($issuer===$primary?'principal_legacy':'owned_option'):'public_factory_default';
            if(!$present&&$issuer!==$primary&&in_array($key,['default_ticket_reply_status','email_piping_default_priority'],true)){
                $kind=$key==='default_ticket_reply_status'?'status':'priority';$code=$kind==='status'?'answered':'medium';
                $row=IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.NativeCompanyTicketCatalogSchema::CATALOG.' WHERE issuer_id=? AND kind=? AND code=?',[$issuer,$kind,$code])->row_array();
                if(!$row)throw new DomainException('native_ticket_catalog_defaults_missing');
                $values[$key]=NativeCompanyResourceId::encode('company_ticket_'.$kind,(int)$row['id']);$sources[$key]='owned_public_catalog';
            }
        }
        $order=CompanyTicketPreferences::readInOwnedTransaction($this->db,$this->p,$issuer);
        $values['ticket_replies_order']=$order['ticket_replies_order'];$sources['ticket_replies_order']=$order['revision']>0?'owned_ticket_preferences':'owned_canonical_default';
        if($order['revision']===0&&$issuer===$primary){$values['ticket_replies_order']=$store->exists($issuer,'ticket_replies_order')?$store->get($issuer,'ticket_replies_order'):self::DEFAULTS['ticket_replies_order'];$sources['ticket_replies_order']='principal_legacy';}
        self::prepare($values);$catalog=new NativeCompanyTicketCatalog($this->db,$this->p);
        $catalog->get($issuer,$actor,'status',$values['default_ticket_reply_status']);$catalog->get($issuer,$actor,'priority',$values['email_piping_default_priority']);
        $this->access($issuer,$actor);if($acl->primaryId()!==$primary)throw new DomainException('native_ticket_preferences_denied');
        return ['values'=>$values,'sources'=>$sources,'order_revision'=>$order['revision'],'revision'=>hash('sha256',json_encode([$issuer,$primary,$values,$sources,$order],JSON_THROW_ON_ERROR)),
            'ai_available'=>false,'ai_unavailable_reason'=>'company_scoped_provider_not_implemented'];
    }
    /** Deletion fence under the same issuer lock as Settings writes. */
    public function assertCatalogNotSelected(int $issuer,string $kind,array $row):void
    {
        if(!in_array($kind,['status','priority'],true))return;
        IssuerTransaction::assertOwned($this->db,$this->p,['issuers_registry','issuers_installation_identity',NativeCompanyTicketCatalogSchema::CATALOG]);
        $store=new CompanySettingsStorage($this->db,$this->p);$key=$kind==='status'?'default_ticket_reply_status':'email_piping_default_priority';
        if($store->exists($issuer,$key))$selected=$store->get($issuer,$key);
        elseif($store->isPrimary($issuer))$selected=self::defaultValue($key);
        else{
            $default=IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.NativeCompanyTicketCatalogSchema::CATALOG.' WHERE issuer_id=? AND kind=? AND code=?',[$issuer,$kind,$kind==='status'?'answered':'medium'])->row_array();
            if(!$default)throw new DomainException('native_ticket_catalog_defaults_missing');
            $selected=NativeCompanyResourceId::encode('company_ticket_'.$kind,(int)$default['id']);
        }
        self::prepare([$key=>$selected]);if((string)$row['native_id']===$selected)throw new DomainException('native_ticket_catalog_referenced');
    }
    /** Caller supplies the Settings transaction and revision; no nested commit, provider or transport. */
    public function saveInOwnedTransaction(int $issuer,int $actor,string $revision,array $values):array
    {
        self::prepare($values);IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$acl=$this->access($issuer,$actor,true);$acl->lockFinancialIdentity($issuer,$actor);
        $before=$this->readInOwnedTransaction($issuer,$actor);if(!hash_equals($before['revision'],$revision))throw new DomainException('native_ticket_preferences_stale');
        foreach(['ai_enable_ticket_reply_suggestions','ai_enable_ticket_summarization']as$key)if(($values[$key]??'0')==='1')throw new DomainException('native_ticket_ai_provider_unavailable');
        $catalog=new NativeCompanyTicketCatalog($this->db,$this->p);foreach(['default_ticket_reply_status'=>'status','email_piping_default_priority'=>'priority']as$key=>$kind)if(isset($values[$key]))$catalog->get($issuer,$actor,$kind,$values[$key]);
        if(isset($values['ticket_replies_order'])){(new CompanyTicketPreferences($this->db,$this->p))->saveInOwnedTransaction($issuer,$actor,$before['order_revision'],['ticket_replies_order'=>$values['ticket_replies_order']]);unset($values['ticket_replies_order']);}
        if($values){$store=new CompanySettingsStorage($this->db,$this->p);if($store->isPrimary($issuer)){
            $guard=new CompanySettingsCommitGuard($this->db,$this->p,$issuer,$actor,'edit');foreach($values as$key=>$value)$guard->writeOption('update',$key,$value);$guard->audit('company_settings_batch_updated');$guard->confirm();
        }else$store->updateBatchInOwnedTransaction($issuer,$actor,$values);}
        $after=$this->readInOwnedTransaction($issuer,$actor);foreach($values as$key=>$value)if($after['values'][$key]!==$value)throw new RuntimeException('issuer_ticket_preferences_unconfirmed');$this->access($issuer,$actor,true);return$after;
    }
}
