<?php

declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/LayoutCatalog.php';
require_once __DIR__.'/Fields.php';

/**
 * Compare uma observação CBR643 com uma fatura já autorizada.
 * Coincidências não estabelecem vínculo nem autenticam arquivo ou crédito.
 */
final class Cnab400InvoiceComparison
{
    public static function compare(int $issuer, array $eventRow, array $event, array $invoice, array $document): array
    {
        if ($issuer < 1 || (int)($eventRow['issuer_id'] ?? 0) !== $issuer
            || (int)($invoice['issuer_id'] ?? 0) !== $issuer
            || (int)($document['issuer_id'] ?? 0) !== $issuer
            || (int)($document['invoice_id'] ?? 0) !== (int)($invoice['id'] ?? 0)
            || (int)($invoice['id'] ?? 0) < 1) {
            throw new \DomainException('bb400_comparison_scope_denied');
        }
        if (($event['layout'] ?? null) !== LayoutCatalog::CNAB400
            || ($event['channel'] ?? null) !== 'cnab400'
            || ($event['provider'] ?? null) !== 'banco_brasil'
            || ($event['scope_key'] ?? null) !== ($eventRow['scope_key'] ?? null)
            || ($event['financial_effect'] ?? null) !== 'not_applied') {
            throw new \DomainException('bb400_comparison_event_denied');
        }
        $invoiceId = (int)$invoice['id'];
        $expectedDocument = 'CRM'.$invoiceId;
        $reportedDocument = $event['document'] ?? null;
        $eventDue = $event['due_date'] ?? null;
        $invoiceDue = $invoice['duedate'] ?? null;
        $nominal = $event['nominal_amount'] ?? null;
        if (!is_string($reportedDocument) || !is_string($nominal)
            || !is_string($invoice['total'] ?? null) || !is_string($invoiceDue)) {
            throw new \DomainException('bb400_comparison_invalid_source');
        }
        try {
            $sameNominal = Fields::cents($nominal) === Fields::cents($invoice['total']);
        } catch (\Throwable $error) {
            throw new \DomainException('bb400_comparison_invalid_source', 0, $error);
        }
        $comparison = [
            'document' => $reportedDocument === $expectedDocument,
            'due_date' => is_string($eventDue) && $eventDue === $invoiceDue,
            'nominal_amount' => $sameNominal,
        ];
        return [
            'invoice_id' => $invoiceId,
            'event_id' => (int)($eventRow['id'] ?? 0),
            'checks' => $comparison,
            'all_equal' => !in_array(false, $comparison, true),
            'identity_authority' => 'not_established',
            'currency_evidence' => 'not_present_in_file',
            'financial_effect' => 'not_applied',
        ];
    }
}
