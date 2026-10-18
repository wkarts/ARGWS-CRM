<?php
declare(strict_types=1);
namespace Asaas;

final class AdminSettingsPolicy
{
    public static function validate(array $data): array
    {
        $settings = $data['settings'] ?? [];
        if (!is_array($settings)) { throw new \InvalidArgumentException('invalid_asaas_settings'); }
        $key = 'paymentmethod_asaas_sandbox';
        if (array_key_exists($key, $settings) && !in_array($settings[$key], ['0', '1'], true)) {
            throw new \InvalidArgumentException('asaas_environment_requires_explicit_selection');
        }
        // Legacy secret is neither exposed nor overwritten through the generic settings form.
        if (array_key_exists('paymentmethod_asaas_webhook_secret', $settings)) {
            throw new \InvalidArgumentException('asaas_webhook_requires_dedicated_management');
        }
        return $data;
    }
}
