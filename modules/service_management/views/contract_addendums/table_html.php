<?php defined('BASEPATH') or exit('No direct script access allowed');

$table_data = array(
	_l('the_number_sign'),
	_l('contract_list_subject'),
	_l('sm_contract'),
	_l('client'),
	_l('contract_list_start_date'),
);

$table_data = hooks()->apply_filters('contract_addendums_table_columns', $table_data);

render_datatable($table_data, (isset($class) ? $class : 'contracts'),[],[
	'data-last-order-identifier' => 'contracts',
	'data-default-order'         => get_table_last_order('contracts'),
]);

?>
