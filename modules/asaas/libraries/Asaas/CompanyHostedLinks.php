<?php

declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyService.php';
require_once APPPATH.'services/CompanyPortal.php';

/** A hosted URL is released only for an explicitly published canonical proof and private financial portal identity. */
final class CompanyHostedLinks
{
    private CompanyService $service;
    public function __construct(private $db,private string $p){$this->service=new CompanyService($db,$p);}
    private function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public function tables(bool $portal=false):array{return array_merge($this->service->tablesForRun(),CompanyCheckoutSchema::tables(),['asaas_company_remote_operations'],$this->db->table_exists($this->p.'bb_origins')?['bb_origins']:[],$portal?['contacts','contact_permissions']:[]);}
    private function run(callable $fn,bool $portal=false){$this->service->checkoutModule();return \IssuerTransaction::run($this->db,$this->p,$this->tables($portal),$fn);}
    private function link(int $issuer,int $actor,int $origin,?int $proofId=null):array
    {
        [$row,$meta,$account,$parent]=$this->service->checkoutSourceInOwnedTransaction($issuer,$actor,$origin);
        if($meta['billing_type']!=='CREDIT_CARD'||!in_array((int)$parent['status'],[1,4],true)||$row['state']!=='issued')throw new \DomainException('asaas_company_checkout_unavailable');
        if($this->rows('SELECT origin_id FROM '.$this->p.'asaas_company_remote_operations WHERE origin_id=? FOR UPDATE',[$origin])||$this->rows('SELECT id FROM '.$this->p.'invoicepaymentrecords WHERE invoiceid=? FOR UPDATE',[$row['invoice_id']])||$this->rows('SELECT id FROM '.$this->p.'credits WHERE invoice_id=? FOR UPDATE',[$row['invoice_id']])||$this->rows('SELECT origin_id FROM '.$this->p.'asaas_company_receipts WHERE origin_id=? FOR UPDATE',[$origin]))throw new \DomainException('asaas_company_checkout_unavailable');
        if($this->rows("SELECT id FROM {$this->p}asaas_company_events WHERE account_row=? AND remote_id=? AND (state IN ('conflict','review_required') OR event_type IN ('PAYMENT_CONFIRMED','PAYMENT_RECEIVED','PAYMENT_RECEIVED_IN_CASH')) FOR UPDATE",[$account['id'],$row['remote_id']]))throw new \DomainException('asaas_company_checkout_unavailable');
        $proof=$this->rows('SELECT * FROM '.$this->p.'asaas_company_proofs WHERE origin_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE',[$origin])[0]??null;
        if(!$proof||$proof['operation']!=='query'||$proof['account_revision']!==$account['revision']||($proofId!==null&&((int)$proof['id']!==$proofId||$proof['created_at']<gmdate('Y-m-d H:i:s',time()-300))))throw new \DomainException('asaas_company_checkout_fresh_query_required');
        $safe=json_decode($proof['result_json'],true,32,JSON_THROW_ON_ERROR);
        if(!hash_equals($proof['evidence_hash'],hash('sha256',json_encode($safe,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)))||!in_array($safe['status']??'',['PENDING','OVERDUE'],true)||($safe['adverse']??true)||($safe['deleted']??true))throw new \DomainException('asaas_company_checkout_unavailable');
        $url=HostedCardCheckout::verifiedUrl(['id'=>$row['remote_id'],'billingType'=>'CREDIT_CARD','externalReference'=>$parent['hash'],'invoiceUrl'=>$safe['checkout_url']??null,'deleted'=>false],$account['environment'],$parent['hash']);
        return ['origin_id'=>$origin,'invoice_id'=>(int)$row['invoice_id'],'client_id'=>(int)$parent['clientid'],'proof_id'=>(int)$proof['id'],'account_revision'=>$account['revision'],'url'=>$url,'url_hash'=>hash('sha256',$url),'confirmed_at'=>$proof['created_at']];
    }
    public function publish($issuer,$actor,$origin,$proof,bool $enabled):void
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$origin=\IssuerPolicy::id($origin);$proof=\IssuerPolicy::id($proof);
        $this->run(function()use($issuer,$actor,$origin,$proof,$enabled){(new \IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->service->authorize($issuer,$actor,'issue');
            $old=$this->rows('SELECT * FROM '.$this->p.'asaas_company_hosted_links WHERE origin_id=? AND issuer_id=? FOR UPDATE',[$origin,$issuer])[0]??null;$now=gmdate('Y-m-d H:i:s');
            if(!$enabled){if(!$this->rows('SELECT id FROM '.$this->p.'asaas_payment_origins WHERE id=? AND issuer_id=? FOR UPDATE',[$origin,$issuer]))throw new \DomainException('asaas_company_checkout_unavailable');if($old)\IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_company_hosted_links SET enabled=0,updated_at=? WHERE origin_id=? AND issuer_id=?',[$now,$origin,$issuer]);}
            else{$link=$this->link($issuer,$actor,$origin,$proof);if($old)\IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_company_hosted_links SET proof_id=?,account_revision=?,url_hash=?,published_by=?,enabled=1,updated_at=? WHERE origin_id=? AND issuer_id=?',[$proof,$link['account_revision'],$link['url_hash'],$actor,$now,$origin,$issuer]);else \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_hosted_links(origin_id,issuer_id,proof_id,account_revision,url_hash,published_by,enabled,created_at,updated_at) VALUES(?,?,?,?,?,?,1,?,?)',[$origin,$issuer,$proof,$link['account_revision'],$link['url_hash'],$actor,$now,$now]);}
            $written=$this->rows('SELECT enabled FROM '.$this->p.'asaas_company_hosted_links WHERE origin_id=? AND issuer_id=?',[$origin,$issuer])[0]??null;if(($written!==null&&(int)$written['enabled']!==($enabled?1:0))||($enabled&&$written===null))throw new \RuntimeException('issuer_asaas_checkout_unconfirmed');
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,?,?)',[$actor,$enabled?'company_asaas_checkout_published':'company_asaas_checkout_hidden',$issuer,$origin,$now]);
        });
    }
    public function staffLink($issuer,$actor,$origin):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$origin=\IssuerPolicy::id($origin);return $this->run(fn()=>$this->link($issuer,$actor,$origin));
    }
    private function portalOwned(\CompanyPortal $portal,array $session,int $issuer,int $invoice,bool $audit):array
    {

            $who=$portal->invoiceIdentity($session,$issuer,true);if(!$who||count($this->rows('SELECT id FROM '.$this->p.'contact_permissions WHERE userid=? AND permission_id=1 FOR UPDATE',[$who['contact_id']]))!==1)throw new \DomainException('asaas_company_checkout_unavailable');
            // Strict private owner and requested document are authorized before provider lookup.
            if(!$this->rows('SELECT i.id FROM '.$this->p.'invoices i JOIN '.$this->p.'issuers_clients o ON o.client_id=i.clientid WHERE i.id=? AND i.issuer_id=? AND i.clientid=? AND o.owner_issuer_id=? AND i.status IN (1,4) FOR UPDATE',[$invoice,$issuer,$who['client_id'],$issuer]))throw new \DomainException('asaas_company_checkout_unavailable');
            $publication=$this->rows('SELECT l.origin_id,l.url_hash,l.account_revision,l.published_by FROM '.$this->p.'asaas_company_hosted_links l JOIN '.$this->p.'asaas_payment_origins o ON o.id=l.origin_id AND o.issuer_id=l.issuer_id WHERE l.issuer_id=? AND o.invoice_id=? AND l.enabled=1 FOR UPDATE',[$issuer,$invoice])[0]??null;if(!$publication)throw new \DomainException('asaas_company_checkout_unavailable');
            $link=$this->link($issuer,(int)$publication['published_by'],(int)$publication['origin_id']);if($link['invoice_id']!==$invoice||$link['client_id']!==$who['client_id']||!hash_equals($publication['url_hash'],$link['url_hash'])||!hash_equals($publication['account_revision'],$link['account_revision']))throw new \DomainException('asaas_company_checkout_unavailable');
            if($audit)\IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(0,?,?,?,?)',['company_asaas_checkout_portal_opened',$issuer,$invoice,gmdate('Y-m-d H:i:s')]);
            return ['url'=>$link['url'],'confirmed_at'=>$link['confirmed_at']];
    }
    public function portalLink(\CompanyPortal $portal,array $session,$issuer,$invoice):array
    {
        $issuer=\IssuerPolicy::id($issuer);$invoice=\IssuerPolicy::id($invoice);return $this->run(fn()=>$this->portalOwned($portal,$session,$issuer,$invoice,true),true);
    }
    public function portalPage(\CompanyPortal $portal,array $session,$issuer,array $invoices):array
    {
        $issuer=\IssuerPolicy::id($issuer);if(count($invoices)>50)throw new \DomainException('asaas_company_checkout_unavailable');$ids=array_map([\IssuerPolicy::class,'id'],$invoices);
        return $this->run(function()use($portal,$session,$issuer,$ids){$available=[];foreach($ids as $id){try{$this->portalOwned($portal,$session,$issuer,$id,false);$available[$id]=true;}catch(\DomainException $denied){}}return $available;},true);
    }
}
