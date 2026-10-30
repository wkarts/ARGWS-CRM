<?php
/** CLI-only, read-only aggregate diagnostics. Credentials are environment-only. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/application/services/StaffEmailIdentitySchema.php';
try {
    $dsn = getenv('STAFF_IDENTITY_DSN');
    $prefix = getenv('STAFF_IDENTITY_PREFIX') ?: 'tbl';
    if (!is_string($dsn) || !str_starts_with($dsn, 'mysql:') || !str_contains($dsn, 'charset=utf8mb4')) {
        throw new RuntimeException('configuration');
    }
    $pdo = new PDO($dsn, getenv('STAFF_IDENTITY_USER') ?: '', getenv('STAFF_IDENTITY_PASSWORD') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->exec('SET TRANSACTION READ ONLY');
    $pdo->beginTransaction();
    $db = new class($pdo) {
        public function __construct(public PDO $pdo) {}
        public function query(string $sql, array $params = []) {
            $query = $this->pdo->prepare($sql);
            $query->execute($params);
            return new class($query->fetchAll(PDO::FETCH_ASSOC)) {
                public function __construct(private array $rows) {}
                public function result_array(): array { return $this->rows; }
            };
        }
    };
    $report = StaffEmailIdentitySchema::preflight($db, $prefix);
    $pdo->rollBack();
    echo json_encode($report, JSON_THROW_ON_ERROR).PHP_EOL;
    if ($report['invalid_rows'] || $report['duplicate_groups']) {
        fwrite(STDERR, "Upgrade bloqueado: revise as identidades de equipe em acesso administrativo restrito. Nenhuma identidade ou vínculo foi alterado.\n");
        exit(2);
    }
    exit(0);
} catch (Throwable $error) {
    // SQL, bindings, DSNs, email addresses and driver error text must not enter deploy logs.
    fwrite(STDERR, "Preflight indisponível. Verifique conexão, prefixo e permissão de leitura; nenhum dado foi alterado.\n");
    exit(1);
}
