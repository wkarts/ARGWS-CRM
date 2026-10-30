<?php

namespace app\services;

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Explicit SQL ownership value. A NULL legacy scope never includes named issuers.
 * This is a predicate, not an authorization grant or a session/global selector.
 */
final class InvoiceIssuerScope
{
    private ?int $issuerId;

    private function __construct(?int $issuerId)
    {
        if ($issuerId !== null && $issuerId < 1) {
            throw new \InvalidArgumentException('invoice_issuer_invalid');
        }
        $this->issuerId = $issuerId;
    }

    public static function legacy(): self { return new self(null); }
    public static function forIssuer($issuerId): self
    {
        if ((!is_int($issuerId) && !is_string($issuerId)) || !preg_match('/^[1-9][0-9]*$/D', (string) $issuerId)
            || (string) (int) $issuerId !== (string) $issuerId) {
            throw new \InvalidArgumentException('invoice_issuer_invalid');
        }
        return new self((int) $issuerId);
    }
    public function issuerId(): ?int { return $this->issuerId; }

    public function predicate(string $alias): array
    {
        self::identifier($alias);
        return $this->issuerId === null
            ? ['sql' => $alias . '.issuer_id IS NULL', 'bindings' => []]
            : ['sql' => $alias . '.issuer_id = ?', 'bindings' => [$this->issuerId]];
    }

    public function contains(array $invoice): bool
    {
        if (!array_key_exists('issuer_id', $invoice)) {
            return $this->issuerId === null;
        }
        if ($invoice['issuer_id'] === null) {
            return $this->issuerId === null;
        }
        return $this->issuerId !== null && (is_int($invoice['issuer_id']) || is_string($invoice['issuer_id'])) && preg_match('/^[1-9][0-9]*$/D', (string) $invoice['issuer_id'])
            && (string) $invoice['issuer_id'] === (string) $this->issuerId;
    }

    public static function schemaReady($db, string $table): bool
    {
        self::identifier($table);
        return $db->field_exists('issuer_id', $table);
    }

    /** Legacy endpoints retain old behavior before migration and fail closed after it. */
    public static function legacySql($db, string $table, ?string $alias = null): string
    {
        $alias = $alias ?? $table;
        self::identifier($alias);
        return self::schemaReady($db, $table) ? self::legacy()->predicate($alias)['sql'] : '1=1';
    }

    public static function applyLegacy($db, string $table, ?string $alias = null): void
    {
        $db->where(self::legacySql($db, $table, $alias), null, false);
    }

    /** Lightweight raw lookup avoids invoking hydration hooks/financial providers before authorization. */
    public static function isLegacyInvoice($db, string $table, $invoiceId): bool
    {
        if ((!is_int($invoiceId) && !is_string($invoiceId)) || !preg_match('/^[1-9][0-9]*$/D', (string) $invoiceId)) {
            return false;
        }
        self::identifier($table);
        $where = self::legacySql($db, $table);
        $result = $db->query('SELECT id FROM ' . $table . ' WHERE id = ? AND ' . $where, [$invoiceId]);
        return $result !== false && $result->row() !== null;
    }

    /** Existing core writers can create/update only NULL legacy ownership until rollout is complete. */
    public static function legacyWriteData(array $data): array
    {
        if (array_key_exists('issuer_id', $data) && $data['issuer_id'] !== null) {
            throw new \DomainException('invoice_issuer_rollout_blocked');
        }
        // Omit NULL to remain compatible before migration; never coerce a named issuer into legacy.
        unset($data['issuer_id']);
        return $data;
    }

    /** IDs attached to a legacy write must not point at another ownership scope. */
    public static function assertLegacyReferences($db, string $table, array $data): void
    {
        $ids = $data['invoices_to_merge'] ?? [];
        if (!is_array($ids)) { throw new \DomainException('invoice_reference_scope_invalid'); }
        if (!empty($data['is_recurring_from'])) { $ids[] = $data['is_recurring_from']; }
        foreach ($ids as $id) {
            if (!self::isLegacyInvoice($db, $table, $id)) { throw new \DomainException('invoice_reference_scope_invalid'); }
        }
    }

    private static function identifier(string $value): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $value)) {
            throw new \InvalidArgumentException('invoice_issuer_invalid_identifier');
        }
    }
}
