<?php
namespace {
require __DIR__.'/native_project_form_controller.php';
}
namespace NativeProjectFormHttpTest {
$assignment=new \NativeCompanyWorkAssignments($db,$p);$id=(int)$savedSource['source_id'];$before=$assignment->read($a,7,'project',$id);CompanyRequestScope::$actor=7;
$controller->input->verb='POST';$controller->input->body=['_native_company_csrf'=>'synthetic-native-csrf','native_members_revision'=>$before['revision'],'project_members'=>['7','92']];$controller->add_edit_members($saved);
$members=$assignment->read($a,7,'project',$id);essentialCheck(array_map('intval',array_column($members['members'],'staffid'))===[7,92]&&$GLOBALS['formRedirect'][2]===303,'Original member route invokes real owned atomic writer');
$controller->input->body['native_members_revision']=$members['revision'];$controller->remove_team_member($saved,92);$one=$assignment->read($a,7,'project',$id);essentialCheck(array_map('intval',array_column($one['members'],'staffid'))===[7],'Exact native removal preserves other assigned member');
try{$controller->remove_team_member($saved,7);throw new \RuntimeException('stale accepted');}catch(\LogicException$e){essentialCheck($e->getMessage()==='HTTP 422','Stale removal refused');}
$controller->input->verb='GET';try{$controller->remove_team_member($saved,7);throw new \RuntimeException('GET accepted');}catch(\LogicException$e){essentialCheck($e->getMessage()==='HTTP 422','Original URL does not authorize GET mutation');}
$controller->input->verb='POST';$controller->input->body['native_members_revision']=$one['revision'];CompanyRequestScope::$issuer=$b;try{$controller->remove_team_member($saved,7);throw new \RuntimeException('cross issuer accepted');}catch(\LogicException$e){essentialCheck($e->getMessage()==='HTTP 422','Cross-company removal denied');}CompanyRequestScope::$issuer=$a;
CompanyRequestScope::$actor=92;try{$controller->remove_team_member($saved,7);throw new \RuntimeException('create-only accepted');}catch(\LogicException$e){essentialCheck($e->getMessage()==='HTTP 422','Create-only role cannot mutate existing membership');}CompanyRequestScope::$actor=7;
essentialCheck($assignment->read($a,7,'project',$id)===$one,'Denied member actions preserve actual state and revision');
$both=$assignment->replace($a,7,'project',$id,$one['revision'],[7,92]);
$db->query('DELETE FROM '.$p.'issuers_resource_permissions WHERE issuer_id=? AND staff_id=92 AND feature=? AND capability=?',[$a,'projects','view']);
$rawBefore=$db->query('SELECT * FROM '.$p.'company_project_members WHERE issuer_id=? AND project_id=? ORDER BY staff_id',[$a,$id])->result_array();$controller->input->body['native_members_revision']=$both['revision'];
try{$controller->remove_team_member($saved,7);throw new \RuntimeException('hidden member silently dropped');}catch(\LogicException$e){essentialCheck($e->getMessage()==='HTTP 422','Removing one member cannot silently drop a different revoked/hidden member');}
essentialCheck($db->query('SELECT * FROM '.$p.'company_project_members WHERE issuer_id=? AND project_id=? ORDER BY staff_id',[$a,$id])->result_array()===$rawBefore,'Failed exact removal preserves both recorded memberships atomically');
echo 'Native project original member routes: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
}
