<?php
require __DIR__.'/support/NativeInstalledSchemaFixture.php';
$source=file_get_contents(dirname(__DIR__).'/install/database.sql');$p='essential_0123456789_';$plan=NativeInstalledSchemaFixture::plan($source,$p);
if(count($plan)<100)throw new RuntimeException('Incomplete native baseline');
foreach($plan as$name=>$ddl){foreach(array_merge([$ddl['create']],$ddl['alters'])as$sql){if(!str_contains($sql,'`'.$p.$name.'`')||preg_match('/\b(?:INSERT INTO|UPDATE `?essential_|DELETE FROM|TRUNCATE|DROP TABLE)\b/i',$sql))throw new RuntimeException('Seed mutation imported');}}
if(count($plan['customers_groups']['alters'])!==2||!str_contains(implode(' ',$plan['customers_groups']['alters']),'ADD PRIMARY KEY'))throw new RuntimeException('Native group primary/auto metadata omitted');
try{NativeInstalledSchemaFixture::plan($source,'tbl');throw new LogicException('Production prefix accepted');}catch(InvalidArgumentException$expected){}
if(NativeInstalledSchemaFixture::plan($source."\nINSERT INTO tblstaff VALUES ('synthetic-never-executed');",$p)!==$plan)throw new RuntimeException('Seed data affected DDL-only plan');
echo 'Native fixture: '.count($plan).' original DDL plans, exact native indexes, no seed rows, restricted prefix passed.'.PHP_EOL;
