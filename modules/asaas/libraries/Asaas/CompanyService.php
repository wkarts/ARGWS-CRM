<?php

declare(strict_types=1);
namespace Asaas;
require_once APPPATH.'services/CompanyInvoiceBalance.php';
require_once __DIR__.'/CompanySchema.php';
require_once __DIR__.'/CompanyRemoteOperationsSchema.php';
require_once __DIR__.'/CompanyClient.php';
require_once __DIR__.'/CompanyCardSettlement.php';
require_once __DIR__.'/CompanyCheckoutSchema.php';
require_once __DIR__.'/HostedCardCheckout.php';
require_once __DIR__.'/WebhookSecurity.php';
require_once APPPATH.'services/issuers/IssuerTransaction.php';
require_once APPPATH.'services/issuers/IssuerResourceAccess.php';
require_once APPPATH.'services/CompanyInvoices.php';
require_once APPPATH.'services/CompanyCurrencyUnits.php';

/** Named-company adapter. Never enters the legacy gateway, global configuration or invoice map. */
final class CompanyService
{
    public function __construct(private $db,private string $p)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('asaas_company_prefix_invalid');
    }
    private function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public function module():void
    {
        $row=$this->rows('SELECT active,installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]??[];
        if((int)($row['active']??0)!==1||!preg_match('/^[0-9]\.[0-9]\.[0-9]$/D',(string)($row['installed_version']??''))||(int)str_replace('.','',$row['installed_version'])<149)throw new \DomainException('asaas_company_module_required');
    }
    public static function tables():array
    {
        return array_merge(['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','modules','issuers_audit','invoices','issuers_invoice_documents','itemable','item_tax','currencies','company_currency_units','clients','issuers_clients','invoicepaymentrecords','credits','asaas_payment_origins'],CompanySchema::tables());
    }
    private function tablesForRun():array{return array_values(array_unique(array_merge(self::tables(),(new \CompanyInvoiceBalance($this->db,$this->p))->tables(),$this->db->table_exists($this->p.'bb_origins')?['bb_origins']:[],$this->db->table_exists($this->p.'asaas_company_remote_operations')?['asaas_company_remote_operations']:[])));}
    private function transaction(callable $operation)
    {
        CompanySchema::ensure($this->db,$this->p,false);if($this->db->table_exists($this->p.'asaas_company_remote_operations'))CompanyRemoteOperationsSchema::ensure($this->db,$this->p,false);$this->module();
        return \IssuerTransaction::run($this->db,$this->p,$this->tablesForRun(),function()use($operation){$this->module();return $operation();});
    }
    public function authorize(int $issuer,int $actor,string $action):void
    {
        $this->module();$acl=new \IssuerResourceAccess($this->db,$this->p);
        if($action==='view'){if(!$acl->can($issuer,$actor,'invoices','view')&&!$acl->can($issuer,$actor,'settings','view'))throw new \DomainException('asaas_company_access_denied');return;}
        $requirements=match($action){'configure'=>[['settings','edit']],'configuration'=>[['settings','view']],
            'issue'=>[['invoices','view'],['invoices','edit'],['customers','view']],
            'refund'=>[['invoices','view'],['invoices','edit'],['payments','delete'],['settings','edit']],
            'payer'=>[['invoices','view'],['customers','view'],['customers','create']],'remote_remove'=>[['invoices','view'],['invoices','edit'],['invoices','delete'],['settings','edit']],'settle'=>[['invoices','view'],['payments','create']],default=>[['invoices','view']]};
        foreach($requirements as [$resource,$cap])if(!$acl->can($issuer,$actor,$resource,$cap))throw new \DomainException('asaas_company_access_denied');
    }
    public function checkoutModule():void
    {
        $this->module();$row=$this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]??[];
        if(version_compare($row['installed_version']??'0','1.5.1','<'))throw new \DomainException('asaas_company_checkout_module_required');CompanyCheckoutSchema::ensure($this->db,$this->p,false);
    }
    public function payerAccountInOwnedTransaction(int $issuer,int $actor,int $account):array
    {
        \IssuerTransaction::assertOwned($this->db,$this->p,$this->tablesForRun());$this->checkoutModule();$this->authorize($issuer,$actor,'payer');$row=$this->account($issuer,$account);
        if((int)$row['enabled']!==1||$row['verified_at']===null)throw new \DomainException('asaas_company_account_unverified');return $row;
    }
    public function checkoutSourceInOwnedTransaction(int $issuer,int $actor,int $origin):array
    {
        \IssuerTransaction::assertOwned($this->db,$this->p,$this->tablesForRun());$this->checkoutModule();$this->authorize($issuer,$actor,'issue');$source=$this->source($issuer,$origin);(new \CompanyInvoiceBalance($this->db,$this->p))->inOwned($issuer,$actor,(int)$source[0]['invoice_id']);return $source;
    }
    private static function opaque($value,int $limit=128):string
    {
        if(!is_string($value)||!preg_match('/^[A-Za-z0-9_-]{1,'.$limit.'}$/D',$value))throw new \DomainException('asaas_company_invalid_input');return $value;
    }
    private static function environment($value):string
    {
        if(!in_array($value,['sandbox','production'],true))throw new \DomainException('asaas_company_invalid_input');return $value;
    }
    private static function revision($value):string
    {
        if(!is_string($value)||!preg_match('/^[a-f0-9]{64}$/D',$value))throw new \DomainException('asaas_company_stale');return $value;
    }
    private function audit(int $issuer,int $actor,string $event,int $subject):void
    {
        \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,?,?)',[$actor,$event,$issuer,$subject,gmdate('Y-m-d H:i:s')]);
        $id=(int)$this->db->insert_id();if($id<1||!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND event=?',[$id,$issuer,$event]))throw new \RuntimeException('issuer_asaas_audit_unconfirmed');
    }
    private function account(int $issuer,int $account):array
    {
        $row=$this->rows('SELECT * FROM '.$this->p.'asaas_company_accounts WHERE id=? AND issuer_id=? FOR UPDATE',[$account,$issuer])[0]??null;
        if(!$row)throw new \DomainException('asaas_company_access_denied');return $row;
    }
    private static function publicAccount(array $row):array
    {
        $row['secret_configured']=$row['secret_cipher']!=='';$row['webhook_configured']=$row['token_hash']!==null;
        unset($row['secret_cipher'],$row['token_hash']);return $row;
    }
    public function accounts($issuer,$actor):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$this->authorize($issuer,$actor,'view');
        return array_map([self::class,'publicAccount'],$this->rows('SELECT * FROM '.$this->p.'asaas_company_accounts WHERE issuer_id=? ORDER BY id',[$issuer]));
    }
    public function saveAccount($issuer,$actor,string $environment,string $wallet,string $apiKey,string $revision,callable $encrypt):int
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);self::environment($environment);self::opaque($wallet);self::revision($revision);$this->authorize($issuer,$actor,'configure');
        if($apiKey===''||strlen($apiKey)>4096||preg_match('/[\x00-\x20\x7f]/',$apiKey))throw new \DomainException('asaas_company_invalid_input');
        try{$cipher=$encrypt($apiKey);}catch(\Throwable $e){throw new \DomainException('asaas_company_encryption_failed');}
        if(!is_string($cipher)||$cipher===''||hash_equals($cipher,$apiKey))throw new \DomainException('asaas_company_encryption_failed');
        return $this->transaction(function()use($issuer,$actor,$environment,$wallet,$cipher,$revision):int{
            $this->authorize($issuer,$actor,'configure');$old=$this->rows('SELECT * FROM '.$this->p.'asaas_company_accounts WHERE issuer_id=? AND environment=? FOR UPDATE',[$issuer,$environment])[0]??null;
            if($old){if(!hash_equals($old['revision'],$revision))throw new \DomainException('asaas_company_stale');if($old['account_id']!==$wallet)throw new \DomainException('asaas_company_account_immutable');
                $id=(int)$old['id'];$next=bin2hex(random_bytes(32));\IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_company_accounts SET secret_cipher=?,revision=?,verified_at=NULL WHERE id=? AND issuer_id=?',[$cipher,$next,$id,$issuer]);
            }else{
                if($revision!==str_repeat('0',64))throw new \DomainException('asaas_company_stale');
                if($this->rows('SELECT id FROM '.$this->p.'asaas_company_accounts WHERE account_id=? AND environment=? FOR UPDATE',[$wallet,$environment]))throw new \DomainException('asaas_company_account_conflict');
                $next=bin2hex(random_bytes(32));\IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_accounts(issuer_id,environment,account_id,secret_cipher,revision,verified_at,webhook_public_id,token_hash,enabled,created_at) VALUES(?,?,?,?,?,NULL,?,NULL,1,?)',[$issuer,$environment,$wallet,$cipher,$next,bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);$id=(int)$this->db->insert_id();
            }
            $saved=$this->account($issuer,$id);if($saved['secret_cipher']!==$cipher||$saved['revision']!==$next||$saved['verified_at']!==null)throw new \RuntimeException('issuer_asaas_account_unconfirmed');
            $this->audit($issuer,$actor,'company_asaas_credentials_saved',$id);return $id;
        });
    }
    private function client(array $account,callable $decrypt,?callable $transport=null):CompanyClient
    {
        if((int)$account['enabled']!==1)throw new \DomainException('asaas_company_account_disabled');
        try{$secret=$decrypt($account['secret_cipher']);}catch(\Throwable $e){throw new \DomainException('asaas_company_credentials_required');}
        if(!is_string($secret))throw new \DomainException('asaas_company_credentials_required');return new CompanyClient($account['environment'],$secret,$transport);
    }
    private static function wallet(CompanyClient $client,array $account):void
    {
        $response=$client->request('GET','wallets/');$rows=$response['data']??null;
        if(!is_array($rows)||count($rows)!==1||($response['hasMore']??false)!==false||($rows[0]['id']??null)!==$account['account_id'])throw new \DomainException('asaas_company_account_unverified');
    }
    public function verifyAccount($issuer,$actor,$account,string $revision,callable $decrypt,?callable $transport=null):void
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$account=\IssuerPolicy::id($account);self::revision($revision);$this->authorize($issuer,$actor,'configure');
        $row=$this->transaction(function()use($issuer,$actor,$account,$revision){$this->authorize($issuer,$actor,'configure');$row=$this->account($issuer,$account);if(!hash_equals($row['revision'],$revision))throw new \DomainException('asaas_company_stale');return $row;});
        self::wallet($this->client($row,$decrypt,$transport),$row);
        $this->transaction(function()use($issuer,$actor,$account,$revision):void{$this->authorize($issuer,$actor,'configure');$row=$this->account($issuer,$account);if(!hash_equals($row['revision'],$revision))throw new \DomainException('asaas_company_stale');$now=gmdate('Y-m-d H:i:s');\IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_company_accounts SET verified_at=? WHERE id=? AND issuer_id=?',[$now,$account,$issuer]);if($this->account($issuer,$account)['verified_at']!==$now)throw new \RuntimeException('issuer_asaas_account_unconfirmed');$this->audit($issuer,$actor,'company_asaas_account_verified',$account);});
    }
    public function rotateWebhook($issuer,$actor,$account,string $revision):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$account=\IssuerPolicy::id($account);self::revision($revision);
        return $this->transaction(function()use($issuer,$actor,$account,$revision):array{$this->authorize($issuer,$actor,'configure');$row=$this->account($issuer,$account);if(!hash_equals($row['revision'],$revision)||$row['verified_at']===null)throw new \DomainException('asaas_company_stale');
            $token=bin2hex(random_bytes(32));$next=bin2hex(random_bytes(32));\IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_company_accounts SET token_hash=?,revision=? WHERE id=? AND issuer_id=?',[hash('sha256',$token),$next,$account,$issuer]);
            $saved=$this->account($issuer,$account);if(!hash_equals($saved['token_hash'],hash('sha256',$token)))throw new \RuntimeException('issuer_asaas_token_unconfirmed');$this->audit($issuer,$actor,'company_asaas_webhook_rotated',$account);
            return ['public_id'=>$saved['webhook_public_id'],'one_time_token'=>$token];
        });
    }
    public function disableAccount($issuer,$actor,$account,string $revision):void
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$account=\IssuerPolicy::id($account);self::revision($revision);
        $this->transaction(function()use($issuer,$actor,$account,$revision):void{$this->authorize($issuer,$actor,'configure');$row=$this->account($issuer,$account);if(!hash_equals($row['revision'],$revision))throw new \DomainException('asaas_company_stale');\IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_company_accounts SET enabled=0,token_hash=NULL,automation_actor=NULL,revision=? WHERE id=? AND issuer_id=?',[bin2hex(random_bytes(32)),$account,$issuer]);$this->audit($issuer,$actor,'company_asaas_account_disabled',$account);});
    }
    public function enableAccount($issuer,$actor,$account,string $revision):void
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$account=\IssuerPolicy::id($account);self::revision($revision);
        $this->transaction(function()use($issuer,$actor,$account,$revision):void{$this->authorize($issuer,$actor,'configure');$row=$this->account($issuer,$account);if(!hash_equals($row['revision'],$revision))throw new \DomainException('asaas_company_stale');\IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_company_accounts SET enabled=1,verified_at=NULL,token_hash=NULL,automation_actor=NULL,revision=? WHERE id=? AND issuer_id=?',[bin2hex(random_bytes(32)),$account,$issuer]);$this->audit($issuer,$actor,'company_asaas_account_enabled',$account);});
    }
    public function configureAutomation($issuer,$actor,$account,string $revision,bool $enabled):void
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$account=\IssuerPolicy::id($account);self::revision($revision);
        $this->transaction(function()use($issuer,$actor,$account,$revision,$enabled):void{
            $this->authorize($issuer,$actor,'configure');$row=$this->account($issuer,$account);if(!hash_equals($row['revision'],$revision))throw new \DomainException('asaas_company_stale');
            if($enabled){$this->authorize($issuer,$actor,'settle');if($row['verified_at']===null||(int)$row['enabled']!==1||$row['token_hash']===null)throw new \DomainException('asaas_company_account_unverified');}
            \IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_company_accounts SET automation_actor=?,revision=? WHERE id=? AND issuer_id=?',[$enabled?$actor:null,bin2hex(random_bytes(32)),$account,$issuer]);
            $saved=$this->account($issuer,$account);if(($saved['automation_actor']===null?null:(int)$saved['automation_actor'])!==($enabled?$actor:null))throw new \RuntimeException('issuer_asaas_automation_unconfirmed');
            if($enabled)foreach($this->rows("SELECT * FROM {$this->p}asaas_company_events WHERE account_row=? AND event_type='PAYMENT_RECEIVED' AND state='observed' ORDER BY id LIMIT 100",[$account]) as $event)$this->enqueueEvent($saved,$event);
            $this->audit($issuer,$actor,$enabled?'company_asaas_automation_enabled':'company_asaas_automation_disabled',$account);
        });
    }
    private function enqueueEvent(array $account,array $event):bool
    {
        \IssuerTransaction::assertOwned($this->db,$this->p,self::tables());
        if($account['automation_actor']===null||(int)$account['automation_actor']<1||(int)$account['enabled']!==1||$account['verified_at']===null||(int)$event['account_row']!==(int)$account['id']||$event['event_type']!=='PAYMENT_RECEIVED'||$event['state']!=='observed')return false;
        $origin=$this->rows('SELECT o.id FROM '.$this->p.'asaas_payment_origins o JOIN '.$this->p.'asaas_company_origin_meta m ON m.origin_id=o.id JOIN '.$this->p.'invoices i ON i.id=o.invoice_id AND i.issuer_id=o.issuer_id JOIN '.$this->p.'issuers_invoice_documents d ON d.invoice_id=i.id AND d.issuer_id=i.issuer_id WHERE o.issuer_id=? AND o.account_id=? AND o.environment=? AND o.remote_id=? AND m.account_row=? AND o.provider=\'asaas\' AND o.channel=\'api\' AND o.state IN (\'issued\',\'received_observed\',\'issuing\',\'uncertain\') FOR UPDATE',[$account['issuer_id'],$account['account_id'],$account['environment'],$event['remote_id'],$account['id']])[0]??null;
        if(!$origin||$this->rows('SELECT event_id FROM '.$this->p.'asaas_company_jobs WHERE event_id=? FOR UPDATE',[$event['id']]))return false;
        $now=gmdate('Y-m-d H:i:s');\IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_jobs(event_id,issuer_id,origin_id,actor_id,state,attempts,lease_token,lease_until,next_attempt_at,last_code,created_at,updated_at) VALUES(?,?,?,?,?,0,NULL,NULL,?,?,?,?)',[$event['id'],$account['issuer_id'],$origin['id'],$account['automation_actor'],'pending',$now,'received_event',$now,$now]);
        if(!$this->rows('SELECT event_id FROM '.$this->p.'asaas_company_jobs WHERE event_id=? AND origin_id=? AND issuer_id=?',[$event['id'],$origin['id'],$account['issuer_id']]))throw new \RuntimeException('issuer_asaas_job_unconfirmed');return true;
    }
    /** Bounded repair for authenticated old events whose origin became known after ingress. No HTTP or financial write. */
    public function enqueueObservedJobsInOwnedTransaction(int $limit=100):int
    {
        if($limit<1||$limit>100)throw new \InvalidArgumentException('asaas_company_worker_limit');\IssuerTransaction::assertOwned($this->db,$this->p,self::tables());$this->module();
        $events=$this->rows("SELECT e.* FROM {$this->p}asaas_company_events e JOIN {$this->p}asaas_company_accounts a ON a.id=e.account_row JOIN {$this->p}issuers_registry r ON r.id=a.issuer_id AND r.active=1 JOIN {$this->p}asaas_payment_origins o ON o.issuer_id=a.issuer_id AND o.account_id=a.account_id AND o.environment=a.environment AND o.remote_id=e.remote_id JOIN {$this->p}asaas_company_origin_meta m ON m.origin_id=o.id AND m.account_row=a.id JOIN {$this->p}invoices i ON i.id=o.invoice_id AND i.issuer_id=o.issuer_id JOIN {$this->p}issuers_invoice_documents d ON d.invoice_id=i.id AND d.issuer_id=i.issuer_id LEFT JOIN {$this->p}asaas_company_jobs j ON j.event_id=e.id WHERE e.event_type='PAYMENT_RECEIVED' AND e.state='observed' AND a.enabled=1 AND a.verified_at IS NOT NULL AND a.automation_actor>0 AND o.provider='asaas' AND o.channel='api' AND o.state IN ('issued','received_observed','issuing','uncertain') AND j.event_id IS NULL ORDER BY e.id LIMIT ".$limit.' FOR UPDATE');$queued=0;
        foreach($events as $event){$account=$this->rows('SELECT * FROM '.$this->p.'asaas_company_accounts WHERE id=? FOR UPDATE',[$event['account_row']])[0]??null;if($account&&$this->enqueueEvent($account,$event))$queued++;}return $queued;
    }
    private function parent(int $issuer,int $invoice):array
    {
        $row=$this->rows('SELECT i.id,i.issuer_id,i.clientid,i.hash,i.date,i.duedate,i.total,i.currency,i.status,NULL currency_name,d.legal_identity_json,d.recipient_identity_json,d.formatted_number FROM '.$this->p.'invoices i JOIN '.$this->p.'issuers_invoice_documents d ON d.invoice_id=i.id AND d.issuer_id=i.issuer_id WHERE i.id=? AND i.issuer_id=? FOR UPDATE',[$invoice,$issuer])[0]??null;
        if(!$row)throw new \DomainException('asaas_company_invoice_denied');
        $row['currency_name']=(new \CompanyCurrencyUnits($this->db,$this->p))->resolveRead($row['currency'])['iso_code'];
        if($row['currency_name']!=='BRL')throw new \DomainException('asaas_company_invoice_denied');
        // DECIMAL database values are normalized at the trusted storage boundary (SQLite's PDO fixture returns floats).
        $row['total']=\CompanyInvoiceAmounts::decimal(\app\services\InvoicePaymentWriter::minorUnits($row['total']));
        $recipient=json_decode($row['recipient_identity_json'],true,8,JSON_THROW_ON_ERROR);\IssuerPolicy::taxId($recipient['tax_id']??null);
        return $row;
    }
    private static function snapshot(array $row):array
    {
        unset($row['status']);foreach($row as $key=>$value)$row[$key]=(string)$value;return $row;
    }
    private static function hash(array $value):string{return hash('sha256',json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));}
    private function unchanged(array $meta,array $parent):void
    {
        $snapshot=json_decode($meta['snapshot_json'],true,32,JSON_THROW_ON_ERROR);
        if(!hash_equals($meta['snapshot_hash'],self::hash($snapshot))||$snapshot!==self::snapshot($parent))throw new \DomainException('asaas_company_snapshot_divergence');
    }
    private static function customer(CompanyClient $client,string $customer,array $parent):void
    {
        $row=$client->request('GET','customers/'.$customer);$recipient=json_decode($parent['recipient_identity_json'],true,8,JSON_THROW_ON_ERROR);
        if(($row['id']??null)!==$customer||($row['deleted']??false)!==false||\IssuerPolicy::taxId($row['cpfCnpj']??null)!==\IssuerPolicy::taxId($recipient['tax_id']??null))throw new \DomainException('asaas_company_customer_unverified');
    }
    public function hostedCheckout($issuer,$actor,$invoice,$account,string $customer,string $revision,string $requestKey,callable $decrypt,?callable $transport=null,$installments=1):int
    {
        $this->checkoutModule();HostedCardCheckout::installments($installments);
        $origin=$this->issueOwned($issuer,$actor,$invoice,$account,$customer,'CREDIT_CARD',$revision,$requestKey,$decrypt,$transport);$this->query($issuer,$actor,$origin,$decrypt,$transport);return $origin;
    }
    public function issue($issuer,$actor,$invoice,$account,string $customer,string $billingType,string $revision,string $requestKey,callable $decrypt,?callable $transport=null):int
    {
        if(!in_array($billingType,['PIX','BOLETO'],true))throw new \DomainException('asaas_company_profile_unavailable');return $this->issueOwned($issuer,$actor,$invoice,$account,$customer,$billingType,$revision,$requestKey,$decrypt,$transport);
    }
    private function issueOwned($issuer,$actor,$invoice,$account,string $customer,string $billingType,string $revision,string $requestKey,callable $decrypt,?callable $transport=null):int
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$invoice=\IssuerPolicy::id($invoice);$account=\IssuerPolicy::id($account);self::opaque($customer,64);self::revision($revision);self::revision($requestKey);
        $this->authorize($issuer,$actor,'issue');
        $prepared=$this->transaction(function()use($issuer,$actor,$invoice,$account,$revision):array{$this->authorize($issuer,$actor,'issue');$row=$this->account($issuer,$account);if($row['verified_at']===null||(int)$row['enabled']!==1)throw new \DomainException('asaas_company_account_unverified'); $parent=$this->parent($issuer,$invoice);(new \CompanyInvoiceBalance($this->db,$this->p))->inOwned($issuer,$actor,$invoice);(new \CompanyCurrencyUnits($this->db,$this->p))->assertWrite($parent['currency']);if(!hash_equals((new \CompanyInvoices($this->db,$this->p))->revision($issuer,$actor,$invoice),$revision))throw new \DomainException('asaas_company_stale');return [$row,$parent];});
        [$config,$parent]=$prepared;$client=$this->client($config,$decrypt,$transport);self::wallet($client,$config);self::customer($client,$customer,$parent);
        $reserved=$this->transaction(function()use($issuer,$actor,$invoice,$account,$config,$parent,$revision,$requestKey,$customer,$billingType):array{
            $this->authorize($issuer,$actor,'issue');$current=$this->account($issuer,$account);if(!hash_equals($current['revision'],$config['revision'])||$current['verified_at']===null)throw new \DomainException('asaas_company_stale');
            $now=$this->parent($issuer,$invoice);(new \CompanyInvoiceBalance($this->db,$this->p))->inOwned($issuer,$actor,$invoice);(new \CompanyCurrencyUnits($this->db,$this->p))->assertWrite($now['currency']);if(self::snapshot($now)!==self::snapshot($parent)||!hash_equals((new \CompanyInvoices($this->db,$this->p))->revision($issuer,$actor,$invoice),$revision))throw new \DomainException('asaas_company_stale');
            $old=$this->rows('SELECT o.id,m.request_key,m.account_row,m.customer_id,m.billing_type,m.snapshot_hash FROM '.$this->p.'asaas_payment_origins o LEFT JOIN '.$this->p.'asaas_company_origin_meta m ON m.origin_id=o.id WHERE o.invoice_id=? FOR UPDATE',[$invoice])[0]??null;
            if($old){if((int)$old['account_row']!==$account||$old['request_key']!==$requestKey||$old['customer_id']!==$customer||$old['billing_type']!==$billingType||$old['snapshot_hash']!==self::hash(self::snapshot($parent)))throw new \DomainException('asaas_company_origin_conflict');return [(int)$old['id'],false];}
            if(!in_array((int)$now['status'],[1,4],true)||$this->rows('SELECT id FROM '.$this->p.'invoicepaymentrecords WHERE invoiceid=? FOR UPDATE',[$invoice])||$this->rows('SELECT id FROM '.$this->p.'credits WHERE invoice_id=? FOR UPDATE',[$invoice]))throw new \DomainException('asaas_company_balance_requires_review');
            if($this->db->table_exists($this->p.'bb_origins')&&$this->rows('SELECT id FROM '.$this->p.'bb_origins WHERE invoice_id=? FOR UPDATE',[$invoice]))throw new \DomainException('asaas_company_multiple_providers');
            $amount=\app\services\InvoicePaymentWriter::minorUnits($now['total']);if($amount<1||$amount>9999999999999)throw new \DomainException('asaas_company_invalid_input');
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_payment_origins(invoice_id,invoice_hash,invoice_total,invoice_due_date,issuer_id,provider,account_id,environment,channel,remote_id,batch_id,state,last_code,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,NULL,NULL,?,?,?,?)',[$invoice,$now['hash'],$now['total'],$now['duedate'],$issuer,'asaas',$config['account_id'],$config['environment'],'api','issuing','company_reserved',gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')]);$id=(int)$this->db->insert_id();
            $snapshot=self::snapshot($parent);\IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_origin_meta(origin_id,account_row,created_by,request_key,snapshot_hash,snapshot_json,customer_id,billing_type,created_at) VALUES(?,?,?,?,?,?,?,?,?)',[$id,$account,$actor,$requestKey,self::hash($snapshot),json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$customer,$billingType,gmdate('Y-m-d H:i:s')]);
            if(!$this->rows('SELECT origin_id FROM '.$this->p.'asaas_company_origin_meta WHERE origin_id=? AND account_row=?',[$id,$account]))throw new \RuntimeException('issuer_asaas_origin_unconfirmed');$this->audit($issuer,$actor,'company_asaas_origin_reserved',$id);return [$id,true];
        });
        [$origin,$send]=$reserved;if(!$send)return $origin;
        $payload=['customer'=>$customer,'billingType'=>$billingType,'value'=>\CompanyInvoiceAmounts::decimal(\app\services\InvoicePaymentWriter::minorUnits($parent['total'])),'dueDate'=>$parent['duedate'],'externalReference'=>$parent['hash'],'description'=>'Fatura '.$parent['formatted_number'],'fine'=>['value'=>0],'interest'=>['value'=>0],'discount'=>['value'=>0]];
        try{$response=$client->request('POST','payments',$payload);$remote=self::opaque($response['id']??null,64);$this->storeProof($issuer,$actor,$origin,$config,$parent,$response,$remote,'issue');}
        catch(\Throwable $e){$this->transaction(function()use($issuer,$actor,$origin):void{$this->authorize($issuer,$actor,'issue');\IssuerStorage::query($this->db,"UPDATE {$this->p}asaas_payment_origins SET state='uncertain',last_code='company_post_unconfirmed',updated_at=? WHERE id=? AND issuer_id=? AND state='issuing'",[gmdate('Y-m-d H:i:s'),$origin,$issuer]);$this->audit($issuer,$actor,'company_asaas_post_unconfirmed',$origin);});throw new \RuntimeException('asaas_company_post_unconfirmed');}
        return $origin;
    }
    private function source(int $issuer,int $origin):array
    {
        $row=$this->rows('SELECT * FROM '.$this->p.'asaas_payment_origins WHERE id=? AND issuer_id=? AND provider=? AND channel=? FOR UPDATE',[$origin,$issuer,'asaas','api'])[0]??null;
        $meta=$this->rows('SELECT * FROM '.$this->p.'asaas_company_origin_meta WHERE origin_id=? FOR UPDATE',[$origin])[0]??null;
        if(!$row||!$meta)throw new \DomainException('asaas_company_origin_denied');$account=$this->account($issuer,(int)$meta['account_row']);
        if($row['account_id']!==$account['account_id']||$row['environment']!==$account['environment']||(int)$account['enabled']!==1||$account['verified_at']===null)throw new \DomainException('asaas_company_account_unverified');
        $parent=$this->parent($issuer,(int)$row['invoice_id']);$this->unchanged($meta,$parent);return [$row,$meta,$account,$parent];
    }
    /** Internal composition: private credentials never leave a transaction-owning service. */
    public function remoteSourceInOwnedTransaction(int $issuer,int $actor,int $origin):array
    {
        \IssuerTransaction::assertOwned($this->db,$this->p,$this->tablesForRun());$this->authorize($issuer,$actor,'remote_remove');$source=$this->source($issuer,$origin);(new \CompanyInvoiceBalance($this->db,$this->p))->inOwned($issuer,$actor,(int)$source[0]['invoice_id']);return $source;
    }
    /** Refund-only private composition. This grants no remote operation by itself. */
    public function refundSourceInOwnedTransaction(int $issuer,int $actor,int $origin,bool $write=true):array
    {
        \IssuerTransaction::assertOwned($this->db,$this->p,$this->tablesForRun());
        if($write)$this->authorize($issuer,$actor,'refund');
        elseif(!(new \IssuerResourceAccess($this->db,$this->p))->can($issuer,$actor,'invoices','view'))throw new \DomainException('asaas_company_access_denied');
        if($write)$source=$this->source($issuer,$origin);
        else{
            // Historical private metadata remains readable after an account is disabled or rotated.
            $row=$this->rows('SELECT * FROM '.$this->p.'asaas_payment_origins WHERE id=? AND issuer_id=? AND provider=? AND channel=? FOR UPDATE',[$origin,$issuer,'asaas','api'])[0]??null;
            $meta=$this->rows('SELECT * FROM '.$this->p.'asaas_company_origin_meta WHERE origin_id=? FOR UPDATE',[$origin])[0]??null;
            if(!$row||!$meta)throw new \DomainException('asaas_company_origin_denied');$account=$this->account($issuer,(int)$meta['account_row']);
            if($row['account_id']!==$account['account_id']||$row['environment']!==$account['environment'])throw new \DomainException('asaas_company_origin_denied');
            $source=[$row,$meta,$account,$this->parent($issuer,(int)$row['invoice_id'])];
        }
        (new \CompanyInvoiceBalance($this->db,$this->p))->inOwned($issuer,$actor,(int)$source[0]['invoice_id']);
        return $source;
    }
    private static function money($value):string
    {
        try{if(is_float($value))throw new \DomainException();return \CompanyInvoiceAmounts::decimal(\app\services\InvoicePaymentWriter::minorUnits($value));}catch(\Throwable $e){throw new \DomainException('asaas_company_amount_requires_review');}
    }
    private static function normalize(array $response,array $origin,array $meta,array $parent,string $remote):array
    {
        if(($response['id']??null)!==$remote||($origin['remote_id']!==null&&$origin['remote_id']!==$remote)||($response['externalReference']??null)!==$parent['hash']||($response['customer']??null)!==$meta['customer_id']||($response['dueDate']??null)!==$parent['duedate']||($response['billingType']??null)!==$meta['billing_type']||self::money($response['value']??null)!==self::money($parent['total']))throw new \DomainException('asaas_company_response_conflict');
        if(isset($response['deleted'])&&!is_bool($response['deleted']))throw new \DomainException('asaas_company_response_conflict');
        $status=$response['status']??null;if(!is_string($status)||!preg_match('/^[A-Z_]{1,32}$/D',$status))throw new \DomainException('asaas_company_response_conflict');
        $safe=['id'=>$remote,'status'=>$status,'value'=>self::money($response['value']),'net_value'=>isset($response['netValue'])?self::money($response['netValue']):'',
            'customer'=>$meta['customer_id'],'billing_type'=>$meta['billing_type'],'due_date'=>$parent['duedate'],'external_reference'=>$parent['hash'],'payment_date'=>$response['paymentDate']??'','credit_date'=>$response['creditDate']??'',
            'deleted'=>($response['deleted']??false)!==false,'adverse'=>!empty($response['refunds'])||!empty($response['chargeback'])||!empty($response['split'])||!empty($response['installment'])||!empty($response['subscription'])||!empty($response['anticipated'])];
        foreach(['payment_date','credit_date'] as $date)if(!is_string($safe[$date])||($safe[$date]!==''&&!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$safe[$date])))throw new \DomainException('asaas_company_response_conflict');
        foreach(['interestValue','originalValue'] as $field)if(isset($response[$field])&&self::money($response[$field])!==($field==='originalValue'?self::money($parent['total']):'0.00'))$safe['adverse']=true;
        foreach(['fine','interest','discount'] as $field)if(isset($response[$field]['value'])&&self::money($response[$field]['value'])!=='0.00')$safe['adverse']=true;
        if($meta['billing_type']==='CREDIT_CARD'&&isset($response['invoiceUrl'])&&(!is_string($response['invoiceUrl'])||strlen($response['invoiceUrl'])>2048))throw new \DomainException('asaas_company_response_conflict');
        if($meta['billing_type']==='CREDIT_CARD'){$safe['checkout_url']=$safe['deleted']?'':HostedCardCheckout::verifiedUrl($response,$origin['environment'],$parent['hash']);$safe['card_settlement']=CompanyCardSettlement::observe($response);}
        $safe['fee']='';if($safe['net_value']!==''){$gross=\app\services\InvoicePaymentWriter::minorUnits($safe['value']);$net=\app\services\InvoicePaymentWriter::minorUnits($safe['net_value']);if($net>$gross)throw new \DomainException('asaas_company_amount_requires_review');$safe['fee']=\CompanyInvoiceAmounts::decimal($gross-$net);}
        return $safe;
    }
    private function storeProof(int $issuer,int $actor,int $origin,array $config,array $parent,array $response,string $remote,string $operation='query'):int
    {
        $out=$this->transaction(function()use($issuer,$actor,$origin,$config,$parent,$response,$remote,$operation):int{
            $this->authorize($issuer,$actor,'observe');[$row,$meta,$account,$now]=$this->source($issuer,$origin);
            if(!hash_equals($account['revision'],$config['revision'])||self::snapshot($now)!==self::snapshot($parent))throw new \DomainException('asaas_company_stale');
            try{$safe=self::normalize($response,$row,$meta,$parent,$remote);}catch(\DomainException $error){\IssuerStorage::query($this->db,"UPDATE {$this->p}asaas_payment_origins SET state='review_required',last_code='company_canonical_conflict' WHERE id=? AND issuer_id=?",[$origin,$issuer]);$this->audit($issuer,$actor,'company_asaas_canonical_conflict',$origin);return 0;}
            $review=$safe['adverse']||$safe['deleted']||str_contains($safe['status'],'REFUND')||str_contains($safe['status'],'CHARGEBACK');
            if($row['state']==='review_required')$review=true;
            $state=$review?'review_required':($operation==='query'&&$safe['status']==='RECEIVED'?'received_observed':'issued');
            if($this->db->table_exists($this->p.'asaas_company_remote_operations')&&($remoteOperation=$this->rows('SELECT state FROM '.$this->p.'asaas_company_remote_operations WHERE origin_id=? FOR UPDATE',[$origin])[0]??null)){
                $stillDeleted=$safe['deleted']&&!$safe['adverse']&&in_array($safe['status'],['PENDING','OVERDUE'],true);
                if($remoteOperation['state']==='deleted'&&!$stillDeleted){
                    \IssuerStorage::query($this->db,"UPDATE {$this->p}asaas_company_remote_operations SET state='review_required',last_code='canonical_deleted_contradicted',updated_at=? WHERE origin_id=?",[gmdate('Y-m-d H:i:s'),$origin]);$state='review_required';
                }else $state=$remoteOperation['state']==='deleted'?'remote_removed':'remote_removal_pending';
            }
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_proofs(origin_id,account_revision,actor_id,operation,result_json,evidence_hash,state,created_at) VALUES(?,?,?,?,?,?,?,?)',[$origin,$config['revision'],$actor,$operation,json_encode($safe,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),self::hash($safe),$state,gmdate('Y-m-d H:i:s')]);$proof=(int)$this->db->insert_id();
            \IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_payment_origins SET remote_id=?,state=?,last_code=?,updated_at=? WHERE id=? AND issuer_id=?',[$remote,$state,'company_query_'.$safe['status'],gmdate('Y-m-d H:i:s'),$origin,$issuer]);
            // Bind remote ID and every already-observed eligible callback atomically; a callback may precede the POST response.
            foreach($this->rows("SELECT e.* FROM {$this->p}asaas_company_events e LEFT JOIN {$this->p}asaas_company_jobs j ON j.event_id=e.id WHERE e.account_row=? AND e.remote_id=? AND e.event_type='PAYMENT_RECEIVED' AND e.state='observed' AND j.event_id IS NULL ORDER BY e.id LIMIT 100 FOR UPDATE",[$account['id'],$remote]) as $event)$this->enqueueEvent($account,$event);
            if($proof<1||!$this->rows('SELECT id FROM '.$this->p.'asaas_company_proofs WHERE id=? AND evidence_hash=?',[$proof,self::hash($safe)]))throw new \RuntimeException('issuer_asaas_proof_unconfirmed');$this->audit($issuer,$actor,'company_asaas_canonical_observed',$origin);return $proof;
        });
        if($out===0)throw new \DomainException('asaas_company_response_conflict');return $out;
    }
    public function query($issuer,$actor,$origin,callable $decrypt,?callable $transport=null,string $recoveryId=''):int
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$origin=\IssuerPolicy::id($origin);$this->authorize($issuer,$actor,'observe');
        [$row,$meta,$account,$parent]=$this->transaction(function()use($issuer,$actor,$origin){$this->authorize($issuer,$actor,'observe');return $this->source($issuer,$origin);});
        $remote=$row['remote_id']??self::opaque($recoveryId,64);$client=$this->client($account,$decrypt,$transport);
        try{self::wallet($client,$account);self::customer($client,$meta['customer_id'],$parent);}catch(\DomainException $error){$this->transaction(function()use($issuer,$actor,$origin):void{$this->authorize($issuer,$actor,'observe');\IssuerStorage::query($this->db,"UPDATE {$this->p}asaas_payment_origins SET state='review_required',last_code='company_identity_conflict' WHERE id=? AND issuer_id=?",[$origin,$issuer]);$this->audit($issuer,$actor,'company_asaas_identity_conflict',$origin);});throw $error;}
        return $this->storeProof($issuer,$actor,$origin,$account,$parent,$client->request('GET','payments/'.$remote),$remote);
    }
    /** Public inbox authenticates scope and persists minimum; it never writes a financial receipt. */
    public function receive(string $publicId,array $headers,string $body):array
    {
        $this->module();if(!preg_match('/^[a-f0-9]{32}$/D',$publicId))return ['status'=>404];
        $account=$this->rows('SELECT * FROM '.$this->p.'asaas_company_accounts WHERE webhook_public_id=?',[$publicId])[0]??null;
        if(!$account||(int)$account['enabled']!==1||$account['token_hash']===null)return ['status'=>401];
        $verified=WebhookSecurity::validateDigest($account['token_hash'],$headers,$body);if(!$verified['ok'])return ['status'=>$verified['status']];
        if(!isset($verified['payload']['payment']['id']))return ['status'=>422];
        return $this->transaction(function()use($account,$verified,$headers,$body):array{
            $current=$this->account((int)$account['issuer_id'],(int)$account['id']);
            if((int)$current['enabled']!==1||$current['token_hash']===null||!WebhookSecurity::validateDigest($current['token_hash'],$headers,$body)['ok'])return ['status'=>401];
            if(!$this->rows('SELECT id FROM '.$this->p.'issuers_registry WHERE id=? AND active=1',[$account['issuer_id']]))return ['status'=>401];
            $remote=$verified['payload']['payment']['id'];$event=$verified['event'];$digest=hash('sha256',$body);
            $old=$this->rows('SELECT * FROM '.$this->p.'asaas_company_events WHERE account_row=? AND event_id=? FOR UPDATE',[$account['id'],$verified['event_id']])[0]??null;
            if($old){if($old['payload_hash']===$digest)return ['status'=>200,'duplicate'=>true];\IssuerStorage::query($this->db,"UPDATE {$this->p}asaas_company_events SET state='conflict' WHERE id=?",[$old['id']]);return ['status'=>409];}
            $adverse=in_array($event,['PAYMENT_ANTICIPATED','PAYMENT_CREDIT_CARD_CAPTURE_REFUSED','PAYMENT_REPROVED_BY_RISK_ANALYSIS','PAYMENT_DELETED','PAYMENT_REFUNDED','PAYMENT_PARTIALLY_REFUNDED','PAYMENT_REFUND_IN_PROGRESS','PAYMENT_RECEIVED_IN_CASH_UNDONE','PAYMENT_CHARGEBACK_REQUESTED','PAYMENT_CHARGEBACK_DISPUTE','PAYMENT_AWAITING_CHARGEBACK_REVERSAL','PAYMENT_SPLIT_DIVERGENCE_BLOCK'],true);
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_events(account_row,event_id,event_type,remote_id,payload_hash,state,created_at) VALUES(?,?,?,?,?,?,?)',[$account['id'],$verified['event_id'],$event,$remote,$digest,$adverse?'review_required':'observed',gmdate('Y-m-d H:i:s')]);
            if($adverse)\IssuerStorage::query($this->db,"UPDATE {$this->p}asaas_payment_origins SET state='review_required',last_code='company_adverse_event' WHERE issuer_id=? AND account_id=? AND environment=? AND remote_id=?",[$account['issuer_id'],$account['account_id'],$account['environment'],$remote]);
            $written=$this->rows('SELECT payload_hash FROM '.$this->p.'asaas_company_events WHERE account_row=? AND event_id=?',[$account['id'],$verified['event_id']])[0]??[];if(($written['payload_hash']??null)!==$digest)throw new \RuntimeException('issuer_asaas_event_unconfirmed');
            $storedEvent=$this->rows('SELECT * FROM '.$this->p.'asaas_company_events WHERE account_row=? AND event_id=?',[$account['id'],$verified['event_id']])[0];$this->enqueueEvent($current,$storedEvent);
            return ['status'=>200,'duplicate'=>false];
        });
    }
    public function overview($issuer,$actor):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$this->authorize($issuer,$actor,'view');CompanySchema::ensure($this->db,$this->p,false);
        $canInvoices=(new \IssuerResourceAccess($this->db,$this->p))->can($issuer,$actor,'invoices','view');
        $origins=$canInvoices?$this->rows('SELECT o.id,o.invoice_id,o.account_id,o.environment,o.remote_id,o.state,m.billing_type,m.created_by FROM '.$this->p.'asaas_payment_origins o JOIN '.$this->p.'asaas_company_origin_meta m ON m.origin_id=o.id WHERE o.issuer_id=? ORDER BY o.id DESC LIMIT 100',[$issuer]):[];
        foreach($origins as &$origin){$proof=$this->rows('SELECT id,state,result_json,created_at FROM '.$this->p.'asaas_company_proofs WHERE origin_id=? ORDER BY id DESC LIMIT 1',[$origin['id']])[0]??null;if($proof){$safe=json_decode($proof['result_json'],true,32,JSON_THROW_ON_ERROR);unset($proof['result_json']);$proof+=array_intersect_key($safe,array_flip(['status','value','net_value','fee','payment_date','credit_date','checkout_url']));$proof['fee_posting_status']=$safe['fee']==='0.00'?'not_applicable':'not_posted';$linked=$this->rows('SELECT f.expense_id,e.total_cents,e.state FROM '.$this->p.'asaas_company_fee_expenses f JOIN '.$this->p.'company_expenses e ON e.id=f.expense_id AND e.issuer_id=f.issuer_id WHERE f.origin_id=? AND f.issuer_id=?',[$origin['id'],$issuer])[0]??null;if($linked){$proof['fee_posting_status']=$linked['state']==='recorded'&&(int)$linked['total_cents']===\app\services\InvoicePaymentWriter::minorUnits($safe['fee'])?'posted':'review_required';$proof['fee_expense_id']=(int)$linked['expense_id'];}}$origin['remote_operation']=$this->db->table_exists($this->p.'asaas_company_remote_operations')?($this->rows('SELECT state,last_code,updated_at FROM '.$this->p.'asaas_company_remote_operations WHERE origin_id=? AND issuer_id=?',[$origin['id'],$issuer])[0]??null):null;$origin['hosted_publication']=$this->db->table_exists($this->p.'asaas_company_hosted_links')?($this->rows('SELECT enabled,updated_at FROM '.$this->p.'asaas_company_hosted_links WHERE origin_id=? AND issuer_id=?',[$origin['id'],$issuer])[0]??null):null;$origin['proof']=$proof;$origin['receipt']=$this->rows('SELECT payment_id,created_at FROM '.$this->p.'asaas_company_receipts WHERE origin_id=? AND issuer_id=?',[$origin['id'],$issuer])[0]??null;}
        unset($origin);
        $events=$canInvoices?$this->rows('SELECT e.event_id,e.event_type,e.remote_id,e.state,e.created_at,a.environment FROM '.$this->p.'asaas_company_events e JOIN '.$this->p.'asaas_company_accounts a ON a.id=e.account_row WHERE a.issuer_id=? ORDER BY e.id DESC LIMIT 100',[$issuer]):[];
        $jobs=$canInvoices?$this->rows('SELECT event_id,origin_id,actor_id,state,attempts,next_attempt_at,last_code,updated_at FROM '.$this->p.'asaas_company_jobs WHERE issuer_id=? ORDER BY event_id DESC LIMIT 100',[$issuer]):[];
        $acl=new \IssuerResourceAccess($this->db,$this->p);$feeCategories=$acl->can($issuer,$actor,'expenses','view')&&$acl->can($issuer,$actor,'expenses','create')?$this->rows('SELECT id,name FROM '.$this->p.'company_expense_categories WHERE issuer_id=? AND active=1 ORDER BY id LIMIT 500',[$issuer]):[];
        $remoteReady=$this->db->table_exists($this->p.'asaas_company_remote_operations')&&version_compare(($this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]['installed_version']??'0'),'1.5.0','>=');
        return ['remote_operations_ready'=>$remoteReady,'accounts'=>$this->accounts($issuer,$actor),'origins'=>$origins,'events'=>$events,'jobs'=>$jobs,'fee_categories'=>$feeCategories];
    }
    /** Trusted final receipt adapter only: caller must own the entire core transaction. */
    public function receiptClaim(int $issuer,int $actor,int $origin,int $proofId):array
    {
        \IssuerTransaction::assertOwned($this->db,$this->p,self::tables());$this->authorize($issuer,$actor,'settle');[$row,$meta,$account,$parent]=$this->source($issuer,$origin);(new \CompanyInvoiceBalance($this->db,$this->p))->inOwned($issuer,$actor,(int)$row['invoice_id']);
        if($this->db->table_exists($this->p.'asaas_company_refund_requests')){require_once __DIR__.'/CompanyRefundSchema.php';CompanyRefundSchema::ensure($this->db,$this->p,false);\IssuerTransaction::assertOwned($this->db,$this->p,array_keys(CompanyRefundSchema::definitions()));if($this->rows("SELECT request_key FROM {$this->p}asaas_company_refund_requests WHERE origin_id=? AND (sent_at IS NOT NULL OR state<>'cancelled_local') FOR UPDATE",[$origin]))throw new \DomainException('asaas_refund_receipt_review_required');}
        $proof=$this->rows('SELECT * FROM '.$this->p.'asaas_company_proofs WHERE origin_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE',[$origin])[0]??null;
        if(!$proof||(int)$proof['id']!==$proofId||$proof['state']!=='received_observed'||$proof['operation']!=='query'||$row['state']!=='received_observed'||$proof['account_revision']!==$account['revision'])throw new \DomainException('asaas_company_receipt_requires_review');
        $safe=json_decode($proof['result_json'],true,32,JSON_THROW_ON_ERROR);
        if(!hash_equals($proof['evidence_hash'],self::hash($safe))||$safe['status']!=='RECEIVED'||$safe['adverse']||$safe['deleted']||$safe['net_value']===''||$safe['fee']===''||self::money($safe['value'])!==self::money($parent['total']))throw new \DomainException('asaas_company_receipt_requires_review');
        $date=$safe['payment_date'];$today=(new \DateTimeImmutable('now',new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
        if($meta['billing_type']==='CREDIT_CARD'){$this->checkoutModule();$date=CompanyCardSettlement::receiptDate($safe,$meta,$parent['date'],$today);}
        elseif(!in_array($meta['billing_type'],['PIX','BOLETO'],true)||$safe['billing_type']!==$meta['billing_type'])throw new \DomainException('asaas_company_receipt_requires_review');
        $parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if(!$parsed||$parsed->format('Y-m-d')!==$date||$date>$today||$date<$parent['date'])throw new \DomainException('asaas_company_receipt_requires_review');
        if($this->rows("SELECT id FROM {$this->p}asaas_company_events WHERE account_row=? AND remote_id=? AND state IN ('review_required','conflict') FOR UPDATE",[$account['id'],$row['remote_id']]))throw new \DomainException('asaas_company_adverse_history');
        if($this->db->table_exists($this->p.'bb_origins')&&$this->rows('SELECT id FROM '.$this->p.'bb_origins WHERE invoice_id=? FOR UPDATE',[$row['invoice_id']]))throw new \DomainException('asaas_company_multiple_providers');
        $key=self::hash(['company-asaas-received',$issuer,$row['account_id'],$row['environment'],$row['remote_id']]);
        $evidenceParts=[$meta['snapshot_hash'],$safe['value'],$safe['net_value'],$safe['fee'],$date,$safe['billing_type'],$row['remote_id']];
        if($meta['billing_type']==='CREDIT_CARD')$evidenceParts[]=$safe['card_settlement'];
        $evidence=self::hash($evidenceParts);
        $old=$this->rows('SELECT * FROM '.$this->p.'asaas_company_receipts WHERE origin_id=? OR canonical_key=? FOR UPDATE',[$origin,$key]);
        if(count($old)>1||($old&&((int)$old[0]['origin_id']!==$origin||(int)$old[0]['issuer_id']!==$issuer||$old[0]['canonical_key']!==$key||$old[0]['evidence_hash']!==$evidence)))throw new \DomainException('asaas_company_receipt_conflict');
        if(!$old&&$proof['created_at']<gmdate('Y-m-d H:i:s',time()-300))throw new \DomainException('asaas_company_stale');
        if(!$old&&($this->rows('SELECT id FROM '.$this->p.'invoicepaymentrecords WHERE invoiceid=? FOR UPDATE',[$row['invoice_id']])||$this->rows('SELECT id FROM '.$this->p.'credits WHERE invoice_id=? FOR UPDATE',[$row['invoice_id']])))throw new \DomainException('asaas_company_balance_requires_review');
        return ['invoice'=>(int)$row['invoice_id'],'key'=>$key,'input'=>['amount'=>$safe['value'],'date'=>$date,'reference'=>'ASAAS-'.$key,'note'=>'Recebimento nominal Asaas. Líquido '.$safe['net_value'].'; tarifa observada '.$safe['fee'].'. A tarifa não representa uma despesa contabilizada.'],'evidence'=>$evidence,'proof'=>$proofId];
    }
    public function receiptApplied(int $issuer,int $actor,int $origin,array $claim,int $payment):void
    {
        \IssuerTransaction::assertOwned($this->db,$this->p,self::tables());$this->authorize($issuer,$actor,'settle');$this->source($issuer,$origin);
        $old=$this->rows('SELECT * FROM '.$this->p.'asaas_company_receipts WHERE origin_id=? FOR UPDATE',[$origin])[0]??null;
        if($old){if((int)$old['payment_id']!==$payment||$old['canonical_key']!==$claim['key']||$old['evidence_hash']!==$claim['evidence'])throw new \DomainException('asaas_company_receipt_conflict');return;}
        \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_receipts(origin_id,issuer_id,proof_id,canonical_key,evidence_hash,payment_id,created_by,created_at) VALUES(?,?,?,?,?,?,?,?)',[$origin,$issuer,$claim['proof'],$claim['key'],$claim['evidence'],$payment,$actor,gmdate('Y-m-d H:i:s')]);
        if(!$this->rows('SELECT origin_id FROM '.$this->p.'asaas_company_receipts WHERE origin_id=? AND payment_id=? AND canonical_key=?',[$origin,$payment,$claim['key']]))throw new \RuntimeException('issuer_asaas_receipt_unconfirmed');$this->audit($issuer,$actor,'company_asaas_receipt_applied',$origin);
    }
}
