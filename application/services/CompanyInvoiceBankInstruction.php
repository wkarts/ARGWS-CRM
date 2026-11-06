<?php
declare(strict_types=1);
/** Pure, explicit local CNAB issuance instruction. No bank, account or payer values are inferred. */
final class CompanyInvoiceBankInstruction
{
    public const PAYER=['payer_name','payer_address','payer_district','payer_zip','payer_city','payer_state','species','acceptance'];
    public static function input($value):array
    {
        if(!is_array($value)||array_diff(array_keys($value),['contract_id','timezone','payer','approval400'])||!isset($value['contract_id'],$value['timezone'],$value['payer']))throw new DomainException('company_invoice_bank_instruction_invalid');
        $contract=IssuerPolicy::id($value['contract_id']);$payer=$value['payer'];
        if(!is_array($payer)||array_diff(array_keys($payer),self::PAYER)||array_diff(self::PAYER,array_keys($payer)))throw new DomainException('company_invoice_bank_instruction_invalid');
        $out=[];foreach(self::PAYER as $key){$v=$payer[$key];if(!is_string($v)||$v===''||strlen($v)>100||preg_match('/[^\x20-\x7e]/',$v))throw new DomainException('company_invoice_bank_instruction_invalid');$out[$key]=$v;}
        if(!preg_match('/^[0-9]{8}$/D',$out['payer_zip'])||!preg_match('/^[A-Z]{2}$/D',$out['payer_state'])||!preg_match('/^[0-9]{2}$/D',$out['species'])||!in_array($out['acceptance'],['A','N'],true))throw new DomainException('company_invoice_bank_instruction_invalid');
        if(!is_string($value['timezone'])||!in_array($value['timezone'],\DateTimeZone::listIdentifiers(),true))throw new DomainException('company_invoice_bank_instruction_invalid');
        $result=['contract_id'=>$contract,'timezone'=>$value['timezone'],'payer'=>$out];
        if(isset($value['approval400'])){
            $approval=$value['approval400'];$keys=['currency_reference','interest_reference','homologation_reference','currency_confirmed','interest_confirmed','homologation_confirmed'];
            if(!is_array($approval)||array_diff(array_keys($approval),$keys)||array_diff($keys,array_keys($approval)))throw new DomainException('company_invoice_bank_instruction_invalid');
            $result['approval400']=[];
            foreach($keys as $key){$v=$approval[$key];if(!is_string($v)||strlen($v)>191||trim($v)===''||preg_match('/[\x00-\x1f\x7f]/',$v)||str_ends_with($key,'_confirmed')&&$v!=='1')throw new DomainException('company_invoice_bank_instruction_invalid');$result['approval400'][$key]=$v;}
        }
        return $result;
    }
    public static function form($value):?array
    {
        if($value===null)return null;
        if(!is_array($value)||array_diff(array_keys($value),['enabled','contract_id','timezone','payer','approval400','confirm400']))throw new DomainException('company_invoice_bank_instruction_invalid');
        if(!isset($value['enabled']))return null;
        if($value['enabled']!=='1')throw new DomainException('company_invoice_bank_instruction_invalid');
        $instruction=['contract_id'=>$value['contract_id']??null,'timezone'=>$value['timezone']??null,'payer'=>$value['payer']??null];
        if(($value['confirm400']??null)==='1')$instruction['approval400']=$value['approval400']??null;
        elseif(isset($value['confirm400']))throw new DomainException('company_invoice_bank_instruction_invalid');
        return self::input($instruction);
    }
}
