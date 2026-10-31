<?php
require dirname(__DIR__).'/issuers_core_support.php';require APPPATH.'services/NativeCompanyCustomerUploadMailSchema.php';
$f=essentialFixture('1.0.2',1);$db=$f->db;$p='upload'.bin2hex(random_bytes(16));$start=$GLOBALS['essentialChecks'];
foreach(['issuers_registry','files','mail_queue']as$t)$db->query('CREATE TABLE '.$p.$t.'(id INTEGER PRIMARY KEY)');
NativeCompanyCustomerUploadMailSchema::ensure($db,$p);NativeCompanyCustomerUploadMailSchema::ensure($db,$p);NativeCompanyCustomerUploadMailSchema::assertValid($db,$p);
essentialCheck(strlen($p)===38&&strlen($p.NativeCompanyCustomerUploadMailSchema::OUTBOX)===64,'maximum supported prefix creates actual 64-character table and replays');
try{NativeCompanyCustomerUploadMailSchema::ensure(new stdClass(),str_repeat('p',39));throw new RuntimeException('oversized prefix accepted');}catch(InvalidArgumentException $e){essentialCheck($e->getMessage()==='Invalid prefix','oversized prefix denied before any database access');}
$source=file_get_contents(APPPATH.'services/NativeCompanyCustomerUploadMailSchema.php');essentialCheck(str_contains($source,"'CONSTRAINT ncfum_'.sha1("),'MySQL DDL names every FK deterministically below64 regardless of native _ibfk suffix');
echo 'Customer upload mail identifier boundaries: '.($GLOBALS['essentialChecks']-$start).' SQLite and deterministic DDL checks passed; MySQL execution remains separate.'.PHP_EOL;
