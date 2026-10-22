<?php
/** Real production models with a PDO/SQLite query-builder adapter; not a full CI HTTP runtime. */
define('BASEPATH', __DIR__);
require __DIR__ . '/../../application/services/ClientIssuerScope.php';
require __DIR__ . '/../../application/services/utilities/Arr.php';
#[AllowDynamicProperties]
class App_Model { public $db; }
require __DIR__ . '/../../application/models/Clients_model.php';
require __DIR__ . '/../../application/models/Client_vault_entries_model.php';
require __DIR__ . '/../../application/models/Client_groups_model.php';
class ClientModelProbe extends Clients_model { public function __construct() {} }
class ClientVaultProbe extends Client_vault_entries_model { public function __construct() {} }
class ClientGroupsProbe extends Client_groups_model { public function __construct() {} }
class ClientModelResult {
    public function __construct(private array $rows) {}
    public function row() { return $this->rows ? (object) $this->rows[0] : null; }
    public function result_array() { return $this->rows; }
}
class ClientModelDb {
    private array $where = [], $args = [], $joins = [];
    private string $select = '*', $from = '';
    private int $affected = 0;
    public int $writes = 0;
    public function __construct(public PDO $pdo) {}
    public function table_exists($name) {
        $q = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
        $q->execute([$name]); return (bool) $q->fetch();
    }
    public function field_exists($field, $table) {
        foreach ($this->pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC) as $row) if ($row['name'] === $field) return true;
        return false;
    }
    public function like($key, $value, $side = 'both') { return $this->addLike($key, $value, $side, false); }
    public function or_like($key, $value, $side = 'both') { return $this->addLike($key, $value, $side, true); }
    private function addLike($key, $value, $side, $or) {
        $this->condition($key." LIKE ? ESCAPE '!'", $or);
        $value = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
        $this->args[] = ($side === 'before' || $side === 'both' ? '%' : '').$value.($side === 'after' || $side === 'both' ? '%' : '');
        return $this;
    }
    public function query($sql, $args = []) {
        $q = $this->pdo->prepare($sql); $q->execute($args);
        return new ClientModelResult($q->fetchAll(PDO::FETCH_ASSOC));
    }
    public function select($sql) { $this->select = $sql; return $this; }
    public function from($table) { $this->from = $table; return $this; }
    public function join($table, $on, $type = '') { $this->joins[] = "$type JOIN $table ON $on"; return $this; }
    private function condition($condition, $or = false) {
        if ($this->where && end($this->where) !== '(') $this->where[] = $or ? 'OR' : 'AND';
        $this->where[] = $condition;
    }
    public function where($key, $val = null, $escape = null) {
        if (is_array($key)) { foreach ($key as $k => $v) $this->where($k, $v); return $this; }
        $this->condition($key . ($val === null ? '' : (preg_match('/[=<>!]$/', trim($key)) ? ' ?' : '=?')));
        if ($val !== null) $this->args[] = $val;
        return $this;
    }
    public function where_in($key, $vals) {
        $this->condition($key . ' IN (' . implode(',', array_fill(0, count($vals), '?')) . ')');
        array_push($this->args, ...$vals); return $this;
    }
    public function group_start() { $this->condition('('); return $this; }
    public function group_end() { $this->where[] = ')'; return $this; }
    public function order_by(...$args) { return $this; }
    public function limit(...$args) { return $this; }
    public function escape($value) { return $this->pdo->quote($value); }
    public function escape_str($value) { return str_replace("'", "''", $value); }
    public function escape_like_str($value) { return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $this->escape_str($value)); }
    private function conditions() { return $this->where ? ' WHERE ' . implode(' ', $this->where) : ''; }
    private function reset() { $this->select = '*'; $this->from = ''; $this->where = $this->args = $this->joins = []; }
    public function get($table = '') {
        $sql = 'SELECT ' . $this->select . ' FROM ' . ($table ?: $this->from) . ' ' . implode(' ', $this->joins) . $this->conditions();
        $args = $this->args; $this->reset(); return $this->query($sql, $args);
    }
    public function update($table, $data) {
        if (!str_starts_with($table, db_prefix())) $table = db_prefix() . $table;
        $sql = 'UPDATE ' . $table . ' SET ' . implode(',', array_map(fn($k) => $k . '=?', array_keys($data))) . $this->conditions();
        $args = array_merge(array_values($data), $this->args); $this->reset();
        $q = $this->pdo->prepare($sql); $q->execute($args); $this->affected = $q->rowCount(); $this->writes++; return true;
    }
    public function delete($table) {
        $sql = 'DELETE FROM ' . $table . $this->conditions(); $args = $this->args; $this->reset();
        $q = $this->pdo->prepare($sql); $q->execute($args); $this->affected = $q->rowCount(); $this->writes++; return true;
    }
    public function insert($table, $data) {
        $q = $this->pdo->prepare('INSERT INTO ' . $table . ' (' . implode(',', array_keys($data)) . ') VALUES (' . implode(',', array_fill(0, count($data), '?')) . ')');
        $q->execute(array_values($data)); $this->affected = $q->rowCount(); $this->writes++; return true;
    }
    public function affected_rows() { return $this->affected; }
    public function insert_id() { return (int) $this->pdo->lastInsertId(); }
}
function db_prefix() { return 'q_'; }
function prefixed_table_fields_array($table) { return [$table . '.*']; }
if (!defined('CLIENT_ISSUER_REAL_HELPERS')) { function get_sql_select_client_company() { return 'q_clients.company'; }
}
function get_option($key) { return 1; }
function hooks() { return $GLOBALS['hooks']; }
function log_activity(...$args) { $GLOBALS['effects'][] = 'log'; }
function handle_custom_fields_post(...$args) { $GLOBALS['effects'][] = 'custom_fields'; return false; }
function do_action_deprecated(...$args) { $GLOBALS['effects'][] = 'deprecated_hook'; }
function clear_textarea_breaks($value) { return $value; }
if (!defined('CLIENT_ISSUER_REAL_HELPERS')) { function is_email_verification_enabled() { return false; }
}
if (!defined('CLIENT_ISSUER_REAL_HELPERS')) { function get_contact_permissions() { return []; }
}
function app_hash_password($value) { $GLOBALS['effects'][] = 'hash'; if(isset($GLOBALS['beforeRecoveryHash'])){$fn=$GLOBALS['beforeRecoveryHash'];unset($GLOBALS['beforeRecoveryHash']);$fn();} return password_hash($value, PASSWORD_DEFAULT); }
function _l($value) { return $value; }
function get_custom_fields($value) { return []; }
if (!defined('CLIENT_ISSUER_REAL_HELPERS')) { function get_upload_path_by_type($type) { return $GLOBALS['client_test_upload_paths'][$type] ?? '/no-fixtures-on-disk/'; } }
function is_gdpr() { return false; }
function get_staff_user_id() { return 7; }
function get_client_user_id() { return 1; }
function get_contact_user_id() { return 10; }
function staff_can(...$args) { return $GLOBALS['client_test_staff_can'] ?? true; }
function staff_cant(...$args) { return false; }
if (!defined('CLIENT_ISSUER_REAL_HELPERS')) { function have_assigned_customers() { return true; }
}
function is_admin() { return true; }
function &get_instance() { return $GLOBALS['ci']; }
$GLOBALS['effects'] = [];
$GLOBALS['hooks'] = new class {
    public function apply_filters($name, $value, ...$args) { $GLOBALS['effects'][] = $name; return $value; }
    public function do_action($name, ...$args) { $GLOBALS['effects'][] = $name; }
};
$pdo = new PDO('sqlite::memory:'); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
// Synthetic staff emails are ASCII. This adapter enables the real identity SQL;
// it is not a proof of MySQL/MariaDB Unicode collation semantics.
$pdo->sqliteCreateCollation('utf8mb4_unicode_ci', static fn($a, $b) => strcasecmp($a, $b));
$concat = fn(...$parts) => implode('', $parts);
if (method_exists($pdo, 'createFunction')) { $pdo->createFunction('CONCAT', $concat); } else { @$pdo->sqliteCreateFunction('CONCAT', $concat); }
$pdo->exec("CREATE TABLE q_clients(userid INTEGER PRIMARY KEY,company TEXT,vat TEXT,country INT,active INT DEFAULT 1,default_currency INT DEFAULT 1,show_primary_contact INT DEFAULT 0,billing_country INT DEFAULT 0,shipping_country INT DEFAULT 0,address TEXT DEFAULT '',billing_street TEXT DEFAULT '',billing_city TEXT DEFAULT '',billing_state TEXT DEFAULT '',billing_zip TEXT DEFAULT '',shipping_street TEXT DEFAULT '',shipping_city TEXT DEFAULT '',shipping_state TEXT DEFAULT '',shipping_zip TEXT DEFAULT '');
CREATE TABLE q_countries(country_id INT,short_name TEXT);
CREATE TABLE q_contacts(id INTEGER PRIMARY KEY,userid INT,is_primary INT,active INT,email TEXT,firstname TEXT DEFAULT '',lastname TEXT DEFAULT '',password TEXT DEFAULT 'private-hash',new_pass_key TEXT DEFAULT 'private-reset',new_pass_key_requested TEXT DEFAULT '',email_verification_key TEXT DEFAULT 'private-verification',email_verified_at TEXT,email_verification_sent_at TEXT,last_password_change TEXT);
CREATE TABLE q_issuers_clients(client_id INT,owner_issuer_id INT);
CREATE TABLE q_issuers_client_links(client_id INT,issuer_id INT);
CREATE TABLE q_customer_admins(customer_id INT,staff_id INT);
CREATE TABLE q_customer_groups(id INTEGER PRIMARY KEY,customer_id INT,groupid INT);
CREATE TABLE q_vault(id INTEGER PRIMARY KEY,customer_id INT,description TEXT,share_in_projects INT DEFAULT 0,last_updated_from INT,last_updated TEXT,date_created TEXT);
CREATE TABLE q_files(id INT,rel_id INT,rel_type TEXT,file_name TEXT,external TEXT,visible_to_customer INT);
INSERT INTO q_countries VALUES(1,'Legacy country'),(2,'Private country');
INSERT INTO q_clients(userid,company,vat,country) VALUES(1,'Legacy','l',1),(2,'Private X','x',2),(3,'Private Y','y',2);
INSERT INTO q_contacts(id,userid,is_primary,active,email) VALUES(10,1,1,1,'l@example.test'),(20,2,1,1,'x@example.test'),(30,3,1,1,'y@example.test');
INSERT INTO q_issuers_clients VALUES(2,11),(3,22);
INSERT INTO q_issuers_client_links VALUES(2,22);
INSERT INTO q_customer_admins VALUES(2,7),(1,8);
INSERT INTO q_vault(id,customer_id,description) VALUES(1,1,'Legacy vault'),(2,2,'Private vault');
INSERT INTO q_files VALUES(1,1,'customer','legacy.txt','external',1),(2,2,'customer','private.txt','external',1),(3,1,'task','task.txt','external',1);
INSERT INTO q_customer_groups VALUES(1,1,8),(2,2,9)");
$model = new ClientModelProbe; $model->db = new ClientModelDb($pdo);
$vault = new ClientVaultProbe; $vault->db = $model->db;
$groups = new ClientGroupsProbe; $groups->db = $model->db;
$model->client_vault_entries_model = $vault;
$model->client_groups_model = $groups;
$GLOBALS['ci'] = $model;
$n = 0;
function check($ok, $message) { global $n; $n++; if (!$ok) throw new RuntimeException($message); }
