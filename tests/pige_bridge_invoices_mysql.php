<?php
/** Native invoice writer/ACL with real disposable MySQL. Never connects to a financial provider. */
require __DIR__.'/pige_bridge_connections_mysql.php';
require_once __DIR__.'/../modules/pige_bridge/libraries/PigeBridgeInvoices.php';
$file=__DIR__.'/../modules/pige_bridge/migrations/103_version_103.php';$source=preg_replace('/^<\?php\s*/','',file_get_contents($file),1);$source=str_replace('class Migration_Version_103 extends','class PigeInvoiceFixtureMigration103 extends',$source,$changed);if($changed!==1)throw new RuntimeException('migration declaration changed');eval($source);(new PigeInvoiceFixtureMigration103())->up();(new PigeInvoiceFixtureMigration103())->up();
register_shutdown_function(function()use($db,$p){$db->pdo->exec('DROP TABLE IF EXISTS '.$p.'pige_bridge_invoice_origins');});
$scope['account_id']='synthetic-invoice-account';$secret=str_repeat('invoice-fixture-token-',3);$connections->save('invoice',$scope,$secret,true,0,7);$connections->saveMapping('invoice',1,['enabled'=>true,'actor_id'=>7,'currency_id'=>1],7);
$bridge=new PigeBridgeInvoices($db,$cipher,$p);$secretHash=hash('sha256',$secret);
$event=['schema_version'=>2,'event_id'=>'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa','event_type'=>'invoice.requested','scope'=>$scope,'data'=>['charge_id'=>'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb','client_id'=>$ca,'amount_cents'=>12345,'currency'=>'BRL','due_date'=>'2030-12-31','description'=>'Synthetic PIGE charge']];
function invoiceStatusEvent(array $event):array{$event['event_type']='invoice.status.requested';$event['data']=['charge_id'=>$event['data']['charge_id']];return $event;}
function bridgeThrows(callable $call,string $label):void{$failed=false;try{$call();}catch(Throwable $e){$failed=true;}essentialCheck($failed,$label);}
$before=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n;$payments=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords')->row()->n;
$_SESSION=['issuer_id'=>$b];$created=$bridge->create('invoice',$event,$secretHash);$_SESSION=['issuer_id'=>$a];$replay=$bridge->create('invoice',$event,$secretHash);unset($_SESSION);
essentialCheck($created===$replay&&$created['status']==='invoice_created'&&$created['invoice_id']>0,'idempotent invoice is invariant under UI company A/B');
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n===$before+1,'one local invoice only');
$writes=count($db->writes());$read=$bridge->status('invoice',invoiceStatusEvent($event),$secretHash);essentialCheck($read===$created&&count($db->writes())===$writes,'status performs zero writes even after committed invoice without journal attachment');
$changed=$event;$changed['data']['amount_cents']++;bridgeThrows(fn()=>$bridge->create('invoice',$changed,$secretHash),'same charge changed amount conflicts');
$changed=$event;$changed['event_id']='cccccccc-cccc-4ccc-8ccc-cccccccccccc';bridgeThrows(fn()=>$bridge->create('invoice',$changed,$secretHash),'same charge new event conflicts');
$changed=$event;$changed['scope']['issuer_id']=(string)$b;bridgeThrows(fn()=>$bridge->create('invoice',$changed,$secretHash),'payload issuer cannot override credential');
$foreign=$event;$foreign['event_id']='dddddddd-dddd-4ddd-8ddd-dddddddddddd';$foreign['data']['charge_id']='eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee';$foreign['data']['client_id']=$cb;bridgeThrows(fn()=>$bridge->create('invoice',$foreign,$secretHash),'client owned by unrelated company denied');
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n===$before+1,'rejected foreign client creates no invoice');
$pending=$event;$pending['event_id']='11111111-1111-4111-8111-111111111111';$pending['data']['charge_id']='22222222-2222-4222-8222-222222222222';
$db->failOn='INSERT INTO '.$p.'invoices';bridgeThrows(fn()=>$bridge->create('invoice',$pending,$secretHash),'writer failure preserves recoverable origin');$db->failOn=null;
$writes=count($db->writes());$state=$bridge->status('invoice',invoiceStatusEvent($pending),$secretHash);essentialCheck($state['status']==='pending'&&$state['invoice_id']===null&&count($db->writes())===$writes,'read-only status after uncertain attempt neither issues nor enqueues');
$retried=$bridge->create('invoice',$pending,$secretHash);essentialCheck($retried['status']==='invoice_created'&&$retried['request_id']===$state['request_id'],'explicit same request retry recovers with immutable request identity');
$connections->saveMapping('invoice',2,['enabled'=>false,'actor_id'=>7,'currency_id'=>1],7);bridgeThrows(fn()=>$bridge->create('invoice',$pending,$secretHash),'revoked mapping blocks further create even retry');
$writes=count($db->writes());essentialCheck($bridge->status('invoice',invoiceStatusEvent($pending),$secretHash)['invoice_id']===$retried['invoice_id']&&count($db->writes())===$writes,'mapping disabled still permits authorized read-only historical receipt');
$db->query('UPDATE '.$p.'invoices SET issuer_id=? WHERE id=?',[$b,$created['invoice_id']]);bridgeThrows(fn()=>$bridge->status('invoice',invoiceStatusEvent($event),$secretHash),'changed native invoice issuer is not silently trusted');$db->query('UPDATE '.$p.'invoices SET issuer_id=? WHERE id=?',[$a,$created['invoice_id']]);
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords')->row()->n===$payments,'local invoice never records payment');
echo "PIGE invoice native integration: immutable origin, variable scoped client, replay, failure recovery, zero-write status; no external issue.\n";
