<?php

declare(strict_types=1);
namespace Asaas;
require_once __DIR__ . '/ModuleReadiness.php';

/** Legacy gateway profiles only. Named companies use CompanyService/account rows. */
final class EnvironmentSettings
{
    public const ENVIRONMENTS = ['production', 'sandbox'];
    public const DEFAULTS = [
        'active' => '0', 'label' => 'Asaas', 'default_selected' => '1',
        'debug' => '0', 'file_log_enabled' => '0',
        'sync_enabled' => '0', 'sync_days' => '7', 'sync_limit' => '50',
        'currencies' => 'BRL', 'description' => 'Pagamento da Fatura {invoice_number}',
        'interest_value' => '0.00', 'fine_value' => '0.00',
        'discount_type' => '1', 'discount_value' => '0', 'discount_days' => '0',
        'installmentCount' => '1', 'diasdevencimento' => '3',
        'billet_only' => '1', 'card_only' => '1', 'pix_only' => '1',
        'delete_charge' => '0', 'update_charge' => '0', 'disable_charge_notification' => '1',
        'send_customer_email' => '0', 'send_customer_mobile' => '0',
        'ultima_atualizacao_servicos_municipais' => '1',
    ];
    public const READ_ONLY = ['sync_enabled', 'sync_days', 'sync_limit', 'installmentCount', 'ultima_atualizacao_servicos_municipais'];
    public const BOOLEANS = ['active', 'default_selected', 'debug', 'file_log_enabled', 'discount_type',
        'billet_only', 'card_only', 'pix_only', 'delete_charge', 'update_charge',
        'disable_charge_notification', 'send_customer_email', 'send_customer_mobile'];

    public static function assertLegacyScope(): void
    {
        if (class_exists('CompanySettingsRuntime', false) && \CompanySettingsRuntime::active()
            && !\CompanySettingsRuntime::isPrimary()) {
            throw new \DomainException('asaas_company_service_required');
        }
    }

    public static function ready(): bool
    {
        if (!function_exists('get_option') || get_option('paymentmethod_asaas_profile_schema') !== '1' || !function_exists('get_instance')) return false;
        $ci = &get_instance();
        return isset($ci->db) && ModuleReadiness::ready154($ci->db, db_prefix());
    }

    public static function requireReady(): void
    {
        if (!self::ready()) throw new \DomainException('asaas_profile_migration_required');
    }

    public static function environment(string $environment): string
    {
        if (!in_array($environment, self::ENVIRONMENTS, true)) {
            throw new \InvalidArgumentException('invalid_asaas_profile_environment');
        }
        return $environment;
    }

    public static function fromSelector($value): string
    {
        if ($value !== '0' && $value !== '1') {
            throw new \InvalidArgumentException('asaas_environment_requires_explicit_selection');
        }
        return $value === '1' ? 'sandbox' : 'production';
    }

    public static function option(string $field, string $environment): string
    {
        self::environment($environment);
        if (!array_key_exists($field, self::DEFAULTS)) {
            throw new \InvalidArgumentException('unknown_asaas_profile_setting');
        }
        return 'paymentmethod_asaas_profile_' . $environment . '_' . $field;
    }

    public static function originEnvironment(array $origin): string
    {
        if (($origin['issuer_id'] ?? null) !== null || ($origin['provider'] ?? '') !== 'asaas'
            || ($origin['channel'] ?? '') !== 'api') {
            throw new \DomainException('asaas_legacy_profile_scope_mismatch');
        }
        return self::environment((string) ($origin['environment'] ?? ''));
    }

    /** $read returns null only for an absent option; an explicitly empty value is data. */
    public static function read(string $field, string $environment, callable $read): string
    {
        $value = $read(self::option($field, $environment));
        return $value === null ? self::DEFAULTS[$field] : (string) $value;
    }

    /** Retain native renderer/encryption contracts, including native gateway settings. */
    public static function expandDefinitions(array $definitions): array
    {
        $result = [];
        foreach ($definitions as $definition) {
            $field = $definition['name'];
            if (!array_key_exists($field, self::DEFAULTS)) {
                $result[] = $definition;
                continue;
            }
            foreach (self::ENVIRONMENTS as $environment) {
                $profile = $definition;
                $profile['name'] = 'profile_' . $environment . '_' . $field;
                $profile['default_value'] = self::DEFAULTS[$field];
                $profile['asaas_environment'] = $environment;
                $profile['asaas_setting'] = $field;
                $profile['field_attributes']['data-asaas-environment'] = $environment;
                $profile['field_attributes']['data-asaas-setting'] = $field;
                if (in_array($field, self::READ_ONLY, true)) {
                    $profile['type'] = 'input';
                    $profile['field_attributes']['disabled'] = true;
                    $profile['field_attributes']['readonly'] = true;
                    $profile['after'] = '<p class="text-muted" data-asaas-profile-note="' . $profile['name'] . '">' . ($field === 'installmentCount'
                        ? 'Cartão disponível somente à vista. Valor legado preservado, sem efeito no checkout.'
                        : ($field === 'ultima_atualizacao_servicos_municipais'
                            ? 'Informação preservada deste ambiente; sem atualização manual.'
                            : 'Parâmetro legado preservado; não controla a reconciliação automática atual.')) . '</p>';
                }
                if ($field === 'currencies') {
                    $profile['after'] = '<p class="text-muted" data-asaas-profile-note="' . $profile['name'] . '">Esta integração emite cobranças somente em BRL.</p>';
                }
                $result[] = $profile;
            }
        }
        return $result;
    }

    public static function validateValue(string $field, $value): string
    {
        if (!array_key_exists($field, self::DEFAULTS) || !is_string($value)) {
            throw new \InvalidArgumentException('invalid_asaas_profile_value');
        }
        if (in_array($field, self::BOOLEANS, true)) {
            if (!in_array($value, ['0', '1'], true)) throw new \InvalidArgumentException('invalid_asaas_profile_boolean');
        } elseif (in_array($field, ['interest_value', 'fine_value', 'discount_value'], true)) {
            if (!preg_match('/^(?:0|[1-9][0-9]{0,8})(?:\.[0-9]{1,2})?$/D', $value)) throw new \InvalidArgumentException('invalid_asaas_profile_decimal');
        } elseif (in_array($field, ['discount_days', 'diasdevencimento'], true)) {
            if (!preg_match('/^(?:0|[1-9][0-9]{0,3})$/D', $value)) throw new \InvalidArgumentException('invalid_asaas_profile_days');
        } elseif ($field === 'currencies') {
            if ($value !== 'BRL') throw new \InvalidArgumentException('asaas_profile_requires_brl');
        } elseif ($field === 'label') {
            if (trim($value) === '' || strlen($value) > 191 || preg_match('/[\x00-\x1F\x7F]/', $value)) throw new \InvalidArgumentException('invalid_asaas_profile_label');
        } elseif ($field === 'description') {
            if (strlen($value) > 500 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) throw new \InvalidArgumentException('invalid_asaas_profile_description');
        }
        return $value;
    }
}
