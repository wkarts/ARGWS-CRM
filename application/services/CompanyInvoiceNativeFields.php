<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Existing native presentation columns only. No amount, owner, provider, status or sequence is writable here. */
final class CompanyInvoiceNativeFields
{
    public const TEXT = ['adminnote'=>20000, 'terms'=>20000,
        'billing_street'=>200, 'billing_city'=>100, 'billing_state'=>100, 'billing_zip'=>100,
        'shipping_street'=>200, 'shipping_city'=>100, 'shipping_state'=>100, 'shipping_zip'=>100];
    public const INTEGER = ['sale_agent'=>[0,2147483647], 'billing_country'=>[0,2147483647], 'shipping_country'=>[0,2147483647],
        'include_shipping'=>[0,1], 'show_shipping_on_invoice'=>[0,1], 'show_quantity_as'=>[1,3]];

    public static function input($input): array
    {
        if (!is_array($input) || array_diff(array_keys($input), array_merge(array_keys(self::TEXT), array_keys(self::INTEGER)))) {
            throw new DomainException('company_invoice_native_fields_invalid');
        }
        $result = [];
        foreach (self::TEXT as $field=>$max) {
            if (!array_key_exists($field, $input)) { continue; }
            $value = $input[$field];
            if (!is_string($value) || strlen($value) > $max || preg_match('//u', $value) !== 1
                || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value)) {
                throw new DomainException('company_invoice_native_fields_invalid');
            }
            $result[$field] = $value;
        }
        foreach (self::INTEGER as $field=>[$minimum,$maximum]) {
            if (!array_key_exists($field, $input)) { continue; }
            $value = $input[$field];
            if ((!is_int($value) && !is_string($value)) || !preg_match('/^(?:0|[1-9][0-9]{0,9})$/D', (string)$value)
                || (int)$value < $minimum || (int)$value > $maximum) {
                throw new DomainException('company_invoice_native_fields_invalid');
            }
            $result[$field] = (int)$value;
        }
        return $result;
    }

    public static function fromRecord(array $record): array
    {
        $values = [];
        foreach (self::TEXT as $field=>$max) { $values[$field] = $record[$field] ?? ''; }
        foreach (self::INTEGER as $field=>$range) { if($field==='sale_agent'&&(int)($record[$field]??0)===0)continue; $values[$field] = $record[$field] ?? ($field === 'show_quantity_as' ? 1 : 0); }
        return self::input($values);
    }

}
