<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/Agreement.php';

/** CBR641/643 use four/eight-digit accounts and DDMMAA, unlike CNAB240. */
final class Fields400
{
    public static function account(Agreement $a): array
    {
        if ($a->branch[0] !== '0' || substr($a->account, 0, 4) !== '0000') {
            throw new \InvalidArgumentException('bb400_account_width_unsupported');
        }
        return [substr($a->branch, 1), $a->branchDigit, substr($a->account, 4), $a->accountDigit];
    }
    public static function date(string $iso): string
    {
        Fields::date($iso);
        return substr($iso, 8, 2).substr($iso, 5, 2).substr($iso, 2, 2);
    }
    public static function parseDate(string $wire, bool $optional = false): ?string
    {
        if ($optional && in_array($wire, ['000000', '      '], true)) return null;
        if (!preg_match('/^[0-9]{6}$/D', $wire)) throw new \UnexpectedValueException('bb400_date_invalid');
        // Explicit profile contract: 2000–2099. Never pivot according to the processing year.
        $iso = '20'.substr($wire, 4, 2).'-'.substr($wire, 2, 2).'-'.substr($wire, 0, 2);
        Fields::date($iso);
        return $iso;
    }
    public static function money(string $value): string
    {
        $cents = Fields::cents($value);
        if ($cents > 9999999999999) throw new \InvalidArgumentException('bb400_amount_overflow');
        return Fields::digits((string)$cents, 13);
    }
    public static function numeric(string $wire): int
    {
        if (!preg_match('/^[0-9]{1,14}$/D', $wire)) throw new \UnexpectedValueException('bb400_numeric_invalid');
        return (int)$wire;
    }
}
