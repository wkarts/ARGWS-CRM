<?php

declare(strict_types=1);

/** Exact monetary rules for the native company invoice editor. No float arithmetic. */
final class CompanyInvoiceAmounts
{
    public const MAX = 999999999999999;

    public static function minor($value, bool $signed = false): int
    {
        if ((!is_string($value) && !is_int($value))
            || !preg_match('/^(-?)(0|[1-9][0-9]{0,12})(?:\.([0-9]{1,2}))?$/D', (string) $value, $parts)
            || (!$signed && $parts[1] === '-')) {
            throw new DomainException('company_invoice_invalid_input');
        }
        $amount = (int) $parts[2] * 100 + (int) str_pad($parts[3] ?? '', 2, '0');
        return $parts[1] === '-' ? -$amount : $amount;
    }

    public static function decimal(int $amount): string
    {
        return ($amount < 0 ? '-' : '') . intdiv(abs($amount), 100) . '.'
            . str_pad((string) (abs($amount) % 100), 2, '0', STR_PAD_LEFT);
    }

    /** floor(a*b/d), remainder; b <= d, all intermediate values fit in signed 64 bits. */
    private static function ratio(int $a, int $b, int $d): array
    {
        if ($a < 0 || $b < 0 || $d < 1 || $b > $d || $a > self::MAX || $d > self::MAX) {
            throw new DomainException('company_invoice_invalid_input');
        }
        $q = 0; $r = 0; $aq = intdiv($a, $d); $ar = $a % $d;
        foreach (str_split(decbin($b)) as $bit) {
            $q *= 2; $r *= 2;
            if ($bit === '1') { $q += $aq; $r += $ar; }
            $q += intdiv($r, $d); $r %= $d;
        }
        return [$q, $r];
    }

    private static function percent(int $amount, int $bps): int
    {
        [$q, $r] = self::ratio($amount, $bps, 10000);
        return $q + ($r >= 5000 ? 1 : 0);
    }

    /** Fixed discount is apportioned by largest remainder, ties follow original item order. */
    private static function distribute(array $lines, int $discount, int $subtotal): array
    {
        $parts = []; $remainders = []; $allocated = 0;
        foreach ($lines as $i => $line) {
            [$q, $r] = self::ratio($line, $discount, $subtotal);
            $parts[$i] = $q; $remainders[$i] = $r; $allocated += $q;
        }
        uksort($remainders, static fn($a, $b) => ($remainders[$b] <=> $remainders[$a]) ?: ($a <=> $b));
        foreach ($remainders as $i => $unused) {
            if ($allocated === $discount) { break; }
            ++$parts[$i]; ++$allocated;
        }
        return $parts;
    }

    /** Legacy single-tax fields remain accepted; the editor uses the explicit taxes array. */
    public static function calculate(array $items, array $options = []): array
    {
        if (PHP_INT_SIZE < 8 || count($items) < 1 || count($items) > 100
            || array_diff(array_keys($options), ['discount_mode', 'discount_value', 'discount_type', 'adjustment', 'calculation_contract'])) {
            throw new DomainException('company_invoice_invalid_input');
        }
        $contract=$options['calculation_contract']??null;if(array_key_exists('calculation_contract',$options)&&$contract!=='native_sales_v1')throw new DomainException('company_invoice_calculation_contract_invalid');
        [$prepared,$lines,$subtotal]=self::prepare($items,$contract==='native_sales_v1');
        if(array_key_exists('calculation_contract',$options)){require_once __DIR__.'/CompanyInvoiceNativeAmounts.php';return CompanyInvoiceNativeAmounts::calculate($prepared,$lines,$subtotal,$options);}
        $mode = array_key_exists('discount_mode', $options) ? $options['discount_mode'] : 'fixed';
        $type = array_key_exists('discount_type', $options) ? $options['discount_type'] : 'after_tax';
        if (!in_array($mode, ['fixed', 'percent'], true) || !in_array($type, ['before_tax', 'after_tax'], true)) {
            throw new DomainException('company_invoice_invalid_input');
        }
        $value = self::minor(array_key_exists('discount_value', $options) ? $options['discount_value'] : '0');
        if ($mode === 'percent' && $value > 10000) { throw new DomainException('company_invoice_invalid_input'); }
        $discount = $mode === 'percent' ? self::percent($subtotal, $value) : $value;
        if ($discount > $subtotal) { throw new DomainException('company_invoice_discount_exceeds_subtotal'); }
        $allocated = $type === 'before_tax' ? self::distribute($lines, $discount, $subtotal) : array_fill(0, count($lines), 0);
        $taxTotal = 0;
        foreach ($prepared as $i => $item) {
            foreach ($item['taxes'] as $tax) {
                $amount = self::percent($lines[$i] - $allocated[$i], self::minor($tax['rate']));
                if ($taxTotal > self::MAX - $amount) { throw new DomainException('company_invoice_invalid_input'); }
                $taxTotal += $amount;
            }
        }
        $adjustment = self::minor(array_key_exists('adjustment', $options) ? $options['adjustment'] : '0', true);
        $total = $subtotal - $discount + $taxTotal + $adjustment;
        if ($total < 1 || $total > self::MAX) { throw new DomainException('company_invoice_invalid_input'); }
        return ['items' => $prepared, 'subtotal' => self::decimal($subtotal), 'tax' => self::decimal($taxTotal),
            'discount_mode' => $mode, 'discount_value' => self::decimal($value), 'discount_type' => $type,
            'discount_percent' => $mode === 'percent' ? self::decimal($value) : '0.00',
            'discount_total' => self::decimal($discount), 'adjustment' => self::decimal($adjustment), 'total' => self::decimal($total)];
    }

