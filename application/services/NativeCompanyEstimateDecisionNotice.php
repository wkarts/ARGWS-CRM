<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyNotifications.php';
require_once __DIR__.'/NativeCompanyResourceReferences.php';
require_once __DIR__.'/CompanySalesPreferences.php';
require_once __DIR__.'/CompanyInvoiceLines.php';

/** Staff notifications are derived from a committed-in-frame contact decision. */
final class NativeCompanyEstimateDecisionNotice
{
    public function __construct(private $db,private string $p){}
    private function rows(string $q,array $args=[]):array{return IssuerStorage::query($this->db,$q,$args)->result_array();}
    private function event(int $issuer,int $audit):array
    {
        $r=$this->rows("SELECT subject_id,actor_id,metadata_json FROM {$this->p}issuers_audit WHERE issuer_id=? AND id=? AND event='company_estimate_portal_decision'",[$issuer,$audit])[0]??null;if(!$r||(int)$r['actor_id']!==0)throw new DomainException('native_estimate_notice_denied');
        $meta=json_decode($r['metadata_json'],true,32,JSON_THROW_ON_ERROR);if(!is_array($meta)||!in_array($meta['decision']??null,['accepted','declined'],true)||!is_int($meta['contact_id']??null)||!is_int($meta['client_id']??null)||!is_string($meta['request_key']??null)||!preg_match('/^[a-f0-9]{64}$/D',$meta['request_key']))throw new DomainException('native_estimate_notice_denied');
        $doc=$this->rows("SELECT * FROM {$this->p}company_sales_documents WHERE issuer_id=? AND id=? AND client_id=? AND kind='estimates'",[$issuer,$r['subject_id'],$meta['client_id']])[0]??null;if(!$doc||($meta['decision']==='accepted'?!in_array($doc['state'],['accepted','converting','converted'],true):$doc['state']!=='declined'))throw new DomainException('native_estimate_notice_denied');
        $contact=$this->rows('SELECT id FROM '.$this->p.'contacts WHERE id=? AND userid=?',[$meta['contact_id'],$meta['client_id']]);if(count($contact)!==1)throw new DomainException('native_estimate_notice_denied');
        $payload=json_decode($doc['payload_json'],true,32,JSON_THROW_ON_ERROR);if(!is_array($payload)||(int)($payload['client_id']??0)!==$meta['client_id']||!hash_equals($doc['input_hash'],hash('sha256',json_encode([$doc['kind'],$payload],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))))throw new DomainException('native_estimate_notice_denied');$amounts=CompanyInvoiceLines::calculate($payload['items'],array_intersect_key($payload,array_flip(['discount_mode','discount_value','discount_type','adjustment','calculation_contract'])));if(CompanyInvoiceAmounts::minor((string)$doc['total'])!==CompanyInvoiceAmounts::minor($amounts['total']))throw new DomainException('native_estimate_notice_denied');
        $context=CompanySalesPreferences::context($doc['context_json'],$doc['origin_hash'],$issuer,$meta['client_id']);$number=CompanySalesPreferences::presentation('estimates',$doc['number'],$context,'',$payload['date']??null)['formatted_number'];
        $recipients=[(int)$doc['actor_id']];$agent=(int)($payload['native_fields']['sale_agent']??0);if($agent>0)$recipients[]=$agent;$recipients=array_values(array_unique($recipients));sort($recipients,SORT_NUMERIC);
        return ['meta'=>$meta,'document_id'=>(int)$doc['id'],'number'=>$number,'recipients'=>$recipients];
    }
    public function source(int $issuer,int $recipient,int $audit):array
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($issuer,$recipient,'estimates','view'))throw new DomainException('native_estimate_notice_denied');$event=$this->event($issuer,$audit);if(!in_array($recipient,$event['recipients'],true))throw new DomainException('native_estimate_notice_denied');
        $canonical=NativeCompanyResourceId::encode('company_estimate',$event['document_id']);$resource=NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$recipient,'estimate',$canonical,'view');$decision=$event['meta']['decision'];
        if($this->event($issuer,$audit)!==$event||!$acl->can($issuer,$recipient,'estimates','view'))throw new DomainException('native_estimate_notice_denied');
        return ['actor_id'=>null,'actor_kind'=>'contact','contact_actor_id'=>$event['meta']['contact_id'],'resource_id'=>$canonical,'url'=>$resource['url'],'title'=>$decision==='accepted'?'Orçamento aceito':'Orçamento recusado','body'=>$event['number'],'revision'=>1];
    }
    public function queueInOwned(int $issuer,int $audit,CompanyPortalSales $writer):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,NativeCompanyNotifications::tables('estimate_decision'));NativeCompanyHeaderSchema::assertValid($this->db,$this->p);$writer->assertDecisionInOwned($this->db,$this->p,$issuer,$audit);$event=$this->event($issuer,$audit);$ids=[];$acl=new IssuerResourceAccess($this->db,$this->p);
        foreach($event['recipients'] as$recipient){if(!$acl->can($issuer,$recipient,'estimates','view'))continue;$source=$this->source($issuer,$recipient,$audit);$eventKey='estimate_decision:'.$audit;$params=[$issuer,$recipient,$eventKey];$row=$this->rows('SELECT * FROM '.$this->p.NativeCompanyHeaderSchema::NOTIFICATIONS.' WHERE issuer_id=? AND recipient_id=? AND event_key=?',$params)[0]??null;
            if(!$row){IssuerStorage::query($this->db,'INSERT INTO '.$this->p.NativeCompanyHeaderSchema::NOTIFICATIONS.' (issuer_id,recipient_id,actor_kind,actor_id,contact_actor_id,source_kind,source_id,resource_id,event_key,title,body,created_at) VALUES(?,?,?,NULL,?,?,?,?,?,?,?,?)',[$issuer,$recipient,'contact',$event['meta']['contact_id'],'estimate_decision',$audit,$source['resource_id'],$eventKey,$source['title'],$source['body'],gmdate('Y-m-d H:i:s')]);$row=$this->rows('SELECT * FROM '.$this->p.NativeCompanyHeaderSchema::NOTIFICATIONS.' WHERE issuer_id=? AND recipient_id=? AND event_key=?',$params)[0]??null;}
            if(!$row)throw new RuntimeException('issuer_notification_write_unconfirmed');foreach(['actor_kind'=>'contact','contact_actor_id'=>$event['meta']['contact_id'],'source_kind'=>'estimate_decision','source_id'=>$audit,'resource_id'=>$source['resource_id'],'title'=>$source['title'],'body'=>$source['body']]as$key=>$value)if((string)($row[$key]??'')!==(string)$value)throw new DomainException('issuer_notification_event_conflict');$this->source($issuer,$recipient,$audit);$ids[]=(int)$row['id'];
        }
        if($this->event($issuer,$audit)!==$event)throw new DomainException('native_estimate_notice_denied');$writer->assertDecisionInOwned($this->db,$this->p,$issuer,$audit);return$ids;
    }
}
