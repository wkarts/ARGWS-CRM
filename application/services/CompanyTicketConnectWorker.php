<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyTicketConnect.php';
/** Sessionless bounded discovery and dispatch; only the persisted explicit company opt-in enables discovery. */
final class CompanyTicketConnectWorker
{
    public function __construct(private $db,private string $p,private ?CompanyConnectApiClient $client=null,private ?array $endpoints=null){}
    public function run(callable $encrypt,callable $decrypt,int $limit=20):array
    {
        if(class_exists('CompanySettingsRuntime',false)&&CompanySettingsRuntime::active())throw new DomainException('company_connect_api_worker_context_denied');if($limit<1||$limit>100)throw new InvalidArgumentException('Invalid batch limit');CompanyTicketConnectSchema::assertValid($this->db,$this->p);$sender=new CompanyTicketConnect($this->db,$this->p,$this->client,$this->endpoints);$counts=['queued'=>0,'accepted'=>0,'uncertain'=>0,'blocked'=>0,'discovery_blocked'=>0];
        $policies=IssuerStorage::query($this->db,'SELECT issuer_id,configured_by FROM '.$this->p.'company_ticket_connect_policies WHERE enabled=1 ORDER BY scan_count,issuer_id LIMIT '.$limit)->result_array();foreach($policies as$p){try{$sender->rotateDiscovery((int)$p['issuer_id']);$counts['queued']+=$sender->discover((int)$p['issuer_id'],(int)$p['configured_by'],$encrypt,$limit);}catch(Throwable $e){$counts['discovery_blocked']++;}}
        $rows=IssuerStorage::query($this->db,"SELECT message_key,issuer_id,actor_id FROM ".$this->p."company_ticket_connect_messages WHERE status='pending' ORDER BY created_at,message_key LIMIT ".$limit)->result_array();foreach($rows as$r){try{$state=$sender->dispatch((int)$r['issuer_id'],(int)$r['actor_id'],$r['message_key'],$decrypt);if(isset($counts[$state]))$counts[$state]++;}catch(Throwable $e){$sender->blockPending((int)$r['issuer_id'],$r['message_key']);$row=IssuerStorage::query($this->db,'SELECT status FROM '.$this->p.'company_ticket_connect_messages WHERE issuer_id=? AND message_key=?',[$r['issuer_id'],$r['message_key']])->row_array();if(in_array($row['status']??'',['uncertain','submitting'],true))$counts['uncertain']++;elseif(($row['status']??'')==='blocked')$counts['blocked']++;}}return$counts;
    }
}
