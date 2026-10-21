<?php
defined('BASEPATH') or exit('No direct script access allowed');

require FCPATH . 'modules/asaas/traits/BaseApi.php';
require_once __DIR__ . '/AsaasSdkProvider.php';
require_once __DIR__ . '/Asaas/AsaasAdapter.php';
require_once __DIR__ . '/Asaas/EnvironmentSettings.php';

class Asaas_gateway extends App_gateway
{
    use BaseApi;
    private \Asaas\AsaasAdapter $adapter;
    private AsaasSdkProvider $provider;
    private ?string $operationEnvironment = null;

    public function __construct()
    {
        parent::__construct();

        $this->setId('asaas');
        $this->setName('Asaas');

        $this->setSettings(array(
            array(
                'name' => 'api_key',
                'encrypted' => true,
                'input_type' => 'password',
                'label' => 'Chave da API — Produção',
                'type' => 'input',
            ),
            array(
                'name' => 'api_key_sandbox',
                'encrypted' => true,
                'input_type' => 'password',
                'label' => 'Chave da API — Homologação',
                'type' => 'input',
            ),
            array(
                'name' => 'sandbox',
                'label' => 'Ambiente de novas operações',
                'type' => 'yes_no',
                'default_value' => 1,
            ),
            array(
                'name' => 'debug',
                'label' => 'Diagnóstico detalhado',
                'type' => 'yes_no',
                'default_value' => 0,
            ),
            array(
                'name' => 'file_log_enabled',
                'label' => 'Diagnóstico em arquivo',
                'type' => 'yes_no',
                'default_value' => 0,
            ),
            array(
                'name' => 'sync_enabled',
                'label' => 'Sincronização automática (CRON)',
                'type' => 'yes_no',
                'default_value' => 0,
            ),
            array(
                'name' => 'sync_days',
                'label' => 'Janela de dias para sincronização',
                'type' => 'input',
                'default_value' => 7,
            ),
            array(
                'name' => 'sync_limit',
                'label' => 'Limite de cobranças por execução',
                'type' => 'input',
                'default_value' => 50,
            ),
            array(
                'name' => 'currencies',
                'label' => 'settings_paymentmethod_currencies',
                'default_value' => 'BRL'
            ),
            array(
                'name' => 'description',
                'label' => 'settings_paymentmethod_description',
                'type' => 'textarea',
                'default_value' => 'Pagamento da Fatura {invoice_number}',
            ),
            array(
                'name' => 'interest_value',
                'label' => 'Valor juros',
                'type' => 'input',
                'default_value' => '0.00',
            ),
            array(
                'name' => 'fine_value',
                'label' => 'Valor multa',
                'type' => 'input',
                'default_value' => '0.00',
            ),
            array(
                'name' => 'discount_type',
                'label' => 'Tipo de desconto',
                'type' => 'yes_no',
                'default_value' => 1,
                //'field_attributes' => ['id' => 'discount_type_row'],
                //  'after'            => '<p class="mbot15">Statement descriptors are limited to 22 characters, cannot use the special characters <, >, \', ", or *, and must not consist solely of numbers.</p>',
            ),
            array(
                'name' => 'discount_value',
                'label' => 'Valor desconto',
                'type' => 'input',
                'default_value' => '0',
            ),
            array(
                'name' => 'discount_days',
                'label' => 'Dias para desconto',
                'type' => 'input',
                'default_value' => 0,
            ),
            array(
                'name' => 'installmentCount',
                'label' => 'Limite de parcelas',
                'type' => 'input',
                'default_value' => 1,
            ),
            array(
                'name' => 'diasdevencimento',
                'label' => 'Prazo para Pagamento em dias pós o cálculo de juros e multas',
                'type' => 'input',
                'default_value' => 3,
            ),
            array(
                'name' => 'billet_only',
                'label' => 'Habilitar boleto',
                'type' => 'yes_no',
                'default_value' => 1,
            ),
            array(
                'name' => 'card_only',
                'label' => 'Habilitar cartão de crédito',
                'type' => 'yes_no',
                'default_value' => 1,
            ),
            array(
                'name' => 'pix_only',
                'label' => 'Habilitar PIX',
                'type' => 'yes_no',
                'default_value' => 1,
            ),
            array(
                'name' => 'delete_charge',
                'label' => 'Deletar cobrança da fatura no Asaas',
                'type' => 'yes_no',
                'default_value' => 0,
            ),

            array(
                'name' => 'update_charge',
                'label' => 'Atualizar cobrança da fatura no Asaas',
                'type' => 'yes_no',
                'default_value' => 0,
            ),

            array(
                'name' => 'disable_charge_notification',
                'label' => 'Desativar notificações de cobrança',
                'type' => 'yes_no',
                'default_value' => 1,
            ),

            array(
                'name' => 'send_customer_email',
                'label' => 'Enviar e-mail do cliente para o Asaas (Customer)',
                'type' => 'yes_no',
                'default_value' => 0,
            ),

            array(
                'name' => 'send_customer_mobile',
                'label' => 'Enviar celular/telefone do cliente para o Asaas (Customer)',
                'type' => 'yes_no',
                'default_value' => 0,
            ),

            array(
                'name'          => 'ultima_atualizacao_servicos_municipais',
                'label'         => 'Última atualização de serviços municipais',
                'type'          => 'input',
                'default_value' => 1,
                // 'after' => '<p class="mbot15 text-gray">Última atualização de serviços municipais</p>',
                'input_type' => 'date',
                'field_attributes' => ['disabled' => true],
            ),

        ));

        $this->settings = \Asaas\EnvironmentSettings::expandDefinitions($this->settings);

        $this->provider = new AsaasSdkProvider($this);
        $this->adapter = new \Asaas\AsaasAdapter($this->provider, $this);
    }

    public function initMode($modes)
    {
        if (class_exists('CompanySettingsRuntime', false) && CompanySettingsRuntime::active()
            && !CompanySettingsRuntime::isPrimary()) return $modes;
        // Only the tracked native migration may seed profiles; GET registration never migrates data.
        if (!\Asaas\EnvironmentSettings::ready()) return $modes;
        return parent::initMode($modes);
    }

    public function selectedEnvironment(): string
    {
        \Asaas\EnvironmentSettings::assertLegacyScope();
        \Asaas\EnvironmentSettings::requireReady();
        return \Asaas\EnvironmentSettings::fromSelector(get_option('paymentmethod_asaas_sandbox'));
    }

    public function getSetting($name)
    {
        \Asaas\EnvironmentSettings::assertLegacyScope();
        if ($name === 'sandbox') {
            \Asaas\EnvironmentSettings::requireReady();
            return $this->operationEnvironment === null ? get_option('paymentmethod_asaas_sandbox')
                : ($this->operationEnvironment === 'sandbox' ? '1' : '0');
        }
        if (!array_key_exists($name, \Asaas\EnvironmentSettings::DEFAULTS)) return parent::getSetting($name);
        if (!\Asaas\EnvironmentSettings::ready()) {
            if (in_array($name, ['active', 'default_selected'], true)) return '0';
            if ($name === 'label') return 'Asaas';
            \Asaas\EnvironmentSettings::requireReady();
        }
        try { $environment = $this->operationEnvironment ?? $this->selectedEnvironment(); }
        catch (\InvalidArgumentException $error) {
            // Allow administrators to repair an invalid selector; never enable a mode implicitly.
            if (in_array($name, ['active', 'default_selected'], true)) return '0';
            if ($name === 'label') return 'Asaas';
            throw $error;
        }
        return $this->getSettingForEnvironment($name, $environment);
    }

