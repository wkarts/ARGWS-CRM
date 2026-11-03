<?php
require __DIR__.'/../issuers_core_support.php';
require_once APPPATH.'services/issuers/IssuerRegistry.php';require_once APPPATH.'services/NativeCompanyDashboardPreferences.php';
register_shutdown_function(static function():void{foreach($GLOBALS['essentialFixtures']??[] as $f)$f->db->query('DROP TABLE IF EXISTS '.$f->prefix.'user_meta');essentialCleanup();});
$f=essentialFixture('1.0.2',1);$db=$f->db;$p=$f->prefix;IssuerCore::reconcile($db,$p);IssuerCore::bindInstallationMaster($db,$p,7);
$r=new IssuerRegistry($db,$p);$a=$r->saveIssuer(7,['legal_name'=>'Dashboard layout A','tax_id'=>'11222333000181']);$b=$r->saveIssuer(7,['legal_name'=>'Dashboard layout B','tax_id'=>'52998224725']);
$db->query("CREATE TABLE {$p}user_meta(umeta_id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,staff_id INT NOT NULL,meta_key VARCHAR(191),meta_value TEXT) ENGINE=InnoDB");
$db->query("INSERT INTO {$p}staff(staffid,active,admin) VALUES(51,1,0)");$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(?,51,1)",[$a]);
$db->query("INSERT INTO {$p}user_meta(staff_id,meta_key,meta_value) VALUES(7,'dashboard_widgets_order','historical-unread'),(7,'dashboard_widgets_visibility','historical-unread')");unset($db->data_cache['table_names']);
$pa=new NativeCompanyDashboardPreferences($db,$p,$a,7);$pb=new NativeCompanyDashboardPreferences($db,$p,$b,7);$p51=new NativeCompanyDashboardPreferences($db,$p,$a,51);
essentialCheck($pa->read()===['order'=>[],'visibility'=>[]],'new company layout does not adopt global preferences');
$pa->save('order',['left-8'=>['widget-calendar'],'right-4'=>'empty']);$pa->save('visibility',[['id'=>'todos','visible'=>'0']]);
essentialCheck($pa->read()===['order'=>['left-8'=>['widget-calendar'],'right-4'=>[]],'visibility'=>[['id'=>'todos','visible'=>0]]],'native order and visibility roundtrip');
essentialCheck($pb->read()===['order'=>[],'visibility'=>[]]&&$p51->read()===['order'=>[],'visibility'=>[]],'company B and second actor isolated');
$db->failOn='UPDATE '.$p.'user_meta';try{$pa->save('visibility',[]);throw new RuntimeException('failure injection ignored');}catch(RuntimeException $e){essentialCheck($e->getMessage()==='issuer_storage_failed','write failure surfaced');}finally{$db->failOn=null;}
essentialCheck($pa->read()['visibility']===[['id'=>'todos','visible'=>0]],'failed write preserves existing layout');
$pa->save('reset');essentialCheck($pa->read()===['order'=>[],'visibility'=>[]],'explicit reset clears only selected layout');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}user_meta WHERE meta_value='historical-unread'")->row_array()['n']===2,'legacy layout never mutated');
$db->query("UPDATE {$p}issuers_staff SET can_view=0 WHERE issuer_id=? AND staff_id=51",[$a]);essentialThrows(fn()=>$p51->save('reset'),'native_dashboard_preferences_denied','revoked member cannot mutate layout');
echo "Native company dashboard preferences: {$GLOBALS['essentialChecks']} checks passed.\n";
