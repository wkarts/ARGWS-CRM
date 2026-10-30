<?php
declare(strict_types=1);
namespace BancoBrasil;

final class Fields
{
    public static function digits(string $value, int $width): string
    {
        if (!preg_match('/^[0-9]+$/D', $value) || strlen($value) > $width) {
            throw new \InvalidArgumentException('bb_numeric_field_invalid');
        }
        return str_pad($value, $width, '0', STR_PAD_LEFT);
    }
    public static function alpha(string $value, int $width, bool $left = false): string
    {
        // No silent truncation/transliteration: the caller must review a changed legal name/address.
        if ($value === '' || trim($value, ' ') !== $value || !preg_match('/^[A-Z0-9 ]+$/D', $value) || strlen($value) > $width) {
            throw new \InvalidArgumentException('bb_alpha_field_invalid');
        }
        return str_pad($value, $width, ' ', $left ? STR_PAD_RIGHT : STR_PAD_LEFT);
    }
    public static function taxId(string $value): string
    {
        $normalized = strtoupper(str_replace(['.', '/', '-', ' '], '', $value));
        if (!preg_match('/^(?:[0-9]{11}|[A-Z0-9]{12}[0-9]{2})$/D', $normalized)) {
            throw new \InvalidArgumentException('bb_tax_id_invalid');
        }
        return $normalized;
    }
    public static function taxField(string $value, int $width): string
    {
        return str_pad(self::taxId($value), $width, '0', STR_PAD_LEFT);
    }
    public static function cents(string $amount): int
    {
        if (!preg_match('/^(0|[1-9][0-9]{0,12})\.([0-9]{2})$/D', $amount, $m)) {
            throw new \InvalidArgumentException('bb_amount_invalid');
        }
        return (int) $m[1] * 100 + (int) $m[2];
    }
    public static function amount(int $cents): string
    {
        if ($cents < 0 || $cents > 999999999999999) throw new \InvalidArgumentException('bb_amount_invalid');
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
    public static function date(string $iso): string
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $iso);
        if (!$d || $d->format('Y-m-d') !== $iso || $iso < '2000-01-01' || $iso > '2099-12-31') {
            throw new \InvalidArgumentException('bb_date_invalid');
        }
        return $d->format('dmY');
    }
    public static function parseDate(string $wire, bool $optional = false): ?string
    {
        if ($optional && in_array($wire, ['00000000', '        '], true)) return null;
        if (!preg_match('/^[0-9]{8}$/D', $wire)) throw new \UnexpectedValueException('bb_date_invalid');
        $iso = substr($wire, 4, 4) . '-' . substr($wire, 2, 2) . '-' . substr($wire, 0, 2);
        self::date($iso);
        return $iso;
    }
}
