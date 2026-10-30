<?php

namespace app\services;

abstract class AbstractKanban
{
    protected $limit;

    protected $default_sort;

    protected $default_sort_direction;

    protected $status;

    protected $page = 1;

    protected $refreshAtTotal;

    protected $ci;

    protected $q;

    protected $queryTapCallback;

    private $sort_by_column;

    private $sort_direction;

    public function __construct($status)
    {
        $this->status         = $status;
        $this->ci             = &get_instance();
        $this->limit          = $this->limit();
        $this->sort_by_column = $this->defaultSortColumn();
        $this->sort_direction = $this->defaultSortDirection();
    }

    public function tapQuery(callable $callback)
    {
        $this->queryTapCallback = $callback;

        return $this;
    }

    public function totalPages()
    {
        return ceil(
            $this->countAll() / $this->limit
        );
    }

    public function get()
    {
        if ($this->refreshAtTotal && $this->refreshAtTotal !== '0') {
            // Update the current page based on the total number provided to load
            $this->page(ceil(($this->refreshAtTotal) / $this->limit()));
            $allPagesTotal = $this->page * $this->limit();

            if ($allPagesTotal > $this->refreshAtTotal) {
                $this->ci->db->limit($this->refreshAtTotal);
            } else {
                $this->ci->db->limit($allPagesTotal);
            }
        } else {
            if ($this->page > 1) {
                $position = (($this->page - 1) * $this->limit());
                $this->ci->db->limit($this->limit(), $position);
            } else {
                $this->ci->db->limit($this->limit());
            }
        }

        $this->initiateQuery();

        if ($this->q) {
            $this->applySearchQuery($this->q);
        }

        $this->applySortQuery();
        $this->tapQueryIfNeeded();

        return $this->ci->db->get()->result_array();
    }

    public function countAll()
    {
        $this->initiateQuery();

        if ($this->q) {
            $this->applySearchQuery($this->q);
        }

        $this->tapQueryIfNeeded();

        return $this->ci->db->count_all_results();
    }

    public function refresh($atTotal)
    {
        $this->refreshAtTotal = $atTotal;

        return $this;
    }

    public function page($page)
    {
        $this->page = $page;

        return $this;
    }

    public function getPage()
    {
        return $this->page;
    }

    public function sortBy($column, $direction)
    {
        if ($column && $direction) {
            $this->sort_by_column = $column;
            $this->sort_direction = $direction;
        }

        return $this;
    }

    public function search($q)
    {
        $this->q = $q;

        return $this;
    }

    protected function applySortQuery()
    {
        if ($this->sort_by_column && $this->sort_direction) {
            $nullsLast  = $this->qualifyColumn($this->sort_by_column) . ' IS NULL ' . $this->sort_direction;
            $actualSort = $this->qualifyColumn($this->sort_by_column) . ' ' . $this->sort_direction;

            $this->ci->db->order_by(
                $nullsLast . ', ' . $actualSort,
                '',
                false
            );
        }
    }

    protected function tapQueryIfNeeded()
    {
        if ($this->queryTapCallback) {
            call_user_func_array($this->queryTapCallback, [$this->status, $this->ci]);
        }
    }

    protected function qualifyColumn($column)
    {
        return db_prefix() . $this->table() . '.' . $column;
    }

    /** Validate the complete batch before a caller changes its lead record. */
    public static function permitsOrder($data, $table, $primaryKey = 'id'): bool
    {
        if (!is_array($data) || !$data || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $table) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $primaryKey)) return false;
        $ids = [];
        foreach ($data as $order) {
            if (!is_array($order) || count($order) !== 2 || !isset($order[0], $order[1])) return false;
            $id = filter_var($order[0], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
            $position = filter_var($order[1], FILTER_VALIDATE_INT, ['options'=>['min_range'=>0]]);
            if ($id === false || $position === false || isset($ids[$id])) return false;
            $ids[$id] = true;
        }
        $ci = &get_instance();
        $scope = ClientIssuerScope::legacyTableSql($ci->db, db_prefix(), db_prefix().$table);
        if ($scope === null) return true; // Other adapters retain their own authorization boundary.
        $query = $ci->db->query('SELECT '.$primaryKey.' FROM '.db_prefix().$table.' WHERE '.$primaryKey.' IN ('.implode(',',array_fill(0,count($ids),'?')).') AND '.$scope, array_keys($ids));
        return $query !== false && count($query->result_array()) === count($ids);
    }

    public static function updateOrder($data, $column, $table, $status, $statusColumnName = 'status', $primaryKey = 'id')
    {
        if (!self::permitsOrder($data, $table, $primaryKey) || !is_scalar($status)
            || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $column) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $statusColumnName)) return false;
        $ci = &get_instance();
        $ids = array_map(static fn($row)=>(int)$row[0], $data);
        $positions = array_map(static fn($row)=>(int)$row[1], $data);
        $slots = implode(',', array_fill(0,count($ids),'?'));
        $scope = ClientIssuerScope::legacyTableSql($ci->db, db_prefix(), db_prefix().$table) ?? '1=1';
        $result = $ci->db->query('UPDATE '.db_prefix().$table.' SET '.$column.'=?+'.$column.' WHERE '.$primaryKey.' NOT IN ('.$slots.') AND '.$statusColumnName.'=? AND '.$scope, array_merge([max($positions)],$ids,[$status]));
        if ($result === false) return false;
        $cases = []; $values = [];
        foreach ($data as $row) { $cases[] = 'WHEN ? THEN ?'; $values[] = (int)$row[0]; $values[] = (int)$row[1]; }
        return $ci->db->query('UPDATE '.db_prefix().$table.' SET '.$column.'=CASE '.$primaryKey.' '.implode(' ',$cases).' ELSE '.$column.' END WHERE '.$primaryKey.' IN ('.$slots.') AND '.$scope, array_merge($values,$ids)) !== false;
    }

    abstract protected function table();

    abstract protected function initiateQuery();

    abstract protected function applySearchQuery($q);

    abstract public function defaultSortDirection();

    abstract public function defaultSortColumn();

    abstract public function limit();
}
