<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyBankComponentsData.php';
/** Exact full reversal declared from independent evidence; not bank authentication or bank execution. */
final class CompanyBankReversalData
{
    public const BASIS='bb_full_external_reversal_declared_v1';
    private function __construct(private readonly array $values){}
    private static function hash($hash):string{if(!is_string($hash)||!preg_match('/^[a-f0-9]{64}$/D',$hash))throw new DomainException('company_bank_reversal_invalid');return $hash;}
    private static function money($value):string{if(!is_string($value))throw new DomainException('company_bank_reversal_invalid');try{$cents=CompanyInvoiceAmounts::minor($value);if($cents<1||$cents>CompanyInvoiceAmounts::MAX)throw new DomainException();return CompanyInvoiceAmounts::decimal($cents);}catch(Throwable $e){throw new DomainException('company_bank_reversal_invalid');}}
    private static function date($value):string{$date=is_string($value)?DateTimeImmutable::createFromFormat('!Y-m-d',$value):false;if(!$date||$date->format('Y-m-d')!==$value)throw new DomainException('company_bank_reversal_invalid');return $value;}
    private static function text($value,int $limit):string{if(!is_string($value)||trim($value)===''||strlen($value)>$limit||preg_match('//u',$value)!==1||preg_match('/[\x00-\x1f\x7f]/',$value))throw new DomainException('company_bank_reversal_invalid');return trim($value);}
    public static function declare(CompanyBankComponentsData $original,string $originalKey,string $scopeHash,string $originalBookDate,string $today,array $input):self
    {
        $allowed=['external_reversal_confirmed','full_reinstatement_confirmed','returned_paid','reinstated_principal','date','reference','reason'];if(array_diff(array_keys($input),$allowed)||array_diff($allowed,array_keys($input))||($input['external_reversal_confirmed']??null)!=='1'||($input['full_reinstatement_confirmed']??null)!=='1')throw new DomainException('company_bank_reversal_independent_confirmation_required');
        $data=$original->data();$returned=self::money($input['returned_paid']);$reinstated=self::money($input['reinstated_principal']);if($returned!==$data['paid']||$reinstated!==$data['principal_extinguished'])throw new DomainException('company_bank_reversal_full_amount_required');$date=self::date($input['date']);if($date<self::date($originalBookDate)||$date>self::date($today))throw new DomainException('company_bank_reversal_date_requires_review');
        $reference=self::text($input['reference'],191);$reason=self::text($input['reason'],2000);$scope=self::hash($scopeHash);$key=self::hash($originalKey);
        return new self(['basis'=>self::BASIS,'currency'=>$data['currency'],'original_receipt_key'=>$key,'original_components_hash'=>$original->fingerprint(),'original_evidence_hash'=>$data['evidence_hash'],'scope_hash'=>$scope,'cash_principal_reversed'=>$data['cash_principal'],'principal_reinstated'=>$data['principal_extinguished'],'discount_reversed'=>$data['discount'],'abatement_reversed'=>$data['abatement'],'charges_aggregate_reversed'=>$data['charges_aggregate'],'returned_paid'=>$returned,'net_reversed'=>$data['net_observed'],'book_date'=>$date,'external_reference'=>$reference,'reason'=>$reason]);
    }
    public static function fromCaptured(array $values,CompanyBankComponentsData $original):self
    {
        $expected=['basis','currency','original_receipt_key','original_components_hash','original_evidence_hash','scope_hash','cash_principal_reversed','principal_reinstated','discount_reversed','abatement_reversed','charges_aggregate_reversed','returned_paid','net_reversed','book_date','external_reference','reason'];if(array_diff(array_keys($values),$expected)||array_diff($expected,array_keys($values))||($values['basis']??null)!==self::BASIS)throw new DomainException('company_bank_reversal_invalid');
        // Canonical keys/amounts/proof are revalidated against the original immutable decomposition.
        $date=self::date($values['book_date']);$dto=self::declare($original,$values['original_receipt_key'],$values['scope_hash'],$date,$date,['external_reversal_confirmed'=>'1','full_reinstatement_confirmed'=>'1','returned_paid'=>$values['returned_paid'],'reinstated_principal'=>$values['principal_reinstated'],'date'=>$date,'reference'=>$values['external_reference'],'reason'=>$values['reason']]);$canonical=$dto->data();foreach($canonical as $key=>$value)if(($values[$key]??null)!==$value)throw new DomainException('company_bank_reversal_invalid');return $dto;
    }
    public function data():array{return $this->values;}
    public function cashPrincipal():string{return $this->values['cash_principal_reversed'];}
    public function principalReinstated():string{return $this->values['principal_reinstated'];}
    public function fingerprint():string{return hash('sha256',json_encode($this->values,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}
    public function externalReferenceKey():string{return hash('sha256',json_encode([self::BASIS,$this->values['scope_hash'],$this->values['external_reference']],JSON_THROW_ON_ERROR));}
}
