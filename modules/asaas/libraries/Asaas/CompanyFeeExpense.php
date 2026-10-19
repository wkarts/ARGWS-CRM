<?php

declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyService.php';
require_once APPPATH.'services/CompanyExpenses.php';

/** Explicit tariff expense from an applied canonical receipt; same native expense writer and audit. */
final class CompanyFeeExpense
{
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('asaas_company_prefix_invalid');}
    private function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public function post($issuer,$actor,$origin,$proof,$category):int
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$origin=\IssuerPolicy::id($origin);$proof=\IssuerPolicy::id($proof);$category=\IssuerPolicy::id($category);
        CompanySchema::ensure($this->db,$this->p,false);$expenses=new \CompanyExpenses($this->db,$this->p);$tables=array_values(array_unique(array_merge(CompanyService::tables(),$expenses->tables(),(new \CompanyInvoiceBalance($this->db,$this->p))->tables(),$this->db->table_exists($this->p.'bb_origins')?['bb_origins']:[])));
        return \IssuerTransaction::run($this->db,$this->p,$tables,function()use($issuer,$actor,$origin,$proof,$category,$expenses):int{
            $acl=new \IssuerResourceAccess($this->db,$this->p);if(!$acl->can($issuer,$actor,'expenses','view')||!$acl->can($issuer,$actor,'expenses','create'))throw new \DomainException('company_expense_denied');
            $claim=(new CompanyService($this->db,$this->p))->receiptClaim($issuer,$actor,$origin,$proof);
            $receipt=$this->rows('SELECT * FROM '.$this->p.'asaas_company_receipts WHERE origin_id=? AND issuer_id=? FOR UPDATE',[$origin,$issuer])[0]??null;
            $stored=$this->rows('SELECT result_json,evidence_hash FROM '.$this->p.'asaas_company_proofs WHERE id=? AND origin_id=? FOR UPDATE',[$proof,$origin])[0]??null;
            if(!$receipt||!$stored||$receipt['evidence_hash']!==$claim['evidence']||$receipt['canonical_key']!==$claim['key'])throw new \DomainException('asaas_company_fee_receipt_required');
            $safe=json_decode($stored['result_json'],true,32,JSON_THROW_ON_ERROR);$cents=\app\services\InvoicePaymentWriter::minorUnits($safe['fee']);if($cents<1)throw new \DomainException('asaas_company_fee_not_applicable');
            $date=$safe['credit_date']!==''?$safe['credit_date']:$safe['payment_date'];$parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);$today=(new \DateTimeImmutable('now',new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
            if(!$parsed||$parsed->format('Y-m-d')!==$date||$date>$today)throw new \DomainException('asaas_company_fee_date_requires_review');
            $key=hash('sha256','company-asaas-fee:'.$claim['key']);$evidence=hash('sha256',$claim['evidence'].':'.$safe['fee'].':'.$date);
            $old=$this->rows('SELECT * FROM '.$this->p.'asaas_company_fee_expenses WHERE origin_id=? OR canonical_key=? FOR UPDATE',[$origin,$key]);
            if($old){if(count($old)!==1||(int)$old[0]['issuer_id']!==$issuer||$old[0]['canonical_key']!==$key||$old[0]['evidence_hash']!==$evidence)throw new \DomainException('asaas_company_fee_conflict');
                $expense=$this->rows('SELECT id,category_id,total_cents,state FROM '.$this->p.'company_expenses WHERE id=? AND issuer_id=? FOR UPDATE',[$old[0]['expense_id'],$issuer])[0]??null;
                if(!$expense||(int)$expense['category_id']!==$category||(int)$expense['total_cents']!==$cents||$expense['state']!=='recorded')throw new \DomainException('asaas_company_fee_conflict');return (int)$expense['id'];
            }
            $id=$expenses->createInOwnedTransaction($issuer,$actor,['category_id'=>$category,'client_id'=>0,'project_id'=>0,'currency'=>'BRL','date'=>$date,'description'=>'Tarifa Asaas da cobrança recebida','note'=>'Evidência canônica '.$claim['evidence'].'. Bruto '.$safe['value'].'; líquido '.$safe['net_value'].'; tarifa '.$safe['fee'].'.','amount'=>$safe['fee'],'tax_rate'=>'0.00'],$key);
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_fee_expenses(origin_id,issuer_id,proof_id,payment_id,expense_id,canonical_key,evidence_hash,created_by,created_at) VALUES(?,?,?,?,?,?,?,?,?)',[$origin,$issuer,$proof,$receipt['payment_id'],$id,$key,$evidence,$actor,gmdate('Y-m-d H:i:s')]);
            if(!$this->rows('SELECT origin_id FROM '.$this->p.'asaas_company_fee_expenses WHERE origin_id=? AND issuer_id=? AND expense_id=? AND evidence_hash=?',[$origin,$issuer,$id,$evidence]))throw new \RuntimeException('issuer_asaas_fee_unconfirmed');return $id;
        });
    }
}
