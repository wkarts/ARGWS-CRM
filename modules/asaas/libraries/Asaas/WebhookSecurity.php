<?php

declare(strict_types=1);

namespace Asaas;

final class WebhookSecurity
{
    /**
     * Os webhooks do Asaas usam "asaas-access-token" (não Bearer/API key).
     * O token é independente da chave usada para realizar operações na API.
     *
     * @return array{ok: bool, status: int, message: string, event_id?: string, event?: string, payload?: array}
     */
    public static function validate(string $secret, array $headers, string $rawBody): array
    {
        // Não aceitar webhooks enquanto a autenticação estiver desconfigurada.
        if ($secret === '') {
            return ['ok' => false, 'status' => 503, 'message' => 'Webhook sem autenticação configurada.'];
        }

        $normalized = [];
        foreach ($headers as $header => $value) {
            $normalized[strtolower((string) $header)] = is_array($value) ? (string) reset($value) : (string) $value;
        }
        $provided = $normalized['asaas-access-token'] ?? '';
        if ($provided === '' || !hash_equals($secret, $provided)) {
            return ['ok' => false, 'status' => 401, 'message' => 'Não autorizado.'];
        }

        if ($rawBody === '' || strlen($rawBody) > 1024 * 1024) {
            return ['ok' => false, 'status' => 413, 'message' => 'Conteúdo do evento ausente ou muito grande.'];
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
            return ['ok' => false, 'status' => 400, 'message' => 'JSON inválido.'];
        }

        $eventId = $payload['id'] ?? null;
        $event = $payload['event'] ?? null;
        if (!is_string($eventId) || $eventId === '' || strlen($eventId) > 128
            || !is_string($event) || $event === '' || strlen($event) > 64) {
            return ['ok' => false, 'status' => 400, 'message' => 'Identificação do evento inválida.'];
        }

        // Campos novos na API podem aparecer sem prejudicar a desserialização.
        return ['ok' => true, 'status' => 200, 'message' => 'Evento autenticado.',
            'event_id' => $eventId, 'event' => $event, 'payload' => $payload];
    }
}
