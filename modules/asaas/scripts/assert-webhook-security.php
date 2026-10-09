<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libraries/Asaas/WebhookSecurity.php';

use Asaas\WebhookSecurity;

$secret = str_repeat('A', 40);
$valid = json_encode(['id' => 'evt_test_1', 'event' => 'PAYMENT_RECEIVED',
    'payment' => ['id' => 'pay_test_1']], JSON_THROW_ON_ERROR);

$cases = [
    ['válido', $secret, ['Asaas-Access-Token' => $secret], $valid, true],
    ['ausente', $secret, [], $valid, false],
    ['incorreto', $secret, ['asaas-access-token' => 'outro'], $valid, false],
    ['legado', $secret, ['x-webhook-token' => $secret], $valid, false],
    ['não configurado', '', ['asaas-access-token' => $secret], $valid, false],
    ['json inválido', $secret, ['asaas-access-token' => $secret], '{', false],
    ['sem identificador', $secret, ['asaas-access-token' => $secret], '{"event":"PAYMENT_RECEIVED"}', false],
];

foreach ($cases as [$name, $configured, $headers, $body, $expected]) {
    $result = WebhookSecurity::validate($configured, $headers, $body);
    if ($result['ok'] !== $expected) {
        fwrite(STDERR, 'Falha na validação: ' . $name . PHP_EOL);
        exit(1);
    }
}

echo 'Verificação do webhook: 7 cenários passaram.' . PHP_EOL;
