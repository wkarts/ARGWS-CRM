<?php

declare(strict_types=1);
require_once __DIR__ . '/issuers/IssuerRegistry.php';

/** Interactive staff selection only. Workers/jobs must pass explicit IDs to their own scope. */
final class CompanyContext
{
    public const SELECTION = 'company_context_selection';
    public const NONCE = 'company_context_nonce';
    public const CSRF = 'company_context_switch_csrf';
    private IssuerRegistry $registry;
    private IssuerResourceAccess $access;
    private $session;
    private int $staffId;
    private ?int $issuerId = null;

    public function __construct($db, string $prefix, $session, $staffId)
    {
        $this->registry = new IssuerRegistry($db, $prefix);
        $this->access = new IssuerResourceAccess($db, $prefix);
        $this->session = $session;
        $this->staffId = IssuerPolicy::id($staffId);
    }

    public function resolve(bool $recovery = false, bool $allowEmpty = false): ?int
    {
        // Validates active staff even when there is no selected/default company.
        $this->registry->available($this->staffId);
        $selection = $this->session->userdata(self::SELECTION);
        if ($selection !== null) {
            if (!is_array($selection) || ($selection['staff_id'] ?? null) !== $this->staffId
                || !$this->access->member($selection['issuer_id'] ?? null, $this->staffId)) {
                if (!$recovery) { throw new DomainException('company_context_membership_revoked'); }
                $this->session->unset_userdata(self::SELECTION);
                $this->rotate();
            } else {
                return $this->issuerId = IssuerPolicy::id($selection['issuer_id']);
            }
        }
        $default = $this->registry->defaultForStaff($this->staffId);
        // Only the proven installer master has an implicit primary-company default.
        if ($default === null && $this->access->isMaster($this->staffId)) { $default = $this->access->primaryId(); }
        if ($default !== null && $this->access->member($default, $this->staffId)) {
            $this->issuerId = $default;
            $this->session->set_userdata(self::SELECTION, ['staff_id' => $this->staffId, 'issuer_id' => $default]);
            return $default;
        }
        if (!$recovery && !$allowEmpty) { throw new DomainException('company_context_selection_required'); }
        return null;
    }

    public function staffId(): int { return $this->staffId; }
    public function issuerId(): ?int { return $this->issuerId; }
    public function isPrimary(): bool { return $this->issuerId !== null && $this->issuerId === $this->access->primaryId(); }
    public function available(): array { return $this->registry->available($this->staffId); }
    public function selected(): ?array { return $this->issuerId === null ? null : $this->registry->get($this->issuerId, $this->staffId); }

    private function secret(string $key): string
    {
        $secret = $this->session->userdata($key);
        if (!is_string($secret) || !preg_match('/^[a-f0-9]{64}$/D', $secret)) {
            $secret = bin2hex(random_bytes(32));
            $this->session->set_userdata($key, $secret);
        }
        return $secret;
    }

    public function token(): string
    {
        $payload = $this->staffId . ':' . ($this->issuerId ?? 0);
        return $payload . ':' . hash_hmac('sha256', $payload, $this->secret(self::NONCE));
    }

    public function csrf(): string { return $this->secret(self::CSRF); }
    /** Separate CSRF purpose; changing company rotates the token's context binding. */
    public function nativeCsrf(): string
    {
        return hash_hmac('sha256', 'native-admin:' . $this->token(), $this->secret('company_native_admin_csrf'));
    }

    public function assertToken($token): void
    {
        if (!is_string($token) || !hash_equals($this->token(), $token)) {
            throw new DomainException('company_context_mismatch');
        }
        if ($this->issuerId !== null && !$this->access->member($this->issuerId, $this->staffId)) {
            throw new DomainException('company_context_membership_revoked');
        }
    }

    private function rotate(): void
    {
        $this->session->set_userdata(self::NONCE, bin2hex(random_bytes(32)));
        $this->session->set_userdata(self::CSRF, bin2hex(random_bytes(32)));
    }

    /** Select for the next request only. Never repurpose this request's runtime or original body. */
    public function select($target, $csrf, $token): bool
    {
        $this->assertToken($token);
        if (!is_string($csrf) || !hash_equals($this->csrf(), $csrf)) { throw new DomainException('company_context_invalid_csrf'); }
        $target = IssuerPolicy::id($target);
        if (!$this->access->member($target, $this->staffId)) { throw new DomainException('company_context_access_denied'); }
        $this->session->set_userdata(self::SELECTION, ['staff_id' => $this->staffId, 'issuer_id' => $target]);
        $this->rotate();
        return $target === $this->access->primaryId();
    }
}
