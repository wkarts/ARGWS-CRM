<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

class Asaas extends AdminController
{
    protected $apiKey;

    public function __construct()
    {
        parent::__construct();
        if (!is_admin()) {
            access_denied('Asaas');
            return;
        }
        $this->load->library('asaas/asaas_gateway');
    }

    public function index()
    {
        $this->render_webhooks();
    }

    private function render_webhooks(array $generated = [])
    {
        if (!is_admin()) {
            access_denied('Asaas');
            return;
        }
        require_once __DIR__ . '/../libraries/Asaas/WebhookAdminPresentation.php';
        $store = $this->asaas_gateway->getAdapter()->webhookIntegrations();
        $csrf = $this->session->userdata('asaas_webhook_admin_csrf');
        if (!is_string($csrf) || !preg_match('/^[a-f0-9]{64}$/D', $csrf)) {
            $csrf = bin2hex(random_bytes(32));
            $this->session->set_userdata('asaas_webhook_admin_csrf', $csrf);
        }
        $base = defined('APP_BASE_URL') ? (string) APP_BASE_URL : '';
        $indexPage = (string) $this->config->item('index_page');
        $data = [
            'title' => 'Webhooks Asaas',
            'csrf_token' => $csrf,
            'ready' => $store->ready(),
            'trusted_url_ready' => \Asaas\WebhookAdminPresentation::callbackUrl($base, str_repeat('0', 32), $indexPage) !== null,
            'integrations' => [],
            'selected_id' => null,
            'events' => [],
            'review_queue' => [],
            'pending_automations' => [],
            'token_revealed' => !empty($generated['revealed']),
            'generated_token' => $generated['one_time_token'] ?? null,
            'generated_id' => $generated['integration_id'] ?? $generated['id'] ?? null,
        ];
        if ($data['ready']) {
            $data['integrations'] = $store->listForAdmin();
            $data['review_queue'] = $store->reviewQueue(50);
            $data['pending_automations'] = $store->pendingAutomations(50);
            $requested = $this->input->get('integration');
            if ($requested !== null && (!is_string($requested) || !preg_match('/^[1-9][0-9]*$/D', $requested)
                || filter_var($requested, FILTER_VALIDATE_INT) === false)) {
                show_404();
                return;
            }
            foreach ($data['integrations'] as &$integration) {
                $integration['callback_url'] = \Asaas\WebhookAdminPresentation::callbackUrl($base, (string) $integration['public_id'], $indexPage);
                if (($requested === null && $data['selected_id'] === null) || (string) $integration['id'] === $requested) {
                    $data['selected_id'] = (int) $integration['id'];
                }
            }
            unset($integration);
            if ($requested !== null && $data['selected_id'] === null) {
                show_404();
                return;
            }
            if ($data['selected_id'] !== null) {
                $data['events'] = \Asaas\WebhookAdminPresentation::eventRows($store->recentEvents($data['selected_id'], 25));
            }
        }
        $this->output->set_header('Cache-Control: no-store, private, max-age=0');
        $this->output->set_header('Pragma: no-cache');
        $this->output->set_header('Referrer-Policy: no-referrer');
        $data['embedded_panel'] = $this->input->get('panel') === '1';
        $this->load->view('asaas/webhooks', $data);
    }

    public function register_integration()
    {
        if (!$this->require_webhook_management()) {
            return;
        }
        try {
            require_once __DIR__ . '/../libraries/Asaas/WebhookAdminPresentation.php';
            $base = defined('APP_BASE_URL') ? (string) APP_BASE_URL : '';
            if (\Asaas\WebhookAdminPresentation::callbackUrl($base, str_repeat('0', 32), (string) $this->config->item('index_page')) === null) {
                throw new InvalidArgumentException('invalid_configured_base_url');
            }
            $generated = $this->asaas_gateway->getAdapter()->registerWebhookIntegration(
                trim($this->webhook_posted_string('label', 100)),
                $this->webhook_posted_string('environment', 16),
                trim($this->webhook_posted_string('account_id', 128))
            );
            $this->session->set_userdata('asaas_webhook_admin_csrf', bin2hex(random_bytes(32)));
            $this->render_webhooks($generated);
            return;
        } catch (Throwable $error) {
            // Never render/log request bodies, tokens or provider exception details.
            set_alert('danger', 'Cadastro local não confirmado. Confira a conta, o ambiente, a configuração HTTPS. Nenhum webhook remoto foi alterado.');
        }
        redirect(admin_url('asaas' . ($this->input->get('panel') === '1' ? '?panel=1' : '')));
    }

