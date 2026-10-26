<?php
/** SQLite exercises real writes/rollback; information_schema is an explicit metadata double. */
declare(strict_types=1);
require __DIR__.'/ticket_preferences.php';$db->query('UPDATE tblissuers_installation_identity SET master_staff_id=17');$db->query('UPDATE tblissuers_staff SET can_view=1 WHERE issuer_id=2 AND staff_id=17');
require_once __DIR__.'/../../application/services/NativeCompanyTicketCommands.php';$actual=$db;require __DIR__.'/ticket_catalog_schema.php';$db=$actual;
function ticketSqliteDdl(string $table,array $parts,$db):void{
    $columns=[];foreach($parts as$part)if(str_starts_with($part,'`')){
        $part=preg_replace('/ CHARACTER SET [a-z0-9_]+ COLLATE [a-z0-9_]+/','',$part);$part=str_replace('int unsigned','int',$part);
        $part=str_replace('int NOT NULL AUTO_INCREMENT PRIMARY KEY','INTEGER PRIMARY KEY AUTOINCREMENT',$part);$columns[]=$part;
    }$db->query('CREATE TABLE tbl'.$table.' ('.implode(',',$columns).')');
}
ticketSqliteDdl(NativeCompanyTicketReplySchema::BODY,NativeCompanyTicketReplySchema::definitions('tbl')[NativeCompanyTicketReplySchema::BODY],$db);
foreach(CompanyContractsSchema::definitions('tbl')as$table=>$parts)ticketSqliteDdl($table,$parts,$db);
// SQLite cannot ALTER nullability; fixture starts with the exact target shape. MySQL script verifies actual evolution.
$db->query('DROP TABLE tblcompany_tickets');$parts=CompanyContractsSchema::definitions('tbl')['company_tickets'];$parts=array_map(fn($p)=>$p==='`client_id` int NOT NULL'?'`client_id` int NULL':$p,$parts);ticketSqliteDdl('company_tickets',$parts,$db);
foreach(NativeCompanyDepartmentSchema::definitions('tbl')as$table=>$parts)ticketSqliteDdl($table,$parts,$db);
foreach(['clients'=>'userid INTEGER PRIMARY KEY,active INTEGER','contacts'=>'id INTEGER PRIMARY KEY,userid INTEGER,active INTEGER,firstname TEXT,lastname TEXT,email TEXT,ticket_emails INTEGER NOT NULL DEFAULT 1','issuers_clients'=>'client_id INTEGER PRIMARY KEY,owner_issuer_id INTEGER','company_customer_admins'=>'issuer_id INTEGER,client_id INTEGER,staff_id INTEGER','customer_admins'=>'customer_id INTEGER,staff_id INTEGER']as$table=>$ddl)$db->query('CREATE TABLE tbl'.$table.'('.$ddl.')');
$db->query('ALTER TABLE tblstaff ADD COLUMN is_not_staff INTEGER NOT NULL DEFAULT 0');
$db->query('INSERT INTO tblclients VALUES(2,1),(3,1)');$db->query('INSERT INTO tblissuers_clients VALUES(2,2),(3,3)');$db->query("INSERT INTO tblcontacts(id,userid,active,firstname,lastname,email) VALUES(2,2,1,'Own','Contact','a@example.invalid'),(3,3,1,'Private','B','b@example.invalid')");
foreach([[1,2,17,'A support'],[2,3,18,'B support']]as[$id,$issuer,$actor,$name])$db->query("INSERT INTO tblcompany_departments(id,issuer_id,name,imap_username,email,host,encryption,folder,password_cipher,calendar_id,email_from_header,delete_after_import,hidefromclient,revision,actor_id,created_at,updated_at) VALUES(?,?,?,'','','','','INBOX','','',0,0,0,1,?,?,?)",[$id,$issuer,$name,$actor,'2026-01-01 00:00:00','2026-01-01 00:00:00']);
final class TicketCreateDb{
 public PDO$pdo;public bool$db_debug=true,$save_queries=true;private array$metadata=[];
 function __construct(private$db,array $additional=[]){$this->pdo=$db->pdo;$m=new ReflectionMethod(TicketCatalogSchemaDb::class,'metadata');foreach(array_merge(NativeCompanyDepartmentSchema::definitions('tbl'),NativeCompanyTicketCatalogSchema::definitions('tbl'),['company_tickets'=>CompanyContractsSchema::definitions('tbl')['company_tickets']],$additional)as$name=>$parts)$this->metadata['tbl'.$name]=$m->invoke(null,$parts);
 $t=&$this->metadata['tblcompany_tickets'];foreach($t['columns']as&$c)if($c['COLUMN_NAME']==='client_id')$c['IS_NULLABLE']='YES';$t['indexes'][]=['INDEX_NAME'=>'client_id','COLUMN_NAME'=>'client_id','NON_UNIQUE'=>1,'SUB_PART'=>null];foreach(['issuer_id','id','client_id']as$column)$t['indexes'][]=['INDEX_NAME'=>'portal_support_client','COLUMN_NAME'=>$column,'NON_UNIQUE'=>0,'SUB_PART'=>null];}
 function query($sql,$p=[]){foreach(['COLUMNS'=>'columns','STATISTICS'=>'indexes','KEY_COLUMN_USAGE'=>'foreign','REFERENTIAL_CONSTRAINTS'=>'rules','TABLES'=>'engine']as$n=>$k)if(str_contains($sql,'information_schema.'.$n))return new TicketCatalogSchemaRows($this->metadata[$p[0]][$k]??[]);return$this->db->query($sql,$p);}function table_exists($t){return$this->db->table_exists($t);}function field_exists($f,$t){return$this->db->field_exists($f,$t);}function insert_id(){return$this->db->insert_id();}function trans_begin(){return$this->db->trans_begin();}function trans_commit(){return$this->db->trans_commit();}function trans_rollback(){return$this->db->trans_rollback();}function trans_status(){return true;}
}
// Real SQLite schema for shared decoration writers (not a MySQL metadata claim).
NativeCompanyCustomFieldSchema::ensure($db,'tbl');
$db->query('ALTER TABLE tblissuers_audit ADD COLUMN metadata_json TEXT NULL');
foreach(NativeCompanyWorkTagsSchema::definitions('tbl')as$table=>$parts){$parts=array_map(static function($part){$part=preg_replace('/`id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY/','`id` INTEGER PRIMARY KEY AUTOINCREMENT',$part);return preg_replace('/UNIQUE KEY `[^`]+`/','UNIQUE',$part);},$parts);$db->query('CREATE TABLE tbl'.$table.' ('.implode(',',$parts).')');}
$wrapped=new TicketCreateDb($db);$service=new NativeCompanyTicketCommands($wrapped,'tbl');$priority=array_values(array_filter($catalog->list(2,17,'priority'),fn($r)=>$r['name']==='Medium'))[0];
$input=['subject'=>'Native email ticket','message'=>'<p>Message</p><script>bad()</script>','name'=>'External sender','email'=>'sender@example.invalid','department'=>NativeCompanyResourceId::encode('company_ticket_department',1),'priority'=>$priority['native_id'],'assigned'=>'17'];
$deny(fn()=>$service->assertCreatedInOwned($wrapped,'tbl',2,17,'company_ticket',1));
$id=$service->createNative(2,17,$input,str_repeat('a',64));$source=NativeCompanyResourceId::decode('ticket',$id)['source_id'];$deny(fn()=>$service->assertCreatedInOwned($wrapped,'tbl',2,17,'company_ticket',$source));$row=$db->query('SELECT * FROM tblcompany_tickets WHERE id=?',[$source])->row_array();
kc($row['client_id']===null&&$row['status']==='native'&&$row['priority']==='native'&&!str_contains($row['body'],'script'),'email native ticket has NULL customer, authoritative native refs and purified message');
$meta=$db->query('SELECT * FROM tblcompany_ticket_native_metadata WHERE ticket_id=?',[$source])->row_array();kc($meta['sender_origin']==='staff_entered'&&$meta['contact_id']===null,'entered email not misrepresented as registered or verified identity');
kc($service->createNative(2,17,$input,str_repeat('a',64))===$id&&(int)$db->query('SELECT COUNT(*) n FROM tblcompany_tickets')->row_array()['n']===1,'durable replay creates no duplicate');
$deny(fn()=>$service->createNative(2,17,array_replace($input,['subject'=>'Changed']),str_repeat('a',64)));
$deny(fn()=>$service->createNative(2,17,array_replace($input,['department'=>NativeCompanyResourceId::encode('company_ticket_department',2)]),str_repeat('b',64)));
$contact=array_replace($input,['contactid'=>'2','userid'=>'2','name'=>'','email'=>'']);$contactId=$service->createNative(2,17,$contact,str_repeat('c',64));$cr=$db->query('SELECT * FROM tblcompany_ticket_native_metadata WHERE ticket_id=?',[NativeCompanyResourceId::decode('ticket',$contactId)['source_id']])->row_array();kc($cr['sender_email']==='a@example.invalid'&&$cr['sender_origin']==='registered_contact','native selected contact derives sender from scoped row');
$deny(fn()=>$service->createNative(2,17,array_replace($contact,['contactid'=>'3','userid'=>'3']),str_repeat('d',64)));
$db->query('UPDATE tblcontacts SET active=0 WHERE id=2');$deny(fn()=>$service->createNative(2,17,$contact,str_repeat('c',64)));$db->query('UPDATE tblcontacts SET active=1 WHERE id=2');
$before=$db->query('SELECT * FROM tblcompany_tickets ORDER BY id')->result_array();$db->query("CREATE TRIGGER no_ticket_audit BEFORE INSERT ON tblissuers_audit WHEN NEW.event='company_native_ticket_created' BEGIN SELECT RAISE(IGNORE); END");try{$service->createNative(2,17,$input,str_repeat('e',64));throw new LogicException('ignored audit accepted');}catch(RuntimeException$e){kc(in_array($e->getMessage(),['issuer_ticket_write_unconfirmed','issuer_operation_write_unconfirmed'],true),'ignored audit is failure');}kc($before===$db->query('SELECT * FROM tblcompany_tickets ORDER BY id')->result_array(),'ticket metadata/catalog/receipt transaction rolls back');$db->query('DROP TRIGGER no_ticket_audit');
echo "Native ticket create actual SQLite with explicit metadata double: email NULL/customer contact, purified body, A/B refs, durable replay/current revocation and audit rollback passed. No GUI, notification or real MySQL claim.\n";
