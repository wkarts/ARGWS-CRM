<?php
declare(strict_types=1);
// This negative driver-boundary fixture must remain real SQLite even when the
// surrounding CI job supplies MySQL credentials for the positive SQL suites.
$sqliteFixtureEnvironment=[];foreach(['ISSUERS_TEST_DSN','ISSUERS_TEST_USER','ISSUERS_TEST_PASSWORD']as$name)$sqliteFixtureEnvironment[$name]=getenv($name);
putenv('ISSUERS_TEST_DSN=sqlite::memory:');putenv('ISSUERS_TEST_USER');putenv('ISSUERS_TEST_PASSWORD');
try{require __DIR__.'/companies/invoices.php';}finally{foreach($sqliteFixtureEnvironment as$name=>$value)putenv($value===false?$name:$name.'='.$value);}
foreach($sqliteFixtureEnvironment as$name=>$value)essentialCheck(getenv($name)===$value,'SQLite fixture restores inherited database environment '.$name);
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/ApiService.php';
essentialCheck($db->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite','this driver boundary test must use a real SQLite DB');$fresh=$s->create($a,7,$input,hash('sha256','own-number-sqlite-boundary'));$before=$s->document($a,7,$fresh)->data();$tablesBefore=$db->pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);$calls=0;$api=new BancoBrasil\ApiService($db,$p,null,null,static function()use(&$calls):array{++$calls;throw new LogicException('must not reach any transport');});
try{$api->amendOwnNumber($a,7,1,['previous_reference'=>'CRM'.$fresh,'new_reference'=>'SQLITE-001','contract_confirmed'=>'1','reference'=>'Synthetic local contract','reason'=>'Unsupported driver must not mutate'],str_repeat('d',64));throw new LogicException('BB MySQL schema guard unexpectedly passed SQLite');}catch(PDOException$error){essentialCheck(str_contains($error->getMessage(),'information_schema'),'existing InnoDB schema boundary refuses unsupported SQLite');}
essentialCheck($calls===0&&$s->document($a,7,$fresh)->data()===$before&&$s->payments($a,7,$fresh)===[],'unsupported SQLite leaves canonical document/payment intact and sends no OAuth/PATCH');essentialCheck($db->pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN)===$tablesBefore,'no fictitious BB SQLite schema or aliases created');echo 'BB own-number SQLite guard: '.$GLOBALS['essentialChecks'].' cumulative actual SQLite checks; positive BB runtime remains InnoDB only.'.PHP_EOL;
