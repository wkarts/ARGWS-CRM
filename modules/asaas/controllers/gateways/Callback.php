<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Callback extends APP_Controller
{
    public function __construct()
    {
        // Public callbacks must not render initialization diagnostics.
        ini_set('display_errors', '0');
        parent::__construct();
    }

    public function index()
    {
        $this->output->set_content_type('text/plain', 'utf-8')
            ->set_header('Cache-Control: no-store')
            ->set_header('X-Content-Type-Options: nosniff');

        if ($this->input->method(true) !== 'POST') {
            $this->output->set_status_header(405)
                ->set_header('Allow: POST')
                ->set_output('Método não permitido.');
            return;
        }

        try {
            $headers = array_change_key_case($this->input->request_headers(), CASE_LOWER);
            $rawBody = file_get_contents('php://input', false, null, 0, 1048577);
            if (!is_string($rawBody)) {
                $this->output->set_status_header(400)->set_output('Não foi possível ler o evento.');
                return;
            }

            // Resolve the module library only after the method/body guard.
            $this->load->library('asaas/asaas_gateway');
            $result = $this->asaas_gateway->handle_webhook($headers, $rawBody);
        } catch (Throwable $error) {
            log_message('error', '[Asaas] Falha temporária no recebimento do webhook.');
            $this->output->set_status_header(503)
                ->set_output('Falha temporária ao receber o evento.');
            return;
        }

        $processed = ($result['processed'] ?? false) === true;
        $status = (int) ($result['http_status'] ?? ($processed ? 200 : 503));
        if ($status < 200 || $status > 599 || (!$processed && $status < 400)) {
            $status = $processed ? 200 : 503;
        }

        $this->output->set_status_header($status)
            ->set_output($processed ? 'OK' : ($status < 500 ? 'Evento não aceito.' : 'Falha temporária.'));

        if (!$processed) {
            log_message('error', '[Asaas] Webhook não aceito. HTTP ' . $status);
        }
    }
}
