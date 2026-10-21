<?php

declare(strict_types=1);
namespace Asaas;

/** Authenticated native encryption, bound to one receiver; hashes still authenticate ingress. */
final class WebhookTokenCipher
{
    public function __construct(private $encrypt = null, private $decrypt = null) {}

    private function crypt(string $operation, string $value)
    {
        try {
            $callback = $this->$operation;
            if ($callback !== null) return $callback($value);
            $ci = &get_instance();
            $ci->load->library('encryption');
            return $ci->encryption->$operation($value);
        } catch (\Throwable $error) {
            throw new \RuntimeException('webhook_token_cipher_unavailable');
        }
    }

    private static function context(array $row, string $kind): array
    {
        $publicId = $row[$kind === 'company' ? 'webhook_public_id' : 'public_id'] ?? null;
        $issuer = $row['issuer_id'] ?? null;
        if (!in_array($kind, ['legacy', 'company'], true)
            || ($kind === 'legacy' ? $issuer !== null : filter_var($issuer, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) === false)
            || !is_string($publicId) || !preg_match('/^[a-f0-9]{32}$/D', $publicId)
            || !in_array($row['environment'] ?? '', ['production', 'sandbox'], true)
            || !is_string($row['account_id'] ?? null) || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $row['account_id'])) {
            throw new \RuntimeException('webhook_token_context_invalid');
        }
        return ['purpose'=>'asaas-webhook-v1', 'kind'=>$kind, 'issuer_id'=>$issuer === null ? null : (int)$issuer,
            'account_id'=>$row['account_id'], 'environment'=>$row['environment'], 'public_id'=>$publicId];
    }

    public function seal(string $token, array $row, string $kind): string
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) throw new \RuntimeException('webhook_token_invalid');
        $payload = json_encode(self::context($row, $kind) + ['token'=>$token], JSON_THROW_ON_ERROR);
        $cipher = $this->crypt('encrypt', $payload);
        if (!is_string($cipher) || $cipher === '' || hash_equals($payload, $cipher) || str_contains($cipher, $token)) {
            throw new \RuntimeException('webhook_token_cipher_unavailable');
        }
        if (!hash_equals($token, $this->open($cipher, hash('sha256', $token), $row, $kind))) {
            throw new \RuntimeException('webhook_token_cipher_unavailable');
        }
        return $cipher;
    }

    public function open(?string $cipher, string $hash, array $row, string $kind): string
    {
        if ($cipher === null || $cipher === '') throw new \DomainException('webhook_token_regeneration_required');
        $plain = $this->crypt('decrypt', $cipher);
        try { $payload = is_string($plain) ? json_decode($plain, true, 8, JSON_THROW_ON_ERROR) : null; }
        catch (\Throwable $error) { $payload = null; }
        if (!is_array($payload)) throw new \RuntimeException('webhook_token_unavailable');
        $token = $payload['token'] ?? null;
        unset($payload['token']);
        if ($payload !== self::context($row, $kind) || !is_string($token)
            || !preg_match('/^[a-f0-9]{64}$/D', $token) || !hash_equals($hash, hash('sha256', $token))) {
            throw new \RuntimeException('webhook_token_unavailable');
        }
        return $token;
    }
}
