<?php

declare(strict_types=1);

final class IssuerTestResult
{
    private array $rows;
    public function __construct(array $rows) { $this->rows = $rows; }
    public function result_array(): array { return $this->rows; }
}
final class IssuerTestDb
{
    public PDO $pdo;
    public string $driver;
    public ?string $failOn = null;
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    public function query(string $sql, array $params = [])
    {
        if ($this->failOn !== null && strpos($sql, $this->failOn) !== false) {
            throw new RuntimeException('injected_storage_failure');
        }
        if ($this->driver === 'sqlite') { $sql = str_replace(' FOR UPDATE', '', $sql); }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return new IssuerTestResult($statement->columnCount() ? $statement->fetchAll(PDO::FETCH_ASSOC) : []);
    }
    public function trans_begin(): bool { return $this->pdo->beginTransaction(); }
    public function trans_commit(): bool { return $this->pdo->commit(); }
    public function trans_rollback(): bool { return $this->pdo->inTransaction() ? $this->pdo->rollBack() : true; }
    public function trans_status(): bool { return true; }
    public function insert_id(): int { return (int) $this->pdo->lastInsertId(); }
}
