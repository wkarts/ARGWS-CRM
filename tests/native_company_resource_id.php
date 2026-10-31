<?php
/** Pure transport identity tests; no authority is conferred by decoding. */
require dirname(__DIR__).'/application/services/NativeCompanyResourceId.php';
$checks=0;
$check=static function(bool $ok,string $message)use(&$checks):void{if(!$ok)throw new RuntimeException($message);++$checks;};
$sources=['company_project'=>'project','company_task'=>'task','company_standalone_task'=>'task','company_ticket'=>'ticket','company_contract'=>'contract','company_lead'=>'lead','company_expense'=>'expense','company_estimate'=>'estimate','company_proposal'=>'proposal','company_invoice_draft'=>'invoice','company_task_timer'=>'timer','company_standalone_timer'=>'timer','company_event'=>'event','company_custom_field'=>'custom_field','company_project_status'=>'project_status','company_task_status'=>'task_status','company_ticket_status'=>'ticket_status','company_ticket_department'=>'ticket_department','company_ticket_priority'=>'ticket_priority','company_ticket_service'=>'ticket_service','company_work_tag'=>'work_tag','company_todo'=>'todo','company_announcement'=>'announcement','company_reminder'=>'reminder','company_note'=>'note','company_item'=>'item','company_item_group'=>'item_group','company_tax'=>'tax'];
$sources += ['company_consent_purpose'=>'consent_purpose','company_consent'=>'consent','company_blank_task_timer'=>'timer','company_filter'=>'filter','company_notification'=>'notification','company_search_history'=>'search_history'];
$sources += ['company_kb_article'=>'kb_article','company_kb_group'=>'kb_group','company_newsfeed_post'=>'newsfeed_post','company_newsfeed_comment'=>'newsfeed_comment'];

$sources += ['company_task_checklist_template'=>'task_checklist_template'];
$seen=[];
foreach($sources as $source=>$type){
    foreach([1,2147483647,4294967295]as$id){
        $encoded=NativeCompanyResourceId::encode($source,$id);
        $decoded=NativeCompanyResourceId::decode($type,$encoded);
        $check($decoded===['source_kind'=>$source,'source_id'=>$id,'canonical_id'=>$encoded],'lossless namespace roundtrip');
        $check(!isset($seen[$encoded]),'disjoint storage IDs cannot collide');$seen[$encoded]=true;
        $check((float)$encoded<9007199254740991,'browser safe integer');
    }
}
foreach(array_unique(array_values($sources))as$type){
    foreach([1,4294967295]as$id){$check(NativeCompanyResourceId::encode($type,$id)===(string)$id,'legacy identity preserved');$check(NativeCompanyResourceId::decode($type,(string)$id)['source_kind']===$type,'legacy lineage explicit');}
}
$reject=static function(callable $work)use($check):void{try{$work();}catch(DomainException $e){$check(true,'invalid identity rejected');return;}throw new RuntimeException('invalid identity accepted');};
foreach([0,-1,'01','1.0',1.0,true,null,[],"1\n",'1e3','9007199254740992','18446744073709551615']as$bad){$reject(static fn()=>NativeCompanyResourceId::decode('task',$bad));}
$reject(static fn()=>NativeCompanyResourceId::decode('task',NativeCompanyResourceId::encode('company_project',1)));
$reject(static fn()=>NativeCompanyResourceId::decode('task','4294967296'));
$reject(static fn()=>NativeCompanyResourceId::encode('company_task','4294967296'));
$reject(static fn()=>NativeCompanyResourceId::encode('untrusted_source',1));
echo "Native resource identity: {$checks} checks passed.\n";
