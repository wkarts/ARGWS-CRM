<?php

declare(strict_types=1);
namespace BancoBrasil;
require_once APPPATH.'services/CompanyReceiptSource.php';
require_once __DIR__.'/ApiService.php';

/** Explicit receipt from a completed, account-proven API query; never trusts an upload/webhook/header. */
final class ApiCreditedReceipt implements \CompanyReceiptSource
{
    private ?array $captured=null;
    private bool $hasAsaas=false;
    public function __construct(private int $originId,private int $operationId)
    {
        \IssuerPolicy::id($originId);\IssuerPolicy::id($operationId);
    }
    public function tables():array{return array_merge(array_merge(ApiService::receiptTables(),['bb_return_events','bb_event_receipts','bb_api_notifications','bb_api_webhook_receivers']),$this->hasAsaas?['asaas_payment_origins']:[]);}
    public function assertSchema($db,string $p):void
    {
        require_once __DIR__.'/ApiWebhookSchema.php';ApiWebhookSchema::ensure($db,$p,false);
        ApiSchema::ensure($db,$p,false);ApiReceiptSchema::ensure($db,$p,false);OriginationSchema::assertValid($db,$p);
        require_once __DIR__.'/ReconciliationSchema.php';ReconciliationSchema::ensure($db,$p,false);
        $this->hasAsaas=$db->table_exists($p.'asaas_payment_origins');
    }
    private static function rows($db,string $sql,array $args=[]):array{return \IssuerStorage::query($db,$sql,$args)->result_array();}
    private static function date($value):string
    {
        if(!is_string($value)||!preg_match('/^([0-9]{2})\.([0-9]{2})\.([0-9]{4})$/D',$value,$m))throw new \DomainException('bb_api_credit_date_requires_review');
        $iso=$m[3].'-'.$m[2].'-'.$m[1];try{Fields::date($iso);}catch(\Throwable $e){throw new \DomainException('bb_api_credit_date_requires_review');}return $iso;
    }
    public function claim($db,string $p,int $issuer,int $actor):array
    {
        \IssuerTransaction::assertOwned($db,$p,array_merge($this->tables(),['company_payment_receipts']));
        [$origin,,$snapshot]=$this->source($db,$p,$issuer,$actor);
        if($origin['state']!=='api_settlement_observed'||(int)$origin['active_slot']!==1)throw new \DomainException('bb_api_credit_state_requires_review');
        $operation=self::rows($db,"SELECT * FROM {$p}bb_api_operations WHERE origin_id=? AND operation='query' ORDER BY id DESC LIMIT 1 FOR UPDATE",[$this->originId])[0]??null;
        if(!$operation||(int)$operation['id']!==$this->operationId||$operation['status']!=='validated'||(int)$operation['http_status']!==200||!preg_match('/^[a-f0-9]{64}$/D',$operation['response_hash']))throw new \DomainException('bb_api_credit_query_stale');
        $result=json_decode($operation['result_json'],true,32,JSON_THROW_ON_ERROR);
        //16=CREDITADO.6=LIQUIDADO, partial/cheque/cartório and operational down states are insufficient.
        if(($result['codigoEstadoTituloCobranca']??null)!=='16'||($result['account_scope_confirmed']??null)!=='1'||!preg_match('/^[a-f0-9]{64}$/D',$result['account_evidence_hash']??'')||!in_array($result['codigoTipoLiquidacao']??null,['1','2','7','8'],true)||!in_array($result['codigoNaturezaRecebimento']??null,['1','7'],true))throw new \DomainException('bb_api_credit_state_requires_review');
        foreach(self::rows($db,"SELECT result_json FROM {$p}bb_api_operations WHERE origin_id=? AND operation='query' AND status='validated' AND id<?",[$this->originId,$this->operationId]) as $prior){
            $history=json_decode($prior['result_json'],true,32,JSON_THROW_ON_ERROR);
            if(in_array($history['codigoEstadoTituloCobranca']??null,['2','3','4','5','7','8','9','10','11','12','13','17','18','19'],true))throw new \DomainException('bb_api_credit_adverse_history');
        }
        foreach(self::rows($db,"SELECT n.projection_json FROM {$p}bb_api_notifications n JOIN {$p}bb_origins o ON o.id=n.origin_id AND o.issuer_id=n.issuer_id WHERE n.issuer_id=? AND o.account_id=? AND o.environment=? AND o.agreement_number=? AND o.remote_id IN (?,?) FOR UPDATE",[$issuer,$origin['account_id'],$origin['environment'],$origin['agreement_number'],$origin['remote_id'],substr($origin['remote_id'],3)]) as $notification){$observed=json_decode($notification['projection_json'],true,32,JSON_THROW_ON_ERROR);if(($observed['codigoEstadoBaixaOperacional']??null)==='10')throw new \DomainException('bb_api_credit_adverse_history');}
        $zero=['valorPagamentoParcialTitulo','valorImpostoSobreOprFinanceirasRecebidoTitulo','valorAbatimentoTotal','valorJuroMoraRecebido','valorDescontoUtilizado','valorMultaRecebido','valorReajuste','valorOutroRecebido'];
        foreach($zero as $field)if(!array_key_exists($field,$result)||\app\services\InvoicePaymentWriter::minorUnits($result[$field])!==0)throw new \DomainException('bb_api_credit_adjustments_require_review');
        $nominal=\app\services\InvoicePaymentWriter::minorUnits($snapshot['invoice']['total']);
        foreach(['valorOriginalTituloCobranca','valorPagoSacado','valorCreditoCedente'] as $field)if(!isset($result[$field])||\app\services\InvoicePaymentWriter::minorUnits($result[$field])!==$nominal||$nominal<1)throw new \DomainException('bb_api_credit_amount_divergence');
        $credit=self::date($result['dataCreditoLiquidacao']??null);$today=(new \DateTimeImmutable('now',new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
        if($credit>$today||$credit<$snapshot['invoice']['date'])throw new \DomainException('bb_api_credit_date_requires_review');
        $parent=self::rows($db,"SELECT i.id,i.clientid,i.currency,i.total,i.date,i.duedate,c.name currency_name FROM {$p}invoices i JOIN {$p}currencies c ON c.id=i.currency WHERE i.id=? AND i.issuer_id=? FOR UPDATE",[$origin['invoice_id'],$issuer])[0]??null;
        if(!$parent||$parent['currency_name']!==$snapshot['invoice']['currency']||\app\services\InvoicePaymentWriter::minorUnits($parent['total'])!==$nominal)throw new \DomainException('bb_api_credit_snapshot_divergence');
        foreach(['id','clientid','date','duedate'] as $field)if((string)$parent[$field]!== (string)$snapshot['invoice'][$field])throw new \DomainException('bb_api_credit_snapshot_divergence');
        $document=self::rows($db,"SELECT * FROM {$p}issuers_invoice_documents WHERE invoice_id=? AND issuer_id=? FOR UPDATE",[$parent['id'],$issuer])[0]??[];
        foreach(['legal_identity_json','recipient_identity_json','formatted_number'] as $field)if(!isset($document[$field])||$document[$field]!==$snapshot['document'][$field])throw new \DomainException('bb_api_credit_snapshot_divergence');
        if($this->hasAsaas&&self::rows($db,"SELECT id FROM {$p}asaas_payment_origins WHERE invoice_id=? FOR UPDATE",[$parent['id']]))throw new \DomainException('bb_api_credit_multiple_providers');
        $adverse=self::rows($db,"SELECT e.id FROM {$p}bb_return_events e JOIN {$p}bb_connections c ON c.id=e.connection_id AND c.issuer_id=e.issuer_id WHERE e.issuer_id=? AND c.account_id=? AND c.environment=? AND c.agreement_number=? AND e.remote_id IN (?,?) AND (e.occurrence IN ('44','50','93') OR e.observation IN ('cheque_returned','cheque_pending_clearance','operational_down_reported')) FOR UPDATE",[$issuer,$origin['account_id'],$origin['environment'],$origin['agreement_number'],$origin['remote_id'],substr($origin['remote_id'],3)]);
        if($adverse)throw new \DomainException('bb_api_credit_adverse_history');
        $cross=self::rows($db,"SELECT r.payment_id FROM {$p}bb_event_receipts r JOIN {$p}bb_origins o ON o.id=r.origin_id WHERE r.issuer_id=? AND o.account_id=? AND o.environment=? AND o.agreement_number=? AND o.remote_id IN (?,?) FOR UPDATE",[$issuer,$origin['account_id'],$origin['environment'],$origin['agreement_number'],$origin['remote_id'],substr($origin['remote_id'],3)]);
        if($cross)throw new \DomainException('bb_api_credit_already_recorded_cnab');
        // One key per immutable title, independent of UI request/actor/query ID. A differing later credit conflicts.
        $key=hash('sha256',json_encode(['bb-api-credit',$issuer,$origin['account_id'],$origin['environment'],$origin['agreement_number'],$origin['remote_id']],JSON_THROW_ON_ERROR));
        $evidence=hash('sha256',json_encode([$origin['payload_hash'],$snapshot['api_stage'],$result['numeroContratoCobranca'],$nominal,$credit,'16','account_proven_api_credit'],JSON_THROW_ON_ERROR));
        $old=self::rows($db,"SELECT * FROM {$p}bb_api_receipts WHERE origin_id=? OR canonical_key=? FOR UPDATE",[$this->originId,$key]);
        if(count($old)>1||($old&&((int)$old[0]['origin_id']!==$this->originId||(int)$old[0]['issuer_id']!==$issuer||$old[0]['canonical_key']!==$key||$old[0]['evidence_hash']!==$evidence)))throw new \DomainException('bb_api_credit_receipt_conflict');
        if(!$old&&$operation['updated_at']<gmdate('Y-m-d H:i:s',time()-300))throw new \DomainException('bb_api_credit_query_stale');
        $this->captured=compact('issuer','actor','origin','key','evidence');
        return ['invoice'=>(int)$parent['id'],'key'=>$key,'input'=>['amount'=>Fields::amount($nominal),'date'=>$credit,'reference'=>'BBAPI-'.$key,'note'=>'Crédito integral sem ajustes, confirmado por consulta API vinculada à conta de origem.']];
    }
    private function source($db,string $p,int $issuer,int $actor):array
    {
        return (new ApiService($db,$p))->receiptOriginInOwnedTransaction($issuer,$actor,$this->originId);
    }
    public function applied($db,string $p,int $issuer,int $actor,int $payment):void
    {
        $c=$this->captured;if(!$c||$c['issuer']!==$issuer||$c['actor']!==$actor)throw new \DomainException('bb_api_credit_claim_required');
        $this->source($db,$p,$issuer,$actor);$old=self::rows($db,"SELECT * FROM {$p}bb_api_receipts WHERE origin_id=? FOR UPDATE",[$this->originId])[0]??null;
        if($old){if((int)$old['payment_id']!==$payment||$old['canonical_key']!==$c['key']||$old['evidence_hash']!==$c['evidence'])throw new \DomainException('bb_api_credit_receipt_conflict');return;}
        \IssuerStorage::query($db,"INSERT INTO {$p}bb_api_receipts(origin_id,issuer_id,operation_id,canonical_key,payment_id,evidence_hash,created_by,created_at) VALUES(?,?,?,?,?,?,?,?)",[$this->originId,$issuer,$this->operationId,$c['key'],$payment,$c['evidence'],$actor,date('Y-m-d H:i:s')]);
        \IssuerStorage::query($db,"INSERT INTO {$p}bb_audit(issuer_id,connection_id,actor_id,action,resource_type,resource_id,created_at) VALUES(?,?,?,'api_credit_applied','api_origin',?,?)",[$issuer,$c['origin']['connection_id'],$actor,$this->originId,date('Y-m-d H:i:s')]);
        $audit=(int)$db->insert_id();
        $confirmed=self::rows($db,"SELECT payment_id,evidence_hash FROM {$p}bb_api_receipts WHERE origin_id=? AND issuer_id=?",[$this->originId,$issuer])[0]??[];
        if((int)($confirmed['payment_id']??0)!==$payment||($confirmed['evidence_hash']??null)!==$c['evidence']||$audit<1||!self::rows($db,"SELECT id FROM {$p}bb_audit WHERE id=? AND issuer_id=? AND resource_id=?",[$audit,$issuer,$this->originId]))throw new \RuntimeException('issuer_bb_api_credit_unconfirmed');
    }
}
