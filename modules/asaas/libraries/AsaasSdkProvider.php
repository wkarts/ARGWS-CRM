<?php

declare(strict_types=1);
use Asaas\Sdk\AsaasSdk;
use Asaas\Sdk\Config\AsaasConfig;
use Asaas\Sdk\Exception\ApiException;
use Asaas\Sdk\Exception\TransportException;
use Asaas\Sdk\Http\Client;
use Asaas\Sdk\Http\Environment;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

require_once __DIR__ . '/Asaas/CustomerContactPolicy.php';

class AsaasSdkProvider
{
    private App_gateway $gateway;
    private static bool $autoloaded = false;

    public function __construct(App_gateway $gateway)
    {
        $this->gateway = $gateway;
        $this->ensureComposerAutoload();
    }

    public function ensureComposerAutoload(): void
    {
        if (self::$autoloaded) {
            return;
        }

        $paths = [];

        if (defined('FCPATH')) {
            $paths[] = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        }

        $paths[] = __DIR__ . '/../vendor/autoload.php';

        foreach ($paths as $path) {
            if (is_file($path)) {
                require_once $path;
                self::$autoloaded = true;
                return;
            }
        }
    }

    public function sdk(?int $companyId = null): AsaasSdk
    {
        $this->ensureComposerAutoload();

        $config = new AsaasConfig(
            apiKey: $this->getApiKey($companyId),
            environment: $this->environmentEnum($companyId),
            appName: $this->getUserAgent($companyId),
            timeout: $this->getTimeout($companyId),
            connectTimeout: $this->getConnectTimeout($companyId),
            httpClient: $this->httpClient($this->environmentEnum($companyId), $companyId)
        );

        return new AsaasSdk($config);
    }

    public function client(?int $companyId = null): Client
    {
        $this->ensureComposerAutoload();

        return new Client(
            $this->getApiKey($companyId),
            $this->environmentEnum($companyId),
            $this->getUserAgent($companyId),
            $this->getTimeout($companyId),
            $this->getConnectTimeout($companyId),
            null,
            $this->httpClient($this->environmentEnum($companyId), $companyId)
        );
    }

    /** Explicit environment is independent from the gateway's current sandbox toggle. */
    public function clientForEnvironment(string $environment, ?int $issuerId = null): Client
    {
        if (!in_array($environment, ['sandbox', 'production'], true)) {
            throw new \InvalidArgumentException('invalid_origin_environment');
        }
        if ($issuerId !== null) {
            // The issuer foundation is intentionally opt-in until end-to-end isolation.
            if (!function_exists('issuers_assert_operational_scope')) {
                throw new \RuntimeException('issuer_rollout_blocked');
            }
            issuers_assert_operational_scope($issuerId);
            $name = $environment === 'sandbox' ? 'api_key_sandbox' : 'api_key';
            $stored = get_option('paymentmethod_asaas_' . $name . '_issuer_' . $issuerId);
            if (!is_string($stored) || trim($stored) === '' || !function_exists('decrypt')) {
                throw new \RuntimeException('issuer_credentials_missing');
            }
            $key = decrypt($stored);
        } else {
            $key = $this->gateway->decryptSetting($environment === 'sandbox' ? 'api_key_sandbox' : 'api_key');
        }
        if (!is_string($key) || trim($key) === '') {
            throw new \RuntimeException('origin_credentials_missing');
        }
        $environmentEnum = $environment === 'sandbox' ? Environment::Sandbox : Environment::Production;
        return new Client($key, $environmentEnum,
            $this->getUserAgent(), $this->getTimeout(), $this->getConnectTimeout(), null, $this->httpClient($environmentEnum));
    }

