<?php
declare(strict_types=1);
require_once __DIR__.'/PigeBridgeChargeProtocol.php';
require_once __DIR__.'/PigeBridgeInvoices.php';
require_once __DIR__.'/PigeBridgeExternalConfiguration.php';
require_once __DIR__.'/../../asaas/libraries/Asaas/CompanyService.php';
/** Explicit Asaas adapter. All financial ownership comes from persisted connection/origin, not UI session. */
final class PigeBridgeCharges
{
    public function __construct(private $db,private $encryption,private string $prefix,private $transport=null)
    {if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)||($transport!==null&&!is_callable($transport)))throw new InvalidArgumentException('invalid_adapter');}
    public static function tables():array{return array_values(array_unique(array_merge(PigeBridgeExternalConfiguration::tables(),PigeBridgeInvoices::tables(),Asaas\CompanyService::tables(),['pige_bridge_charge_origins','company_invoice_requests','asaas_company_payers'])));}
    private static function json(array $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function config():PigeBridgeExternalConfiguration{return new PigeBridgeExternalConfiguration($this->db,$this->encryption,$this->prefix);}
    private function invoice(array $event,string $connection,string $secret):array{return (new PigeBridgeInvoices($this->db,$this->encryption,$this->prefix))->verifiedOrigin($connection,$event['scope'],$event['data']['charge_id'],$event['event_id'],$secret);}
    private function row(string $connection,string $charge):?array{return $this->rows('SELECT * FROM '.$this->prefix.'pige_bridge_charge_origins WHERE connection_key=? AND charge_id=?',[$connection,$charge])[0]??null;}
    private static function snapshot(array $row):array
    {
        if(!hash_equals($row['origin_hash'],hash('sha256',$row['origin_json'])))throw new DomainException('pige_charge_origin_changed');$v=json_decode($row['origin_json'],true,32,JSON_THROW_ON_ERROR);
        foreach(['connection_key','request_key','request_id'] as $key)if(($v[$key]??null)!==$row[$key])throw new DomainException('pige_charge_origin_changed');
        if(!hash_equals($row['payload_hash'],PigeBridgeChargeProtocol::hash($v['event']))||$v['event']['event_id']!==$row['event_id']||$v['event']['data']['charge_id']!==$row['charge_id'])throw new DomainException('pige_charge_origin_changed');return $v;
    }
    private function payer(array $event,array $config,array $invoice):array
    {
        $row=$this->rows('SELECT * FROM '.$this->prefix.'asaas_company_payers WHERE id=?',[$event['data']['payer_id']])[0]??null;$clientId=$invoice['snapshot']['event']['data']['client_id'];
        if(!$row||(int)$row['issuer_id']!==(int)$event['scope']['issuer_id']||(int)$row['account_row']!==$config['mapping']['account_row']||(int)$row['client_id']!==$clientId||$row['state']!=='ready'||!is_string($row['customer_id'])||!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$row['customer_id'])||!is_string($row['proof_hash'])||!preg_match('/^[a-f0-9]{64}$/D',$row['proof_hash']))throw new DomainException('pige_existing_payer_required');
        $client=$this->rows('SELECT c.company,c.vat FROM '.$this->prefix.'clients c JOIN '.$this->prefix.'issuers_clients o ON o.client_id=c.userid WHERE c.userid=? AND c.active=1 AND o.owner_issuer_id=?',[$clientId,(int)$event['scope']['issuer_id']])[0]??null;
        if(!$client)throw new DomainException('pige_existing_payer_required');$identity=['client_id'=>$clientId,'name'=>trim((string)$client['company']),'cpfCnpj'=>IssuerPolicy::taxId($client['vat'])];
        if(!hash_equals($row['snapshot_hash'],hash('sha256',self::json($identity))))throw new DomainException('pige_payer_identity_changed');
        $proof=['id'=>$row['customer_id'],'external_reference'=>$row['external_reference'],'identity_hash'=>$row['snapshot_hash'],'notifications_disabled'=>true];if(!hash_equals($row['proof_hash'],hash('sha256',self::json($proof))))throw new DomainException('pige_payer_proof_changed');
        return ['payer_id'=>(int)$row['id'],'client_id'=>$clientId,'customer_id'=>$row['customer_id'],'snapshot_hash'=>$row['snapshot_hash'],'proof_hash'=>$row['proof_hash']];
    }
    private function guard(array $origin,string $secret,bool $reconcile=false):void
    {
        IssuerTransaction::assertOwned($this->db,$this->prefix,self::tables());$row=$this->row($origin['connection_key'],$origin['charge_id']);if(!$row||$row!==$origin)throw new DomainException('pige_charge_origin_changed');$v=self::snapshot($row);
        $config=$this->config()->assertInOwned($row['connection_key'],$v['event']['scope'],$secret,$v['external_revision'],$reconcile);
        if(!hash_equals($v['secret_hash'],$secret)||$config['mapping']!==$v['mapping']||$config['account_revision']!==$v['account_revision'])throw new DomainException('pige_charge_configuration_changed');
        $invoice=$this->invoice($v['event'],$row['connection_key'],$secret);
        if($invoice['row']['origin_hash']!==$v['invoice_origin_hash']||$invoice['invoice_id']!==$v['invoice_id']||$this->payer($v['event'],$config,$invoice)!==$v['payer'])throw new DomainException('pige_charge_source_changed');
    }
    private function nativeOrigin(array $v):?array
    {
        $row=$this->rows('SELECT o.*,m.account_row,m.request_key,m.customer_id,m.billing_type FROM '.$this->prefix.'asaas_payment_origins o JOIN '.$this->prefix.'asaas_company_origin_meta m ON m.origin_id=o.id WHERE o.invoice_id=?',[$v['invoice_id']])[0]??null;
        if(!$row)return null;
        if((int)$row['issuer_id']!==(int)$v['event']['scope']['issuer_id']||(int)$row['account_row']!==$v['mapping']['account_row']||$row['account_id']!==$v['event']['scope']['account_id']||$row['environment']!==$v['event']['scope']['environment']||$row['request_key']!==$v['request_key']||$row['customer_id']!==$v['payer']['customer_id']||$row['billing_type']!==$v['event']['data']['billing_type'])throw new DomainException('pige_native_origin_conflict');
        $policy=$this->rows('SELECT issuer_id,origin_reference,mode FROM '.$this->prefix.'asaas_company_origin_policy WHERE origin_id=?',[$row['id']])[0]??null;
        if(!$policy||(int)$policy['issuer_id']!==(int)$row['issuer_id']||$policy['origin_reference']!==$v['request_key']||$policy['mode']!=='manual_only')throw new DomainException('pige_native_origin_policy_conflict');return $row;
    }
    private function reserve(string $connection,array $event,string $secret):array
    {
        return IssuerTransaction::run($this->db,$this->prefix,self::tables(),function()use($connection,$event,$secret):array{
            $config=$this->config()->assertInOwned($connection,$event['scope'],$secret);if($config['mapping']['provider']!=='asaas'||array_key_exists('bb_title',$event['data']))throw new DomainException('pige_provider_contract_invalid');$old=$this->row($connection,$event['data']['charge_id']);
            if($old){if(!hash_equals($old['payload_hash'],PigeBridgeChargeProtocol::hash($event)))throw new DomainException('pige_charge_idempotency_conflict');$this->guard($old,$secret);return $old;}
            if($this->rows('SELECT request_key FROM '.$this->prefix.'pige_bridge_charge_origins WHERE connection_key=? AND event_id=?',[$connection,$event['event_id']]))throw new DomainException('pige_charge_idempotency_conflict');
            $invoice=$this->invoice($event,$connection,$secret);$payer=$this->payer($event,$config,$invoice);$issuer=(int)$event['scope']['issuer_id'];$actor=$config['mapping']['actor_id'];
            $key=hash('sha256',self::json(['pige-external-v3',$connection,$invoice['row']['origin_hash'],$event['data']['charge_id']]));$id='req_'.bin2hex(random_bytes(16));
            $v=['connection_key'=>$connection,'request_key'=>$key,'request_id'=>$id,'event'=>$event,'external_revision'=>(int)$config['revision'],'mapping'=>$config['mapping'],'account_revision'=>$config['account_revision'],'secret_hash'=>$secret,'invoice_origin_hash'=>$invoice['row']['origin_hash'],'invoice_id'=>$invoice['invoice_id'],'invoice_revision'=>(new CompanyInvoices($this->db,$this->prefix))->revision($issuer,$actor,$invoice['invoice_id']),'payer'=>$payer];$json=self::json($v);
            $row=['request_key'=>$key,'request_id'=>$id,'connection_key'=>$connection,'event_id'=>$event['event_id'],'charge_id'=>$event['data']['charge_id'],'payload_hash'=>PigeBridgeChargeProtocol::hash($event),'origin_hash'=>hash('sha256',$json),'origin_json'=>$json,'created_at'=>gmdate('Y-m-d H:i:s')];
            IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'pige_bridge_charge_origins (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));$stored=$this->row($connection,$row['charge_id']);if($stored!==$row)throw new RuntimeException('pige_charge_origin_unconfirmed');return $stored;
        });
    }
    public function execute(string $action,string $connection,array $event,string $secret):array
    {
        PigeBridgeChargeProtocol::decode(self::json($event),$action);if($action==='status')return $this->status($connection,$event,$secret);
        $origin=$action==='issue'?$this->reserve($connection,$event,$secret):$this->row($connection,$event['data']['charge_id']);if(!$origin)throw new OutOfBoundsException('pige_charge_not_found');$v=self::snapshot($origin);
        if(PigeBridgeConnections::scope($v['event']['scope'])!==PigeBridgeConnections::scope($event['scope']))throw new DomainException('pige_charge_scope_conflict');
        $guard=fn()=>$this->guard($origin,$secret,$action==='reconcile');IssuerTransaction::run($this->db,$this->prefix,self::tables(),$guard);
        $native=$this->nativeOrigin($v);$service=new Asaas\CompanyService($this->db,$this->prefix);$issuer=(int)$event['scope']['issuer_id'];$actor=$v['mapping']['actor_id'];$decrypt=fn($cipher)=>$this->encryption->decrypt($cipher);$instrument=null;$payment=null;
        if($action==='issue'){
            // A reserved/uncertain native origin is NEVER re-POSTed; explicit sync must recover it.
            if($native===null)$service->issue($issuer,$actor,$v['invoice_id'],$v['mapping']['account_row'],$v['payer']['customer_id'],$v['event']['data']['billing_type'],$v['invoice_revision'],$v['request_key'],$decrypt,$this->transport,null,$guard,self::tables(),true,$v['request_key']);
        }else{
            if(!$native)throw new DomainException('pige_charge_not_dispatched');$id=(int)$native['id'];
            if($action==='sync')$service->observe($issuer,$actor,$id,$decrypt,$this->transport,$event['data']['recovery_id'],$guard,self::tables());
            elseif($action==='instruments')$instrument=$service->instruments($issuer,$actor,$id,$decrypt,$this->transport,$guard,self::tables())['instrument'];
            elseif($action==='reconcile')$payment=$service->reconcileExplicit($issuer,$actor,$id,$event['data']['proof_id'],$v['request_key'],$guard,self::tables());
        }
        $reply=$this->status($connection,$event,$secret);$reply['instrument']=$instrument;$reply['payment_effect_applied']=$payment!==null;return $reply;
    }
    /** Read-only journal/native state: no native service invocation, transaction, enqueue or HTTP. */
    public function status(string $connection,array $event,string $secret):array
    {
        $origin=$this->row($connection,$event['data']['charge_id']);if(!$origin)throw new OutOfBoundsException('pige_charge_not_found');$v=self::snapshot($origin);
        if(PigeBridgeConnections::scope($v['event']['scope'])!==PigeBridgeConnections::scope($event['scope']))throw new DomainException('pige_charge_scope_conflict');
        $invoice=$this->invoice($event,$connection,$secret);if($invoice['row']['origin_hash']!==$v['invoice_origin_hash']||$invoice['invoice_id']!==$v['invoice_id'])throw new DomainException('pige_charge_source_changed');
        $native=$this->nativeOrigin($v);$proof=null;$receipt=null;
        if($native){$proof=$this->rows('SELECT id,operation,state FROM '.$this->prefix.'asaas_company_proofs WHERE origin_id=? ORDER BY id DESC LIMIT 1',[$native['id']])[0]??null;$receipt=$this->rows('SELECT payment_id FROM '.$this->prefix.'asaas_company_receipts WHERE origin_id=? AND issuer_id=?',[$native['id'],(int)$event['scope']['issuer_id']])[0]??null;}
        return ['schema_version'=>3,'event_id'=>$event['event_id'],'charge_id'=>$event['data']['charge_id'],'request_id'=>$origin['request_id'],'scope'=>$event['scope'],'provider'=>'asaas','status'=>$native['state']??'reserved','origin_id'=>$native===null?null:(int)$native['id'],'remote_id'=>$native['remote_id']??null,'proof_id'=>$proof===null||$proof['operation']!=='query'||$proof['state']==='quarantined'?null:(int)$proof['id'],'reconciliation_status'=>$receipt===null?'not_reconciled':'reconciled','payment_id'=>$receipt===null?null:(int)$receipt['payment_id'],'instrument'=>null,'payment_effect_applied'=>false,'academic_effect_applied'=>false];
    }
}
