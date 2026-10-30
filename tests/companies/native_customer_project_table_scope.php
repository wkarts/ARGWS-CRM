<?php
/** Actual original Projects::table request with customer lineage and issuer ACL. */
require __DIR__.'/native_projects_controller.php';
$controller->input->verb='POST';$controller->input->body=$request+['_native_company_csrf'=>'synthetic-context-proof','framework_csrf'=>'synthetic'];
CompanyRequestScope::$issuer=$a;CompanyRequestScope::$actor=7;
$controller->table((string)$ca);$wire=json_decode($controller->output->body,true,512,JSON_THROW_ON_ERROR);
$scoped=$page->read($a,7,$request,(string)$ca,'2026-10-25');
essentialCheck($wire['iTotalRecords']===$scoped['recordsTotal']&&count($wire['aaData'])===count($scoped['rows']),'original table endpoint retains owned customer scope');
foreach($scoped['rows'] as$row)essentialCheck((int)$row['clientid']===$ca&&(int)$row['issuer_id']===$a,'project rows belong to the selected issuer and customer');
$reject(fn()=>$controller->table((string)$cb),403);
CompanyRequestScope::$issuer=$b;$reject(fn()=>$controller->table((string)$ca),403);
CompanyRequestScope::$issuer=$a;unset($controller->input->body['_native_company_csrf']);$reject(fn()=>$controller->table((string)$ca),403);
echo "Original project table customer scope, A/B and CSRF passed.\n";
