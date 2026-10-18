<?php

declare(strict_types=1);
require_once __DIR__ . '/CompanySettingsStorage.php';

/** Explicit bounded request scope. No session/default selection or mutable public setter. */
final class CompanySettingsRuntime
{
    private static ?self $current = null;
    private CompanySettingsStorage $storage;
    private IssuerResourceAccess $access;
    private int $issuerId;
    private int $staffId;

    private function __construct($db, string $prefix, $issuerId, $staffId)
    {
        $this->issuerId = IssuerPolicy::id($issuerId);
        $this->staffId = IssuerPolicy::id($staffId);
        $this->storage = new CompanySettingsStorage($db, $prefix);
        $this->access = new IssuerResourceAccess($db, $prefix);
        $this->assertMember();
        $this->storage->isPrimary($this->issuerId); // Unresolved installation never falls through.
    }

    public static function run($db, string $prefix, $issuerId, $staffId, callable $operation)
    {
        $release = self::enterRequest($db, $prefix, $issuerId, $staffId);
        try { return $operation(); } finally { $release(); }
    }

    /** Framework lifecycle lease; only its owner can close it. Repeated release is harmless. */
    public static function enterRequest($db, string $prefix, $issuerId, $staffId): callable
    {
        if (self::$current !== null) { throw new DomainException('company_settings_scope_change_denied'); }
        $scope = new self($db, $prefix, $issuerId, $staffId);
        self::$current = $scope;
        $released = false;
        return static function () use ($scope, &$released): void {
            if ($released) { return; }
            $released = true;
            if (self::$current === $scope) { self::$current = null; }
        };
    }

    public static function active(): bool { return self::$current !== null; }
    public static function issuerId(): ?int { return self::$current === null ? null : self::$current->issuerId; }
    public static function staffId(): ?int { return self::$current === null ? null : self::$current->staffId; }
    private function assertMember(): void
    {
        if (!$this->access->member($this->issuerId, $this->staffId)) { throw new DomainException('company_settings_access_denied'); }
    }
    private static function scope(): self
    {
        if (self::$current === null) { throw new DomainException('company_settings_context_required'); }
        self::$current->assertMember();
        return self::$current;
    }
    public static function isPrimary(): bool { $s = self::scope(); return $s->storage->isPrimary($s->issuerId); }
    public static function can(string $capability): bool
    {
        $s = self::scope();
        return $s->access->can($s->issuerId, $s->staffId, 'settings', $capability);
    }
    public static function canResource(string $feature,string $capability): bool
    {
        $s=self::scope(); return $s->access->can($s->issuerId,$s->staffId,$feature,$capability);
    }
    public static function canInfrastructure(): bool
    {
        $s = self::scope();
        return $s->storage->isPrimary($s->issuerId) && $s->access->administrative($s->issuerId,$s->staffId);
    }
    public static function assertCan(string $capability): void
    {
        if (!self::can($capability)) { throw new DomainException('company_settings_access_denied'); }
    }
    public static function all(): array { $s = self::scope(); return $s->storage->all($s->issuerId); }
    public static function get($name): string { $s = self::scope(); return $s->storage->get($s->issuerId, $name); }
    public static function exists($name): bool { $s = self::scope(); return $s->storage->exists($s->issuerId, $name); }
    public static function update($name, $value, $autoload = null): bool { return self::mutate('update', $name, $value, $autoload); }
    public static function add($name, $value = '', $autoload = 1): bool { return self::mutate('add', $name, $value, $autoload); }
    public static function delete($name): bool { return self::mutate('delete', $name, '', null); }
    public static function updateBatch(array $values): int
    {
        $s=self::scope();return $s->storage->updateBatch($s->issuerId,$s->staffId,$values);
    }
    public static function saveIdentity(array $input): bool
    {
        $s = self::scope();
        return $s->storage->saveIdentity($s->issuerId, $s->staffId, $input);
    }
    private static function mutate(string $operation, $name, $value, $autoload): bool
    {
        $s = self::scope();
        return $s->storage->mutate($s->issuerId, $s->staffId, $operation, $name, $value, $autoload);
    }
}