    public function getSettingForEnvironment(string $name, string $environment): string
    {
        \Asaas\EnvironmentSettings::assertLegacyScope();
        \Asaas\EnvironmentSettings::requireReady();
        return \Asaas\EnvironmentSettings::read($name, $environment, function (string $key) {
            // Query the row to distinguish an intentionally empty value from an absent one.
            $row = $this->ci->db->get_where(db_prefix() . 'options', ['name' => $key])->row_array() ?? [];
            return array_key_exists('value', $row) ? $row['value'] : null;
        });
    }

    public function getSettingForOrigin(string $name, array $origin): string
    {
        return $this->getSettingForEnvironment($name, \Asaas\EnvironmentSettings::originEnvironment($origin));
    }

    private function withInvoiceProfile(int $invoiceId, callable $operation)
    {
        if ($invoiceId <= 0) throw new \InvalidArgumentException('asaas_invoice_required');
        $origin = $this->adapter->originStore()->byInvoice($invoiceId);
        $environment = $origin ? \Asaas\EnvironmentSettings::originEnvironment($origin) : $this->selectedEnvironment();
        $previous = $this->operationEnvironment;
        $this->operationEnvironment = $environment;
        try { return $operation(); }
        finally { $this->operationEnvironment = $previous; }
    }

    public function getProvider(): AsaasSdkProvider
    {
        return $this->provider;
    }

    

    public function getAdapter(): \Asaas\AsaasAdapter
    {
        return $this->adapter;
    }

    public function getEnvironmentLabel(): string
    {
        return $this->provider->environmentLabel();
    }

    public function isFileLogEnabled(?string $environment = null): bool
    {
        $value = $environment === null ? $this->getSetting('file_log_enabled') : $this->getSettingForEnvironment('file_log_enabled', $environment);

        return $value === '1' || $value === 1 || $value === true;
    }

    public function apiKey(?int $companyId = null): string
    {
        return $this->provider->apiKey($companyId);
    }

    public function isSandbox(?int $companyId = null): bool
    {
        return $this->provider->env($companyId) === 'sandbox';
    }

    public function client(?int $companyId = null): \Asaas\Sdk\Http\Client
    {
        return $this->provider->client($companyId);
    }

    public function request(
        string $method,
        string $path,
        array $query = [],
        array $headers = [],
        ?array $payload = null,
        bool $expectsBinary = false,
        ?int $companyId = null
    ): array {
        return $this->adapter->request($method, $path, $query, $headers, $payload, $expectsBinary, $companyId);
    }

    public function process_payment($data)
    {
        try {
            return $this->withInvoiceProfile((int) ($data['invoice']->id ?? 0), fn() => $this->process_payment_checked($data));
        } catch (\Throwable $error) {
            log_message('error', '[Asaas] Operação de cobrança não confirmada: process_payment.');
            if (function_exists('set_alert')) {
                set_alert('warning', 'Cobrança Asaas não confirmada. Solicite a revisão da origem da cobrança.');
            }
            return json_encode(['errors' => [['code' => 'payment_requires_review', 'description' => 'Cobrança não confirmada; revisão necessária.']]]);
        }
    }

    private function process_payment_checked($data)
    {
        if (empty($data)) {
            return;
        }

        $invoice = $data['invoice']->id;
        $ci = &get_instance();
        $ci->db->where('id', $invoice);
        $row = $ci->db->get(db_prefix() . 'invoices')->row_array();
        $this->adapter->assertInvoiceScope($row);
        if ($this->getSetting('card_only') === '1' && $this->getSetting('billet_only') === '0' && $this->getSetting('pix_only') === '0') {
            redirect(site_url('asaas/checkout/cartao/' . $row['hash']));
            return;
        }

        $ci->db->select('c.*, cc.email');
        $ci->db->from(db_prefix() . 'clients c');
        $ci->db->join(db_prefix() . 'contacts cc', 'cc.userid = c.userid AND cc.is_primary = 1', 'LEFT');
        $ci->db->where('c.userid', $row['clientid']);
        $client = $ci->db->get()->row_array();

        $debug = $this->getSetting('debug');

        $api_key = $this->getApiKey();
        $api_url = $this->getUrlBase();

        $billet_only = $this->getSetting('billet_only');
        $card_only = $this->getSetting('card_only');
        $pix_only = $this->getSetting('pix_only');
        $interest = $this->getSetting('interest_value');
        $fine = $this->getSetting('fine_value');
        $discount_value = $this->getSetting('discount_value');
        $dueDateLimitDays = $this->getSetting('discount_days');
        $discount_type = $this->getSetting('discount_type');
        $description = $this->getSetting('description');

        $search_charge = $this->search_charge($api_url, $api_key, $row["hash"]);

        if ($row['status'] == '4') {
            $row['total'] = $this->calculate_invoice($invoice, $row, $fine, $interest);
            if ($this->debug_enable()) {
                echo $row['total'];
                echo "<br>";
                echo $ci->db->last_query();
                echo "<br>";
                echo "<pre>";
                var_dump($search_charge);
                echo "</pre>";
            }
        }

        $ci->db->where('id', $invoice);
        $row = $ci->db->get(db_prefix() . 'invoices')->row_array();

        $disable_charge_notification = $this->getSetting('disable_charge_notification');

        if ($disable_charge_notification == '1') {
            $notificationDisabled = true;
        } else {
            $notificationDisabled = false;
        }

        $invoice_number = $row['prefix'] . str_pad($row['number'], 6, "0", STR_PAD_LEFT);
        $description = utf8_encode(str_replace("{invoice_number}", $invoice_number, $description));

        $document = str_replace('/', '', str_replace('-', '', str_replace('.', '', $client['vat'])));
        $postalCode = str_replace('-', '', str_replace('.', '', $client['zip']));

        $customer = $this->search_customer($api_url, $api_key, $document);
        if ($customer['totalCount'] == "0") {
            $post_data = json_encode([
                "name" => $client['company'],
                "email" => $client['email'],
                "cpfCnpj" => $document,
                "postalCode" => $postalCode,
                "address" => $client['address'],
                "addressNumber" => $client['numero'],
                "complement" => "",
                "phone" => $client['phonenumber'],
                "mobilePhone" => $client['phonenumber'],
                "externalReference" => $invoice,
                "notificationDisabled" => $notificationDisabled,
            ]);

            $cliente_create = $this->create_customer($api_url, $api_key, $post_data);
            $cliente_id = $cliente_create['id'];

            log_activity('Cliente cadastrado no Asaas [Cliente ID: ' . $cliente_id . ']');

            if ($this->debug_enable()) {
                echo "Campos cadastro";
                echo "<br>";
                echo "<pre>";
                var_dump($post_data);
                echo "</pre>";
                echo "Cliente cadastrado no Asaas ID" . $cliente_id;
                echo "<hr>";
            }
        } else {
            // se existir recupera os dados para cobranca
            $cliente_id = $customer['data'][0]['id'];
            if ($this->debug_enable()) {
                echo "Cliente já existente ID " . $cliente_id;
                echo "<hr>";
            }
        }

        $discount = NULL;

        $sem_desconto = strpos($row['adminnote'], "{sem_desconto}", 0);

        if ($discount_type == 1) {

            $type = 'FIXED';

            $discount = [
                'type' => 'FIXED',
                "value" => $discount_value,
                "dueDateLimitDays" => $dueDateLimitDays,
            ];
        }

        if ($discount_type == 0) {

            $type = 'PERCENTAGE';

            $discount = [
                'type' => 'PERCENTAGE',
                "value" => $discount_value,
                "dueDateLimitDays" => $dueDateLimitDays,
            ];
        }

        if (is_bool($sem_desconto)) {
            $discount = [
                'type' => $type,
                "value" => $discount_value,
                "dueDateLimitDays" => $dueDateLimitDays,
            ];
        }

        if ($this->debug_enable()) {
            echo "Tipo desconto config " . $discount_type;
            echo "<br>";
            echo "Tipo desconto " . $type;
            echo "<br>";
            echo "Campos desconto";
            echo "<br>";
            echo "<pre>";
            var_dump($discount);
            echo "</pre>";
            echo "<hr>";
            echo "Sem desconto " .   var_dump($sem_desconto);
            echo "<br>";
        }

        $post_data = [
            "customer" => $cliente_id,
            "billingType" => "BOLETO",
            "dueDate" => $row['duedate'],
            "value" => $row['total'],
            "description" => $description,
            "externalReference" => $row['hash'],
            "discount" => $discount,
            "fine" => [
                "value" => $fine,
            ],
            "interest" => [
                "value" => $interest,
            ],
            "postalService" => false
        ];

        if ($search_charge) {
            unset($post_data["discount"]);
            unset($post_data["fine"]);
            unset($post_data["interest"]);
        }

        $post_data = json_encode($post_data);

        if ($this->debug_enable()) {

            echo "Campos cobranca asaas";
            echo "<br>";
            echo "<pre>";
            var_dump($post_data);
            echo "</pre>";
            echo "<hr>";

            //	die();
        }

        // não tem cobrança no asaas
        if (!$search_charge) {
            $charge = $this->create_charge($api_url, $api_key, $post_data);

            log_activity('Cobrança Boleto/Pix Asaas [Fatura ID: ' . $invoice . ']');
        } else {
            $charge = $this->update_charge($search_charge->id, $post_data);

            log_activity('Cobrança atualizada Asaas [Fatura ID: ' . $invoice . ']');
        }


        //	echo "<hr>";

        //		die();

        if ($billet_only == 1 && $card_only == 0 && $pix_only == 0) {

            redirect(site_url('asaas/checkout/boleto/' . $row['hash']));
        }

        if ($billet_only == 0 && $card_only == 1 && $pix_only == 0) {

            redirect(site_url('asaas/checkout/cartao/' . $row['hash']));
        }

        if ($billet_only == 0 && $card_only == 0 && $pix_only == 1) {

            redirect(site_url('asaas/checkout/qrcode/' . $row['hash']));
        }
        redirect(site_url('asaas/checkout/index/' . $row['hash']));
    }

