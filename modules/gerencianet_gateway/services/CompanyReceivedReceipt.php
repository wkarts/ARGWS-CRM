<?php
declare(strict_types=1);
namespace Efi;
require_once APPPATH.'services/CompanyReceiptSource.php';
require_once __DIR__.'/CompanyCheckout.php';
/** Exact trusted class: accepts only persisted origin/proof IDs, never a public financial payload. */
final class CompanyReceivedReceipt implements \CompanyReceiptSource
{
    private ?array $captured=null;
    private ?CompanyCheckout $checkout=null;
    public function __construct(private int $origin,private int $proof){\IssuerPolicy::id($origin);\IssuerPolicy::id($proof);}
    public function assertSchema($db,string $prefix):void
    {
        CompanySchema::ensure($db,$prefix,false);$this->checkout=new CompanyCheckout($db,$prefix,new CompanyAccounts($db,$prefix));
    }
    public function tables():array{if($this->checkout===null)throw new \DomainException('efi_company_schema_required');return $this->checkout->tables();}
    private static function rows($db,string $sql,array $args=[]):array{return \IssuerStorage::query($db,$sql,$args)->result_array();}
    public function claim($db,string $p,int $issuer,int $actor):array
    {
        if($this->checkout===null)throw new \DomainException('efi_company_schema_required');
        \IssuerTransaction::assertOwned($db,$p,array_merge($this->tables(),['company_payment_receipts']));
        [$origin,$account,$invoice]=$this->checkout->sourceInOwned($issuer,$actor,$this->origin,'settle');
        if($origin['state']!=='bound')throw new \DomainException('efi_company_receipt_requires_review');
        $proof=self::rows($db,'SELECT * FROM '.$p.'efi_company_proofs WHERE origin_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE',[$this->origin])[0]??null;
        if(!$proof||(int)$proof['id']!==$this->proof||$proof['operation']!=='query'||$proof['state']!=='received_observed'||$proof['account_revision']!==$account['revision'])throw new \DomainException('efi_company_receipt_requires_review');
        $safe=json_decode($proof['result_json'],true,16,JSON_THROW_ON_ERROR);
        if(!is_array($safe)||!hash_equals($proof['evidence_hash'],CompanyProof::hash($safe))||($safe['receipt_eligible']??false)!==true||!preg_match('/^[a-f0-9]{64}$/D',(string)($safe['settlement_hash']??''))||($safe['kind']??null)!==$origin['kind']||$origin['kind']!=='pix'||($safe['provider_id']??null)!==$origin['provider_id']||($safe['currency']??null)!=='BRL'||($safe['amount_minor']??null)!==(int)$origin['amount_minor'])throw new \DomainException('efi_company_proof_conflict');
        foreach(self::rows($db,'SELECT result_json FROM '.$p.'efi_company_proofs WHERE origin_id=? AND operation=\'query\' FOR UPDATE',[$this->origin]) as $prior){$history=json_decode($prior['result_json'],true,16,JSON_THROW_ON_ERROR);if(($history['adverse']??false)===true)throw new \DomainException('efi_company_adverse_history');}
        $date=$safe['date']??null;$parsed=is_string($date)?\DateTimeImmutable::createFromFormat('!Y-m-d',$date):false;
        $today=(new \DateTimeImmutable('now',new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
        if(!$parsed||$parsed->format('Y-m-d')!==$date||$date>$today||$date<$invoice['date'])throw new \DomainException('efi_company_receipt_date_unproven');
        $key=CompanyProof::hash(['company-efi-received',$issuer,$origin['account_hash'],$origin['environment'],$origin['kind'],$origin['provider_id']]);
        $evidence=CompanyProof::hash([$origin['snapshot_hash'],$safe]);
        $old=self::rows($db,'SELECT * FROM '.$p.'efi_company_receipts WHERE origin_id=? OR canonical_key=? FOR UPDATE',[$this->origin,$key]);
        if(count($old)>1||($old&&((int)$old[0]['origin_id']!==$this->origin||(int)$old[0]['issuer_id']!==$issuer||$old[0]['canonical_key']!==$key||$old[0]['evidence_hash']!==$evidence)))throw new \DomainException('efi_company_receipt_conflict');
        if(!$old){
            if($proof['created_at']<gmdate('Y-m-d H:i:s',time()-300)||$proof['created_at']>gmdate('Y-m-d H:i:s'))throw new \DomainException('efi_company_query_stale');
            if(self::rows($db,'SELECT id FROM '.$p.'invoicepaymentrecords WHERE invoiceid=? FOR UPDATE',[$invoice['id']])||self::rows($db,'SELECT id FROM '.$p.'credits WHERE invoice_id=? FOR UPDATE',[$invoice['id']]))throw new \DomainException('efi_company_balance_requires_review');
        }
        $this->captured=['issuer'=>$issuer,'actor'=>$actor,'key'=>$key,'evidence'=>$evidence];
        return ['invoice'=>(int)$invoice['id'],'key'=>$key,'input'=>['amount'=>\EfiChargeLedger::decimal((int)$origin['amount_minor']),'date'=>$date,'reference'=>'EFI-'.$key,'note'=>'Recebimento Efí vinculado à empresa, conta e ambiente, confirmado por consulta autenticada.']];
    }
    public function applied($db,string $p,int $issuer,int $actor,int $payment):void
    {
        $claim=$this->captured;if(!$claim||$claim['issuer']!==$issuer||$claim['actor']!==$actor)throw new \DomainException('efi_company_claim_required');
        $this->checkout->sourceInOwned($issuer,$actor,$this->origin,'settle');
        $old=self::rows($db,'SELECT * FROM '.$p.'efi_company_receipts WHERE origin_id=? FOR UPDATE',[$this->origin])[0]??null;
        if($old){if((int)$old['payment_id']!==$payment||$old['canonical_key']!==$claim['key']||$old['evidence_hash']!==$claim['evidence'])throw new \DomainException('efi_company_receipt_conflict');return;}
        \IssuerStorage::query($db,'INSERT INTO '.$p.'efi_company_receipts(origin_id,issuer_id,proof_id,payment_id,canonical_key,evidence_hash,created_by,created_at) VALUES(?,?,?,?,?,?,?,?)',[$this->origin,$issuer,$this->proof,$payment,$claim['key'],$claim['evidence'],$actor,gmdate('Y-m-d H:i:s')]);
        if(!self::rows($db,'SELECT origin_id FROM '.$p.'efi_company_receipts WHERE origin_id=? AND issuer_id=? AND payment_id=? AND canonical_key=? AND evidence_hash=?',[$this->origin,$issuer,$payment,$claim['key'],$claim['evidence']]))throw new \RuntimeException('issuer_efi_receipt_unconfirmed');
    }
}
