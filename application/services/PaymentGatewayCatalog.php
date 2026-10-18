<?php

namespace app\services;

defined('BASEPATH') or exit('No direct script access allowed');

/** Presentation policy only: never unregister gateways or remove persisted data. */
final class PaymentGatewayCatalog
{
    public const HIDDEN_NATIVE_IDS = [
        'authorize_acceptjs', 'instamojo', 'mollie', 'paypal_braintree',
        'payu_money', 'stripe', 'stripe_ideal', 'two_checkout',
    ];

    public static function idealEnabled(): bool
    {
        return filter_var(getenv('CRM_ENABLE_IDEAL'), FILTER_VALIDATE_BOOLEAN) === true;
    }

    public static function hiddenIds(): array
    {
        // Optional local app-config override, restricted to the eight native IDs.
        $configured = defined('CRM_HIDDEN_NATIVE_PAYMENT_GATEWAYS')
            ? CRM_HIDDEN_NATIVE_PAYMENT_GATEWAYS : self::HIDDEN_NATIVE_IDS;
        $ids = is_array($configured)
            ? array_values(array_intersect(self::HIDDEN_NATIVE_IDS, array_filter($configured, 'is_string')))
            : self::HIDDEN_NATIVE_IDS;
        if (!self::idealEnabled()) {
            $ids[] = 'Ideal_gateway';
        }
        return $ids;
    }

    public static function isHidden($id): bool
    {
        return is_string($id) && in_array($id, self::hiddenIds(), true);
    }

    public static function decodeModes($value): array
    {
        if (is_string($value)) {
            $value = @unserialize($value, ['allowed_classes' => false]);
        }
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_filter($value, static function ($id) {
            return is_string($id) || is_int($id);
        }));
    }

    /** Existing invoice choices remain valid for already-issued links. */
    public static function forSelection(array $gateways, $existingModes = []): array
    {
        $existing = self::decodeModes($existingModes);
        return array_values(array_filter($gateways, static function ($gateway) use ($existing) {
            return !self::isHidden($gateway['id']) || in_array($gateway['id'], $existing, true);
        }));
    }

    /** Preserve old hidden IDs on edit, but never accept newly submitted hidden IDs. */
    public static function normalizeModes($submitted, $existingModes = []): array
    {
        $modes = array_values(array_filter(self::decodeModes($submitted), static function ($id) {
            return !self::isHidden($id);
        }));
        foreach (self::decodeModes($existingModes) as $id) {
            if (self::isHidden($id) && !in_array($id, $modes, true)) {
                $modes[] = $id;
            }
        }
        return array_values(array_unique($modes));
    }

    public static function allowsCheckout($id, $existingModes): bool
    {
        return !self::isHidden($id) || in_array($id, self::decodeModes($existingModes), true);
    }

    /** Exact registered option names avoid matching unrelated Stripe integrations. */
    public static function filterSettings(array $settings, array $gateways): array
    {
        foreach ($gateways as $gateway) {
            if (self::isHidden($gateway['id'])) {
                foreach ($gateway['instance']->getSettings() as $option) {
                    unset($settings[$option['name']]);
                }
            }
        }
        return $settings;
    }
}
