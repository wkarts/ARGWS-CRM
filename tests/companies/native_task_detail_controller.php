<?php
/** Official Tasks::get_task_data composes the full owned modal from native projections. */
require __DIR__.'/native_task_form_http.php';
\NativeTaskHttpTest\CompanyRequestScope::$issuer=$a;\NativeTaskHttpTest\CompanyRequestScope::$actor=7;
$controller->input->verb='GET';$controller->input->query=[];$controller->output->body='';
$controller->get_task_data($canonical);$html=$controller->output->body;
essentialCheck(str_contains($html,'data-native-task-detail="1"')&&str_contains($html,'data-native-task-id="'.$canonical.'"'),'Official task detail renders the canonical task in the original modal');
essentialCheck(str_contains($html,'name="_company_context" value="synthetic-context"')&&str_contains($html,'name="_native_company_csrf" value="synthetic-csrf"'),'Task detail binds mutations to company context and native CSRF');
essentialCheck(str_contains($html,'tasks/add_task_comment')&&str_contains($html,'id="checklist-items"')&&str_contains($html,'tasks/upload_file'),'Task detail exposes comments, checklist and project file workflows');

$controller->input->verb='POST';try{$controller->get_task_data($canonical);throw new RuntimeException('POST detail accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 422','Task detail is GET-only');}
$controller->input->verb='GET';\NativeTaskHttpTest\CompanyRequestScope::$issuer=$b;try{$controller->get_task_data($canonical);throw new RuntimeException('Foreign detail accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 403','Another company cannot open the task detail');}
\NativeTaskHttpTest\CompanyRequestScope::$issuer=$a;try{$controller->get_task_data((string)NativeCompanyResourceId::decode('task',$canonical)['source_id']);throw new RuntimeException('Numeric detail accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 403','Legacy numeric IDs cannot alias owned task detail');}
echo 'Official task detail: original modal, context/CSRF, comments/checklist/files and direct-route guards passed.'.PHP_EOL;
