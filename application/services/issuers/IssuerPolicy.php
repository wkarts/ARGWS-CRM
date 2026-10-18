<?php

declare(strict_types=1);

/** Pure validation shared by HTTP, CLI and future integration adapters. */
final class IssuerPolicy
{
    public const CAPABILITIES = ['view', 'manage_clients', 'share_clients', 'manage_integrations'];

    public static function id($value): int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]*$/D', (string) $value)
            || strlen((string) $value) > 10 || (float) $value > 2147483647) {
            throw new DomainException('issuer_invalid_id');
        }
        return (int) $value;
    }

    public static function taxId($value, bool $required = true): ?string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9.\/\-\s]*$/D', $value)) {
            throw new DomainException('issuer_invalid_tax_id');
        }
        $digits = strtoupper(preg_replace('/[.\/\-\s]/', '', $value));
        if ($digits === '' && !$required) {
            return null;
        }
        if ((!preg_match('/^[0-9]{11}$/D', $digits) && !preg_match('/^[A-Z0-9]{12}[0-9]{2}$/D', $digits))
            || preg_match('/^(\d)\1+$/D', $digits)) {
            throw new DomainException('issuer_invalid_tax_id');
        }
        $base = strlen($digits) === 11 ? 9 : 12;
        for ($position = $base; $position < $base + 2; $position++) {
            $sum = 0;
            for ($i = 0; $i < $position; $i++) {
                $weight = $base === 9 ? ($position + 1 - $i) : (($position - 1 - $i) % 8 + 2);
                // Receita Federal: alphanumeric CNPJ uses the uppercase ASCII value minus 48.
                $sum += (ord($digits[$i]) - 48) * $weight;
            }
            $digit = $sum % 11 < 2 ? 0 : 11 - $sum % 11;
            if ((int) $digits[$position] !== $digit) {
                throw new DomainException('issuer_invalid_tax_id');
            }
        }
        return $digits;
    }

    public static function permissions(array $values): array
    {
        foreach ($values as $value) {
            if (!is_string($value) || !in_array($value, self::CAPABILITIES, true)) {
                throw new DomainException('issuer_invalid_permission');
            }
        }
        if (!in_array('view', $values, true) && count($values) > 0) {
            throw new DomainException('issuer_view_required');
        }
        if (in_array('share_clients', $values, true) && !in_array('manage_clients', $values, true)) {
            throw new DomainException('issuer_manage_required');
        }
        return array_values(array_unique($values));
    }

    /** Financial isolation is not delivered by a registry. No option can bypass this gate. */
    public static function assertOperationalScope($issuerId): void
    {
        self::id($issuerId);
        throw new DomainException('issuer_rollout_blocked');
    }
}