    public function calculate_invoice($invoice, $row, $fine, $interest)
    {
        $ci = &get_instance();

        // Obter o timestamp atual
        $now = time();

        // Converte a data de vencimento em timestamp
        $duedate = strtotime($row["duedate"]);

        // Calcula a diferença em segundos entre a data atual e a data de vencimento
        $datediff = $now - $duedate;

        // Obtém o número de dias para ajuste de vencimento
        $datevence = $this->getSetting('diasdevencimento');

        // Ajusta o subtotal da fatura
        $row["subtotal"] = $row["subtotal"] + $row["adjustment"];
        $row["subtotal"] = get_invoice_total_left_to_pay($row["id"], $row["subtotal"]);

        // Calcula o número de dias em atraso
        $overdue_days = round($datediff / (60 * 60 * 24));

        // Calcula os juros proporcionais ao mês
        $daily_interest_rate = $interest / 30; // Juros ao mês convertido para dia
        $overdue_interest = $row["subtotal"] * ($daily_interest_rate / 100) * $overdue_days;

        // Calcula a multa de atraso
        $overdue_fine = $row["subtotal"] * ($fine / 100); // Multa é uma porcentagem do subtotal

        // Atualiza o total devido com multa e juros
        $updated_total_overdue = $overdue_interest + $overdue_fine;
        $updated_total = $row["subtotal"] + $updated_total_overdue;

        // Calcula o ajuste da fatura
        $adjustment = $row["adjustment"] + $updated_total_overdue;

        // Verifica se a fatura é recorrente
        $is_recurring = $ci->db->get_where(db_prefix() . 'invoices', ['id' => $invoice, 'recurring' => 1])->row_array();

        // Prepara os dados para atualização
        $update_data = [
            'status' => 1,
            'adjustment' => $adjustment,
            'subtotal' => $updated_total,
            'total' => $updated_total,
        ];

        // Se a fatura não é recorrente, atualiza a data de vencimento
        if (!$is_recurring) {
            $update_data['duedate'] = date('Y-m-d', strtotime("+" . $datevence . " days"));
        }

        // Atualiza a fatura no banco de dados
        $ci->db->where('id', $invoice);
        $ci->db->update(db_prefix() . 'invoices', $update_data);

        // Retorna o total atualizado
        return $updated_total;
    }

    public function get_charge($hash)
    {
        $api_key = $this->getApiKey();
        $api_url = $this->getUrlBase();
        $charge = $this->search_charge($api_url, $api_key, $hash);
        return $charge;
    }

    public function get_charge2($hash)
    {
        $api_key = $this->getApiKey();
        $api_url = $this->getUrlBase();
        $charge = $this->search_charge2($api_url, $api_key, $hash);
        return $charge;
    }

    public function search_charge($api_url, $api_key, $hash)
    {
        $payments = $this->adapter->listPayments(['externalReference' => $hash]);

        // Verifique se a resposta é válida e se contém dados
        if (isset($payments['data']) && is_array($payments['data'])) {
            foreach ($payments['data'] as $charge) {
                if (($charge['externalReference'] ?? null) === $hash) {
                    return json_decode(json_encode($charge));
                }
            }
        }

        // Retorna null se nenhuma cobrança for encontrada
        return null;
    }

    public function search_charge2($api_url, $api_key, $hash)
    {
        $payments = $this->adapter->listPayments([
            'externalReference' => $hash,
            'limit' => 100,
        ]);
        $charges = $payments['data'] ?? [];

        $response = [];

        if ($charges) {
            foreach ($charges as $charge) {
                if (($charge['externalReference'] ?? null) === $hash) {
                    $response[] = json_decode(json_encode($charge));
                }
            }
        }
        return $response;
    }

    public function debug_enable()
    {
        return $this->getSetting('debug') == '1';
    }

    public function charge_billet($invoice)
    {
        try {
            return $this->withInvoiceProfile((int) ($invoice->id ?? 0), fn() => $this->charge_billet_checked($invoice));
        } catch (\Throwable $error) {
            log_message('error', '[Asaas] Operação de cobrança não confirmada: charge_billet.');
            if (function_exists('set_alert')) {
                set_alert('warning', 'Cobrança Asaas não confirmada. Solicite a revisão da origem da cobrança.');
            }
            return json_encode(['errors' => [['code' => 'payment_requires_review', 'description' => 'Cobrança não confirmada; revisão necessária.']]]);
        }
    }