    public function rotate_integration_token()
    {
        if (!$this->require_webhook_management()) {
            return;
        }
        try {
            $generated = $this->asaas_gateway->getAdapter()->webhookIntegrations()->rotateToken($this->webhook_posted_id());
            $this->session->set_userdata('asaas_webhook_admin_csrf', bin2hex(random_bytes(32)));
            $this->render_webhooks($generated);
            return;
        } catch (Throwable $error) {
            set_alert('danger', 'Rotação não confirmada. Confira o recebedor e o histórico antes de gerar outro token.');
        }
        redirect(admin_url('asaas' . ($this->input->get('panel') === '1' ? '?panel=1' : '')));
    }

    public function reveal_integration_token()
    {
        if (!$this->require_webhook_management()) return;
        try {
            $revealed = $this->asaas_gateway->getAdapter()->webhookIntegrations()->revealToken($this->webhook_posted_id());
            $this->session->set_userdata('asaas_webhook_admin_csrf', bin2hex(random_bytes(32)));
            $this->render_webhooks($revealed);
            return;
        } catch (Throwable $error) {
            set_alert('warning', 'Token indisponível para recuperação. Registros antigos guardam somente o hash; use a regeneração explícita para criar um novo token.');
        }
        redirect(admin_url('asaas' . ($this->input->get('panel') === '1' ? '?panel=1' : '')));
    }

    public function disable_integration()
    {
        if (!$this->require_webhook_management()) {
            return;
        }
        try {
            $this->asaas_gateway->getAdapter()->webhookIntegrations()->disable($this->webhook_posted_id());
            set_alert('success', 'Recebedor local desativado. Histórico preservado; o webhook remoto não foi modificado.');
        } catch (Throwable $error) {
            set_alert('danger', 'Não foi possível desativar o recebedor local.');
        }
        redirect(admin_url('asaas' . ($this->input->get('panel') === '1' ? '?panel=1' : '')));
    }

    public function retry_reviewed_event()
    {
        if (!$this->require_webhook_management()) {
            return;
        }
        try {
            $this->asaas_gateway->getAdapter()->retryReviewedWebhook(
                $this->webhook_posted_id('event_row_id'), $this->webhook_posted_id()
            );
            set_alert('success', 'Evento devolvido à fila após conferência da origem. O CRON refará a validação canônica; nenhuma baixa foi feita nesta ação.');
        } catch (Throwable $error) {
            set_alert('warning', 'Evento não liberado. Concilie a origem e confira o recebedor da mesma conta e ambiente.');
        }
        redirect(admin_url('asaas' . ($this->input->get('panel') === '1' ? '?panel=1' : '')));
    }

    private function require_webhook_management(): bool
    {
        if (!is_admin()) {
            access_denied('Asaas');
            return false;
        }
        if ($this->input->method(true) !== 'POST') {
            show_404();
            return false;
        }
        $stored = $this->session->userdata('asaas_webhook_admin_csrf');
        $submitted = $this->input->post('webhook_admin_csrf');
        if (!is_string($stored) || !preg_match('/^[a-f0-9]{64}$/D', $stored)
            || !is_string($submitted) || !hash_equals($stored, $submitted)) {
            show_error('Sessão inválida. Atualize a página e tente novamente.', 403);
            return false;
        }
        if ($this->input->post('webhook_token') !== null || $this->input->post('one_time_token') !== null || $this->input->post('token') !== null) {
            show_error('O token deve ser gerado pelo CRM; valores externos não são aceitos.', 400);
            return false;
        }
        if (!$this->asaas_gateway->getAdapter()->webhookIntegrations()->ready()) {
            show_error('Atualização do módulo pendente.', 503);
            return false;
        }
        return true;
    }

    private function webhook_posted_string(string $name, int $maxLength): string
    {
        $value = $this->input->post($name);
        if (!is_string($value) || $value === '' || strlen($value) > $maxLength) {
            throw new InvalidArgumentException('invalid_webhook_input');
        }
        return $value;
    }

    private function webhook_posted_id(string $name = 'integration_id'): int
    {
        $value = $this->webhook_posted_string($name, 20);
        if (!preg_match('/^[1-9][0-9]*$/D', $value) || filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException('invalid_integration_id');
        }
        return (int) $value;
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
        $response = $this->asaas_gateway->charges($this->asaas_gateway->getApiKey(), null);

        $response = json_decode($response, TRUE);

        natsort($response["data"]);

        $data = [
            "response" => $response ? $response["data"] : NULL,
        ];

        $this->load->view('asaas/charges', $data);
    }

    public function customers()
    {
        $response = $this->asaas_gateway->get_customers($this->asaas_gateway->getApiKey(), null);

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
        $response = $this->invoice->services($this->asaas_gateway->getApiKey(), null);
    }

    /** Legacy remote mutation routes are disabled; configure provider-side delivery in Asaas. */
    public function setup_webhook() { show_404(); }
    public function set_webhook($api_key = null, $api_url = null, $email = null) { show_404(); }
    public function set_webhook_invoice($api_key = null, $api_url = null, $email = null) { show_404(); }
    public function set_webhook_transfer($email = null) { show_404(); }

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
