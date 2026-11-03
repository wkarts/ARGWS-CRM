<?php
require dirname(__DIR__).'/issuers_core_support.php';
$f=essentialFixture('1.0.2',1);$db=$f->db;$p=$f->prefix;
$db->query("CREATE TABLE {$p}mail_queue (id INT NOT NULL PRIMARY KEY, status VARCHAR(16) NOT NULL, message TEXT NOT NULL)");
$db->query("INSERT INTO {$p}mail_queue VALUES(1,'pending','synthetic existing message')");
CompanyMailQueueSchema::ensure($db,$p);
CompanyMailQueueSchema::ensure($db,$p);
$row=$db->query("SELECT * FROM {$p}mail_queue WHERE id=1")->row();
essentialCheck($row->issuer_id===null,'historical pending origin is not guessed');
essentialCheck($row->status==='pending' && $row->message==='synthetic existing message','migration preserves queue payload and state');
$db->query("UPDATE {$p}mail_queue SET issuer_id=41 WHERE id=1");
CompanyMailQueueSchema::ensure($db,$p);
essentialCheck((int)$db->query("SELECT issuer_id FROM {$p}mail_queue WHERE id=1")->row()->issuer_id===41,'repeat upgrade preserves assigned origin');
$failed=false;try{CompanyMailQueueSchema::ensure($db,'invalid;prefix');}catch(InvalidArgumentException $error){$failed=true;}
essentialCheck($failed,'prefix injection rejected');
$db->query("DROP TABLE {$p}mail_queue");
echo 'Company mail schema: '.$GLOBALS['essentialChecks'].' checks passed ('.$db->driver().').'.PHP_EOL;
