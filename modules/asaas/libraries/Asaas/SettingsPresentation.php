<?php
declare(strict_types=1);
namespace Asaas;

require_once __DIR__ . '/WebhookAdminPresentation.php';
require_once __DIR__ . '/WebhookIntegrationStore.php';
require_once __DIR__ . '/../../vendor/argws/asaas-sdk-php/src/Http/Environment.php';

/** Read-only settings DTOs. Never hydrate credentials, token hashes or request-derived URLs. */
final class SettingsPresentation
{
    public static function apiUrls(): array
    {
        return [
            'production' => \Asaas\Sdk\Http\Environment::Production->value,
            'sandbox' => \Asaas\Sdk\Http\Environment::Sandbox->value,
        ];
    }

    public static function legacy($db, string $base, string $indexPage = ''): array
    {
        $data = ['authorized' => false, 'state' => 'restricted', 'integrations' => [], 'api_urls' => self::apiUrls()];
        try {
            if (!is_admin() || (class_exists('CompanySettingsRuntime', false)
                && \CompanySettingsRuntime::active() && !\CompanySettingsRuntime::isPrimary())) {
                return $data;
            }
            $data['authorized'] = true;
            if (!(new WebhookIntegrationStore($db))->ready()) {
                $data['state'] = 'schema_pending';
                return $data;
            }
            if (WebhookAdminPresentation::callbackUrl($base, str_repeat('0', 32), $indexPage) === null) {
                $data['state'] = 'invalid_base';
                return $data;
            }
            // Select only public presentation data; the legacy store is never a tenant fallback.
            $rows = $db->select('id,issuer_id,provider,label,account_id,environment,enabled,public_id')
                ->where('issuer_id', null)->where('provider', 'asaas')->order_by('id', 'DESC')
                ->get(db_prefix() . 'asaas_webhook_integrations')->result_array();
            foreach ($rows as $row) {
                if (!array_key_exists('issuer_id', $row) || $row['issuer_id'] !== null
                    || ($row['provider'] ?? null) !== 'asaas'
                    || !in_array($row['environment'] ?? null, ['production', 'sandbox'], true)) {
                    continue;
                }
                $url = WebhookAdminPresentation::callbackUrl($base, (string) ($row['public_id'] ?? ''), $indexPage);
                if ($url === null) { continue; }
                $data['integrations'][] = [
                    'id' => (int) $row['id'], 'label' => (string) $row['label'],
                    'account_id' => (string) $row['account_id'], 'environment' => $row['environment'],
                    'enabled' => (int) $row['enabled'] === 1, 'callback_url' => $url,
                ];
            }
            $data['state'] = $data['integrations'] ? 'ready' : 'empty';
        } catch (\Throwable $error) {
            // Do not render or log exception details, configuration values or provider payloads.
            $data['state'] = 'unavailable';
            $data['integrations'] = [];
        }
        return $data;
    }
}
