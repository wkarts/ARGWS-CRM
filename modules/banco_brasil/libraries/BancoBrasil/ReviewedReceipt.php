<?php

declare(strict_types=1);
namespace BancoBrasil;
require_once APPPATH.'services/CompanyReceiptSource.php';
require_once __DIR__.'/Reconciliation.php';
require_once __DIR__.'/ApiReceiptSchema.php';
require_once __DIR__.'/ApiMovements.php';
/** An operator-verified credit, never an authentication assertion derived from an uploaded file. */
final class ReviewedReceipt implements \CompanyReceiptSource
{
    private ?array $captured=null;
    private bool $hasAsaas=false;
    private string $reason;
    private string $reference;
    public function __construct(private int $eventId,array $input)
    {
        \IssuerPolicy::id($eventId);
        if(array_diff(array_keys($input),['credit_confirmed','reason','reference'])||($input['credit_confirmed']??null)!=='1')throw new \DomainException('bb_independent_credit_confirmation_required');
        $this->reason=Reconciliation::text($input['reason']??null,2000);$this->reference=Reconciliation::text($input['reference']??null,191);
    }
    public function tables():array{return array_merge(array_merge(Reconciliation::tables(),['company_currency_units','company_financial_events','bb_api_receipts','bb_api_operations','bb_api_notifications','bb_api_webhook_receivers']),$this->hasAsaas?['asaas_payment_origins']:[]);}
    public function assertSchema($db,string $prefix):void
    {
        require_once __DIR__.'/ApiWebhookSchema.php';ApiWebhookSchema::ensure($db,$prefix,false);
        $this->hasAsaas=$db->table_exists($prefix.'asaas_payment_origins');
        ReconciliationSchema::ensure($db,$prefix,false);ApiReceiptSchema::ensure($db,$prefix,false);OriginationSchema::assertValid($db,$prefix);
    }
    public function claim($db,string $p,int $issuer,int $actor):array
    {
        \IssuerTransaction::assertOwned($db,$p,array_merge(['invoices','invoicepaymentrecords','credits','company_payment_receipts','issuers_audit'],$this->tables()));
        (new \IssuerResourceAccess($db,$p))->lockFinancialIdentity($issuer,$actor);
        \IssuerStorage::query($db,'SELECT module_name FROM '.$p.'modules WHERE module_name=? FOR UPDATE',['banco_brasil']);
        Reconciliation::authorize($db,$p,$issuer,$actor,'reconcile');
        [$row,$e]=Reconciliation::event($db,$p,$issuer,$this->eventId);[$origin,$snapshot,$batch]=Reconciliation::origin($db,$p,$issuer,$row,$e);
        $link=\IssuerStorage::query($db,'SELECT origin_id FROM '.$p.'bb_event_links WHERE event_id=? AND issuer_id=? FOR UPDATE',[$this->eventId,$issuer])->row_array();
        if(!$link||(int)$link['origin_id']!==(int)$origin['id'])throw new \DomainException('bb_event_explicit_link_required');
        if(($e['channel']??null)!=='cnab240'||($e['layout']??null)!==Agreement::LAYOUT_ID||$row['occurrence']!=='06'||$row['observation']!=='settlement_reported'||($e['currency']??null)!=='BRL'||$batch['generation_state']!=='generated'||$batch['delivery_state']!=='reported_submitted'||$batch['layout_id']!==Agreement::LAYOUT_ID||$batch['layout_source_hash']!==Agreement::SOURCE_SHA256)throw new \DomainException('bb_event_requires_specialist_review');
        foreach(['fees','interest','discount','abatement','iof','other_expenses','other_credits'] as $field)if(!isset($e[$field])||Fields::cents($e[$field])!==0)throw new \DomainException('bb_event_adjustments_require_review');
        $paid=Fields::cents($e['paid_amount']??'');$nominal=Fields::cents($e['nominal_amount']??'');
        if($paid<1||$paid>$nominal||Fields::cents($e['net_amount']??'')!==$paid||Fields::cents($snapshot['title']['amount'])!==$nominal)throw new \DomainException('bb_event_amount_divergence');
        $today=(new \DateTimeImmutable('now',new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
        foreach(['occurrence_date','credit_date'] as $field){if(!is_string($e[$field]??null))throw new \DomainException('bb_event_credit_date_required');Fields::date($e[$field]);if($e[$field]>$today||$e[$field]<$snapshot['invoice']['date'])throw new \DomainException('bb_event_credit_date_requires_review');}
        if($e['credit_date']<$e['occurrence_date'])throw new \DomainException('bb_event_credit_date_requires_review');
        if($this->hasAsaas&&\IssuerStorage::query($db,'SELECT id FROM '.$p.'asaas_payment_origins WHERE invoice_id=? FOR UPDATE',[$origin['invoice_id']])->row_array())throw new \DomainException('bb_event_multiple_providers_require_review');
        $parent=\IssuerStorage::query($db,'SELECT total,currency FROM '.$p.'invoices WHERE id=? AND issuer_id=? FOR UPDATE',[$origin['invoice_id'],$issuer])->row_array();
        if(!$parent||(new \CompanyCurrencyUnits($db,$p))->resolveRead($parent['currency'])['iso_code']!==($snapshot['invoice']['currency']??null)||\app\services\InvoicePaymentWriter::minorUnits($parent['total'])!==$nominal)throw new \DomainException('bb_event_amount_divergence');
        // A reversal/conditional/operational event is never silently ignored, regardless of import order.
        $adverse=\IssuerStorage::query($db,"SELECT id FROM ".$p."bb_return_events WHERE issuer_id=? AND scope_key=? AND remote_id=? AND control_reference=? AND id<>? AND (occurrence IN ('44','50','93') OR observation IN ('cheque_returned','cheque_pending_clearance','operational_down_reported'))",[$issuer,$row['scope_key'],$row['remote_id'],$row['control_reference'],$this->eventId])->result_array();
        if($adverse)throw new \DomainException('bb_event_adverse_history_requires_review');
        foreach(\IssuerStorage::query($db,"SELECT n.projection_json FROM {$p}bb_api_notifications n JOIN {$p}bb_origins o ON o.id=n.origin_id AND o.issuer_id=n.issuer_id WHERE n.issuer_id=? AND o.account_id=? AND o.environment=? AND o.agreement_number=? AND o.remote_id IN (?,?) FOR UPDATE",[$issuer,$origin['account_id'],$origin['environment'],$origin['agreement_number'],$origin['remote_id'],'000'.$origin['remote_id']])->result_array() as $notification){$observed=json_decode($notification['projection_json'],true,32,JSON_THROW_ON_ERROR);if(($observed['codigoEstadoBaixaOperacional']??null)==='10')throw new \DomainException('bb_event_adverse_history_requires_review');}
        ApiMovements::assertNoAdverseHistory($db,$p,$origin,false);
        $api=\IssuerStorage::query($db,"SELECT r.payment_id FROM {$p}bb_api_receipts r JOIN {$p}bb_origins o ON o.id=r.origin_id WHERE r.issuer_id=? AND o.account_id=? AND o.environment=? AND o.agreement_number=? AND o.remote_id IN (?,?) FOR UPDATE",[$issuer,$origin['account_id'],$origin['environment'],$origin['agreement_number'],$origin['remote_id'],'000'.$origin['remote_id']])->row_array();
        if($api)throw new \DomainException('bb_event_credit_already_recorded_api');
        $key=hash('sha256',json_encode(['bb-credit',$issuer,$origin['account_id'],$origin['environment'],$origin['agreement_number'],$origin['remote_id'],$e['occurrence_date'],$e['credit_date'],$e['paid_amount'],$e['net_amount']],JSON_THROW_ON_ERROR));
        $evidence=hash('sha256',json_encode([$row['payload_hash'],$origin['payload_hash'],$this->reference,$this->reason,'operator_verified_bank_credit'],JSON_THROW_ON_ERROR));
        $duplicate=\IssuerStorage::query($db,'SELECT event_id,evidence_hash FROM '.$p.'bb_event_receipts WHERE canonical_key=? FOR UPDATE',[$key])->row_array();
        if($duplicate&&((int)$duplicate['event_id']!==$this->eventId||!hash_equals($duplicate['evidence_hash'],$evidence)))throw new \DomainException('bb_event_receipt_conflict');
        $this->captured=compact('issuer','actor','row','origin','key','evidence');
        return ['invoice'=>(int)$origin['invoice_id'],'key'=>$key,'input'=>['amount'=>$e['paid_amount'],'date'=>$e['credit_date'],'reference'=>'BB240-'.$key,'note'=>'Conciliação assistida; crédito conferido pelo operador. Evidência: '.$this->reference.'. Motivo: '.$this->reason]];
    }
    public function applied($db,string $p,int $issuer,int $actor,int $payment):void
    {
        $c=$this->captured;
        if(!$c||$c['issuer']!==$issuer||$c['actor']!==$actor)throw new \DomainException('bb_receipt_claim_required');
        \IssuerTransaction::assertOwned($db,$p,$this->tables());Reconciliation::authorize($db,$p,$issuer,$actor,'reconcile');
        $old=\IssuerStorage::query($db,'SELECT * FROM '.$p.'bb_event_receipts WHERE event_id=? FOR UPDATE',[$this->eventId])->row_array();
        if($old){if((int)$old['payment_id']!==$payment||(int)$old['issuer_id']!==$issuer||(int)$old['origin_id']!==(int)$c['origin']['id']||$old['canonical_key']!==$c['key']||$old['evidence_hash']!==$c['evidence'])throw new \DomainException('bb_event_receipt_conflict');return;}
        \IssuerStorage::query($db,'INSERT INTO '.$p.'bb_event_receipts(event_id,origin_id,issuer_id,canonical_key,payment_id,evidence_hash,created_by,created_at) VALUES(?,?,?,?,?,?,?,?)',[$this->eventId,$c['origin']['id'],$issuer,$c['key'],$payment,$c['evidence'],$actor,date('Y-m-d H:i:s')]);
        \IssuerStorage::query($db,"UPDATE ".$p."bb_return_events SET financial_state='applied_assisted' WHERE id=? AND issuer_id=?",[$this->eventId,$issuer]);
        Reconciliation::appendReview($db,$p,$issuer,$actor,$this->eventId,'credit_confirmed_assisted',$this->reason,$this->reference);
        Reconciliation::audit($db,$p,$issuer,(int)$c['row']['connection_id'],$actor,'assisted_credit_applied',$this->eventId);
        $confirmed=\IssuerStorage::query($db,'SELECT r.payment_id,e.financial_state FROM '.$p.'bb_event_receipts r JOIN '.$p.'bb_return_events e ON e.id=r.event_id WHERE r.event_id=? AND r.issuer_id=?',[$this->eventId,$issuer])->row_array();
        if(!$confirmed||(int)$confirmed['payment_id']!==$payment||$confirmed['financial_state']!=='applied_assisted')throw new \RuntimeException('issuer_bb_receipt_unconfirmed');
    }
}
