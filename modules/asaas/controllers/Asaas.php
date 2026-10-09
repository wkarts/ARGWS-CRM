<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

class Asaas extends AdminController
{
    protected $apiKey;

    public function __construct()
    {
        parent::__construct();
        $this->load->library('asaas/asaas_gateway');
        $this->apiKey  = $this->asaas_gateway->getApiKey();
    }

    public function index()
    {
        if (!is_admin()) {
            access_denied('Asaas');
        }

        $data = [
            'title' => 'Webhooks Asaas',
            'webhook_url' => site_url('asaas/gateways/callback'),
            'email' => (string) get_option('smtp_email'),
            'has_webhook_token' => strlen((string) $this->asaas_gateway->getSetting('webhook_secret')) >= 32,
        ];
        $this->load->view('asaas/webhooks', $data);
    }

    public function get_invoice_data($invoice_hash)
    {
        $this->db->where('hash', $invoice_hash);
        $invoice = $this->db->get(db_prefix() . 'invoices')->row();

        if ($invoice->status == 2) {
            echo 1;
        } else {
            echo 0;
        }
    }

    public function charges()
    {
        $response = $this->asaas_gateway->charges($this->apiKey, null);

        $response = json_decode($response, TRUE);

        natsort($response["data"]);

        $data = [
            "response" => $response ? $response["data"] : NULL,
        ];

        $this->load->view('asaas/charges', $data);
    }

    public function customers()
    {
        $response = $this->asaas_gateway->get_customers($this->apiKey, null);

        natsort($response["data"]);

        $data = [
            "response" => $response ? $response["data"] : NULL,
        ];

        $this->load->view('asaas/customers', $data);
    }

    public function health()
    {
        $status = $this->asaas_gateway->health_check();
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($status));
    }

    public function merge()
    {
        // Rota antiga de diagnóstico sem contrato funcional: não deve exibir
        // CPF/CNPJ, dados bancários ou pagamentos em saída de depuração.
        show_404();
    }

    public function services()
    {
        $response = $this->invoice->services($this->apiKey, null);
    }

    public function setup_webhook()
    {
        if (!is_admin()) {
            access_denied('Asaas');
        }
        if ($this->input->method(true) !== 'POST') {
            show_404();
        }

        $token = trim((string) $this->asaas_gateway->getSetting('webhook_secret'));
        $email = trim((string) $this->input->post('notification_email'));
        $url = site_url('asaas/gateways/callback');

        if (strlen($token) < 32 || strlen($token) > 255 || preg_match('/\\s/', $token)) {
            set_alert('danger', 'Configure primeiro um token exclusivo para o webhook, com 32 a 255 caracteres, sem espaços.');
            redirect(admin_url('asaas'));
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !filter_var($url, FILTER_VALIDATE_URL)
            || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            set_alert('danger', 'Informe um e-mail válido e disponibilize o webhook por HTTPS.');
            redirect(admin_url('asaas'));
            return;
        }

        $adapter = $this->asaas_gateway->getAdapter();
        $list = $adapter->requestStructured('GET', '/webhooks', ['limit' => 100]);
        if (empty($list['success'])) {
            set_alert('danger', 'Não foi possível consultar os webhooks da conta Asaas.');
            redirect(admin_url('asaas'));
            return;
        }
        $webhooks = $list['data']['data'] ?? [];
        if (!is_array($webhooks)) {
            set_alert('danger', 'A listagem de webhooks retornou dados incompatíveis.');
            redirect(admin_url('asaas'));
            return;
        }
        foreach ($webhooks as $webhook) {
            if (is_array($webhook) && (string) ($webhook['url'] ?? '') === $url) {
                set_alert('warning', 'Este endereço já consta na conta Asaas. Confirme o token configurado antes de ativar o envio.');
                redirect(admin_url('asaas'));
                return;
            }
        }
        if (!empty($list['data']['hasMore'])) {
            set_alert('warning', 'A conta possui mais webhooks. Consulte as demais páginas antes de criar outro.');
            redirect(admin_url('asaas'));
            return;
        }

        $result = $adapter->requestStructured('POST', '/webhooks', [], [], [
            'name' => 'CRM — eventos de pagamento',
            'url' => $url,
            'email' => $email,
            'enabled' => true,
            'interrupted' => false,
            'apiVersion' => 3,
            'authToken' => $token,
            'sendType' => 'SEQUENTIALLY',
            'events' => [
                'PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED', 'PAYMENT_OVERDUE',
                'PAYMENT_REFUNDED', 'PAYMENT_PARTIALLY_REFUNDED',
                'PAYMENT_CHARGEBACK_REQUESTED',
            ],
        ]);

        if (!empty($result['success']) && !empty($result['data']['id'])) {
            set_alert('success', 'Webhook cadastrado. Consulte a fila de eventos no painel Asaas.');
        } else {
            set_alert('danger', 'Falha ao cadastrar o webhook. Confira as permissões da chave da API.');
        }
        redirect(admin_url('asaas'));
    }

    public function set_webhook($api_key, $api_url, $email)
    {
        $webhook = $this->asaas_gateway->get_webhook($api_key, $api_url);
        if ($webhook["url"] !== site_url('asaas/gateways/callback/index')) {
            $post_data = json_encode([
                "url" => site_url('asaas/gateways/callback/index'),
                "email" => $email,
                "interrupted" => false,
                "enabled" => true,
                "apiVersion" => 3
            ]);
            $create_webhook = $this->asaas_gateway->create_webhook($api_key, $api_url, $post_data);
            return $create_webhook;
        }
    }

    public function set_webhook_invoice($api_key, $api_url, $email)
    {
        $webhook = $this->asaas_gateway->get_webhook_invoice($api_key, $api_url);

        if ($webhook["url"] !== site_url('asaas_invoice/gateways/callback/index')) {
            $post_data = json_encode([
                "url" => site_url('asaas_invoice/gateways/callback'),
                "email" => $email,
                "interrupted" => false,
                "enabled" => true,
                "apiVersion" => 3

            ]);

            $create_webhook = $this->asaas_gateway->create_webhook_invoice($api_key, $api_url, $post_data);

            return $create_webhook;
        }
    }

    public function set_webhook_transfer($email)
    {
        $webhook = $this->asaas_gateway->get_webhook_transfer($this->apiKey, null);
        if ($webhook["url"] !== site_url('asaas/gateways/callback/invoices')) {
            $post_data = json_encode([
                "url" => site_url('asaas/gateways/callback'),
                "email" => $email,
                "interrupted" => false,
                "enabled" => true,
                "apiVersion" => 3

            ]);
            $create_webhook = $this->asaas_gateway->create_webhook_transfer($this->apiKey, null, $post_data);
            return $create_webhook;
        }
    }

    public function retorna_cobranca($id = 178458832)
    {
        $response = $this->asaas_gateway->request('GET', '/payments/' . $id);

        echo json_encode(is_array($response) ? $response : []);
    }

    public function retorna_cobrancas($hash = '')
    {
        $response = $this->asaas_gateway->request('GET', '/payments', ['externalReference' => $hash]);

        echo json_encode(is_array($response) ? $response : []);
    }
}
