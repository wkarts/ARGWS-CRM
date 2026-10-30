<?php
/** Reconstructed test support after source recovery. Synthetic storage/transport only. */
declare(strict_types=1);
require __DIR__.'/native_settings.php';
require_once APPPATH.'vendor/autoload.php';

$db->query("UPDATE {$p}issuers_registry SET active=1 WHERE id IN (41,82)");
$db->query("UPDATE {$p}issuers_staff SET can_view=1 WHERE staff_id=12 AND issuer_id IN (41,82)");
foreach([41,82]as$owner)foreach(['settings'=>['view','edit','delete'],'connect-api-connector'=>['view','configure','send','reconnect'],'pusher'=>['view','configure','publish'],'projects'=>['view','create','edit'],'tasks'=>['view','create','edit']]as$feature=>$caps)foreach($caps as$cap){
    if(!$db->query("SELECT issuer_id FROM {$p}issuers_resource_permissions WHERE issuer_id=? AND staff_id=12 AND feature=? AND capability=?",[$owner,$feature,$cap])->num_rows())$db->query("INSERT INTO {$p}issuers_resource_permissions VALUES(?,12,?,?)",[$owner,$feature,$cap]);
}
$seal439=static fn(string$v):string=>'test-sealed:'.base64_encode($v);
$open439=static fn(string$v):string=>str_starts_with($v,'test-sealed:')?(string)base64_decode(substr($v,12),true):'';
function resume439Schema(string$class):void
{
    global$db,$p;
    if($db->driver()==='mysql'){$class::ensure($db,$p);$class::ensure($db,$p);}
    else{
        $m=new ReflectionMethod($class,'definitions');$definitions=$m->getNumberOfRequiredParameters()?$class::definitions($p):$class::definitions();
        foreach($definitions as$table=>$fields){
            if(array_is_list($fields))$sql=array_map(static fn($v)=>preg_replace('/ CHARACTER SET [a-z0-9_]+ COLLATE [a-z0-9_]+/','',$v),$fields);
            else{$sql=[];foreach($fields as$name=>$type)$sql[]='`'.$name.'` '.$type.' NOT NULL';$sql[]='PRIMARY KEY (`'.array_key_first($fields).'`)';}
            $db->query('CREATE TABLE IF NOT EXISTS '.$p.$table.' ('.implode(',',$sql).') ENGINE=InnoDB');
        }
    }
    unset($db->data_cache['table_names']);
}
register_shutdown_function(static function():void{
    global$db,$p;
    if(isset($db,$p)&&$db->driver()==='mysql')foreach(['company_pusher_task_notices','company_pusher_profiles','company_connect_pairing_attempts','company_connect_instance_operations','company_connect_api_messages','company_connect_api_accounts','company_mail_transport_queue_bindings','company_microsoft_mail_states','company_microsoft_mail_accounts']as$table)$db->pdo->exec('DROP TABLE IF EXISTS '.$p.$table);
    essentialCleanup();
});