    private function charge_billet_checked($invoice)
    {
        if (empty($invoice)) {
            return;
        }
        $this->adapter->assertInvoiceScope((array) $invoice);
        $client = $invoice->client;

        $description                 = $this->getSetting('description');
        $interest                    = $this->getSetting('interest_value');
        $fine                        = $this->getSetting('fine_value');
        $discount_value              = $this->getSetting('discount_value');
        $dueDateLimitDays            = $this->getSetting('discount_days');
        $discount_type               = $this->getSetting('discount_type');
        $disable_charge_notification = $this->getSetting('disable_charge_notification');

        if ($disable_charge_notification == '1') {
            $notificationDisabled = true;
        } else {
            $notificationDisabled = false;
        }

        $invoice_number = format_invoice_number($invoice->id);
        $description = mb_convert_encoding(str_replace("{invoice_number}", $invoice_number, $description), 'UTF-8', 'ISO-8859-1');

        $document = str_replace('/', '', str_replace('-', '', str_replace('.', '', $client->vat)));
        $postalCode = str_replace('-', '', str_replace('.', '', $client->zip));

        $customer = $this->search_customer($this->getUrlBase(), $this->getApiKey(), $document);

        if ($customer['totalCount'] == '0') {
            $post_data = json_encode([
                "name"                 => $client->company,
                "email"                => $client->email,
                "cpfCnpj"              => $document,
                "postalCode"           => $postalCode,
                "address"              => $client->address,
                "addressNumber"        => $client->numero,
                "complement"           => "",
                "phone"                => $client->phonenumber,
                "mobilePhone"          => $client->phonenumber,
                "externalReference"    => $invoice, // TODO: verificar essa informação
                "notificationDisabled" => $notificationDisabled,
            ]);

            $cliente_create = $this->create_customer($this->getUrlBase(), $this->getApiKey(), $post_data);

            $cliente_id = $cliente_create['id'];

            log_activity('Cliente cadastrado no Asaas [Cliente ID: ' . $cliente_id . ']');

            if ($this->debug_enable()) {
                echo "Campos cadastro";
                echo "<br>";
                echo "<pre>";
                var_dump($post_data);
                echo "</pre>";
                echo "Cliente cadastrado no Asaas ID " . $cliente_id;
                echo "<hr>";
            }
        } else {
            // se existir recupera os dados para cobranca
            $cliente_id = $customer['data'][0]['id'];
            if ($this->debug_enable()) {
                echo "Cliente já existente ID " . $cliente_id;
                echo "<hr>";
            }
        }

        $discount = NULL;

        $sem_desconto = strpos($invoice->adminnote, "{sem_desconto}", 0);

        if ($discount_type == 1) {

            $type = 'FIXED';

            $discount = [
                'type'             => 'FIXED',
                "value"            => $discount_value,
                "dueDateLimitDays" => $dueDateLimitDays,
            ];
        }

        if ($discount_type == 0) {

            $type = 'PERCENTAGE';

            $discount = [
                'type' => 'PERCENTAGE',
                "value" => $discount_value,
                "dueDateLimitDays" => $dueDateLimitDays,
            ];
        }

        if (is_bool($sem_desconto)) {
            $discount = [
                'type' => $type,
                "value" => $discount_value,
                "dueDateLimitDays" => $dueDateLimitDays,
            ];
        }

        if ($this->debug_enable()) {
            echo "Tipo desconto" . $discount_type;
            echo "<br>";
            echo "Campos desconto";
            echo "<br>";
            echo "<pre>";
            var_dump($discount);
            echo "</pre>";
            echo "<hr>";
            echo "Sem desconto" . $sem_desconto;
            echo "<br>";
            echo "<pre>";
            var_dump($discount);
            echo "</pre>";
            echo "<hr>";
        }

        $post_data = json_encode([
            "customer"          => $cliente_id,
            "billingType"       => "BOLETO",
            "dueDate"           => $invoice->duedate,
            "value"             => $invoice->total,
            "description"       => $description,
            "externalReference" => $invoice->hash,
            "discount" => $discount,
            "fine" => [
                "value" => $fine,
            ],
            "interest" => [
                "value" => $interest,
            ],
            "postalService" => false
        ]);

        $charge = $this->create_charge($this->getUrlBase(), $this->getApiKey(), $post_data);

        $charge = $this->safe_json_decode_array($charge);

        log_activity('Cobrança Boleto Criada no Asaas [Fatura ID: ' . $invoice->id . ']');

        if ($this->debug_enable()) {

            echo "Cobrança Boleto";
            echo "<br>";
            echo "<pre>";
            var_dump($charge);
            echo "</pre>";
            echo "<hr>";
        }

        return $charge;
    }

    public function charge_credit_card($data)
    {
        try {
            return $this->withInvoiceProfile((int) ($data['invoice']->id ?? 0), fn() => $this->charge_credit_card_checked($data));
        } catch (\Throwable $error) {
            log_message('error', '[Asaas] Operação de cobrança não confirmada: charge_credit_card.');
            if (function_exists('set_alert')) {
                set_alert('warning', 'Cobrança Asaas não confirmada. Solicite a revisão da origem da cobrança.');
            }
            return json_encode(['errors' => [['code' => 'payment_requires_review', 'description' => 'Cobrança não confirmada; revisão necessária.']]]);
        }
    }

    private function charge_credit_card_checked($data)
    {
        require_once __DIR__ . '/Asaas/HostedCardCheckout.php';
        if (!is_array($data) || array_diff(array_keys($data), ['invoice', 'installmentCount'])
            || empty($data['invoice']->id)) {
            throw new \InvalidArgumentException('raw_card_checkout_disabled');
        }
        \Asaas\HostedCardCheckout::installments($data['installmentCount'] ?? 1);
        $ci = &get_instance();
        $invoice = $ci->db->get_where(db_prefix() . 'invoices', ['id' => (int) $data['invoice']->id])->row_array();
        $store = $this->adapter->originStore();
        $origin = $invoice ? $store->byInvoice((int) $invoice['id']) : null;
        $active = $origin ? $this->getSettingForOrigin('active', $origin) : $this->getSetting('active');
        $card = $origin ? $this->getSettingForOrigin('card_only', $origin) : $this->getSetting('card_only');
        if (!$invoice || !in_array((int) $invoice['status'], [1, 4], true)
            || $active !== '1' || $card !== '1'
            || !\Asaas\HostedCardCheckout::allowsInvoice($invoice['allowed_payment_modes'] ?? null)) {
            throw new \DomainException('hosted_card_invoice_unavailable');
        }
        $this->adapter->assertInvoiceScope($invoice);
        $currency = get_currency($invoice['currency'] ?? 0);
        if (!$currency || strtoupper((string) $currency->name) !== 'BRL') {
            throw new \DomainException('hosted_card_requires_brl');
        }
        if ($origin) {
            if (($origin['state'] ?? '') !== 'issued' || empty($origin['remote_id'])) {
                throw new \DomainException('card_origin_review_required');
            }
            $payment = $this->adapter->getPayment($origin['remote_id']);
            // Existing BOLETO must be explicitly replaced through reviewed origin switching.
            // Never update/delete it or create a second charge just to show a card form.
        } else {
            if ($ci->db->get_where(db_prefix() . 'asaas_payments_map', ['invoice_id' => (int) $invoice['id']])->row_array()
                || $ci->db->get_where(db_prefix() . 'invoicepaymentrecords', ['invoiceid' => (int) $invoice['id']])->num_rows() > 0) {
                throw new \DomainException('card_origin_review_required');
            }
            $client = $ci->db->get_where(db_prefix() . 'clients', ['userid' => (int) $invoice['clientid']])->row();
            if (!$client) {
                throw new \DomainException('card_customer_unavailable');
            }
            $customer = $this->ensure_customer_for_client((int) $invoice['clientid'], $client);
            if (!($customer['ok'] ?? false) || empty($customer['asaas_customer_id'])) {
                throw new \DomainException('card_customer_unavailable');
            }
            $amount = get_invoice_total_left_to_pay($invoice['id'], $invoice['total']);
            $payload = \Asaas\HostedCardCheckout::payload($invoice, (string) $customer['asaas_customer_id'], $amount, 1);
            $payment = $this->adapter->createPayment($payload);
            $origin = $store->byInvoice((int) $invoice['id']);
            if (!$origin || ($origin['state'] ?? '') !== 'issued' || ($origin['remote_id'] ?? '') !== ($payment['id'] ?? null)) {
                throw new \DomainException('card_origin_review_required');
            }
        }
        $url = \Asaas\HostedCardCheckout::verifiedUrl($payment, (string) $origin['environment'], (string) $invoice['hash']);
        // Return only presentation metadata; never propagate provider card/token fields.
        return json_encode(['id' => $payment['id'], 'invoiceUrl' => $url, 'billingType' => $payment['billingType']]);
    }

