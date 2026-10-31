<?php

declare(strict_types=1);

require_once __DIR__ . '/issuers/IssuerStorage.php';
require_once __DIR__ . '/issuers/IssuerTransaction.php';

/**
 * One read-only task-table operation. Only exact ACL, schema and relationship
 * snapshots are memoized; locks and financial checks always reach the database.
 * Every cached observation is re-read before any response is emitted.
 */
final class NativeCompanyTaskScopedDb
{
    public int $_trans_depth = 0;
    private array $queries = [];
    private array $fields = [];
    private array $draftReferences = [];
    private array $allowed;

    public function __construct(private object $connection, private string $prefix)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) throw new InvalidArgumentException('Invalid prefix');
        IssuerTransaction::assertIndependent($connection);
        $this->allowed = [
            "SELECT primary_issuer_id FROM {$prefix}issuers_installation_identity WHERE id=1",
            "SELECT s.staffid FROM {$prefix}staff s JOIN {$prefix}issuers_installation_identity i ON i.master_staff_id=s.staffid WHERE i.id=1 AND s.staffid=?",
            "SELECT s.staffid FROM {$prefix}staff s JOIN {$prefix}issuers_installation_identity i ON i.master_staff_id=s.staffid WHERE i.id=1 AND s.staffid=? AND s.active=1 AND s.admin=1",
            "SELECT id FROM {$prefix}issuers_registry WHERE id=? AND active=1",
            "SELECT staffid FROM {$prefix}staff WHERE staffid=? AND active=1",
            "SELECT issuer_id FROM {$prefix}issuers_staff WHERE issuer_id=? AND staff_id=? AND can_view=1",
            "SELECT issuer_id FROM {$prefix}issuers_resource_permissions WHERE issuer_id=? AND staff_id=? AND feature=? AND capability=?",
            "SELECT version FROM {$prefix}migrations",
            "SELECT id,active,settings_json FROM {$prefix}issuers_registry WHERE id=?",
            "SELECT t.id,t.project_id FROM {$prefix}company_tasks t JOIN {$prefix}company_projects p ON p.id=t.project_id AND p.issuer_id=t.issuer_id WHERE t.id=? AND t.issuer_id=?",
            "SELECT native_fields_json,native_fields_revision FROM {$prefix}company_standalone_tasks WHERE issuer_id=? AND id=?",
            "SELECT id AS source_id, name AS label FROM {$prefix}company_tasks WHERE id=? AND issuer_id=?",
            "SELECT id AS source_id, name AS label FROM {$prefix}company_standalone_tasks WHERE id=? AND issuer_id=?",
            "SELECT id,issuer_id,client_id,assignee_id FROM {$prefix}company_tickets WHERE issuer_id=? AND id=?",
            "SELECT owner_issuer_id FROM {$prefix}issuers_clients WHERE client_id=?",
            "SELECT userid FROM {$prefix}clients WHERE userid=?",
            "SELECT department_source_kind,department_source_id,contact_id,project_source_kind,project_source_id FROM {$prefix}company_ticket_native_metadata WHERE issuer_id=? AND ticket_id=?",
            "SELECT active,is_not_staff FROM {$prefix}staff WHERE staffid=?",
            "SELECT subject FROM {$prefix}company_tickets WHERE id=? AND issuer_id=?",
            "SELECT id,issuer_id,clientid FROM {$prefix}invoices WHERE id=?",
            "SELECT id AS source_id,number AS label FROM {$prefix}invoices WHERE id=? AND issuer_id=?",
            "SELECT id AS source_id,number AS label FROM {$prefix}invoices WHERE id=? AND clientid=? AND issuer_id IS NULL",
        ];
    }
    public function __get(string $name) { return $this->connection->$name; }
    public function __isset(string $name): bool { return isset($this->connection->$name); }
    public function __call(string $name, array $args) { return $this->connection->$name(...$args); }
    public function query(string $sql, array $params=[])
    {
        if (!preg_match('/^\s*(?:SELECT\b|PRAGMA\b|SHOW\b|DESCRIBE\b|EXPLAIN\b|SET\s+TRANSACTION\b)/i', $sql)) throw new RuntimeException('native_task_index_read_only');
        $schemaPattern = '/^PRAGMA\s+(?:table_info|index_list|index_info|foreign_key_list)\(`?(?:sqlite_autoindex_)?' . preg_quote($this->prefix, '/') . '[A-Za-z0-9_]+`?\)$/iD';
        $sqliteTables=preg_match("/^SELECT name FROM sqlite_master WHERE type='table' AND name IN \\(\\?(?:,\\?)*\\)$/D",$sql) && $params!==[] && count(array_filter($params,fn($name)=>is_string($name)&&str_starts_with($name,$this->prefix)))===count($params);
        if (!in_array($sql, $this->allowed, true) && !preg_match($schemaPattern, $sql) && !$sqliteTables) return IssuerStorage::query($this->connection, $sql, $params);
        $key = hash('sha256', json_encode([$sql,$params], JSON_THROW_ON_ERROR));
        if (!isset($this->queries[$key])) {
            $this->queries[$key] = ['sql'=>$sql,'params'=>$params,'rows'=>IssuerStorage::query($this->connection,$sql,$params)->result_array()];
        }
        return new NativeCompanyTaskScopedResult($this->queries[$key]['rows']);
    }
    public function field_exists(string $field, string $table): bool
    {
        if (!in_array($table, [$this->prefix.'issuers_installation_identity',$this->prefix.'issuers_resource_permissions'], true)
            && !str_starts_with($table, $this->prefix.'company_')) {
            return IssuerStorage::fieldExists($this->connection,$field,$table);
        }
        $key = $table."\0".$field;
        if (!array_key_exists($key,$this->fields)) $this->fields[$key] = ['table'=>$table,'field'=>$field,'present'=>IssuerStorage::fieldExists($this->connection,$field,$table)];
        return $this->fields[$key]['present'];
    }
    /** Cache one authorized draft DTO within this read; confirm re-resolves it outside the proxy. */
    public function draftReference(int $issuer,int $actor,string $canonical):array
    {
        $key=$issuer.':'.$actor.':'.$canonical;if(!isset($this->draftReferences[$key])){
            require_once __DIR__.'/NativeCompanyResourceReferences.php';$value=NativeCompanyResourceReferences::resolve($this,$this->prefix,$issuer,$actor,'invoice',$canonical,'view');
            if($value['source_kind']!=='company_invoice_draft')throw new DomainException('native_task_relation_invalid');$this->draftReferences[$key]=['issuer'=>$issuer,'actor'=>$actor,'id'=>$canonical,'value'=>$value];
        }return$this->draftReferences[$key]['value'];
    }
    public function trans_begin(): bool
    {
        $ok=(bool)$this->connection->trans_begin();if($ok)$this->_trans_depth++;return $ok;
    }
    public function trans_commit(): bool
    {
        $ok=(bool)$this->connection->trans_commit();if($ok)$this->_trans_depth--;return $ok;
    }
    public function trans_rollback(): bool
    {
        $ok=(bool)$this->connection->trans_rollback();if($ok)$this->_trans_depth=max(0,$this->_trans_depth-1);return $ok;
    }
    public function confirm(): void
    {
        if ($this->_trans_depth !== 0) throw new RuntimeException('issuer_outer_transaction_not_allowed');
        foreach ($this->fields as $field) {
            if (IssuerStorage::fieldExists($this->connection,$field['field'],$field['table']) !== $field['present']) throw new DomainException('native_task_index_changed');
        }
        foreach ($this->queries as $query) {
            if (IssuerStorage::query($this->connection,$query['sql'],$query['params'])->result_array() !== $query['rows']) throw new DomainException('native_task_index_changed');
        }
        foreach($this->draftReferences as$reference)if(NativeCompanyResourceReferences::resolve($this->connection,$this->prefix,$reference['issuer'],$reference['actor'],'invoice',$reference['id'],'view')!==$reference['value'])throw new DomainException('native_task_index_changed');
    }
}
final class NativeCompanyTaskScopedResult
{
    public function __construct(private array $rows) {}
    public function result_array(): array { return $this->rows; }
    public function row_array(): array { return $this->rows[0]??[]; }
    public function row() { return isset($this->rows[0]) ? (object)$this->rows[0] : null; }
    public function num_rows(): int { return count($this->rows); }
}
