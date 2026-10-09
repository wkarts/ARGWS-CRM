<?php

declare(strict_types=1);

namespace Asaas;

use App_gateway;
use Asaas\Sdk\Exception\ApiException;
use Asaas\Sdk\Exception\TransportException;
use AsaasSdkProvider;

final class AsaasAdapter
{
    private const PREFACES_RESOURCE_PATH = '/prefaces';

    private AsaasSdkProvider $provider;
    private ?App_gateway $gateway;
    private $ci;
    private ?array $logColumns = null;

    public function __construct(AsaasSdkProvider $provider, ?App_gateway $gateway = null)
    {
        $this->provider = $provider;
        $this->gateway = $gateway;
        $this->ci = &get_instance();
    }

    /**
     * Normaliza o path para evitar que o Guzzle "engula" o /v3 do base_uri da SDK.
     *
     * A SDK define base_uri como https://api(-sandbox).asaas.com/v3 (sem barra final).
     * No Guzzle, quando base_uri termina sem '/', o último segmento ('v3') é tratado como arquivo.
     * Então request('customers') vira https://.../customers (perde o v3).
     *
     * Como a SDK é a fonte da verdade e não deve ser alterada aqui, o módulo deve
     * enviar path sempre como "v3/<recurso>", sem barra inicial.
     */
    /**
 * Normaliza o path para garantir que a SDK (base_uri .../v3 sem barra final)
 * gere URL final correta no Guzzle.
 *
 * Regras:
 * - remove barra inicial ("/customers" -> "customers")
 * - garante prefixo "v3/" sem duplicar ("customers" -> "v3/customers")
 */
private function normalizePath(string $path): string
{
    // remove barra inicial (evita path absoluto)
    $path = ltrim($path, '/');

    // garante prefixo v3/ sem duplicar
    if ($path === 'v3') {
        return 'v3/';
    }

    if (!str_starts_with($path, 'v3/')) {
        $path = 'v3/' . $path;
    }

    return $path;
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
        $client = $this->provider->client($companyId);
        // A SDK usa base_uri com /v3. Quando o path começa com '/', o Guzzle zera o path do base_uri.
        // Portanto, no módulo, sempre enviamos path relativo (sem '/').
        $originalPath = $path;
        $path = $this->normalizePath($path);
        $correlationId = $headers['X-Correlation-Id'] ?? null;
        $headers = $this->provider->headers($headers, $correlationId);

        try {
            $response = $client->request($method, $path, $query, $headers, $payload, false, $expectsBinary);
            $this->log('info', 'Asaas request concluída.', [
                'method' => $method,
                'path' => $path,
                'original_path' => $originalPath,
                'query' => $query,
                'correlation_id' => $headers['X-Correlation-Id'] ?? null,
            ]);

            return is_array($response) ? $response : [];
        } catch (ApiException $exception) {
            $this->log('error', 'Erro na API Asaas.', [
                'method' => $method,
                'path' => $path,
                'original_path' => $originalPath,
                'query' => $query,
                'status' => $exception->getStatusCode(),
                'message' => $exception->getMessage(),
                'correlation_id' => $headers['X-Correlation-Id'] ?? null,
            ]);

            return [];
        } catch (TransportException $exception) {
            $this->log('error', 'Erro de transporte na API Asaas.', [
                'method' => $method,
                'path' => $path,
                'original_path' => $originalPath,
                'query' => $query,
                'message' => $exception->getMessage(),
                'correlation_id' => $headers['X-Correlation-Id'] ?? null,
            ]);

            return [];
        }
    }

