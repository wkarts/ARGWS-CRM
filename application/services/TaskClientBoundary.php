<?php
namespace app\services;
defined('BASEPATH') or exit('No direct script access allowed');

/** Legacy task relations are not an authorization grant to private customer records. */
final class TaskClientBoundary
{
    private const RELATIONS = [
        'customer' => ['clients', 'userid', 'userid'],
        'project' => ['projects', 'id', 'clientid'],
        'invoice' => ['invoices', 'id', 'clientid'],
        'estimate' => ['estimates', 'id', 'clientid'],
        'contract' => ['contracts', 'id', 'client'],
        'expense' => ['expenses', 'id', 'clientid'],
        'ticket' => ['tickets', 'ticketid', 'userid'],
        'proposal' => ['proposals', 'id', 'rel_id'],
    ];

    public static function legacySql($db, string $prefix, string $alias): string
    {
        foreach ([$prefix, $alias] as $identifier) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $identifier)) throw new \InvalidArgumentException('task_scope_identifier');
        }
        $conditions = [];
        foreach (self::RELATIONS as $type => [$table, $id, $client]) {
            // Some older installations do not have every optional relation table.
            if (!$db->table_exists($prefix . $table)) continue;
            $ref = 'task_' . $type . '_boundary';
            $predicate = in_array($type, ['project','expense','ticket'], true)
                ? CustomerRelationBoundary::sql($db, $prefix, $table, $ref)
                : ClientIssuerScope::legacySql($db, $prefix, $ref, $client);
            if ($type === 'proposal') $predicate = "($ref.rel_type IS NULL OR LOWER($ref.rel_type) <> 'customer' OR ($predicate))";
            if ($type === 'invoice') $predicate .= ' AND ' . InvoiceIssuerScope::legacySql($db, $prefix . $table, $ref);
            // NOT EXISTS retains orphan/non-customer legacy tasks without exposing a private relation.
            $conditions[] = "($alias.rel_type IS NULL OR LOWER($alias.rel_type) <> '$type' OR NOT EXISTS (SELECT 1 FROM "
                . $prefix . "$table AS $ref WHERE $ref.$id=$alias.rel_id AND NOT ($predicate)))";
        }
        return $conditions ? '(' . implode(' AND ', $conditions) . ')' : '1=1';
    }

    public static function permitsRelation($db, string $prefix, array $data): bool
    {
        $type = $data['rel_type'] ?? '';
        if ($type === '' || $type === null) return true;
        if (!is_string($type)) return false;
        $canonical = strtolower($type);
        if (!isset(self::RELATIONS[$canonical])) return true; // Extension relations remain outside this adapter.
        if ($canonical !== $type) return false;
        [$table, $id, $client] = self::RELATIONS[$type];
        $value = $data['rel_id'] ?? null;
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]*$/D', (string)$value)) return false;
        if (!$db->table_exists($prefix . $table)) return false;
        $query = $db->query('SELECT * FROM ' . $prefix . $table . ' WHERE ' . $id . '=?', [$value]);
        if ($query === false || !($row = $query->row())) return false;
        if (in_array($type, ['expense', 'ticket'], true) && !CustomerRelationBoundary::permitsWrite($db, $prefix, $type === 'expense' ? 'expenses' : 'tickets', (array)$row)) return false;
        if ($type === 'invoice' && !InvoiceIssuerScope::legacy()->contains((array)$row)) return false;
        if ($type === 'proposal' && strtolower((string)$row->rel_type) !== 'customer') return true;
        // Expenses/tickets may legitimately have no customer relation.
        if (in_array($type, ['expense', 'ticket'], true) && empty($row->$client)) return true;
        return ClientIssuerScope::isLegacyClient($db, $prefix, $row->$client);
    }

    public static function legacyTimerSql($db, string $prefix, string $alias): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $alias)) throw new \InvalidArgumentException('task_scope_identifier');
        $parent = self::legacySql($db, $prefix, 'timer_task_boundary');
        return '('.$alias.'.task_id=0 OR EXISTS (SELECT 1 FROM '.$prefix.'tasks AS timer_task_boundary WHERE timer_task_boundary.id='.$alias.'.task_id AND '.$parent.'))';
    }

    /** Resolve a child through a fixed schema map; the caller still validates its parent. */
    public static function childTaskId($db, string $prefix, string $kind, $id): ?int
    {
        $map = ['comment'=>['task_comments','taskid'], 'checklist'=>['task_checklist_items','taskid'],
            'timer'=>['taskstimers','task_id'], 'attachment'=>['files','rel_id']];
        if (!isset($map[$kind]) || (!is_int($id) && !is_string($id)) || !preg_match('/^[1-9][0-9]*$/D', (string)$id)) return null;
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $prefix)) throw new \InvalidArgumentException('task_scope_identifier');
        [$table,$column] = $map[$kind];
        $result = $db->query('SELECT '.$column.' FROM '.$prefix.$table.' WHERE id=?'.($kind==='attachment' ? " AND rel_type='task'" : ''), [$id]);
        if ($result===false || !($row=$result->row())) return null;
        return (int)$row->$column;
    }

    public static function permitsTask($db, string $prefix, $id, array $changes = []): bool
    {
        if ((!is_int($id) && !is_string($id)) || !preg_match('/^[1-9][0-9]*$/D', (string)$id)) return false;
        $query = $db->query('SELECT rel_type,rel_id FROM ' . $prefix . 'tasks WHERE id=? AND ' . self::legacySql($db, $prefix, $prefix . 'tasks'), [$id]);
        if ($query === false || !($row = $query->row())) return false;
        return !$changes || self::permitsRelation($db, $prefix, array_replace((array)$row, $changes));
    }
}
