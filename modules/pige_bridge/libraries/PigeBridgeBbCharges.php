<?php
declare(strict_types=1);
require_once __DIR__.'/PigeBridgeChargeProtocol.php';
require_once __DIR__.'/PigeBridgeInvoices.php';
require_once __DIR__.'/PigeBridgeExternalConfiguration.php';
require_once __DIR__.'/../../banco_brasil/libraries/BancoBrasil/ApiService.php';
/** BB adapter: boleto only, explicit title data, no payer creation or implicit bank settlement. */
final class PigeBridgeBbCharges
{
    public function __construct(private $db,private $encryption,private string $prefix,private $transport=null)
    {if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)||($transport!==null&&!is_callable($transport)))throw new InvalidArgumentException('invalid_adapter');}
    public static function tables():array{return array_values(array_unique(array_merge(PigeBridgeExternalConfiguration::tables(),PigeBridgeExternalConfiguration::providerTables('banco_brasil'),PigeBridgeInvoices::tables(),BancoBrasil\ApiService::receiptTables(),['pige_bridge_charge_origins','company_invoice_requests','bb_api_origin_policy','itemable','item_tax'])));}
    private static function json(array $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function config():PigeBridgeExternalConfiguration{return new PigeBridgeExternalConfiguration($this->db,$this->encryption,$this->prefix);}
    private function invoice(array $event,string $connection,string $secret):array{return (new PigeBridgeInvoices($this->db,$this->encryption,$this->prefix))->verifiedOrigin($connection,$event['scope'],$event['data']['charge_id'],$event['event_id'],$secret);}
    private function row(string $connection,string $charge):?array{return $this->rows('SELECT * FROM '.$this->prefix.'pige_bridge_charge_origins WHERE connection_key=? AND charge_id=?',[$connection,$charge])[0]??null;}
    private static function snapshot(array $row):array
    {
        if(!hash_equals($row['origin_hash'],hash('sha256',$row['origin_json'])))throw new DomainException('pige_charge_origin_changed');$v=json_decode($row['origin_json'],true,32,JSON_THROW_ON_ERROR);
        foreach(['connection_key','request_key','request_id'] as $key)if(($v[$key]??null)!==$row[$key])throw new DomainException('pige_charge_origin_changed');
        if(($v['mapping']['provider']??null)!=='banco_brasil'||!hash_equals($row['payload_hash'],PigeBridgeChargeProtocol::hash($v['event']))||$v['event']['event_id']!==$row['event_id']||$v['event']['data']['charge_id']!==$row['charge_id'])throw new DomainException('pige_charge_origin_changed');return $v;
    }
    private function payer(array $event,array $invoice):array
    {
        $client=$invoice['snapshot']['event']['data']['client_id'];$issuer=(int)$event['scope']['issuer_id'];
        if($event['data']['payer_id']!==$client||$event['data']['billing_type']!=='BOLETO'||!isset($event['data']['bb_title']))throw new DomainException('pige_bb_title_required');
        if(!$this->rows('SELECT c.userid FROM '.$this->prefix.'clients c JOIN '.$this->prefix.'issuers_clients o ON o.client_id=c.userid WHERE c.userid=? AND c.active=1 AND (o.owner_issuer_id=? OR EXISTS(SELECT 1 FROM '.$this->prefix.'issuers_client_links l WHERE l.client_id=c.userid AND l.issuer_id=?))',[$client,$issuer,$issuer]))throw new DomainException('pige_bb_payer_denied');
        return BancoBrasil\ApiPayload::input($event['data']['bb_title']);
    }
    private function guard(array $origin,string $secret,bool $reconcile=false):void
    {
        IssuerTransaction::assertOwned($this->db,$this->prefix,self::tables());$row=$this->row($origin['connection_key'],$origin['charge_id']);if(!$row||$row!==$origin)throw new DomainException('pige_charge_origin_changed');$v=self::snapshot($row);
        $config=$this->config()->assertInOwned($row['connection_key'],$v['event']['scope'],$secret,$v['external_revision'],$reconcile);
        if(!hash_equals($v['secret_hash'],$secret)||$config['mapping']!==$v['mapping']||$config['account_revision']!==$v['account_revision'])throw new DomainException('pige_charge_configuration_changed');
        $invoice=$this->invoice($v['event'],$row['connection_key'],$secret);
        if($invoice['row']['origin_hash']!==$v['invoice_origin_hash']||$invoice['invoice_id']!==$v['invoice_id']||$this->payer($v['event'],$invoice)!==$v['title'])throw new DomainException('pige_charge_source_changed');
    }
    private function nativeOrigin(array $v):?array
    {
        $row=$this->rows('SELECT o.*,n.profile_id,n.request_key,n.input_hash FROM '.$this->prefix.'bb_origins o JOIN '.$this->prefix.'bb_api_origins n ON n.origin_id=o.id WHERE n.request_key=?',[$v['request_key']])[0]??null;if(!$row)return null;
        $inputHash=hash('sha256',json_encode([$v['mapping']['profile_row'],$v['invoice_id'],$v['title']],JSON_THROW_ON_ERROR));
        if((int)$row['invoice_id']!==$v['invoice_id']||(int)$row['issuer_id']!==(int)$v['event']['scope']['issuer_id']||(int)$row['connection_id']!==$v['mapping']['account_row']||(int)$row['profile_id']!==$v['mapping']['profile_row']||$row['provider']!=='banco_brasil'||$row['channel']!=='api'||$row['account_id']!==$v['event']['scope']['account_id']||$row['environment']!==$v['event']['scope']['environment']||!hash_equals($row['input_hash'],$inputHash))throw new DomainException('pige_native_origin_conflict');
        $policy=$this->rows('SELECT issuer_id,origin_reference,mode FROM '.$this->prefix.'bb_api_origin_policy WHERE origin_id=?',[$row['id']])[0]??null;
        if(!$policy||(int)$policy['issuer_id']!==(int)$row['issuer_id']||$policy['origin_reference']!==$v['request_key']||$policy['mode']!=='bridge_manual')throw new DomainException('pige_native_origin_policy_conflict');return $row;
    }
    private function reserve(string $connection,array $event,string $secret):array
    {
        return IssuerTransaction::run($this->db,$this->prefix,self::tables(),function()use($connection,$event,$secret):array{
            $config=$this->config()->assertInOwned($connection,$event['scope'],$secret);if($config['mapping']['provider']!=='banco_brasil')throw new DomainException('pige_provider_contract_invalid');$old=$this->row($connection,$event['data']['charge_id']);
            if($old){if(!hash_equals($old['payload_hash'],PigeBridgeChargeProtocol::hash($event)))throw new DomainException('pige_charge_idempotency_conflict');$this->guard($old,$secret);return $old;}
            if($this->rows('SELECT request_key FROM '.$this->prefix.'pige_bridge_charge_origins WHERE connection_key=? AND event_id=?',[$connection,$event['event_id']]))throw new DomainException('pige_charge_idempotency_conflict');
            $invoice=$this->invoice($event,$connection,$secret);$title=$this->payer($event,$invoice);
            $key=hash('sha256',self::json(['pige-external-v3',$connection,$invoice['row']['origin_hash'],$event['data']['charge_id']]));$id='req_'.bin2hex(random_bytes(16));
            $v=['connection_key'=>$connection,'request_key'=>$key,'request_id'=>$id,'event'=>$event,'external_revision'=>(int)$config['revision'],'mapping'=>$config['mapping'],'account_revision'=>$config['account_revision'],'secret_hash'=>$secret,'invoice_origin_hash'=>$invoice['row']['origin_hash'],'invoice_id'=>$invoice['invoice_id'],'title'=>$title];$json=self::json($v);
            $row=['request_key'=>$key,'request_id'=>$id,'connection_key'=>$connection,'event_id'=>$event['event_id'],'charge_id'=>$event['data']['charge_id'],'payload_hash'=>PigeBridgeChargeProtocol::hash($event),'origin_hash'=>hash('sha256',$json),'origin_json'=>$json,'created_at'=>gmdate('Y-m-d H:i:s')];
            IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'pige_bridge_charge_origins (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));$stored=$this->row($connection,$row['charge_id']);if($stored!==$row)throw new RuntimeException('pige_charge_origin_unconfirmed');return $stored;
        });
    }
    public function execute(string $action,string $connection,array $event,string $secret):array
    {
        PigeBridgeChargeProtocol::decode(self::json($event),$action);if($action==='status')return $this->status($connection,$event,$secret);
        if($action==='sync'&&$event['data']['recovery_id']!=='')throw new DomainException('pige_bb_remote_id_immutable');
        $origin=$action==='issue'?$this->reserve($connection,$event,$secret):$this->row($connection,$event['data']['charge_id']);if(!$origin)throw new OutOfBoundsException('pige_charge_not_found');$v=self::snapshot($origin);
        if(PigeBridgeConnections::scope($v['event']['scope'])!==PigeBridgeConnections::scope($event['scope']))throw new DomainException('pige_charge_scope_conflict');
        $guard=fn()=>$this->guard($origin,$secret,$action==='reconcile');IssuerTransaction::run($this->db,$this->prefix,self::tables(),$guard);
        $native=$this->nativeOrigin($v);$issuer=(int)$event['scope']['issuer_id'];$actor=$v['mapping']['actor_id'];$decrypt=fn($cipher)=>$this->encryption->decrypt($cipher);$encrypt=fn($plain)=>$this->encryption->encrypt($plain);$service=new BancoBrasil\ApiService($this->db,$this->prefix,$encrypt,$decrypt,$this->transport);$instrument=null;$payment=null;
        if($action==='issue'){
            if($native===null)$service->issue($issuer,$actor,$v['mapping']['profile_row'],$v['invoice_id'],$v['title'],$v['request_key'],$guard,self::tables(),$v['request_key']);
        }else{
            if(!$native)throw new DomainException('pige_charge_not_dispatched');$id=(int)$native['id'];
            if($action==='sync')$service->query($issuer,$actor,$id,hash('sha256',self::json(['pige-bb-sync',$v['request_key'],$event['event_id']])),$guard,self::tables(),$v['request_key']);
            elseif($action==='instruments')$instrument=IssuerTransaction::run($this->db,$this->prefix,self::tables(),function()use($guard,$service,$issuer,$actor,$id,$native){$guard();$out=$service->checkoutInstrumentInOwnedTransaction($issuer,$actor,$id);$guard();return ['billing_type'=>'BOLETO','identification_field'=>$out['line'],'bar_code'=>$out['barcode'],'nosso_numero'=>$native['remote_id']];});
            elseif($action==='reconcile'){require_once __DIR__.'/../../banco_brasil/libraries/BancoBrasil/ApiCreditedReceipt.php';$payment=(new \app\services\InvoicePaymentWriter($this->db))->recordCompanyProviderReceipt($issuer,$actor,BancoBrasil\ApiCreditedReceipt::explicit($id,$event['data']['proof_id'],$v['request_key'],$guard,self::tables()));}
        }
        $reply=$this->status($connection,$event,$secret);$reply['instrument']=$instrument;$reply['payment_effect_applied']=$payment!==null;return $reply;
    }
    /** DB-only projection. Query/sync, instrument projection and reconciliation are distinct commands. */
    public function status(string $connection,array $event,string $secret):array
    {
        $origin=$this->row($connection,$event['data']['charge_id']);if(!$origin)throw new OutOfBoundsException('pige_charge_not_found');$v=self::snapshot($origin);
        if(PigeBridgeConnections::scope($v['event']['scope'])!==PigeBridgeConnections::scope($event['scope']))throw new DomainException('pige_charge_scope_conflict');
        $invoice=$this->invoice($event,$connection,$secret);if($invoice['row']['origin_hash']!==$v['invoice_origin_hash']||$invoice['invoice_id']!==$v['invoice_id'])throw new DomainException('pige_charge_source_changed');
        $native=$this->nativeOrigin($v);$proof=null;$receipt=null;
        if($native){$proof=$this->rows("SELECT id,status FROM ".$this->prefix."bb_api_operations WHERE origin_id=? AND operation='query' ORDER BY id DESC LIMIT 1",[$native['id']])[0]??null;$receipt=$this->rows('SELECT payment_id FROM '.$this->prefix.'bb_api_receipts WHERE origin_id=? AND issuer_id=?',[$native['id'],(int)$event['scope']['issuer_id']])[0]??null;}
        return ['schema_version'=>3,'event_id'=>$event['event_id'],'charge_id'=>$event['data']['charge_id'],'request_id'=>$origin['request_id'],'scope'=>$event['scope'],'provider'=>'banco_brasil','status'=>$native['state']??'reserved','origin_id'=>$native===null?null:(int)$native['id'],'remote_id'=>$native['remote_id']??null,'proof_id'=>$proof===null||$proof['status']!=='validated'?null:(int)$proof['id'],'reconciliation_status'=>$receipt===null?'not_reconciled':'reconciled','payment_id'=>$receipt===null?null:(int)$receipt['payment_id'],'instrument'=>null,'payment_effect_applied'=>false,'academic_effect_applied'=>false];
    }
}
