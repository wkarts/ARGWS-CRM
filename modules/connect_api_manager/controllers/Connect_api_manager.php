<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Connect_api_manager extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library(CONNECT_API_MANAGER_MODULE . '/ConnectApiManagerSecureStore');
        $this->load->library(CONNECT_API_MANAGER_MODULE . '/ConnectApiManagerClient');
        $this->load->model(CONNECT_API_MANAGER_MODULE . '/connect_api_manager_model');
        $this->ensureNamespaceHash();
    }

    public function index()
    {
        $this->requirePermission('view');
        $instances = [];
        $apiError = null;
        $configured = $this->isConfigured();

        if ($configured) {
            try {
                $response = $this->client()->fetchInstances();
                $instances = $this->filterInstancesByScope($this->normalizeInstances($response));
                $instances = $this->decorateInstances($instances);
            } catch (Throwable $e) {
                $apiError = $e->getMessage();
            }
        }

        $data = [
            'title' => _l('connect_api_manager_title'),
            'configured' => $configured,
            'instances' => $instances,
            'api_error' => $apiError,
            'namespace_label' => connect_api_manager_namespace_label(),
            'scope' => connect_api_manager_scope(),
        ];

        $this->load->view('dashboard', $data);
    }

    public function settings()
    {
        $this->requirePermission('credentials');

        if ($this->input->post()) {
            $url = rtrim(trim((string) $this->input->post('api_url')), '/');
            $token = trim((string) $this->input->post('admin_token'));
            $verifyTls = $this->input->post('verify_tls') ? '1' : '0';
            $timeout = max(5, min(120, (int) $this->input->post('timeout')));
            $scope = (string) $this->input->post('scope') === 'all' ? 'all' : 'module';

            update_option('connect_api_manager_api_url', $url);
            if ($token !== '') {
                update_option('connect_api_manager_admin_token', $this->connectapimanagersecurestore->encrypt($token));
            }
            update_option('connect_api_manager_verify_tls', $verifyTls);
            update_option('connect_api_manager_timeout', (string) $timeout);
            update_option('connect_api_manager_scope', $scope);

            set_alert('success', _l('settings_updated'));
            redirect(admin_url('connect_api_manager/settings'));
        }

        $data = [
            'title' => _l('connect_api_manager_title') . ' · ' . _l('connect_api_manager_settings'),
            'api_url' => get_option('connect_api_manager_api_url'),
            'has_token' => $this->getAdminToken() !== '',
            'verify_tls' => get_option('connect_api_manager_verify_tls') !== '0',
            'timeout' => (int) (get_option('connect_api_manager_timeout') ?: 30),
            'scope' => connect_api_manager_scope(),
            'namespace_label' => connect_api_manager_namespace_label(),
        ];

        $this->load->view('settings', $data);
    }

    public function test_connection()
    {
        $this->requirePermission('credentials');
        try {
            $response = $this->client()->fetchInstances();
            $instances = $this->filterInstancesByScope($this->normalizeInstances($response));
            set_alert('success', _l('connect_api_manager_connection_ok') . ' (' . count($instances) . ')');
        } catch (Throwable $e) {
            set_alert('danger', $e->getMessage());
        }
        redirect(admin_url('connect_api_manager/settings'));
    }

    public function create()
    {
        $this->requirePermission('create');
        $this->requirePost();

        $logicalName = $this->normalizeLogicalName((string) $this->input->post('instance_name'));
        if ($logicalName === '') {
            set_alert('danger', _l('connect_api_manager_invalid_instance_name'));
            redirect(admin_url('connect_api_manager'));
        }

        $name = connect_api_manager_namespace_prefix() . $logicalName;
        if (strlen($name) > 191) {
            set_alert('danger', _l('connect_api_manager_invalid_instance_name'));
            redirect(admin_url('connect_api_manager'));
        }

        $integration = (string) $this->input->post('integration');
        $allowed = ['WHATSAPP-BAILEYS', 'WHATSAPP-BUSINESS', 'CONNECT'];
        if (!in_array($integration, $allowed, true)) {
            $integration = 'WHATSAPP-BAILEYS';
        }

        $method = (string) $this->input->post('connection_method');
        if (!in_array($method, ['later', 'qrcode', 'pairing'], true)) {
            $method = 'qrcode';
        }

        $number = preg_replace('/\D+/', '', (string) $this->input->post('number'));
        if ($method === 'pairing' && strlen($number) < 10) {
            set_alert('danger', _l('connect_api_manager_pairing_number_required'));
            redirect(admin_url('connect_api_manager'));
        }

        $payload = [
            'instanceName' => $name,
            'integration' => $integration,
            'qrcode' => $integration === 'WHATSAPP-BAILEYS' && in_array($method, ['qrcode', 'pairing'], true),
        ];

        $manualToken = trim((string) $this->input->post('instance_token'));
        if ($manualToken !== '') {
            $payload['token'] = $manualToken;
        }

        if ($number !== '' && ($method === 'pairing' || $integration === 'WHATSAPP-BUSINESS')) {
            $payload['number'] = $number;
        }

        try {
            $response = $this->client()->createInstance($payload);
            $token = (string) ($response['hash'] ?? $manualToken);
            $state = $response['instance']['status'] ?? null;
            $phoneNumber = $this->extractPhoneNumberFromResponse($response);
            $this->connect_api_manager_model->save_instance($name, $token, $integration, $state, $phoneNumber);

            $data = [
                'title' => _l('connect_api_manager_connection'),
                'instance_name' => $name,
                'response' => $response,
                'instance_token' => $token,
                'connection_method' => $method,
                'status_url' => admin_url('connect_api_manager/connection_status/' . rawurlencode($name)),
                'return_url' => admin_url('connect_api_manager'),
            ];
            $this->load->view('connection', $data);
        } catch (Throwable $e) {
            set_alert('danger', $e->getMessage());
            redirect(admin_url('connect_api_manager'));
        }
    }

    public function connection($instanceName)
    {
        $this->requirePermission('manage');
        $this->requirePost();
        $instanceName = rawurldecode($instanceName);
        $this->assertInstanceAllowed($instanceName);

        $method = (string) $this->input->post('method');
        if (!in_array($method, ['qrcode', 'pairing'], true)) {
            $method = 'qrcode';
        }
        $number = preg_replace('/\D+/', '', (string) $this->input->post('number'));
        if ($method === 'pairing' && strlen($number) < 10) {
            set_alert('danger', _l('connect_api_manager_pairing_number_required'));
            redirect(admin_url('connect_api_manager'));
        }

        try {
            $response = $this->client()->connect($instanceName, $method === 'pairing' ? $number : null);
            $data = [
                'title' => _l('connect_api_manager_connection'),
                'instance_name' => $instanceName,
                'response' => $response,
                'instance_token' => '',
                'connection_method' => $method ?: 'qrcode',
                'status_url' => admin_url('connect_api_manager/connection_status/' . rawurlencode($instanceName)),
                'return_url' => admin_url('connect_api_manager'),
            ];
            $this->load->view('connection', $data);
        } catch (Throwable $e) {
            set_alert('danger', $e->getMessage());
            redirect(admin_url('connect_api_manager'));
        }
    }

    public function connection_status($instanceName)
    {
        $this->requirePermission('view');
        $name = rawurldecode($instanceName);
        $this->assertInstanceAllowed($name);

        $this->output
            ->set_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0')
            ->set_header('Pragma: no-cache')
            ->set_content_type('application/json', 'utf-8');

        try {
            $stateResponse = $this->client()->connectionState($name);
            $state = (string) ($stateResponse['instance']['state'] ?? 'unknown');
            $number = '';

            try {
                $infoResponse = $this->client()->fetchInstance($name);
                $number = $this->extractPhoneNumberFromResponse($infoResponse);
                $infoRows = $this->normalizeInstances($infoResponse);
                if (($state === '' || $state === 'unknown') && !empty($infoRows[0]['connectionStatus'])) {
                    $state = (string) $infoRows[0]['connectionStatus'];
                }
            } catch (Throwable $ignored) {
                // O estado continua válido mesmo se os detalhes demorarem a ficar disponíveis.
            }

            if ($number === '') {
                $number = $this->connect_api_manager_model->get_instance_number($name);
            }

            if ($this->connect_api_manager_model->is_local_instance($name)) {
                $this->connect_api_manager_model->update_runtime_info($name, $number, $state);
            }

            $connected = in_array(strtolower($state), ['open', 'connected', 'online'], true);
            $this->output->set_output(json_encode([
                'success' => true,
                'connected' => $connected,
                'state' => $state,
                'state_label' => connect_api_manager_translate_state($state),
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

    public function state($instanceName)
    {
        $this->requirePermission('view');
        $name = rawurldecode($instanceName);
        $this->assertInstanceAllowed($name);

        try {
            $response = $this->client()->connectionState($name);
            set_alert('info', _l('connect_api_manager_state') . ': ' . connect_api_manager_translate_state($response['instance']['state'] ?? 'unknown'));
        } catch (Throwable $e) {
            set_alert('danger', $e->getMessage());
        }
        redirect(admin_url('connect_api_manager'));
    }

    public function restart($instanceName)
    {
        $this->requirePermission('manage');
        $this->requirePost();
        $this->performAction('restart', rawurldecode($instanceName));
    }

    public function logout($instanceName)
    {
        $this->requirePermission('manage');
        $this->requirePost();
        $this->performAction('logout', rawurldecode($instanceName));
    }

    public function delete($instanceName)
    {
        $this->requirePermission('delete');
        $this->requirePost();
        $name = rawurldecode($instanceName);
        $this->assertInstanceAllowed($name);

        try {
            $this->client()->delete($name);
            $this->connect_api_manager_model->remove_instance($name);
            set_alert('success', _l('connect_api_manager_instance_deleted'));
        } catch (Throwable $e) {
            set_alert('danger', $e->getMessage());
        }
        redirect(admin_url('connect_api_manager'));
    }

    public function send_test($instanceName)
    {
        $this->requirePermission('manage');
        $this->requirePost();
        $name = rawurldecode($instanceName);
        $this->assertInstanceAllowed($name);

        $number = preg_replace('/\D+/', '', (string) $this->input->post('number'));
        $message = trim((string) $this->input->post('message'));
        if ($number === '' || $message === '') {
            set_alert('danger', _l('connect_api_manager_test_required'));
            redirect(admin_url('connect_api_manager'));
        }

        try {
            $this->client()->sendText($name, $number, $message);
            set_alert('success', _l('connect_api_manager_test_sent'));
        } catch (Throwable $e) {
            set_alert('danger', $e->getMessage());
        }
        redirect(admin_url('connect_api_manager'));
    }

    public function token($instanceName)
    {
        $this->requirePermission('credentials');
        $name = rawurldecode($instanceName);
        $this->assertInstanceAllowed($name, true);

        $token = $this->connect_api_manager_model->get_instance_token($name);
        $this->output
            ->set_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0')
            ->set_header('Pragma: no-cache')
            ->set_content_type('application/json', 'utf-8');

        if ($token === '') {
            $this->output->set_status_header(404)->set_output(json_encode([
                'success' => false,
                'message' => _l('connect_api_manager_token_unavailable'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return;
        }

        $this->output->set_output(json_encode([
            'success' => true,
            'instance' => $name,
            'token' => $token,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function performAction($action, $instanceName)
    {
        $this->assertInstanceAllowed($instanceName);
        try {
            $this->client()->{$action}($instanceName);
            set_alert('success', _l('connect_api_manager_action_ok'));
        } catch (Throwable $e) {
            set_alert('danger', $e->getMessage());
        }
        redirect(admin_url('connect_api_manager'));
    }

    private function client()
    {
        return $this->connectapimanagerclient->configure([
            'base_url' => get_option('connect_api_manager_api_url'),
            'api_key' => $this->getAdminToken(),
            'timeout' => (int) (get_option('connect_api_manager_timeout') ?: 30),
            'verify_tls' => get_option('connect_api_manager_verify_tls') !== '0',
        ]);
    }

    private function getAdminToken()
    {
        return $this->connectapimanagersecurestore->decrypt(get_option('connect_api_manager_admin_token'));
    }

    private function isConfigured()
    {
        return trim((string) get_option('connect_api_manager_api_url')) !== '' && $this->getAdminToken() !== '';
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

    private function filterInstancesByScope($instances)
    {
        if (connect_api_manager_scope() === 'all') {
            return $instances;
        }

        $filtered = [];
        foreach ($instances as $row) {
            $name = $this->instanceNameFromRow($row);
            if ($name !== '' && $this->instanceBelongsToModule($name)) {
                $filtered[] = $row;
            }
        }

        return $filtered;
    }

    private function decorateInstances($instances)
    {
        foreach ($instances as &$row) {
            $name = $this->instanceNameFromRow($row);
            $isLocal = $name !== '' && $this->connect_api_manager_model->is_local_instance($name);
            $number = $this->extractPhoneNumberFromRow($row);
            if ($number === '' && $isLocal) {
                $number = $this->connect_api_manager_model->get_instance_number($name);
            }
            $state = (string) ($row['connectionStatus'] ?? $row['state'] ?? $row['status'] ?? ($row['instance']['state'] ?? ''));

            $row['_connect_local'] = $isLocal;
            $row['_connect_has_token'] = $name !== '' && $this->connect_api_manager_model->has_instance_token($name);
            $row['_connect_owned'] = $name !== '' && $this->instanceBelongsToModule($name);
            $row['_connect_number'] = $number;

            if ($isLocal && ($number !== '' || $state !== '')) {
                $this->connect_api_manager_model->update_runtime_info($name, $number, $state);
            }
        }
        unset($row);

        return $instances;
    }

    private function extractPhoneNumberFromResponse($response)
    {
        $rows = $this->normalizeInstances($response);
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $number = $this->extractPhoneNumberFromRow($row);
                if ($number !== '') {
                    return $number;
                }
            }
        }

        return $this->extractPhoneNumberFromRow(is_array($response) ? $response : []);
    }

    private function extractPhoneNumberFromRow($row)
    {
        if (!is_array($row)) {
            return '';
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

    private function instanceNameFromRow($row)
    {
        return (string) ($row['name'] ?? $row['instanceName'] ?? ($row['instance']['instanceName'] ?? ''));
    }

    private function instanceBelongsToModule($name)
    {
        // A propriedade é confirmada pelo registro local do Manage.
        // O namespace facilita identificação e evita colisões, mas não é usado sozinho como autorização.
        return $this->connect_api_manager_model->is_local_instance($name);
    }

    private function assertInstanceAllowed($name, $allowLocalTokenLookup = false)
    {
        if (connect_api_manager_scope() === 'all') {
            if ($allowLocalTokenLookup && !$this->connect_api_manager_model->is_local_instance($name)) {
                show_error(_l('connect_api_manager_token_unavailable'), 404);
            }
            return;
        }

        if (!$this->instanceBelongsToModule($name)) {
            show_error(_l('connect_api_manager_scope_denied'), 403);
        }
    }

    private function normalizeLogicalName($name)
    {
        $name = trim((string) $name);
        if (function_exists('iconv')) {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
            if ($ascii !== false) {
                $name = $ascii;
            }
        }
        $name = strtolower($name);
        $name = preg_replace('/\s+/', '-', $name);
        $name = preg_replace('/[^a-z0-9._-]+/', '-', $name);
        $name = preg_replace('/-+/', '-', $name);
        $name = trim($name, '-._');

        if ($name === '' || strlen($name) < 2) {
            return '';
        }

        return $name;
    }

    private function ensureNamespaceHash()
    {
        if (connect_api_manager_namespace_hash() !== '') {
            return;
        }

        try {
            $hash = substr(bin2hex(random_bytes(4)), 0, 6);
        } catch (Throwable $e) {
            $hash = substr(hash('sha256', uniqid((string) mt_rand(), true)), 0, 6);
        }

        add_option('connect_api_manager_namespace_hash', $hash);
        if (trim((string) get_option('connect_api_manager_namespace_hash')) === '') {
            update_option('connect_api_manager_namespace_hash', $hash);
        }
    }

    private function requirePost()
    {
        if (strtoupper((string) $this->input->method(true)) !== 'POST') {
            show_error('Método não permitido', 405);
        }
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

    private function requirePermission($capability)
    {
        if (!connect_api_manager_can($capability)) {
            access_denied(_l('connect_api_manager_title'));
        }
    }
}
