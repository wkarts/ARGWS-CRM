<?php
declare(strict_types=1);
/** Real disposable MySQL/MariaDB proof. Never loads production config or sends mail. */
define('BASEPATH', __DIR__);
require_once __DIR__.'/../application/services/StaffEmailIdentity.php';
require_once __DIR__.'/../application/services/StaffEmailIdentitySchema.php';

final class IdentityResult
{
    public function __construct(private array $rows) {}
    public function result_array(): array { return $this->rows; }
    public function row_array(): array { return $this->rows[0] ?? []; }
    public function row(): ?object { return isset($this->rows[0]) ? (object)$this->rows[0] : null; }
}
final class IdentityDb
{
    public bool $db_debug = false, $save_queries = false;
    public string $dbdriver = 'pdo';
    public PDO $conn_id;
    public function __construct(public PDO $pdo) { $this->conn_id = $pdo; }
    public function query(string $sql, array $args = []): IdentityResult
    {
        $stmt = $this->pdo->prepare($sql); $stmt->execute($args);
        return new IdentityResult($stmt->columnCount() ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []);
    }
    public function field_exists(string $field, string $table): bool
    { return (bool)$this->query('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?', [$table,$field])->result_array(); }
    public function table_exists(string $table): bool
    { return (bool)$this->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table])->result_array(); }
}
function identityConnect(): PDO
{
    $dsn = getenv('STAFF_IDENTITY_TEST_DSN');
    if (!is_string($dsn) || !str_starts_with($dsn, 'mysql:')) { throw new RuntimeException('Explicit disposable MySQL test DSN required'); }
    return new PDO($dsn, getenv('STAFF_IDENTITY_TEST_USER') ?: 'root', getenv('STAFF_IDENTITY_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
}
function identityWrite(PDO $pdo, string $table, string $operation, int $id, string $email): void
{
    if ($operation === 'create') { $stmt=$pdo->prepare("INSERT INTO $table (staffid,email,active) VALUES (?,?,1)"); $stmt->execute([$id,$email]); }
    else { $stmt=$pdo->prepare("UPDATE $table SET email=? WHERE staffid=?"); $stmt->execute([$email,$id]); }
}
// The child receives synthetic fixture names only; process output contains no SQL/data.
if (($argv[1] ?? '') === 'contender') {
    if (!preg_match('/^sid_[a-f0-9]{16}_staff$/D', $argv[2] ?? '')) { exit(3); }
    $pdo=identityConnect(); $pdo->exec('SET SESSION innodb_lock_wait_timeout=10');
    echo "READY\n"; flush();
    try { identityWrite($pdo,$argv[2],$argv[3],(int)$argv[4],' RACE@EXAMPLE.INVALID '); echo "WRITTEN\n"; }
    catch (PDOException $e) { echo (int)($e->errorInfo[1] ?? 0) === 1062 ? "DUPLICATE\n" : "UNEXPECTED_DATABASE_FAILURE\n"; }
    exit;
}
$checks=0; $prefix='sid_'.bin2hex(random_bytes(8)).'_'; $table=$prefix.'staff'; $pdo=identityConnect(); $db=new IdentityDb($pdo);
function check(bool $ok, string $label): void { global $checks; ++$checks; if (!$ok) { throw new RuntimeException('FAIL: '.$label); } }
function rejected(callable $fn, string $label): Throwable
{ try { $fn(); } catch(Throwable $e) { check(true,$label); return $e; } throw new RuntimeException('FAIL: expected rejection '.$label); }
function snapshot(PDO $pdo,string $table): array { return $pdo->query("SELECT staffid,email,active FROM $table ORDER BY staffid")->fetchAll(PDO::FETCH_ASSOC); }
function resetTable(PDO $pdo,string $table): void
{
    $pdo->exec("DROP TABLE IF EXISTS $table");
    $pdo->exec("CREATE TABLE $table (staffid INT NOT NULL PRIMARY KEY,email VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,active INT NOT NULL DEFAULT 1) ENGINE=InnoDB");
}
try {
    resetTable($pdo,$table);
    identityWrite($pdo,$table,'create',1,' Owner@Example.Invalid ');
    $before=snapshot($pdo,$table);
    $report=StaffEmailIdentitySchema::preflight($db,$prefix);
    check($report===['rows'=>1,'invalid_rows'=>0,'duplicate_groups'=>0],'preflight structured counts');
    check(!str_contains(json_encode($report,JSON_THROW_ON_ERROR),'@'),'preflight contains no personal email');
    StaffEmailIdentitySchema::ensure($db,$prefix);
    check(snapshot($pdo,$table)===$before,'migration preserves original email and identity');
    StaffEmailIdentitySchema::ensure($db,$prefix);
    check(snapshot($pdo,$table)===$before,'replay preserves identity');
    $key=$pdo->query("SELECT staff_email_identity FROM $table WHERE staffid=1")->fetchColumn();
    check($key==='owner@example.invalid','generated key uses lower and ASCII space trim');
    $index=$db->query('SELECT NON_UNIQUE,COLUMN_NAME,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?',[$table,'uq_staff_email_identity'])->result_array();
    check(count($index)===1 && (string)$index[0]['NON_UNIQUE']==='0' && $index[0]['COLUMN_NAME']==='staff_email_identity' && $index[0]['SUB_PART']===null,'full global unique index');
    $error=rejected(fn()=>identityWrite($pdo,$table,'create',2,'OWNER@example.invalid'),'direct insert collision');
    check($error instanceof PDOException && (int)($error->errorInfo[1]??0)===1062,'database enforces direct writer collision');
    identityWrite($pdo,$table,'create',2,'other@example.invalid');
    rejected(fn()=>identityWrite($pdo,$table,'update',2,' owner@example.invalid '),'direct update collision');
    check(StaffEmailIdentity::unique($db,$prefix,' OWNER@example.invalid ')->staffid==1,'unique lookup canonical identity');
    check(StaffEmailIdentity::assertAvailable($db,$prefix,' OWNER@example.invalid ',1)==='owner@example.invalid','same identity may retain email');
    rejected(fn()=>StaffEmailIdentity::assertAvailable($db,$prefix,'OWNER@example.invalid',2),'different identity conflicts');
    $pdo->exec("UPDATE $table SET active=0 WHERE staffid=1");
    rejected(fn()=>StaffEmailIdentity::assertAvailable($db,$prefix,'owner@example.invalid',2),'inactive identity reserves global email');

    foreach ([['create','create'],['create','update'],['update','create'],['update','update']] as [$first,$second]) {
        $pdo->exec("DELETE FROM $table");
        if ($first==='update') { identityWrite($pdo,$table,'create',10,'first@example.invalid'); }
        if ($second==='update') { identityWrite($pdo,$table,'create',20,'second@example.invalid'); }
        $pdo->beginTransaction(); identityWrite($pdo,$table,$first,10,'race@example.invalid');
        $process=proc_open([PHP_BINARY,__FILE__,'contender',$table,$second,'20'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        check(is_resource($process),'independent race process started'); fclose($pipes[0]);
        stream_set_timeout($pipes[1],15);
        check(trim((string)fgets($pipes[1]))==='READY','independent race connection ready');
        usleep(200000);
        check(proc_get_status($process)['running'],'second writer remains live before first commits owned key');
        $pdo->commit();
        $output=trim(stream_get_contents($pipes[1])); $errors=stream_get_contents($pipes[2]);
        fclose($pipes[1]);fclose($pipes[2]);proc_close($process);
        check($output==='DUPLICATE' && $errors==='','concurrent '.$first.'/'.$second.' has exactly one winner');
        check((int)$pdo->query("SELECT COUNT(*) FROM $table WHERE staff_email_identity='race@example.invalid'")->fetchColumn()===1,'race preserves global singleton');
    }

    resetTable($pdo,$table);
    identityWrite($pdo,$table,'create',1,'duplicate@example.invalid'); identityWrite($pdo,$table,'create',2,' DUPLICATE@EXAMPLE.INVALID ');
    $pdo->exec("UPDATE $table SET active=0 WHERE staffid=2");
    $before=snapshot($pdo,$table);
    check(StaffEmailIdentity::unique($db,$prefix,'duplicate@example.invalid')===null,'ambiguous legacy lookup fails closed including inactive');
    $report=StaffEmailIdentitySchema::preflight($db,$prefix);
    check($report===['rows'=>2,'invalid_rows'=>0,'duplicate_groups'=>1],'preflight counts inactive duplicate group');
    check(!str_contains(json_encode($report,JSON_THROW_ON_ERROR),'@'),'duplicate preflight redacts email');
    $error=rejected(fn()=>StaffEmailIdentitySchema::ensure($db,$prefix),'duplicate upgrade refuses');
    check($error->getMessage()==='issuer_staff_email_preflight_failed','upgrade has actionable redacted error code');
    check(snapshot($pdo,$table)===$before,'duplicate upgrade changes no identity');
    check(!$db->field_exists('staff_email_identity',$table),'duplicate preflight runs before DDL');
    resetTable($pdo,$table);
    identityWrite($pdo,$table,'create',1,'café@example.invalid'); identityWrite($pdo,$table,'create',2,'cafe@example.invalid');
    $before=snapshot($pdo,$table); $report=StaffEmailIdentitySchema::preflight($db,$prefix);
    check($report===['rows'=>2,'invalid_rows'=>1,'duplicate_groups'=>1],'Unicode database collation detects accent equivalent legacy identities');
    check(StaffEmailIdentity::unique($db,$prefix,'cafe@example.invalid')===null,'Unicode ambiguous legacy identity fails closed');
    rejected(fn()=>StaffEmailIdentitySchema::ensure($db,$prefix),'Unicode legacy conflict blocks migration');
    check(snapshot($pdo,$table)===$before,'Unicode preflight never normalizes or renames legacy identity');
    resetTable($pdo,$table); identityWrite($pdo,$table,'create',1,'partial@example.invalid'); $before=snapshot($pdo,$table);
    $pdo->exec("ALTER TABLE $table ADD staff_email_identity VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (LOWER(TRIM(email))) STORED");
    StaffEmailIdentitySchema::ensure($db,$prefix);
    check(snapshot($pdo,$table)===$before,'partial DDL replay preserves identity and installs missing index');
    rejected(fn()=>identityWrite($pdo,$table,'create',2,'PARTIAL@example.invalid'),'resumed index is enforced');
    $pdo->exec("ALTER TABLE $table DROP INDEX uq_staff_email_identity, ADD INDEX uq_staff_email_identity (staff_email_identity)");
    $error=rejected(fn()=>StaffEmailIdentitySchema::ensure($db,$prefix),'same-name nonunique index is not trusted');
    check($error->getMessage()==='issuer_staff_email_schema_invalid','tampered index actionable error');
    resetTable($pdo,$table); identityWrite($pdo,$table,'create',1,'malformed@example.invalid');
    $pdo->exec("ALTER TABLE $table ADD staff_email_identity VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (LOWER(email)) STORED");
    $error=rejected(fn()=>StaffEmailIdentitySchema::ensure($db,$prefix),'same-name wrong generated expression is not trusted');
    check($error->getMessage()==='issuer_staff_email_schema_invalid','tampered expression actionable error');
    resetTable($pdo,$table);identityWrite($pdo,$table,'create',1,"control\t@example.invalid");$before=snapshot($pdo,$table);
    rejected(fn()=>StaffEmailIdentitySchema::ensure($db,$prefix),'invalid legacy email refuses migration');
    check(snapshot($pdo,$table)===$before,'invalid legacy remains untouched');
    check(!$db->field_exists('staff_email_identity',$table),'invalid preflight runs before DDL');
    echo 'Staff email identity MySQL/MariaDB: '.$checks.' checks passed; server '.$pdo->getAttribute(PDO::ATTR_SERVER_VERSION).PHP_EOL;
} finally { if($pdo->inTransaction()){$pdo->rollBack();}$pdo->exec("DROP TABLE IF EXISTS $table"); }
