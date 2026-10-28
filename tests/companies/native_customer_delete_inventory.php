<?php
/** Detect missing direct and polymorphic customer fields in the shipped SQL schema. */
declare(strict_types=1);
require_once __DIR__.'/../../application/services/CompanyCustomerDeletionReferences.php';
$sql=file_get_contents(__DIR__.'/../../install/database.sql');
if(!is_string($sql))throw new RuntimeException('installation schema unavailable');
$reflection=new ReflectionClass(CompanyCustomerDeletionReferences::class);
$direct=$reflection->getReflectionConstant('DIRECT')->getValue();$poly=$reflection->getReflectionConstant('POLYMORPHIC')->getValue();
foreach(['contacts'=>'userid','tickets'=>'userid','contracts'=>'client'] as$table=>$field)if(($direct[$table]??null)!==$field)throw new RuntimeException('missing core reference '.$table.'.'.$field);
preg_match_all('/CREATE TABLE `tbl([^`]+)` \((.*?)\) ENGINE=/s',$sql,$tables,PREG_SET_ORDER);$checkedDirect=0;$checkedPoly=0;
foreach($tables as$table){
    preg_match_all('/^\s*`([^`]+)`/m',$table[2],$columns);$columns=$columns[1];$name=$table[1];
    foreach(['clientid','client_id','customer_id'] as$field)if(in_array($field,$columns,true)){
        if(($direct[$name]??null)!==$field)throw new RuntimeException('missing direct customer reference '.$name.'.'.$field);$checkedDirect++;
    }
    if(in_array('rel_id',$columns,true)&&in_array('rel_type',$columns,true)){
        if(($poly[$name][0]??null)!=='rel_id'||($poly[$name][1]??null)!=='rel_type')throw new RuntimeException('missing polymorphic reference '.$name);$checkedPoly++;
    }elseif(in_array('relid',$columns,true)&&in_array('fieldto',$columns,true)){
        if(($poly[$name][0]??null)!=='relid'||($poly[$name][1]??null)!=='fieldto')throw new RuntimeException('missing field relation '.$name);$checkedPoly++;
    }
}
if($checkedDirect<10||$checkedPoly<16)throw new RuntimeException('installation schema inventory unexpectedly incomplete');
echo "Shipped schema inventory: $checkedDirect direct and $checkedPoly polymorphic reference columns accounted for.\n";
