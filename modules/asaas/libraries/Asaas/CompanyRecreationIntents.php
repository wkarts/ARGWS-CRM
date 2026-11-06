<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyRecreationSchema.php';
require_once __DIR__.'/CompanyRemoteOperations.php';
/** Reserve only after a native cancellation and fresh deleted/unpaid proof. No outbound request. */
final class CompanyRecreationIntents
{
    public function __construct(private $db,private string $p){}
    public function reserve(int $issuer,int $actor,int $invoice,int $origin,string $revision,string $key):array
    {
        foreach([$issuer,$actor,$invoice,$origin] as $id)\IssuerPolicy::id($id);
        foreach([$revision,$key] as $value)if(!preg_match('/^[a-f0-9]{64}$/D',$value))throw new \DomainException('asaas_recreation_invalid');
        CompanyRecreationSchema::ensure($this->db,$this->p,false);
        $remote=new CompanyRemoteOperations($this->db,$this->p);$service=new CompanyService($this->db,$this->p);
        $writer=new \CompanyInvoices($this->db,$this->p);
        return \IssuerTransaction::run($this->db,$this->p,$writer->transactionParticipants(array_merge($remote->tables(),[CompanyRecreationSchema::TABLE])),function()use($issuer,$actor,$invoice,$origin,$revision,$key,$remote,$service,$writer):array{
            (new \IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);
            $service->authorize($issuer,$actor,'remote_remove');$service->authorize($issuer,$actor,'issue');
            $module=$this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]??[];
            if(version_compare($module['installed_version']??'0','1.6.4','<'))throw new \DomainException('asaas_recreation_upgrade_required');
            $row=$this->rows('SELECT status FROM '.$this->p.'invoices WHERE id=? AND issuer_id=? FOR UPDATE',[$invoice,$issuer])[0]??null;
            if(!$row||(int)$row['status']!==5||!hash_equals($revision,$writer->revision($issuer,$actor,$invoice)))throw new \DomainException('asaas_recreation_stale');
            $receipts=$this->rows('SELECT metadata_json FROM '.$this->p.'issuers_audit WHERE issuer_id=? AND subject_id=? AND event=? FOR UPDATE',[$issuer,$invoice,'company_asaas_invoice_cancelled']);
            if(count($receipts)!==1)throw new \DomainException('asaas_recreation_cancellation_required');
            $receipt=json_decode($receipts[0]['metadata_json'],true,16,JSON_THROW_ON_ERROR);
            if(($receipt['invoice_id']??null)!==$invoice||($receipt['origin_id']??null)!==$origin)throw new \DomainException('asaas_recreation_cancellation_required');
            // Includes receipt/card/job/adverse history checks and a proof no older than five minutes.
            $remote->assertCancellationInOwned($issuer,$actor,$invoice,$origin);
            $proof=$this->rows('SELECT id,account_revision FROM '.$this->p.'asaas_company_proofs WHERE origin_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE',[$origin])[0];
            $existing=$this->rows('SELECT * FROM '.$this->p.CompanyRecreationSchema::TABLE.' WHERE origin_id=? OR request_key=? FOR UPDATE',[$origin,$key]);
            if($existing){
                if(count($existing)!==1)throw new \DomainException('asaas_recreation_conflict');$saved=$existing[0];
                foreach(['origin_id'=>$origin,'issuer_id'=>$issuer,'invoice_id'=>$invoice,'actor_id'=>$actor] as $field=>$value)if((int)$saved[$field]!==$value)throw new \DomainException('asaas_recreation_conflict');
                if($saved['request_key']!==$key||$saved['invoice_revision']!==$revision||$saved['account_revision']!==$proof['account_revision']||$saved['state']!=='reserved'||$saved['successor_origin_id']!==null)throw new \DomainException('asaas_recreation_conflict');
                return $this->publicState($saved);
            }
            $now=gmdate('Y-m-d H:i:s');
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.CompanyRecreationSchema::TABLE.'(origin_id,issuer_id,invoice_id,actor_id,invoice_revision,account_revision,request_key,proof_id,state,successor_origin_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,NULL,?,?)',[$origin,$issuer,$invoice,$actor,$revision,$proof['account_revision'],$key,$proof['id'],'reserved',$now,$now]);
            $saved=$this->rows('SELECT * FROM '.$this->p.CompanyRecreationSchema::TABLE.' WHERE origin_id=? AND request_key=?',[$origin,$key])[0]??null;
            if(!$saved)throw new \RuntimeException('issuer_asaas_recreation_unconfirmed');
            return $this->publicState($saved);
        });
    }
    private function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function publicState(array $row):array{return ['origin_id'=>(int)$row['origin_id'],'invoice_id'=>(int)$row['invoice_id'],'state'=>$row['state'],'charge_created'=>false,'invoice_reopened'=>false];}
}
