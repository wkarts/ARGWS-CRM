<?php

declare(strict_types=1);

require_once __DIR__ . '/InvoiceIssuerDocuments.php';
require_once __DIR__ . '/../CompanyBankComponentsData.php';

/** Immutable, whitelisted DTO. Construction always reads an explicitly authorized persisted invoice. */
final class InvoiceIssuerDocumentData
{
    private array $data;
    private const MAX_CENTS = 999999999999999;

    private function __construct(array $source)
    {
        $record = $source['record'];
        $snapshot = $source['snapshot'];
        $invoiceId = IssuerPolicy::id($record['id']);
        $issuerId = IssuerPolicy::id($record['issuer_id']);
        if ((int) $snapshot['invoice_id'] !== $invoiceId || (int) $snapshot['issuer_id'] !== $issuerId) {
            throw new DomainException('issuer_document_scope_mismatch');
        }
        $recipient = $snapshot['recipient'];
        if ((string) $recipient['client_id'] !== (string) $record['clientid']) {
            throw new DomainException('issuer_document_scope_mismatch');
        }
        $recipient = [
            'client_id'=>$recipient['client_id'],
            'name'=>self::text($recipient['name'], 191, true),
            'tax_id'=>self::text($recipient['tax_id'], 14),
            'billing'=>self::address($record, 'billing', $source['countries']),
            'shipping'=>null,
        ];
        if ((string) $record['include_shipping'] === '1' && (string) $record['show_shipping_on_invoice'] === '1') {
            $recipient['shipping'] = self::address($record, 'shipping', $source['countries']);
        }
        $currency = $source['currency'];
        if (!$currency || (string) $currency['id'] !== (string) $record['currency'] || !preg_match('/^[A-Z]{3}$/D', $currency['name'])) {
            throw new DomainException('issuer_document_invalid_currency');
        }
        if (count($source['items']) < 1 || count($source['items']) > 1000) {
            throw new DomainException('issuer_document_invalid_items');
        }
        $items = []; $allItemIds = []; $products = 0; $roundedSum = 0;
        foreach ($source['items'] as $item) {
            if ((int) $item['rel_id'] !== $invoiceId || $item['rel_type'] !== 'invoice') {
                throw new DomainException('issuer_document_scope_mismatch');
            }
            $id = IssuerPolicy::id($item['id']);
            if (isset($allItemIds[$id])) { throw new DomainException('issuer_document_invalid_items'); }
            $allItemIds[$id] = true;
            if (!in_array((string) $item['is_optional'], ['0','1'], true) || !in_array((string) $item['is_selected'], ['0','1'], true)) {
                throw new DomainException('issuer_document_invalid_items');
            }
            if ((string) $item['is_optional'] === '1' && (string) $item['is_selected'] !== '1') { continue; }
            $quantity = self::cents($item['qty']);
            $rate = self::cents($item['rate']);
            if ($quantity <= 0 || ($rate > 0 && $quantity > intdiv(self::MAX_CENTS * 100, $rate))) {
                throw new DomainException('issuer_document_invalid_items');
            }
            $product = $quantity * $rate;
            if ($products > self::MAX_CENTS * 100 - $product) { throw new DomainException('issuer_document_invalid_totals'); }
            $products += $product;
            $amount = intdiv($product + 50, 100);
            $roundedSum += $amount;
            $items[$id] = [
                'id'=>$id, 'description'=>self::text($item['description'], 10000, true),
                'details'=>self::literal($item['long_description'] ?? '', 20000),
                'quantity'=>self::decimal($quantity), 'rate'=>self::decimal($rate), 'amount'=>self::decimal($amount),
                'unit'=>self::unit($item['unit'] ?? ''), 'taxes'=>[],
            ];
        }
        if (!$items) { throw new DomainException('issuer_document_invalid_items'); }
        foreach ($source['taxes'] as $tax) {
            $itemId = IssuerPolicy::id($tax['itemid']);
            if ((int) $tax['rel_id'] !== $invoiceId || $tax['rel_type'] !== 'invoice' || !isset($allItemIds[$itemId])) {
                throw new DomainException('issuer_document_scope_mismatch');
            }
            if (!isset($items[$itemId])) { continue; }
            $rate = self::cents($tax['taxrate']);
            if ($rate > 10000) { throw new DomainException('issuer_document_invalid_totals'); }
            $items[$itemId]['taxes'][] = ['name'=>self::text($tax['taxname'],100,true), 'rate'=>self::decimal($rate)];
        }
        $totals = [];
        foreach (['subtotal','total_tax','discount_total','adjustment','total'] as $field) {
            $totals[$field] = self::cents($record[$field] ?? '0.00', $field === 'adjustment');
        }
        // Core can round each item or the aggregate; both valid decimal strategies must reconcile exactly.
        if (!in_array($totals['subtotal'], [$roundedSum,intdiv($products + 50,100)], true)
            || $totals['discount_total'] > $totals['subtotal'] + $totals['total_tax']
            || $totals['subtotal'] + $totals['total_tax'] - $totals['discount_total'] + $totals['adjustment'] !== $totals['total']) {
            throw new DomainException('issuer_document_invalid_totals');
        }
        if (!in_array((string) $record['status'], ['1','2','3','4','5','6'], true)) {
            throw new DomainException('issuer_document_invalid_status');
        }
        $this->data = [
            'invoice_id'=>$invoiceId, 'issuer_id'=>$issuerId, 'number'=>$snapshot['formatted_number'],
            'identity'=>$snapshot['identity'], 'recipient'=>$recipient, 'currency'=>$currency['name'],'currency_basis'=>$currency['basis'],
            'date'=>self::date($record['date']), 'due_date'=>empty($record['duedate']) ? null : self::date($record['duedate']),
            'status'=>(int) $record['status'], 'items'=>array_values($items),
            'totals'=>array_map([self::class,'decimal'],$totals),
            'position'=>self::position($source['position']??null,$invoiceId,$issuerId,$totals['total']),
            'note'=>self::text($record['clientnote'] ?? '',20000),
        ];
        if (strlen(json_encode($this->data, JSON_THROW_ON_ERROR)) > 16777215) {
            throw new DomainException('issuer_document_content_too_large');
        }
    }

