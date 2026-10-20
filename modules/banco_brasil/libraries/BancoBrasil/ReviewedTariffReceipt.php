<?php

declare(strict_types=1);
namespace BancoBrasil;
require_once APPPATH.'services/CompanyReceiptSource.php';
require_once __DIR__.'/Reconciliation.php';
require_once __DIR__.'/ApiReceiptSchema.php';
require_once __DIR__.'/ApiMovements.php';require_once __DIR__.'/ModuleStore.php';
require_once __DIR__.'/Cnab240Generator.php';
require_once APPPATH.'services/CompanyBankComponentsData.php';
require_once APPPATH.'services/CompanyBankReceiptComponents.php';
require_once APPPATH.'services/CompanyInvoiceBalance.php';
/** Explicit local component policy and independently checked credit; an upload never authenticates a bank. */
final class ReviewedTariffReceipt implements \CompanyReceiptSource
{
    private ?array $captured=null;
    private bool $hasAsaas=false;
    private array $balanceTables=[];
    private string $reason;
    private string $reference;
    public function __construct(private int $eventId,array $input)
    {
        \IssuerPolicy::id($eventId);
        if(array_diff(array_keys($input),['credit_confirmed','tariff_policy_confirmed','reason','reference'])||(($input['credit_confirmed']??null)!=='1'||($input['tariff_policy_confirmed']??null)!=='1'))throw new \DomainException('bb_independent_credit_confirmation_required');
        $this->reason=Reconciliation::text($input['reason']??null,2000);$this->reference=Reconciliation::text($input['reference']??null,191);
    }
    public function tables():array{return array_merge(array_merge(Reconciliation::tables(),$this->balanceTables,['bb_contract_declarations','bb_batch_actions','itemable','item_tax','clients','issuers_clients','issuers_client_links','issuers_sequences','currencies','countries','company_bank_receipt_components','company_currency_units','company_financial_events','bb_api_receipts','bb_api_operations','bb_api_notifications','bb_api_webhook_receivers']),$this->hasAsaas?['asaas_payment_origins']:[]);}
    public function assertSchema($db,string $prefix):void
    {
        require_once __DIR__.'/ApiWebhookSchema.php';ApiWebhookSchema::ensure($db,$prefix,false);
        $this->balanceTables=(new \CompanyInvoiceBalance($db,$prefix))->tables();
        $this->hasAsaas=$db->table_exists($prefix.'asaas_payment_origins');
        \CompanyBankComponentsSchema::assertValid($db,$prefix);ReconciliationSchema::ensure($db,$prefix,false);ApiReceiptSchema::ensure($db,$prefix,false);OriginationSchema::assertValid($db,$prefix);
    }
    public function claim($db,string $p,int $issuer,int $actor):array
    {
        \IssuerTransaction::assertOwned($db,$p,array_merge(['invoices','invoicepaymentrecords','credits','company_payment_receipts','issuers_audit'],$this->tables()));
        (new \IssuerResourceAccess($db,$p))->lockFinancialIdentity($issuer,$actor);
        \IssuerStorage::query($db,'SELECT module_name FROM '.$p.'modules WHERE module_name=? FOR UPDATE',['banco_brasil']);
        Reconciliation::authorize($db,$p,$issuer,$actor,'reconcile');Reconciliation::authorize($db,$p,$issuer,$actor,'reconcile_tariff');
        [$row,$e]=Reconciliation::event($db,$p,$issuer,$this->eventId);[$origin,$snapshot,$batch]=Reconciliation::origin($db,$p,$issuer,$row,$e);
        $link=\IssuerStorage::query($db,'SELECT origin_id FROM '.$p.'bb_event_links WHERE event_id=? AND issuer_id=? FOR UPDATE',[$this->eventId,$issuer])->row_array();
        if(!$link||(int)$link['origin_id']!==(int)$origin['id'])throw new \DomainException('bb_event_explicit_link_required');
        if(($e['channel']??null)!=='cnab240'||($e['layout']??null)!==Agreement::LAYOUT_ID||$row['occurrence']!=='06'||$row['observation']!=='settlement_reported'||($e['currency']??null)!=='BRL'||$batch['generation_state']!=='generated'||$batch['delivery_state']!=='reported_submitted'||$batch['layout_id']!==Agreement::LAYOUT_ID||$batch['layout_source_hash']!==Agreement::SOURCE_SHA256)throw new \DomainException('bb_event_requires_specialist_review');
        $nominal=Fields::cents($e['nominal_amount']??'');
        if(Fields::cents($snapshot['title']['amount'])!==$nominal)throw new \DomainException('bb_event_amount_divergence');
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
        $receipt=\IssuerStorage::query($db,'SELECT * FROM '.$p.'bb_event_receipts WHERE event_id=? AND issuer_id=? FOR UPDATE',[$this->eventId,$issuer])->row_array();$sourceTime=$receipt?$receipt['created_at']:($this->captured['sourceTime']??date('Y-m-d H:i:s'));
        $frame=$this->frame($db,$p,$issuer,$row,$origin);
        $evidence=hash('sha256',json_encode([$row['payload_hash'],$origin['payload_hash'],$frame,$sourceTime,$this->reference,$this->reason,\CompanyBankComponentsData::TARIFF_BASIS],JSON_THROW_ON_ERROR));
        $duplicate=\IssuerStorage::query($db,'SELECT event_id,evidence_hash FROM '.$p.'bb_event_receipts WHERE canonical_key=? FOR UPDATE',[$key])->row_array();
        if($duplicate&&((int)$duplicate['event_id']!==$this->eventId||!hash_equals($duplicate['evidence_hash'],$evidence)))throw new \DomainException('bb_event_receipt_conflict');
        $componentsService=new \CompanyBankReceiptComponents($db,$p);
        $components=$componentsService->capturedInOwned($issuer,$actor,(int)$origin['invoice_id'],$key);
        if($components){
            if($components->data()['basis']!==\CompanyBankComponentsData::TARIFF_BASIS||!hash_equals($components->data()['evidence_hash'],$evidence)||$components->data()['nominal']!==\CompanyInvoiceAmounts::decimal($nominal))throw new \DomainException('bb_event_receipt_conflict');
        }else{
            $balance=(new \CompanyInvoiceBalance($db,$p))->inOwned($issuer,$actor,(int)$origin['invoice_id']);
            $components=\CompanyBankComponentsData::fromCnab240Tariff($e,\CompanyInvoiceAmounts::decimal($nominal),$balance['receivable'],$evidence);
        }
        $proofActor=$receipt?(int)$receipt['created_by']:$actor;$reviewKey=hash('sha256',json_encode([$issuer,$proofActor,$this->eventId,'tariff_credit_confirmed',$this->reason,$this->reference,$receipt?$this->financialFrame($db,$p,$issuer,(int)$receipt['payment_id']):null],JSON_THROW_ON_ERROR));$review=\IssuerStorage::query($db,'SELECT * FROM '.$p.'bb_event_reviews WHERE review_key=? AND issuer_id=? FOR UPDATE',[$reviewKey,$issuer])->row_array();$auditRows=\IssuerStorage::query($db,"SELECT * FROM {$p}bb_audit WHERE issuer_id=? AND resource_type='return_event' AND resource_id=? AND action='assisted_tariff_credit_applied' ORDER BY id FOR UPDATE",[$issuer,$this->eventId])->result_array();if(count($auditRows)>1||($receipt&&!$review)||(!$receipt&&($review||$auditRows)))throw new \DomainException('bb_event_receipt_conflict');$audit=$auditRows[0]??[];$native=$this->nativeFrame($db,$p,$issuer,$actor,(int)$origin['invoice_id']);if($this->captured!==null&&$this->captured['native']!==$native)throw new \RuntimeException('issuer_bb_tariff_parent_unconfirmed');$this->captured=compact('issuer','actor','row','origin','key','evidence','frame','native','receipt','review','audit','sourceTime');
        return ['invoice'=>(int)$origin['invoice_id'],'key'=>$key,'components'=>$components,'input'=>['amount'=>$components->cashPrincipal(),'date'=>$e['credit_date'],'reference'=>'BB240-'.$key,'note'=>'Conciliação assistida integral com tarifa positiva; BB junho2026 p31 U93–107=pago–tarifa; crédito/líquido conferidos pelo operador. Evidência: '.$this->reference.'. Motivo: '.$this->reason]];
    }
    public function applied($db,string $p,int $issuer,int $actor,int $payment):void
    {
        $financial=$this->financialFrame($db,$p,$issuer,$payment);$this->confirmCoreAudits($db,$p,$issuer,$payment);$c=$this->captured;
        if(!$c||$c['issuer']!==$issuer||$c['actor']!==$actor)throw new \DomainException('bb_receipt_claim_required');
        \IssuerTransaction::assertOwned($db,$p,$this->tables());Reconciliation::authorize($db,$p,$issuer,$actor,'reconcile');Reconciliation::authorize($db,$p,$issuer,$actor,'reconcile_tariff');
        $old=\IssuerStorage::query($db,'SELECT * FROM '.$p.'bb_event_receipts WHERE event_id=? FOR UPDATE',[$this->eventId])->row_array();
        if($old){if($old!==$c['receipt'])throw new \RuntimeException('issuer_bb_tariff_source_unconfirmed');if((int)$old['payment_id']!==$payment||(int)$old['issuer_id']!==$issuer||(int)$old['origin_id']!==(int)$c['origin']['id']||$old['canonical_key']!==$c['key']||$old['evidence_hash']!==$c['evidence'])throw new \DomainException('bb_event_receipt_conflict');$this->claim($db,$p,$issuer,$actor);if($this->captured['frame']!==$c['frame'])throw new \RuntimeException('issuer_bb_tariff_source_unconfirmed');$this->confirmReceipt($db,$p,$issuer,$payment,$c);if($this->financialFrame($db,$p,$issuer,$payment)!==$financial)throw new \RuntimeException('issuer_bb_tariff_source_unconfirmed');(new \CompanyInvoiceBalance($db,$p))->inOwned($issuer,$actor,(int)$c['origin']['invoice_id']);return;}
        if($c['receipt']||$c['review']||$c['audit'])throw new \DomainException('bb_event_receipt_conflict');
        $c['receipt']=['event_id'=>$this->eventId,'origin_id'=>$c['origin']['id'],'issuer_id'=>$issuer,'canonical_key'=>$c['key'],'payment_id'=>$payment,'evidence_hash'=>$c['evidence'],'created_by'=>$actor,'created_at'=>$c['sourceTime']];$this->insertImage($db,$p.'bb_event_receipts',$c['receipt'],false);
        \IssuerStorage::query($db,"UPDATE ".$p."bb_return_events SET financial_state='applied_assisted' WHERE id=? AND issuer_id=?",[$this->eventId,$issuer]);
        $c['review']=['event_id'=>$this->eventId,'issuer_id'=>$issuer,'actor_id'=>$actor,'decision'=>'tariff_credit_confirmed','reason'=>$this->reason,'evidence_reference'=>$this->reference,'review_key'=>hash('sha256',json_encode([$issuer,$actor,$this->eventId,'tariff_credit_confirmed',$this->reason,$this->reference,$financial],JSON_THROW_ON_ERROR)),'created_at'=>$c['sourceTime']];$this->insertImage($db,$p.'bb_event_reviews',$c['review']);
        $c['audit']=['issuer_id'=>$issuer,'connection_id'=>$c['row']['connection_id'],'actor_id'=>$actor,'action'=>'assisted_tariff_credit_applied','resource_type'=>'return_event','resource_id'=>$this->eventId,'created_at'=>$c['sourceTime']];$this->insertImage($db,$p.'bb_audit',$c['audit']);
        $confirmed=\IssuerStorage::query($db,'SELECT r.payment_id,e.financial_state FROM '.$p.'bb_event_receipts r JOIN '.$p.'bb_return_events e ON e.id=r.event_id WHERE r.event_id=? AND r.issuer_id=?',[$this->eventId,$issuer])->row_array();
        if(!$confirmed||(int)$confirmed['payment_id']!==$payment||$confirmed['financial_state']!=='applied_assisted')throw new \RuntimeException('issuer_bb_receipt_unconfirmed');$this->claim($db,$p,$issuer,$actor);if($this->captured['frame']!==$c['frame'])throw new \RuntimeException('issuer_bb_tariff_source_unconfirmed');$this->confirmReceipt($db,$p,$issuer,$payment,$c);if($this->financialFrame($db,$p,$issuer,$payment)!==$financial)throw new \RuntimeException('issuer_bb_tariff_source_unconfirmed');(new \CompanyInvoiceBalance($db,$p))->inOwned($issuer,$actor,(int)$c['origin']['invoice_id']);
    }

