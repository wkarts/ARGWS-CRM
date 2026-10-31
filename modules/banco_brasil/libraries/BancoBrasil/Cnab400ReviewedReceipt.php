<?php

declare(strict_types=1);
namespace BancoBrasil;
require_once APPPATH.'services/CompanyReceiptSource.php';
require_once __DIR__.'/Reconciliation.php';
require_once __DIR__.'/Cnab400ReceiptAssessment.php';
require_once __DIR__.'/Cnab400GeneratedSource.php';
require_once __DIR__.'/Cnab400Preparation.php';
require_once __DIR__.'/ApiReceiptSchema.php';
require_once __DIR__.'/ApiMovements.php';

/**
 * Explicitly reviewed nominal CNAB400 receipt. Financial application remains
 * gated by an issuer-bound declaration for the exact batch, while local
 * preparation and generation endpoints remain available under their own
 * technical, permission and audit checks. No automatic bank transmission or
 * settlement is performed.
 */
final class Cnab400ReviewedReceipt implements \CompanyReceiptSource
{
    private ?array $captured=null;
    private bool $hasAsaas=false;
    private string $reason;
    private string $creditReference;
    private string $homologationReference;
    private string $confirmedAmount;
    public function __construct(private int $eventId,array $input)
    {
        \IssuerPolicy::id($eventId);
        $keys=['credit_confirmed','currency_confirmed','homologation_confirmed','reason','reference','homologation_reference','confirmed_amount'];
        if(array_diff(array_keys($input),$keys)||array_diff($keys,array_keys($input))
            ||($input['credit_confirmed']??null)!=='1'||($input['currency_confirmed']??null)!=='1'
            ||($input['homologation_confirmed']??null)!=='1')throw new \DomainException('bb400_credit_confirmation_required');
        $this->reason=Reconciliation::text($input['reason'],2000);
        $this->creditReference=Reconciliation::text($input['reference'],191);
        $this->homologationReference=Reconciliation::text($input['homologation_reference'],191);
        if(!is_string($input['confirmed_amount']))throw new \DomainException('bb400_confirmed_amount_invalid');
        try{$cents=Fields::cents($input['confirmed_amount']);}catch(\InvalidArgumentException $error){throw new \DomainException('bb400_confirmed_amount_invalid',0,$error);}
        if($cents<1)throw new \DomainException('bb400_confirmed_amount_invalid');
        $this->confirmedAmount=$input['confirmed_amount'];
    }
    public function tables():array
    {
        return array_merge(Reconciliation::tables(),['company_currency_units','company_financial_events','bb_batch_actions','bb_cnab_preparations','bb_contract_declarations','bb_api_receipts','bb_api_operations','bb_api_notifications','bb_api_webhook_receivers'],$this->hasAsaas?['asaas_payment_origins']:[]);
    }
    public function assertSchema($db,string $prefix):void
    {
        require_once __DIR__.'/ApiWebhookSchema.php';ApiWebhookSchema::ensure($db,$prefix,false);
        $this->hasAsaas=$db->table_exists($prefix.'asaas_payment_origins');
        ReconciliationSchema::ensure($db,$prefix,false);ApiReceiptSchema::ensure($db,$prefix,false);OriginationSchema::assertValid($db,$prefix);SchemaValidator::assertCompatible($db,$prefix,Cnab400PreparationSchema::requirements());
    }
    private function homologation($db,string $p,int $issuer,int $batch):array
    {
        $policy=(new Cnab400Preparation($db,$p))->approvedBatchInOwned($issuer,$batch);
        if(!is_string($policy['homologation_reference']??null)||!hash_equals($policy['homologation_reference'],$this->homologationReference))throw new \DomainException('bb400_homologation_required');
        return $policy;
    }
    public function claim($db,string $p,int $issuer,int $actor):array
    {
        \IssuerTransaction::assertOwned($db,$p,array_merge(['invoices','invoicepaymentrecords','credits','company_payment_receipts','issuers_audit'],$this->tables()));
        (new \IssuerResourceAccess($db,$p))->lockFinancialIdentity($issuer,$actor);
        \IssuerStorage::query($db,'SELECT module_name FROM '.$p.'modules WHERE module_name=? FOR UPDATE',['banco_brasil']);
        Reconciliation::authorize($db,$p,$issuer,$actor,'reconcile');
        [$row,$event]=Reconciliation::event($db,$p,$issuer,$this->eventId);
        [$origin,$snapshot,$batch]=Reconciliation::origin($db,$p,$issuer,$row,$event);
        $linked=\IssuerStorage::query($db,'SELECT origin_id FROM '.$p.'bb_event_links WHERE event_id=? AND issuer_id=? FOR UPDATE',[$this->eventId,$issuer])->row_array();
        if(!$linked||(int)$linked['origin_id']!==(int)$origin['id'])throw new \DomainException('bb_event_explicit_link_required');
        $profile=LayoutCatalog::get(LayoutCatalog::CNAB400);
        if(($origin['channel']??null)!=='cnab400'||($batch['layout_id']??null)!==LayoutCatalog::CNAB400
            ||($batch['layout_source_hash']??null)!==$profile['remittance_sha256']||($batch['generation_state']??null)!=='generated'
            ||($batch['delivery_state']??null)!=='reported_submitted')throw new \DomainException('bb_event_requires_specialist_review');
        $approval=$this->homologation($db,$p,$issuer,(int)$batch['id']);
        Cnab400GeneratedSource::validate($origin,$snapshot,$batch);
        $assessment=Cnab400ReceiptAssessment::assess($event,true);
        if(!$assessment['settlement_reported']||!$assessment['positive_paid']||!$assessment['paid_equals_nominal']||$assessment['adjustments_present']
            ||($event['currency']??null)!==null||($event['declared_currency']??null)!=='BRL'
            ||($event['currency_evidence']??null)!=='not_present_in_file')throw new \DomainException('bb400_credit_requires_review');
        $paid=Fields::cents($event['paid_amount']);$nominal=Fields::cents($event['nominal_amount']);
        if($paid!==Fields::cents($this->confirmedAmount)||Fields::cents((string)($snapshot['title']['amount']??''))!==$nominal)
            throw new \DomainException('bb400_credit_amount_divergence');
        $today=(new \DateTimeImmutable('now',new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
        foreach(['occurrence_date','credit_date'] as $field){
            if(!is_string($event[$field]??null))throw new \DomainException('bb_event_credit_date_required');
            Fields::date($event[$field]);if($event[$field]>$today||$event[$field]<($snapshot['invoice']['date']??''))throw new \DomainException('bb_event_credit_date_requires_review');
        }
        if($event['credit_date']<$event['occurrence_date'])throw new \DomainException('bb_event_credit_date_requires_review');
        if($this->hasAsaas&&\IssuerStorage::query($db,'SELECT id FROM '.$p.'asaas_payment_origins WHERE invoice_id=? FOR UPDATE',[$origin['invoice_id']])->row_array())throw new \DomainException('bb_event_multiple_providers_require_review');
        $parent=\IssuerStorage::query($db,'SELECT total,currency,duedate FROM '.$p.'invoices WHERE id=? AND issuer_id=? FOR UPDATE',[$origin['invoice_id'],$issuer])->row_array();
        if(!$parent||(new \CompanyCurrencyUnits($db,$p))->resolveRead($parent['currency'])['iso_code']!=='BRL'
            ||\app\services\InvoicePaymentWriter::minorUnits($parent['total'])!==$nominal||($parent['duedate']??null)!==($snapshot['invoice']['duedate']??null))throw new \DomainException('bb400_credit_amount_divergence');
        $adverse=\IssuerStorage::query($db,"SELECT id FROM {$p}bb_return_events WHERE issuer_id=? AND scope_key=? AND remote_id=? AND control_reference=? AND id<>? AND (occurrence IN ('09','10','20','44','46','93') OR observation IN ('cheque_returned','cheque_pending_clearance','operational_down_reported'))",[$issuer,$row['scope_key'],$row['remote_id'],$row['control_reference'],$this->eventId])->result_array();
        if($adverse)throw new \DomainException('bb_event_adverse_history_requires_review');
        foreach(\IssuerStorage::query($db,"SELECT n.projection_json FROM {$p}bb_api_notifications n JOIN {$p}bb_origins o ON o.id=n.origin_id AND o.issuer_id=n.issuer_id WHERE n.issuer_id=? AND o.account_id=? AND o.environment=? AND o.agreement_number=? AND o.remote_id IN (?,?) FOR UPDATE",[$issuer,$origin['account_id'],$origin['environment'],$origin['agreement_number'],$origin['remote_id'],'000'.$origin['remote_id']])->result_array() as $notification){$observed=json_decode($notification['projection_json'],true,32,JSON_THROW_ON_ERROR);if(($observed['codigoEstadoBaixaOperacional']??null)==='10')throw new \DomainException('bb_event_adverse_history_requires_review');}
        ApiMovements::assertNoAdverseHistory($db,$p,$origin,false);
        $api=\IssuerStorage::query($db,"SELECT r.payment_id FROM {$p}bb_api_receipts r JOIN {$p}bb_origins o ON o.id=r.origin_id WHERE r.issuer_id=? AND o.account_id=? AND o.environment=? AND o.agreement_number=? AND o.remote_id IN (?,?) FOR UPDATE",[$issuer,$origin['account_id'],$origin['environment'],$origin['agreement_number'],$origin['remote_id'],'000'.$origin['remote_id']])->row_array();
        if($api)throw new \DomainException('bb_event_credit_already_recorded_api');
        $other=\IssuerStorage::query($db,"SELECT r.event_id FROM {$p}bb_event_receipts r JOIN {$p}bb_origins o ON o.id=r.origin_id WHERE r.issuer_id=? AND o.account_id=? AND o.environment=? AND o.agreement_number=? AND o.remote_id IN (?,?) AND r.event_id<>? FOR UPDATE",[$issuer,$origin['account_id'],$origin['environment'],$origin['agreement_number'],$origin['remote_id'],'000'.$origin['remote_id'],$this->eventId])->row_array();
        if($other)throw new \DomainException('bb_event_credit_already_recorded');
        $key=hash('sha256',json_encode(['bb400-credit',$issuer,$origin['account_id'],$origin['environment'],$origin['agreement_number'],$origin['remote_id'],$event['occurrence_date'],$event['credit_date'],$event['paid_amount']],JSON_THROW_ON_ERROR));
        $evidence=hash('sha256',json_encode([$row['payload_hash'],$origin['payload_hash'],$this->creditReference,$this->reason,$approval['homologation_reference'],$this->confirmedAmount,'operator_declared_bank_credit_currency_and_homologation'],JSON_THROW_ON_ERROR));
        $duplicate=\IssuerStorage::query($db,'SELECT event_id,evidence_hash FROM '.$p.'bb_event_receipts WHERE canonical_key=? FOR UPDATE',[$key])->row_array();
        if($duplicate&&((int)$duplicate['event_id']!==$this->eventId||!hash_equals($duplicate['evidence_hash'],$evidence)))throw new \DomainException('bb_event_receipt_conflict');
        $this->captured=compact('issuer','actor','row','origin','batch','approval','key','evidence');
        return ['invoice'=>(int)$origin['invoice_id'],'key'=>$key,'input'=>['amount'=>$event['paid_amount'],'date'=>$event['credit_date'],'reference'=>'BB400-'.$key,'note'=>'Crédito CNAB400 conferido pelo operador. Evidência: '.$this->creditReference.'. Motivo: '.$this->reason]];
    }
    public function applied($db,string $p,int $issuer,int $actor,int $payment):void
    {
        $c=$this->captured;if(!$c||$c['issuer']!==$issuer||$c['actor']!==$actor)throw new \DomainException('bb_receipt_claim_required');
        \IssuerTransaction::assertOwned($db,$p,$this->tables());Reconciliation::authorize($db,$p,$issuer,$actor,'reconcile');
        [$current,$event]=Reconciliation::event($db,$p,$issuer,$this->eventId);[$origin,$snapshot,$batch]=Reconciliation::origin($db,$p,$issuer,$current,$event);
        if(!hash_equals($c['row']['payload_hash'],$current['payload_hash'])||!hash_equals($c['origin']['payload_hash'],$origin['payload_hash'])
            ||(int)$origin['id']!==(int)$c['origin']['id']||(int)$batch['id']!==(int)$c['batch']['id'])throw new \DomainException('bb_event_receipt_conflict');
        $this->homologation($db,$p,$issuer,(int)$batch['id']);
        Cnab400GeneratedSource::validate($origin,$snapshot??[],$batch);
        $old=\IssuerStorage::query($db,'SELECT * FROM '.$p.'bb_event_receipts WHERE event_id=? FOR UPDATE',[$this->eventId])->row_array();
        if($old){if((int)$old['payment_id']!==$payment||(int)$old['issuer_id']!==$issuer||(int)$old['origin_id']!==(int)$origin['id']||$old['canonical_key']!==$c['key']||$old['evidence_hash']!==$c['evidence'])throw new \DomainException('bb_event_receipt_conflict');return;}
        \IssuerStorage::query($db,'INSERT INTO '.$p.'bb_event_receipts(event_id,origin_id,issuer_id,canonical_key,payment_id,evidence_hash,created_by,created_at) VALUES(?,?,?,?,?,?,?,?)',[$this->eventId,$origin['id'],$issuer,$c['key'],$payment,$c['evidence'],$actor,date('Y-m-d H:i:s')]);
        \IssuerStorage::query($db,"UPDATE {$p}bb_return_events SET financial_state='applied_assisted' WHERE id=? AND issuer_id=?",[$this->eventId,$issuer]);
        Reconciliation::appendReview($db,$p,$issuer,$actor,$this->eventId,'credit_confirmed_assisted',$this->reason,$this->creditReference);
        Reconciliation::audit($db,$p,$issuer,(int)$current['connection_id'],$actor,'assisted_400_credit_applied',$this->eventId);
        $confirmed=\IssuerStorage::query($db,'SELECT r.payment_id,e.financial_state FROM '.$p.'bb_event_receipts r JOIN '.$p.'bb_return_events e ON e.id=r.event_id WHERE r.event_id=? AND r.issuer_id=?',[$this->eventId,$issuer])->row_array();
        if(!$confirmed||(int)$confirmed['payment_id']!==$payment||$confirmed['financial_state']!=='applied_assisted')throw new \RuntimeException('issuer_bb_receipt_unconfirmed');
        // A late trigger or concurrent permission change cannot silently change the credited source.
        Reconciliation::authorize($db,$p,$issuer,$actor,'reconcile');
        [$finalRow,$finalEvent]=Reconciliation::event($db,$p,$issuer,$this->eventId);
        [$finalOrigin,$finalSnapshot,$finalBatch]=Reconciliation::origin($db,$p,$issuer,$finalRow,$finalEvent);
        if(!hash_equals($c['row']['payload_hash'],$finalRow['payload_hash'])||!hash_equals($c['origin']['payload_hash'],$finalOrigin['payload_hash'])
            ||(int)$finalOrigin['id']!==(int)$c['origin']['id']||(int)$finalBatch['id']!==(int)$c['batch']['id']
            ||$this->homologation($db,$p,$issuer,(int)$finalBatch['id'])!==$c['approval'])throw new \DomainException('bb_event_receipt_conflict');
        Cnab400GeneratedSource::validate($finalOrigin,$finalSnapshot,$finalBatch);
        $finalLink=\IssuerStorage::query($db,'SELECT origin_id FROM '.$p.'bb_event_links WHERE event_id=? AND issuer_id=?',[$this->eventId,$issuer])->row_array();
        $native=\IssuerStorage::query($db,'SELECT invoiceid,amount,paymentmode FROM '.$p.'invoicepaymentrecords WHERE id=?',[$payment])->row_array();
        $nativeProof=\IssuerStorage::query($db,'SELECT issuer_id,invoice_id,payment_id FROM '.$p.'company_payment_receipts WHERE request_key=?',[$c['key']])->row_array();
        if((int)($finalLink['origin_id']??0)!==(int)$finalOrigin['id']||(int)($native['invoiceid']??0)!==(int)$finalOrigin['invoice_id']
            ||($native['paymentmode']??null)!=='bb_cnab400_reviewed'||\app\services\InvoicePaymentWriter::minorUnits($native['amount']??null)!==Fields::cents($this->confirmedAmount)
            ||(int)($nativeProof['issuer_id']??0)!==$issuer||(int)($nativeProof['invoice_id']??0)!==(int)$finalOrigin['invoice_id']
            ||(int)($nativeProof['payment_id']??0)!==$payment)throw new \RuntimeException('issuer_bb_receipt_unconfirmed');

    }
}
