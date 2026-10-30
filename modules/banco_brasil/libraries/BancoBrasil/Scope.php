<?php
declare(strict_types=1);
namespace BancoBrasil;

/** Immutable local binding. accountId is a canonical account reference, not proof of ownership. */
final class Scope
{
    public function __construct(
        public readonly int $issuerId,
        public readonly int $connectionId,
        public readonly string $accountId,
        public readonly string $environment,
        public readonly string $agreement
    ) {
        if ($issuerId < 1 || $connectionId < 1
            || !preg_match('/^001:[0-9]{5}-[0-9X]:[0-9]{12}-[0-9X]$/D', $accountId)
            || !in_array($environment, ['sandbox', 'production'], true)
            || !preg_match('/^[1-9][0-9]{6}$/D', $agreement)) {
            throw new \InvalidArgumentException('bb_scope_invalid');
        }
    }
    public function key(): string
    {
        return hash('sha256', json_encode([$this->issuerId, $this->connectionId, 'banco_brasil',
            $this->accountId, $this->environment, $this->agreement], JSON_THROW_ON_ERROR));
    }
    public function assertSame(self $other): void
    {
        if (!hash_equals($this->key(), $other->key())) throw new \DomainException('bb_scope_mismatch');
    }
}
