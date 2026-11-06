<?php
/** Canonical credit issuance and actual SQL prove a project link is not a customer link. */
require __DIR__.'/credit_metadata.php';
require_once APPPATH.'services/NativeCompanyProjectOriginalHttp.php';
$start=$GLOBALS['essentialChecks'];

$db->query('INSERT INTO '.$p.'company_projects(issuer_id,client_id,responsible_id,name,description,start_date,due_date,status,revision,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,1,?,?)',
    [$a,$ca,7,'Second project for the same client','','2026-10-19','2026-11-19','active','2026-10-19 00:00:00','2026-10-19 00:00:00']);
$otherProject=(int)$db->insert_id();
$different=$request;
$different['native_metadata']['project']=['source_kind'=>'company_project','source_id'=>$otherProject,'source_revision'=>1];
$different['native_metadata']['reference_no']='OTHER-PROJECT';
$otherCredit=$writer->issueCompanyCredit($a,7,$different,hash('sha256','project-credit-other-project'));
$unbound=$request;$unbound['native_metadata']['project']=null;
$unboundCredit=$writer->issueCompanyCredit($a,7,$unbound,hash('sha256','project-credit-unbound'));

$page=new class($db){
    use NativeCompanyProjectOriginalHttp;
    public function __construct(public $db){}
    public function section(int $issuer,int $actor,int $project):array{return $this->nativeProjectRelationSection($issuer,$actor,$project,'project_credit_notes');}
};
$before=$counts();
$section=$page->section($a,7,$projects[$a]);
$expected=[$new,$own,$boundedId];rsort($expected,SORT_NUMERIC);
essentialCheck(array_column($section['rows'],'id')===$expected,'project section includes only the exact typed captured project, excluding same-client historical/unbound/other-project credits');
essentialCheck($section['columns']===['id','number','date','total','status'],'project section has explicit credit columns, not the storage receipt columns');
essentialCheck(str_starts_with($section['rows'][0]['total'],$reader->get($a,7,$own)['currency'].' '),'project total retains its actual currency');
essentialCheck(!str_contains(json_encode($section,JSON_THROW_ON_ERROR),'ADMIN-')&&!str_contains(json_encode($section,JSON_THROW_ON_ERROR),'INTERNAL-'),'minimal project projection does not expose private note or custom-field metadata');
essentialCheck(array_column($page->section($a,7,$otherProject)['rows'],'id')===[$otherCredit],'a second project for the same customer receives only its own credit');
essentialCheck($page->section($b,7,$projects[$b])['rows']===[],'another authorized company never inherits first-company project credits');
essentialThrows(fn()=>$page->section($b,7,$projects[$a]),'company_project_access_denied','foreign project ID is denied even when the actor can view both companies');

essentialCheck(array_column($page->section($a,17,$projects[$a])['rows'],'id')===$expected,'ordinary actor with both current capabilities can read the exact financial relation');
$db->query('DELETE FROM '.$p.'issuers_resource_permissions WHERE issuer_id=? AND staff_id=? AND feature=? AND capability=?',[$a,17,'projects','view']);
essentialThrows(fn()=>$page->section($a,17,$projects[$a]),'company_project_access_denied','credit permission alone cannot read a project relationship');
$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,?,?,?)',[$a,17,'projects','view']);
$db->query('DELETE FROM '.$p.'issuers_resource_permissions WHERE issuer_id=? AND staff_id=? AND feature=? AND capability=?',[$a,17,'credit_notes','view']);
essentialThrows(fn()=>$page->section($a,17,$projects[$a]),'company_credit_access_denied','project permission alone cannot read credit documents');
essentialCheck($counts()===$before,'relationship reads and denied requests preserve financial rows, receipts and audit');
echo 'Project exact credit relation: '.($GLOBALS['essentialChecks']-$start).' additional checks passed ('.$db->driver().').'.PHP_EOL;
