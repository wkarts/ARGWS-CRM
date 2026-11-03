<?php

declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/LayoutCatalog.php';
require_once __DIR__.'/Fields.php';

/** Read-only assessment of one parsed CBR643 event; it cannot authorize a receipt. */
final class Cnab400ReceiptAssessment
{
    public static function assess(array $event,bool $originMatches):array
    {
        if(($event['layout']??null)!==LayoutCatalog::CNAB400||($event['channel']??null)!=='cnab400'
            ||($event['provider']??null)!=='banco_brasil'||($event['financial_effect']??null)!=='not_applied')throw new \DomainException('bb400_assessment_event_denied');
        $nominal=$event['nominal_amount']??null;$paid=$event['paid_amount']??null;
        if(!is_string($nominal)||!is_string($paid))throw new \DomainException('bb400_assessment_invalid');
        try{$nominalCents=Fields::cents($nominal);$paidCents=Fields::cents($paid);}
        catch(\InvalidArgumentException $error){throw new \DomainException('bb400_assessment_invalid',0,$error);}
        $code=$event['occurrence']??null;$nature=$event['nature']??null;
        $reported=($code==='06'&&($event['observation']??null)==='settlement_reported'&&!in_array($nature,['04','10','11'],true));
        $adjustmentNames=['fees','other_expenses','discount_interest','iof','abatement','discount','interest','other_credits','unused_abatement'];
        $adjustments=[];
        foreach($adjustmentNames as $name){
            if(!is_string($event[$name]??null))throw new \DomainException('bb400_assessment_invalid');
            try{$adjustments[$name]=Fields::cents($event[$name])!==0;}
            catch(\InvalidArgumentException $error){throw new \DomainException('bb400_assessment_invalid',0,$error);}
        }
        return ['origin_matches'=>$originMatches,'settlement_reported'=>$reported,'positive_paid'=>$paidCents>0,
            'paid_equals_nominal'=>$paidCents===$nominalCents,'adjustments_present'=>in_array(true,$adjustments,true),
            'adjustment_flags'=>$adjustments,'currency_evidence'=>'not_present_in_file','bank_file_authenticated'=>false,
            'payment_authorized'=>false,'financial_effect'=>'not_applied',
            'blockers'=>['bank_credit_independent_confirmation','currency_and_layout_contract','cnab400_financial_writer_not_operational','bank_homologation']];
    }
}