    public function charge_pix($data)
    {
        try {
            return $this->withInvoiceProfile((int) ($data['invoice']->id ?? 0), fn() => $this->charge_pix_checked($data));
        } catch (\Throwable $error) {
            log_message('error', '[Asaas] Operação de cobrança não confirmada: charge_pix.');
            if (function_exists('set_alert')) {
                set_alert('warning', 'Cobrança Asaas não confirmada. Solicite a revisão da origem da cobrança.');
            }
            return json_encode(['errors' => [['code' => 'payment_requires_review', 'description' => 'Cobrança não confirmada; revisão necessária.']]]);
        }
    }

    private function charge_pix_checked($data)
    {
        if (empty($data)) {
            return;
        }
        $invoice = $data['invoice']->id;
        $ci = &get_instance();
        $ci->db->where('id', $invoice);
        $row = $ci->db->get(db_prefix() . "invoices")->row_array();
        $this->adapter->assertInvoiceScope($row);

        $ci->db->select('c.*, cc.email');
        $ci->db->from(db_prefix() . 'clients c');
        $ci->db->join(db_prefix() . 'contacts cc', 'cc.userid = c.userid AND cc.is_primary = 1', 'LEFT');
        $ci->db->where('c.userid', $row['clientid']);
        $client = $ci->db->get()->row_array();

        $api_key = $this->getApiKey();
        $api_url = $this->getUrlBase();
        $description = $this->getSetting('description');
        $interest = $this->getSetting('interest_value');
        $fine = $this->getSetting('fine_value');
        $discount_value = $this->getSetting('discount_value');
        $dueDateLimitDays = $this->getSetting('discount_days');
        $billet_only = $this->getSetting('billet_only');

        $discount_type = $this->getSetting('discount_type');

        $disable_charge_notification = $this->getSetting('disable_charge_notification');

        if ($disable_charge_notification == '1') {
            $notificationDisabled = false;
        } else {
            $notificationDisabled = true;
        }


        $invoice_number = $row['prefix'] . str_pad($row['number'], 6, "0", STR_PAD_LEFT);
        $description = utf8_encode(str_replace("{invoice_number}", $invoice_number, $description));
        // valida cliente
        $document = str_replace('/', '', str_replace('-', '', str_replace('.', '', $client['vat'])));
        $postalCode = str_replace('-', '', str_replace('.', '', $client->zip));
        $customer = $this->search_customer($api_url, $api_key, $document);
        if ($customer['totalCount'] == "0") {

            $post_data = [
                "name" => $client['company'],
                "email" => $client['email'],
                "cpfCnpj" => $document,
                "postalCode" => $postalCode,
                "address" => $client['address'],
                "addressNumber" => $client['numero'],
                "phone" => $client['phonenumber'],
                "mobilePhone" => $client['phonenumber'],
                "complement" => "",
                "externalReference" => $invoice,
                "notificationDisabled" => $notificationDisabled,
            ];
            $post_data = json_encode($post_data);
            $cliente_create = $this->create_customer($api_url, $api_key, $post_data);
            $cliente_id = $cliente_create['id'];
            log_activity('Cliente cadastrado no Asaas [Cliente ID: ' . $cliente_id . ']');

            if ($this->debug_enable()) {
                echo "Campos cadastro";
                echo "<br>";
                echo "<pre>";
                var_dump($post_data);
                echo "</pre>";
                echo "Cliente cadastrado no Asaas ID" . $cliente_id;
                echo "<hr>";
            }
        } else {
            // se existir recupera os dados para cobranca
            $cliente_id = $customer['data'][0]['id'];
            if ($this->debug_enable()) {
                echo "Cliente já existente ID" . $cliente_id;
                echo "<hr>";
            }
        }


        if ($row['status'] == '4') {

            // calculate_invoice()

            $now = time();
            $duedate = strtotime($row["duedate"]);
            $datediff = $now - $duedate;
            $datevence = $this->getSetting('diasdevencimento');;

            $row["subtotal"] = $row["subtotal"] + $row["adjustment"];

            $row["subtotal"] = get_invoice_total_left_to_pay($row["id"], $row["subtotal"]);

            $overdue_days = round($datediff / (60 * 60 * 24));

            $overdue_days_interest = $interest * (int)$overdue_days;

            $overdue_interest = $row["subtotal"] * $overdue_days_interest;

            $overdue_fine = $row["subtotal"] * $fine;

            $updated_total_overdue = number_format($overdue_interest, 2) + $overdue_fine;
            $updated_total = $row["subtotal"] + number_format($updated_total_overdue / 100, 2);
            $adjustment = $row["adjustment"] + number_format($updated_total_overdue / 100, 2);

            $update_data = [
                'status' => 1,
                'adjustment' => $adjustment,
                'subtotal' => $updated_total,
                'total' => $updated_total,
                'duedate' => date('Y-m-d', strtotime("+" . $datevence, " day"))
            ];

            $ci->db->where('id', $invoice);
            $ci->db->update(db_prefix() . 'invoices', $update_data);

            $search_charge = $this->search_charge($api_url, $api_key, $row["hash"]);

            //

            $discount = NULL;

            $sem_desconto = strpos($row["adminnote"], "{sem_desconto}", 0);

            if ($discount_type == 1) {

                $type = 'FIXED';

                $discount = [
                    'type' => 'FIXED',
                    "value" => $discount_value,
                    "dueDateLimitDays" => $dueDateLimitDays,
                ];
            }

            if ($discount_type == 0) {

                $type = 'PERCENTAGE';

                $discount = [
                    'type' => 'PERCENTAGE',
                    "value" => $discount_value,
                    "dueDateLimitDays" => $dueDateLimitDays,
                ];
            }

            if (is_bool($sem_desconto)) {
                $discount = [
                    'type' => $type,
                    "value" => $discount_value,
                    "dueDateLimitDays" => $dueDateLimitDays,
                ];
            }

            $post_data = json_encode([
                "customer" => $search_charge->customer,
                "billingType" => $search_charge->billingType,
                "dueDate" => date('Y-m-d', strtotime("+10 day")),
                "value" => $updated_total,
                "description" => $search_charge->description,
                "externalReference" => $row['hash'],
                "discount" => $discount,
                "fine" => [
                    "value" => $fine,
                ],
                "interest" => [
                    "value" => $interest,
                ],
                "postalService" => false
            ]);

            $charge = $this->update_charge($search_charge->id, $post_data);

            return $charge;
        }

        $discount = NULL;

        $sem_desconto = strpos($row["adminnote"], "{sem_desconto}", 0);

        if ($discount_type == 1) {

            $type = 'FIXED';

            $discount = [
                'type' => 'FIXED',
                "value" => $discount_value,
                "dueDateLimitDays" => $dueDateLimitDays,
            ];
        }

        if ($discount_type == 0) {

            $type = 'PERCENTAGE';

            $discount = [
                'type' => 'PERCENTAGE',
                "value" => $discount_value,
                "dueDateLimitDays" => $dueDateLimitDays,
            ];
        }

        if (is_bool($sem_desconto)) {
            $discount = [
                'type' => $type,
                "value" => $discount_value,
                "dueDateLimitDays" => $dueDateLimitDays,
            ];
        }

        $row["total"] = get_invoice_total_left_to_pay($row["id"], $row["subtotal"]);
        $post_data = json_encode([
            "customer" => $cliente_id,
            "billingType" => "PIX",
            "dueDate" => $row['duedate'],
            "value" => $row['total'],
            "description" => $description,
            "externalReference" => $row['hash'],
            "discount" => $discount,
            "fine" => [
                "value" => $fine,
            ],
            "interest" => [
                "value" => $interest,
            ],
            "postalService" => false
        ]);

        $charge = $this->create_charge($api_url, $api_key, $post_data);
        $charge = $this->safe_json_decode_array($charge);

        log_activity('Cobran�a PIX Asaas [Fatura ID: ' . $invoice . ']');

        return $charge;
    }

