<?php
declare(strict_types=1);
require_once __DIR__.'/PigeBridgeInvoiceProtocol.php';
require_once __DIR__.'/PigeBridgeConnections.php';
require_once __DIR__.'/../../../application/services/CompanyInvoices.php';
/** Immutable local invoice origin. No bank service, legacy hooks, customer creation or academic writer. */
final class PigeBridgeInvoices
{
    public function __construct(private $db,private $encryption,private string $prefix)
    {if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('invalid_prefix');}
    public static function tables():array{return array_merge(PigeBridgeConnections::tables(),['pige_bridge_invoice_origins','clients','issuers_clients','issuers_client_links']);}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private static function json(array $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
    private function connections():PigeBridgeConnections{return new PigeBridgeConnections($this->db,$this->encryption,$this->prefix);}
    private function origin(string $connection,string $charge,bool $lock=false):?array
    {return $this->rows('SELECT * FROM '.$this->prefix.'pige_bridge_invoice_origins WHERE connection_key=? AND charge_id=?'.($lock?' FOR UPDATE':''),[$connection,$charge])[0]??null;}
    private static function snapshot(array $row):array
    {
        if(!hash_equals($row['origin_hash'],hash('sha256',$row['origin_json'])))throw new DomainException('pige_origin_changed');
        $v=json_decode($row['origin_json'],true,16,JSON_THROW_ON_ERROR);
        if(!is_array($v)||!isset($v['event'],$v['mapping'],$v['revision'],$v['issue_date'],$v['secret_hash']))throw new DomainException('pige_origin_changed');
        foreach(['connection_key','request_key','request_id'] as $field)if(($v[$field]??null)!==$row[$field])throw new DomainException('pige_origin_changed');
        if(PigeBridgeInvoiceProtocol::semanticHash($v['event'])!==$row['payload_hash']||$v['event']['data']['charge_id']!==$row['charge_id']||$v['event']['event_id']!==$row['event_id'])throw new DomainException('pige_origin_changed');
        return $v;
    }
    private static function input(array $v):array
    {
        $data=$v['event']['data'];return ['client_id'=>$data['client_id'],'date'=>$v['issue_date'],'due_date'=>$data['due_date'],'currency'=>$v['mapping']['currency_id'],'items'=>[['description'=>'Cobrança escolar','qty'=>'1.00','rate'=>CompanyInvoices::decimal($data['amount_cents'])]],'note'=>''];
    }
    private static function inputHash(array $input):string
    {return hash('sha256',json_encode(array_merge(CompanyInvoiceLines::calculate($input['items'],[]),['client_id'=>$input['client_id'],'date'=>$input['date'],'due_date'=>$input['due_date'],'currency'=>$input['currency'],'note'=>$input['note']]),JSON_THROW_ON_ERROR));}
    private function assertOrigin(array $origin,array $event,string $secretHash):void
    {
        IssuerTransaction::assertOwned($this->db,$this->prefix,self::tables());
        $row=$this->origin($origin['connection_key'],$origin['charge_id'],true);
        if(!$row||$row!==$origin)throw new DomainException('pige_origin_changed');
        $v=self::snapshot($row);$mapping=$this->connections()->assertMappingInOwned($row['connection_key'],$event['scope'],$v['revision']);
        if($mapping['mapping']!==$v['mapping']||!hash_equals($mapping['row']['secret_hash'],$secretHash)||!hash_equals($v['secret_hash'],$secretHash))throw new DomainException('pige_configuration_changed');
    }
    public function create(string $connection,array $event,string $secretHash):array
    {
        $hash=PigeBridgeInvoiceProtocol::semanticHash($event);
        $origin=IssuerTransaction::run($this->db,$this->prefix,self::tables(),function()use($connection,$event,$secretHash,$hash):array{
            $mapping=$this->connections()->assertMappingInOwned($connection,$event['scope']);
            if(!hash_equals($mapping['row']['secret_hash'],$secretHash))throw new DomainException('pige_configuration_changed');
            $existing=$this->origin($connection,$event['data']['charge_id'],true);
            if($existing){if(!hash_equals($existing['payload_hash'],$hash))throw new DomainException('pige_invoice_idempotency_conflict');$this->assertOrigin($existing,$event,$secretHash);return $existing;}
            if($this->rows('SELECT request_key FROM '.$this->prefix.'pige_bridge_invoice_origins WHERE connection_key=? AND event_id=? FOR UPDATE',[$connection,$event['event_id']]))throw new DomainException('pige_invoice_idempotency_conflict');
            // Reject unrelated/inactive clients before reserving this immutable charge key.
            $issuer=(int)$event['scope']['issuer_id'];$client=$event['data']['client_id'];
            if(!$this->rows('SELECT c.userid FROM '.$this->prefix.'clients c JOIN '.$this->prefix.'issuers_clients o ON o.client_id=c.userid WHERE c.userid=? AND c.active=1 AND (o.owner_issuer_id=? OR EXISTS(SELECT 1 FROM '.$this->prefix.'issuers_client_links l WHERE l.client_id=c.userid AND l.issuer_id=?)) FOR UPDATE',[$client,$issuer,$issuer]))throw new DomainException('pige_invoice_recipient_denied');
            $date=(new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
            if($event['data']['due_date']<$date)throw new DomainException('pige_invoice_due_date_invalid');
            $requestKey=hash('sha256',self::json(['pige-invoice-v2',$connection,$mapping['scope_hash'],$event['data']['charge_id']]));$requestId='req_'.bin2hex(random_bytes(16));
            $snapshot=['connection_key'=>$connection,'request_key'=>$requestKey,'request_id'=>$requestId,'event'=>$event,'mapping'=>$mapping['mapping'],'revision'=>$mapping['revision'],'issue_date'=>$date,'secret_hash'=>$secretHash];$json=self::json($snapshot);
            $row=['request_key'=>$requestKey,'request_id'=>$requestId,'connection_key'=>$connection,'event_id'=>$event['event_id'],'charge_id'=>$event['data']['charge_id'],'payload_hash'=>$hash,'origin_hash'=>hash('sha256',$json),'origin_json'=>$json,'created_at'=>gmdate('Y-m-d H:i:s')];
            IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'pige_bridge_invoice_origins (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));
            $stored=$this->origin($connection,$event['data']['charge_id'],true);if(!$stored||$stored!==$row)throw new RuntimeException('pige_origin_write_unconfirmed');return $stored;
        });
        $v=self::snapshot($origin);$input=self::input($v);
        (new CompanyInvoices($this->db,$this->prefix))->create((int)$event['scope']['issuer_id'],$v['mapping']['actor_id'],$input,$origin['request_key'],fn()=>$this->assertOrigin($origin,$event,$secretHash),self::tables());
        return $this->status($connection,['schema_version'=>2,'event_id'=>$event['event_id'],'event_type'=>'invoice.status.requested','scope'=>$event['scope'],'data'=>['charge_id'=>$event['data']['charge_id']]],$secretHash);
    }
    /** Internal adapter data only; never returned by the public status endpoint. */
    public function verifiedOrigin(string $connection,array $scope,string $charge,string $eventId,string $secretHash):array
    {
        $receipt=$this->status($connection,['schema_version'=>2,'event_id'=>$eventId,'event_type'=>'invoice.status.requested','scope'=>$scope,'data'=>['charge_id'=>$charge]],$secretHash);
        if($receipt['invoice_id']===null)throw new DomainException('pige_invoice_not_ready');
        $row=$this->origin($connection,$charge);if(!$row)throw new DomainException('pige_origin_changed');return ['row'=>$row,'snapshot'=>self::snapshot($row),'invoice_id'=>$receipt['invoice_id']];
    }
    /** DB-only: no transaction helper (which writes locks), no provider, enqueue, audit or UPDATE. */
    public function status(string $connection,array $event,string $secretHash):array
    {
        PigeBridgeInvoiceProtocol::decodeStatus(self::json($event));
        $origin=$this->origin($connection,$event['data']['charge_id']);if(!$origin)throw new OutOfBoundsException('pige_invoice_not_found');$v=self::snapshot($origin);
        if(PigeBridgeConnections::scope($v['event']['scope'])!==PigeBridgeConnections::scope($event['scope']))throw new DomainException('pige_origin_changed');
        $current=$this->rows('SELECT * FROM '.$this->prefix.'pige_bridge_connections WHERE connection_key=?',[$connection])[0]??null;
        if(!$current||(int)$current['enabled']!==1||!hash_equals($current['secret_hash'],$secretHash)||!hash_equals($current['scope_hash'],hash('sha256',self::json(PigeBridgeConnections::scope($event['scope'])))))throw new DomainException('pige_configuration_changed');
        $issuer=(int)$event['scope']['issuer_id'];$actor=$v['mapping']['actor_id'];$acl=new IssuerResourceAccess($this->db,$this->prefix);
        if(!$acl->can($issuer,$actor,'invoices','view')||!$acl->can($issuer,$actor,'customers','view'))throw new DomainException('pige_invoice_status_denied');
        $receipt=$this->rows('SELECT * FROM '.$this->prefix.'company_invoice_requests WHERE request_key=?',[$origin['request_key']])[0]??null;$id=null;
        if($receipt){
            $input=self::input($v);
            if((int)$receipt['issuer_id']!==$issuer||(int)$receipt['actor_id']!==$actor||!hash_equals($receipt['input_hash'],self::inputHash($input)))throw new DomainException('pige_invoice_changed');
            $invoice=$this->rows('SELECT id,issuer_id,clientid,currency,date,duedate,total FROM '.$this->prefix.'invoices WHERE id=?',[$receipt['invoice_id']])[0]??null;
            if(!$invoice||(int)$invoice['issuer_id']!==$issuer||(int)$invoice['clientid']!==$input['client_id']||(int)$invoice['currency']!==$input['currency']||$invoice['date']!==$input['date']||$invoice['duedate']!==$input['due_date']||\app\services\InvoicePaymentWriter::minorUnits((string)$invoice['total'])!==$v['event']['data']['amount_cents'])throw new DomainException('pige_invoice_changed');$id=(int)$invoice['id'];
        }
        return ['schema_version'=>2,'event_id'=>$event['event_id'],'charge_id'=>$event['data']['charge_id'],'request_id'=>$origin['request_id'],'scope'=>$event['scope'],'status'=>$id===null?'pending':'invoice_created','invoice_id'=>$id,'capabilities'=>['issue_charge'=>false],'payment_effect_applied'=>false,'academic_effect_applied'=>false];
    }
}
