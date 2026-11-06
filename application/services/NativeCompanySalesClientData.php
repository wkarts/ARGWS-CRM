<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyClients.php';
require_once __DIR__.'/NativeCompanySalesCatalogLookup.php';
require_once __DIR__.'/CompanyNativeContacts.php';

/** Original customer-selection fields under sales/customer authority, without invoice/task/expense grants. */
final class NativeCompanySalesClientData
{
    public function __construct(private $db,private string $p){}
    public function read(int $issuer,int $actor,string $kind,$customer,string $document='',string $revision=''):array
    {
        $lookup=new NativeCompanySalesCatalogLookup($this->db,$this->p,$issuer,$actor,$kind,$document,$revision);$client=IssuerPolicy::id($customer);
        $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($issuer,$actor,'customers','view'))throw new DomainException('company_client_access_denied');
        $clients=new NativeCompanyClients($this->db,$this->p,$issuer,$actor);$details=$clients->details($client);if(!$details)throw new DomainException('company_client_access_denied');
        $owned=isset($details['native_profile']);$profile=$owned?$details['native_profile']:[];$public=$details['profile']??[];
        // A shared client does not expose its owner's private address/contact. The form retains manual entry.
        $billing=[];foreach(['billing','shipping']as$part)foreach(['street','city','state','zip','country']as$field)$billing[$part.'_'.$field]=$profile[$part.'_'.$field]??($field==='country'?0:'');
        $currency=(int)($profile['default_currency']??0);
        if($currency!==0&&count(IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.'currencies WHERE id=?',[$currency])->result_array())!==1)throw new DomainException('native_sales_editor_changed');
        $project=false;if($acl->can($issuer,$actor,'projects','view')&&$this->db->table_exists($this->p.'company_projects'))$project=(bool)IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.'company_projects WHERE issuer_id=? AND client_id=? LIMIT 1',[$issuer,$client])->row_array();
        $email='';$contactId=null;$contact=null;
        if($owned){
            $primary=IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.'contacts WHERE userid=? AND is_primary=1 ORDER BY id LIMIT 2',[$client])->result_array();
            if(count($primary)>1)throw new DomainException('company_contact_invalid_primary');
            if($primary){$contactId=(int)$primary[0]['id'];$contact=(new CompanyNativeContacts($this->db,$this->p))->get($issuer,$actor,$client,$contactId);$email=(string)$contact['contact']['email'];}
        }
        if($clients->details($client)!==$details||($contact!==null&&(new CompanyNativeContacts($this->db,$this->p))->get($issuer,$actor,$client,$contactId)!==$contact))throw new DomainException('native_sales_editor_changed');
        $lookup->assertCurrent();if(!$acl->can($issuer,$actor,'customers','view'))throw new DomainException('company_client_access_denied');
        return['billing_shipping'=>[$billing],'client_currency'=>$currency,'customer_has_projects'=>$project,'billable_tasks'=>[],'merge_info'=>'','expenses_bill_info'=>'','profile_available'=>$owned,
            'to'=>(string)$details['client']['company'],'company'=>(string)$details['client']['company'],'is_using_company'=>true,
            'address'=>(string)($public['address']??''),'city'=>(string)($public['city']??''),'state'=>(string)($public['state']??''),'zip'=>(string)($public['zip']??''),
            'country'=>(int)($profile['country']??0),'phone'=>(string)($public['phonenumber']??''),'email'=>$email,'currency'=>$currency];
    }
}
