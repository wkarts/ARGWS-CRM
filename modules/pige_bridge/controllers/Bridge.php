<?php

defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__ . '/../libraries/PigeBridgeProtocol.php';
class Bridge extends App_Controller
{
    public function requests()
    {
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
        if ('/' . trim($this->uri->uri_string(), '/') !== PigeBridgeProtocol::REQUEST_PATH) {
            return $this->respond(404, ['error' => 'not_found']);
        }
        $body = $this->input->raw_input_stream;
        try {
            $event = PigeBridgeProtocol::decode($body);
        } catch (Throwable $e) {
            return $this->respond(strlen($body) > PigeBridgeProtocol::MAX_BODY_BYTES ? 413 : 400, ['error' => 'invalid_request']);
        }
        try {
            $connections = defined('APP_PIGE_BRIDGE_CONNECTIONS') && is_array(APP_PIGE_BRIDGE_CONNECTIONS) ? APP_PIGE_BRIDGE_CONNECTIONS : [];
            $connection = PigeBridgeProtocol::authenticate($event, $body, [
                'timestamp' => $this->input->get_request_header('X-Pige-Timestamp', false),
                'event_id' => $this->input->get_request_header('X-Pige-Event-Id', false),
                'signature' => $this->input->get_request_header('X-Pige-Signature', false),
            ], $connections, time());
        } catch (Throwable $e) {
            return $this->respond(401, ['error' => 'unauthorized']);
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

    private function respond(int $status, array $payload)
    {
        $this->output->set_header('Cache-Control: no-store');
        return $this->output->set_status_header($status)->set_content_type('application/json')->set_output(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