    private function insertImage($db,string $table,array &$image,bool $autoId=true):void
    {
        \IssuerStorage::query($db,'INSERT INTO '.$table.' (`'.implode('`,`',array_keys($image)).'`) VALUES('.implode(',',array_fill(0,count($image),'?')).')',array_values($image));if(!$autoId)return;$id=(int)$db->insert_id();if($id<1)throw new \RuntimeException('issuer_bb_tariff_source_unconfirmed');$image=['id'=>$id]+$image;
    }
    private function exactImage(array $actual,array $expected):void
    {
        if(array_diff(array_keys($actual),array_keys($expected))||array_diff(array_keys($expected),array_keys($actual)))throw new \RuntimeException('issuer_bb_tariff_source_unconfirmed');foreach($expected as$field=>$value)if((string)$actual[$field]!== (string)$value)throw new \RuntimeException('issuer_bb_tariff_source_unconfirmed');
    }
    private function nativeFrame($db,string $p,int $issuer,int $actor,int $invoice):string
    {
        $parent=\IssuerStorage::query($db,'SELECT * FROM '.$p.'invoices WHERE id=? AND issuer_id=? FOR UPDATE',[$invoice,$issuer])->row_array();if(!$parent)throw new \DomainException('bb_event_amount_divergence');unset($parent['status']);$parts=[$parent];
        foreach(['issuers_invoice_documents'=>['invoice_id',$invoice],'issuers_registry'=>['id',$issuer],'staff'=>['staffid',$actor],'clients'=>['userid',$parent['clientid']],'issuers_sequences'=>['issuer_id',$issuer]]as$table=>[$column,$value])$parts[]=\IssuerStorage::query($db,'SELECT * FROM '.$p.$table.' WHERE '.$column.'=? ORDER BY '.$column.' FOR UPDATE',[$value])->result_array();
        $parts[]=\IssuerStorage::query($db,"SELECT * FROM {$p}itemable WHERE rel_id=? AND rel_type='invoice' ORDER BY item_order,id FOR UPDATE",[$invoice])->result_array();$parts[]=\IssuerStorage::query($db,"SELECT * FROM {$p}item_tax WHERE rel_id=? AND rel_type='invoice' ORDER BY id FOR UPDATE",[$invoice])->result_array();
        foreach(['issuers_staff','issuers_resource_permissions']as$table)$parts[]=\IssuerStorage::query($db,'SELECT * FROM '.$p.$table.' WHERE issuer_id=? AND staff_id=? ORDER BY '.($table==='issuers_staff'?'staff_id':'feature,capability').' FOR UPDATE',[$issuer,$actor])->result_array();
        $parts[]=\IssuerStorage::query($db,'SELECT * FROM '.$p.'issuers_clients WHERE client_id=? FOR UPDATE',[$parent['clientid']])->result_array();$parts[]=\IssuerStorage::query($db,'SELECT * FROM '.$p.'issuers_client_links WHERE client_id=? ORDER BY issuer_id FOR UPDATE',[$parent['clientid']])->result_array();$parts[]=\IssuerStorage::query($db,"SELECT * FROM {$p}company_financial_events WHERE issuer_id=? AND source_type='invoice' AND source_id=? ORDER BY issuer_sequence FOR UPDATE",[$issuer,(string)$invoice])->result_array();
        return hash('sha256',json_encode($parts,JSON_THROW_ON_ERROR));
    }
    /** Prove native core audit semantics independently before binding their entire byte image. */
    private function confirmCoreAudits($db,string $p,int $issuer,int $payment):void
    {
        $receipt=\IssuerStorage::query($db,'SELECT * FROM '.$p.'company_payment_receipts WHERE issuer_id=? AND payment_id=? FOR UPDATE',[$issuer,$payment])->row_array();$component=\IssuerStorage::query($db,'SELECT * FROM '.$p.'company_bank_receipt_components WHERE issuer_id=? AND payment_id=? FOR UPDATE',[$issuer,$payment])->row_array();if(!$receipt||!$component||(int)$receipt['actor_id']!==(int)$component['created_by']||(int)$receipt['invoice_id']!==(int)$component['invoice_id']||$receipt['request_key']!==$component['request_key'])throw new \RuntimeException('issuer_bb_tariff_source_unconfirmed');
        $invoice=(int)$receipt['invoice_id'];$actor=(int)$receipt['actor_id'];$json=static fn(array$v):string=>json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $expected=[['actor_id'=>$actor,'event'=>'company_provider_payment_recorded','issuer_id'=>$issuer,'subject_id'=>$invoice,'metadata_json'=>null],['actor_id'=>$actor,'event'=>'company_bank_components_recorded','issuer_id'=>$issuer,'subject_id'=>$invoice,'metadata_json'=>$json(['request_key'=>$receipt['request_key'],'invoice_id'=>$invoice,'payment_id'=>$payment,'payload_hash'=>$component['payload_hash'],'evidence_hash'=>$component['evidence_hash']])]];
        $events=\IssuerStorage::query($db,"SELECT * FROM {$p}company_financial_events WHERE issuer_id=? AND source_type='payment' AND source_id=? ORDER BY issuer_sequence FOR UPDATE",[$issuer,(string)$payment])->result_array();if(count($events)!==2||array_diff(['bank_components_recorded','receipt_recorded'],array_column($events,'kind')))throw new \RuntimeException('issuer_bb_tariff_source_unconfirmed');foreach($events as$event){if((int)$event['actor_id']!==$actor)throw new \RuntimeException('issuer_bb_tariff_source_unconfirmed');$expected[]=['actor_id'=>$actor,'event'=>'company_financial_event_recorded','issuer_id'=>$issuer,'subject_id'=>$issuer,'metadata_json'=>json_encode(['evidence_key'=>$event['evidence_key'],'kind'=>$event['kind'],'source_type'=>$event['source_type'],'source_id'=>$event['source_id'],'payload_hash'=>$event['payload_hash']],JSON_THROW_ON_ERROR)];}
        $rows=\IssuerStorage::query($db,"SELECT * FROM {$p}issuers_audit WHERE issuer_id=? AND ((event IN ('company_provider_payment_recorded','company_bank_components_recorded') AND subject_id=?) OR (event='company_financial_event_recorded' AND JSON_UNQUOTE(JSON_EXTRACT(CASE WHEN JSON_VALID(metadata_json) THEN metadata_json ELSE '{}' END,'$.source_type'))='payment' AND JSON_UNQUOTE(JSON_EXTRACT(CASE WHEN JSON_VALID(metadata_json) THEN metadata_json ELSE '{}' END,'$.source_id'))=?)) ORDER BY id FOR UPDATE",[$issuer,$invoice,(string)$payment])->result_array();if(count($rows)!==count($expected))throw new \RuntimeException('issuer_bb_tariff_source_unconfirmed');foreach($rows as$row){$found=false;foreach($expected as$key=>$image){$matches=true;foreach($image as$field=>$value)if($value===null?$row[$field]!==null:(string)$row[$field]!== (string)$value){$matches=false;break;}if($matches){unset($expected[$key]);$found=true;break;}}if(!$found)throw new \RuntimeException('issuer_bb_tariff_source_unconfirmed');}
    }
    private function financialFrame($db,string $p,int $issuer,int $payment):string
    {
        $pay=\IssuerStorage::query($db,'SELECT * FROM '.$p.'invoicepaymentrecords WHERE id=? FOR UPDATE',[$payment])->row_array();if(!$pay)throw new \RuntimeException('issuer_bb_tariff_source_unconfirmed');$invoice=(int)$pay['invoiceid'];$parts=[$pay];foreach(['invoices'=>['id',$invoice],'issuers_invoice_documents'=>['invoice_id',$invoice],'company_payment_receipts'=>['payment_id',$payment],'company_bank_receipt_components'=>['payment_id',$payment]]as$table=>[$column,$id])$parts[]=\IssuerStorage::query($db,'SELECT * FROM '.$p.$table.' WHERE '.$column.'=? AND issuer_id=? FOR UPDATE',[$id,$issuer])->result_array();$parts[]=\IssuerStorage::query($db,"SELECT * FROM {$p}company_financial_events WHERE issuer_id=? AND source_type='payment' AND source_id=? ORDER BY issuer_sequence FOR UPDATE",[$issuer,(string)$payment])->result_array();$parts[]=\IssuerStorage::query($db,"SELECT * FROM {$p}issuers_audit WHERE issuer_id=? AND ((event IN ('company_provider_payment_recorded','company_bank_components_recorded') AND subject_id=?) OR (event='company_financial_event_recorded' AND JSON_UNQUOTE(JSON_EXTRACT(CASE WHEN JSON_VALID(metadata_json) THEN metadata_json ELSE '{}' END,'$.source_type'))='payment' AND JSON_UNQUOTE(JSON_EXTRACT(CASE WHEN JSON_VALID(metadata_json) THEN metadata_json ELSE '{}' END,'$.source_id'))=?)) ORDER BY id FOR UPDATE",[$issuer,$invoice,(string)$payment])->result_array();return hash('sha256',json_encode($parts,JSON_THROW_ON_ERROR));
    }
    private function frame($db,string $p,int $issuer,array $row,array $origin):string
    {
        // Full source/binding/provenance is captured independently of mutable financial_state.
        unset($row['financial_state']);$parts=[$row,$origin];foreach(['bb_connections'=>['id',$row['connection_id']],'bb_layout_bindings'=>['id',$row['layout_binding_id']]]as$table=>[$column,$id]){$rows=\IssuerStorage::query($db,'SELECT * FROM '.$p.$table.' WHERE '.$column.'=? AND issuer_id=? FOR UPDATE',[$id,$issuer])->result_array();if(count($rows)!==1)throw new \DomainException('bb_event_provenance_required');$parts[]=$rows;if($table==='bb_connections'){$current=$rows[0];$identity=hash('sha256',json_encode([$issuer,$current['account_id'],$current['environment'],$current['agreement_number'],$current['wallet'],$current['variation']],JSON_THROW_ON_ERROR));if($identity!==$current['identity_hash']||hash('sha256',json_encode([$identity,$current['issuer_tax_id'],$current['issuer_name']],JSON_THROW_ON_ERROR))!==$current['configuration_hash'])throw new \DomainException('bb_layout_binding_mismatch');}else{$binding=(new ModuleStore($db,$p))->layoutBinding((int)$id,$current);if($binding['layout_id']!==Agreement::LAYOUT_ID)throw new \DomainException('bb_layout_binding_mismatch');}}
        $parts[]=\IssuerStorage::query($db,"SELECT f.*,x.lot_number link_lot,x.record_sequence link_sequence,x.layout_binding_id link_binding FROM {$p}bb_return_file_events x JOIN {$p}bb_return_files f ON f.id=x.return_file_id WHERE x.return_event_id=? AND x.issuer_id=? AND f.issuer_id=? ORDER BY f.id FOR UPDATE",[$this->eventId,$issuer,$issuer])->result_array();$parts[]=\IssuerStorage::query($db,'SELECT * FROM '.$p.'bb_return_file_events WHERE return_event_id=? ORDER BY id FOR UPDATE',[$this->eventId])->result_array();$parts[]=$this->originationFrame($db,$p,$issuer,$row,$origin);return hash('sha256',json_encode($parts,JSON_THROW_ON_ERROR));
    }
    private function originationFrame($db,string $p,int $issuer,array $event,array $origin):array
    {
        $one=static function(string $table,string $column,int $id)use($db,$p):array{
            $rows=\IssuerStorage::query($db,'SELECT * FROM '.$p.$table.' WHERE '.$column.'=? FOR UPDATE',[$id])->result_array();
            if(count($rows)!==1)throw new \DomainException('bb_event_provenance_required');return $rows[0];
        };
        $link=$one('bb_event_links','event_id',$this->eventId);
        if((int)$link['issuer_id']!==$issuer||(int)$link['origin_id']!==(int)$origin['id'])throw new \DomainException('bb_event_provenance_required');
        $memberships=\IssuerStorage::query($db,'SELECT * FROM '.$p.'bb_batch_origins WHERE origin_id=? FOR UPDATE',[$origin['id']])->result_array();
        if(count($memberships)!==1)throw new \DomainException('bb_event_provenance_required');
        $batch=$one('bb_batches','id',(int)$memberships[0]['batch_id']);
        if((int)$batch['issuer_id']!==$issuer||(int)$batch['connection_id']!==(int)$event['connection_id']||$batch['scope_key']!==$event['scope_key']||(int)$batch['layout_binding_id']!==(int)$event['layout_binding_id']||$batch['layout_id']!==Agreement::LAYOUT_ID||$batch['layout_source_hash']!==Agreement::SOURCE_SHA256||$batch['generation_state']!=='generated'||$batch['delivery_state']!=='reported_submitted'||!hash_equals($batch['content_hash'],hash('sha256',$batch['content'])))throw new \DomainException('bb_event_provenance_required');
        $binding=$one('bb_layout_bindings','id',(int)$batch['layout_binding_id']);
        $allLinks=\IssuerStorage::query($db,'SELECT * FROM '.$p.'bb_batch_origins WHERE batch_id=? ORDER BY origin_id FOR UPDATE',[$batch['id']])->result_array();
        if(!$allLinks||count($allLinks)>100)throw new \DomainException('bb_event_provenance_required');
        $origins=[];$snapshots=[];$contracts=[];$titles=[];$agreement=null;$generationDate=null;
        foreach($allLinks as$member){
            $child=$one('bb_origins','id',(int)$member['origin_id']);$snapshot=$one('bb_origin_snapshots','origin_id',(int)$child['id']);
            if((int)$child['issuer_id']!==$issuer||(int)$child['connection_id']!==(int)$batch['connection_id']||$child['scope_key']!==$batch['scope_key']||$child['provider']!=='banco_brasil'||$child['channel']!=='cnab240'||(int)$child['layout_binding_id']!==(int)$batch['layout_binding_id']||(int)$snapshot['layout_binding_id']!==(int)$batch['layout_binding_id']||!hash_equals($child['payload_hash'],hash('sha256',$snapshot['snapshot_json'])))throw new \DomainException('bb_event_provenance_required');
            $data=json_decode($snapshot['snapshot_json'],true,32,JSON_THROW_ON_ERROR);$connection=$data['connection']??[];
            if((int)($data['contract_id']??0)!==(int)$snapshot['contract_id']||(int)($data['layout_binding_id']??0)!==(int)$batch['layout_binding_id']||($data['scope_key']??null)!==$batch['scope_key']||(int)($data['invoice']['id']??0)!==(int)$child['invoice_id']||(int)($data['invoice']['issuer_id']??0)!==$issuer||(int)($connection['id']??0)!==(int)$batch['connection_id'])throw new \DomainException('bb_event_provenance_required');
            $contract=$one('bb_contract_declarations','id',(int)$snapshot['contract_id']);
            if((int)$contract['issuer_id']!==$issuer||(int)$contract['connection_id']!==(int)$batch['connection_id']||(int)$contract['layout_binding_id']!==(int)$batch['layout_binding_id']||!hash_equals($contract['declaration_hash'],hash('sha256',$contract['declaration_json'])))throw new \DomainException('bb_event_provenance_required');
            $declared=json_decode($contract['declaration_json'],true,16,JSON_THROW_ON_ERROR);
            if(($declared['profile_hash']??null)!==$binding['profile_hash']||($declared['connection_hash']??null)!==($connection['configuration_hash']??null)||($declared['reference']??null)!==$contract['reference']||($declared['beneficiary_name']??null)!==($data['beneficiary_name']??null)||($declared['verification']??null)!=='operator_declaration_not_bank_verification'||($declared['interest_exempt']??null)!==true||($declared['exclusive_range']??null)!==true||(($connection['environment']??null)==='sandbox'&&($declared['test_exchange']??null)!==true))throw new \DomainException('bb_event_provenance_required');
            $a=new Agreement(new Scope($issuer,(int)$connection['id'],$connection['account_id'],$connection['environment'],$connection['agreement_number']),$connection['issuer_tax_id'],$data['beneficiary_name'],$connection['variation'],$connection['wallet']);
            if($a->scope->key()!==$batch['scope_key']||$child['account_id']!==$connection['account_id']||$child['environment']!==$connection['environment']||$child['agreement_number']!==$connection['agreement_number']||$child['remote_id']!==$a->ourNumber($data['title']['sequence'])||$child['control_reference']!==$data['title']['control'])throw new \DomainException('bb_event_provenance_required');
            if($agreement!==null&&($agreement!==$connection||$generationDate!==$data['generation_date']))throw new \DomainException('bb_event_provenance_required');
            $agreement=$connection;$generationDate=$data['generation_date'];$origins[]=$child;$snapshots[]=$snapshot;$contracts[(int)$contract['id']]=$contract;$titles[(int)$child['invoice_id']]=$data['title'];$codecAgreement=$a;
        }
        ksort($titles,SORT_NUMERIC);ksort($contracts,SORT_NUMERIC);
        if((new Cnab240Generator())->generate($codecAgreement,(int)$batch['file_sequence'],$generationDate,array_values($titles))!==$batch['content'])throw new \DomainException('bb_event_provenance_required');
        $actions=\IssuerStorage::query($db,'SELECT * FROM '.$p.'bb_batch_actions WHERE batch_id=? ORDER BY id FOR UPDATE',[$batch['id']])->result_array();
        $manual=array_values(array_filter($actions,static fn(array $action):bool=>$action['action']==='manual_submission_reported'));
        if(count($manual)!==1||(int)$manual[0]['issuer_id']!==$issuer||(int)$manual[0]['actor_id']<1||trim($manual[0]['reference'])==='')throw new \DomainException('bb_event_provenance_required');
        return [$link,$memberships,$batch,$allLinks,$origins,$snapshots,array_values($contracts),$actions];
    }
    private function confirmReceipt($db,string $p,int $issuer,int $payment,array $c):void
    {
        $receipt=\IssuerStorage::query($db,'SELECT * FROM '.$p.'bb_event_receipts WHERE event_id=? AND issuer_id=? FOR UPDATE',[$this->eventId,$issuer])->row_array();if(!$receipt||(int)$receipt['payment_id']!==$payment||(int)$receipt['origin_id']!==(int)$c['origin']['id']||$receipt['canonical_key']!==$c['key']||$receipt['evidence_hash']!==$c['evidence'])throw new \RuntimeException('issuer_bb_tariff_source_unconfirmed');
        $original=\IssuerStorage::query($db,'SELECT created_by FROM '.$p.'company_bank_receipt_components WHERE payment_id=? AND issuer_id=? FOR UPDATE',[$payment,$issuer])->row_array();if(!$original||(int)$receipt['created_by']!==(int)$original['created_by'])throw new \RuntimeException('issuer_bb_tariff_source_unconfirmed');$review=\IssuerStorage::query($db,'SELECT * FROM '.$p.'bb_event_reviews WHERE review_key=? AND issuer_id=? FOR UPDATE',[$c['review']['review_key']??'', $issuer])->row_array();$audit=\IssuerStorage::query($db,'SELECT * FROM '.$p.'bb_audit WHERE id=? AND issuer_id=? FOR UPDATE',[$c['audit']['id']??0,$issuer])->row_array();$this->exactImage($receipt,$c['receipt']);$this->exactImage($review,$c['review']);$this->exactImage($audit,$c['audit']);
        if((int)($review['event_id']??0)!==$this->eventId||(int)($review['actor_id']??0)!==(int)$receipt['created_by']||($review['decision']??null)!=='tariff_credit_confirmed'||($review['reason']??null)!==$this->reason||($review['evidence_reference']??null)!==$this->reference||(int)($audit['actor_id']??0)!==(int)$receipt['created_by']||($audit['action']??null)!=='assisted_tariff_credit_applied'||(int)($audit['connection_id']??0)!==(int)$c['row']['connection_id']||($audit['resource_type']??null)!=='return_event'||(int)($audit['resource_id']??0)!==$this->eventId)throw new \RuntimeException('issuer_bb_tariff_source_unconfirmed');
        if($receipt['created_at']!==$c['sourceTime']||($review['created_at']??null)!==$c['sourceTime']||($audit['created_at']??null)!==$c['sourceTime'])throw new \RuntimeException('issuer_bb_tariff_source_unconfirmed');
        Reconciliation::authorize($db,$p,$issuer,$c['actor'],'reconcile');Reconciliation::authorize($db,$p,$issuer,$c['actor'],'reconcile_tariff');
    }
}
