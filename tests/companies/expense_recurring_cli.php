<?php
// Registered before the baseline fixture cleanup: child tables must be dropped first.
register_shutdown_function(static function():void{if(!isset($GLOBALS['db'],$GLOBALS['p'])||!preg_match('/^essential_[a-f0-9]{10}_$/D',$GLOBALS['p']))return;foreach(['company_invoice_schedule_discovery','company_invoice_schedule_drafts','company_invoice_schedule_modes','company_invoice_draft_events','company_invoice_drafts','company_invoice_schedule_requests','company_invoice_schedule_runs','company_invoice_schedules']as$table)$GLOBALS['db']->query('DROP TABLE IF EXISTS '.$GLOBALS['p'].$table);});
require __DIR__.'/expense_recurring.php';
require_once APPPATH.'services/CompanyRecurringWorkerScope.php';require_once APPPATH.'services/CompanySalesSchema.php';
if($db->driver()==='mysql')CompanySalesSchema::ensure($db,$p);else foreach(CompanySalesSchema::definitions() as $table=>$fields){$cols=[];foreach($fields as $field=>$type)$cols[]=$field.' '.(str_contains($type,'AUTO_INCREMENT')?'INTEGER PRIMARY KEY AUTOINCREMENT':$type.' NOT NULL');foreach(CompanySalesSchema::indexes($table) as $key=>[$columns,$nonUnique])if($key==='PRIMARY'&&!isset($fields['id']))$cols[]='PRIMARY KEY('.implode(',',$columns).')';elseif($key!=='PRIMARY'&&!$nonUnique)$cols[]='UNIQUE('.implode(',',$columns).')';$db->query('CREATE TABLE '.$p.$table.'('.implode(',',$cols).')');}unset($db->data_cache['table_names']);
/** The real shared worker also enters the independent native invoice namespace (438+).
 * Install its native-schema prerequisites explicitly and keep every schedule empty.
 * These are isolated service fixtures, not proof of the native migration executor.
 */
