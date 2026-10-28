<?php
require __DIR__.'/native_task_index_page.php';require_once APPPATH.'services/NativeCompanyRelatedTaskRows.php';
if(!function_exists('adjust_hex_brightness')){function adjust_hex_brightness($color,$n){return$color;}}
if(!function_exists('base_url')){function base_url($path=''){return'https://crm.test/'.$path;}}
if(!$db->field_exists('profile_image',$p.'staff'))$db->query('ALTER TABLE '.$p.'staff ADD COLUMN profile_image TEXT NULL');
$render=(new NativeCompanyRelatedTaskRows($db,$p))->render($a,7,$page);
essentialCheck($render['draw']===9&&$render['iTotalRecords']===$page['recordsTotal']&&count($render['aaData'])===count($page['rows']),'Original task row component renders the mixed-source DataTables page');
foreach($render['aaData'] as $n=>$row){$source=$page['rows'][$n];essentialCheck(count(array_filter(array_keys($row),'is_int'))===9&&$row['DT_RowAttr']['data-native-task-id']===$source['id'],'Each real row has original columns and exact scoped identity');essentialCheck(str_contains($row[2],htmlspecialchars($source['row']['name'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')),'Original row safely displays actual task name');}
echo 'Main task original row composition: '.$GLOBALS['essentialChecks']." cumulative checks passed; page controller/browser remain pending.\n";
