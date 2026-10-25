<?php
/** Synthetic storage/SDK contracts. No provider network or real credentials. */
declare(strict_types=1);
error_reporting(E_ALL);
set_error_handler(static function($level,$message,$file,$line){throw new ErrorException($message,0,$level,$file,$line);});
define('BASEPATH',dirname(__DIR__,2).'/system/');
require dirname(__DIR__,2).'/modules/gerencianet_gateway/services/EfiChargeLedger.php';
require dirname(__DIR__,2).'/modules/gerencianet_gateway/services/EfiCertificateStore.php';
$checks=0;
function check(bool $ok,string $name):void{++$GLOBALS['checks'];if(!$ok)throw new RuntimeException($name);}
function denied(callable $run,string $name):void{try{$run();}catch(Throwable $error){check(true,$name);return;}throw new RuntimeException('Expected denial: '.$name);}
class EfiResult { function __construct(private array $rows){}function result_array(){return $this->rows;}function row_array(){return $this->rows[0]??[];} }
class EfiFakeDb {
 public array $invoice=['id'=>17,'issuer_id'=>null,'hash'=>'synthetic_hash','total'=>'10.00','status'=>1,'currency'=>1,'currency_name'=>'BRL'];
 public array $bindings=[],$receipts=[],$queries=[]; public bool $lock=true,$failTracking=false,$failInsert=false;
 function query($sql,$args=[]){
  $this->queries[]=[$sql,$args];$rows=[];
  if(str_contains($sql,'DATABASE() AS database_name'))$rows=[['database_name'=>'synthetic_fixture']];
  elseif(str_contains($sql,'GET_LOCK'))$rows=[['acquired'=>(int)$this->lock]];
  elseif(str_contains($sql,'RELEASE_LOCK'))$rows=[['released'=>1]];
  elseif(str_starts_with($sql,'SELECT i.id'))$rows=((int)$args[0]===$this->invoice['id']&&$args[1]===$this->invoice['hash']&&$this->invoice['issuer_id']===null)?[$this->invoice]:[];
  elseif(str_starts_with($sql,'SELECT * FROM tblefi_charge_bindings'))$rows=array_values(array_filter($this->bindings,fn($b)=>[$b['kind'],$b['sandbox'],$b['account_hash'],$b['provider_id']] === [$args[0],$args[1],$args[2],$args[3]]));
  elseif(str_starts_with($sql,'INSERT INTO tblefi_charge_bindings')){
   if($this->failInsert)return false;
   $this->bindings[]=['id'=>count($this->bindings)+1,'kind'=>$args[0],'sandbox'=>$args[1],'account_hash'=>$args[2],'provider_id'=>$args[3],'invoice_id'=>$args[4],'issuer_id'=>null,'amount_minor'=>$args[5],'currency'=>'BRL','payment_id'=>null];
  }elseif(str_starts_with($sql,'SELECT id,invoiceid,amount,paymentmode,transactionid'))$rows=array_values(array_filter($this->receipts,fn($r)=>[$r['invoiceid'],$r['paymentmode'],$r['transactionid']] === [(int)$args[0],$args[1],$args[2]]));
  elseif(str_starts_with($sql,'UPDATE tblefi_charge_bindings')){
   if($this->failTracking)return false;
   foreach($this->bindings as &$b)if($b['id']===$args[1]&&($b['payment_id']===null||$b['payment_id']===$args[2]))$b['payment_id']=$args[0];unset($b);
  }else throw new LogicException('Unexpected query in fake');
  return new EfiResult($rows);
 }
}
$options=['client_id'=>'synthetic_client','sandbox'=>true];$provider=str_repeat('a',26);
$db=new EfiFakeDb();$ledger=new EfiChargeLedger($db,'tbl');$invoice=$ledger->invoice(17,'synthetic_hash');
$binding=$ledger->bind('pix',$provider,$options,$invoice);check(count($db->bindings)===1,'new local binding');
$ledger->bind('pix',$provider,$options,$invoice);check(count($db->bindings)===1,'binding replay unchanged');
$paid=['status'=>'CONCLUIDA','txid'=>$provider,'valor'=>['original'=>'10.00'],'pix'=>[['txid'=>$provider,'valor'=>'10.00','endToEndId'=>str_repeat('E',32)]]];
EfiChargeLedger::assertPaid($binding,$paid);check(true,'authenticated full Pix proof');
foreach(['status','txid','valor','pix'] as $field){$bad=$paid;unset($bad[$field]);denied(fn()=>EfiChargeLedger::assertPaid($binding,$bad),'missing paid field '.$field);}
$bad=$paid;$bad['pix'][0]['devolucoes']=[['id'=>'synthetic']];denied(fn()=>EfiChargeLedger::assertPaid($binding,$bad),'refund requires review');
$bad=$paid;$bad['pix'][0]['valor']='9.99';denied(fn()=>EfiChargeLedger::assertPaid($binding,$bad),'partial value denied');
$bad=$paid;$bad['pix'][]=$bad['pix'][0];denied(fn()=>EfiChargeLedger::assertPaid($binding,$bad),'multiple receipts require review');
foreach([['client_id'=>'other','sandbox'=>true],['client_id'=>'synthetic_client','sandbox'=>false]] as $other)denied(fn()=>$ledger->bound('pix',$provider,$other,$invoice),'account/environment isolation');
$other=$invoice;$other['id']=18;denied(fn()=>$ledger->bound('pix',$provider,$options,$other),'invoice association not caller controlled');
$other=$invoice;$other['total']='11.00';denied(fn()=>$ledger->bound('pix',$provider,$options,$other),'amount change requires review');
denied(fn()=>$ledger->bound('pix',str_repeat('b',26),$options,$invoice),'historical unbound Pix not inferred');
$db->invoice['issuer_id']=5;denied(fn()=>$ledger->invoice(17,'synthetic_hash'),'named tenant denied');$db->invoice['issuer_id']=null;
denied(fn()=>$ledger->invoice(17,'wrong_hash'),'wrong invoice hash denied');
$writes=0;$write=function($data)use($db,&$writes){++$writes;$db->receipts[]=$data+['id'=>91];return 91;};
$db->lock=false;denied(fn()=>$ledger->settle('pix',$provider,$options,$invoice,$paid,$write),'busy callback rejected');check($writes===0,'busy does not write');$db->lock=true;
$db->failTracking=true;denied(fn()=>$ledger->settle('pix',$provider,$options,$invoice,$paid,$write),'tracking write failure explicit');check($writes===1&&count($db->receipts)===1,'native receipt remains committed');
$db->failTracking=false;$id=$ledger->settle('pix',$provider,$options,$invoice,$paid,$write);check($id===91&&$writes===1,'replay recovers receipt without second write');
$ledger->settle('pix',$provider,$options,$invoice,$paid,$write);check($writes===1,'idempotent second callback');
$db->receipts[0]['amount']='8.00';denied(fn()=>$ledger->settle('pix',$provider,$options,$invoice,$paid,$write),'receipt content conflict');
foreach(['boleto','card'] as $kind){$b=$binding;$b['kind']=$kind;$b['provider_id']='987';$r=['code'=>200,'data'=>['charge_id'=>987,'status'=>'paid','total'=>1000]];EfiChargeLedger::assertPaid($b,$r);check(true,$kind.' paid cents');$r['data']['total']=999;denied(fn()=>EfiChargeLedger::assertPaid($b,$r),'wrong cents denied');}
// A writer failure before receipt insertion must leave tracking unset and retryable.
$db2=new EfiFakeDb();$l2=new EfiChargeLedger($db2,'tbl');$l2->bind('pix',$provider,$options,$invoice);
denied(fn()=>$l2->settle('pix',$provider,$options,$invoice,$paid,fn()=>false),'native write false is not success');check(!$db2->receipts&&$db2->bindings[0]['payment_id']===null,'failed native writer records nothing');
echo "Efí ledger synthetic contracts: {$checks} PASS; no SDK/network/database engine execution.\n";
