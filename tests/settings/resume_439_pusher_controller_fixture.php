<?php
/** Reconstructed fixture for the byte-recovered controller regression; all writes are synthetic setup. */
register_shutdown_function(static function():void{global$db,$p;if(isset($db,$p)&&$db->driver()==='mysql')foreach(['company_pusher_task_notices','company_pusher_profiles']as$table)$db->pdo->exec('DROP TABLE IF EXISTS '.$p.$table);});
require dirname(__DIR__).'/companies/invoice_delivery.php';
require_once APPPATH.'services/CompanyPusherTaskNotifications.php';
require_once APPPATH.'services/CompanyProjectExtrasSchema.php';require_once APPPATH.'services/CompanyStandaloneTasksSchema.php';
foreach(['firstname','lastname']as$field)if(!$db->field_exists($field,$p.'staff'))$db->query('ALTER TABLE '.$p.'staff ADD '.$field.' varchar(80) NOT NULL DEFAULT \'Synthetic\'');
foreach([CompanyProjectsSchema::class,CompanyProjectExtrasSchema::class,CompanyStandaloneTasksSchema::class,CompanyPusherSchema::class]as$class){
    if($db->driver()==='mysql')$class::ensure($db,$p);
    else foreach($class::definitions($p)as$table=>$fields){$fields=array_map(fn($v)=>preg_replace('/ CHARACTER SET [a-z0-9_]+ COLLATE [a-z0-9_]+/','',$v),$fields);$db->query('CREATE TABLE '.$p.$table.'('.implode(',',$fields).') ENGINE=InnoDB');}
}
unset($db->data_cache['table_names']);
if(!$db->query('SELECT issuer_id FROM '.$p.'issuers_staff WHERE issuer_id=? AND staff_id=12',[$b])->num_rows())$db->query('INSERT INTO '.$p.'issuers_staff(issuer_id,staff_id,can_view) VALUES(?,12,1)',[$b]);
foreach([['settings','view'],['settings','edit'],['pusher','configure']]as[$feature,$cap])$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,?,?)',[$b,$feature,$cap]);
$db->query("INSERT INTO {$p}company_projects(id,issuer_id,client_id,responsible_id,name,description,status,start_date,due_date,revision,created_at,updated_at) VALUES(401,?,?,7,'Synthetic recovery project','Private project','active','2026-01-01','2026-12-31',1,'2026-01-01 00:00:00','2026-01-01 00:00:00')",[$b,$cb]);
$db->query("INSERT INTO {$p}company_tasks(id,issuer_id,project_id,assignee_id,name,description,status,due_date,checklist_json,revision,created_at,updated_at) VALUES(402,?,401,7,'Synthetic recovery task','Private task','open','2026-11-01','[]',1,'2026-01-01 00:00:00','2026-01-01 00:00:00')",[$b]);
$tb=402;$ts=new CompanyTasks($db,$p);$profile=new CompanyPusherProfile($db,$p);$encrypt=fn($v)=>'sealed:'.base64_encode($v);$decrypt=fn($v)=>str_starts_with($v,'sealed:')?base64_decode(substr($v,7),true):false;$calls=[];
$http=new GuzzleHttp\Client(['handler'=>function($request)use(&$calls){$calls[]=$request;throw new RuntimeException('synthetic provider must remain untouched');}]);$notices=new CompanyPusherTaskNotifications($db,$p,new CompanyPusherTransport($http));
foreach([$a,$b]as$owner)$profile->save($owner,7,0,(string)(800000+$owner),'synthetic_public_key_'.$owner,'eu','synthetic-secret-'.$owner,$encrypt);
$globalBefore=$db->query('SELECT * FROM '.$p.'options ORDER BY name')->result_array();