    /** Mutations may have succeeded before a timeout/5xx; never replay them. */
    private function httpClient(Environment $environment, ?int $companyId = null): GuzzleClient
    {
        $stack = HandlerStack::create();
        $profile = $environment === Environment::Sandbox ? 'sandbox' : 'production';
        $expectedHost = parse_url($environment->value, PHP_URL_HOST);
        $stack->push(Middleware::mapRequest(function (RequestInterface $request) use ($profile, $companyId, $expectedHost): RequestInterface {
            $uri = $request->getUri();
            // A raw SDK Client can receive an absolute URL or setEnvironment().
            // Neither may send this client's credentials to a different origin.
            if ($uri->getScheme() !== 'https' || $uri->getHost() !== $expectedHost
                || ($uri->getPort() !== null && $uri->getPort() !== 443) || $uri->getUserInfo() !== '') {
                throw new \DomainException('asaas_request_origin_mismatch');
            }
            return $this->customerRequest($request, $profile, $companyId);
        }), 'asaas_customer_contact_policy');
        // Redirects can replay mutations and forward credentials to another host.
        $stack->push(Middleware::mapResponse(static function (ResponseInterface $response): ResponseInterface {
            if ($response->getStatusCode() >= 300 && $response->getStatusCode() < 400) throw new \RuntimeException('asaas_redirect_refused');
            return $response;
        }), 'asaas_no_redirect_response');
        $stack->push(Middleware::retry(
            static function (int $retries, RequestInterface $request, ?ResponseInterface $response = null, ?\Throwable $exception = null): bool {
                if (!in_array(strtoupper($request->getMethod()), ['GET', 'HEAD'], true) || $retries >= 3) return false;
                return $exception !== null || ($response !== null && ($response->getStatusCode() === 429 || $response->getStatusCode() >= 500));
            },
            static fn(int $retries): int => (int) (200 * (2 ** $retries))
        ), 'asaas_read_only_retry');
        return new GuzzleClient([
            'base_uri' => $environment->value,
            'timeout' => $this->getTimeout($companyId),
            'connect_timeout' => $this->getConnectTimeout($companyId),
            'handler' => $stack,
            'allow_redirects' => false,
        ]);
    }

    /** Also protects public client()/sdk() compatibility callers that bypass the adapter. */
    private function customerRequest(RequestInterface $request, string $environment, ?int $companyId): RequestInterface
    {
        $uri = $request->getUri();
        if (!\Asaas\CustomerContactPolicy::isCustomerPath($uri->getPath())) return $request;
        $stream = $request->getBody();
        $position = $stream->isSeekable() ? $stream->tell() : null;
        $rawBody = (string) $stream;
        if ($position !== null) $stream->seek($position);
        elseif ($rawBody !== '') $request = $request->withBody(Utils::streamFor($rawBody));
        if ($rawBody === '' && $uri->getQuery() === '') return $request;
        $payload = $rawBody === '' ? null : json_decode($rawBody, true);
        if ($rawBody !== '' && !is_array($payload)) throw new \DomainException('asaas_customer_payload_requires_json');
        $query = [];
        parse_str($uri->getQuery(), $query);
        $setting = function (string $name) use ($environment, $companyId) {
            if ($companyId === null && method_exists($this->gateway, 'getSettingForEnvironment')) {
                return $this->gateway->getSettingForEnvironment($name, $environment);
            }
            return $this->getSettingValue($name, $companyId);
        };
        [, $filteredQuery, $filteredPayload] = \Asaas\CustomerContactPolicy::apply($request->getMethod(), $uri->getPath(), $query, $payload,
            (string) $setting('send_customer_email') === '1',
            (string) $setting('send_customer_mobile') === '1',
            (string) $setting('disable_charge_notification') === '1');
        if ($filteredQuery !== $query) $request = $request->withUri($uri->withQuery(http_build_query($filteredQuery, '', '&', PHP_QUERY_RFC3986)));
        if ($filteredPayload !== $payload) {
            $body = json_encode($filteredPayload, JSON_THROW_ON_ERROR);
            $request = $request->withBody(Utils::streamFor($body))->withoutHeader('Transfer-Encoding')->withHeader('Content-Length', (string) strlen($body));
        }
        return $request;
    }

    public function contextForClient(Client $client, string $environment, ?int $issuerId = null): array
    {
        $wallets = $client->request('GET', 'v3/wallets/', [], [], null, false, false);
        $rows = is_array($wallets) ? ($wallets['data'] ?? null) : null;
        if (!is_array($rows) || count($rows) !== 1 || !is_string($rows[0]['id'] ?? null)
            || trim($rows[0]['id']) === '' || ($wallets['hasMore'] ?? false)) {
            throw new \RuntimeException('origin_account_unverified');
        }
        return ['issuer_id' => $issuerId, 'provider' => 'asaas', 'channel' => 'api',
            'account_id' => $rows[0]['id'], 'environment' => $environment];
    }

    public function clientForOrigin(array $origin): Client
    {
        if (($origin['provider'] ?? '') !== 'asaas' || ($origin['channel'] ?? '') !== 'api') {
            throw new \RuntimeException('unsupported_origin');
        }
        $issuer = isset($origin['issuer_id']) ? (int) $origin['issuer_id'] : null;
        $client = $this->clientForEnvironment((string) ($origin['environment'] ?? ''), $issuer);
        $context = $this->contextForClient($client, $origin['environment'], $issuer);
        if (!hash_equals((string) ($origin['account_id'] ?? ''), $context['account_id'])) {
            throw new \RuntimeException('origin_account_mismatch');
        }
        return $client;
    }

    public function env(?int $companyId = null): string
    {
        return $this->getSandboxSetting($companyId) ? 'sandbox' : 'production';
    }

