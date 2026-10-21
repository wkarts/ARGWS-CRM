<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once APPPATH.'services/CompanyLocalNoncashSource.php';
require_once APPPATH.'services/CompanyLocalNoncash.php';
require_once __DIR__.'/ApiService.php';require_once __DIR__.'/ApiFinancialMutationFence.php';require_once __DIR__.'/Reconciliation.php';

/** A zero-cash BB16 observation is evidence for a separately consented LOCAL reduction, never evidence of received money. */
final class Api16LocalNoncash implements \CompanyLocalNoncashSource
{
    private array$input;private bool$hasAsaas=false;
    public function __construct(private int$originId,private int$operationId,array$input){\IssuerPolicy::id($originId);\IssuerPolicy::id($operationId);$this->input=\CompanyLocalNoncash::input($input);}
    public function originId():int{return$this->originId;}
    public function input():array{return$this->input;}
    public function tables():array{return array_values(array_unique(array_merge(ApiService::receiptTables(),['bb_return_events','bb_event_receipts'],\CompanyLocalNoncashSchema::tables(),\CompanyLocalNoncashIntegrity::tables(),$this->hasAsaas?['asaas_payment_origins']:[])));}
    public function assertSchema($db,string$p):void
    {
        require_once __DIR__.'/ApiWebhookSchema.php';require_once __DIR__.'/ReconciliationSchema.php';require_once APPPATH.'services/CompanyInvoices.php';
        ApiWebhookSchema::ensure($db,$p,false);ApiSchema::ensure($db,$p,false);ApiReceiptSchema::ensure($db,$p,false);OriginationSchema::assertValid($db,$p);ReconciliationSchema::ensure($db,$p,false);\CompanyLocalNoncashSchema::ensure($db,$p,false);$this->hasAsaas=$db->table_exists($p.'asaas_payment_origins');
    }
    private static function rows($db,string$sql,array$args=[]):array{return \IssuerStorage::query($db,$sql,$args)->result_array();}
    private static function date($value):string
    {
        if(!is_string($value)||!preg_match('/^([0-9]{2})\.([0-9]{2})\.([0-9]{4})$/D',$value,$m))throw new \DomainException('bb_api_credit_date_requires_review');$iso=$m[3].'-'.$m[2].'-'.$m[1];try{Fields::date($iso);}catch(\Throwable$error){throw new \DomainException('bb_api_credit_date_requires_review');}return$iso;
    }
    private function authority($db,string$p,int$issuer,int$actor):array
    {
        \IssuerTransaction::assertOwned($db,$p,$this->tables());[$origin,,$snapshot]=(new ApiService($db,$p))->receiptOriginInOwnedTransaction($issuer,$actor,$this->originId);Reconciliation::authorize($db,$p,$issuer,$actor,'reconcile_api_components');$acl=new \IssuerResourceAccess($db,$p);foreach([['banco_brasil','local_noncash'],['invoices','view'],['invoices','edit'],['payments','create']]as[$feature,$cap])if(!$acl->can($issuer,$actor,$feature,$cap))throw new \DomainException('company_local_noncash_denied');return[$origin,$snapshot];
    }
    public function capture($db,string$p,int$issuer,int$actor):array
    {
        [$origin,$snapshot]=$this->authority($db,$p,$issuer,$actor);
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
        ApiMovements::assertNoAdverseHistory($db,$p,$origin,true);
        $zero=['valorPagamentoParcialTitulo','valorImpostoSobreOprFinanceirasRecebidoTitulo','valorMultaRecebido','valorReajuste','valorOutroRecebido'];
        foreach($zero as $field)if(!array_key_exists($field,$result)||\app\services\InvoicePaymentWriter::minorUnits($result[$field])!==0)throw new \DomainException('bb_api_credit_adjustments_require_review');
        $nominal=\app\services\InvoicePaymentWriter::minorUnits($snapshot['invoice']['total']);
        foreach(['valorOriginalTituloCobranca'] as $field)if(!isset($result[$field])||\app\services\InvoicePaymentWriter::minorUnits($result[$field])!==$nominal||$nominal<1)throw new \DomainException('bb_api_credit_amount_divergence');
        $credit=self::date($result['dataCreditoLiquidacao']??null);$today=(new \DateTimeImmutable('now',new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
        if($credit>$today||$credit<$snapshot['invoice']['date'])throw new \DomainException('bb_api_credit_date_requires_review');
        $parent=self::rows($db,"SELECT i.id,i.clientid,i.currency,i.total,i.date,i.duedate FROM {$p}invoices i WHERE i.id=? AND i.issuer_id=? FOR UPDATE",[$origin['invoice_id'],$issuer])[0]??null;
        if(!$parent||(new \CompanyCurrencyUnits($db,$p))->resolveRead($parent['currency'])['iso_code']!==$snapshot['invoice']['currency']||\app\services\InvoicePaymentWriter::minorUnits($parent['total'])!==$nominal)throw new \DomainException('bb_api_credit_snapshot_divergence');
        foreach(['id','clientid','date','duedate'] as $field)if((string)$parent[$field]!== (string)$snapshot['invoice'][$field])throw new \DomainException('bb_api_credit_snapshot_divergence');
        $document=self::rows($db,"SELECT * FROM {$p}issuers_invoice_documents WHERE invoice_id=? AND issuer_id=? FOR UPDATE",[$parent['id'],$issuer])[0]??[];
        foreach(['legal_identity_json','recipient_identity_json','formatted_number'] as $field)if(!isset($document[$field])||$document[$field]!==$snapshot['document'][$field])throw new \DomainException('bb_api_credit_snapshot_divergence');
        if($this->hasAsaas&&self::rows($db,"SELECT id FROM {$p}asaas_payment_origins WHERE invoice_id=? FOR UPDATE",[$parent['id']]))throw new \DomainException('bb_api_credit_multiple_providers');
        $adverse=self::rows($db,"SELECT e.id FROM {$p}bb_return_events e JOIN {$p}bb_connections c ON c.id=e.connection_id AND c.issuer_id=e.issuer_id WHERE e.issuer_id=? AND c.account_id=? AND c.environment=? AND c.agreement_number=? AND e.remote_id IN (?,?) AND (e.occurrence IN ('44','50','93') OR e.observation IN ('cheque_returned','cheque_pending_clearance','operational_down_reported')) FOR UPDATE",[$issuer,$origin['account_id'],$origin['environment'],$origin['agreement_number'],$origin['remote_id'],substr($origin['remote_id'],3)]);
        if($adverse)throw new \DomainException('bb_api_credit_adverse_history');
        $cross=self::rows($db,"SELECT r.payment_id FROM {$p}bb_event_receipts r JOIN {$p}bb_origins o ON o.id=r.origin_id WHERE r.issuer_id=? AND o.account_id=? AND o.environment=? AND o.agreement_number=? AND o.remote_id IN (?,?) FOR UPDATE",[$issuer,$origin['account_id'],$origin['environment'],$origin['agreement_number'],$origin['remote_id'],substr($origin['remote_id'],3)]);
        if($cross)throw new \DomainException('bb_api_credit_already_recorded_cnab');
        foreach(['valorPagoSacado','valorCreditoCedente','valorJuroMoraRecebido']as$field)if(!array_key_exists($field,$result)||\app\services\InvoicePaymentWriter::minorUnits($result[$field])!==0)throw new \DomainException('company_local_noncash_requires_zero_cash');
        $discount=\app\services\InvoicePaymentWriter::minorUnits($result['valorDescontoUtilizado']??null);$abatement=\app\services\InvoicePaymentWriter::minorUnits($result['valorAbatimentoTotal']??null);if($discount>$nominal||$abatement!==$nominal-$discount||Fields::cents($this->input['discount'])!==$discount||Fields::cents($this->input['abatement'])!==$abatement)throw new \DomainException('company_local_noncash_equation_invalid');
        if($operation['updated_at']<gmdate('Y-m-d H:i:s',time()-300)||$operation['updated_at']>gmdate('Y-m-d H:i:s'))throw new \DomainException('bb_api_credit_query_stale');ApiFinancialMutationFence::assertResolvedInOwned($db,$p,$this->originId,$operation,$result);
        if($this->input['book_date']<$parent['date']||$this->input['book_date']<$credit)throw new \DomainException('company_local_noncash_invalid');
        return ['invoice_id'=>(int)$parent['id'],'origin_id'=>$this->originId,'operation_id'=>$this->operationId,'origin_hash'=>$origin['payload_hash'],'query_hash'=>$operation['response_hash'],'query_projection_hash'=>hash('sha256',\CompanyLocalNoncash::json($result)),'account_hash'=>$result['account_evidence_hash'],'query_credit_date'=>$credit];
    }
    public function confirmCurrent($db,string$p,int$i,int$a,array$captured,bool$new):void
    {
        if($new){$again=$this->capture($db,$p,$i,$a);foreach($again as$key=>$value)if(($captured[$key]??null)!==$value)throw new \DomainException('company_local_noncash_conflict');return;}
        [$origin]=$this->authority($db,$p,$i,$a);$operation=self::rows($db,'SELECT * FROM '.$p.'bb_api_operations WHERE id=? AND origin_id=? FOR UPDATE',[$this->operationId,$this->originId])[0]??null;
        if(!$operation||$operation['operation']!=='query'||$operation['status']!=='validated'||(int)$operation['http_status']!==200||($captured['origin_id']??null)!==$this->originId||($captured['operation_id']??null)!==$this->operationId||($captured['invoice_id']??null)!==(int)$origin['invoice_id']||($captured['origin_hash']??null)!==$origin['payload_hash']||($captured['query_hash']??null)!==$operation['response_hash']||($captured['query_projection_hash']??null)!==hash('sha256',\CompanyLocalNoncash::json(json_decode($operation['result_json'],true,32,JSON_THROW_ON_ERROR))))throw new \DomainException('company_local_noncash_conflict');
    }
}