    /**
     * Executa requisição com envelope padronizado para novos módulos sem quebrar legado.
     *
     * @return array{success: bool, data: array|null, error: array|null, meta: array}
     */
    public function requestStructured(
        string $method,
        string $path,
        array $query = [],
        array $headers = [],
        ?array $payload = null,
        bool $expectsBinary = false,
        ?int $companyId = null
    ): array {
        $client = $this->provider->client($companyId);
        // A SDK usa base_uri com /v3. Quando o path começa com '/', o Guzzle zera o path do base_uri.
        // Portanto, no módulo, sempre enviamos path relativo (sem '/').
        $originalPath = $path;
        $path = $this->normalizePath($path);
        $correlationId = $headers['X-Correlation-Id'] ?? null;
        $headers = $this->provider->headers($headers, $correlationId);

        try {
            $response = $client->request($method, $path, $query, $headers, $payload, false, $expectsBinary);

            return [
                'success' => true,
                'data' => is_array($response) ? $response : [],
                'error' => null,
                'meta' => [
                    'method' => $method,
                    'path' => $path,
                    'original_path' => $originalPath,
                    'correlation_id' => $headers['X-Correlation-Id'] ?? null,
                ],
            ];
        } catch (ApiException $exception) {
            return [
                'success' => false,
                'data' => null,
                'error' => [
                    'type' => 'api_error',
                    'status' => $exception->getStatusCode(),
                    'message' => $exception->getMessage(),
                ],
                'meta' => [
                    'method' => $method,
                    'path' => $path,
                    'original_path' => $originalPath,
                    'correlation_id' => $headers['X-Correlation-Id'] ?? null,
                ],
            ];
        } catch (TransportException $exception) {
            return [
                'success' => false,
                'data' => null,
                'error' => [
                    'type' => 'transport_error',
                    'status' => null,
                    'message' => $exception->getMessage(),
                ],
                'meta' => [
                    'method' => $method,
                    'path' => $path,
                    'original_path' => $originalPath,
                    'correlation_id' => $headers['X-Correlation-Id'] ?? null,
                ],
            ];
        }
    }

    /**
     * @return array{success: bool, data: array|null, error: array|null, meta: array}
     */
    public function createPreface(array $payload, array $headers = [], ?int $companyId = null): array
    {
        if ($payload === []) {
            return $this->validationError('Payload do Prefaces é obrigatório.');
        }

        return $this->requestStructured('POST', self::PREFACES_RESOURCE_PATH, [], $headers, $payload, false, $companyId);
    }

    /**
     * @return array{success: bool, data: array|null, error: array|null, meta: array}
     */
    public function getPreface(string $prefaceId, array $headers = [], ?int $companyId = null): array
    {
        if (trim($prefaceId) === '') {
            return $this->validationError('prefaceId é obrigatório.');
        }

        return $this->requestStructured('GET', self::PREFACES_RESOURCE_PATH . '/' . $prefaceId, [], $headers, null, false, $companyId);
    }

    /**
     * @return array{success: bool, data: array|null, error: array|null, meta: array}
     */
    public function listPrefaces(array $query = [], array $headers = [], ?int $companyId = null): array
    {
        return $this->requestStructured('GET', self::PREFACES_RESOURCE_PATH, $query, $headers, null, false, $companyId);
    }

    /**
     * @return array{success: bool, data: array|null, error: array|null, meta: array}
     */
    public function updatePreface(string $prefaceId, array $payload, string $method = 'PUT', array $headers = [], ?int $companyId = null): array
    {
        $method = strtoupper($method);
        if (!in_array($method, ['PUT', 'PATCH'], true)) {
            return $this->validationError('Método de atualização inválido. Use PUT ou PATCH.');
        }

        if (trim($prefaceId) === '') {
            return $this->validationError('prefaceId é obrigatório.');
        }

        if ($payload === []) {
            return $this->validationError('Payload do Prefaces é obrigatório.');
        }

        return $this->requestStructured($method, self::PREFACES_RESOURCE_PATH . '/' . $prefaceId, [], $headers, $payload, false, $companyId);
    }

    /**
     * @return array{success: bool, data: array|null, error: array|null, meta: array}
     */
    public function deletePreface(string $prefaceId, array $headers = [], ?int $companyId = null): array
    {
        if (trim($prefaceId) === '') {
            return $this->validationError('prefaceId é obrigatório.');
        }

        return $this->requestStructured('DELETE', self::PREFACES_RESOURCE_PATH . '/' . $prefaceId, [], $headers, null, false, $companyId);
    }