    public function create_charge($api_url, $api_key, $post_data)
    {
        $payload = $this->safe_json_decode_array($post_data);
        $response = $this->adapter->createPayment(is_array($payload) ? $payload : []);
        return json_encode($response);
    }

    public function create_qrcode($payment_id)
    {
        $response = $this->adapter->getPixInfo($payment_id);
        return json_encode($response);
    }

    public function get_customer($cpfCnpj)
    {
        $customer = $this->search_customer($this->getUrlBase(), $this->getApiKey(), $cpfCnpj);
        return $customer;
    }

    public function search_customer($api_url, $api_key, $cpfCnpj)
    {
        return $this->adapter->request('GET', '/customers', ['cpfCnpj' => $cpfCnpj]);
    }

    public function update_charge($charge_id, $post_data)
    {
        $payload = $this->safe_json_decode_array($post_data);
        return $this->adapter->updatePayment($charge_id, is_array($payload) ? $payload : []);
    }

    // https://api.asaas.com/v3/payments/id
    public function delete_charge($charge_id)
    {
        return $this->adapter->deletePayment((string) $charge_id);
    }

    public function create_customer($api_url, $api_key, $post_data)
    {
        $payload = $this->safe_json_decode_array($post_data);
        return $this->adapter->request('POST', '/customers', [], [], is_array($payload) ? $payload : []);
    }

    // Assinaturas legadas preservadas; os paths de API agora são oficiais.
    // Todas as categorias são administradas pelo endpoint /v3/webhooks.
    public function get_webhook($api_key, $api_url)
    {
        return $this->adapter->request('GET', '/webhooks', ['limit' => 100]);
    }

    public function create_webhook($api_key, $api_url, $post_data)
    {
        $payload = $this->safe_json_decode_array($post_data);
        if (!is_array($payload)) {
            return ['error' => 'invalid_payload'];
        }
        $token = (string) ($payload['authToken'] ?? $this->getSetting('webhook_secret'));
        $url = (string) ($payload['url'] ?? '');
        $email = (string) ($payload['email'] ?? '');
        $events = $payload['events'] ?? [];
        if (strlen($token) < 32 || strlen($token) > 255 || preg_match('/\\s/', $token)
            || !filter_var($url, FILTER_VALIDATE_URL)
            || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https'
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || !is_array($events) || $events === []) {
            return ['error' => 'invalid_webhook_configuration'];
        }

        $payload['authToken'] = $token;
        $payload['sendType'] = $payload['sendType'] ?? 'SEQUENTIALLY';
        $payload['apiVersion'] = 3;
        $payload['enabled'] = $payload['enabled'] ?? true;
        $payload['name'] = $payload['name'] ?? 'Eventos CRM';
        // Sem exposição de token em URL ou na saída dos logs locais.
        return $this->adapter->request('POST', '/webhooks', [], [], $payload);
    }

    public function get_webhook_invoice($api_key, $api_url)
    {
        return $this->get_webhook($api_key, $api_url);
    }

    public function create_webhook_invoice($api_key, $api_url, $post_data)
    {
        return $this->create_webhook($api_key, $api_url, $post_data);
    }

    public function get_webhook_transfer($api_key, $api_url)
    {
        return $this->get_webhook($api_key, $api_url);
    }

    public function create_webhook_transfer($api_key, $api_url, $post_data)
    {
        return $this->create_webhook($api_key, $api_url, $post_data);
    }

    public function get_customers($api_key, $api_url)
    {
        return $this->adapter->request('GET', '/customers', ['limit' => 100]);
    }

    public function charges($api_key, $api_url, $offset = NULL)
    {
        return json_encode($this->adapter->listPayments(['limit' => 100]));
    }

    public function health_check()
    {
        return $this->adapter->healthStatus();
    }

    public function handle_webhook(array $headers, string $rawBody, ?string $publicId = null): array
    {
        return $this->adapter->handleWebhook($headers, $rawBody, $publicId);
    }

    public function ensure_customer_from_client($client): array
    {
        return $this->adapter->ensureCustomerFromClient($client);
    }

    /**
     * Upsert do vínculo invoice -> asaas payment no banco (tabela asaas_payments_map).
     * Grava status, billing_type, valor, vencimento, payload_cache e last_sync_at se as colunas existirem.
     */
    public function upsert_payment_map(int $invoiceId, $payment, ?string $asaasCustomerId = null): bool
    {
        $CI = &get_instance();
        $table = db_prefix() . "asaas_payments_map";

        if ($invoiceId <= 0) {
            log_message("error", "[Asaas] upsert_payment_map: invoiceId inválido.");
            return false;
        }

        if (!$CI->db->table_exists($table)) {
            log_message("error", "[Asaas] upsert_payment_map: tabela asaas_payments_map não existe.");
            return false;
        }

        // Normaliza payment (array|object)
        if (is_object($payment)) {
            $paymentArr = json_decode(json_encode($payment), true);
        } elseif (is_array($payment)) {
            $paymentArr = $payment;
        } else {
            $paymentArr = [];
        }

        // Payment-map cache is metadata only. A provider response can include a
        // reusable creditCardToken/card holder object even in a hosted flow.
        // Never persist arbitrary nested data or credentials from that response.
        $paymentArr = array_intersect_key(
            array_filter($paymentArr, static fn($value) => is_scalar($value) || $value === null),
            array_flip(['id', 'status', 'value', 'dueDate', 'billingType', 'externalReference', 'customer'])
        );
        $paymentId = $paymentArr["id"] ?? null;
        if (!is_string($paymentId) || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $paymentId)) {
            log_message("error", "[Asaas] upsert_payment_map: payment id vazio para invoice_id=" . $invoiceId);
            return false;
        }

