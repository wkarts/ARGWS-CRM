<?php

/** Versioned wire contract shared with PIGE. No framework, credentials or network side effects. */
final class PigeBridgeProtocol
{
    public const REQUEST_PATH = '/pige_bridge/api/v1/requests';
    public const DIAGNOSTIC_PATH = '/pige_bridge/api/v1/diagnostics';
    public const MAX_BODY_BYTES = 16384;

    private static function exactKeys(array $value, array $expected): void
    {
        $actual = array_keys($value);
        sort($actual);
        sort($expected);
        if ($actual !== $expected) {
            throw new InvalidArgumentException('unexpected_fields');
        }
    }

    private static function isUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $value);
    }

    public static function signature(string $secret, string $timestamp, string $eventId, string $method, string $path, string $body): string
    {
        return 'sha256=' . hash_hmac('sha256', implode("\n", [$timestamp, $eventId, $method, $path, $body]), $secret);
    }

    public static function decode(string $body): array
    {
        if (strlen($body) > self::MAX_BODY_BYTES) {
            throw new InvalidArgumentException('body_too_large');
        }
        $value = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($value) || ($value['schema_version'] ?? null) !== 1 || ($value['event_type'] ?? null) !== 'charge.requested') {
            throw new InvalidArgumentException('unsupported_envelope');
        }
        if (!is_string($value['event_id'] ?? null) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $value['event_id'])) {
            throw new InvalidArgumentException('invalid_event_id');
        }
        self::exactKeys($value, ['schema_version', 'event_id', 'event_type', 'scope', 'data']);
        $canonical = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR);
        if ($canonical !== $body) {
            throw new InvalidArgumentException('noncanonical_json');
        }
        $scope = $value['scope'] ?? null;
        $data = $value['data'] ?? null;
        if (!is_array($scope) || !is_array($data)) {
            throw new InvalidArgumentException('invalid_envelope');
        }
        self::exactKeys($scope, ['school_id', 'issuer_id', 'account_id', 'environment']);
        self::exactKeys($data, ['charge_id', 'amount_cents', 'currency', 'due_date', 'description']);
        foreach (['issuer_id'] as $field) {
            if (!is_string($scope[$field] ?? null) || !preg_match('/^[1-9][0-9]{0,18}$/D', $scope[$field])) {
                throw new InvalidArgumentException('invalid_scope');
            }
        }
        if (!is_string($scope['school_id'] ?? null) || !self::isUuid($scope['school_id'])) {
            throw new InvalidArgumentException('invalid_school_id');
        }
        if (!is_string($scope['account_id'] ?? null) || !preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D', $scope['account_id']) || !in_array($scope['environment'] ?? null, ['sandbox', 'production'], true)) {
            throw new InvalidArgumentException('invalid_scope');
        }
        if (!is_string($data['charge_id'] ?? null) || !self::isUuid($data['charge_id'])) {
            throw new InvalidArgumentException('invalid_charge_id');
        }
        if (!is_int($data['amount_cents'] ?? null) || $data['amount_cents'] <= 0 || $data['amount_cents'] > 999999999999 || ($data['currency'] ?? null) !== 'BRL') {
            throw new InvalidArgumentException('invalid_amount');
        }
        $date = $data['due_date'] ?? null;
        if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
            throw new InvalidArgumentException('invalid_due_date');
        }
        if (!is_string($data['description'] ?? null) || preg_match('/^[\s\p{Z}\x{0085}]*$/u', $data['description']) || strlen($data['description']) > 2000 || preg_match_all('/./us', $data['description']) > 500 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $data['description'])) {
            throw new InvalidArgumentException('invalid_description');
        }
        return $value;
    }

    public static function decodeDiagnostic(string $body): array
    {
        if(strlen($body)>self::MAX_BODY_BYTES)throw new InvalidArgumentException('body_too_large');
        $value=json_decode($body,true,16,JSON_THROW_ON_ERROR);
        if(!is_array($value)||($value['schema_version']??null)!==1||($value['event_type']??null)!=='connection.test'||!is_array($value['data']??null))throw new InvalidArgumentException('unsupported_diagnostic');
        self::exactKeys($value,['schema_version','event_id','event_type','scope','data']);self::exactKeys($value['data'],['nonce']);
        if(!is_string($value['data']['nonce'])||!self::isUuid($value['data']['nonce'])||json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_LINE_TERMINATORS|JSON_THROW_ON_ERROR)!==$body)throw new InvalidArgumentException('invalid_diagnostic');
        $probe=$value;$probe['event_type']='charge.requested';$probe['data']=['charge_id'=>$value['data']['nonce'],'amount_cents'=>1,'currency'=>'BRL','due_date'=>'2030-01-01','description'=>'Connection diagnostic'];
        self::decode(json_encode($probe,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_LINE_TERMINATORS|JSON_THROW_ON_ERROR));return $value;
    }

    /** Select exactly one explicitly configured scope; never fall back to another issuer/account. */
    public static function authenticate(array $envelope, string $body, array $headers, array $connections, int $now, string $path=self::REQUEST_PATH): string
    {
        if(!in_array($path,[self::REQUEST_PATH,self::DIAGNOSTIC_PATH,'/pige_bridge/api/v2/invoices','/pige_bridge/api/v2/invoices/status','/pige_bridge/api/v3/charges','/pige_bridge/api/v3/charges/status','/pige_bridge/api/v3/charges/sync','/pige_bridge/api/v3/charges/instruments','/pige_bridge/api/v3/charges/reconcile'],true))throw new RuntimeException('unauthorized');
        $timestamp = $headers['timestamp'] ?? '';
        $eventId = $headers['event_id'] ?? '';
        $signature = $headers['signature'] ?? '';
        if (!is_string($timestamp) || !preg_match('/^[1-9][0-9]{0,10}$/D', $timestamp) || abs($now - (int) $timestamp) > 300 || $eventId !== $envelope['event_id'] || !is_string($signature) || !preg_match('/^sha256=[0-9a-f]{64}$/D', $signature)) {
            throw new RuntimeException('unauthorized');
        }
        $matches = [];
        foreach ($connections as $key => $connection) {
            if (!is_string($key) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $key) || !is_array($connection) || ($connection['enabled'] ?? false) !== true || !is_string($connection['secret'] ?? null) || strlen($connection['secret']) < 32) {
                continue;
            }
            $same = true;
            foreach (['school_id', 'issuer_id', 'account_id', 'environment'] as $field) {
                if (($connection['scope'][$field] ?? null) !== $envelope['scope'][$field]) {
                    $same = false;
                }
            }
            if ($same) {
                $matches[$key] = $connection;
            }
        }
        if (count($matches) !== 1) {
            throw new RuntimeException('unauthorized');
        }
        $key = array_key_first($matches);
        $expected = self::signature($matches[$key]['secret'], $timestamp, $eventId, 'POST', $path, $body);
        if (!hash_equals($expected, $signature)) {
            throw new RuntimeException('unauthorized');
        }
        return $key;
    }

    public static function receipt(array $row): array
    {
        return [
            'schema_version' => 1,
            'event_id' => $row['event_id'],
            'request_id' => $row['request_id'],
            'status' => 'blocked',
            'reason' => 'issuer_finance_not_isolated',
            'capabilities' => ['issue_charge' => false],
        ];
    }
}
