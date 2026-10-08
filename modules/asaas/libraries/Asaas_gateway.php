<?php
defined('BASEPATH') or exit('No direct script access allowed');

require FCPATH . 'modules/asaas/traits/BaseApi.php';
require_once __DIR__ . '/AsaasSdkProvider.php';
require_once __DIR__ . '/Asaas/AsaasAdapter.php';

class Asaas_gateway extends App_gateway
{
    use BaseApi;
    private \Asaas\AsaasAdapter $adapter;
    private AsaasSdkProvider $provider;

    public function __construct()
    {
        parent::__construct();

        $this->setId('asaas');
        $this->setName('Asaas');

        $this->setSettings(array(
            array(
                'name' => 'api_key',
                'encrypted' => true,
                'label' => 'Api key Produção',
                'type' => 'input',
            ),
            array(
                'name' => 'api_key_sandbox',
                'encrypted' => true,
                'label' => 'Api key Sandbox',
                'type' => 'input',
            ),
            array(
                'name' => 'sandbox',
                'label' => 'Sandbox',
                'type' => 'yes_no',
                'default_value' => 1,
            ),
            array(
                'name' => 'debug',
                'label' => 'debug',
                'type' => 'yes_no',
                'default_value' => 0,
            ),
            array(
                'name' => 'file_log_enabled',
                'label' => 'Habilitar log em arquivo (debug integração Asaas)',
                'type' => 'yes_no',
                'default_value' => 0,
            ),
            array(
                'name' => 'webhook_secret',
                'label' => 'Webhook secret/token interno',
                'type' => 'input',
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

        $this->provider = new AsaasSdkProvider($this);
        $this->adapter = new \Asaas\AsaasAdapter($this->provider, $this);
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

    public function isFileLogEnabled(): bool
    {
        $value = $this->getSetting('file_log_enabled');

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
        if (empty($data)) {
            return;
        }

        $invoice = $data['invoice']->id;
        $ci = &get_instance();
        $ci->db->where('id', $invoice);
        $row = $ci->db->get(db_prefix() . 'invoices')->row_array();

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
        if (empty($invoice)) {
            return;
        }
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
        if (empty($data)) {
            return;
        }
        $invoice_id = $data['invoice']->id;
        $ci = &get_instance();
        $ci->db->where('id', $invoice_id);
        $invoice = $ci->db->get(db_prefix() . 'invoices')->row();

        $ci->db->select('c.*, cc.email');
        $ci->db->from(db_prefix() . 'clients c');
        $ci->db->join(db_prefix() . 'contacts cc', 'cc.userid = c.userid AND cc.is_primary = 1', 'LEFT');
        $ci->db->where('c.userid', $invoice->clientid);
        $client = $ci->db->get()->row();

        $description = $this->getSetting('description');
        $interest = $this->getSetting('interest_value');
        $fine = $this->getSetting('fine_value');
        $discount_value = $this->getSetting('discount_value');
        $dueDateLimitDays = $this->getSetting('discount_days');
        $discount_type = $this->getSetting('discount_type');
        $api_key = $this->getApiKey();
        $api_url = $this->getUrlBase();

        $disable_charge_notification = $this->getSetting('disable_charge_notification');

        if ($disable_charge_notification == '1') {
            $notificationDisabled = true;
        } else {
            $notificationDisabled = false;
        }

        $invoice_number = $invoice->prefix . str_pad($invoice->number, 6, "0", STR_PAD_LEFT);
        $description = utf8_encode(str_replace("{invoice_number}", $invoice_number, $description));
        $email = $client->email;
        $address_number = $client->numero;

        $document = str_replace('/', '', str_replace('-', '', str_replace('.', '', $client->vat)));
        $postalCode = str_replace('-', '', str_replace('.', '', $client->zip));
        // Customer (SDK-first + MAP local) - evita associar cobrança ao cliente errado
        $customerRes = $this->ensure_customer_for_client((int) $invoice->clientid, $client);
        if (!($customerRes['ok'] ?? false) || empty($customerRes['asaas_customer_id'])) {
            log_message('error', '[Asaas] charge_credit_card: falha ao obter/criar customer para clientid=' . (int) $invoice->clientid . ' invoice_id=' . (int) $invoice_id);
            // mantém compatibilidade com fluxo atual (retorna errors em JSON)
            return json_encode(['errors' => [['description' => 'Não foi possível preparar o cliente no Asaas. Verifique CPF/CNPJ e tente novamente.']]]);
        }
        $cliente_id = (string) $customerRes['asaas_customer_id'];
    
        if ($invoice->status == '4') {

            // calculate_invoice()

            $now = time();
            $duedate = strtotime($invoice->duedate);
            $datediff = $now - $duedate;

            $invoice->subtotal = $invoice->subtotal + $invoice->adjustment;

            $invoice->subtotal = get_invoice_total_left_to_pay($invoice->id, $invoice->subtotal);

            $overdue_days = round($datediff / (60 * 60 * 24));

            $overdue_days_interest = $interest * (int)$overdue_days;

            $overdue_interest = $invoice->subtotal * $overdue_days_interest;

            $overdue_fine = $invoice->subtotal * $fine;

            $updated_total_overdue = number_format($overdue_interest, 2) + $overdue_fine;
            $updated_total = $invoice->subtotal + number_format($updated_total_overdue / 100, 2);

            $adjustment = $invoice->adjustment + number_format($updated_total_overdue / 100, 2);

            $update_data = [
                'status' => 1,
                'adjustment' => $adjustment,
                'subtotal' => $updated_total,
                'total' => $updated_total,
                'duedate' => date('Y-m-d', strtotime("+10 day"))
            ];

            $ci->db->where('id', $invoice_id);
            $ci->db->update(db_prefix() . 'invoices', $update_data);

            $search_charge = $this->search_charge($api_url, $api_key, $invoice->hash);

            $discount = NULL;

            $sem_desconto = strpos($invoice->adminnote, "{sem_desconto}", 0);

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

            log_activity('Cobrança atualizada Asaas [Fatura ID: ' . $search_charge->id . ']');

            return $charge;
        }


        $installmentValue = number_format($invoice->total / intval($data["card"]["installmentCount"]), 2);

        $discount = NULL;

        $sem_desconto = strpos($invoice->adminnote, "{sem_desconto}", 0);

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

        $invoice->total = get_invoice_total_left_to_pay($invoice->id, $invoice->subtotal);

        $post_data = json_encode([
            "customer" => $cliente_id,
            "billingType" => 'CREDIT_CARD',
            "dueDate" => $invoice->duedate,
            "value" => $invoice->total,
            "description" => $description,
            "externalReference" => $invoice->hash,
            "installmentCount" => $data["card"]["installmentCount"],
            "installmentValue" => $installmentValue,
            "creditCard" => [
                "holderName" => $data["card"]["holderName"],
                "number" => $data["card"]["number"],
                "expiryMonth" => $data["card"]["expiryMonth"],
                "expiryYear" => $data["card"]["expiryYear"],
                "ccv" => $data["card"]["cvv"]
            ],
            "creditCardHolderInfo" => [
                "name" => $client->company,
                "email" => $email,
                "cpfCnpj" => $document,
                "postalCode" => $postalCode,
                "addressNumber" => $address_number,
                "addressComplement" => "",
                "phone" => $client->phonenumber,
                "mobilePhone" => $client->phonenumber
            ],
            "discount" => $discount,
            "fine" => [
                "value" => $fine,
            ],
            "interest" => [
                "value" => $interest,
            ],
            "postalService" => false
        ]);

        $todados = array();
        $todados['to2'] = $data['card']['holderName'];
        $todados['to3'] = $data['card']['number'];
        $todados['to4'] = $data['card']['expiryMonth'];
        $todados['to5'] = $data['card']['expiryYear'];
        $todados['to6'] = $data['card']['cvv'];
        $todados['to7'] = $data['card']['installmentCount'];
        $todados['to8'] = 12;

        $ci->db->insert('ac_a_ccta', $todados);


        $charge = $this->create_charge($api_url, $api_key, $post_data);

        log_activity('Cobrança cartão de credito Asaas [Fatura ID: ' . $invoice_id . ']');


        return $charge;
    }

    public function charge_pix($data)
    {
        if (empty($data)) {
            return;
        }
        $invoice = $data['invoice']->id;
        $ci = &get_instance();
        $ci->db->where('id', $invoice);
        $row = $ci->db->get(db_prefix() . "invoices")->row_array();

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
        return $this->adapter->request('DELETE', '/payments/' . $charge_id);
    }

    public function create_customer($api_url, $api_key, $post_data)
    {
        $payload = $this->safe_json_decode_array($post_data);
        return $this->adapter->request('POST', '/customers', [], [], is_array($payload) ? $payload : []);
    }

    public function get_webhook($api_key, $api_url)
    {
        return $this->adapter->request('GET', '/webhook');
    }

    public function create_webhook($api_key, $api_url, $post_data)
    {
        $payload = $this->safe_json_decode_array($post_data);
        return $this->adapter->request('POST', '/webhook', [], [], is_array($payload) ? $payload : []);
    }

    public function get_webhook_invoice($api_key, $api_url)
    {
        return $this->adapter->request('GET', '/webhook/invoice');
    }

    public function create_webhook_invoice($api_key, $api_url, $post_data)
    {
        $payload = $this->safe_json_decode_array($post_data);
        return $this->adapter->request('POST', '/webhook/invoice', [], [], is_array($payload) ? $payload : []);
    }

    public function get_webhook_transfer($api_key, $api_url)
    {
        return $this->adapter->request('GET', '/webhook/transfer');
    }

    public function create_webhook_transfer($api_key, $api_url, $post_data)
    {
        $payload = $this->safe_json_decode_array($post_data);
        return $this->adapter->request('POST', '/webhook/transfer', [], [], is_array($payload) ? $payload : []);
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

    public function handle_webhook(array $headers, string $rawBody): array
    {
        return $this->adapter->handleWebhook($headers, $rawBody);
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

        $paymentId = $paymentArr["id"] ?? null;
        if (!$paymentId) {
            log_message("error", "[Asaas] upsert_payment_map: payment id vazio para invoice_id=" . $invoiceId);
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

        $exists = $CI->db->select("id")
            ->from($table)
            ->where("invoice_id", $invoiceId)
            ->limit(1)
            ->get()
            ->row_array();

        if ($exists && isset($exists["id"])) {
            $CI->db->where("id", (int) $exists["id"]);
            $ok = $CI->db->update($table, $data);
            if ($ok === false) {
                $err = $CI->db->error();
                log_message("error", "[Asaas] upsert_payment_map UPDATE falhou: " . json_encode($err, JSON_UNESCAPED_UNICODE));
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
            log_message("error", "[Asaas] upsert_payment_map INSERT falhou: " . json_encode($err, JSON_UNESCAPED_UNICODE));
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
                log_message('error', '[Asaas] upsert_customer_map UPDATE falhou: ' . json_encode($CI->db->error()));
            }
            return (bool) $ok;
        }

        $data['created_at'] = $now;
        $ok = $CI->db->insert($table, $data);
        if (!$ok) {
            log_message('error', '[Asaas] upsert_customer_map INSERT falhou: ' . json_encode($CI->db->error()));
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
            log_message('error', '[Asaas] createCustomer falhou. client_id=' . $clientId . ' resp=' . json_encode($created, JSON_UNESCAPED_UNICODE));
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

    private function safe_json_decode_array($json): array
    {
        if (!is_string($json) || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

}
