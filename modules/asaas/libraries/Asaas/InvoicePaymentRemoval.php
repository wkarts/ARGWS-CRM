<?php

declare(strict_types=1);

namespace Asaas;

/**
 * Removes an unpaid, explicitly linked remote charge without refunding money.
 * The caller supplies AsaasAdapter::requestStructured as the transport.
 * This class never deletes an invoice or a payment record in the CRM.
 */
final class InvoicePaymentRemoval
{
    /** @var callable */
    private $request;

    public function __construct(callable $request)
    {
        $this->request = $request;
    }

    /** @return array{success: bool, code: string, payment_id: string} */
    public function remove(
        string $paymentId,
        string $invoiceHash,
        bool $hasLocalReceipts,
        array $origin = [],
        array $executionAccount = []
    ): array
    {
        if ($hasLocalReceipts) {
            return $this->result(false, 'local_receipts_require_review', $paymentId);
        }
        // Both contexts must come from trusted persisted origin/account records,
        // never the bank currently selected on a screen or request body. Legacy
        // maps without origin cannot be made safe by copying current settings.
        if (($origin['provider'] ?? '') !== 'asaas' || ($origin['channel'] ?? '') !== 'api') {
            return $this->result(false, 'unsupported_or_unknown_origin', $paymentId);
        }
        if (($origin['remote_id'] ?? '') !== $paymentId
            || !is_string($origin['account_id'] ?? null) || $origin['account_id'] === ''
            || !in_array($origin['environment'] ?? '', ['sandbox', 'production'], true)) {
            return $this->result(false, 'unverified_origin', $paymentId);
        }
        foreach (['provider', 'channel', 'account_id', 'environment'] as $field) {
            if (($executionAccount[$field] ?? null) !== $origin[$field]) {
                return $this->result(false, 'origin_account_mismatch', $paymentId);
            }
        }
        if ($paymentId === '' || preg_match('/[^a-zA-Z0-9_-]/', $paymentId)) {
            return $this->result(false, 'invalid_payment_link', $paymentId);
        }

        $path = '/payments/' . $paymentId;
        try {
            $lookup = ($this->request)('GET', $path);
        } catch (\Throwable $error) {
            return $this->result(false, 'lookup_failed', $paymentId);
        }
        if (!is_array($lookup) || ($lookup['success'] ?? false) !== true) {
            // A 404 may be a different account/environment, not proof of removal.
            return $this->result(false, 'lookup_failed', $paymentId);
        }
        $payment = $lookup['data'] ?? null;
        if (!is_array($payment) || ($payment['id'] ?? null) !== $paymentId) {
            return $this->result(false, 'invalid_remote_payment', $paymentId);
        }
        $reference = trim((string) ($payment['externalReference'] ?? ''));
        if ($reference !== '' && ($invoiceHash === '' || !hash_equals($invoiceHash, $reference))) {
            return $this->result(false, 'reference_mismatch', $paymentId);
        }

        // Only known unpaid states are eligible. Preserve paid/partial/refunded
        // history and require review for new or unrecognized provider states.
        if (!in_array($payment['status'] ?? '', ['PENDING', 'OVERDUE'], true)) {
            return $this->result(false, 'remote_state_requires_review', $paymentId);
        }
        if (($payment['deleted'] ?? false) === true) {
            return $this->result(true, 'already_deleted', $paymentId);
        }
        try {
            $removal = ($this->request)('DELETE', $path);
        } catch (\Throwable $error) {
            return $this->result(false, 'removal_unconfirmed', $paymentId);
        }
        $data = is_array($removal) ? ($removal['data'] ?? null) : null;
        if (!is_array($removal) || ($removal['success'] ?? false) !== true
            || !is_array($data) || ($data['id'] ?? null) !== $paymentId
            || ($data['deleted'] ?? false) !== true) {
            // Do not mark CANCELLED/DELETED based on an empty/error response.
            // A new user attempt begins with GET, never a blind DELETE retry.
            return $this->result(false, 'removal_unconfirmed', $paymentId);
        }
        return $this->result(true, 'deleted', $paymentId);
    }

    private function result(bool $success, string $code, string $paymentId): array
    {
        return ['success' => $success, 'code' => $code, 'payment_id' => $paymentId];
    }
}
