<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Connect_api_connector extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library(CONNECT_API_CONNECTOR_MODULE . '/ConnectApiConnectorSecureStore');
        $this->load->library(CONNECT_API_CONNECTOR_MODULE . '/ConnectApiConnectorConfig');
        $this->load->library(CONNECT_API_CONNECTOR_MODULE . '/ConnectApiConnectorClient');
    }

    public function index()
    {
        $this->requirePermission('view');
        $config = $this->connectapiconnectorconfig->all();
        $state = null;
        $apiError = null;
        $instanceNumber = preg_replace('/\D+/', '', (string) ($config['instance_number'] ?? ''));
        if ($this->isConfigured($config)) {
            try {
                $client = $this->client($config);
                $state = $client->state();
                try {
                    $info = $client->info();
                    $detectedNumber = $this->extractPhoneNumberFromResponse($info);
                    if ($detectedNumber !== '') {
                        $instanceNumber = $detectedNumber;
                        update_option('connect_api_connector_instance_number', $instanceNumber);
                    }
                } catch (Throwable $ignored) {
                    // O estado continua disponível mesmo se os detalhes ainda não tiverem sido persistidos pela API.
                }
            } catch (Throwable $e) {
                $apiError = $e->getMessage();
            }
        }

        $managerAvailable = connect_api_connector_manager_available();
        $managed = $managerAvailable ? hooks()->apply_filters('connect_api_manager_connector_instances', []) : [];
        if (!is_array($managed)) $managed = [];

        $data = [
            'title' => _l('connect_api_connector_title'),
            'config' => $config,
            'state' => $state,
            'api_error' => $apiError,
            'instance_number' => $instanceNumber,
            'managed_module_available' => $managerAvailable,
            'managed_instances' => $managed,
        ];
        $this->load->view('dashboard', $data);
    }

    public function save()
    {
        $this->requirePermission('configure');
        $this->requirePost();
        $url = rtrim(trim((string) $this->input->post('api_url')), '/');
        $instance = trim((string) $this->input->post('instance_name'));
        $token = trim((string) $this->input->post('instance_token'));

        $previousInstance = (string) get_option('connect_api_connector_instance_name');
        update_option('connect_api_connector_api_url', $url);
        update_option('connect_api_connector_instance_name', $instance);
        if ($previousInstance !== $instance) {
            update_option('connect_api_connector_instance_number', '');
        }
        if ($token !== '') {
            update_option('connect_api_connector_instance_token', $this->connectapiconnectorsecurestore->encrypt($token));
        }
        update_option('connect_api_connector_verify_tls', $this->input->post('verify_tls') ? '1' : '0');
        update_option('connect_api_connector_timeout', (string) max(5, min(120, (int) $this->input->post('timeout'))));
        update_option('connect_api_connector_default_country', preg_replace('/\D+/', '', (string) $this->input->post('default_country')));
        update_option('connect_api_connector_default_area', preg_replace('/\D+/', '', (string) $this->input->post('default_area')));
        update_option('connect_api_connector_auto_normalize', $this->input->post('auto_normalize') ? '1' : '0');
        update_option('connect_api_connector_sms_takeover', $this->input->post('sms_takeover') ? '1' : '0');
        if ($this->input->post('sms_takeover')) {
            connect_api_connector_activate_sms_gateway();
        }
        set_alert('success', _l('settings_updated'));
        redirect(admin_url('connect_api_connector'));
    }

    public function bind_managed()
    {
        $this->requirePermission('configure');
        $this->requirePost();
        $selected = (string) $this->input->post('managed_instance');
        if (!connect_api_connector_manager_available()) {
            set_alert('danger', _l('connect_api_connector_managed_not_available'));
            redirect(admin_url('connect_api_connector'));
        }

        $managed = hooks()->apply_filters('connect_api_manager_connector_instances', []);
        foreach (is_array($managed) ? $managed : [] as $item) {
            if (($item['instance_name'] ?? '') === $selected && !empty($item['instance_token'])) {
                if (!empty($item['api_url'])) {
                    update_option('connect_api_connector_api_url', rtrim((string) $item['api_url'], '/'));
                }
                update_option('connect_api_connector_instance_name', $item['instance_name']);
                update_option('connect_api_connector_instance_token', $this->connectapiconnectorsecurestore->encrypt($item['instance_token']));
                update_option('connect_api_connector_instance_number', preg_replace('/\D+/', '', (string) ($item['phone_number'] ?? '')));
                set_alert('success', _l('connect_api_connector_managed_bound'));
                redirect(admin_url('connect_api_connector'));
            }
        }
        set_alert('danger', _l('connect_api_connector_managed_not_found'));
        redirect(admin_url('connect_api_connector'));
    }

    public function test_connection()
    {
        $this->requirePermission('configure');
        try {
            $response = $this->client()->state();
            $state = connect_api_connector_translate_state($response['instance']['state'] ?? 'unknown');
            set_alert('success', _l('connect_api_connector_connection_ok') . ': ' . $state);
        } catch (Throwable $e) {
            set_alert('danger', $e->getMessage());
        }
        redirect(admin_url('connect_api_connector'));
    }

    public function connect()
    {
        $this->requirePermission('reconnect');
        $this->requirePost();
        $method = (string) $this->input->post('method');
        if (!in_array($method, ['qrcode', 'pairing'], true)) {
            $method = 'qrcode';
        }
        $number = $this->connectapiconnectorconfig->normalizeNumber($this->input->post('number'));
        if ($method === 'pairing' && strlen($number) < 10) {
            set_alert('danger', _l('connect_api_connector_pairing_number_required'));
            redirect(admin_url('connect_api_connector'));
        }
        try {
            $response = $method === 'pairing' ? $this->client()->connectPairing($number) : $this->client()->connectQr();
            $data = [
                'title' => _l('connect_api_connector_connection'),
                'response' => $response,
                'instance_name' => get_option('connect_api_connector_instance_name'),
                'status_url' => admin_url('connect_api_connector/connection_status'),
                'return_url' => admin_url('connect_api_connector'),
            ];
            $this->load->view('connection', $data);
        } catch (Throwable $e) {
            set_alert('danger', $e->getMessage());
            redirect(admin_url('connect_api_connector'));
        }
    }

    public function connection_status()
    {
        $this->requirePermission('view');
        $config = $this->connectapiconnectorconfig->all();

        $this->output
            ->set_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0')
            ->set_header('Pragma: no-cache')
            ->set_content_type('application/json', 'utf-8');

        if (!$this->isConfigured($config)) {
            $this->output->set_status_header(400)->set_output(json_encode([
                'success' => false,
                'connected' => false,
                'message' => _l('connect_api_connector_not_configured'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return;
        }

        try {
            $client = $this->client($config);
            $stateResponse = $client->state();
            $state = (string) ($stateResponse['instance']['state'] ?? 'unknown');
            $number = preg_replace('/\D+/', '', (string) get_option('connect_api_connector_instance_number'));

            try {
                $infoResponse = $client->info();
                $detectedNumber = $this->extractPhoneNumberFromResponse($infoResponse);
                if ($detectedNumber !== '') {
                    $number = $detectedNumber;
                    update_option('connect_api_connector_instance_number', $number);
                }
                $rows = $this->normalizeInstances($infoResponse);
                if (($state === '' || $state === 'unknown') && !empty($rows[0]['connectionStatus'])) {
                    $state = (string) $rows[0]['connectionStatus'];
                }
            } catch (Throwable $ignored) {
                // O monitor de estado continua funcionando mesmo sem detalhes adicionais.
            }

            $connected = in_array(strtolower($state), ['open', 'connected', 'online'], true);
            $this->output->set_output(json_encode([
                'success' => true,
                'connected' => $connected,
                'state' => $state,
                'state_label' => connect_api_connector_translate_state($state),
                'number' => $number,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (Throwable $e) {
            $this->output->set_status_header(500)->set_output(json_encode([
                'success' => false,
                'connected' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    public function restart()
    {
        $this->requirePermission('reconnect');
        $this->requirePost();
        try {
            $this->client()->restart();
            set_alert('success', _l('connect_api_connector_restart_ok'));
        } catch (Throwable $e) {
            set_alert('danger', $e->getMessage());
        }
        redirect(admin_url('connect_api_connector'));
    }

    public function send_test()
    {
        $this->requirePermission('send');
        $this->requirePost();
        $number = $this->connectapiconnectorconfig->normalizeNumber($this->input->post('number'));
        $message = trim((string) $this->input->post('message'));
        try {
            if ($number === '' || $message === '') throw new RuntimeException(_l('connect_api_connector_test_required'));
            $this->client()->sendText($number, $message);
            set_alert('success', _l('connect_api_connector_test_sent'));
        } catch (Throwable $e) {
            set_alert('danger', $e->getMessage());
        }
        redirect(admin_url('connect_api_connector'));
    }

    private function normalizeInstances($response)
    {
        if (!is_array($response)) {
            return [];
        }
        if ($this->isListArray($response)) {
            return $response;
        }
        foreach (['instances', 'data', 'response'] as $key) {
            if (isset($response[$key]) && is_array($response[$key]) && $this->isListArray($response[$key])) {
                return $response[$key];
            }
        }
        return [];
    }

    private function extractPhoneNumberFromResponse($response)
    {
        $rows = $this->normalizeInstances($response);
        if (empty($rows) && is_array($response)) {
            $rows = [$response];
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $candidates = [
                $row['number'] ?? null,
                $row['ownerJid'] ?? null,
                $row['owner'] ?? null,
                $row['instance']['number'] ?? null,
                $row['instance']['ownerJid'] ?? null,
                $row['instance']['owner'] ?? null,
            ];
            foreach ($candidates as $candidate) {
                $number = $this->normalizeInstancePhone($candidate);
                if ($number !== '') {
                    return $number;
                }
            }
        }

        return '';
    }

    private function normalizeInstancePhone($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (strpos($value, '@') !== false) {
            $value = explode('@', $value, 2)[0];
        }
        if (strpos($value, ':') !== false) {
            $value = explode(':', $value, 2)[0];
        }
        $digits = preg_replace('/\D+/', '', $value);
        return strlen($digits) >= 8 ? $digits : '';
    }

    private function isListArray($value)
    {
        if (!is_array($value)) {
            return false;
        }
        $index = 0;
        foreach (array_keys($value) as $key) {
            if ($key !== $index++) {
                return false;
            }
        }
        return true;
    }

    private function client($config = null)
    {
        return $this->connectapiconnectorclient->configure($config ?: $this->connectapiconnectorconfig->all());
    }

    private function isConfigured($config)
    {
        return !empty($config['base_url']) && !empty($config['instance_name']) && !empty($config['instance_token']);
    }

    private function requirePost()
    {
        if (strtoupper((string) $this->input->method(true)) !== 'POST') {
            show_error('Método não permitido', 405);
        }
    }

    private function requirePermission($capability)
    {
        if (!connect_api_connector_can($capability)) {
            access_denied(_l('connect_api_connector_title'));
        }
    }
}
