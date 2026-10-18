<?php

declare(strict_types=1);

namespace Asaas;

/** Append-only identities; only operational state can change after reservation. */
final class PaymentOriginStore
{
    private $db;
    private string $table;
    private string $events;

    public function __construct($db)
    {
        $this->db = $db;
        $this->table = db_prefix() . 'asaas_payment_origins';
        $this->events = db_prefix() . 'asaas_payment_origin_events';
    }

    public function ready(): bool
    {
        return $this->db->table_exists($this->table) && $this->db->table_exists($this->events);
    }

    public function byInvoice(int $invoiceId): ?array
    {
        if (!$this->ready()) {
            return null;
        }
        return $this->db->get_where($this->table, ['invoice_id' => $invoiceId])->row_array() ?: null;
    }

    public function byRemoteId(string $remoteId): ?array
    {
        if (!$this->ready()) {
            return null;
        }
        $rows = $this->db->get_where($this->table, ['remote_id' => $remoteId])->result_array();
        if (count($rows) > 1) {
            throw new \RuntimeException('ambiguous_payment_origin');
        }
        return $rows[0] ?? null;
    }

    public function reserve(array $invoice, array $context, string $state = 'issuing', ?string $remoteId = null): array
    {
        if (!$this->ready()) {
            throw new \RuntimeException('payment_origin_migration_required');
        }
        foreach (['account_id', 'environment', 'provider', 'channel'] as $field) {
            if (empty($context[$field])) {
                throw new \InvalidArgumentException('incomplete_payment_origin');
            }
        }
        if ($context['provider'] !== 'asaas' || $context['channel'] !== 'api'
            || !in_array($context['environment'], ['production', 'sandbox'], true)
            || empty($invoice['id']) || empty($invoice['hash'])) {
            throw new \InvalidArgumentException('invalid_payment_origin');
        }
        $row = [
            'invoice_id' => (int) $invoice['id'],
            'invoice_hash' => (string) $invoice['hash'],
            'invoice_total' => $invoice['total'],
            'invoice_due_date' => $invoice['duedate'] ?: null,
            'issuer_id' => $context['issuer_id'] ?? null,
            'provider' => 'asaas',
            'account_id' => (string) $context['account_id'],
            'environment' => $context['environment'],
            'channel' => 'api',
            'remote_id' => $remoteId,
            'batch_id' => null,
            'state' => $state,
            'last_code' => $state,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        // UNIQUE invoice_id reserves before POST. Concurrent/repeated attempts
        // never create another charge, including after an uncertain timeout.
        if ($this->byInvoice((int) $invoice['id']) !== null) {
            throw new \RuntimeException('payment_origin_already_reserved');
        }
        $debug = $this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $ok = $this->db->insert($this->table, $row);
        } finally {
            $this->db->db_debug = $debug;
        }
        if (!$ok) {
            throw new \RuntimeException('payment_origin_reservation_failed');
        }
        $this->event((int) $invoice['id'], $state);
        return $row;
    }

    public function attachRemote(int $invoiceId, string $remoteId): void
    {
        if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $remoteId)) {
            throw new \InvalidArgumentException('invalid_remote_id');
        }
        $origin = $this->byInvoice($invoiceId);
        if (!$origin || (!empty($origin['remote_id']) && $origin['remote_id'] !== $remoteId)) {
            throw new \RuntimeException('payment_origin_is_immutable');
        }
        if (empty($origin['remote_id'])) {
            $ok = $this->db->where('invoice_id', $invoiceId)->where('remote_id IS NULL', null, false)
                ->update($this->table, ['remote_id' => $remoteId]);
            if (!$ok || $this->db->affected_rows() !== 1) {
                throw new \RuntimeException('payment_origin_binding_failed');
            }
        }
        $this->state($invoiceId, 'issued', 'issued');
    }

    public function state(int $invoiceId, string $state, string $code): void
    {
        if (!$this->ready()) {
            throw new \RuntimeException('payment_origin_migration_required');
        }
        if (!$this->db->where('invoice_id', $invoiceId)->update($this->table, [
            'state' => $state, 'last_code' => $code, 'updated_at' => date('Y-m-d H:i:s'),
        ])) {
            throw new \RuntimeException('payment_origin_state_failed');
        }
        $this->event($invoiceId, $code);
    }

    public function event(int $invoiceId, string $code, ?string $evidenceHash = null): void
    {
        if (!$this->ready()) {
            throw new \RuntimeException('payment_origin_migration_required');
        }
        if (!$this->db->insert($this->events, [
            'invoice_id' => $invoiceId, 'code' => $code,
            'actor_id' => function_exists('get_staff_user_id') ? (int) get_staff_user_id() : null,
            'evidence_hash' => $evidenceHash, 'created_at' => date('Y-m-d H:i:s'),
        ])) {
            throw new \RuntimeException('payment_origin_audit_failed');
        }
    }
}
