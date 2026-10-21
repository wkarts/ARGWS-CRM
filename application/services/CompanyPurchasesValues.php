<?php

declare(strict_types=1);

/** Exact decimal quantities and amounts; no floats, automatic exchange source or silent rounding of input. */
final class CompanyPurchasesValues
{
    public const MAX_MONEY = 99999999999;
    public const MAX_QUANTITY = 999999999;

    public static function integer($value, int $min = 0, int $max = 2147483647): int
    {
        if (!(is_int($value) || (is_string($value) && preg_match('/^(0|[1-9][0-9]*)$/D', $value))) || strlen((string)$value) > strlen((string)$max) || (int)$value < $min || (int)$value > $max) { throw new DomainException('company_purchase_invalid'); }
        return (int)$value;
    }

    public static function text($value, int $max, bool $required = false): string
    {
        if (!is_string($value) || strlen($value) > $max || preg_match('//u', $value) !== 1 || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value)) { throw new DomainException('company_purchase_invalid'); }
        $value = trim($value);
        if ($required && $value === '') { throw new DomainException('company_purchase_invalid'); }
        return $value;
    }

    public static function fields(array $input, array $allowed): void
    {
        if (array_diff(array_keys($input), $allowed)) { throw new DomainException('company_purchase_invalid'); }
    }

    public static function decimal($value, int $scale, int $max, int $min = 0): int
    {
        if (PHP_INT_SIZE < 8 || !is_string($value) || !preg_match('/^(0|[1-9][0-9]{0,10})(?:\.([0-9]{1,'.$scale.'}))?$/D', $value, $m)) { throw new DomainException('company_purchase_invalid'); }
        $factor = 10 ** $scale;
        if ((int)$m[1] > intdiv($max, $factor)) { throw new DomainException('company_purchase_invalid'); }
        $n = (int)$m[1] * $factor + (int)str_pad($m[2] ?? '', $scale, '0');
        if ($n < $min || $n > $max) { throw new DomainException('company_purchase_invalid'); }
        return $n;
    }

    public static function display(int $value, int $scale = 2): string
    {
        $factor = 10 ** $scale;
        return intdiv($value, $factor).'.'.str_pad((string)($value % $factor), $scale, '0', STR_PAD_LEFT);
    }

    public static function date($value): string
    {
        $d = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
        if (!$d || $d->format('Y-m-d') !== $value || $value < '2000-01-01' || $value > '2100-12-31') { throw new DomainException('company_purchase_invalid'); }
        return $value;
    }

    public static function currency($value): string
    {
        if (!is_string($value) || !in_array($value, ['BRL','USD','EUR'], true)) { throw new DomainException('company_purchase_invalid'); }
        return $value;
    }

    public static function json(array $value): string
    {
        $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (strlen($json) > 60000) { throw new DomainException('company_purchase_invalid'); }
        return $json;
    }

    public static function decode(string $value): array
    {
        try { $r = json_decode($value, true, 32, JSON_THROW_ON_ERROR); }
        catch (JsonException $e) { throw new RuntimeException('issuer_purchase_integrity_invalid', 0, $e); }
        if (!is_array($r)) { throw new RuntimeException('issuer_purchase_integrity_invalid'); }
        return $r;
    }

    /** Order-independent row digest, stable across PDO/mysql string representations. */
    public static function hash(array $row): string
    {
        unset($row['id'], $row['record_hash']);
        ksort($row);
        return hash('sha256', self::json(array_map(static fn($v) => $v === null ? null : (string)$v, $row)));
    }

    public static function base(int $quantity, int $unitCents): int
    {
        // Caps make the multiplication fit a signed 64-bit integer.
        self::integer($quantity, 0, self::MAX_QUANTITY);
        self::integer($unitCents, 0, 999999999);
        $base = intdiv($quantity * $unitCents + 500, 1000);
        if ($base > self::MAX_MONEY) { throw new DomainException('company_purchase_amount_limit'); }
        return $base;
    }

    public static function tax(int $base, int $basisPoints): int
    {
        self::integer($base, 0, self::MAX_MONEY);
        self::integer($basisPoints, 0, 10000);
        return intdiv($base * $basisPoints + 5000, 10000);
    }

    public static function lines($input): array
    {
        if (!is_array($input) || !array_is_list($input) || count($input) < 1 || count($input) > 50) { throw new DomainException('company_purchase_invalid'); }
        $lines = [];
        foreach ($input as $i => $line) {
            if (!is_array($line)) { throw new DomainException('company_purchase_invalid'); }
            self::fields($line, ['description','unit','quantity']);
            $lines[] = ['line'=>$i + 1,'description'=>self::text($line['description'] ?? null, 191, true),'unit'=>self::text($line['unit'] ?? null, 16, true),'quantity_milli'=>self::decimal($line['quantity'] ?? null, 3, self::MAX_QUANTITY, 1)];
        }
        return $lines;
    }
}
