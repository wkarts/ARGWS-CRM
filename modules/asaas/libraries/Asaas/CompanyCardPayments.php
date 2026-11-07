<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyService.php';
require_once __DIR__.'/CompanyCardPaymentsSchema.php';
require_once __DIR__.'/CompanyCardPaymentRequest.php';

/** Direct token payment, one dispatch per origin. No PAN/CVV/token persistence or implicit settlement. */
final class CompanyCardPayments
{
    private CompanyService $service;
    public function __construct(private $db,private string $p){$this->service=new CompanyService($db,$p);}
    private function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function tables():array{return array_values(array_unique(array_merge($this->service->tablesForRun(),CompanyPreferencesSchema::tables(),[CompanyCardPaymentsSchema::TABLE])));}
    private function run(int $issuer,int $actor,callable $fn)
    {
        CompanyCardPaymentsSchema::ensure($this->db,$this->p,false);
        return \IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($issuer,$actor,$fn){
            $this->service->authorize($issuer,$actor,'issue');$this->service->authorize($issuer,$actor,'settle');
            $module=$this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]??[];
            if(version_compare($module['installed_version']??'0','1.6.2','<'))throw new \DomainException('asaas_card_upgrade_required');
            return $fn();
        });
    }
    private function ledger(int $issuer,int $origin):?array{return $this->rows('SELECT * FROM '.$this->p.CompanyCardPaymentsSchema::TABLE.' WHERE issuer_id=? AND origin_id=? FOR UPDATE',[$issuer,$origin])[0]??null;}
    private static function publicState(array $row):array{return array_intersect_key($row,array_flip(['origin_id','state','last_code','updated_at']));}
    private function proof(array $origin,array $account):array
    {
        $proof=$this->rows('SELECT * FROM '.$this->p.'asaas_company_proofs WHERE origin_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE',[$origin['id']])[0]??null;
        if(!$proof||$proof['operation']!=='query'||$proof['account_revision']!==$account['revision']||$proof['created_at']<gmdate('Y-m-d H:i:s',time()-300))throw new \DomainException('asaas_card_fresh_query_required');
        $safe=json_decode($proof['result_json'],true,32,JSON_THROW_ON_ERROR);
        if(!hash_equals($proof['evidence_hash'],hash('sha256',json_encode($safe,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR))))throw new \DomainException('asaas_card_proof_invalid');
        return [$proof,$safe];
    }
    public function pay($issuer,$actor,$origin,string $operation,array $input,string $key,callable $decrypt,?callable $transport=null):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$origin=\IssuerPolicy::id($origin);
        if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new \DomainException('asaas_card_request_invalid');
        $payload=CompanyCardPaymentRequest::payload($operation,$input);
        // Native CREDIT_CARD origins currently have no canonical voucher settlement contract.
        if($operation==='payWithCard'&&$payload['cardType']!=='CREDIT')throw new \DomainException('asaas_card_voucher_origin_pending');
        $hash=hash('sha256',json_encode([$origin,$operation,$payload],JSON_THROW_ON_ERROR));
        $existing=$this->run($issuer,$actor,function()use($issuer,$actor,$origin,$key,$hash){$this->service->checkoutSourceInOwnedTransaction($issuer,$actor,$origin);$old=$this->ledger($issuer,$origin);if($old&&(!hash_equals($old['request_key'],$key)||!hash_equals($old['request_hash'],$hash)))throw new \DomainException('asaas_card_attempt_requires_review');return $old;});
        if($existing)return self::publicState($existing);
        $this->service->query($issuer,$actor,$origin,$decrypt,$transport);
        $prepared=$this->run($issuer,$actor,function()use($issuer,$actor,$origin,$operation,$key,$hash){
            [$row,$meta,$account,$invoice]=$this->service->checkoutSourceInOwnedTransaction($issuer,$actor,$origin);
            $old=$this->ledger($issuer,$origin);if($old){if(!hash_equals($old['request_key'],$key)||!hash_equals($old['request_hash'],$hash))throw new \DomainException('asaas_card_attempt_requires_review');return null;}
            if($meta['billing_type']!=='CREDIT_CARD'||$row['state']!=='issued'||!$row['remote_id']||!in_array((int)$invoice['status'],[1,4],true))throw new \DomainException('asaas_card_origin_ineligible');
            (new CompanyPreferences($this->db,$this->p))->snapshotInOwned($issuer,$actor,$account,'CREDIT_CARD',$invoice['formatted_number']);
            [$proof,$safe]=$this->proof($row,$account);
            if(!in_array($safe['status']??'',['PENDING','OVERDUE'],true)||($safe['deleted']??true)||($safe['adverse']??true)||($safe['payment_date']??'')!==''||($safe['credit_date']??'')!=='')throw new \DomainException('asaas_card_origin_ineligible');
            foreach([['invoicepaymentrecords','invoiceid',$row['invoice_id']],['credits','invoice_id',$row['invoice_id']],['asaas_company_receipts','origin_id',$origin],['asaas_company_remote_operations','origin_id',$origin]] as [$table,$column,$id])if($this->db->table_exists($this->p.$table)&&$this->rows('SELECT '.$column.' FROM '.$this->p.$table.' WHERE '.$column.'=? FOR UPDATE',[$id]))throw new \DomainException('asaas_card_financial_history');
            $now=gmdate('Y-m-d H:i:s');
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.CompanyCardPaymentsSchema::TABLE.'(origin_id,issuer_id,actor_id,account_revision,request_key,request_hash,operation,proof_id,state,last_code,created_at,updated_at,sent_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)',[$origin,$issuer,$actor,$account['revision'],$key,$hash,$operation,$proof['id'],'sending','card_dispatch_claimed',$now,$now,$now]);
            $saved=$this->ledger($issuer,$origin);if(!$saved||$saved['request_hash']!==$hash||$saved['state']!=='sending')throw new \RuntimeException('issuer_asaas_card_write_unconfirmed');
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,?,?)',[$actor,'card_dispatch_claimed',$issuer,$origin,$now]);
            return [$row,$account];
        });
        if($prepared===null)return $this->inspect($issuer,$actor,$origin);
        [$row,$account]=$prepared;
        try{
            $secret=$decrypt($account['secret_cipher']);if(!is_string($secret)||$secret==='')throw new \RuntimeException();
            $response=(new CompanyClient($account['environment'],$secret,$transport))->request('POST','payments/'.$row['remote_id'].'/'.$operation,$payload);
            if(($response['id']??null)!==$row['remote_id'])throw new \RuntimeException();
            $this->observe($issuer,$actor,$origin,$key,'awaiting_query','card_response_observed');
        }catch(\Throwable $e){$this->observe($issuer,$actor,$origin,$key,'uncertain','card_dispatch_unconfirmed');return $this->inspect($issuer,$actor,$origin);}
        finally{unset($payload,$input,$secret,$response);}
        return $this->refresh($issuer,$actor,$origin,$decrypt,$transport);
    }
    private function observe(int $issuer,int $actor,int $origin,string $key,string $state,string $code):void
    {
        // The committed claim permits observation after HTTP even if access was revoked in flight.
        \IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($issuer,$actor,$origin,$key,$state,$code){
            $old=$this->ledger($issuer,$origin);if(!$old||!hash_equals($old['request_key'],$key)||(int)$old['actor_id']!==$actor)throw new \DomainException('asaas_card_claim_invalid');
            if($old['state']==='sending')\IssuerStorage::query($this->db,'UPDATE '.$this->p.CompanyCardPaymentsSchema::TABLE.' SET state=?,last_code=?,updated_at=? WHERE origin_id=? AND issuer_id=?',[$state,$code,gmdate('Y-m-d H:i:s'),$origin,$issuer]);
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,?,?)',[$actor,$code,$issuer,$origin,gmdate('Y-m-d H:i:s')]);
        });
    }
    public function page(int $issuer,int $actor):array
    {
        return $this->run($issuer,$actor,function()use($issuer){$out=[];foreach($this->rows('SELECT origin_id,state,last_code,updated_at FROM '.$this->p.CompanyCardPaymentsSchema::TABLE.' WHERE issuer_id=? ORDER BY origin_id DESC LIMIT 200',[$issuer]) as $row)$out[(int)$row['origin_id']]=self::publicState($row);return $out;});
    }
    public function inspect($issuer,$actor,$origin):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$origin=\IssuerPolicy::id($origin);
        return $this->run($issuer,$actor,function()use($issuer,$origin){$row=$this->ledger($issuer,$origin);if(!$row)throw new \DomainException('asaas_card_attempt_unavailable');return self::publicState($row);});
    }
    public function refresh($issuer,$actor,$origin,callable $decrypt,?callable $transport=null):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$origin=\IssuerPolicy::id($origin);$this->inspect($issuer,$actor,$origin);
        $this->service->query($issuer,$actor,$origin,$decrypt,$transport);
        return $this->run($issuer,$actor,function()use($issuer,$actor,$origin){
            [$row,$meta,$account]=$this->service->checkoutSourceInOwnedTransaction($issuer,$actor,$origin);[$proof,$safe]=$this->proof($row,$account);$old=$this->ledger($issuer,$origin);
            $state=in_array($safe['status']??'',['CONFIRMED','RECEIVED'],true)&&!($safe['adverse']??true)&&!($safe['deleted']??true)?'observed':'review_required';
            \IssuerStorage::query($this->db,'UPDATE '.$this->p.CompanyCardPaymentsSchema::TABLE.' SET state=?,last_code=?,updated_at=? WHERE origin_id=? AND issuer_id=?',[$state,'card_canonical_query',gmdate('Y-m-d H:i:s'),$origin,$issuer]);
            return self::publicState($this->ledger($issuer,$origin));
        });
    }
}
