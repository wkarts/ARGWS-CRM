<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyInvoiceRecurringStorage.php';require_once __DIR__.'/CompanyMailConfig.php';require_once __DIR__.'/CompanyEmailTemplates.php';

/** Fixed queue source; a recurrence can veto but never grant document/recipient authority. */
final class CompanyInvoiceRecurringDeliverySource extends CompanyInvoiceRecurringStorage
{
    public const HEADER='companyInvoiceOccurrence';
    public function __construct($db,string$p,private int$issuer,private int$actor,private int$scheduleId,private string$date){parent::__construct($db,$p);}
    public function marker():string{return self::runKey($this->issuer,$this->scheduleId,$this->date);}
    private static function transportHash($db,string $p,int $issuer):string
    {
        $s=new CompanySettingsStorage($db,$p);$data=[];foreach(['mail_engine','email_protocol','smtp_host','smtp_port','smtp_encryption','smtp_email','smtp_username','smtp_password','smtp_email_charset','email_signature','email_header','email_footer'] as $k)$data[$k]=$s->get($issuer,$k);
        $file=__DIR__.'/CompanyGoogleMailOAuth.php';$fingerprint=null;if(is_file($file)){require_once $file;$fingerprint=(new CompanyGoogleMailOAuth($db,$p))->fingerprint($issuer);}if($data['email_protocol']==='google'&&$fingerprint===null)throw new DomainException('company_invoice_recurring_oauth_required');$data['owned_google_account']=$fingerprint;
        $policyFile=__DIR__.'/CompanyMailTransportPolicy.php';if(is_file($policyFile)){require_once $policyFile;$policy=CompanyMailTransportPolicy::fingerprint($db,$p,$issuer);if($policy!==null)$data['owned_transport_policy']=$policy;}
        // Stable OAuth identity/epoch is above. Access-token refresh must not invalidate future cycles.
        if($data['email_protocol']==='microsoft')foreach(['microsoft_mail_client_id','microsoft_mail_client_secret','microsoft_mail_azure_tenant_id','microsoft_mail_refresh_token'] as $k)$data[$k]=$s->get($issuer,$k);
        return hash('sha256',json_encode($data,JSON_THROW_ON_ERROR));
    }
    private static function transport($db,string $p,int $issuer,int $actor,array $payload,int $contact,string $base):array
    {
        $acl=new IssuerResourceAccess($db,$p);foreach([['invoices','view'],['invoices','create'],['invoices','edit'],['customers','view']] as [$f,$c])if(!$acl->can($issuer,$actor,$f,$c))throw new DomainException('company_invoice_recurring_denied');IssuerTransaction::assertOwned($db,$p,['contacts','contact_permissions','emailtemplates','options','issuers_clients','clients','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff']);
        $rows=IssuerStorage::query($db,'SELECT c.id,c.userid,c.firstname,c.lastname,c.email,c.active FROM '.$p.'contacts c JOIN '.$p.'clients x ON x.userid=c.userid AND x.active=1 JOIN '.$p.'issuers_clients o ON o.client_id=x.userid AND o.owner_issuer_id=? WHERE c.id=? AND c.userid=? AND c.active=1 AND (SELECT COUNT(*) FROM '.$p.'contact_permissions n WHERE n.userid=c.id AND n.permission_id=1)=1 FOR UPDATE',[$issuer,$contact,$payload['client_id']])->result_array();$r=$rows[0]??null;if(!$r||!filter_var($r['email'],FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$r['email']))throw new DomainException('company_invoice_recurring_contact_denied');$template=CompanyEmailTemplates::findForIssuer($db,$p,$issuer,'invoice-send-to-client','portuguese_br');if(!$template||(int)$template->active!==1||!str_contains($template->message,'{invoice_link}'))throw new DomainException('company_invoice_delivery_template_required');$store=new CompanySettingsStorage($db,$p);$sender=$store->get($issuer,'smtp_email');if(!filter_var($sender,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$sender)||!in_array($store->get($issuer,'email_protocol'),['smtp','google','microsoft'],true))throw new DomainException('company_invoice_delivery_sender_required');return ['mode'=>'owned_invoice_notification','automatic_delivery'=>true,'contact_id'=>$contact,'recipient_hash'=>hash('sha256',json_encode($r,JSON_THROW_ON_ERROR)),'configuration_hash'=>self::transportHash($db,$p,$issuer),'template_hash'=>hash('sha256',json_encode((array)$template,JSON_THROW_ON_ERROR)),'base_url'=>$base];
    }
    public static function captureInOwned($db,string $p,int $issuer,int $actor,array $payload,int $contact,string $base,callable $decrypt):array
    {
        CompanyMailConfig::build($db,$p,$issuer,$decrypt,null,'document_notifications');return self::transport($db,$p,$issuer,$actor,$payload,$contact,$base);
    }
    public static function assertFrozenInOwned($db,string $p,int $issuer,int $actor,array $payload,array $frozen):void
    {
        $now=self::transport($db,$p,$issuer,$actor,$payload,(int)($frozen['contact_id']??0),(string)($frozen['base_url']??''));if(!hash_equals(self::hash($frozen),self::hash($now)))throw new DomainException('company_invoice_recurring_transport_changed');
    }
    public function assertFor($db,string$p,int$i,int$a,int$invoice,int$contact):void
    {
        if($db!==$this->db||$p!==$this->prefix||$i!==$this->issuer||$a!==$this->actor)throw new DomainException('company_invoice_recurring_denied');IssuerTransaction::assertOwned($db,$p,$this->tables());$r=$this->schedule($i,$this->scheduleId);$run=$this->occurrence($i,$this->scheduleId,$this->date);if(!$run||!in_array($r['state'],['active','processing','completed'],true)||!in_array($run['state'],['invoiced','queued','completed'],true)||(int)$run['actor_id']!==$a||(int)$run['invoice_id']!==$invoice||$run['delivery_key']!==self::deliveryKey($i,$this->scheduleId,$this->date))throw new DomainException('company_invoice_recurring_paused');$config=$this->validate($i,$a,$r,$run,false);if(!$config['send']||$config['contact_id']!==$contact)throw new DomainException('company_invoice_recurring_contact_denied');
        $proof=$this->rows('SELECT invoice_id,actor_id FROM '.$p.'company_invoice_requests WHERE issuer_id=? AND request_key=? FOR UPDATE',[$i,self::runKey($i,$this->scheduleId,$this->date)])[0]??null;if(!$proof||(int)$proof['invoice_id']!==$invoice||(int)$proof['actor_id']!==$a||!hash_equals($run['invoice_revision'],(new CompanyInvoices($db,$p))->revision($i,$a,$invoice)))throw new DomainException('company_invoice_stale');
    }
    public static function assertQueueRouting($db,string$p,object$email,array$headers):void
    {
        $present=[];foreach(array_keys(CompanyInvoiceRecurringSchema::definitions())as$t)$present[]=$db->table_exists($p.$t);if(!in_array(true,$present,true)){if(array_key_exists(self::HEADER,$headers))throw new DomainException('company_invoice_recurring_denied');return;}CompanyInvoiceRecurringSchema::assertValid($db,$p);$source=new self($db,$p,0,0,0,'2000-01-01');
        IssuerTransaction::run($db,$p,$source->tables(),function()use($db,$p,$email,$headers){$rows=IssuerStorage::query($db,'SELECT r.issuer_id,r.actor_id,r.schedule_id,r.run_date,r.invoice_id,r.delivery_key,d.contact_id FROM '.$p.'company_invoice_schedule_runs r JOIN '.$p.'company_invoice_deliveries d ON d.issuer_id=r.issuer_id AND d.invoice_id=r.invoice_id AND d.request_key=r.delivery_key WHERE d.queue_id=? FOR UPDATE',[$email->id])->result_array();if(!$rows){if(array_key_exists(self::HEADER,$headers))throw new DomainException('company_invoice_recurring_denied');return;}if(count($rows)!==1)throw new DomainException('company_invoice_recurring_denied');$r=$rows[0];$source=new self($db,$p,(int)$r['issuer_id'],(int)$r['actor_id'],(int)$r['schedule_id'],$r['run_date']);if((int)($email->issuer_id??0)!==(int)$r['issuer_id']||!is_string($headers['companyInvoiceDelivery']??null)||!hash_equals($r['delivery_key'],$headers['companyInvoiceDelivery'])||!is_string($headers[self::HEADER]??null)||!hash_equals($source->marker(),$headers[self::HEADER]))throw new DomainException('company_invoice_recurring_denied');$source->assertFor($db,$p,(int)$r['issuer_id'],(int)$r['actor_id'],(int)$r['invoice_id'],(int)$r['contact_id']);});
    }
    public static function assertDispatchIfOwned($db,string$p,object$email,string$requestKey,?string$marker=null):void
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$requestKey))throw new DomainException('company_invoice_recurring_denied');$headers=@unserialize($email->headers,['allowed_classes'=>false]);if(!is_array($headers)||($headers['companyInvoiceDelivery']??null)!==$requestKey||$marker!==null&&($headers[self::HEADER]??null)!==$marker)throw new DomainException('company_invoice_recurring_denied');self::assertQueueRouting($db,$p,$email,$headers);
    }
}
