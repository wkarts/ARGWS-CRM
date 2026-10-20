<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyInvoiceAmounts.php';

/** Exact non-fiscal decomposition. This DTO never authenticates a bank source or grants writer authority. */
final class CompanyBankComponentsData
{
    public const BASIS='bb_cnab240_explicit_local_equation_v1';
    public const API16_BASIS='bb_api16_explicit_full_equation_v1';
    private function __construct(private readonly array $values){}
    private static function minor($input):int
    {
        if(!is_string($input))throw new DomainException('company_bank_components_invalid');
        try{$value=CompanyInvoiceAmounts::minor($input);}catch(Throwable $error){throw new DomainException('company_bank_components_invalid');}
        if($value<0||$value>CompanyInvoiceAmounts::MAX)throw new DomainException('company_bank_components_invalid');return $value;
    }
    private static function add(int $a,int $b):int{if($a>CompanyInvoiceAmounts::MAX-$b)throw new DomainException('company_bank_components_overflow');return $a+$b;}
    public static function fromCnab240(array $event,string $invoiceNominal,string $lockedBalance,string $evidenceHash):self
    {
        if(($event['channel']??null)!=='cnab240'||($event['currency']??null)!=='BRL'||($event['occurrence']??null)!=='06'||($event['observation']??null)!=='settlement_reported'||!preg_match('/^[a-f0-9]{64}$/D',$evidenceHash))throw new DomainException('company_bank_components_invalid');
        $data=['currency'=>'BRL','basis'=>self::BASIS,'evidence_hash'=>$evidenceHash];
        foreach(['nominal_amount'=>'nominal','paid_amount'=>'paid','net_amount'=>'net_observed','interest'=>'charges_aggregate','discount'=>'discount','abatement'=>'abatement','fees'=>'fees','iof'=>'iof','other_expenses'=>'other_expenses','other_credits'=>'other_credits'] as $source=>$field)$data[$field]=CompanyInvoiceAmounts::decimal(self::minor($event[$source]??null));
        $nominal=self::minor($invoiceNominal);$balance=self::minor($lockedBalance);if($nominal!==self::minor($data['nominal'])||$balance<1||$balance>$nominal)throw new DomainException('company_bank_components_balance_invalid');
        $cash=self::minor($data['paid'])-self::minor($data['charges_aggregate']);if($cash<1)throw new DomainException('company_bank_components_cash_invalid');
        $reduction=self::add(self::minor($data['discount']),self::minor($data['abatement']));$principal=self::add($cash,$reduction);if($principal>$balance)throw new DomainException('company_bank_components_exceeds_balance');
        $data+=['cash_principal'=>CompanyInvoiceAmounts::decimal($cash),'principal_extinguished'=>CompanyInvoiceAmounts::decimal($principal),'balance_before'=>CompanyInvoiceAmounts::decimal($balance),'balance_after'=>CompanyInvoiceAmounts::decimal($balance-$principal)];
        return self::fromCaptured($data);
    }
    /** Full, single API16 credit only. This exact local equation never infers a partial movement or an undisclosed fee. */
    public static function fromApi16(array $result,string $invoiceNominal,string $lockedBalance,string $evidenceHash):self
    {
        if(($result['codigoEstadoTituloCobranca']??null)!=='16'||!preg_match('/^[a-f0-9]{64}$/D',$evidenceHash))throw new DomainException('company_bank_components_invalid');
        foreach(['valorPagamentoParcialTitulo','valorImpostoSobreOprFinanceirasRecebidoTitulo','valorMultaRecebido','valorReajuste','valorOutroRecebido'] as $field)if(self::minor($result[$field]??null)!==0)throw new DomainException('company_bank_components_net_contract_required');
        $data=['currency'=>'BRL','basis'=>self::API16_BASIS,'evidence_hash'=>$evidenceHash];
        foreach(['valorOriginalTituloCobranca'=>'nominal','valorPagoSacado'=>'paid','valorCreditoCedente'=>'net_observed','valorJuroMoraRecebido'=>'charges_aggregate','valorDescontoUtilizado'=>'discount','valorAbatimentoTotal'=>'abatement'] as $source=>$field)$data[$field]=CompanyInvoiceAmounts::decimal(self::minor($result[$source]??null));
        $nominal=self::minor($invoiceNominal);$balance=self::minor($lockedBalance);if($nominal!==self::minor($data['nominal'])||$nominal<1||$balance!==$nominal)throw new DomainException('company_bank_components_balance_invalid');
        $cash=self::minor($data['paid'])-self::minor($data['charges_aggregate']);if($cash<1)throw new DomainException('company_bank_components_cash_invalid');$principal=self::add($cash,self::add(self::minor($data['discount']),self::minor($data['abatement'])));if($principal!==$nominal)throw new DomainException('company_bank_components_equation_invalid');
        $data+=['fees'=>'0.00','iof'=>'0.00','other_expenses'=>'0.00','other_credits'=>'0.00','cash_principal'=>CompanyInvoiceAmounts::decimal($cash),'principal_extinguished'=>CompanyInvoiceAmounts::decimal($principal),'balance_before'=>CompanyInvoiceAmounts::decimal($balance),'balance_after'=>'0.00'];return self::fromCaptured($data);
    }
    /** Validates an immutable captured receipt; callers must independently prove its source/hash/ownership. */
    public static function fromCaptured(array $data):self
    {
        $fields=['currency','basis','evidence_hash','nominal','paid','net_observed','charges_aggregate','discount','abatement','fees','iof','other_expenses','other_credits','cash_principal','principal_extinguished','balance_before','balance_after'];
        if(array_diff(array_keys($data),$fields)||array_diff($fields,array_keys($data))||($data['currency']??null)!=='BRL'||!in_array($data['basis']??null,[self::BASIS,self::API16_BASIS],true)||!is_string($data['evidence_hash']??null)||!preg_match('/^[a-f0-9]{64}$/D',$data['evidence_hash']))throw new DomainException('company_bank_components_invalid');
        $cents=[];foreach(array_diff($fields,['currency','basis','evidence_hash']) as $field){$cents[$field]=self::minor($data[$field]);$data[$field]=CompanyInvoiceAmounts::decimal($cents[$field]);}
        // No undocumented net calculation: zero all deductions/other credits until a primary contract defines them.
        foreach(['fees','iof','other_expenses','other_credits'] as $field)if($cents[$field]!==0)throw new DomainException('company_bank_components_net_contract_required');
        if($cents['paid']<1||$cents['cash_principal']<1||$cents['nominal']<1||$cents['balance_before']<1||$cents['balance_before']>$cents['nominal']||$cents['net_observed']!==$cents['paid'])throw new DomainException('company_bank_components_balance_invalid');
        $cashPlusCharges=self::add($cents['cash_principal'],$cents['charges_aggregate']);$reductions=self::add($cents['discount'],$cents['abatement']);$principal=self::add($cents['cash_principal'],$reductions);
        if($cashPlusCharges!==$cents['paid']||$principal!==$cents['principal_extinguished']||$principal>$cents['balance_before']||$cents['balance_before']-$principal!==$cents['balance_after'])throw new DomainException('company_bank_components_equation_invalid');
        if($data['basis']===self::API16_BASIS&&($cents['principal_extinguished']!==$cents['nominal']||$cents['balance_before']!==$cents['nominal']||$cents['balance_after']!==0))throw new DomainException('company_bank_components_equation_invalid');
        // Stable key order is part of the persisted evidence, independent of form/JSON field ordering.
        $normalized=[];foreach($fields as $field)$normalized[$field]=$data[$field];return new self($normalized);
    }
    public function sourceType():string{return $this->values['basis']===self::API16_BASIS?'bb_api16':'bb_cnab240';}
    public function paymentMode():string{return $this->values['basis']===self::API16_BASIS?'bb_api16_components_reviewed':'bb_cnab240_components_reviewed';}
    public function data():array{return $this->values;}
    public function cashPrincipal():string{return $this->values['cash_principal'];}
    public function principalExtinguished():string{return $this->values['principal_extinguished'];}
    public function remaining():string{return $this->values['balance_after'];}
    public function fingerprint():string{return hash('sha256',json_encode($this->values,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));}
}
