<?php

declare(strict_types=1);
define('ASAAS_CONTACT_FIXTURES_ONLY', true);
require __DIR__ . '/asaas_customer_contact_policy.php';

use Asaas\Sdk\Http\Client;
use Asaas\Sdk\Exception\ApiException;
use Asaas\Sdk\Exception\TransportException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

function retryFixtureClient(ContactTransportProvider $provider, string $factory, string $environment): Client {
    if ($factory === 'selected') return $provider->client();
    if ($factory === 'environment') return $provider->clientForEnvironment($environment);
    $sdk = $provider->sdk();
    return $provider->intercept((new ReflectionProperty($sdk, 'client'))->getValue($sdk));
}
function uncertainResponse(ContactTransportProvider $provider, string $failure): void {
    $provider->transport->append($failure === 'timeout'
        ? new ConnectException('SYNTHETIC_TIMEOUT_PRIVATE_DETAILS', new Request('POST', 'https://synthetic.invalid'))
        : new Response((int) $failure, [], '{"errors":[{"description":"SYNTHETIC_PROVIDER_DETAILS"}]}'));
}

foreach (['production', 'sandbox'] as $environment) foreach (['selected', 'environment', 'sdk'] as $factory) foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) foreach (['500', '429', 'timeout'] as $failure) {
    [$gateway, $adapter, $provider] = contactFixture($environment);
    $client = retryFixtureClient($provider, $factory, $environment);
    uncertainResponse($provider, $failure); $provider->responses(remoteCustomer());
    $exception = null;
    try { $client->request($method, 'v3/customers/cus_synthetic', [], [], ['name' => 'Synthetic Customer']); }
    catch (ApiException | TransportException $caught) { $exception = $caught; }
    assertContact($exception !== null, 'uncertain mutation is propagated, not converted to success/rejection: ' . $factory . '/' . $method . '/' . $failure);
    assertContact($failure === 'timeout' ? $exception instanceof TransportException : $exception instanceof ApiException && $exception->getStatusCode() === (int) $failure, 'failure classification is preserved');
    assertContact(count($provider->history) === 1 && $provider->transport->count() === 1, 'mutation has exactly one HTTP attempt; success sentinel remains unused');
    $request = $provider->history[0]['request'];
    assertContact($request->getMethod() === $method && $request->getUri()->getPath() === '/v3/customers/cus_synthetic', 'original method/path retained');
    assertContact($request->getUri()->getHost() === ($environment === 'sandbox' ? 'api-sandbox.asaas.com' : 'api.asaas.com'), 'retry policy does not switch environment');
}

// Redirect handling is not retry middleware: 307/308 could otherwise replay a
// POST and leak the access_token to a different host. Every redirect is uncertain.
foreach (['production', 'sandbox'] as $environment) foreach (['selected', 'environment', 'sdk'] as $factory) foreach ([301, 302, 303, 307, 308] as $status) foreach (['POST', 'PUT', 'PATCH', 'DELETE', 'GET', 'HEAD'] as $method) {
    [$gateway, $adapter, $provider] = contactFixture($environment);
    $client = retryFixtureClient($provider, $factory, $environment);
    $provider->transport->append(new Response($status, ['Location' => 'https://synthetic-redirect.invalid/v3/customers'], json_encode(remoteCustomer())));
    $provider->responses(remoteCustomer()); $failed = false;
    try { $client->request($method, 'v3/customers/cus_synthetic', [], [], ['name' => 'Synthetic Customer']); }
    catch (TransportException $expected) { $failed = true; }
    assertContact($failed && count($provider->history) === 1 && $provider->transport->count() === 1, 'redirect is a single uncertain attempt, never followed or considered success');
    assertContact($provider->history[0]['request']->getUri()->getHost() !== 'synthetic-redirect.invalid', 'credentials never reach redirect destination');
}

// A reused raw SDK Client cannot change environment or send the saved key to
// an absolute third-party/insecure URL. Fail before the synthetic socket handler.
foreach (['production', 'sandbox'] as $environment) foreach (['selected', 'environment', 'sdk'] as $factory) foreach (['changed_environment', 'absolute_host', 'insecure_scheme', 'alternate_port', 'userinfo'] as $case) {
    [$gateway, $adapter, $provider] = contactFixture($environment, '1', '1');
    $client = retryFixtureClient($provider, $factory, $environment);
    $path = 'v3/customers';
    $host = $environment === 'sandbox' ? 'api-sandbox.asaas.com' : 'api.asaas.com';
    if ($case === 'changed_environment') $client->setEnvironment($environment === 'sandbox' ? Asaas\Sdk\Http\Environment::Production : Asaas\Sdk\Http\Environment::Sandbox);
    if ($case === 'absolute_host') $path = 'https://synthetic-third-party.invalid/v3/customers';
    if ($case === 'insecure_scheme') $path = 'http://' . $host . '/v3/customers';
    if ($case === 'alternate_port') $path = 'https://' . $host . ':8443/v3/customers';
    if ($case === 'userinfo') $path = 'https://synthetic-user@' . $host . '/v3/customers';
    $provider->responses(remoteCustomer()); $blocked = false;
    try { $client->request('POST', $path, [], [], ['name' => 'Synthetic Customer', 'email' => 'synthetic@example.invalid']); }
    catch (TransportException $error) { $blocked = $error->getPrevious() instanceof DomainException && $error->getPrevious()->getMessage() === 'asaas_request_origin_mismatch'; }
    assertContact($blocked && $provider->history === [] && $provider->transport->count() === 1, 'origin mismatch never transmits credentials/contacts: ' . $case);
}

