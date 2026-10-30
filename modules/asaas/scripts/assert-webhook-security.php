<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libraries/Asaas/WebhookSecurity.php';

use Asaas\WebhookSecurity;

$secret = str_repeat('A', 40);
$valid = json_encode(['id' => 'evt_test_1', 'event' => 'PAYMENT_RECEIVED',
    'payment' => ['id' => 'pay_test_1']], JSON_THROW_ON_ERROR);

$jsonHeaders = ['Content-Type' => 'application/json'];
$cases = [
    ['válido', $secret, $jsonHeaders + ['Asaas-Access-Token' => $secret], $valid, true],
    ['ausente', $secret, $jsonHeaders, $valid, false],
    ['incorreto', $secret, $jsonHeaders + ['asaas-access-token' => 'outro'], $valid, false],
    ['legado', $secret, $jsonHeaders + ['x-webhook-token' => $secret], $valid, false],
    ['não configurado', '', $jsonHeaders + ['asaas-access-token' => $secret], $valid, false],
    ['content-type ausente', $secret, ['asaas-access-token' => $secret], $valid, false],
    ['json inválido', $secret, $jsonHeaders + ['asaas-access-token' => $secret], '{', false],
    ['sem identificador', $secret, $jsonHeaders + ['asaas-access-token' => $secret], '{"event":"PAYMENT_RECEIVED"}', false],
];

foreach ($cases as [$name, $configured, $headers, $body, $expected]) {
    $result = WebhookSecurity::validate($configured, $headers, $body);
    if ($result['ok'] !== $expected) {
        fwrite(STDERR, 'Falha na validação: ' . $name . PHP_EOL);
        exit(1);
    }
}

echo 'Verificação do webhook: 8 cenários passaram.' . PHP_EOL;
