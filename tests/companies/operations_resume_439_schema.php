<?php
/** Rebuilt recovery test helper. This is new validation, not recovered historical evidence. */
function operationsResumeSchema($db, string $prefix, string $schema): void
{
    if ($db->driver() === 'mysql') {
        $schema::ensure($db, $prefix);
        $schema::ensure($db, $prefix);
    } else {
        $method = new ReflectionMethod($schema, 'definitions');
        $definitions = $method->getNumberOfRequiredParameters() ? $schema::definitions($prefix) : $schema::definitions();
        foreach ($definitions as $table => $fields) {
            if ($db->table_exists($prefix.$table)) continue;
            if (array_is_list($fields)) {
                $parts = $fields;
            } else {
                $parts = [];
                foreach ($fields as $name => $type) {
                    $nullable = str_ends_with($type, ' NULL');
                    $parts[] = '`'.$name.'` '.(str_contains($type, 'AUTO_INCREMENT')
                        ? 'INTEGER PRIMARY KEY AUTOINCREMENT'
                        : str_replace(' NULL', '', $type).($nullable ? ' NULL' : ' NOT NULL'));
                }
                foreach ($schema::indexes($table) as $name => [$columns, $nonUnique]) {
                    if ($name === 'PRIMARY' && !in_array('id', $columns, true)) $parts[] = 'PRIMARY KEY ('.implode(',', $columns).')';
                    elseif ($name !== 'PRIMARY' && !$nonUnique) $parts[] = 'UNIQUE ('.implode(',', $columns).')';
                }
            }
            $sql = 'CREATE TABLE '.$prefix.$table.' ('.implode(',', $parts).')';
            $sql = preg_replace('/ CHARACTER SET [a-z0-9_]+ COLLATE [a-z0-9_]+/', '', $sql);
            $db->query($sql);
        }
    }
    unset($db->data_cache['table_names']);
}

function operationsResumeCleanup(array $schemas): void
{
    register_shutdown_function(static function () use ($schemas): void {
        if (!isset($GLOBALS['db'], $GLOBALS['p']) || !preg_match('/^essential_[a-f0-9]{10}_$/D', $GLOBALS['p'])) return;
        $db = $GLOBALS['db']; $prefix = $GLOBALS['p'];
        foreach (array_reverse($schemas) as $schema) {
            if (!class_exists($schema, false)) continue;
            $method = new ReflectionMethod($schema, 'definitions');
            $definitions = $method->getNumberOfRequiredParameters() ? $schema::definitions($prefix) : $schema::definitions();
            foreach (array_reverse(array_keys($definitions)) as $table) $db->query('DROP TABLE IF EXISTS '.$prefix.$table);
        }
        unset($db->data_cache['table_names']);
    });
}