    public function environmentLabel(?int $companyId = null): string
    {
        return $this->getSandboxSetting($companyId) ? 'SANDBOX' : 'PRODUCTION';
    }

    public function baseUrl(?int $companyId = null): string
    {
        $this->ensureComposerAutoload();

        return $this->environmentEnum($companyId)->value;
    }

    public function baseHost(?int $companyId = null): string
    {
        $baseUrl = $this->baseUrl($companyId);

        return rtrim(preg_replace('#/v3/?$#', '', $baseUrl), '/');
    }

    public function apiKey(?int $companyId = null): string
    {
        return $this->getApiKey($companyId);
    }

    public function headers(array $extra = [], ?string $correlationId = null): array
    {
        // The correlation ID remains available to local operation logs; do not
        // send installation or request identifiers to the payment provider.
        return $extra;
    }

    /**
     * @return array{online: bool, status: string, httpStatus: int|null, latencyMs: int, message: string}
     */
    public function healthCheck(?int $companyId = null): array
    {
        $startedAt = microtime(true);

        try {
            $this->sdk($companyId)->payment->listPayments(['limit' => 1], $this->headers());

            return [
                'online' => true,
                'status' => 'ONLINE',
                'httpStatus' => 200,
                'latencyMs' => (int) ((microtime(true) - $startedAt) * 1000),
                'message' => 'API respondeu com sucesso.',
            ];
        } catch (ApiException $exception) {
            $status = $exception->getStatusCode();

            return [
                'online' => false,
                'status' => $this->mapApiStatus($status),
                'httpStatus' => $status,
                'latencyMs' => (int) ((microtime(true) - $startedAt) * 1000),
                'message' => 'Falha de comunicação com o Asaas. Confira a configuração e tente novamente.',
            ];
        } catch (TransportException $exception) {
            return [
                'online' => false,
                'status' => 'TRANSPORT_ERROR',
                'httpStatus' => null,
                'latencyMs' => (int) ((microtime(true) - $startedAt) * 1000),
                'message' => 'Falha de comunicação com o Asaas. Confira a configuração e tente novamente.',
            ];
        }
    }

    public function setting(string $name, ?int $companyId = null): mixed
    {
        return $this->getSettingValue($name, $companyId);
    }

    private function mapApiStatus(?int $status): string
    {
        if ($status === 401 || $status === 403) {
            return 'AUTH_ERROR';
        }

        if ($status === 429) {
            return 'RATE_LIMIT';
        }

        if ($status !== null && $status >= 500) {
            return 'SERVER_ERROR';
        }

        return 'API_ERROR';
    }

    private function getSandboxSetting(?int $companyId = null): bool
    {
        $value = $this->getSettingValue('sandbox', $companyId);

        if ($value !== '1' && $value !== '0') {
            throw new \RuntimeException('asaas_environment_selection_required');
        }
        return $value === '1';
    }

    private function getApiKey(?int $companyId = null): string
    {
        $settingName = $this->getSandboxSetting($companyId) ? 'api_key_sandbox' : 'api_key';

        $override = $this->getSettingOverride($settingName, $companyId);
        if ($override !== null) {
            return $override;
        }

        if ($companyId !== null) {
            throw new \RuntimeException('asaas_explicit_company_credentials_missing');
        }
        return (string) $this->gateway->decryptSetting($settingName);
    }

    private function getUserAgent(?int $companyId = null): string
    {
        return 'ARGWS-CRM/1.0 Asaas';
    }

    private function getTimeout(?int $companyId = null): float
    {
        return 30.0;
    }

    private function getConnectTimeout(?int $companyId = null): float
    {
        return 10.0;
    }

    private function environmentEnum(?int $companyId = null): Environment
    {
        return $this->getSandboxSetting($companyId) ? Environment::Sandbox : Environment::Production;
    }

    private function getSettingValue(string $name, ?int $companyId = null): mixed
    {
        $override = $this->getSettingOverride($name, $companyId);
        if ($override !== null) {
            return $override;
        }

        if ($companyId !== null) {
            throw new \RuntimeException('asaas_explicit_company_setting_missing');
        }
        return $this->gateway->getSetting($name);
    }

    private function getSettingOverride(string $name, ?int $companyId = null): mixed
    {
        if ($companyId !== null && $companyId <= 0) {
            throw new \InvalidArgumentException('asaas_invalid_company');
        }
        if ($companyId === null || !function_exists('get_option')) {
            return null;
        }

        $optionKey = 'paymentmethod_asaas_' . $name . '_company_' . $companyId;
        $value = get_option($optionKey);

        if ($value === '' || $value === null) {
            return null;
        }

        if (in_array($name, ['api_key', 'api_key_sandbox'], true) && function_exists('decrypt')) {
            return decrypt($value);
        }

        return $value;
    }
}

