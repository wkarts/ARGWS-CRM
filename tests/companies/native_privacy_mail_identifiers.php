<?php
require dirname(__DIR__).'/issuers_core_support.php';require APPPATH.'services/NativeCompanyPrivacyMailSchema.php';
$f=essentialFixture('1.0.2',1);$db=$f->db;$p='privacy_'.bin2hex(random_bytes(10));$start=$GLOBALS['essentialChecks'];
foreach(['issuers_registry','gdpr_requests','mail_queue']as$t)$db->query('CREATE TABLE '.$p.$t.'(id INTEGER PRIMARY KEY)');
NativeCompanyPrivacyMailSchema::ensure($db,$p);NativeCompanyPrivacyMailSchema::ensure($db,$p);NativeCompanyPrivacyMailSchema::assertValid($db,$p);
essentialCheck(strlen($p)===28&&strlen($p.NativeCompanyPrivacyMailSchema::RECEIPTS)===64,'maximum supported prefix creates actual 64-character table and replays');
try{NativeCompanyPrivacyMailSchema::ensure(new stdClass(),str_repeat('p',29));throw new RuntimeException('oversized prefix accepted');}catch(InvalidArgumentException $e){essentialCheck($e->getMessage()==='Invalid prefix','oversized prefix denied before any database access');}
$source=file_get_contents(APPPATH.'services/NativeCompanyPrivacyMailSchema.php');essentialCheck(str_contains($source,"'CONSTRAINT npmf_'.sha1("),'MySQL DDL names every FK deterministically below64 regardless of native _ibfk suffix');
echo 'Privacy mail identifier boundaries: '.($GLOBALS['essentialChecks']-$start).' SQLite and deterministic DDL checks passed; MySQL execution remains separate.'.PHP_EOL;
