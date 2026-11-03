<?php
require __DIR__.'/auxiliary_contract.php';
require APPPATH.'services/NativeCurrencyReferenceCatalog.php';
$rows=NativeCurrencyReferenceCatalog::tables($p);
$expected=['invoices'=>'currency','expenses'=>'currency','proposals'=>'currency','estimates'=>'currency','clients'=>'default_currency','creditnotes'=>'currency','subscriptions'=>'currency'];
essentialCheck(count($rows)===7,'native catalogue contains all seven existing reference sources');
foreach($rows as$row){$suffix=substr($row['table'],strlen($p));essentialCheck(isset($expected[$suffix])&&$expected[$suffix]===$row['field'],'shared native reference source exact');unset($expected[$suffix]);}
essentialCheck($expected===[],'no missing or duplicate reference source');
try{NativeCurrencyReferenceCatalog::tables('bad;');throw new RuntimeException('not denied');}catch(InvalidArgumentException $expected){}
$app=file_get_contents(APPPATH.'libraries/App.php');$model=file_get_contents(APPPATH.'models/Currencies_model.php');
essentialCheck(str_contains($app,'NativeCurrencyReferenceCatalog::tables(db_prefix())')&&str_contains($model,'NativeCurrencyReferenceCatalog::tables(db_prefix())'),'both native paths use one shared metadata source');
essentialCheck(!str_contains($model,"->drop_column('items'"),'currency deletion cannot drop historical prices column');
echo "Shared native currency reference catalogue and preservation guard: passed.\n";
