<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__ . '/EnvironmentSettings.php';

final class AdminSettingsPolicy
{
    public static function validate(array $data): array
    {
        $settings = $data['settings'] ?? [];
        if (!is_array($settings)) throw new \InvalidArgumentException('invalid_asaas_settings');
        foreach ($settings as $key => $value) {
            if (!is_string($key) || !str_starts_with($key, 'paymentmethod_asaas_')) continue;
            EnvironmentSettings::assertLegacyScope();
            EnvironmentSettings::requireReady();
            if ($key === 'paymentmethod_asaas_sandbox') {
                EnvironmentSettings::fromSelector($value);
                continue;
            }
            if (in_array($key, ['paymentmethod_asaas_api_key', 'paymentmethod_asaas_api_key_sandbox'], true)) {
                if (!is_string($value) || strlen($value) > 4096 || preg_match('/[\x00-\x20\x7F]/', $value)) {
                    throw new \InvalidArgumentException('invalid_asaas_api_key');
                }
                continue;
            }
            if (!preg_match('/^paymentmethod_asaas_profile_(production|sandbox)_(.+)$/D', $key, $match)
                || !array_key_exists($match[2], EnvironmentSettings::DEFAULTS)) {
                throw new \InvalidArgumentException('unknown_asaas_setting');
            }
            if (in_array($match[2], EnvironmentSettings::READ_ONLY, true)) {
                // Disabled browser fields are no authority to overwrite preserved legacy data.
                unset($data['settings'][$key]);
                continue;
            }
            EnvironmentSettings::validateValue($match[2], $value);
        }
        return $data;
    }
}
