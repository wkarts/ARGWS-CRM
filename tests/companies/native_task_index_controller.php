<?php
require __DIR__.'/native_task_index_rows.php';
require_once APPPATH.'services/NativeCompanyTaskScopedDb.php';
class_alias(NativeCompanyRelatedTaskPage::class,'NativeTaskHttpTest\\NativeCompanyRelatedTaskPage');
class_alias(NativeCompanyRelatedTaskRows::class,'NativeTaskHttpTest\\NativeCompanyRelatedTaskRows');
class_alias(NativeCompanyTaskScopedDb::class,'NativeTaskHttpTest\\NativeCompanyTaskScopedDb');
\NativeTaskHttpTest\CompanyRequestScope::$issuer=$a;\NativeTaskHttpTest\CompanyRequestScope::$actor=7;
$controller->input->verb='POST';$controller->input->body=$input+['_company_context'=>'synthetic-context','_native_company_csrf'=>'synthetic-csrf'];$controller->table();
$wire=json_decode($controller->output->body,true,512,JSON_THROW_ON_ERROR);
essentialCheck($wire['iTotalRecords']===$page['recordsTotal']&&count($wire['aaData'])===count($page['rows']),'Original Tasks::table calls scoped reader and original row component');
$controller->input->verb='GET';try{$controller->table();throw new RuntimeException('GET table accepted');}catch(LogicException $e){essentialCheck($e->getMessage()==='HTTP 403','Native table rejects GET');}
$controller->input->verb='POST';unset($controller->input->body['_native_company_csrf']);try{$controller->table();throw new RuntimeException('Missing CSRF accepted');}catch(LogicException $e){essentialCheck($e->getMessage()==='HTTP 403','Native table rejects missing CSRF');}
echo 'Original task index table controller: '.$GLOBALS['essentialChecks']." cumulative checks passed; route and browser remain pending.\n";
