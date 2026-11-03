<?php
require __DIR__.'/companies/native_task_relation_option.php';
require APPPATH.'services/NativeCompanyRelationEndpoints.php';
class CompanyRequestScope {public static int$i;public static bool$allowed=true;static function assertNativeMutation($ci){if(!self::$allowed)throw new DomainException('mutation_denied');}static function context(){return new class{function issuerId(){return CompanyRequestScope::$i;}function staffId(){return 51;}};}}
$db->query("UPDATE {$p}issuers_staff SET can_view=1 WHERE issuer_id=? AND staff_id=51",[$a]);$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,51,'projects','view')",[$a]);CompanyRequestScope::$i=$a;
$ci=(object)['db'=>$db,'input'=>new class {public array$body=[];function post($key){return$this->body[$key]??null;}}];$ci->input->body=['type'=>'tasks','rel_id'=>$owned];$option=NativeCompanyRelationEndpoints::read($ci);essentialCheck($option===[['id'=>$owned,'name'=>'Project A','subtext'=>'']],'Actual endpoint and real task reader preserve selected project task');
$ci->input->body['rel_id']=$standalone;essentialCheck(NativeCompanyRelationEndpoints::read($ci)[0]['id']===$standalone,'Actual endpoint preserves standalone namespace');
$ci->input->body['rel_id']='';essentialThrows(fn()=>NativeCompanyRelationEndpoints::read($ci),'native_relation_selection_required','No selected task never yields fake empty catalogue');
$ci->input->body['rel_id']=$owned;CompanyRequestScope::$i=$b;essentialThrows(fn()=>NativeCompanyRelationEndpoints::read($ci),'native_resource_reference_denied','Endpoint denies cross-company selection');CompanyRequestScope::$i=$a;
CompanyRequestScope::$allowed=false;essentialThrows(fn()=>NativeCompanyRelationEndpoints::read($ci),'mutation_denied','HTTP context/CSRF boundary rejects before reader');
echo 'Actual selected task endpoint composition: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
