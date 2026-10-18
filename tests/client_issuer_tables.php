<?php
require __DIR__ . '/support/client_issuer_test_support.php';
function get_table_custom_fields($type) { return []; }
function total_rows($table,$where) {
    $ci = &get_instance(); $rows = $ci->db->where($where)->get($table)->result_array(); return count($rows);
}
function data_tables_init($columns,$index,$table,$join,$where,$additional) {
    $conditions = array_map(fn($w) => preg_replace('/^AND\s+/i','',$w),$where);
    $sql = 'SELECT '.$table.'.id FROM '.$table.' '.implode(' ',$join).($conditions ? ' WHERE '.implode(' AND ',$conditions) : '');
    $GLOBALS['table_ids'] = array_column(get_instance()->db->query($sql)->result_array(),'id');
    return ['output'=>[], 'rResult'=>[]];
}
class ClientTableProbe {
    public $ci;
    public function run($view,$client_id = 1) { include __DIR__ . '/../application/views/admin/tables/' . $view . '.php'; }
}
$model->load = new class { public function model($name) {} };
$model->input = new class { public function post($key) { return null; } };
$view = new ClientTableProbe; $view->ci = $model;
$view->run('all_contacts');
check($GLOBALS['table_ids'] === [10], 'all-contact table SQL excludes private rows, including admin scope');
$view->run('contacts',1);
check($GLOBALS['table_ids'] === [10], 'legacy customer contact table positive control');
$view->run('contacts',2);
check($GLOBALS['table_ids'] === [], 'private contact table returns zero rows/count source');
echo "Client issuer tables: $n checks passed (actual view predicates / SQLite)\n";