        $origin = $this->getAdapter()->originStore()->byInvoice($invoiceId);
        if ($origin && !empty($origin['remote_id']) && $origin['remote_id'] !== (string) $paymentId) {
            return false;
        }

        $now = date("Y-m-d H:i:s");
        $data = [
            "invoice_id" => $invoiceId,
            "asaas_payment_id" => (string) $paymentId,
        ];

        // Campos opcionais (só grava se existir a coluna)
        if ($asaasCustomerId && $CI->db->field_exists("asaas_customer_id", $table)) {
            $data["asaas_customer_id"] = $asaasCustomerId;
        }

        if ($CI->db->field_exists("billing_type", $table) && isset($paymentArr["billingType"])) {
            $data["billing_type"] = (string) $paymentArr["billingType"];
        }

        if ($CI->db->field_exists("status", $table) && isset($paymentArr["status"])) {
            $data["status"] = (string) $paymentArr["status"];
        }

        if ($CI->db->field_exists("value", $table) && isset($paymentArr["value"])) {
            $data["value"] = (float) $paymentArr["value"];
        }

        if ($CI->db->field_exists("due_date", $table) && isset($paymentArr["dueDate"])) {
            $data["due_date"] = (string) $paymentArr["dueDate"];
        }

        if ($CI->db->field_exists("last_sync_at", $table)) {
            $data["last_sync_at"] = $now;
        }

        if ($CI->db->field_exists("payload_cache", $table)) {
            $data["payload_cache"] = json_encode($paymentArr, JSON_UNESCAPED_UNICODE);
        }

        if ($CI->db->field_exists("updated_at", $table)) {
            $data["updated_at"] = $now;
        }

        $exists = $CI->db->select("id, asaas_payment_id")
            ->from($table)
            ->where("invoice_id", $invoiceId)
            ->limit(1)
            ->get()
            ->row_array();

        if ($exists && isset($exists["id"])) {
            if ((string) $exists['asaas_payment_id'] !== (string) $paymentId) {
                log_message('error', '[Asaas] Vínculo imutável: tentativa de substituir cobrança de invoice_id=' . $invoiceId);
                return false;
            }
            $CI->db->where("id", (int) $exists["id"]);
            $ok = $CI->db->update($table, $data);
            if ($ok === false) {
                $err = $CI->db->error();
                log_message("error", "[Asaas] upsert_payment_map UPDATE não confirmado.");
                return false;
            }
            return true;
        }

        if ($CI->db->field_exists("created_at", $table)) {
            $data["created_at"] = $now;
        }

        $ok = $CI->db->insert($table, $data);
        if ($ok === false) {
            $err = $CI->db->error();
            log_message("error", "[Asaas] upsert_payment_map INSERT não confirmado.");
            return false;
        }