function expenseCliInvoiceSchema($db,string $p,string $class):array
{
    require_once APPPATH.'services/'.$class.'.php';
    $partsStyle=$class==='CompanyInvoiceDraftsSchema';$definitions=$partsStyle?$class::definitions($p):$class::definitions();
    if($db->driver()==='mysql'){$class::ensure($db,$p);$class::ensure($db,$p);}
    else foreach($definitions as$table=>$fields){
        $columns=[];
        if($partsStyle)foreach($fields as$part){if(str_starts_with($part,'KEY '))continue;$part=preg_replace('/ CHARACTER SET [a-z0-9_]+ COLLATE [a-z0-9_]+/','',$part);$part=preg_replace('/^UNIQUE KEY `[^`]+` /','UNIQUE ',$part);$columns[]=str_replace('int NOT NULL AUTO_INCREMENT PRIMARY KEY','INTEGER PRIMARY KEY AUTOINCREMENT',$part);}
        else{
            $auto=false;foreach($fields as$field=>$type){$nullable=str_ends_with($type,' NULL');if(str_contains($type,'AUTO_INCREMENT')){$columns[]=$field.' INTEGER PRIMARY KEY AUTOINCREMENT';$auto=true;}else$columns[]=$field.' '.str_replace([' unsigned',' NULL'],'',$type).($nullable?' NULL':' NOT NULL');}
            foreach($class::indexes($table)as$key=>[$names,$nonUnique])if($key==='PRIMARY'&&!$auto)$columns[]='PRIMARY KEY('.implode(',',$names).')';elseif($key!=='PRIMARY'&&!$nonUnique)$columns[]='UNIQUE('.implode(',',$names).')';
        }
        $db->query('CREATE TABLE '.$p.$table.'('.implode(',',$columns).')');
    }
    unset($db->data_cache['table_names']);$class::assertValid($db,$p);return array_keys($definitions);
}
$nativeCliSchemas=['CompanyInvoiceRecurringSchema'];
// 440 adds immutable modes/private-draft links and durable discovery; 439 has only438 tables.
if(is_file(APPPATH.'services/CompanyInvoiceDraftsSchema.php'))$nativeCliSchemas=array_merge($nativeCliSchemas,['CompanyInvoiceDraftsSchema','CompanyInvoiceRecurringModesSchema','CompanyInvoiceRecurringDraftsSchema','CompanyInvoiceRecurringDiscoverySchema']);
$nativeCliTables=[];foreach($nativeCliSchemas as$class)$nativeCliTables=array_merge($nativeCliTables,expenseCliInvoiceSchema($db,$p,$class));
require __DIR__.'/recurring_cli_prerequisites.php';
foreach($nativeCliTables as$table)essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.$table)->row()->n===0,'shared CLI invoice prerequisite starts empty '.$table);
$cliSchedules=[];$yesterday=gmdate('Y-m-d',time()-86400);foreach([$a=>[$categoryA,$ca,$projectA],$b=>[$categoryB,$cb,$projectB]] as $issuer=>[$category,$client,$project]){$source=$ex->create($issuer,7,array_replace($expenseInput,['category_id'=>$category,'client_id'=>$client,'project_id'=>$project,'date'=>$yesterday]),$rk('cli-source-'.$issuer));$row=$ex->get($issuer,7,$source);$cliSchedules[$issuer]=$recExpenses->create($issuer,7,$source,$row['revision_token'],array_replace($scheduleSpec,['interval_unit'=>'day','interval_value'=>1,'custom'=>true,'max_cycles'=>1]),$rk('cli-schedule-'.$issuer));}
$router=new class{function fetch_directory(){return '';}function fetch_module(){return false;}function fetch_class(){return 'company_recurring_worker';}function fetch_method(){return 'index';}};
$cliInput=new class{public bool $cli=false;function is_cli_request(){return $this->cli;}};
$cliCi=(object)['db'=>$db,'input'=>$cliInput,'router'=>$router,'load'=>new class{function database(){}function library($name){throw new RuntimeException('Cost-only CLI must not load mail encryption');}}];
class CI_Controller{function __get($name){return $GLOBALS['expenseRecurringCliCi']->$name;}}
function show_404(){$GLOBALS['expenseRecurring404']=true;}
$GLOBALS['expenseRecurringCliCi']=$cliCi;require APPPATH.'controllers/Company_recurring_worker.php';$cliController=new Company_recurring_worker();
$beforeCli=$db->query('SELECT * FROM '.$p.'company_expenses ORDER BY id')->result_array();CompanyRecurringWorkerScope::prepare($cliCi);ob_start();$cliController->index();$deniedOutput=ob_get_clean();essentialCheck(!empty($GLOBALS['expenseRecurring404'])&&$deniedOutput===''&&$db->query('SELECT * FROM '.$p.'company_expenses ORDER BY id')->result_array()===$beforeCli,'actual CLI controller rejects HTTP before cost discovery or encryption');
$beforeNativeCliInvoices=$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array();$cliInput->cli=true;essentialCheck(CompanyRecurringWorkerScope::prepare($cliCi),'exact existing worker capability enables both independent financial namespaces');$_SESSION=['active_issuer_id'=>$b,'staff_user_id'=>999];ob_start();$cliController->index();$cliOutput=ob_get_clean();foreach($cliSchedules as $issuer=>$id){$doc=$recExpenses->document($issuer,7,$id);essentialCheck($doc['state']==='completed'&&(int)$doc['completed_cycles']===1&&count($doc['occurrences'])===1,'actual unscoped CLI discovers durable issuer '.$issuer.' and captured actor independently of ambient session');}essentialCheck(str_contains($cliOutput,'Recurrences completed: 0; blocked: 0')&&str_contains($cliOutput,'Expense recurrences completed: 2; blocked: 0'),'actual CLI reports separate invoice and expense counters with no transport use');$beforeCli=$db->query('SELECT * FROM '.$p.'company_expenses ORDER BY id')->result_array();ob_start();$cliController->index();ob_end_clean();essentialCheck($db->query('SELECT * FROM '.$p.'company_expenses ORDER BY id')->result_array()===$beforeCli,'actual CLI retry records no second cost at completed boundary');unset($_SESSION);
essentialCheck(str_contains($cliOutput,'Native invoice schedules completed: 0; blocked: 0'),'shared CLI reports explicit empty native invoice namespace without transport');foreach($nativeCliTables as$table)essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.$table)->row()->n===0,'cost-only CLI leaves native invoice scheduling table empty '.$table);essentialCheck($db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array()===$beforeNativeCliInvoices,'cost-only CLI with native invoice prerequisites preserves every invoice byte');
essentialCheck(str_contains($cliOutput,'Task schedules examined: 0; completed: 0; waiting: 0; blocked: 0'),'shared CLI enters the real empty task processor without treating an unavailable schema as success');
essentialCheck(str_contains($cliOutput,'Lead internal notices scanned: 0; delivered: 0; suppressed: 0; unchanged: 0; failed and pending: 0'),'shared CLI enters the real empty internal Leads inbox processor without transport');
foreach($sharedCliBusinessTables as$table)essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.$table)->row()->n===0,'expense-only CLI preserves empty task/lead business namespace '.$table);
$taskCursor=$db->query('SELECT * FROM '.$p.'company_task_schedule_cursor')->row_array();
essentialCheck((int)$taskCursor['id']===1&&(int)$taskCursor['last_id']===0&&(int)$taskCursor['upper_id']===0&&(int)$taskCursor['revision']===3,'two real task discovery passes advance only the technical cursor revision');
$leadCursor=$db->query('SELECT * FROM '.$p.'company_lead_notice_cursor')->result_array();
essentialCheck(count($leadCursor)===1&&(int)$leadCursor[0]['id']===1&&(int)$leadCursor[0]['last_id']===0,'empty internal notice discovery leaves its technical cursor at zero');
echo 'Expense recurrence actual CLI/capability/session isolation: '.$GLOBALS['essentialChecks'].' cumulative checks passed ('.$db->driver().'). No external delivery.'.PHP_EOL;
