<?php
require __DIR__.'/native_task_related_page.php';require_once APPPATH.'services/NativeCompanyRelatedTaskRows.php';
if(!function_exists('adjust_hex_brightness')){function adjust_hex_brightness($color,$n){return$color;}}
if(!function_exists('base_url')){function base_url($path=''){return'https://crm.test/'.$path;}}
$cols=$db->query('PRAGMA table_info('.$p.'staff)')->result_array();if(!in_array('profile_image',array_column($cols,'name'),true))$db->query('ALTER TABLE '.$p.'staff ADD COLUMN profile_image TEXT NULL');
$render=(new NativeCompanyRelatedTaskRows($db,$p))->render($a,7,$page);$row=$render['aaData'][0];
essentialCheck($render['draw']===3&&count(array_filter(array_keys($row),'is_int'))===9,'Same original nine native related task columns rendered');
essentialCheck(str_contains($row[2],'Updated ticket-linked task')&&str_contains($row[2],'init_task_modal('.$new.')')&&str_contains($row[2],'edit_task('.$new.')'),'Same native task links and handlers with canonical IDs');
essentialCheck($row['DT_RowAttr']['data-task-revision']===(string)$page['rows'][0]['row']['revision']&&strlen($row['DT_RowAttr']['data-policy-hash'])===64,'Original row carries real revision and timer policy frame');
essentialCheck(str_contains($row[6],'tw-space-x-1')&&str_contains($row[7],'<'), 'Real native member and tag renderers reused');
echo 'Related task original row composition: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
