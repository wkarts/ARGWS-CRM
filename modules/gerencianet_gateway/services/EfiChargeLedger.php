<?php

declare(strict_types=1);

defined('BASEPATH') or exit('No direct script access allowed');

/** Local ownership evidence, independent of provider-controlled metadata/URLs. */
final class EfiChargeLedger
{
    public function __construct(private $db, private string $prefix)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) { throw new DomainException('efi_storage_invalid'); }
    }

    public static function minor($amount): int
    {
        if ((!is_string($amount) && !is_int($amount)) || !preg_match('/^(\d{1,13})(?:\.(\d{1,2}))?$/D', (string) $amount, $m)) {
            throw new DomainException('efi_amount_invalid');
        }
        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }

    public static function decimal(int $minor): string { return intdiv($minor, 100) . '.' . str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT); }

    public static function account(array $options): string
    {
        if (!is_string($options['client_id'] ?? null) || $options['client_id'] === '' || !is_bool($options['sandbox'] ?? null)) {
            throw new DomainException('efi_account_unavailable');
        }
        return hash('sha256', $options['client_id']);
    }

    private static function key(string $kind, string $provider): void
    {
        if (!in_array($kind, ['pix','boleto','card'], true)
            || !preg_match($kind === 'pix' ? '/^[A-Za-z0-9]{26,35}$/D' : '/^[1-9][0-9]{0,19}$/D', $provider)) {
            throw new DomainException('efi_charge_invalid');
        }
    }

    public function invoice($id, string $hash): array
    {
        if ((!is_string($id) && !is_int($id)) || !preg_match('/^[1-9][0-9]{0,9}$/D', (string) $id)
            || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $hash)) { throw new DomainException('efi_invoice_denied'); }
        $rows = $this->query('SELECT i.id,i.issuer_id,i.hash,i.total,i.status,i.currency,c.name AS currency_name FROM ' . $this->prefix . 'invoices i JOIN ' . $this->prefix . 'currencies c ON c.id=i.currency WHERE i.id=? AND i.hash=? AND i.issuer_id IS NULL', [$id,$hash])->result_array();
        if (count($rows) !== 1 || $rows[0]['issuer_id'] !== null || $rows[0]['currency_name'] !== 'BRL') { throw new DomainException('efi_invoice_denied'); }
        return $rows[0];
    }

    public function bind(string $kind, string $provider, array $options, array $invoice): array
    {
        self::key($kind,$provider); $account = self::account($options);
        $current = $this->invoice($invoice['id'], $invoice['hash']);
        $amount = self::minor($invoice['total']);
        if ($amount < 1 || self::minor($current['total']) !== $amount || !in_array((int)$current['status'], [1,3,4,6], true)) { throw new DomainException('efi_invoice_changed'); }
        $table = $this->prefix . 'efi_charge_bindings';
        $old = $this->query("SELECT * FROM {$table} WHERE kind=? AND sandbox=? AND account_hash=? AND provider_id=?", [$kind,(int)$options['sandbox'],$account,$provider])->result_array();
        if (!$old) {
            $this->query("INSERT INTO {$table} (kind,sandbox,account_hash,provider_id,invoice_id,issuer_id,amount_minor,currency,payment_id,created_at) VALUES (?,?,?,?,?,NULL,?,'BRL',NULL,UTC_TIMESTAMP())", [$kind,(int)$options['sandbox'],$account,$provider,$current['id'],$amount]);
        }
        return $this->bound($kind,$provider,$options,$current);
    }

    public function bound(string $kind, string $provider, array $options, array $invoice): array
    {
        self::key($kind,$provider);
        $rows = $this->query('SELECT * FROM ' . $this->prefix . 'efi_charge_bindings WHERE kind=? AND sandbox=? AND account_hash=? AND provider_id=?', [$kind,(int)$options['sandbox'],self::account($options),$provider])->result_array();
        if (count($rows) !== 1 || (int)$rows[0]['invoice_id'] !== (int)$invoice['id'] || $rows[0]['issuer_id'] !== null
            || $rows[0]['currency'] !== 'BRL' || (int)$rows[0]['amount_minor'] !== self::minor($invoice['total'])) {
            // Old tokens/infoAdicionais do not prove environment/account/owner. Never infer a binding.
            throw new DomainException('efi_charge_reconciliation_required');
        }
        return $rows[0];
    }

    /** Only authenticated SDK query responses enter here; not POST/webhook payloads. */
    public static function assertPaid(array $binding, array $response): void
    {
        $expected = (int)$binding['amount_minor']; $provider = (string)$binding['provider_id'];
        if ($binding['kind'] === 'pix') {
            $pix = $response['pix'] ?? null;
            if (($response['status'] ?? null) !== 'CONCLUIDA' || ($response['txid'] ?? null) !== $provider
                || self::minor($response['valor']['original'] ?? null) !== $expected || !is_array($pix) || count($pix) !== 1
                || ($pix[0]['txid'] ?? null) !== $provider || self::minor($pix[0]['valor'] ?? null) !== $expected
                || !empty($pix[0]['devolucoes']) || !preg_match('/^[A-Za-z0-9]{32}$/D', (string)($pix[0]['endToEndId'] ?? ''))) {
                throw new DomainException('efi_payment_unconfirmed');
            }
        } else {
            $data = $response['data'] ?? [];
            $total = $data['total'] ?? null;
            if (($response['code'] ?? null) !== 200 || (string)($data['charge_id'] ?? '') !== $provider || ($data['status'] ?? null) !== 'paid'
                || (!is_int($total) && !is_string($total)) || !preg_match('/^[1-9][0-9]{0,14}$/D', (string)$total) || (int)$total !== $expected) {
                throw new DomainException('efi_payment_unconfirmed');
            }
        }
    }

    /** The native payment writer owns its transaction. Advisory lock + deterministic receipt
     * lets replay recover a committed receipt after a tracking/notification failure, without
     * nesting a transaction or accepting an unbound historical transaction ID.
     */
    public function settle(string $kind, string $provider, array $options, array $invoice, array $response, callable $write): int
    {
        $binding = $this->bound($kind,$provider,$options,$invoice); self::assertPaid($binding,$response);
        $database = $this->query('SELECT DATABASE() AS database_name',[])->row_array()['database_name'] ?? null;
        if (!is_string($database) || $database === '') { throw new RuntimeException('efi_database_identity_missing'); }
        // Serialize different Efí charges for one invoice. The native writer additionally
        // takes the invoice row lock against manual/credit/other gateway transactions.
        $lock = 'efi:' . substr(hash('sha256',$database . ':' . $this->prefix . ':' . $binding['invoice_id']),0,58);
        $acquired = $this->query('SELECT GET_LOCK(?,0) AS acquired',[$lock])->row_array();
        if ((int)($acquired['acquired'] ?? 0) !== 1) { throw new DomainException('efi_payment_busy'); }
        try {
            $current = $this->invoice($invoice['id'],$invoice['hash']);
            $binding = $this->bound($kind,$provider,$options,$current);
            $reference = 'efi:' . hash('sha256', $kind . ':' . (int)$options['sandbox'] . ':' . self::account($options) . ':' . $provider);
            $read = fn() => $this->query('SELECT id,invoiceid,amount,paymentmode,transactionid FROM ' . $this->prefix . 'invoicepaymentrecords WHERE invoiceid=? AND paymentmode=? AND transactionid=?', [$current['id'],'gerencianet',$reference])->result_array();
            $receipts = $read();
            if (!$receipts) {
                if ($binding['payment_id'] !== null) { throw new DomainException('efi_payment_receipt_missing'); }
                if (!in_array((int)$current['status'],[1,3,4,6],true)) { throw new DomainException('efi_invoice_not_payable'); }
                $data = ['invoiceid'=>(int)$current['id'],'amount'=>self::decimal((int)$binding['amount_minor']),
                    'paymentmode'=>'gerencianet','paymentmethod'=>$kind,'transactionid'=>$reference];
                try { $write($data); } catch (Throwable $error) {
                    // A native post-commit hook may throw. Recover only the exact persisted receipt.
                    if (!$read()) { throw new RuntimeException('efi_payment_write_failed',0,$error); }
                }
                $receipts = $read();
            }
            if (count($receipts) !== 1 || self::minor($receipts[0]['amount']) !== (int)$binding['amount_minor']
                || ($binding['payment_id'] !== null && (int)$binding['payment_id'] !== (int)$receipts[0]['id'])) {
                throw new DomainException('efi_payment_receipt_conflict');
            }
            $id = (int)$receipts[0]['id'];
            $this->query('UPDATE ' . $this->prefix . 'efi_charge_bindings SET payment_id=? WHERE id=? AND (payment_id IS NULL OR payment_id=?)',[$id,$binding['id'],$id]);
            $saved = $this->bound($kind,$provider,$options,$current);
            if ((int)$saved['payment_id'] !== $id) { throw new RuntimeException('efi_payment_tracking_unconfirmed'); }
            return $id;
        } finally { $this->query('SELECT RELEASE_LOCK(?) AS released',[$lock]); }
    }

    public function pendingPix(array $options): array
    {
        return $this->query('SELECT b.provider_id,i.id,i.hash FROM ' . $this->prefix . 'efi_charge_bindings b JOIN ' . $this->prefix . 'invoices i ON i.id=b.invoice_id AND i.issuer_id IS NULL WHERE b.kind=? AND b.sandbox=? AND b.account_hash=? AND b.issuer_id IS NULL AND b.payment_id IS NULL ORDER BY b.id LIMIT 100', ['pix',(int)$options['sandbox'],self::account($options)])->result_array();
    }

    private function query(string $sql, array $args)
    {
        $restore=property_exists($this->db,'db_debug');$debug=$restore?$this->db->db_debug:null;
        if($restore)$this->db->db_debug=false;
        try {$query=$this->db->query($sql,$args);if($query===false)throw new RuntimeException('efi_storage_failed');return $query;}
        finally {if($restore)$this->db->db_debug=$debug;}
    }
}