        return true;
    }

    function get_state_abbr()
    {
        $estadosBrasileiros = [
            'AC' => 'Acre',
            'AL' => 'Alagoas',
            'AP' => 'Amapá',
            'AM' => 'Amazonas',
            'BA' => 'Bahia',
            'CE' => 'Ceará',
            'DF' => 'Distrito Federal',
            'ES' => 'Espírito Santo',
            'GO' => 'Goiás',
            'MA' => 'Maranhão',
            'MT' => 'Mato Grosso',
            'MS' => 'Mato Grosso do Sul',
            'MG' => 'Minas Gerais',
            'PA' => 'Pará',
            'PB' => 'Paraíba',
            'PR' => 'Paraná',
            'PE' => 'Pernambuco',
            'PI' => 'Piauí',
            'RJ' => 'Rio de Janeiro',
            'RN' => 'Rio Grande do Norte',
            'RS' => 'Rio Grande do Sul',
            'RO' => 'Rondônia',
            'RR' => 'Roraima',
            'SC' => 'Santa Catarina',
            'SP' => 'São Paulo',
            'SE' => 'Sergipe',
            'TO' => 'Tocantins'
        ];
        return $estadosBrasileiros;
    }

    public function get_customer_customfields($id, $fieldto, $slug)
    {
        $ci = &get_instance();
        $ci->db->where('fieldto', $fieldto);
        $ci->db->where('slug', $slug);
        $customfields = $ci->db->get(db_prefix() . 'customfields')->result();
        foreach ($customfields as $row) {
            $ci->db->where('fieldto', $fieldto);
            $ci->db->where('relid', $id);
            $ci->db->where('fieldid', $row->id);
            $customfieldsvalues = $ci->db->get(db_prefix() . 'customfieldsvalues')->row();
        }
        if (isset($customfieldsvalues)) {
            return $customfieldsvalues->value;
        } else {
            return NULL;
        }
    }
    
    /**
     * Normaliza CPF/CNPJ (apenas dígitos) e valida tamanho 11/14.
     */
    private function normalize_doc(?string $doc): ?string
    {
        $doc = preg_replace('/\D+/', '', (string) $doc);
        if ($doc === '') {
            return null;
        }
        $len = strlen($doc);
        if ($len !== 11 && $len !== 14) {
            return null;
        }
        return $doc;
    }

    private function customerMapClientColumn($db, string $table): string
    {
        return $db->field_exists('client_id', $table) ? 'client_id' : 'perfex_client_id';
    }

    private function customerMapClientValues($db, string $table, int $clientId): array
    {
        $values = [];
        if ($db->field_exists('client_id', $table)) {
            $values['client_id'] = $clientId;
        }
        // Dual-write during the transition so rollback and old integrations retain their mapping.
        if ($db->field_exists('perfex_client_id', $table)) {
            $values['perfex_client_id'] = $clientId;
        }
        return $values;
    }

    /** Busca o ID do cliente no mapa local do Asaas. */
    public function get_customer_map_by_client_id(int $clientId): ?string
    {
        $CI = &get_instance();
        $table = db_prefix() . 'asaas_customers_map';

        if ($clientId <= 0 || !$CI->db->table_exists($table)) {
            return null;
        }

        $row = $CI->db->select('asaas_customer_id')
            ->from($table)
            ->where($this->customerMapClientColumn($CI->db, $table), $clientId)
            ->limit(1)
            ->get()
            ->row_array();

        $id = $row['asaas_customer_id'] ?? null;
        return $id ? (string) $id : null;
    }

    /** @deprecated Preserved for integrations that called the old module method. */
    public function get_customer_map_by_perfex_client_id(int $perfexClientId): ?string
    {
        return $this->get_customer_map_by_client_id($perfexClientId);
    }

    /** Upsert do mapa local de clientes do Asaas. */
    public function upsert_customer_map(int $clientId, string $asaasCustomerId): bool
    {
        $CI = &get_instance();
        $table = db_prefix() . 'asaas_customers_map';

        if ($clientId <= 0 || $asaasCustomerId === '' || !$CI->db->table_exists($table)) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $data = array_merge($this->customerMapClientValues($CI->db, $table, $clientId), [
            'asaas_customer_id' => $asaasCustomerId,
            'updated_at'        => $now,
        ]);

        $exists = $CI->db->select('id')
            ->from($table)
            ->where($this->customerMapClientColumn($CI->db, $table), $clientId)
            ->limit(1)
            ->get()
            ->row_array();

        if (!empty($exists['id'])) {
            $CI->db->where('id', (int) $exists['id']);
            $ok = $CI->db->update($table, $data);
            if (!$ok) {
                log_message('error', '[Asaas] upsert_customer_map UPDATE não confirmado.');
            }
            return (bool) $ok;
        }

        $data['created_at'] = $now;
        $ok = $CI->db->insert($table, $data);
        if (!$ok) {
            log_message('error', '[Asaas] upsert_customer_map INSERT não confirmado.');
        }
        return (bool) $ok;
    }

    /**
     * Garante que existe um Customer no Asaas para o cliente da instalação, com regra estável:
     * 1) Usa MAP local (tblasaas_customers_map) se existir.
     * 2) Se não existir, tenta buscar no Asaas por cpfCnpj (estrito) e depois por email (exato).
     * 3) Se não encontrar, cria Customer no Asaas.
     * 4) Em todos os casos, grava/atualiza MAP local.
     *
     * Retorno: ['ok'=>bool, 'asaas_customer_id'=>string, 'source'=>string, ...]
     */
    public function ensure_customer_for_client(int $clientId, $clientRow): array
    {
        // 1) MAP local
        $mapped = $this->get_customer_map_by_client_id($clientId);
        if (!empty($mapped)) {
            return ['ok' => true, 'asaas_customer_id' => (string) $mapped, 'source' => 'map'];
        }

        // Monta os dados do cliente e do contato principal.
        $name = '';
        if (isset($clientRow->company) && trim((string)$clientRow->company) !== '') {
            $name = (string) $clientRow->company;
        } elseif (isset($clientRow->firstname) && trim((string)$clientRow->firstname) !== '') {
            $name = (string) $clientRow->firstname;
        } else {
            $name = 'Cliente';
        }

        $email = '';
        if (isset($clientRow->email) && trim((string)$clientRow->email) !== '') {
            $email = trim((string) $clientRow->email);
        }

        // CPF/CNPJ pode estar no campo vat, conforme a configuração dos cadastros.
        $doc = null;
        if (isset($clientRow->vat)) {
            $doc = $this->normalize_doc((string)$clientRow->vat);
        }

        // 2) Buscar no Asaas (estrito)
        $found = null;

        if ($doc) {
            $list = $this->adapter->listCustomers(['cpfCnpj' => $doc, 'limit' => 50]);
            $rows = $list['data'] ?? [];
            if (is_array($rows)) {
                foreach ($rows as $c) {
                    if (($c['cpfCnpj'] ?? '') === $doc && !empty($c['id'])) {
                        $found = $c;
                        break;
                    }
                }
            }
        }

        if (!$found && $email !== '') {
            $list = $this->adapter->listCustomers(['email' => $email, 'limit' => 50]);
            $rows = $list['data'] ?? [];
            if (is_array($rows)) {
                foreach ($rows as $c) {
                    $cEmail = (string)($c['email'] ?? '');
                    if ($cEmail !== '' && strcasecmp($cEmail, $email) === 0 && !empty($c['id'])) {
                        $found = $c;
                        break;
                    }
                }
            }
        }

        if ($found && !empty($found['id'])) {
            $asaasCustomerId = (string) $found['id'];
            $this->upsert_customer_map($clientId, $asaasCustomerId);
            return ['ok' => true, 'asaas_customer_id' => $asaasCustomerId, 'source' => 'asaas_search'];
        }

        // 3) Criar customer
        $sendEmail = ((int) $this->getSetting('send_customer_email')) === 1;
        $sendMobile = ((int) $this->getSetting('send_customer_mobile')) === 1;

        $payload = [
            'name' => $name,
        ];

        if ($doc) {
            $payload['cpfCnpj'] = $doc;
        }

        // Endereço (padrão: sempre enviar quando existir)
        if (isset($clientRow->address) && trim((string) $clientRow->address) !== '') {
            $payload['address'] = trim((string) $clientRow->address);
        }
        if (isset($clientRow->numero) && trim((string) $clientRow->numero) !== '') {
            $payload['addressNumber'] = trim((string) $clientRow->numero);
        }
        if (isset($clientRow->complemento) && trim((string) $clientRow->complemento) !== '') {
            $payload['complement'] = trim((string) $clientRow->complemento);
        }
        if (isset($clientRow->bairro) && trim((string) $clientRow->bairro) !== '') {
            $payload['province'] = trim((string) $clientRow->bairro);
        }
        if (isset($clientRow->zip) && trim((string) $clientRow->zip) !== '') {
            $payload['postalCode'] = preg_replace('/[^0-9]/', '', (string) $clientRow->zip);
        }

        // Email / Telefones (somente se habilitado nas flags)
        if ($sendEmail && $email !== '') {
            $payload['email'] = $email;
        }

        if ($sendMobile) {
            if (isset($clientRow->phonenumber) && trim((string)$clientRow->phonenumber) !== '') {
                $payload['phone'] = (string) $clientRow->phonenumber;
            }
            if (isset($clientRow->mobilePhone) && trim((string)$clientRow->mobilePhone) !== '') {
                $payload['mobilePhone'] = (string) $clientRow->mobilePhone;
            } elseif (isset($clientRow->phonenumber) && trim((string)$clientRow->phonenumber) !== '') {
                $payload['mobilePhone'] = (string) $clientRow->phonenumber;
            }
        }

        $created = $this->adapter->createCustomer($payload);
        if (empty($created['id'])) {
            log_message('error', '[Asaas] Cadastro de cliente remoto não confirmado.');
            return ['ok' => false, 'error' => 'createCustomer_failed', 'response' => $created];
        }

        $asaasCustomerId = (string) $created['id'];
        $this->upsert_customer_map($clientId, $asaasCustomerId);

        return ['ok' => true, 'asaas_customer_id' => $asaasCustomerId, 'source' => 'asaas_create'];
    }

    /** @deprecated Preserved for integrations that called the old module method. */
    public function ensure_customer_for_perfex_client(int $perfexClientId, $clientRow): array
    {
        return $this->ensure_customer_for_client($perfexClientId, $clientRow);
    }

    /**
     * Obtém Asaas Payment ID a partir do mapa local pelo invoice_id.
     */
    public function get_payment_id_by_invoice(int $invoiceId): ?string
    {
        $CI = &get_instance();
        $table = db_prefix() . 'asaas_payments_map';

        if ($invoiceId <= 0 || !$CI->db->table_exists($table)) {
            return null;
        }

        $row = $CI->db->select('asaas_payment_id')
            ->from($table)
            ->where('invoice_id', $invoiceId)
            ->limit(1)
            ->get()
            ->row_array();

        $id = $row['asaas_payment_id'] ?? null;
        return $id ? (string) $id : null;
    }

    public function get_payment_map_by_invoice_id(int $invoiceId): ?array
    {
        $CI = &get_instance();
        if (!$CI->db->table_exists(db_prefix() . 'asaas_payments_map')) {
            return null;
        }
        return $CI->db->get_where(db_prefix() . 'asaas_payments_map', ['invoice_id' => $invoiceId])->row_array() ?: null;
    }

    private function safe_json_decode_array($json): array
    {
        if (!is_string($json) || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

}

