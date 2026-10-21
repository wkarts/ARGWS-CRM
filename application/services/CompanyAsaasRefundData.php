<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyInvoiceAmounts.php';
/** Independently declared full external refund. Not a bank command or authority for partial refunds. */
final class CompanyAsaasRefundData
{
    public const BASIS='asaas_full_external_refund_declared_v1';
    private function __construct(private readonly array $values){}
    private static function text($v,int $min,int $max):string{if(!is_string($v)||trim($v)!==$v||strlen($v)<$min||strlen($v)>$max||preg_match('//u',$v)!==1||preg_match('/[\x00-\x1f\x7f]/',$v))throw new DomainException('company_asaas_full_refund_invalid');return $v;}
    private static function hash($v):string{if(!is_string($v)||!preg_match('/^[a-f0-9]{64}$/D',$v))throw new DomainException('company_asaas_full_refund_invalid');return $v;}
    public static function declare(array $original,array $input,string $today):self
    {
        $fields=['external_refund_confirmed','full_cash_return_confirmed','returned_amount','date','reference','reason','independent_evidence_hash'];if(array_diff(array_keys($input),$fields)||array_diff($fields,array_keys($input))||$input['external_refund_confirmed']!=='1'||$input['full_cash_return_confirmed']!=='1')throw new DomainException('company_asaas_full_refund_confirmation_required');
        $amount=is_string($input['returned_amount'])?CompanyInvoiceAmounts::decimal(CompanyInvoiceAmounts::minor($input['returned_amount'])):null;if($amount!==$original['amount']||CompanyInvoiceAmounts::minor($amount)<1)throw new DomainException('company_asaas_full_refund_full_amount_required');$date=is_string($input['date'])?DateTimeImmutable::createFromFormat('!Y-m-d',$input['date']):false;if(!$date||$date->format('Y-m-d')!==$input['date']||$input['date']<$original['payment_date']||$input['date']>$today)throw new DomainException('company_asaas_full_refund_date_requires_review');
        if($input['independent_evidence_hash']===($original['observation_hash']??null)||$input['independent_evidence_hash']===($original['receipt_evidence_hash']??null)||$input['independent_evidence_hash']===str_repeat('0',64))throw new DomainException('company_asaas_full_refund_independent_proof_required');
        return new self(['basis'=>self::BASIS,'source_type'=>'asaas_full_declared','currency'=>$original['currency'],'original_receipt_key'=>self::hash($original['receipt_key']),'original_evidence_hash'=>self::hash($original['receipt_evidence_hash']),'scope_hash'=>self::hash($original['scope_hash']),'refund_request_key'=>self::hash($original['refund_request_key']),'observation_id'=>$original['observation_id'],'observation_hash'=>self::hash($original['observation_hash']),'origin_id'=>$original['origin_id'],'account_row'=>$original['account_row'],'remote_id'=>$original['remote_id'],'cash_principal_reversed'=>$amount,'principal_reinstated'=>$amount,'discount_reversed'=>'0.00','abatement_reversed'=>'0.00','charges_aggregate_reversed'=>'0.00','returned_paid'=>$amount,'net_reversed'=>null,'independent_evidence_hash'=>self::hash($input['independent_evidence_hash']),'book_date'=>$input['date'],'external_reference'=>self::text($input['reference'],3,191),'reason'=>self::text($input['reason'],3,2000)]);
    }
    public static function fromCaptured(array $data,array $original):self
    {
        $input=['external_refund_confirmed'=>'1','full_cash_return_confirmed'=>'1','returned_amount'=>$data['returned_paid']??null,'date'=>$data['book_date']??null,'reference'=>$data['external_reference']??null,'reason'=>$data['reason']??null,'independent_evidence_hash'=>$data['independent_evidence_hash']??null];$expected=self::declare($original,$input,$input['date']??'')->data();if($expected!==$data)throw new DomainException('company_asaas_full_refund_proof_review');return new self($data);
    }
    public function data():array{return $this->values;}
    public function cashPrincipal():string{return $this->values['cash_principal_reversed'];}
    public function principalReinstated():string{return $this->values['principal_reinstated'];}
    public function fingerprint():string{return hash('sha256',json_encode($this->values,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}
    public function externalReferenceKey():string{return hash('sha256',json_encode([self::BASIS,$this->values['scope_hash'],$this->values['external_reference']],JSON_THROW_ON_ERROR));}
}
