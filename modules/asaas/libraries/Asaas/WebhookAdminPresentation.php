<?php
declare(strict_types=1);
namespace Asaas;

/** Builds display URLs only from explicit server configuration, never request Host/proxy headers. */
final class WebhookAdminPresentation
{
    public static function callbackUrl(string $configuredBase, string $publicId, string $indexPage = ''): ?string
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $publicId)
            || !filter_var($configuredBase, FILTER_VALIDATE_URL)
            || preg_match('/[\x00-\x20\x7f\\\\]/', $configuredBase)) {
            return null;
        }
        $parts = parse_url($configuredBase);
        if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }
        if ($indexPage !== '' && (!preg_match('/^[A-Za-z0-9_-][A-Za-z0-9_.-]*$/D', $indexPage)
            || str_contains($indexPage, '..'))) {
            return null;
        }
        $path = $parts['path'] ?? '';
        if (preg_match('~(?:^|/)\.{1,2}(?:/|$)~', rawurldecode($path))) {
            return null;
        }
        return rtrim($configuredBase, '/') . '/' . ($indexPage === '' ? '' : $indexPage . '/')
            . 'asaas/gateways/callback/integration/' . $publicId;
    }

    public static function companyCallbackUrl(string $configuredBase, string $publicId, string $indexPage = ''): ?string
    {
        $legacy = self::callbackUrl($configuredBase, $publicId, $indexPage);
        return $legacy === null ? null : substr($legacy, 0, -strlen('asaas/gateways/callback/integration/'.$publicId))
            .'company_asaas_callback/integration/'.$publicId;
    }

    public static function statusLabel(string $status): string
    {
        return ['received'=>'Recebido', 'pending'=>'Pendente', 'processing'=>'Em processamento',
            'processed'=>'Processado', 'done'=>'Processado', 'observed'=>'Observado, sem baixa', 'review_required'=>'Revisão necessária',
            'review'=>'Revisão necessária', 'failed'=>'Falha', 'ignored'=>'Ignorado',
            'CONFIRMED'=>'Confirmado no Asaas', 'RECEIVED'=>'Recebido no Asaas', 'PENDING'=>'Pendente no Asaas',
            'OVERDUE'=>'Vencido no Asaas', 'REFUNDED'=>'Estornado no Asaas',
            'RECEIVED_IN_CASH'=>'Recebido em dinheiro no Asaas'][$status] ?? $status;
    }

    /** Allow only event metadata explicitly intended for the admin overview. */
    public static function eventRows(array $events): array
    {
        $safe = [];
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }
            $row = [];
            foreach (['event_id', 'event_type', 'process_status', 'verified_status', 'received_at', 'processed_at', 'attempt_count'] as $key) {
                $value = $event[$key] ?? '';
                $row[$key] = is_scalar($value) ? substr((string) $value, 0, 190) : '';
            }
            $safe[] = $row;
        }
        return $safe;
    }
}
