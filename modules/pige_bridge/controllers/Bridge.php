<?php

defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__ . '/../libraries/PigeBridgeProtocol.php';
require_once __DIR__.'/../libraries/PigeBridgeInvoiceProtocol.php';
require_once __DIR__.'/../libraries/PigeBridgeChargeProtocol.php';
class Bridge extends App_Controller
{
    public function requests() { return $this->receive(false); }
    public function diagnostics() { return $this->receive(true); }
    public function invoices() { return $this->receive(false, true); }
    public function invoice_status() { return $this->receive(false, true, true); }
    public function charges() { return $this->receive(false,false,false,'issue'); }
    public function charge_status() { return $this->receive(false,false,false,'status'); }
    public function charge_sync() { return $this->receive(false,false,false,'sync'); }
    public function charge_instruments() { return $this->receive(false,false,false,'instruments'); }
    public function charge_reconcile() { return $this->receive(false,false,false,'reconcile'); }
    private function receive(bool $diagnostic, bool $invoice=false, bool $status=false, ?string $chargeAction=null)
    {
        $path=$invoice?($status?PigeBridgeInvoiceProtocol::STATUS_PATH:PigeBridgeInvoiceProtocol::PATH):($diagnostic?PigeBridgeProtocol::DIAGNOSTIC_PATH:PigeBridgeProtocol::REQUEST_PATH);
        if($chargeAction!==null)$path=PigeBridgeChargeProtocol::path($chargeAction);
        if (!defined('APP_PIGE_BRIDGE_ENABLED') || APP_PIGE_BRIDGE_ENABLED !== true) {
            return $this->respond(503, ['error' => 'integration_disabled']);
        }
        if (!$this->app_modules->is_active('pige_bridge')) {
            return $this->respond(503, ['error' => 'integration_disabled']);
        }
        if ($this->input->method(true) !== 'POST') {
            $this->output->set_header('Allow: POST');
            return $this->respond(405, ['error' => 'method_not_allowed']);
        }
        // The signature fixes a canonical protocol path, including for alternate CI route aliases.
        if ('/' . trim($this->uri->uri_string(), '/') !== $path) {
            return $this->respond(404, ['error' => 'not_found']);
        }
        // Bound the read itself, not only JSON validation after allocating an arbitrary body.
        $stream = $this->openRequestStream();
        if (!is_resource($stream)) {
            return $this->respond(400, ['error' => 'invalid_request']);
        }
        try {
            $body = stream_get_contents($stream, PigeBridgeProtocol::MAX_BODY_BYTES + 1);
        } finally {
            fclose($stream);
        }
        if (!is_string($body)) {
            return $this->respond(400, ['error' => 'invalid_request']);
        }
        try {
            $event = $chargeAction!==null?PigeBridgeChargeProtocol::decode($body,$chargeAction):($invoice?($status?PigeBridgeInvoiceProtocol::decodeStatus($body):PigeBridgeInvoiceProtocol::decode($body)):($diagnostic?PigeBridgeProtocol::decodeDiagnostic($body):PigeBridgeProtocol::decode($body)));
        } catch (Throwable $e) {
            return $this->respond(strlen($body) > PigeBridgeProtocol::MAX_BODY_BYTES ? 413 : 400, ['error' => 'invalid_request']);
        }
        try {
            $connections = $this->configuredConnections();
            $connection = PigeBridgeProtocol::authenticate($event, $body, [
                'timestamp' => $this->input->get_request_header('X-Pige-Timestamp', false),
                'event_id' => $this->input->get_request_header('X-Pige-Event-Id', false),
                'signature' => $this->input->get_request_header('X-Pige-Signature', false),
            ], $connections, time(), $path);
        } catch (Throwable $e) {
            return $this->respond(401, ['error' => 'unauthorized']);
        }
        if($diagnostic)return $this->respond(200,['schema_version'=>1,'event_id'=>$event['event_id'],'nonce'=>$event['data']['nonce'],'scope'=>$event['scope'],'status'=>'authenticated','capabilities'=>$this->diagnosticCapabilities($connection,$event['scope']),'payment_effect_applied'=>false,'academic_effect_applied'=>false]);
        if($chargeAction!==null){
            if($chargeAction!=='status'&&(!defined('APP_PIGE_BRIDGE_EXTERNAL_ENABLED')||APP_PIGE_BRIDGE_EXTERNAL_ENABLED!==true))return $this->respond(503,['error'=>'external_integration_disabled']);
            if($chargeAction==='reconcile'&&(!defined('APP_PIGE_BRIDGE_RECONCILE_ENABLED')||APP_PIGE_BRIDGE_RECONCILE_ENABLED!==true))return $this->respond(503,['error'=>'reconciliation_disabled']);
            try{return $this->respond(200,$this->chargeService()->execute($chargeAction,$connection,$event,hash('sha256',$connections[$connection]['secret'])));}
            catch(OutOfBoundsException $e){return $this->respond(404,['error'=>'charge_not_found']);}
            catch(DomainException $e){return $this->respond(409,['error'=>'charge_scope_or_state_conflict']);}
            catch(Throwable $e){return $this->respond(503,['error'=>'charge_service_unavailable']);}
        }
        if($invoice){
            if(!$status&&(!defined('APP_PIGE_BRIDGE_INVOICES_ENABLED')||APP_PIGE_BRIDGE_INVOICES_ENABLED!==true))return $this->respond(503,['error'=>'invoice_integration_disabled']);
            try {
                $service=$this->invoiceService();$secretHash=hash('sha256',$connections[$connection]['secret']);
                $receipt=$status?$service->status($connection,$event,$secretHash):$service->create($connection,$event,$secretHash);
                return $this->respond(200,$receipt);
            } catch(OutOfBoundsException $e){return $this->respond(404,['error'=>'invoice_not_found']);}
            catch(DomainException $e){return $this->respond(409,['error'=>'invoice_scope_or_state_conflict']);}
            catch(Throwable $e){return $this->respond(503,['error'=>'invoice_service_unavailable']);}
        }
        try {
            $this->load->model('pige_bridge/pige_bridge_model');
            $receipt = $this->pige_bridge_model->accept_request($connection, $event, $body);
        } catch (DomainException $e) {
            return $this->respond(409, ['error' => 'idempotency_conflict']);
        } catch (Throwable $e) {
            // Deliberately exclude raw payloads, credentials and exception text from logs/responses.
            log_activity('PIGE bridge: receipt storage unavailable');
            return $this->respond(503, ['error' => 'receipt_storage_unavailable']);
        }
        return $this->respond(202, PigeBridgeProtocol::receipt($receipt));
    }

