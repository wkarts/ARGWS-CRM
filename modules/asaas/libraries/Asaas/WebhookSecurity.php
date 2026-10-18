<?php
declare(strict_types=1);
namespace Asaas;

/** Authentication is the provider's documented header, not a fabricated HMAC. */
final class WebhookSecurity
{
    public static function validToken(string $token): bool
    {
        return strlen($token) >= 32 && strlen($token) <= 255
            && !preg_match('/\s/', $token) && !str_starts_with($token, '$aact_');
    }

    public static function validate(string $secret, array $headers, string $rawBody): array
    {
        return self::validateDigest(self::validToken($secret) ? hash('sha256', $secret) : '', $headers, $rawBody);
    }

    public static function validateDigest(string $digest, array $headers, string $rawBody): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $digest)) {
            return self::error(503, 'Webhook indisponível.');
        }
        $normalized = [];
        foreach ($headers as $name => $value) {
            $name = strtolower((string) $name);
            if (array_key_exists($name, $normalized) || !is_string($value)) {
                return self::error(401, 'Não autorizado.');
            }
            $normalized[$name] = $value;
        }
        $token = $normalized['asaas-access-token'] ?? '';
        if (!self::validToken($token) || !hash_equals($digest, hash('sha256', $token))) {
            return self::error(401, 'Não autorizado.');
        }
        $type = strtolower(trim(explode(';', $normalized['content-type'] ?? '')[0]));
        if ($type !== 'application/json') {
            return self::error(415, 'Conteúdo deve ser JSON.');
        }
        if ($rawBody === '' || strlen($rawBody) > 1048576) {
            return self::error(413, 'Conteúdo ausente ou muito grande.');
        }
        $payload = json_decode($rawBody, true, 32);
        if (!is_array($payload) || array_is_list($payload) || json_last_error() !== JSON_ERROR_NONE) {
            return self::error(400, 'JSON inválido.');
        }
        $id = $payload['id'] ?? null;
        $event = $payload['event'] ?? null;
        if (!is_string($id) || !preg_match('/^[a-zA-Z0-9_&-]{1,128}$/D', $id)
            || !is_string($event) || !preg_match('/^[A-Z][A-Z0-9_]{1,63}$/D', $event)) {
            return self::error(400, 'Identificação do evento inválida.');
        }
        // Persist only the minimum needed for canonical reconciliation. Never
        // retain untrusted customer, card, token or bank data from the payload.
        $safe = ['id' => $id, 'event' => $event];
        if (str_starts_with($event, 'PAYMENT_')) {
            $p = $payload['payment'] ?? null;
            if (!is_array($p) || !is_string($p['id'] ?? null)
                || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $p['id'])) {
                return self::error(400, 'Cobrança do evento inválida.');
            }
            $safe['payment'] = ['id' => $p['id']];
        }
        return ['ok' => true, 'status' => 200, 'message' => 'Evento autenticado.',
            'event_id' => $id, 'event' => $event, 'payload' => $safe,
            'payload_hash' => hash('sha256', json_encode($safe, JSON_UNESCAPED_SLASHES))];
    }

    private static function error(int $status, string $message): array
    {
        return ['ok' => false, 'status' => $status, 'message' => $message];
    }
}
