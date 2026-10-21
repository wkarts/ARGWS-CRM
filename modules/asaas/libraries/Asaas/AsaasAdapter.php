<?php

declare(strict_types=1);

namespace Asaas;

use App_gateway;
use Asaas\Sdk\Exception\ApiException;
use Asaas\Sdk\Exception\TransportException;
use AsaasSdkProvider;

require_once __DIR__ . '/PaymentOriginStore.php';
require_once __DIR__ . '/EnvironmentSettings.php';
require_once __DIR__ . '/CustomerContactPolicy.php';
require_once __DIR__ . '/InvoicePaymentRemoval.php';
require_once __DIR__ . '/WebhookTransactionGuard.php';

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
        [$client, $requestEnvironment] = $this->resolveRequestClient($method, $path, $query, $companyId);
        [$path, $query, $payload] = $this->applyCustomerContactPolicy($method, $path, $query, $payload, $requestEnvironment, $companyId);
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
                'correlation_id' => $headers['X-Correlation-Id'] ?? null,
            ], $requestEnvironment);

            if (!is_array($response)) {
                throw new \RuntimeException('invalid_asaas_response');
            }
            return $response;
        } catch (ApiException $exception) {
            $this->log('error', 'Erro na API Asaas.', [
                'method' => $method,
                'path' => $path,
                'status' => $exception->getStatusCode(),
                'correlation_id' => $headers['X-Correlation-Id'] ?? null,
            ], $requestEnvironment);

            throw $exception;
        } catch (TransportException $exception) {
            $this->log('error', 'Erro de transporte na API Asaas.', [
                'method' => $method,
                'path' => $path,
                'correlation_id' => $headers['X-Correlation-Id'] ?? null,
            ], $requestEnvironment);

            throw $exception;
        }
    }

    private function applyCustomerContactPolicy(string $method, string $path, array $query, ?array $payload, string $environment, ?int $companyId): array
    {
        if (!CustomerContactPolicy::isCustomerPath($path)) return [$path, $query, $payload];
        $setting = function (string $name) use ($environment, $companyId) {
            // Never inherit legacy preferences into an explicitly selected company.
            if ($companyId === null && $this->gateway !== null && method_exists($this->gateway, 'getSettingForEnvironment')) {
                return $this->gateway->getSettingForEnvironment($name, $environment);
            }
            return $this->provider->setting($name, $companyId);
        };
        return CustomerContactPolicy::apply($method, $path, $query, $payload,
            (string) $setting('send_customer_email') === '1',
            (string) $setting('send_customer_mobile') === '1',
            (string) $setting('disable_charge_notification') === '1');
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
        [$path, $query, $payload] = $this->applyCustomerContactPolicy($method, $path, $query, $payload, $this->provider->env($companyId), $companyId);
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
                    'message' => 'Falha de comunicação com o Asaas.',
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
                    'message' => 'Falha de comunicação com o Asaas.',
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

        $document = $this->normalizeIdentityDocument((string) ($client['vat'] ?? ''));
        if ($document === null) throw new \RuntimeException('customer_document_review_required');
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
        return $this->createInvoicePayment($payload);
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
        return $this->deletePayment($paymentId);
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

    public function webhookIntegrations(): WebhookIntegrationStore
    {
        require_once __DIR__ . '/WebhookIntegrationStore.php';
        return new WebhookIntegrationStore($this->ci->db);
    }

    public function registerWebhookIntegration(string $label, string $environment, string $expectedAccount): array
    {
        require_once __DIR__ . '/WebhookSecurity.php';
        ModuleReadiness::require154($this->ci->db, db_prefix());
        $client = $this->provider->clientForEnvironment($environment);
        $scope = $this->provider->contextForClient($client, $environment);
        if (!hash_equals($expectedAccount, $scope['account_id'])) throw new \RuntimeException('webhook_account_mismatch');
        return $this->webhookIntegrations()->createVerified($label, $scope);
    }

    /** Explicit administrative retry; never infer a historical origin. */
    public function retryReviewedWebhook(int $eventRowId, int $integrationId): void
    {
        $debug=$this->ci->db->db_debug;$this->ci->db->db_debug=false;
        try { $this->retryReviewedWebhookChecked($eventRowId,$integrationId); }
        finally { $this->ci->db->db_debug=$debug; }
    }

    private function retryReviewedWebhookChecked(int $eventRowId, int $integrationId): void
    {
        $db=$this->ci->db;
        $integration=$this->webhookIntegrations()->byId($integrationId);
        if (!$integration || (int)$integration['enabled']!==1 || ($integration['issuer_id']??null)!==null) throw new \RuntimeException('invalid_review_scope');
        $table=db_prefix().'asaas_webhook_events';
        WebhookTransactionGuard::prepare($db);
        $this->assertWebhookTransactionalStorage();
        WebhookTransactionGuard::prepare($db);
        if (!$db->trans_begin()) throw new \RuntimeException('transaction_unavailable');
        try {
            $row=$db->query('SELECT * FROM '.$db->protect_identifiers($table,true).' WHERE id = ? FOR UPDATE',[$eventRowId])->row_array();
            if (!$row || !in_array($row['process_status'],['review_required','failed'],true)
                || (!empty($row['integration_id']) && (int)$row['integration_id']!==$integrationId)) throw new \RuntimeException('event_review_not_allowed');
            $origin=$this->originStore()->byRemoteScope((string)$row['asaas_payment_id'],$integration);
            if (!$origin || ($origin['issuer_id']??null)!==null || $origin['provider']!=='asaas'
                || $origin['account_id']!==$integration['account_id'] || $origin['environment']!==$integration['environment']
                || $origin['state']!=='issued') throw new \RuntimeException('origin_reconciliation_required');
            $this->webhookIntegrations()->audit($integrationId,'event_reviewed',$eventRowId);
            if (!$db->where('id',$eventRowId)->update($table,[
                'integration_id'=>$integrationId,'reviewed_by'=>get_staff_user_id()?:null,
                'reviewed_at'=>date('Y-m-d H:i:s'),'process_status'=>'received',
                'attempt_count'=>0,'next_attempt_at'=>null,'error_message'=>null,
            ]) || !$db->trans_status() || !$db->trans_commit()) throw new \RuntimeException('review_not_persisted');
        } catch (\Throwable $error) { $db->trans_rollback();throw $error; }
    }

    /** Receipt is authenticated and durable; financial reconciliation runs on CRON. */
    public function handleWebhook(array $headers, string $rawBody, ?string $publicId = null): array
    {
        $debug=$this->ci->db->db_debug;
        $this->ci->db->db_debug=false;
        try {
            WebhookTransactionGuard::prepare($this->ci->db);
            return $this->receiveWebhookChecked($headers,$rawBody,$publicId);
        }
        finally { $this->ci->db->db_debug=$debug; }
    }

    private function receiveWebhookChecked(array $headers, string $rawBody, ?string $publicId): array
    {
        require_once __DIR__ . '/WebhookSecurity.php';
        $store = $this->webhookIntegrations();
        if (!$store->ready()) return $this->webhookResult(false, false, 'Webhook indisponível.', 503);
        $integration = $publicId === null ? null : $store->byPublicId($publicId);
        if ($publicId !== null && (!$integration || (int)$integration['enabled'] !== 1)) {
            return $this->webhookResult(false, false, 'Não autorizado.', 401);
        }
        $validated = $integration
            ? WebhookSecurity::validateDigest((string)$integration['token_hash'], $headers, $rawBody)
            : WebhookSecurity::validate((string)$this->provider->setting('webhook_secret'), $headers, $rawBody);
        if (!$validated['ok']) {
            if ($integration) $store->recordRejected((int)$integration['id']);
            return $this->webhookResult(false, false, $validated['message'], $validated['status']);
        }
        $db = $this->ci->db;
        $table = db_prefix().'asaas_webhook_events';
        $scopeKey = $integration ? 'integration:'.$integration['id'] : 'legacy';
        $debug = $db->db_debug;
        $db->db_debug = false;
        $owned = false;
        try {
            foreach ([$table,db_prefix().'asaas_webhook_integrations'] as $participant) {
                $engine=$db->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',[$participant]);
                if (!$engine || strtoupper((string)($engine->row_array()['ENGINE']??''))!=='INNODB') throw new \RuntimeException('webhook_transactional_storage_required');
            }
            WebhookTransactionGuard::prepare($db);
            if (!$db->trans_begin()) throw new \RuntimeException('webhook_ingress_transaction_unavailable');
            $owned = true;
            $stored = $db->query('INSERT IGNORE INTO '.$db->protect_identifiers($table,true)
                .' (event_id,event_type,asaas_payment_id,received_at,payload,process_status,integration_id,scope_key,payload_hash) VALUES (?,?,?,?,?,?,?,?,?)',
                [$validated['event_id'],$validated['event'],$validated['payload']['payment']['id']??null,
                 date('Y-m-d H:i:s'),json_encode($validated['payload']), 'received',
                 $integration['id']??null,$scopeKey,$validated['payload_hash']]);
            if ($stored === false) throw new \RuntimeException('webhook_storage_failed');
            $duplicate = $db->affected_rows() === 0;
            $row = $db->get_where($table,['scope_key'=>$scopeKey,'event_id'=>$validated['event_id']])->row_array();
            if (!$row) throw new \RuntimeException('webhook_storage_unconfirmed');
            if (!is_string($row['payload_hash']??null) || !hash_equals($row['payload_hash'],$validated['payload_hash'])) {
                // Do not overwrite the original event or its settled outcome.
                if ($integration) $store->recordRejected((int)$integration['id']);
                if (!$db->trans_status() || !$db->trans_commit()) throw new \RuntimeException('webhook_conflict_audit_unconfirmed');
                $owned = false;
                return $this->webhookResult(false,false,'Identificação de evento conflitante.',409);
            }
            if ($integration) $store->recordAccepted((int)$integration['id']);
            if (!$db->trans_status() || !$db->trans_commit()) throw new \RuntimeException('webhook_ingress_commit_unconfirmed');
            $owned = false;
            return $this->webhookResult(true,$duplicate,'Evento armazenado.',200);
        } catch (\Throwable $error) {
            if ($owned) $db->trans_rollback();
            throw $error;
        } finally {
            $db->db_debug = $debug;
        }
    }

    public function processPendingWebhookEvents(int $limit = 20): array
    {
        if (!$this->webhookIntegrations()->ready()) return ['processed'=>0,'failed'=>0,'pending'=>0];
        $db=$this->ci->db;
        $table=$db->protect_identifiers(db_prefix().'asaas_webhook_events',true);
        $limit=max(1,min($limit,50));
        $pending=$db->query("SELECT id FROM {$table} WHERE process_status IN ('received','failed') AND (next_attempt_at IS NULL OR next_attempt_at <= ?) ORDER BY id ASC LIMIT {$limit}", [date('Y-m-d H:i:s')])->result_array();
        $processed=0; $failed=0;
        foreach($pending as $event) {
            $result=$this->processStoredWebhook((int)$event['id']);
            $result['processed'] ? $processed++ : $failed++;
        }
        return ['processed'=>$processed,'failed'=>$failed,'pending'=>count($pending)];
    }

    private function processStoredWebhook(int $rowId): array
    {
        $db=$this->ci->db;
        $table=db_prefix().'asaas_webhook_events';
        $debug=$db->db_debug; $db->db_debug=false;
        try { WebhookTransactionGuard::prepare($db); }
        catch (\Throwable $error) { return $this->webhookResult(false,false,'Transação indisponível para processamento.',503); }
        finally { $db->db_debug=$debug; }
        $row=$db->get_where($table,['id'=>$rowId])->row_array();
        if (!$row) return $this->webhookResult(false,false,'Evento indisponível.',503);
        $terminal=['processed','ignored','review_required','observed'];
        if (in_array($row['process_status'],$terminal,true)) return $this->webhookResult(true,true,'Evento registrado.',200);
        $lockKey='crm_asaas_'.substr(hash('sha256',($row['scope_key']??'legacy').':'.($row['asaas_payment_id']?:$row['event_id'])),0,40);
        $lock=$db->query('SELECT GET_LOCK(?, 0) AS acquired',[$lockKey]);
        if (!$lock || (int)($lock->row_array()['acquired']??0)!==1) return $this->webhookResult(false,false,'Evento aguardando processamento.',503);
        $debug=$db->db_debug; $db->db_debug=false;
        $owned=false;
        try {
            $this->assertWebhookTransactionalStorage();
            WebhookTransactionGuard::prepare($db);
            if (!$db->trans_begin()) throw new \RuntimeException('transaction_unavailable');
            $owned=true;
            $current=$db->query('SELECT * FROM '.$db->protect_identifiers($table,true).' WHERE id = ? FOR UPDATE',[$rowId])->row_array();
            if (!$current) throw new \RuntimeException('event_unavailable');
            if (in_array($current['process_status'],$terminal,true)) {
                if (!$db->trans_commit()) throw new \RuntimeException('commit_unconfirmed');
                $owned=false;
                return $this->webhookResult(true,true,'Evento registrado.',200);
            }
            $payload=json_decode((string)$current['payload'],true,32);
            if (!is_array($payload)) throw new \RuntimeException('invalid_persisted_event');
            $integration=empty($current['integration_id']) ? null : $this->webhookIntegrations()->byId((int)$current['integration_id']);
            $result=$this->reconcileWebhookEvent($payload,$integration,$current);
            if (!$db->where('id',$rowId)->update($table,[
                'process_status'=>$result['state'],'verified_status'=>$result['status']??null,
                'invoice_id'=>$result['invoice_id']??null,'processed_at'=>date('Y-m-d H:i:s'),
                'attempt_count'=>(int)($current['attempt_count']??0)+1,'next_attempt_at'=>null,
                'error_message'=>$result['state']==='review_required' ? 'Conferência financeira manual necessária.' : null,
            ]) || !$db->trans_status() || !$db->trans_commit()) throw new \RuntimeException('reconciliation_commit_failed');
            $owned=false;
            return $this->webhookResult(true,false,'Evento registrado.',200);
        } catch (\Throwable $error) {
            if ($owned) $db->trans_rollback();
            $attempt=(int)($row['attempt_count']??0)+1;
            $db->where('id',$rowId)->update($table,[
                'process_status'=>$attempt>=10?'review_required':'failed', 'attempt_count'=>$attempt,
                'next_attempt_at'=>date('Y-m-d H:i:s',time()+min(3600,30*(2**min($attempt,7)))),
                'error_message'=>'Falha temporária na verificação canônica.',
            ]);
            // Never store provider exception text, request headers or raw payloads.
            log_message('error','[Asaas] Falha temporária na conciliação do webhook.');
            return $this->webhookResult(false,false,'Falha temporária no processamento.',503);
        } finally {
            $db->query('SELECT RELEASE_LOCK(?)',[$lockKey]);
            $db->db_debug=$debug;
        }
    }

    /** Every financial mutation requires explicit authenticated scope and canonical GETs. */
    private function reconcileWebhookEvent(array $payload, ?array $integration = null, array $storedEvent = []): array
    {
        $review=['state'=>'review_required'];
        $event=(string)($payload['event']??'');
        if (!str_starts_with($event,'PAYMENT_')) return ['state'=>'ignored'];
        if (!$integration || (int)$integration['enabled']!==1 || ($integration['issuer_id']??null)!==null
            || ($integration['provider']??'')!=='asaas') return $review;
        $paymentId=$payload['payment']['id']??null;
        if (!is_string($paymentId) || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/D',$paymentId)) return $review;
        // No lookup by attacker-supplied invoice hash or unscoped legacy map.
        $origin=$this->originStore()->byRemoteScope($paymentId,$integration);
        if (!$origin || ($origin['issuer_id']??null)!==null || $origin['provider']!=='asaas' || $origin['channel']!=='api'
            || $origin['account_id']!==$integration['account_id'] || $origin['environment']!==$integration['environment']) return $review;
        $invoiceId=(int)$origin['invoice_id'];
        $db=$this->ci->db;
        $locked=$db->query('SELECT * FROM '.$db->protect_identifiers(db_prefix().'invoices',true).' WHERE id = ? FOR UPDATE',[$invoiceId]);
        $invoice=$locked?$locked->row_array():[];
        $lockedOrigin=$db->query('SELECT * FROM '.$db->protect_identifiers(db_prefix().'asaas_payment_origins',true).' WHERE id = ? FOR UPDATE',[(int)$origin['id']]);
        $origin=$lockedOrigin?$lockedOrigin->row_array():[];
        if (!$origin) return $review;
        if (!$invoice || ($invoice['issuer_id']??null)!==null || $invoice['hash']!==$origin['invoice_hash']) return $review;
        $currency=$db->get_where(db_prefix().'currencies',['id'=>(int)($invoice['currency']??0)])->row_array();
        if (($currency['name']??'')!=='BRL') return $review;
        $map=$db->get_where(db_prefix().'asaas_payments_map',['invoice_id'=>$invoiceId])->row_array();
        if (!$map || $map['asaas_payment_id']!==$paymentId) return $review;
        // Account/environment come from immutable origin, not current sandbox toggle.
        $client=$this->provider->clientForOrigin($origin);
        $verified=$this->requestWithClient($client,'GET','/payments/'.$paymentId);
        if (!$verified['success']) throw new \RuntimeException('canonical_payment_unavailable');
        $p=$verified['data'];
        $status=$p['status']??null;
        if (!is_string($status) || !preg_match('/^[A-Z_]{1,32}$/D',$status)) return $review+['status'=>'UNKNOWN','invoice_id'=>$invoiceId];
        $meta=['status'=>$status,'invoice_id'=>$invoiceId];
        if (!empty($p['deleted']) || ($p['id']??'')!==$paymentId || ($p['externalReference']??'')!==$origin['invoice_hash']
            || !isset($p['value']) || !$this->equalMoney($p['value'],$origin['invoice_total'])
            || !$this->equalMoney($p['value'],$invoice['total'])
            || ($p['dueDate']??null)!==$origin['invoice_due_date'] || ($p['dueDate']??null)!==$invoice['duedate']) return $review+$meta;
        $customerId=$p['customer']??null;
        if (!is_string($customerId) || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/D',$customerId)) return $review+$meta;
        $customer=$this->requestWithClient($client,'GET','/customers/'.$customerId);
        if (!$customer['success']) throw new \RuntimeException('canonical_customer_unavailable');
        $local=$db->get_where(db_prefix().'clients',['userid'=>(int)($invoice['clientid']??0)])->row_array();
        $document=$this->normalizeIdentityDocument((string)($local['vat']??''));
        if ($document===null || ($customer['data']['id']??'')!==$customerId || !empty($customer['data']['deleted'])
            || $this->normalizeIdentityDocument((string)($customer['data']['cpfCnpj']??''))!==$document) return $review+$meta;
        $reviewEvents=['PAYMENT_REFUNDED','PAYMENT_PARTIALLY_REFUNDED','PAYMENT_REFUND_IN_PROGRESS',
            'PAYMENT_RECEIVED_IN_CASH_UNDONE','PAYMENT_CHARGEBACK_REQUESTED','PAYMENT_CHARGEBACK_DISPUTE','PAYMENT_AWAITING_CHARGEBACK_REVERSAL'];
        if (in_array($event,$reviewEvents,true) || str_contains($status,'REFUND') || str_contains($status,'CHARGEBACK')) return $review+$meta;
        // Preserve the deployed CRM settlement policy while retaining the exact provider status.
        // CONFIRMED is not equivalent to available funds or PIGE financial confirmation.
        if (!in_array($event, ['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED'], true)
            || !in_array($status, ['CONFIRMED', 'RECEIVED', 'RECEIVED_IN_CASH'], true)) return ['state'=>'observed']+$meta;
        $records=db_prefix().'invoicepaymentrecords';
        require_once __DIR__.'/CanonicalReceiptRecorder.php';
        $paymentDate=CanonicalReceiptRecorder::effectiveDate($p);
        if ($paymentDate===null || !in_array($p['billingType']??null,['PIX','BOLETO','CREDIT_CARD'],true)) return $review+$meta;
        $registeredRows=$db->get_where($records,['transactionid'=>$paymentId])->result_array();
        if ($registeredRows) {
            if (count($registeredRows)!==1) return $review+$meta;
            $registered=$registeredRows[0];
            $proof=$db->get_where(db_prefix().'asaas_settlement_outbox',[
                'invoice_id'=>$invoiceId,'origin_id'=>(int)$origin['id'],'receipt_id'=>(int)$registered['id'],
                'provider'=>'asaas','account_id'=>$origin['account_id'],'environment'=>$origin['environment'],
                'remote_id'=>$paymentId,'payment_date'=>$paymentDate,'billing_type'=>$p['billingType'],
            ])->result_array();
            $proven=count($proof)===1 && (int)$invoice['status']===2 && (int)$registered['invoiceid']===$invoiceId
                && ($registered['paymentmode']??'')==='asaas' && ($registered['paymentmethod']??'')===$p['billingType']
                && ($registered['date']??'')===$paymentDate && $this->equalMoney($registered['amount']??0,$p['value'])
                && $db->get_where($records,['invoiceid'=>$invoiceId])->num_rows()===1;
            return ['state'=>$proven?'processed':'review_required']+$meta;
        }
        if (!in_array((int)$invoice['status'],[1,4],true)
            || $db->get_where($records,['invoiceid'=>$invoiceId])->num_rows()>0
            || $origin['state']!=='issued') return $review+$meta;
        // A generic Payments_model::add would send staff e-mails/Pusher and run
        // arbitrary downstream hooks. Keep those effects in a visible outbox.
        require_once __DIR__.'/CanonicalReceiptRecorder.php';
        if ($db->get_where(db_prefix().'credits',['invoice_id'=>$invoiceId])->num_rows()>0) return $review+$meta;
        (new CanonicalReceiptRecorder($db))->writeLocked($invoice,$origin,$p,$storedEvent);
        // Cache only verified nonsensitive fields, not the provider's full payment.
        if (!$this->gateway->upsert_payment_map($invoiceId,array_intersect_key($p,array_flip(['id','status','value','dueDate','billingType','externalReference','customer'])))) throw new \RuntimeException('map_write_failed');
        return ['state'=>'processed']+$meta;
    }

    private function equalMoney($a, $b): bool
    {
        $values=[];
        foreach ([$a,$b] as $v) {
            if (!is_scalar($v) || !preg_match('/^\d{1,13}(?:\.\d{1,2})?$/D',(string)$v)) return false;
            $parts=explode('.',(string)$v);
            $values[]=ltrim($parts[0].str_pad($parts[1]??'',2,'0'),'0')?:'0';
        }
        return $values[0]!=='0' && $values[0]===$values[1];
    }

    private function assertWebhookTransactionalStorage(): void
    {
        $tables=['invoices','invoicepaymentrecords','asaas_payments_map','asaas_webhook_events',
            'sales_activity','credits','asaas_settlement_outbox','asaas_payment_origins','asaas_webhook_admin_audit'];
        foreach ($tables as $name) {
            $result=$this->ci->db->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [db_prefix().$name]);
            $row=$result?$result->row_array():[];
            if (strtoupper((string)($row['ENGINE']??''))!=='INNODB') throw new \RuntimeException('transactional_storage_required');
        }
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

    public function originStore(): PaymentOriginStore
    {
        return new PaymentOriginStore($this->ci->db);
    }

    private function resolveRequestClient(string $method, string $path, array $query, ?int $companyId)
    {
        $path = '/' . preg_replace('#^/?v3/#', '', ltrim($path, '/'));
        $origin = null;
        if (preg_match('#^/payments/([a-zA-Z0-9_-]+)(?:/|$)#', $path, $match)) {
            $origin = $this->originStore()->byRemoteId($match[1]);
            if (!$origin && strtoupper($method) !== 'GET') {
                throw new \RuntimeException('legacy_origin_review_required');
            }
        } elseif ($path === '/payments' && !empty($query['externalReference'])) {
            $invoice = $this->ci->db->get_where(db_prefix() . 'invoices', ['hash' => $query['externalReference']])->row_array();
            if ($invoice) {
                $this->assertInvoiceScope($invoice);
                $origin = $this->originStore()->byInvoice((int) $invoice['id']);
                if ($origin && empty($origin['remote_id'])) {
                    throw new \RuntimeException('issuance_requires_reconciliation');
                }
            }
        }
        $environment = $origin ? EnvironmentSettings::environment((string) $origin['environment']) : $this->provider->env($companyId);
        return [$origin ? $this->provider->clientForOrigin($origin) : $this->provider->client($companyId), $environment];
    }

    public function assertInvoiceScope(array $invoice): void
    {
        if (!empty($invoice['issuer_id'])) {
            if (!function_exists('issuers_assert_operational_scope')) {
                throw new \RuntimeException('issuer_rollout_blocked');
            }
            issuers_assert_operational_scope((int) $invoice['issuer_id']);
            // Remove only once customer mapping, webhook authentication, checkout,
            // reconciliation and every invoice entry point are issuer-scoped.
            throw new \RuntimeException('issuer_asaas_isolation_pending');
        }
    }

    private function createInvoicePayment(array $payload): array
    {
        $reference = (string) ($payload['externalReference'] ?? '');
        $invoice = $this->ci->db->get_where(db_prefix() . 'invoices', ['hash' => $reference])->row_array();
        if ($reference === '' || !$invoice) {
            throw new \RuntimeException('invoice_reference_required');
        }
        $this->assertInvoiceScope($invoice);
        if (!in_array((int) ($invoice['status'] ?? 0), [1, 4], true)
            || $this->ci->db->get_where(db_prefix() . 'invoicepaymentrecords', ['invoiceid' => (int) $invoice['id']])->num_rows() > 0) {
            throw new \RuntimeException('invoice_not_eligible_for_issuance');
        }
        $store = $this->originStore();
        $existing = $store->byInvoice((int) $invoice['id']);
        if ($existing) {
            if (empty($existing['remote_id']) || $existing['state'] !== 'issued') {
                throw new \RuntimeException('issuance_requires_reconciliation');
            }
            return $this->getPayment($existing['remote_id']);
        }
        $map = $this->ci->db->get_where(db_prefix() . 'asaas_payments_map', ['invoice_id' => (int) $invoice['id']])->row_array();
        if ($map) {
            throw new \RuntimeException('legacy_origin_review_required');
        }
        $environment = $this->provider->env();
        $currency = $this->ci->db->get_where(db_prefix() . 'currencies', ['id' => (int) ($invoice['currency'] ?? 0)])->row_array();
        if (($currency['name'] ?? '') !== 'BRL' || $this->provider->setting('currencies') !== 'BRL') {
            throw new \DomainException('asaas_profile_requires_brl');
        }
        // Keep the legacy combined boleto/Pix issuance contract: billingType is not a UI toggle.
        if ($this->provider->setting('active') !== '1') {
            throw new \DomainException('asaas_profile_payment_disabled');
        }
        $client = $this->provider->clientForEnvironment($environment);
        $context = $this->provider->contextForClient($client, $environment);
        // A legacy customer map is not account-scoped. Verify the candidate in
        // this exact authenticated account before using it to issue a charge.
        $customerId = (string) ($payload['customer'] ?? '');
        if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $customerId)) {
            throw new \RuntimeException('customer_origin_unverified');
        }
        $localClient = $this->ci->db->get_where(db_prefix() . 'clients', ['userid' => (int) ($invoice['clientid'] ?? 0)])->row_array();
        $document = $this->normalizeIdentityDocument((string) ($localClient['vat'] ?? ''));
        $customer = $client->request('GET', 'v3/customers/' . $customerId, [], [], null, false, false);
        if ($document === null || !is_array($customer)
            || ($customer['id'] ?? '') !== $customerId
            || $this->normalizeIdentityDocument((string) ($customer['cpfCnpj'] ?? '')) !== $document
            || !empty($customer['deleted'])) {
            throw new \RuntimeException('customer_origin_unverified');
        }
        $store->reserve($invoice, $context);
        try {
            $response = $client->request('POST', 'v3/payments', [], [], $payload, false, false);
            if (!is_array($response) || empty($response['id']) || !empty($response['errors'])) {
                throw new \RuntimeException('issuance_unconfirmed');
            }
            $store->attachRemote((int) $invoice['id'], (string) $response['id']);
            if (!$this->gateway || !$this->gateway->upsert_payment_map((int) $invoice['id'], $response, $payload['customer'] ?? null)) {
                $store->state((int) $invoice['id'], 'review_required', 'payment_map_write_failed');
                throw new \RuntimeException('payment_map_write_failed');
            }
            return $response;
        } catch (\Throwable $error) {
            $store->state((int) $invoice['id'], 'review_required', 'issuance_unconfirmed');
            throw $error;
        }
    }

    public function removeInvoicePayment(int $invoiceId): array
    {
        $store = $this->originStore();
        $origin = $store->byInvoice($invoiceId);
        if (!$origin || empty($origin['remote_id'])) {
            return ['success' => false, 'code' => 'legacy_origin_review_required'];
        }
        $receipts = $this->ci->db->get_where(db_prefix() . 'invoicepaymentrecords', ['invoiceid' => $invoiceId])->num_rows() > 0;
        if ($receipts) {
            $store->state($invoiceId, 'review_required', 'local_receipts_require_review');
            return ['success' => false, 'code' => 'local_receipts_require_review'];
        }
        if ($origin['state'] === 'removed') {
            if (!$this->gateway || !$this->gateway->upsert_payment_map($invoiceId, ['id' => $origin['remote_id'], 'status' => 'DELETED'])) {
                $store->event($invoiceId, 'payment_map_write_failed');
                return ['success' => false, 'code' => 'payment_map_write_failed'];
            }
            return ['success' => true, 'code' => 'already_removed'];
        }
        try {
            // One verified client instance binds GET and DELETE to the same key/account.
            $client = $this->provider->clientForOrigin($origin);
            $service = new InvoicePaymentRemoval(function ($method, $path) use ($client) {
                return $this->requestWithClient($client, $method, $path);
            });
            $store->state($invoiceId, 'removal_requested', 'removal_requested');
            $result = $service->remove($origin['remote_id'], $origin['invoice_hash'], false, $origin, $origin);
            $store->state($invoiceId, $result['success'] ? 'removed' : 'review_required', $result['code']);
            if ($result['success'] && $this->gateway) {
                if (!$this->gateway->upsert_payment_map($invoiceId, ['id' => $origin['remote_id'], 'status' => 'DELETED'])) {
                    $store->event($invoiceId, 'payment_map_write_failed');
                    return ['success' => false, 'code' => 'payment_map_write_failed'];
                }
            }
            return $result;
        } catch (\Throwable $error) {
            $store->state($invoiceId, 'review_required', 'origin_or_removal_unconfirmed');
            return ['success' => false, 'code' => 'origin_or_removal_unconfirmed'];
        }
    }

    private function requestWithClient($client, string $method, string $path): array
    {
        try {
            $response = $client->request($method, $this->normalizePath($path), [], [], null, false, false);
            return ['success' => is_array($response), 'data' => is_array($response) ? $response : null];
        } catch (\Throwable $error) {
            return ['success' => false, 'data' => null];
        }
    }

    /** Admin-reviewed legacy/uncertain link; never invoked by an automatic backfill. */
    public function reconcileOrigin(int $invoiceId, string $remoteId, string $environment, string $expectedAccount): array
    {
        if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $remoteId) || trim($expectedAccount) === '') {
            throw new \InvalidArgumentException('invalid_reconciliation_input');
        }
        $invoice = $this->ci->db->get_where(db_prefix() . 'invoices', ['id' => $invoiceId])->row_array();
        if (!$invoice) {
            throw new \RuntimeException('invoice_not_found');
        }
        $this->assertInvoiceScope($invoice);
        $store = $this->originStore();
        $existing = $store->byInvoice($invoiceId);
        $map = $this->ci->db->get_where(db_prefix() . 'asaas_payments_map', ['invoice_id' => $invoiceId])->row_array();
        if ($map && ($map['asaas_payment_id'] ?? '') !== $remoteId) {
            throw new \RuntimeException('existing_payment_link_mismatch');
        }
        if ($existing && ($existing['account_id'] !== $expectedAccount || $existing['environment'] !== $environment
            || (!empty($existing['remote_id']) && $existing['remote_id'] !== $remoteId))) {
            throw new \RuntimeException('payment_origin_is_immutable');
        }
        $client = $this->provider->clientForEnvironment($environment);
        $context = $this->provider->contextForClient($client, $environment);
        if (!hash_equals($expectedAccount, $context['account_id'])) {
            throw new \RuntimeException('origin_account_mismatch');
        }
        $response = $this->requestWithClient($client, 'GET', '/payments/' . $remoteId);
        $payment = $response['data'] ?? [];
        if (!$response['success'] || ($payment['id'] ?? '') !== $remoteId
            || ($payment['externalReference'] ?? '') !== $invoice['hash']
            || !isset($payment['value']) || abs((float) $payment['value'] - (float) $invoice['total']) > 0.009
            || ($payment['dueDate'] ?? '') !== $invoice['duedate']) {
            throw new \RuntimeException('reconciliation_evidence_mismatch');
        }
        if (!$existing) {
            $store->reserve($invoice, $context, 'review_required', $remoteId);
        }
        $store->attachRemote($invoiceId, $remoteId);
        if (!$this->gateway->upsert_payment_map($invoiceId, $payment)) {
            $store->state($invoiceId, 'review_required', 'payment_map_write_failed');
            throw new \RuntimeException('payment_map_write_failed');
        }
        $store->event($invoiceId, 'origin_manually_reconciled', hash('sha256', json_encode([
            'id' => $remoteId, 'account_id' => $expectedAccount, 'environment' => $environment,
            'externalReference' => $payment['externalReference'], 'value' => $payment['value'], 'dueDate' => $payment['dueDate'],
        ])));
        return ['success' => true, 'code' => 'origin_manually_reconciled'];
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

    /** Preserve the legal identity of alphanumeric CNPJ; never collapse it to digits. */
    private function normalizeIdentityDocument(string $document): ?string
    {
        $normalized = preg_replace('/[.\/\-\s]+/u', '', strtoupper(trim($document)));
        if (!is_string($normalized) || !preg_match('/^(?:[0-9]{11}|[A-Z0-9]{12}[0-9]{2})$/D', $normalized)) {
            return null;
        }
        return $normalized;
    }

    private function normalizePostalCode(string $postalCode): string
    {
        return preg_replace('/[^0-9]/', '', $postalCode);
    }

    private function log(string $level, string $message, array $context = [], ?string $environment = null): void
    {
        $sanitized = $this->sanitizeContext($context);
        $sanitizedContext = json_encode($sanitized, JSON_UNESCAPED_UNICODE);

        if ($level === 'error') {
            log_message('error', '[Asaas] ' . $message . ' | context=' . $sanitizedContext);
        } elseif ($this->shouldWriteFileLog($environment)) {
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
            log_message('error', '[Asaas] Falha ao persistir log em banco.');
        }
    }


    private function shouldWriteFileLog(?string $environment = null): bool
    {
        if ($this->gateway === null || !method_exists($this->gateway, 'isFileLogEnabled')) {
            return false;
        }

        return (bool) $this->gateway->isFileLogEnabled($environment);
    }

    private function sanitizeContext(array $context): array
    {
        // Diagnostics are metadata-only. A denylist can miss nested contacts,
        // future provider fields, query strings or credentials echoed in errors.
        $safe = [];
        if (isset($context['method'])) {
            $method = strtoupper((string) $context['method']);
            $safe['method'] = in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], true) ? $method : 'OTHER';
        }
        if (isset($context['path'])) {
            $path = ltrim((string) parse_url((string) $context['path'], PHP_URL_PATH), '/');
            $path = preg_replace('#^v3/#', '', $path);
            $resource = explode('/', $path, 2)[0];
            $safe['path'] = in_array($resource, ['customers', 'payments', 'prefaces', 'webhooks', 'wallets'], true)
                ? 'v3/' . $resource . (str_contains($path, '/') ? '/[resource]' : '') : '[resource]';
        }
        if (isset($context['status']) && is_numeric($context['status'])) $safe['status'] = (int) $context['status'];
        if (isset($context['correlation_id']) && is_string($context['correlation_id']) && $context['correlation_id'] !== '') {
            $safe['correlation_id'] = substr(hash('sha256', $context['correlation_id']), 0, 32);
        }
        return $safe;
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
        $origin = $this->originStore()->byRemoteId($paymentId);
        if (!$origin) {
            throw new \RuntimeException('legacy_origin_review_required');
        }
        return $this->removeInvoicePayment((int) $origin['invoice_id']);
    }
}

