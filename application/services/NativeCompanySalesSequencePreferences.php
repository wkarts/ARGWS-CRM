<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';

/**
 * Safe configuration of the real per-company sales allocator.
 *
 * This service does not expose a Settings form or admit an HTTP action.  The
 * principal keeps the historical native allocator; an owned sequence is never
 * inferred from it or from another company.  Existing issued documents are
 * observed and never renumbered or rewritten.
 */
final class NativeCompanySalesSequencePreferences
{
    public function __construct(private $db, private string $prefix)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) {
            throw new InvalidArgumentException('Invalid prefix');
        }
    }

    public function tables(): array
    {
        return ['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions',
            'issuers_installation_identity','staff','issuers_sequences','company_sales_documents','issuers_audit'];
    }

    private static function series(string $kind): string
    {
        return match ($kind) {
            'estimates' => 'EST',
            'proposals' => 'PROP',
            default => throw new DomainException('native_sales_sequence_invalid'),
        };
    }

    private function authorize(int $issuer, int $actor, string $capability): void
    {
        $acl = new IssuerResourceAccess($this->db, $this->prefix);
        // The installation company keeps its historical allocator; this optional
        // owned-company preferences service must never replace that source.
        if ($acl->primaryId() !== null && $issuer === $acl->primaryId()) {
            throw new DomainException('native_sales_sequence_denied');
        }
        if (!$acl->can($issuer, $actor, 'settings', $capability)) {
            throw new DomainException('native_sales_sequence_denied');
        }
    }

    private static function number($value): string
    {
        if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,19}$/D', $value)
            || (strlen($value) === 20 && strcmp($value, '18446744073709551614') > 0)) {
            throw new DomainException('native_sales_sequence_invalid');
        }
        return $value;
    }

    private static function compare(string $left, string $right): int
    {
        return strlen($left) <=> strlen($right) ?: strcmp($left, $right);
    }

    public function readInOwnedTransaction(int $issuer, int $actor, string $kind): array
    {
        return $this->readFrame($issuer,$actor,$kind,fn()=>$this->authorize($issuer,$actor,'view'));
    }

    /** A creator may inspect its own allocator without receiving Settings privileges. */
    public function readForCreationInOwnedTransaction(int $issuer,int $actor,string $kind):array
    {
        self::series($kind);
        $verify=function()use($issuer,$actor,$kind):void{
            $acl=new IssuerResourceAccess($this->db,$this->prefix);
            if(!$acl->can($issuer,$actor,$kind,'view')||!$acl->can($issuer,$actor,$kind,'create'))throw new DomainException('native_sales_sequence_denied');
        };
        return $this->readFrame($issuer,$actor,$kind,$verify);
    }
    public function readForCreation(int $issuer,int $actor,string $kind):array
    {
        return IssuerTransaction::run($this->db,$this->prefix,$this->tables(),fn()=>$this->readForCreationInOwnedTransaction($issuer,$actor,$kind));
    }
    private function readFrame(int $issuer,int $actor,string $kind,callable $verify):array
    {
        $series = self::series($kind);
        IssuerTransaction::assertOwned($this->db, $this->prefix, $this->tables());
        $verify();
        $rows = IssuerStorage::query($this->db,
            'SELECT next_number FROM '.$this->prefix.'issuers_sequences WHERE issuer_id=? AND series=? FOR UPDATE',
            [$issuer, $series])->result_array();
        if (count($rows) > 1) { throw new RuntimeException('native_sales_sequence_ambiguous'); }
        $next = self::number($rows ? (string)$rows[0]['next_number'] : '1');
        $documents = IssuerStorage::query($this->db,
            'SELECT number FROM '.$this->prefix.'company_sales_documents WHERE issuer_id=? AND kind=?',
            [$issuer, $kind])->result_array();
        foreach ($documents as $document) {
            $issued = self::number((string)$document['number']);
            if (self::compare($issued, $next) >= 0) {
                throw new RuntimeException('native_sales_sequence_conflicts_with_issued_document');
            }
        }
        $verify();
        return ['next_number'=>$next,
            'revision'=>hash('sha256', json_encode([$issuer,$kind,$series,(bool)$rows,$next], JSON_THROW_ON_ERROR))];
    }

    public function read(int $issuer, int $actor, string $kind): array
    {
        self::series($kind);
        $this->authorize($issuer, $actor, 'view');
        return IssuerTransaction::run($this->db, $this->prefix, $this->tables(),
            fn() => $this->readInOwnedTransaction($issuer, $actor, $kind));
    }

    public function saveInOwnedTransaction(int $issuer, int $actor, string $kind, string $next, string $revision): array
    {
        $series = self::series($kind);
        $next = self::number($next);
        $this->authorize($issuer, $actor, 'edit');
        $before = $this->readInOwnedTransaction($issuer, $actor, $kind);
        if (!preg_match('/^[a-f0-9]{64}$/D', $revision) || !hash_equals($before['revision'], $revision)) {
            throw new DomainException('native_sales_sequence_stale');
        }
        if (self::compare($next, $before['next_number']) < 0) {
            throw new DomainException('native_sales_sequence_regression');
        }
        if ($next === $before['next_number']) { return $before; }
        // CompanySales persists the document number in a signed INT.
        if (self::compare($next, '2147483647') > 0) {
            throw new DomainException('native_sales_sequence_unemittable');
        }
        $existing = IssuerStorage::query($this->db,
            'SELECT next_number FROM '.$this->prefix.'issuers_sequences WHERE issuer_id=? AND series=?',
            [$issuer,$series])->row_array();
        if ($existing) {
            IssuerStorage::query($this->db,
                'UPDATE '.$this->prefix.'issuers_sequences SET next_number=? WHERE issuer_id=? AND series=? AND next_number=?',
                [$next,$issuer,$series,$before['next_number']]);
        } else {
            IssuerStorage::query($this->db,
                'INSERT INTO '.$this->prefix.'issuers_sequences(issuer_id,series,next_number) VALUES(?,?,?)',
                [$issuer,$series,$next]);
        }
        $after = $this->readInOwnedTransaction($issuer, $actor, $kind);
        if ($after['next_number'] !== $next) { throw new RuntimeException('native_sales_sequence_write_unconfirmed'); }
        $metadata = json_encode(['series'=>$series,'before'=>$before['next_number'],'after'=>$next,
            'nonce'=>bin2hex(random_bytes(16))], JSON_THROW_ON_ERROR);
        IssuerStorage::query($this->db,
            'INSERT INTO '.$this->prefix.'issuers_audit(issuer_id,actor_id,event,metadata_json,created_at) VALUES(?,?,?,?,?)',
            [$issuer,$actor,'native_sales_sequence_advanced',$metadata,gmdate('Y-m-d H:i:s')]);
        $receipt = IssuerStorage::query($this->db,
            'SELECT id FROM '.$this->prefix.'issuers_audit WHERE issuer_id=? AND actor_id=? AND event=? AND metadata_json=?',
            [$issuer,$actor,'native_sales_sequence_advanced',$metadata])->result_array();
        if (count($receipt) !== 1) { throw new RuntimeException('native_sales_sequence_audit_unconfirmed'); }
        $this->authorize($issuer, $actor, 'edit');
        return $after;
    }

    public function save(int $issuer, int $actor, string $kind, string $next, string $revision): array
    {
        self::series($kind);
        $this->authorize($issuer, $actor, 'edit');
        return IssuerTransaction::run($this->db, $this->prefix, $this->tables(),
            fn() => $this->saveInOwnedTransaction($issuer, $actor, $kind, $next, $revision));
    }
}
