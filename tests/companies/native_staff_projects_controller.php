<?php
/** Original Projects action + real PDO reader/formatter; only framework/context are boundary doubles. */
namespace {
require __DIR__.'/native_project_form_controller.php';
require_once APPPATH.'services/NativeCompanyStaffProjects.php';
}
namespace NativeProjectFormHttpTest {
$source=file_get_contents(APPPATH.'services/NativeCompanyStaffProjects.php');
$source=preg_replace('/^<\?php\s*declare\(strict_types=1\);\s*/','',$source);
$source=preg_replace('/^require_once .*?;\s*$/m','',$source);
eval('namespace NativeProjectFormHttpTest; use \\DomainException; use \\RuntimeException; use \\IssuerResourceAccess; use \\IssuerStorage; use \\IssuerPolicy; use \\NativeCompanyWorkStatusCatalog; use \\NativeCompanyResourceId; use \\DateTimeImmutable; use \\InvalidArgumentException; use \\CompanySettingsStorage; use \\CompanySettingsCatalog; use \\CompanySettingsFormats; use \\NativeCompanyFormatting; '.$source);
CompanyRequestScope::$issuer=$a;CompanyRequestScope::$actor=7;$c=new Projects();$c->input->verb='POST';$c->input->body=['_native_company_csrf'=>'synthetic-native-csrf','draw'=>'3','length'=>'25'];
$expected=(new \NativeCompanyStaffProjects($db,$p))->read($a,7,7,true);essentialCheck(count($expected)>0,'Fixture has actual active project membership, not an empty placeholder');
$c->staff_projects();$data=json_decode($c->output->body,true,512,JSON_THROW_ON_ERROR);
essentialCheck($data['draw']===3&&$data['iTotalRecords']===count($expected)&&count($data['aaData'])===count($expected),'Original Projects staff endpoint composes actual company membership records');
essentialCheck(count($data['aaData'][0])===4&&in_array('Cache-Control: no-store',$c->output->headers,true),'Original four-column DataTable contract and private no-store preserved');
CompanyRequestScope::$actor=92;$c->input->body['staff_id']='7';try{$c->staff_projects();throw new \RuntimeException('Unauthorized staff profile accepted');}catch(\LogicException$e){essentialCheck($e->getMessage()==='HTTP 403','Explicit staff lookup requires its own staff ACL');}unset($c->input->body['staff_id']);CompanyRequestScope::$actor=7;
$c->input->body['order']=[['column'=>'9','dir'=>'asc']];try{$c->staff_projects();throw new \RuntimeException('Invalid order accepted');}catch(\LogicException$e){essentialCheck($e->getMessage()==='HTTP 422','Invalid native column rejected');}unset($c->input->body['order']);
unset($c->input->body['_native_company_csrf']);try{$c->staff_projects();throw new \RuntimeException('CSRF absent accepted');}catch(\LogicException$e){essentialCheck($e->getMessage()==='HTTP 403','POST transport requires mutation proof before rows');}
echo 'Original staff projects controller and actual SQLite projection: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
}