    /** Advisory readiness only; the writer rechecks all ACL and mapping state under its lock. */
    protected function diagnosticCapabilities(string $connection,array $scope):array
    {
        $caps=['create_invoice'=>false,'issue_charge'=>false];
        if(!defined('APP_PIGE_BRIDGE_INVOICES_ENABLED')||APP_PIGE_BRIDGE_INVOICES_ENABLED!==true)return $caps;
        try {
            require_once __DIR__.'/../libraries/PigeBridgeConnections.php';$this->load->library('encryption');$p=db_prefix();
            $mapped=(new PigeBridgeConnections($this->db,$this->encryption,$p))->mapping($connection);
            if(!$mapped||$mapped['scope']!==PigeBridgeConnections::scope($scope)||!($mapped['mapping']['enabled']??false))return $caps;
            $actor=$mapped['mapping']['actor_id'];$issuer=(int)$scope['issuer_id'];$acl=new IssuerResourceAccess($this->db,$p);
            $currency=IssuerStorage::query($this->db,'SELECT name FROM '.$p.'currencies WHERE id=?',[$mapped['mapping']['currency_id']])->row();
            $caps['create_invoice']=($currency->name??null)==='BRL'&&$acl->can($issuer,$actor,'invoices','view')&&$acl->can($issuer,$actor,'invoices','create')&&$acl->can($issuer,$actor,'customers','view');
            if(defined('APP_PIGE_BRIDGE_EXTERNAL_ENABLED')&&APP_PIGE_BRIDGE_EXTERNAL_ENABLED===true){require_once __DIR__.'/../libraries/PigeBridgeExternalConfiguration.php';$caps['issue_charge']=(new PigeBridgeExternalConfiguration($this->db,$this->encryption,$p))->ready($connection,$scope);}
        } catch(Throwable $e) { /* Never disclose configuration, SQL or cipher details. */ }
        return $caps;
    }

    protected function chargeService()
    {
        require_once __DIR__.'/../libraries/PigeBridgeChargeRouter.php';$this->load->library('encryption');
        return new PigeBridgeChargeRouter($this->db,$this->encryption,db_prefix());
    }

    protected function invoiceService()
    {
        require_once __DIR__.'/../libraries/PigeBridgeInvoices.php';$this->load->library('encryption');
        return new PigeBridgeInvoices($this->db,$this->encryption,db_prefix());
    }

    protected function configuredConnections(): array
    {
        require_once __DIR__.'/../libraries/PigeBridgeConnections.php';$this->load->library('encryption');
        $deployment=defined('APP_PIGE_BRIDGE_CONNECTIONS')&&is_array(APP_PIGE_BRIDGE_CONNECTIONS)?APP_PIGE_BRIDGE_CONNECTIONS:[];
        return (new PigeBridgeConnections($this->db,$this->encryption,db_prefix()))->active($deployment);
    }

    protected function openRequestStream()
    {
        return fopen('php://input', 'rb');
    }

    private function respond(int $status, array $payload)
    {
        $this->output->set_header('Cache-Control: no-store');
        return $this->output->set_status_header($status)->set_content_type('application/json')->set_output(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