    private static function prepare(array $items,bool $allowZeroRate):array
    {
        $lines = []; $prepared = []; $subtotal = 0;
        foreach (array_values($items) as $index => $item) {
            if (!is_array($item) || array_diff(array_keys($item), ['description', 'qty', 'rate', 'taxname', 'taxrate', 'taxes'])) {
                throw new DomainException('company_invoice_invalid_input');
            }
            $qty = self::minor($item['qty'] ?? null); $rate = self::minor($item['rate'] ?? null);
            if ($qty < 1 || $qty > 99999900 || ($allowZeroRate ? $rate < 0 : $rate < 1) || ($rate > 0 && $qty > intdiv(self::MAX * 100, $rate))) {
                throw new DomainException('company_invoice_invalid_input');
            }
            $line = intdiv($qty * $rate + 50, 100);
            if (($allowZeroRate ? $line < 0 : $line < 1) || $subtotal > self::MAX - $line) { throw new DomainException('company_invoice_invalid_input'); }
            $subtotal += $line; $lines[$index] = $line;
            if (array_key_exists('taxes', $item)) {
                if (isset($item['taxrate']) || isset($item['taxname']) || !is_array($item['taxes']) || count($item['taxes']) > 10) {
                    throw new DomainException('company_invoice_invalid_input');
                }
                $taxes = $item['taxes'];
            } else {
                $taxes = [['name' => $item['taxname'] ?? '', 'rate' => $item['taxrate'] ?? '0']];
            }
            $normalized = []; $names = [];
            foreach ($taxes as $tax) {
                if (!is_array($tax) || array_diff(array_keys($tax), ['name', 'rate'])) { throw new DomainException('company_invoice_invalid_input'); }
                $bps = self::minor($tax['rate'] ?? null); $name = self::text($tax['name'] ?? '', 100, $bps > 0);
                if ($bps > 10000) { throw new DomainException('company_invoice_invalid_input'); }
                if ($bps === 0) { continue; }
                $identity = strtolower($name);
                if (isset($names[$identity])) { throw new DomainException('company_invoice_duplicate_tax'); }
                $names[$identity] = true; $normalized[] = ['name' => $name, 'rate' => self::decimal($bps)];
            }
            $prepared[] = ['description' => self::text($item['description'] ?? null, 10000, true),
                'qty' => self::decimal($qty), 'rate' => self::decimal($rate), 'taxes' => $normalized];
        }
        return[$prepared,$lines,$subtotal];
    }
    /** Pure line validation; zero-price lines only belong to the explicit native contract. */
    public static function normalizedItems(array $items,?string $contract=null):array
    {
        if($contract!==null&&$contract!=='native_sales_v1')throw new DomainException('company_invoice_calculation_contract_invalid');if(count($items)<1||count($items)>100)throw new DomainException('company_invoice_invalid_input');return self::prepare($items,$contract==='native_sales_v1')[0];
    }

    private static function text($value, int $max, bool $required = false): string
    {
        if (!is_string($value) || strlen($value) > $max || preg_match('//u', $value) !== 1
            || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value) || ($required && trim($value) === '')) {
            throw new DomainException('company_invoice_invalid_input');
        }
        return trim($value);
    }
}
