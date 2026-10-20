<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyExpenseRecurringStorage.php';require_once __DIR__.'/CompanyMailConfig.php';require_once __DIR__.'/CompanyEmailTemplates.php';

/** Final persisted-source veto for an explicitly opted-in recurring expense notification. */
final class CompanyExpenseRecurringDeliverySource extends CompanyExpenseRecurringStorage
{
    public const HEADER='companyExpenseOccurrence';
    public function __construct($db,string $prefix,private int $issuer,private int $actor,private int $scheduleId,private string $date){parent::__construct($db,$prefix);}
    public function marker():string{return self::runKey($this->issuer,$this->scheduleId,$this->date);}
    private static function transportHash($db,string $p,int $issuer):string
    {
        $s=new CompanySettingsStorage($db,$p);$data=[];foreach(['mail_engine','email_protocol','smtp_host','smtp_port','smtp_encryption','smtp_email','smtp_username','smtp_password','smtp_email_charset','email_signature','email_header','email_footer'] as $k)$data[$k]=$s->get($issuer,$k);
        $file=__DIR__.'/CompanyGoogleMailOAuth.php';$fingerprint=null;if(is_file($file)){require_once $file;$fingerprint=(new CompanyGoogleMailOAuth($db,$p))->fingerprint($issuer);}if($data['email_protocol']==='google'&&$fingerprint===null)throw new DomainException('company_expense_recurring_oauth_required');$data['owned_google_account']=$fingerprint;
        $policyFile=__DIR__.'/CompanyMailTransportPolicy.php';if(is_file($policyFile)){require_once $policyFile;$policy=CompanyMailTransportPolicy::fingerprint($db,$p,$issuer);if($policy!==null)$data['owned_transport_policy']=$policy;}
        // Stable OAuth identity/epoch is above. Access-token refresh must not invalidate future cycles.
        if($data['email_protocol']==='microsoft')foreach(['microsoft_mail_client_id','microsoft_mail_client_secret','microsoft_mail_azure_tenant_id','microsoft_mail_refresh_token'] as $k)$data[$k]=$s->get($issuer,$k);
        return hash('sha256',json_encode($data,JSON_THROW_ON_ERROR));
    }
    private static function transport($db,string $p,int $issuer,int $actor,array $expense,int $contact,string $base):array
    {
        $acl=new IssuerResourceAccess($db,$p);foreach([['expenses','view'],['expenses','create'],['expenses','edit'],['invoices','view'],['invoices','create'],['invoices','edit'],['customers','view']] as [$f,$c])if(!$acl->can($issuer,$actor,$f,$c))throw new DomainException('company_expense_recurring_denied');IssuerTransaction::assertOwned($db,$p,['contacts','contact_permissions','emailtemplates','options','issuers_clients','clients','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff']);
        $rows=IssuerStorage::query($db,'SELECT c.id,c.userid,c.firstname,c.lastname,c.email,c.active FROM '.$p.'contacts c JOIN '.$p.'clients x ON x.userid=c.userid AND x.active=1 JOIN '.$p.'issuers_clients o ON o.client_id=x.userid AND o.owner_issuer_id=? WHERE c.id=? AND c.userid=? AND c.active=1 AND (SELECT COUNT(*) FROM '.$p.'contact_permissions n WHERE n.userid=c.id AND n.permission_id=1)=1 FOR UPDATE',[$issuer,$contact,$expense['client_id']])->result_array();$r=$rows[0]??null;if(!$r||!filter_var($r['email'],FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$r['email']))throw new DomainException('company_expense_recurring_contact_denied');$template=CompanyEmailTemplates::findForIssuer($db,$p,$issuer,'invoice-send-to-client','portuguese_br');if(!$template||(int)$template->active!==1||!str_contains($template->message,'{invoice_link}'))throw new DomainException('company_invoice_delivery_template_required');$store=new CompanySettingsStorage($db,$p);$sender=$store->get($issuer,'smtp_email');if(!filter_var($sender,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$sender)||!in_array($store->get($issuer,'email_protocol'),['smtp','google','microsoft'],true))throw new DomainException('company_invoice_delivery_sender_required');return ['mode'=>'owned_invoice_notification','automatic_delivery'=>true,'contact_id'=>$contact,'recipient_hash'=>hash('sha256',json_encode($r,JSON_THROW_ON_ERROR)),'configuration_hash'=>self::transportHash($db,$p,$issuer),'template_hash'=>hash('sha256',json_encode((array)$template,JSON_THROW_ON_ERROR)),'base_url'=>$base];
    }
    public static function captureInOwned($db,string $p,int $issuer,int $actor,array $expense,int $contact,string $base,callable $decrypt):array
    {
        CompanyMailConfig::build($db,$p,$issuer,$decrypt,null,'document_notifications');return self::transport($db,$p,$issuer,$actor,$expense,$contact,$base);
    }
    public static function assertFrozenInOwned($db,string $p,int $issuer,int $actor,array $expense,array $frozen):void
    {
        $now=self::transport($db,$p,$issuer,$actor,$expense,(int)($frozen['contact_id']??0),(string)($frozen['base_url']??''));if(!hash_equals(self::hash($frozen),self::hash($now)))throw new DomainException('company_expense_recurring_transport_changed');
    }
    public function assertFor($db,string $prefix,int $issuer,int $actor,int $invoice,int $contact):void
    {
        if($db!==$this->db||$prefix!==$this->prefix||$issuer!==$this->issuer||$actor!==$this->actor)throw new DomainException('company_expense_recurring_denied');IssuerTransaction::assertOwned($db,$prefix,$this->tables());CompanyExpenseRecurringSchema::assertValid($db,$prefix);$r=$this->schedule($issuer,$this->scheduleId);$run=$this->occurrence($issuer,$this->scheduleId,$this->date);if(!$run||!in_array($r['state'],['active','processing','completed'],true)||!in_array($run['state'],['invoiced','queued','completed'],true)||(int)$run['actor_id']!==$actor||(int)$run['invoice_id']!==$invoice||$run['delivery_key']!==self::notificationKey($issuer,$this->scheduleId,$this->date))throw new DomainException('company_expense_recurring_paused');$f=$this->validate($issuer,$actor,$r,$run,false);if(!$f['config']['send']||(int)$f['config']['contact_id']!==$contact)throw new DomainException('company_expense_recurring_contact_denied');
        $invoiceRow=$this->rows('SELECT i.id,i.issuer_id,i.clientid FROM '.$prefix.'invoices i JOIN '.$prefix.'company_invoice_requests q ON q.invoice_id=i.id AND q.issuer_id=i.issuer_id WHERE i.id=? AND i.issuer_id=? AND q.request_key=? AND q.actor_id=? FOR UPDATE',[$invoice,$issuer,self::canonicalKey($issuer,(int)$run['expense_id']),$actor])[0]??null;if(!$invoiceRow||(int)$invoiceRow['clientid']!==(int)$this->decode($r['source_json'])['client_id']||!hash_equals($run['invoice_revision'],(new CompanyInvoices($db,$prefix))->revision($issuer,$actor,$invoice)))throw new DomainException('company_invoice_stale');
    }
    /** A persisted occurrence cannot become legacy mail by deleting its routing headers. */
    public static function assertQueueRouting($db,string $p,object $email,array $headers):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new DomainException('company_expense_recurring_denied');
        $exists=[];foreach(array_keys(CompanyExpenseRecurringSchema::definitions()) as $t)$exists[]=$db->table_exists($p.$t);
        if(!in_array(true,$exists,true)){if(array_key_exists(self::HEADER,$headers))throw new DomainException('company_expense_recurring_denied');return;}
        CompanyExpenseRecurringSchema::assertValid($db,$p);
        $source=new self($db,$p,0,0,0,'2000-01-01');
        IssuerTransaction::run($db,$p,$source->tables(),function()use($db,$p,$email,$headers){
            $rows=IssuerStorage::query($db,'SELECT r.issuer_id,r.actor_id,r.schedule_id,r.run_date,r.invoice_id,d.contact_id,d.request_key FROM '.$p.'company_expense_occurrences r JOIN '.$p.'company_invoice_deliveries d ON d.invoice_id=r.invoice_id AND d.issuer_id=r.issuer_id AND d.request_key=r.delivery_key WHERE d.queue_id=? FOR UPDATE',[$email->id])->result_array();
            if(!$rows){if(array_key_exists(self::HEADER,$headers))throw new DomainException('company_expense_recurring_denied');return;}
            if(count($rows)!==1)throw new DomainException('company_expense_recurring_denied');$r=$rows[0];
            $source=new self($db,$p,(int)$r['issuer_id'],(int)$r['actor_id'],(int)$r['schedule_id'],$r['run_date']);
            if((int)($email->issuer_id??0)!==(int)$r['issuer_id']||!is_string($headers['companyInvoiceDelivery']??null)||!hash_equals($r['request_key'],$headers['companyInvoiceDelivery'])||!is_string($headers[self::HEADER]??null)||!hash_equals($source->marker(),$headers[self::HEADER]))throw new DomainException('company_expense_recurring_denied');
            (new IssuerResourceAccess($db,$p))->lockFinancialIdentity((int)$r['issuer_id'],(int)$r['actor_id']);
            $source->assertFor($db,$p,(int)$r['issuer_id'],(int)$r['actor_id'],(int)$r['invoice_id'],(int)$r['contact_id']);
        });
    }
    /** Called after the native invoice/OAuth guard, before SMTP, including again after an OAuth refresh. */
    public static function assertDispatchIfOwned($db,string $p,object $email,string $requestKey,?string $marker=null):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p)||!preg_match('/^[a-f0-9]{64}$/D',$requestKey))throw new DomainException('company_expense_recurring_denied');$exists=[];foreach(array_keys(CompanyExpenseRecurringSchema::definitions()) as $t)$exists[]=$db->table_exists($p.$t);if(!in_array(true,$exists,true)){if($marker!==null)throw new DomainException('company_expense_recurring_denied');return;}CompanyExpenseRecurringSchema::assertValid($db,$p);
        $source=new self($db,$p,0,0,0,'2000-01-01');IssuerTransaction::run($db,$p,$source->tables(),function()use($db,$p,$email,$requestKey,$marker){$r=IssuerStorage::query($db,'SELECT r.issuer_id,r.actor_id,r.schedule_id,r.run_date,r.invoice_id,d.contact_id FROM '.$p.'company_expense_occurrences r JOIN '.$p.'company_invoice_deliveries d ON d.invoice_id=r.invoice_id AND d.issuer_id=r.issuer_id AND d.request_key=r.delivery_key WHERE d.request_key=? AND d.queue_id=? FOR UPDATE',[$requestKey,$email->id])->row_array();if(!$r){if($marker!==null)throw new DomainException('company_expense_recurring_denied');return;}$source=new self($db,$p,(int)$r['issuer_id'],(int)$r['actor_id'],(int)$r['schedule_id'],$r['run_date']);$headers=@unserialize($email->headers,['allowed_classes'=>false]);if(!is_array($headers)||!is_string($headers[self::HEADER]??null)||!hash_equals($source->marker(),$headers[self::HEADER])||$marker!==null&&!hash_equals($source->marker(),$marker)||(int)($email->issuer_id??0)!==(int)$r['issuer_id'])throw new DomainException('company_expense_recurring_denied');(new IssuerResourceAccess($db,$p))->lockFinancialIdentity((int)$r['issuer_id'],(int)$r['actor_id']);$source->assertFor($db,$p,(int)$r['issuer_id'],(int)$r['actor_id'],(int)$r['invoice_id'],(int)$r['contact_id']);});
    }
}
