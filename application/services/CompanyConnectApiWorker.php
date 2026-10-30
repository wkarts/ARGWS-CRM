<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyInvoiceConnectNotification.php';

/** Sessionless opt-in worker over durable own intents. Does not call global SMS triggers. */
final class CompanyConnectApiWorker
{
    public function __construct(private$db,private string$p,private?CompanyConnectApiClient$client=null,private?array$endpoints=null){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public function run(callable$decrypt,int$limit=20):array
    {
        if(class_exists('CompanySettingsRuntime',false)&&CompanySettingsRuntime::active())throw new DomainException('company_connect_api_worker_context_denied');if($limit<1||$limit>100)throw new InvalidArgumentException('Invalid batch limit');CompanyConnectApiSchema::assertValid($this->db,$this->p);$sender=new CompanyInvoiceConnectNotification($this->db,$this->p,$this->client,$this->endpoints);$counts=['accepted'=>0,'uncertain'=>0,'blocked'=>0];$rows=IssuerStorage::query($this->db,"SELECT request_key,issuer_id,actor_id FROM ".$this->p."company_connect_api_messages WHERE status='pending' ORDER BY created_at,request_key LIMIT ".$limit)->result_array();foreach($rows as$r){try{$status=$sender->dispatch((int)$r['issuer_id'],(int)$r['actor_id'],$r['request_key'],$decrypt);if(isset($counts[$status]))$counts[$status]++;}catch(Throwable$e){IssuerTransaction::run($this->db,$this->p,$sender->tables(),function()use($r){IssuerStorage::query($this->db,"UPDATE ".$this->p."company_connect_api_messages SET status='blocked',updated_at=? WHERE request_key=? AND issuer_id=? AND actor_id=? AND status='pending'",[gmdate('Y-m-d H:i:s'),$r['request_key'],$r['issuer_id'],$r['actor_id']]);});$status=IssuerStorage::query($this->db,'SELECT status FROM '.$this->p.'company_connect_api_messages WHERE request_key=? AND issuer_id=?',[$r['request_key'],$r['issuer_id']])->row()->status??'';if(in_array($status,['uncertain','submitting'],true))$counts['uncertain']++;elseif($status==='blocked')$counts['blocked']++;}}return$counts;
    }
}
