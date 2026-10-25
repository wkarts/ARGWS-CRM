<?php
/** Dedicated real MySQL proof of INSERT metadata lifetime; no provider/production calls. */
declare(strict_types=1);
$dsn=(string)getenv('ISSUERS_TEST_DSN');if(!preg_match('/^mysql:.*(?:^|;)dbname=(issuer_[a-zA-Z0-9_]*module_audit)(?:;|$)/D',$dsn))throw new RuntimeException('Dedicated audit schema required');
require __DIR__.'/invoices.php';require_once APPPATH.'services/CompanyProjectsStorage.php';
final class NativeMysqlAuditProbe extends CompanyProjectsStorage{public function record(int$i,int$a,int$id):void{$this->audit($i,$a,$id,'native_mysql_receipt_probe');}}
$db->query('INSERT INTO '.$p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,?,?)',[7,'native_insert_id_probe',$a,1,'2026-10-25 00:00:00']);$before=$db->insert_id();$affected=$db->query('SELECT ROW_COUNT() AS affected')->row_array()['affected'];$after=$db->insert_id();
essentialCheck($before>0&&(int)$affected===1,'real MySQL confirms INSERT ID and affected row before subsequent read');
echo 'PDO MySQL INSERT metadata: captured_id_positive=true; after_select_id_unchanged='.($before===$after?'true':'false').PHP_EOL;
$probe=new NativeMysqlAuditProbe($db,$p);$probe->record($a,7,1);$row=$db->query('SELECT * FROM '.$p.'issuers_audit ORDER BY id DESC LIMIT 1')->row_array();$metadata=json_decode($row['metadata_json'],true,8,JSON_THROW_ON_ERROR);
essentialCheck($row['event']==='native_mysql_receipt_probe'&&(int)$row['issuer_id']===$a&&preg_match('/^[a-f0-9]{32}$/D',$metadata['operation_nonce'])===1,'actual MySQL project audit proves exact new nonce and identity');
$db->query("CREATE TRIGGER {$p}audit_tamper BEFORE INSERT ON {$p}issuers_audit FOR EACH ROW SET NEW.metadata_json='{}'");try{essentialThrows(fn()=>$probe->record($a,7,1),'issuer_project_write_unconfirmed','real MySQL altered receipt remains rejected');}finally{$db->query('DROP TRIGGER '.$p.'audit_tamper');}
echo "Native project audit real MySQL receipt lifetime and tamper denial passed.\n";
