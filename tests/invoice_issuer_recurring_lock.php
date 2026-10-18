<?php
// Composition test: requires the actual migration-383 recurrence lock implementation.
require __DIR__.'/invoice_issuer_scope.php';
require_once getenv('RECURRING_LOCK_FILE') ?: __DIR__.'/../application/services/RecurringInvoiceLock.php';
use app\services\RecurringInvoiceLockUnavailable;
$checks=0;
$before=count($db->queries);
denied(fn()=>$model->update(['removed_items'=>[2]],1),'invoice_item_scope_invalid');
$queries=array_column(array_slice($db->queries,$before),0);
same(1,count(array_filter($queries,fn($sql)=>str_contains($sql,'GET_LOCK('))),'matrix editor acquires shared lock');
same(1,count(array_filter($queries,fn($sql)=>str_contains($sql,'RELEASE_LOCK('))),'exception releases editor lock');
$name='recurrence:'.substr(hash('sha256',db_prefix()),0,48);
$mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
if($mysql) {
 same(1,(int)$pdo->query("SELECT IS_FREE_LOCK(".$pdo->quote($name).")")->fetchColumn(),'real MySQL lock released');
 $other=new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD'));
 $q=$other->prepare('SELECT GET_LOCK(?,0)');$q->execute([$name]);same(1,(int)$q->fetchColumn(),'second connection holds schedule lock');
} else { $db->lockAvailable=false; }
try {
 try { $model->update(['removed_items'=>[2]],1); throw new RuntimeException('Busy editor accepted'); }
 catch(RecurringInvoiceLockUnavailable $expected) { same(true,true,'busy lock rejects editor before write'); }
} finally {
 if($mysql) { $q=$other->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$name]); }
 else { $db->lockAvailable=true; }
}
$db->query('UPDATE '.db_prefix().'invoices SET recurring=0 WHERE id=1');
$before=count($db->queries);
denied(fn()=>$model->update(['removed_items'=>[2]],1),'invoice_item_scope_invalid');
$queries=array_column(array_slice($db->queries,$before),0);
same(0,count(array_filter($queries,fn($sql)=>str_contains($sql,'GET_LOCK('))),'ordinary invoice does not acquire recurring lock');
$before=count($db->queries);
denied(fn()=>$model->update(['recurring'=>'custom','removed_items'=>[2]],1),'invoice_item_scope_invalid');
$queries=array_column(array_slice($db->queries,$before),0);
same(1,count(array_filter($queries,fn($sql)=>str_contains($sql,'GET_LOCK('))),'enabling recurrence acquires shared lock');
echo 'Invoice editor recurrence lock: '.$checks.' checks passed ('.($mysql?'real MySQL two connections':'SQLite lock protocol double').").\n";
