<?php
$type=getenv('NATIVE_RELATION_DOMAIN')?:'contract';$fixtures=['contract'=>'contracts_operations.php','expense'=>'expenses_operations.php','lead'=>'leads.php'];if(!isset($fixtures[$type]))throw new RuntimeException('Invalid fixture domain');
require dirname(__DIR__).'/companies/'.$fixtures[$type];$type=getenv('NATIVE_RELATION_DOMAIN')?:'contract';require_once APPPATH.'services/NativeCompanyRelationOperations.php';
$d=new NativeCompanyRelationOperations($db,$p);$list=$d->options($a,7,$type);essentialCheck(count($list)>0,'actual owned '.$type.' reader feeds native relation dropdown');$first=$list[0];
essentialCheck($d->options($a,7,$type,'',$first['id'])===[ $first ],'exact selected '.$type.' resolves real owned reader');
$denied=false;try{$d->options($b,7,$type,'',$first['id']);}catch(DomainException$e){$denied=true;}essentialCheck($denied,'foreign canonical '.$type.' ID denied');
essentialCheck($d->options($a,7,$type,'%')===[],'wildcard '.$type.' lookup is literal');
$denied=false;try{$d->options($a,7,$type,'','4242');}catch(DomainException$e){$denied=true;}essentialCheck($denied,'secondary cannot read historical '.$type.' identifier');
// Native historical fixture fields, not a migration or adoption of application data.
if($type==='contract'){
    $db->query("ALTER TABLE {$p}contracts ADD client INT");$db->query("INSERT INTO {$p}clients(userid,company,datecreated,addedfrom,active) VALUES(4243,'Legacy relation customer','2020-01-01',7,1)");$db->query("UPDATE {$p}contracts SET client=4243 WHERE id=4242");
    essentialCheck($d->options($primary,7,$type,'','4242')[0]['name']==='Unchanged legacy contract','primary original contract label preserved');
    $db->query("UPDATE {$p}contracts SET client=? WHERE id=4242",[$ca]);$denied=false;try{$d->options($primary,7,$type,'','4242');}catch(DomainException$e){$denied=true;}essentialCheck($denied,'historical contract pointing to named customer denied');
}elseif($type==='expense'){
    foreach(['clientid INT','expense_name VARCHAR(191)','category INT']as$column)$db->query('ALTER TABLE '.$p.'expenses ADD '.$column);
    $db->query('CREATE TABLE '.$p.'expenses_categories(id INT PRIMARY KEY,name VARCHAR(191))');$db->query("INSERT INTO {$p}expenses_categories VALUES(1,'Native category')");$db->query("UPDATE {$p}expenses SET clientid=0,category=1,expense_name='Historical expense'");
    essentialCheck($d->options($primary,7,$type,'','900')[0]['name']==='Native category (Historical expense)','primary native expense category/description preserved');
    $denied=false;try{$d->options($primary,7,$type,'','901');}catch(DomainException$e){$denied=true;}essentialCheck($denied,'native table named issuer row is not historical authority');
}
echo 'Native '.$type.' relation reader actual storage PASS'.PHP_EOL;
