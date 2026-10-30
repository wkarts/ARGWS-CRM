<?php
declare(strict_types=1);
namespace Asaas;

/** Hosted-only policy. No method accepts PAN, CVV, holder data or reusable card tokens. */
final class HostedCardCheckout
{
    public static function allowsInvoice($modes): bool
    {
        $decoded = is_array($modes) ? $modes : (is_string($modes) ? @unserialize($modes, ['allowed_classes' => false]) : null);
        return is_array($decoded) && in_array('asaas', $decoded, true);
    }

    public static function installments($value): int
    {
        if ($value !== 1 && $value !== '1') {
            // The current immutable-origin ledger represents one remote payment only.
            // Installments need per-title links and cumulative settlement, not a made-up ready flag.
            throw new \DomainException('card_installments_origin_mapping_pending');
        }
        return 1;
    }

    public static function payload(array $invoice, string $customerId, $amount, $installments): array
    {
        self::installments($installments);
        $date = (string) ($invoice['duedate'] ?? '');
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $value = is_numeric($amount) ? (float) $amount : NAN;
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $customerId)
            || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', (string) ($invoice['hash'] ?? ''))
            || !$parsed || $parsed->format('Y-m-d') !== $date
            || !is_finite($value) || $value < 0.01 || round($value, 2) !== $value) {
            throw new \InvalidArgumentException('invalid_hosted_card_invoice');
        }
        return [
            'customer' => $customerId,
            'billingType' => 'CREDIT_CARD',
            'dueDate' => $date,
            'value' => $value,
            'externalReference' => $invoice['hash'],
        ];
    }

    public static function verifiedUrl(array $payment, string $environment, string $invoiceHash): string
    {
        if (!in_array($payment['billingType'] ?? '', ['CREDIT_CARD', 'UNDEFINED'], true)
            || ($payment['externalReference'] ?? '') !== $invoiceHash || !empty($payment['deleted'])
            || !is_string($payment['id'] ?? null) || $payment['id'] === '') {
            throw new \DomainException('hosted_card_requires_origin_review');
        }
        $url = $payment['invoiceUrl'] ?? null;
        $parts = is_string($url) ? parse_url($url) : false;
        $hosts = $environment === 'production' ? ['www.asaas.com', 'asaas.com']
            : ($environment === 'sandbox' ? ['sandbox.asaas.com'] : []);
        if (!is_string($url) || !filter_var($url, FILTER_VALIDATE_URL) || !is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https' || !in_array(strtolower($parts['host'] ?? ''), $hosts, true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
            || isset($parts['query']) || isset($parts['fragment'])
            || !preg_match('~^/i/[A-Za-z0-9_-]+/?$~D', $parts['path'] ?? '')) {
            throw new \DomainException('unverified_hosted_card_url');
        }
        return $url;
    }
}