    private static function position($position,int $invoice,int $issuer,int $total):array
    {
        if(!is_array($position)||($position['invoice_id']??null)!==$invoice||($position['issuer_id']??null)!==$issuer)throw new DomainException('issuer_document_scope_mismatch');$result=[];
        foreach(['total','received','received_original','received_reversed','received_manual_reversed','received_external_reversed','applied_credit','settlement_discount','settlement_abatement','settlement_discount_original','settlement_abatement_original','settlement_discount_reversed','settlement_abatement_reversed','noncash_reductions','receivable'] as $field)$result[$field]=self::decimal(self::cents($position[$field]??null));
        if(self::cents($result['total'])!==$total||self::cents($result['settlement_discount'])+self::cents($result['settlement_abatement'])!==self::cents($result['noncash_reductions'])||self::cents($result['received'])+self::cents($result['applied_credit'])+self::cents($result['noncash_reductions'])+self::cents($result['receivable'])!==$total||self::cents($result['received'])+self::cents($result['received_reversed'])!==self::cents($result['received_original'])||self::cents($result['received_manual_reversed'])+self::cents($result['received_external_reversed'])!==self::cents($result['received_reversed'])||self::cents($result['settlement_discount'])+self::cents($result['settlement_discount_reversed'])!==self::cents($result['settlement_discount_original'])||self::cents($result['settlement_abatement'])+self::cents($result['settlement_abatement_reversed'])!==self::cents($result['settlement_abatement_original']))throw new DomainException('issuer_document_invalid_totals');
        $tariffs=[];foreach($position['components']??[] as$component){if(($component['basis']??null)!==CompanyBankComponentsData::TARIFF_BASIS)continue;$captured=CompanyBankComponentsData::fromCaptured($component)->data();$tariffs[]=['principal_received'=>$captured['cash_principal'],'tariff_deducted'=>$captured['fees'],'bank_net'=>$captured['net_observed']];}if($tariffs)$result['bank_tariffs']=$tariffs;return $result;
    }

    public static function fromInvoice(InvoiceIssuerDocuments $documents, $invoice, $issuer): self
    {
        if (PHP_INT_SIZE < 8) { throw new DomainException('issuer_document_64bit_required'); }
        return new self($documents->readForPdf($invoice, $issuer));
    }

    public static function fromInvoiceInOwned(InvoiceIssuerDocuments$documents,$invoice,$issuer):self
    {
        if(PHP_INT_SIZE<8)throw new DomainException('issuer_document_64bit_required');return new self($documents->readForPdfInOwned($invoice,$issuer));
    }

    public function data(): array { return $this->data; }

    public function __serialize(): array { throw new LogicException('issuer_document_dto_not_serializable'); }
    public function __unserialize(array $data): void { throw new LogicException('issuer_document_dto_not_serializable'); }

    private static function unit($value):string{$value=self::literal($value,160);if(preg_match_all('/./us',$value)>40)throw new DomainException('issuer_document_invalid_content');return $value;}
    private static function literal($value,int $max):string{self::text($value,$max);return $value;}
    private static function text($value, int $max, bool $required = false): string
    {
        if (!is_string($value) || strlen($value) > $max || preg_match('//u',$value) !== 1
            || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$value) || ($required && trim($value) === '')) {
            throw new DomainException('issuer_document_invalid_content');
        }
        return trim($value);
    }

    private static function cents($value, bool $signed = false): int
    {
        // DECIMAL(15,2): never calculate with floating-point money.
        if ((!is_string($value) && !is_int($value) && !is_float($value))
            || !preg_match('/^(-?)(0|[1-9][0-9]{0,12})(?:\.([0-9]{1,2}))?$/D',(string)$value,$parts)
            || (!$signed && $parts[1] === '-')) {
            throw new DomainException('issuer_document_invalid_totals');
        }
        $cents = (int)$parts[2] * 100 + (int)str_pad($parts[3] ?? '',2,'0');
        return $parts[1] === '-' ? -$cents : $cents;
    }

    private static function decimal(int $cents): string
    {
        return ($cents < 0 ? '-' : '') . intdiv(abs($cents),100) . '.' . str_pad((string)(abs($cents) % 100),2,'0',STR_PAD_LEFT);
    }

    private static function date($value): string
    {
        if (!is_string($value) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$value)
            || !($date = DateTimeImmutable::createFromFormat('!Y-m-d',$value)) || $date->format('Y-m-d') !== $value) {
            throw new DomainException('issuer_document_invalid_date');
        }
        return $value;
    }

    private static function address(array $record, string $kind, array $countries): array
    {
        $address = [];
        foreach (['street','city','state','zip'] as $field) { $address[$field] = self::text($record[$kind . '_' . $field] ?? '',500); }
        $id = $record[$kind . '_country'];
        $address['country'] = '';
        if ($id !== null && (string)$id !== '' && (string)$id !== '0') {
            $country = $countries[(string)$id] ?? null;
            if (!$country || (string)$country['country_id'] !== (string)$id || !preg_match('/^[A-Z]{2}$/D',$country['iso2'] ?? '')) {
                throw new DomainException('issuer_document_invalid_content');
            }
            $address['country'] = $country['iso2'];
        }
        return $address;
    }
}