// Recoverable reads retain bounded retries in every provider factory.
foreach (['production', 'sandbox'] as $environment) foreach (['selected', 'environment', 'sdk'] as $factory) foreach (['GET', 'HEAD'] as $method) foreach (['500', '429', 'timeout'] as $failure) {
    [$gateway, $adapter, $provider] = contactFixture($environment);
    $client = retryFixtureClient($provider, $factory, $environment);
    uncertainResponse($provider, $failure); $provider->responses(remoteCustomer());
    $result = $client->request($method, 'v3/customers/cus_synthetic');
    assertContact(count($provider->history) === 2 && $result['id'] === 'cus_synthetic', 'read retries once then returns canonical response');
    assertContact(count(array_filter($provider->history, fn($entry) => $entry['request']->getMethod() !== $method)) === 0, 'read retry preserves method');
}
[$gateway, $adapter, $provider] = contactFixture('sandbox');
$client = $provider->client();
for ($i = 0; $i < 5; $i++) uncertainResponse($provider, '500');
try { $client->request('GET', 'v3/customers'); } catch (ApiException $expected) {}
assertContact(count($provider->history) === 4 && $provider->transport->count() === 1, 'read retry remains bounded to three retries');

// The actual invoice flow keeps its durable origin in review after uncertain POST.
foreach (['production', 'sandbox'] as $environment) foreach (['500', 'timeout'] as $failure) {
    [$gateway, $adapter, $provider, $db] = contactFixture($environment);
    $provider->responses(['data' => [['id' => 'wallet_synthetic']], 'hasMore' => false], remoteCustomer());
    uncertainResponse($provider, $failure); $provider->responses(remotePayment());
    $payload = ['externalReference' => 'synthetic_invoice_hash', 'customer' => 'cus_synthetic', 'value' => 100];
    try { $adapter->createPayment($payload); } catch (ApiException | TransportException $expected) {}
    $origin = $adapter->originStore()->byInvoice(1);
    assertContact($origin['state'] === 'review_required' && $origin['remote_id'] === null, 'uncertain payment retains pending origin for reconciliation');
    assertContact(count($db->tables['tblasaas_payment_origins']) === 1 && $db->tables['tblasaas_payments_map'] === [], 'uncertain payment is never marked issued locally');
    $before = count($provider->history); $blocked = false;
    try { $adapter->createPayment($payload); } catch (RuntimeException $error) { $blocked = $error->getMessage() === 'issuance_requires_reconciliation'; }
    assertContact($blocked && count($provider->history) === $before && $provider->transport->count() === 1, 'repeat issuance cannot replay uncertain POST');
    assertContact(count(array_filter($provider->history, fn($entry) => $entry['request']->getMethod() === 'POST')) === 1, 'one payment POST across original/repeated operation');
}

// Customer creation errors never become an empty lookup, another creation or a
// successful local mapping. No provider side effect is guessed after timeout.
foreach (['production', 'sandbox'] as $environment) foreach (['500', 'timeout'] as $failure) {
    [$gateway, $adapter, $provider, $db] = contactFixture($environment);
    $provider->responses(['data' => [], 'totalCount' => 0]); uncertainResponse($provider, $failure); $provider->responses(remoteCustomer());
    $failed = false;
    try { $adapter->ensureCustomerFromClient($db->tables['tblclients'][0]); } catch (ApiException | TransportException $expected) { $failed = true; }
    assertContact($failed && count($provider->history) === 2 && $provider->transport->count() === 1, 'uncertain customer POST has no automatic recreation');
    assertContact($db->tables['tblasaas_customers_map'] === [] && empty($db->tables['tblclients'][0]['asaas_customer_id']), 'uncertain customer is not claimed as mapped');
}

echo "Asaas mutation retry policy: {$checks} assertions passed; real SDK middleware, synthetic transport only.\n";