    /**
     * @return array{success: bool, data: array|null, error: array{type: string, status: int|null, message: string}, meta: array}
     */
    private function validationError(string $message): array
    {
        return [
            'success' => false,
            'data' => null,
            'error' => [
                'type' => 'validation_error',
                'status' => null,
                'message' => $message,
            ],
            'meta' => [],
        ];
    }

    /**
     * @param array|object $crmClient
     * @return array{customerId: string|null, created: bool, updated: bool, raw: array}
     */
    public function ensureCustomerFromClient($crmClient): array
    {
        $client = $this->normalizeClient($crmClient);
        $clientId = $client['userid'] ?? $client['id'] ?? null;

        if (!$clientId) {
            return [
                'customerId' => null,
                'created' => false,
                'updated' => false,
                'raw' => ['error' => 'Cliente inválido.'],
            ];
        }

        $mapTable = db_prefix() . 'asaas_customers_map';
        if ($this->ci->db->table_exists($mapTable)) {
            $clientColumn = $this->customerMapClientColumn($mapTable);
            $existing = $this->ci->db->get_where($mapTable, [$clientColumn => $clientId])->row_array();
            if ($existing && !empty($existing['asaas_customer_id'])) {
                return [
                    'customerId' => $existing['asaas_customer_id'],
                    'created' => false,
                    'updated' => false,
                    'raw' => $existing,
                ];
            }
        }

        $document = $this->normalizeDocument($client['vat'] ?? '');
        $response = $this->request('GET', '/customers', ['cpfCnpj' => $document]);
        $customerId = null;
        $created = false;
        $updated = false;

        if (!empty($response['data'][0]['id'])) {
            $customerId = $response['data'][0]['id'];
        } else {
            $payload = [
                'name' => $client['company'] ?? $client['name'] ?? 'Cliente',
                'email' => $client['email'] ?? '',
                'cpfCnpj' => $document,
                'postalCode' => $this->normalizePostalCode($client['zip'] ?? ''),
                'address' => $client['address'] ?? '',
                'addressNumber' => $client['numero'] ?? '',
                'complement' => $client['complemento'] ?? '',
                'phone' => $client['phonenumber'] ?? '',
                'mobilePhone' => $client['phonenumber'] ?? '',
            ];

            $createdResponse = $this->request('POST', '/customers', [], [], $payload);
            $customerId = $createdResponse['id'] ?? null;
            $created = $customerId !== null;
        }

        if ($customerId && $this->ci->db->table_exists($mapTable)) {
            $now = date('Y-m-d H:i:s');
            $this->ci->db->replace($mapTable, array_merge($this->customerMapClientValues($mapTable, $clientId), [
                'asaas_customer_id' => $customerId,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        if ($customerId && $this->ci->db->field_exists('asaas_customer_id', db_prefix() . 'clients')) {
            $this->ci->db->where('userid', $clientId);
            $this->ci->db->update(db_prefix() . 'clients', ['asaas_customer_id' => $customerId]);
            $updated = true;
        }

        return [
            'customerId' => $customerId,
            'created' => $created,
            'updated' => $updated,
            'raw' => $response,
        ];
    }

    private function customerMapClientColumn(string $table): string
    {
        return $this->ci->db->field_exists('client_id', $table) ? 'client_id' : 'perfex_client_id';
    }

    private function customerMapClientValues(string $table, int $clientId): array
    {
        $values = [];
        if ($this->ci->db->field_exists('client_id', $table)) {
            $values['client_id'] = $clientId;
        }
        if ($this->ci->db->field_exists('perfex_client_id', $table)) {
            $values['perfex_client_id'] = $clientId;
        }
        return $values;
    }

    
    public function listCustomers(array $query = []): array
    {
        return $this->request('GET', '/customers', $query);
    }

    public function createCustomer(array $payload): array
    {
        return $this->request('POST', '/customers', [], [], $payload);
    }

public function getCustomer(string $asaasCustomerId): array
    {
        return $this->request('GET', '/customers/' . $asaasCustomerId);
    }

    public function updateCustomer(string $asaasCustomerId, array $payload): array
    {
        return $this->request('POST', '/customers/' . $asaasCustomerId, [], [], $payload);
    }

    public function createPayment(array $payload): array
    {
        return $this->request('POST', '/payments', [], [], $payload);
    }

    public function getPayment(string $paymentId): array
    {
        return $this->request('GET', '/payments/' . $paymentId);
    }

    public function listPayments(array $query): array
    {
        return $this->request('GET', '/payments', $query);
    }

    public function updatePayment(string $paymentId, array $payload): array
    {
        return $this->request('POST', '/payments/' . $paymentId, [], [], $payload);
    }

    public function cancelPayment(string $paymentId): array
    {
        return $this->request('POST', '/payments/' . $paymentId . '/cancel');
    }

    
    public function confirmCashReceipt(string $paymentId, array $payload = []): array
    {
        // Confirma recebimento em dinheiro (manual/cash) - /v3/payments/{id}/receiveInCash
        // Payload opcional: paymentDate (Y-m-d), value (float), notifyCustomer (bool)
        return $this->request('POST', '/payments/' . $paymentId . '/receiveInCash', [], [], $payload);
    }

public function refundPayment(string $paymentId, array $payload = []): array
    {
        return [
            'error' => 'refund_not_supported',
            'message' => 'Estorno não suportado pela SDK argws neste momento.',
            'paymentId' => $paymentId,
            'payload' => $payload,
        ];
    }

    public function getPixInfo(string $paymentId): array
    {
        return $this->request('GET', '/payments/' . $paymentId . '/pixQrCode');
    }

    public function getBoletoInfo(string $paymentId): array
    {
        $payment = $this->getPayment($paymentId);

        return [
            'linhaDigitavel' => $payment['identificationField'] ?? null,
            'invoiceUrl' => $payment['invoiceUrl'] ?? null,
            'bankSlipUrl' => $payment['bankSlipUrl'] ?? null,
            'raw' => $payment,
        ];
    }

    /**
     * @return array{processed: bool, duplicated: bool, message: string}
     */
    public function handleWebhook(array $headers, string $rawBody): array
    {
        require_once __DIR__ . '/WebhookSecurity.php';

        $validated = WebhookSecurity::validate(
            (string) $this->provider->setting('webhook_secret'),
            $headers,
            $rawBody
        );
        if (!$validated['ok']) {
            return $this->webhookResult(false, false, $validated['message'], $validated['status']);
        }

        $eventId = $validated['event_id'];
        $payload = $validated['payload'];
        $payment = isset($payload['payment']) && is_array($payload['payment']) ? $payload['payment'] : [];
        $paymentId = isset($payment['id']) && is_string($payment['id']) ? $payment['id'] : null;
        $table = db_prefix() . 'asaas_webhook_events';

        if (!$this->ci->db->table_exists($table)) {
            return $this->webhookResult(false, false, 'Histórico de eventos não inicializado.', 503);
        }

        $receivedAt = date('Y-m-d H:i:s');
        $stored = $this->ci->db->query(
            'INSERT IGNORE INTO ' . $this->ci->db->protect_identifiers($table, true)
                . ' (event_id, event_type, asaas_payment_id, received_at, payload, process_status) '
                . 'VALUES (?, ?, ?, ?, ?, ?)',
            [$eventId, $validated['event'], $paymentId, $receivedAt, $rawBody, 'received']
        );
        if ($stored === false) {
            return $this->webhookResult(false, false, 'Não foi possível armazenar o evento.', 503);
        }

        // Apenas confirmamos recebimento após persistir de modo durável.
        // A conciliação financeira é executada pelo CRON, sem bloquear a
        // conexão do Asaas ou acionar reentregas por operações demoradas.
        return $this->webhookResult(true, $this->ci->db->affected_rows() === 0,
            'Evento armazenado.', 200);
    }

    /**
     * Executa conciliação em pequenos lotes, sem workers adicionais.
     * O CRON pode retomar registros recebidos/falhos após reinicializações.
     * @return array{processed: int, failed: int, pending: int}
     */
    public function processPendingWebhookEvents(int $limit = 20): array
    {
        $table = db_prefix() . 'asaas_webhook_events';
        if (!$this->ci->db->table_exists($table)) {
            return ['processed' => 0, 'failed' => 0, 'pending' => 0];
        }
        $limit = max(1, min($limit, 50));
        $pending = $this->ci->db->select('event_id')
            ->from($table)
            ->where_in('process_status', ['received', 'failed'])
            ->order_by('id', 'ASC')
            ->limit($limit)
            ->get()->result_array();
        $processed = 0;
        $failed = 0;
        foreach ($pending as $event) {
            $result = $this->processStoredWebhook((string) $event['event_id']);
            if ($result['processed']) {
                $processed++;
            } else {
                $failed++;
            }
        }
        return ['processed' => $processed, 'failed' => $failed, 'pending' => count($pending)];
    }

    /**
     * Reprocessamento seguro também para entregas "at least once" do Asaas.
     * Usamos GET_LOCK para serializar eventos que alteram o mesmo pagamento.
     * O contrato legado (processed/duplicated/message) permanece inalterado.
     */
    private function processStoredWebhook(string $eventId): array
    {
        $db = $this->ci->db;
        $table = db_prefix() . 'asaas_webhook_events';
        $row = $db->get_where($table, ['event_id' => $eventId])->row_array();
        if (!$row) {
            return $this->webhookResult(false, false, 'Evento ainda não está disponível.', 503);
        }
        if (in_array($row['process_status'], ['processed', 'ignored', 'review_required'], true)) {
            return $this->webhookResult(true, true, 'Evento já recebido.', 200);
        }

        $paymentLockId = trim((string) ($row['asaas_payment_id'] ?? ''));
        $lockKey = 'crm_asaas_' . substr(hash('sha256', $paymentLockId !== '' ? $paymentLockId : $eventId), 0, 40);
        $lockRow = $db->query('SELECT GET_LOCK(?, 3) AS acquired', [$lockKey]);
        if (!$lockRow || (int) ($lockRow->row_array()['acquired'] ?? 0) !== 1) {
            return $this->webhookResult(false, false, 'Evento aguardando processamento.', 503);
        }

        try {
            $db->trans_begin();
            $current = $db->query(
                'SELECT * FROM ' . $db->protect_identifiers($table, true) . ' WHERE event_id = ? FOR UPDATE',
                [$eventId]
            )->row_array();
            if (!$current) {
                $db->trans_rollback();
                return $this->webhookResult(false, false, 'Evento não encontrado.', 503);
            }
            if (in_array($current['process_status'], ['processed', 'ignored', 'review_required'], true)) {
                $db->trans_commit();
                return $this->webhookResult(true, true, 'Evento já processado.', 200);
            }

            $payload = json_decode((string) $current['payload'], true);
            if (!is_array($payload)) {
                throw new \RuntimeException('Payload persistido inválido.');
            }

            $result = $this->reconcileWebhookEvent($payload);
            $db->where('event_id', $eventId)->update($table, [
                'process_status' => $result,
                'processed_at' => date('Y-m-d H:i:s'),
                'error_message' => $result === 'review_required' ? 'Conferência financeira manual necessária.' : null,
            ]);
            if ($db->trans_status() === false) {
                throw new \RuntimeException('A transação de processamento falhou.');
            }
            $db->trans_commit();

            if ($result === 'review_required') {
                $this->log('error', 'Evento Asaas requer conferência financeira.', [
                    'event_id' => $eventId,
                    'event_type' => $current['event_type'] ?? null,
                ]);
            }

            return $this->webhookResult(true, false, 'Evento recebido e registrado.', 200);
        } catch (\Throwable $error) {
            $db->trans_rollback();
            // Reentrega do mesmo ID pode voltar a tentar, sem registrar baixa dupla.
            $db->where('event_id', $eventId)->update($table, [
                'process_status' => 'failed',
                'error_message' => mb_substr($error->getMessage(), 0, 1000),
            ]);
            $this->log('error', 'Não foi possível conciliar evento Asaas.', [
                'event_id' => $eventId, 'error_type' => get_class($error),
            ]);
            return $this->webhookResult(false, false, 'Falha temporária no processamento.', 503);
        } finally {
            $db->query('SELECT RELEASE_LOCK(?)', [$lockKey]);
        }
    }

    /**
     * Nunca gerar baixa a partir de um ID de cobrança não vinculado ao CRM.
     * Eventos de estorno, chargeback e divergências são guardados para
     * reconciliação manual, sem apagar pagamentos históricos.
     */
    private function reconcileWebhookEvent(array $payload): string
    {
        $event = (string) ($payload['event'] ?? '');
        if (!str_starts_with($event, 'PAYMENT_')) {
            return 'ignored';
        }

        $payment = isset($payload['payment']) && is_array($payload['payment']) ? $payload['payment'] : [];
        $paymentId = trim((string) ($payment['id'] ?? ''));
        if ($paymentId === '') {
            return 'review_required';
        }

        // Asaas emite PAYMENT_CONFIRMED antes da disponibilização do saldo.
        // Os dois eventos podem ser recebidos; a transactionid elimina a
        // duplicação de baixas caso a confirmação já tenha sido contabilizada.
        $settledEvents = ['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED'];
        $reviewEvents = [
            'PAYMENT_REFUNDED', 'PAYMENT_PARTIALLY_REFUNDED',
            'PAYMENT_REFUND_IN_PROGRESS', 'PAYMENT_RECEIVED_IN_CASH_UNDONE',
            'PAYMENT_CHARGEBACK_REQUESTED', 'PAYMENT_CHARGEBACK_DISPUTE',
        ];

        $mapTable = db_prefix() . 'asaas_payments_map';
        $mapped = null;
        if ($this->ci->db->table_exists($mapTable)) {
            $mapped = $this->ci->db->get_where($mapTable, ['asaas_payment_id' => $paymentId])->row_array();
        }

        $externalReference = trim((string) ($payment['externalReference'] ?? ''));
        $invoice = null;
        if ($externalReference !== '') {
            $invoice = $this->ci->db->get_where(db_prefix() . 'invoices',
                ['hash' => $externalReference])->row();
        }
        if (!$invoice && $mapped && (int) ($mapped['invoice_id'] ?? 0) > 0) {
            $invoice = $this->ci->db->get_where(db_prefix() . 'invoices',
                ['id' => (int) $mapped['invoice_id']])->row();
        }

        if ($invoice && $mapped && (int) $mapped['invoice_id'] !== (int) $invoice->id) {
            return 'review_required';
        }
        if (!$invoice) {
            // Pode se tratar de conta/cobrança não vinculada a este CRM.
            return 'review_required';
        }

        if (in_array($event, $reviewEvents, true)) {
            return 'review_required';
        }

        if (!in_array($event, $settledEvents, true)) {
            return 'processed';
        }

        if (!in_array((string) ($payment['status'] ?? ''), ['CONFIRMED', 'RECEIVED', 'RECEIVED_IN_CASH'], true)) {
            return 'review_required';
        }

        $amount = (float) ($payment['value'] ?? 0);
        if ($amount <= 0 || abs($amount - (float) $invoice->total) > 0.01) {
            // Desconto, acréscimo e parcial exigem conferência; a cobrança
            // não pode quitar o valor total de uma fatura por aproximação.
            return 'review_required';
        }

        $records = db_prefix() . 'invoicepaymentrecords';
        $registered = $this->ci->db->get_where($records, ['transactionid' => $paymentId])->row_array();
        if ($registered) {
            return (int) ($registered['invoiceid'] ?? 0) === (int) $invoice->id
                ? 'processed' : 'review_required';
        }

        if ((int) $invoice->status === 2) {
            // Fatura já foi quitada por outro lançamento; não criar outra baixa.
            return 'review_required';
        }
        if (!$this->gateway || !$this->gateway->addPayment([
            'amount' => (float) $invoice->total,
            'invoiceid' => (int) $invoice->id,
            'paymentmethod' => (string) ($payment['billingType'] ?? 'Asaas'),
            'transactionid' => $paymentId,
        ])) {
            throw new \RuntimeException('O lançamento do pagamento não foi confirmado.');
        }

        if ($this->gateway && method_exists($this->gateway, 'upsert_payment_map')) {
            if (!$this->gateway->upsert_payment_map((int) $invoice->id, $payment)) {
                throw new \RuntimeException('Não foi possível sincronizar o vínculo da cobrança.');
            }
        }

        return 'processed';
    }

    private function webhookResult(bool $processed, bool $duplicated, string $message, int $status): array
    {
        return [
            'processed' => $processed,
            'duplicated' => $duplicated,
            'message' => $message,
            'http_status' => $status,
        ];
    }

    public function healthStatus(): array
    {
        return $this->provider->healthCheck();
    }

    private function normalizeClient($crmClient): array
    {
        if (is_array($crmClient)) {
            return $crmClient;
        }

        if (is_object($crmClient)) {
            return get_object_vars($crmClient);
        }

        return [];
    }

    private function normalizeDocument(string $document): string
    {
        return preg_replace('/[^0-9]/', '', $document);
    }

    private function normalizePostalCode(string $postalCode): string
    {
        return preg_replace('/[^0-9]/', '', $postalCode);
    }

    private function log(string $level, string $message, array $context = []): void
    {
        $sanitized = $this->sanitizeContext($context);
        $sanitizedContext = json_encode($sanitized, JSON_UNESCAPED_UNICODE);

        if ($level === 'error') {
            log_message('error', '[Asaas] ' . $message . ' | context=' . $sanitizedContext);
        } elseif ($this->shouldWriteFileLog()) {
            log_message('info', '[Asaas] ' . $message . ' | context=' . $sanitizedContext);
        }

        $table = db_prefix() . 'asaas_logs';
        if (!$this->ci->db->table_exists($table)) {
            return;
        }

        $payload = [
            'level' => $level,
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $columns = $this->getLogColumns($table);

        if (in_array('context_json', $columns, true)) {
            $payload['context_json'] = $sanitizedContext;
        } elseif (in_array('context', $columns, true)) {
            $payload['context'] = $sanitizedContext;
        }

        if (in_array('correlation_id', $columns, true)) {
            $payload['correlation_id'] = $sanitized['correlation_id'] ?? null;
        }

        $inserted = $this->ci->db->insert($table, $payload);
        if ($inserted === false) {
            $error = $this->ci->db->error();
            log_message('error', '[Asaas] Falha ao persistir log em banco: ' . json_encode($error, JSON_UNESCAPED_UNICODE));
        }
    }


    private function shouldWriteFileLog(): bool
    {
        if ($this->gateway === null || !method_exists($this->gateway, 'isFileLogEnabled')) {
            return false;
        }

        return (bool) $this->gateway->isFileLogEnabled();
    }

    private function sanitizeContext(array $context): array
    {
        $sensitiveKeys = ['apiKey', 'api_key', 'access_token', 'authorization', 'token'];

        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $context[$key] = $this->sanitizeContext($value);
                continue;
            }

            $normalizedKey = strtolower((string) $key);
            foreach ($sensitiveKeys as $sensitiveKey) {
                if ($normalizedKey === strtolower($sensitiveKey)) {
                    $context[$key] = '[REDACTED]';
                    break;
                }
            }
        }

        return $context;
    }

    private function getLogColumns(string $table): array
    {
        if ($this->logColumns !== null) {
            return $this->logColumns;
        }

        $columns = [];
        $query = $this->ci->db->query('SHOW COLUMNS FROM `' . $table . '`');
        if ($query) {
            foreach ($query->result_array() as $row) {
                if (!empty($row['Field'])) {
                    $columns[] = $row['Field'];
                }
            }
        }

        $this->logColumns = $columns;

        return $columns;
    }
    public function deletePayment(string $paymentId): array
    {
        return $this->request('DELETE', '/payments/' . $paymentId);
    }
}
