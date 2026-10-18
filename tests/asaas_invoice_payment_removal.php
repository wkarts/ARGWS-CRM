<?php

declare(strict_types=1);

require_once __DIR__ . '/../modules/asaas/libraries/Asaas/InvoicePaymentRemoval.php';

use Asaas\InvoicePaymentRemoval;

$count = 0;
function check(bool $condition, string $message): void
{
    global $count;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $count++;
}

function runRemoval(array $responses, bool $receipts = false, string $id = 'pay_test', string $hash = 'invoice_hash', ?array $origin = null, ?array $executionAccount = null): array
{
    $calls = [];
    $service = new InvoicePaymentRemoval(static function (string $method, string $path) use (&$calls, &$responses): array {
        $calls[] = [$method, $path];
        if ($responses === []) {
            throw new RuntimeException('Unexpected remote call');
        }
        $response = array_shift($responses);
        if ($response instanceof Throwable) {
            throw $response;
        }
        return $response;
    });
    $origin = $origin ?? ['provider' => 'asaas', 'channel' => 'api', 'account_id' => 'account_original', 'environment' => 'sandbox', 'remote_id' => $id];
    $executionAccount = $executionAccount ?? ['provider' => 'asaas', 'channel' => 'api', 'account_id' => 'account_original', 'environment' => 'sandbox'];
    return [$service->remove($id, $hash, $receipts, $origin, $executionAccount), $calls];
}

$pending = ['id' => 'pay_test', 'status' => 'PENDING', 'externalReference' => 'invoice_hash'];
$ok = static fn(array $data): array => ['success' => true, 'data' => $data, 'error' => null];
$deleted = $ok(['id' => 'pay_test', 'deleted' => true]);
foreach (['PENDING', 'OVERDUE'] as $state) {
    [$result, $calls] = runRemoval([$ok(array_replace($pending, ['status' => $state])), $deleted]);
    check($result['success'] && $result['code'] === 'deleted', 'Unpaid charge must be removed');
    check($calls === [['GET', '/payments/pay_test'], ['DELETE', '/payments/pay_test']], 'Use GET then official DELETE');
}

foreach (['CONFIRMED', 'RECEIVED', 'RECEIVED_IN_CASH', 'REFUNDED', 'PARTIALLY_REFUNDED', 'REFUND_REQUESTED', 'CHARGEBACK_REQUESTED', 'AUTHORIZED', 'UNKNOWN', ''] as $state) {
    [$result, $calls] = runRemoval([$ok(array_replace($pending, ['status' => $state]))]);
    check(!$result['success'] && $result['code'] === 'remote_state_requires_review', 'Protected state: ' . $state);
    check(count($calls) === 1, 'Protected states must not DELETE');
}

[$result, $calls] = runRemoval([], true);
check($result['code'] === 'local_receipts_require_review' && $calls === [], 'Any local receipt blocks removal');
foreach (['', '../pay_other', 'pay_a?x=1'] as $id) {
    [$result, $calls] = runRemoval([], false, $id);
    check(!$result['success'] && $calls === [], 'Reject invalid links');
}
foreach ([[], ['success' => false, 'error' => ['status' => 404]], ['success' => false, 'error' => ['status' => 401]], new RuntimeException('timeout')] as $error) {
    [$result, $calls] = runRemoval([$error]);
    check(!$result['success'] && count($calls) === 1, 'Lookup errors cannot authorize deletion');
}
[$result, $calls] = runRemoval([$ok(array_replace($pending, ['externalReference' => 'other_invoice']))]);
check($result['code'] === 'reference_mismatch' && count($calls) === 1, 'Never remove another invoice charge');
[$result, $calls] = runRemoval([$ok(array_replace($pending, ['id' => 'pay_other']))]);
check($result['code'] === 'invalid_remote_payment' && count($calls) === 1, 'Validate returned payment ID');
[$result, $calls] = runRemoval([$ok(array_replace($pending, ['deleted' => true]))]);
check($result['success'] && $result['code'] === 'already_deleted' && count($calls) === 1, 'Repeated removal is read-only');
foreach ([[], ['success' => false], $ok([]), $ok(['id' => 'pay_other', 'deleted' => true]), $ok(['id' => 'pay_test', 'deleted' => false]), new RuntimeException('timeout')] as $error) {
    [$result, $calls] = runRemoval([$ok($pending), $error]);
    check(!$result['success'] && $result['code'] === 'removal_unconfirmed', 'Never falsely confirm failed DELETE');
    check(count($calls) === 2, 'No blind retries or refund fallback');
}
$origin = ['provider' => 'asaas', 'channel' => 'api', 'account_id' => 'account_original', 'environment' => 'sandbox', 'remote_id' => 'pay_test'];
$execution = ['provider' => 'asaas', 'channel' => 'api', 'account_id' => 'account_original', 'environment' => 'sandbox'];
foreach ([[], array_replace($origin, ['provider' => 'other_bank']), array_replace($origin, ['channel' => 'cnab']), array_replace($origin, ['account_id' => '']), array_replace($origin, ['environment' => '']), array_replace($origin, ['remote_id' => 'pay_other'])] as $invalidOrigin) {
    [$result, $calls] = runRemoval([], false, 'pay_test', 'invoice_hash', $invalidOrigin, $execution);
    check(!$result['success'] && $calls === [], 'Unknown or unsupported origin must never reach Asaas');
}
foreach ([[], array_replace($execution, ['provider' => 'other_bank']), array_replace($execution, ['channel' => 'cnab']), array_replace($execution, ['account_id' => 'account_new']), array_replace($execution, ['environment' => 'production'])] as $wrongAccount) {
    [$result, $calls] = runRemoval([], false, 'pay_test', 'invoice_hash', $origin, $wrongAccount);
    check($result['code'] === 'origin_account_mismatch' && $calls === [], 'Changed account/environment must not delete original charge');
}
// Different credentials can rotate within the same proven account identity;
// the guard compares immutable account identity, never secret/API key values.
[$result, $calls] = runRemoval([$ok($pending), $deleted], false, 'pay_test', 'invoice_hash', $origin, $execution);
check($result['success'] && count($calls) === 2, 'Matching proven origin remains eligible');
echo "OK: {$count} assertions; no network requests.\n";
