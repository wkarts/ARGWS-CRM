<?php

declare(strict_types=1);
require_once __DIR__.'/CompanySalesBase.php';require_once __DIR__.'/CompanyTaskBillingAmounts.php';require_once __DIR__.'/CompanyProjectBillingRateSchema.php';require_once __DIR__.'/CompanyProjectsSchema.php';
/** Native project hourly rate, explicitly declared and append-only. Never an ambient option. */
final class CompanyProjectBillingRates extends CompanySalesBase
{
    public function tables():array{return ['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','clients','issuers_clients','issuers_client_links','issuers_audit','company_projects','currencies','company_currency_units','company_project_billing_rates'];}
    private function authorizePrice(int $issuer,int $actor,bool $edit=false):void
    {
        $acl=new IssuerResourceAccess($this->db,$this->prefix);foreach([['projects','view'],['customers','view'],['invoices','view']] as [$f,$c])if(!$acl->can($issuer,$actor,$f,$c))throw new DomainException('company_project_rate_denied');if($edit&&(!$acl->can($issuer,$actor,'projects','edit')||!$acl->can($issuer,$actor,'invoices','create')))throw new DomainException('company_project_rate_denied');
    }
    private function rateTx(int $issuer,int $actor,bool $edit,callable $work)
    {
        CompanyProjectBillingRateSchema::assertValid($this->db,$this->prefix);return IssuerTransaction::run($this->db,$this->prefix,$this->tables(),function()use($issuer,$actor,$edit,$work){(new IssuerResourceAccess($this->db,$this->prefix))->lockFinancialIdentity($issuer,$actor);$this->authorizePrice($issuer,$actor,$edit);$r=$work();$this->authorizePrice($issuer,$actor,$edit);return $r;});
    }
    private function project(int $issuer,int $project):array
    {
        $r=$this->rows('SELECT id,client_id,name,status,revision FROM '.$this->prefix.'company_projects WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$project])[0]??null;if(!$r)throw new DomainException('company_project_rate_denied');return $r;
    }
    /** IDs and fingerprints are revalidated inside the caller's owned transaction. */
    public function inOwned($issuer,$actor,$project,$id,bool $requireLatest=false):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$project=IssuerPolicy::id($project);$id=IssuerPolicy::id($id);IssuerTransaction::assertOwned($this->db,$this->prefix,$this->tables());CompanyProjectBillingRateSchema::assertValid($this->db,$this->prefix);$this->authorizePrice($issuer,$actor);$parent=$this->project($issuer,$project);
        $r=$this->rows('SELECT * FROM '.$this->prefix.'company_project_billing_rates WHERE issuer_id=? AND project_id=? AND id=? FOR UPDATE',[$issuer,$project,$id])[0]??null;if(!$r||!hash_equals($r['payload_hash'],hash('sha256',$r['payload_json'])))throw new DomainException('company_project_rate_stale');$v=$this->decode($r['payload_json']);$unit=(new CompanyCurrencyUnits($this->db,$this->prefix))->resolveRead($v['currency_id']??null);
        if(($v['actor_id']??null)!==(int)$r['actor_id']||($v['request_key']??null)!==$r['request_key']||($v['input_hash']??null)!==$r['input_hash']||($v['recorded_at']??null)!==$r['created_at']||($v['issuer_id']??null)!==$issuer||($v['project_id']??null)!==$project||($v['client_id']??null)!==(int)$parent['client_id']||($v['basis']??null)!=='project_hourly_native_2dp_v1'||($v['currency_iso']??null)!==$unit['iso_code']||($v['currency_basis']??null)!==$unit['basis'])throw new DomainException('company_project_rate_stale');$profile=CompanyTaskBillingAmounts::profile($v['rate']??null,$v['round_mode']??null,$v['round_minutes']??null);foreach(['rate','round_mode','round_minutes'] as $f)if($v[$f]!==$profile[$f])throw new DomainException('company_project_rate_stale');
        if($requireLatest){$latest=$this->rows('SELECT id FROM '.$this->prefix.'company_project_billing_rates WHERE issuer_id=? AND project_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE',[$issuer,$project])[0]??null;if((int)($latest['id']??0)!==$id)throw new DomainException('company_project_rate_stale');}
        $this->authorizePrice($issuer,$actor);return ['id'=>$id,'payload_hash'=>$r['payload_hash'],'payload'=>$v,'project_revision'=>(int)$parent['revision'],'project_name'=>$parent['name']];
    }
    public function declareHourly($issuer,$actor,$project,$revision,array $input,string $key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$project=IssuerPolicy::id($project);$revision=IssuerPolicy::id($revision);self::key($key);if(array_diff(array_keys($input),['currency','rate','round_mode','round_minutes']))throw new DomainException('company_project_rate_invalid');$currency=IssuerPolicy::id($input['currency']??null);$profile=CompanyTaskBillingAmounts::profile($input['rate']??null,$input['round_mode']??null,$input['round_minutes']??null);$hash=self::hash([$project,$revision,$input]);
        return $this->rateTx($issuer,$actor,true,function()use($issuer,$actor,$project,$revision,$currency,$profile,$hash,$key){$parent=$this->project($issuer,$project);$old=$this->rows('SELECT id,issuer_id,project_id,actor_id,input_hash FROM '.$this->prefix.'company_project_billing_rates WHERE request_key=? FOR UPDATE',[$key])[0]??null;if($old){if((int)$old['issuer_id']!==$issuer||(int)$old['project_id']!==$project||(int)$old['actor_id']!==$actor||!hash_equals($old['input_hash'],$hash))throw new DomainException('company_project_rate_conflict');$this->inOwned($issuer,$actor,$project,$old['id']);return (int)$old['id'];}
            if((int)$parent['revision']!==$revision||$parent['status']!=='active')throw new DomainException('company_project_rate_stale');if(!$this->rows('SELECT c.userid FROM '.$this->prefix.'clients c JOIN '.$this->prefix.'issuers_clients o ON o.client_id=c.userid WHERE c.userid=? AND c.active=1 AND o.owner_issuer_id=? FOR UPDATE',[$parent['client_id'],$issuer]))throw new DomainException('company_project_rate_denied');$unit=(new CompanyCurrencyUnits($this->db,$this->prefix))->assertWrite($currency);$payload=array_replace($profile,['basis'=>'project_hourly_native_2dp_v1','actor_id'=>$actor,'request_key'=>$key,'input_hash'=>$hash,'recorded_at'=>gmdate('Y-m-d H:i:s'),'issuer_id'=>$issuer,'project_id'=>$project,'client_id'=>(int)$parent['client_id'],'currency_id'=>$currency,'currency_iso'=>$unit['iso_code'],'currency_basis'=>$unit['basis']]);$this->insert('company_project_billing_rates',['issuer_id'=>$issuer,'project_id'=>$project,'actor_id'=>$actor,'request_key'=>$key,'input_hash'=>$hash,'payload_json'=>self::encode($payload),'payload_hash'=>self::hash($payload),'created_at'=>$payload['recorded_at']]);$id=(int)$this->db->insert_id();$this->inOwned($issuer,$actor,$project,$id,true);$this->priceAudit($issuer,$actor,$project,$id,$key,self::hash($payload));$confirmed=$this->inOwned($issuer,$actor,$project,$id,true);if($confirmed['project_revision']!==$revision||$confirmed['payload']!==$payload||!hash_equals($confirmed['payload_hash'],self::hash($payload))||$this->project($issuer,$project)['status']!=='active')throw new DomainException('company_project_rate_stale');if(!$this->rows('SELECT c.userid FROM '.$this->prefix.'clients c JOIN '.$this->prefix.'issuers_clients o ON o.client_id=c.userid WHERE c.userid=? AND c.active=1 AND o.owner_issuer_id=? FOR UPDATE',[$payload['client_id'],$issuer]))throw new DomainException('company_project_rate_denied');return $id;});
    }
    private function priceAudit(int $issuer,int $actor,int $project,int $id,string $key,string $hash):void
    {
        $metadata=self::encode(['rate_id'=>$id,'request_key'=>$key,'payload_hash'=>$hash]);$this->insert('issuers_audit',['issuer_id'=>$issuer,'actor_id'=>$actor,'subject_id'=>$project,'event'=>'company_project_hourly_rate_declared','metadata_json'=>$metadata,'created_at'=>gmdate('Y-m-d H:i:s')]);$audit=(int)$this->db->insert_id();if(!$this->rows('SELECT id FROM '.$this->prefix.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=?',[$audit,$issuer,$actor,$project,'company_project_hourly_rate_declared',$metadata]))throw new RuntimeException('issuer_project_rate_unconfirmed');
    }
    public function current($issuer,$actor,$project):?array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$project=IssuerPolicy::id($project);return $this->rateTx($issuer,$actor,false,function()use($issuer,$actor,$project){$this->project($issuer,$project);$r=$this->rows('SELECT id FROM '.$this->prefix.'company_project_billing_rates WHERE issuer_id=? AND project_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE',[$issuer,$project])[0]??null;return $r?$this->inOwned($issuer,$actor,$project,$r['id'],true):null;});
    }
}
