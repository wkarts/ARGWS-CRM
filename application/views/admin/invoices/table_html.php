<?php defined('BASEPATH') or exit('No direct script access allowed');

$table_data = array(
  _l('invoice_dt_table_heading_number'),
  _l('invoice_dt_table_heading_amount'),
  _l('invoice_total_tax'),
  array(
    'name'=>_l('invoice_estimate_year'),
    'th_attrs'=>array('class'=>'not_visible')
  ),
  _l('invoice_dt_table_heading_date'),
  array(
    'name'=>_l('invoice_dt_table_heading_client'),
    'th_attrs'=>array('class'=>(isset($client) ? 'not_visible' : ''))
  ),
  _l('project'),
  _l('tags'),
  _l('invoice_dt_table_heading_duedate'),
  _l('invoice_dt_table_heading_status'));
$nativeInvoiceView = class_exists('CompanyRequestScope', false) && CompanyRequestScope::nativeFor(get_instance());
if ($nativeInvoiceView) {
  if (!isset($native_invoice_custom_fields) || !is_array($native_invoice_custom_fields)) throw new DomainException('native_company_invoice_columns_missing');
  $custom_fields = $native_invoice_custom_fields;
} else {
  $custom_fields = get_custom_fields('invoice',array('show_on_table'=>1));
}
foreach($custom_fields as $field){
  array_push($table_data, [
   'name' => e($field['name']),
   'th_attrs' => array('data-type'=>$field['type'], 'data-custom-field'=>1)
 ]);
}
if (!$nativeInvoiceView) $table_data = hooks()->apply_filters('invoices_table_columns', $table_data);
render_datatable($table_data, (isset($class) ? $class : 'invoices'), [], ['id'=>$table_id ?? 'invoices']);
?>
