<?php

declare(strict_types=1);
require_once __DIR__.'/CompanySalesBase.php';require_once __DIR__.'/CompanyBillingInvoiceLifecycle.php';
require_once __DIR__.'/CompanyProjects.php';
require_once __DIR__.'/CompanyTaskBillingSchema.php';
require_once __DIR__.'/CompanyTaskBillingAmounts.php';require_once __DIR__.'/CompanyProjectBillingRates.php';require_once __DIR__.'/CompanyProjectFixedBillingFence.php';
/** Immutable native source provenance, canonical invoice receipt and current authorization. */
abstract class CompanyTaskBillingStorage extends CompanySalesBase
{
    public function tables():array
    {
        $t=array_merge(['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','issuers_clients','issuers_client_links','clients','issuers_audit','currencies','company_currency_units','options','company_invoice_requests','invoices','issuers_invoice_documents'],array_keys(CompanyProjectsSchema::definitions($this->prefix)),['company_task_billing_origins','company_task_billing_links']);
        if($this->db->table_exists($this->prefix.'company_portal_task_origins'))$t[]='company_portal_task_origins';if($this->db->table_exists($this->prefix.'company_project_billing_rates'))$t=array_merge($t,(new CompanyProjectBillingRates($this->db,$this->prefix))->tables());return array_values(array_unique(array_merge($t,CompanyProjectFixedBillingFence::tables($this->db,$this->prefix),(new CompanyBillingInvoiceLifecycle($this->db,$this->prefix))->tables())));
    }
    protected function billingAuth(int $issuer,int $actor):void
    {
        $acl=new IssuerResourceAccess($this->db,$this->prefix);foreach([['tasks','view'],['tasks','edit'],['projects','view'],['customers','view'],['invoices','view'],['invoices','create']] as [$f,$c])if(!$acl->can($issuer,$actor,$f,$c))throw new DomainException('company_task_billing_denied');
    }
    protected function billingTx(int $issuer,int $actor,callable $work)
    {
        CompanyTaskBillingSchema::assertValid($this->db,$this->prefix);return IssuerTransaction::run($this->db,$this->prefix,$this->tables(),function()use($issuer,$actor,$work){(new IssuerResourceAccess($this->db,$this->prefix))->lockFinancialIdentity($issuer,$actor);$this->billingAuth($issuer,$actor);$r=$work();$this->billingAuth($issuer,$actor);return $r;});
    }
    protected function task(int $issuer,int $id):array
    {
        $r=$this->rows('SELECT * FROM '.$this->prefix.'company_tasks WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$id])[0]??null;if(!$r)throw new DomainException('company_task_billing_denied');return $r;
    }
    protected function portal(int $issuer,int $id):?array
    {
        if(!$this->db->table_exists($this->prefix.'company_portal_task_origins'))return null;
        return $this->rows('SELECT issuer_id,project_id,task_id,client_id,contact_id,author_name,billing_state FROM '.$this->prefix.'company_portal_task_origins WHERE issuer_id=? AND task_id=? FOR UPDATE',[$issuer,$id])[0]??null;
    }
    protected function source(int $issuer,int $id):array
    {
        $r=$this->task($issuer,$id);if($r['status']!=='done')throw new DomainException('company_task_billing_not_ready');$p=$this->rows('SELECT id,client_id,name,status FROM '.$this->prefix.'company_projects WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$r['project_id']])[0]??null;if(!$p||!in_array($p['status'],['active','completed'],true))throw new DomainException('company_task_billing_reference_denied');
        $timers=$this->rows('SELECT id,actor_id,started_at,stopped_at,seconds FROM '.$this->prefix.'company_task_timers WHERE issuer_id=? AND task_id=? ORDER BY id LIMIT 501 FOR UPDATE',[$issuer,$id]);
        if(!$timers||count($timers)>500)throw new DomainException('company_task_billing_timer');foreach($timers as $t)if($t['stopped_at']===null)throw new DomainException('company_task_billing_timer');
        $portal=$this->portal($issuer,$id);if($portal&&((int)$portal['project_id']!==(int)$p['id']||(int)$portal['client_id']!==(int)$p['client_id']||!in_array($portal['billing_state'],['unbilled','converting','billed'],true)))throw new DomainException('company_task_billing_reference_denied');if($portal)unset($portal['billing_state']);
        // Project rename/status and task comments do not change already captured billing quantities.
        return ['issuer_id'=>$issuer,'task_id'=>$id,'project_id'=>(int)$p['id'],'client_id'=>(int)$p['client_id'],'project_name'=>$p['name'],'name'=>$r['name'],'description'=>$r['description'],'assignee_id'=>(int)$r['assignee_id'],'due_date'=>$r['due_date'],'checklist_json'=>$r['checklist_json'],'timers'=>$timers,'portal_origin'=>$portal];
    }
    protected function references(int $issuer,int $actor,array $r):void
    {
        // The first profile requires private ownership. No inferred visibility/share grants.
        if(!$this->rows('SELECT c.userid FROM '.$this->prefix.'clients c JOIN '.$this->prefix.'issuers_clients o ON o.client_id=c.userid WHERE c.userid=? AND c.active=1 AND o.owner_issuer_id=? FOR UPDATE',[$r['client_id'],$issuer]))throw new DomainException('company_task_billing_reference_denied');
    }
    protected function portalState(int $issuer,int $id,string $from,string $to):void
    {
        $r=$this->portal($issuer,$id);if(!$r)return;if($r['billing_state']===$to)return;if($r['billing_state']!==$from)throw new DomainException('company_task_billing_conflict');IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'company_portal_task_origins SET billing_state=? WHERE issuer_id=? AND task_id=? AND billing_state=?',[$to,$issuer,$id,$from]);if(($this->portal($issuer,$id)['billing_state']??null)!==$to)throw new RuntimeException('issuer_task_billing_unconfirmed');
    }
    protected function link(int $issuer,int $id):?array{return $this->rows('SELECT * FROM '.$this->prefix.'company_task_billing_links WHERE task_id=? AND issuer_id=? FOR UPDATE',[$id,$issuer])[0]??null;}
    protected function origin(int $issuer,int $id,int $origin):array
    {
        $r=$this->rows('SELECT * FROM '.$this->prefix.'company_task_billing_origins WHERE id=? AND issuer_id=? AND task_id=? FOR UPDATE',[$origin,$issuer,$id])[0]??null;
        if(!$r||!hash_equals($r['source_hash'],hash('sha256',$r['source_json']))||!hash_equals($r['origin_hash'],hash('sha256',$r['context_json']))||!hash_equals($r['payload_hash'],hash('sha256',$r['payload_json'])))throw new RuntimeException('issuer_task_billing_unconfirmed');return $r;
    }
    protected function confirmedInvoice(int $issuer,int $id,array $link):void
    {
        $o=$this->origin($issuer,$id,(int)$link['origin_id']);$receipt=$this->rows('SELECT r.invoice_id,r.actor_id,r.input_hash FROM '.$this->prefix.'company_invoice_requests r JOIN '.$this->prefix.'invoices i ON i.id=r.invoice_id AND i.issuer_id=r.issuer_id WHERE r.request_key=? AND r.issuer_id=? FOR UPDATE',[self::canonicalKey($issuer,$id),$issuer])[0]??null;
        if(!$receipt||(int)$receipt['invoice_id']!==(int)$link['invoice_id']||(int)$receipt['actor_id']!==(int)$link['conversion_actor_id'])throw new RuntimeException('issuer_task_billing_unconfirmed');$payload=$this->decode($o['payload_json']);$canonical=array_merge(CompanyInvoiceAmounts::calculate($payload['items'],array_intersect_key($payload,array_flip(['discount_mode','discount_value','discount_type','adjustment']))),['client_id'=>$payload['client_id'],'date'=>$payload['date'],'due_date'=>$payload['due_date'],'currency'=>$payload['currency'],'note'=>$payload['note']]);if(!hash_equals($receipt['input_hash'],hash('sha256',json_encode($canonical,JSON_THROW_ON_ERROR))))throw new RuntimeException('issuer_task_billing_unconfirmed');$this->assertOriginOrReceipt($issuer,(int)$receipt['actor_id'],$this->decode($o['payload_json']),$o['origin_hash'],$this->decode($o['context_json']),self::canonicalKey($issuer,$id));
    }
    protected static function canonicalKey(int $issuer,int $id):string{return hash('sha256','company-task-invoice:'.$issuer.':'.$id);}
    protected function confirmLink(int $issuer,int $id,array $expected):void{$r=$this->link($issuer,$id);foreach($expected as $k=>$v)if(!$r||(string)$r[$k]!== (string)$v)throw new RuntimeException('issuer_task_billing_unconfirmed');}
    protected function billingAudit(int $issuer,int $actor,int $id,string $event,int $origin,?int $invoice=null):void
    {
        $json=self::encode(['origin_id'=>$origin,'invoice_id'=>$invoice]);$this->insert('issuers_audit',['issuer_id'=>$issuer,'actor_id'=>$actor,'subject_id'=>$id,'event'=>$event,'created_at'=>gmdate('Y-m-d H:i:s'),'metadata_json'=>$json]);$audit=(int)$this->db->insert_id();if(!$this->rows('SELECT id FROM '.$this->prefix.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=?',[$audit,$issuer,$actor,$id,$event,$json]))throw new RuntimeException('issuer_task_billing_unconfirmed');
    }
    protected function guard(int $issuer,int $actor,int $id,int $origin):array
    {
        IssuerTransaction::assertOwned($this->db,$this->prefix,$this->tables());$this->billingAuth($issuer,$actor);$link=$this->link($issuer,$id);if(!$link||!in_array($link['state'],['converting','converted'],true)||(int)$link['origin_id']!==$origin||(int)$link['conversion_actor_id']!==$actor)throw new DomainException('company_task_billing_conflict');$o=$this->origin($issuer,$id,$origin);$old=$this->decode($o['source_json']);CompanyProjectFixedBillingFence::assertAvailableInOwned($this->db,$this->prefix,$issuer,$actor,$old['project_id']);if(isset($old['billing_profile']['project_rate_origin'])){$captured=$old['billing_profile']['project_rate_origin'];$price=(new CompanyProjectBillingRates($this->db,$this->prefix))->inOwned($issuer,$actor,$old['project_id'],$captured['id']);if(!hash_equals($captured['payload_hash'],$price['payload_hash'])||$captured['payload']!==$price['payload'])throw new DomainException('company_project_rate_stale');}$current=$this->source($issuer,$id);$current['project_name']=$old['project_name'];$current['billing_profile']=$old['billing_profile'];if(!hash_equals($o['source_hash'],self::hash($current)))throw new DomainException('company_task_billing_stale');$this->references($issuer,$actor,$current);
        $portal=$this->portal($issuer,$id);if($portal&&$portal['billing_state']!==($link['state']==='converted'?'billed':'converting'))throw new DomainException('company_task_billing_conflict');
        $this->assertOriginOrReceipt($issuer,$actor,$this->decode($o['payload_json']),$o['origin_hash'],$this->decode($o['context_json']),self::canonicalKey($issuer,$id));return $o;
    }
}
