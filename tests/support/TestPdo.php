<?php
declare(strict_types=1);

/** PHP 8.5's driver-specific PDO classes keep SQLite fixtures free of deprecated PDO methods. */
final class TestPdo
{
    public static function connect(string $dsn, ?string $username = null, ?string $password = null, array $options = []): PDO
    {
        if (str_starts_with($dsn, 'sqlite:') && class_exists('Pdo\\Sqlite')) {
            $class = 'Pdo\\Sqlite';
            return new $class($dsn, $username, $password, $options);
        }
        return new PDO($dsn, $username, $password, $options);
    }

    public static function registerSqliteFunction(PDO $pdo, string $name, callable $callback, int $arguments = -1, int $flags = 0): void
    {
        if (class_exists('Pdo\\Sqlite')) {
            $class = 'Pdo\\Sqlite';
            if (!$pdo instanceof $class) {
                throw new LogicException('PHP 8.5 SQLite test connection must use TestPdo::connect');
            }
            $pdo->createFunction($name, $callback, $arguments, $flags);
            return;
        }
        $pdo->sqliteCreateFunction($name, $callback, $arguments, $flags);
    }

    public static function registerSqliteCollation(PDO $pdo, string $name, callable $callback, int $flags = 0): void
    {
        if (class_exists('Pdo\\Sqlite')) {
            $class = 'Pdo\\Sqlite';
            if (!$pdo instanceof $class) {
                throw new LogicException('PHP 8.5 SQLite test connection must use TestPdo::connect');
            }
            $pdo->createCollation($name, $callback);
            return;
        }
        $pdo->sqliteCreateCollation($name, $callback, $flags);
    }
}
