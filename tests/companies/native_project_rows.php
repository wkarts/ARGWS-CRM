<?php
require __DIR__.'/native_project_page.php';
define('FCPATH',dirname(__DIR__,2).'/');
require APPPATH.'helpers/func_helper.php';
require APPPATH.'language/portuguese/portuguese_lang.php';
function _l($key){return$GLOBALS['lang'][$key]??$key;}
function e($value){return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function base_url($path=''){return'https://crm.invalid/subpath/'.$path;}
function admin_url($path=''){return base_url('admin/'.$path);}
require_once APPPATH.'services/NativeCompanyProjectTableFactory.php';
require_once APPPATH.'services/NativeCompanyProjectRows.php';
$db->query("ALTER TABLE {$p}staff ADD profile_image VARCHAR(191) NULL");
$settings=new CompanySettingsStorage($db,$p);$settings->mutate($a,7,'add','dateformat','d/m/Y|%d/%m/%Y');
$factory=new NativeCompanyProjectTableFactory($db,$p);$catalog=$factory->data($a,7);$table=$factory->make($a,7);
essentialCheck($table->id()==='projects'&&$table->viewName()==='projects'&&count($table->rules())===6,'Original app-filters rule identities preserved');
essentialCheck($table->contextIdentity()===['issuer_id'=>$a,'actor_id'=>7]&&count($catalog['summary']['statuses'])===6,'Actual scoped table factory authority and status catalogue');
$result=$page->read($a,7,$request,'','2026-10-25');$rendered=(new NativeCompanyProjectRows($db,$p))->render($a,7,$result);
essentialCheck($rendered['iTotalRecords']===$result['recordsTotal']&&count($rendered['aaData'])===count($result['rows']),'Original DataTables response contract');
foreach($rendered['aaData']as$i=>$row){essentialCheck(str_contains($row[0],'/subpath/admin/projects/view/'.$result['rows'][$i]['id'])&&$row['DT_RowClass']==='has-row-options','Same native row links and styling with canonical ID');essentialCheck(str_contains($row[1],'copy_project(')&&str_contains($row[1],'/projects/delete/'),'Native actions are retained, not hidden by alternate markup');}
$before=$db->queries;try{$table->findRule('tenant_sql');throw new RuntimeException('Unknown filter accepted');}catch(DomainException$e){essentialCheck(true,'No caller-selected SQL/filter identifier');}
echo 'Native project table factory and real row renderer: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
require_once APPPATH.'services/NativeCompanyFilters.php';NativeCompanyFilterSchema::ensure($db,$p);
foreach(['filters'=>'id INT PRIMARY KEY,staff_id INT,identifier VARCHAR(64),view VARCHAR(64),name VARCHAR(191),is_shared INT,builder TEXT','filter_defaults'=>'staff_id INT,filter_id INT,identifier VARCHAR(64),view VARCHAR(64)']as$t=>$ddl)if(!$db->table_exists($p.$t))$db->query("CREATE TABLE {$p}{$t}($ddl)");unset($db->data_cache['table_names']);
$filters=new NativeCompanyFilters($db,$p,$a,7,$table);$saved=$filters->save(['name'=>'Native project progress','is_shared'=>0,'builder'=>['match_type'=>'and','rules'=>[['id'=>'status','type'=>'MultiSelectRule','operator'=>'in','value'=>[NativeCompanyWorkStatusCatalog::id('project','in_progress')]]]]]);
$list=$filters->listing();$withSaved=$factory->make($a,7,'projects',$list);
essentialCheck(count($list)===1&&$withSaved->filtersJs() instanceof \app\services\utilities\Js,'Projects native app-filters uses actual issuer-scoped persistence');
echo 'Native project saved-filter composition: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
