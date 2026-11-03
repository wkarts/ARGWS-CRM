<?php
require __DIR__.'/native_project_form_state.php';
require_once APPPATH.'services/NativeCompanyProjectForm.php';
require_once APPPATH.'services/CompanyTaskRecurringSchema.php';
require APPPATH.'language/portuguese/portuguese_lang.php';
function _l($key,...$args){return $GLOBALS['lang'][$key]??$key;}
// Complete native reference tables, retaining the fixture's existing currency identities.
$currencyIds=array_column($db->query('SELECT id FROM '.$p.'currencies')->result_array(),'id');
foreach(['currencies','emailtemplates']as$table){
 if(!preg_match('/CREATE TABLE `tbl'.preg_quote($table,'/').'` \\(.*?\\) ENGINE=InnoDB[^;]*;/s',$nativeSeed,$match))throw new RuntimeException('Native reference schema missing');
 $sql=str_replace('`tbl'.$table.'`','`'.$p.$table.'`',$match[0]);if($table==='currencies'){foreach(explode("\n",$sql)as$line){$line=trim($line," ,\t\r\n");if(!preg_match('/^`(name|symbol|decimal_separator|thousand_separator|placement|isdefault)` /',$line,$column)||IssuerStorage::fieldExists($db,$column[1],$p.'currencies'))continue;if($db->driver()==='sqlite')$line=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$line);$db->query('ALTER TABLE '.$p.'currencies ADD '.$line);}continue;}if($db->driver()==='sqlite'){$sql=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$sql);$sql=preg_replace('/enum\\([^)]*\\)/','varchar(50)',$sql);}$db->query($sql);
}
$db->query('ALTER TABLE '.$p.'emailtemplates ADD issuer_id INT NULL');
foreach($currencyIds as$id)$db->query('UPDATE '.$p.'currencies SET name=?,symbol=?,decimal_separator=?,thousand_separator=?,placement=?,isdefault=? WHERE id=?',[$id==1?'BRL':'USD','$','.',',','before',$id==1?1:0,$id]);
if($db->driver()==='mysql')CompanyTaskRecurringSchema::ensure($db,$p);else foreach(CompanyTaskRecurringSchema::definitions($p)as$table=>$parts)if(!$db->table_exists($p.$table)){$ddl='CREATE TABLE `'.$p.$table.'` ('.implode(',',$parts).') ENGINE=InnoDB';$db->query(preg_replace('/ CHARACTER SET [a-z0-9]+ COLLATE [a-z0-9_]+/','',$ddl));}
$settings=new CompanySettingsStorage($db,$p);$settings->mutate($a,7,'add','dateformat','d/m/Y|%d/%m/%Y');$settings->mutate($a,7,'add','time_format','24');
require_once APPPATH.'services/NativeCompanyProjectDeadlineSchema.php';
if($db->driver()==='sqlite'){
    // SQLite validates consumers on the exact evolved shape; it does NOT execute/prove MySQL ALTER.
    essentialThrows(fn()=>NativeCompanyProjectDeadlineSchema::ensure($db,$p),'native_project_deadline_mysql_migration_required','SQLite never pretends to execute the production migration');
    $table=$p.'company_projects';$ddl=$db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name=?",[$table])->row_array()['sql'];
    $ddl=preg_replace('/`?due_date`? date NOT NULL/i','`due_date` date NULL',$ddl);$ddl=preg_replace('/^CREATE TABLE\s+`?'.preg_quote($table,'/').'`?/i','CREATE TABLE `'.$table.'_nullable_fixture`',$ddl);
    $foreignKeys=(int)$db->query('PRAGMA foreign_keys')->row_array()['foreign_keys'];$db->query('PRAGMA foreign_keys=OFF');$db->query($ddl);$db->query('INSERT INTO '.$table.'_nullable_fixture SELECT * FROM '.$table);$db->query('DROP TABLE '.$table);$db->query('ALTER TABLE '.$table.'_nullable_fixture RENAME TO '.$table);$db->query('PRAGMA foreign_keys='.$foreignKeys);
    // Existing test harness omits composite indexes; this SQLite shape test does not claim production FK migration coverage.
}else NativeCompanyProjectDeadlineSchema::ensure($db,$p);
NativeCompanyProjectDeadlineSchema::assertValid($db,$p);
$adapter=new NativeCompanyProjectForm($db,$p);
$dto=$adapter->read($a,7,NativeCompanyResourceId::encode('company_project',$withState));
essentialCheck($dto['project']->name===$updateForm['name']&&$dto['native_project_form']['deadline']==='31/12/2026','Original project form receives actual owned source and native localized dates');
essentialCheck(count($dto['project_members'])===2&&$dto['native_project_form']['tags']==='Native form changed tag','Original form receives existing members and tags, not empty substitutes');
essentialThrows(fn()=>$adapter->read($b,7,NativeCompanyResourceId::encode('company_project',$withState)),'native_resource_reference_denied','Native editor refuses foreign company source');
$new=$adapter->read($a,92,null,$client,'2026-10-25');essentialCheck($new['native_project_form']['start_date']==='25/10/2026'&&!isset($new['project']),'Create-only uses native new form without manufacturing a saved project');
echo 'Native project original form adapter: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
$wire=['native_request_key'=>$tk('actual-native-form-create'),'name'=>'Original form submitted project','clientid'=>(string)$client,'description'=>'Native description','start_date'=>'25/10/2026','deadline'=>'','status'=>NativeCompanyWorkStatusCatalog::id('project','in_progress'),'billing_type'=>'1','project_cost'=>'123.45','project_rate_per_hour'=>'','estimated_hours'=>'','progress_from_tasks'=>'on','progress'=>'0','project_members'=>['92'],'tags'=>'Native actual form tag','custom_fields'=>['projects'=>[$fieldId=>'Native form required value']],'settings'=>['available_features'=>['project_overview']],'contact_notification'=>'0'];
$saved=$adapter->save($a,92,null,$wire);$savedSource=NativeCompanyResourceReferences::resolve($db,$p,$a,7,'project',$saved);$loaded=$adapter->read($a,7,$saved);essentialCheck($loaded['project']->settings->available_features['project_files']===0&&$loaded['project']->settings->available_features['project_overview']===1,'Disabled feature is explicit zero for original form, never missing-means-enabled');
essentialCheck($loaded['project']->name===$wire['name']&&$loaded['project']->deadline===null&&$loaded['native_project_form']['tags']===$wire['tags'],'Actual native HTML payload creates persisted nullable-deadline project with tags');
essentialCheck($adapter->save($a,92,null,$wire)===$saved,'Original native request key replays without creating another project');
$edit=$wire;unset($edit['progress_from_tasks']);$edit['progress']='37';$edit['name']='Original form updated project';$edit['native_request_key']=$tk('actual-native-form-update');$edit['native_project_revision']=$loaded['native_project_form']['source']['revision'];$edit['native_metadata_revision']=$loaded['native_project_form']['related']['metadata']['revision'];$edit['native_members_revision']=$loaded['native_project_form']['related']['members']['revision'];$edit['native_tags_revision']=$loaded['native_project_form']['related']['tags']['revision'];$edit['native_form_state_revision']=$loaded['native_project_form']['related']['state']['revision'];
$adapter->save($a,7,$saved,$edit);$updatedDto=$adapter->read($a,7,$saved);essentialCheck($updatedDto['project']->name===$edit['name']&&$updatedDto['project']->progress===37,'Actual native update payload preserves source identity and applies explicit manual progress');
$stale=$edit;$stale['native_request_key']=$tk('actual-native-form-stale');essentialThrows(fn()=>$adapter->save($a,7,$saved,$stale),'company_project_stale','Stale native editor cannot overwrite subsequent project revision');
$invalid=$wire;$invalid['native_request_key']=$tk('invalid-form-date');$invalid['start_date']='31/02/2026';essentialThrows(fn()=>$adapter->save($a,92,null,$invalid),'native_project_date_invalid','Localized invalid date is rejected rather than normalized');
$invalid=$wire;$invalid['native_request_key']=$tk('uncomposed-action');$invalid['mark_all_tasks_as_completed']='on';essentialThrows(fn()=>$adapter->save($a,92,null,$invalid),'native_project_action_not_composed','Unimplemented native side effect is explicit, never silently ignored');
essentialThrows(fn()=>$adapter->save($a,92,$saved,$edit),'native_project_access_denied','Create-only user cannot invoke native update');
echo 'Native project actual form POST composition: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
