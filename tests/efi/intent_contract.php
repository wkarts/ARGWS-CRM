<?php
/** Synthetic durable-dispatch protocol: not a substitute for MySQL commit/concurrency checks. */
declare(strict_types=1);
require __DIR__.'/operational_contract.php';
require dirname(__DIR__,2).'/modules/gerencianet_gateway/services/EfiChargeIntent.php';
class EfiIntentDb extends EfiFakeDb {
 public array $intents=[],$credits=[];public int $_trans_depth=0,$commits=0,$affected=0;
 public bool $failCommit=false,$ambiguousCommit=false,$raw=false;public string $autocommit='1',$engine='InnoDB';private array $backup=[];
 function trans_begin(){$this->backup=$this->intents;$this->_trans_depth=1;return true;}
 function trans_status(){return true;}
 function trans_commit(){if($this->ambiguousCommit){$this->_trans_depth=0;$this->backup=$this->intents;return false;}if($this->failCommit)return false;++$this->commits;$this->_trans_depth=0;return true;}
 function trans_rollback(){$this->intents=$this->backup;$this->_trans_depth=0;return true;}
 function affected_rows(){return $this->affected;}
 function query($sql,$args=[]){
  $this->affected=0;$rows=[];
  if(str_starts_with($sql,'SELECT * FROM tblefi_charge_intents'))$rows=array_values(array_filter($this->intents,fn($i)=>(int)$i['invoice_id']===(int)$args[0]));
  elseif(str_starts_with($sql,'SELECT amount FROM tblinvoicepaymentrecords'))$rows=$this->receipts;
  elseif(str_starts_with($sql,'SELECT amount FROM tblcredits'))$rows=$this->credits;
  elseif(str_starts_with($sql,'SELECT @@SESSION.autocommit'))$rows=[['autocommit'=>$this->autocommit,'completion_type'=>'0']];
  elseif(str_starts_with($sql,'SELECT ENGINE FROM information_schema'))$rows=[['ENGINE'=>$this->engine]];
  elseif(str_starts_with($sql,'SET TRANSACTION')){if($this->raw)throw new RuntimeException('raw transaction');}
  elseif(str_starts_with($sql,'SELECT id,issuer_id,hash,total,status,currency'))$rows=[$this->invoice];
  elseif(str_starts_with($sql,'INSERT INTO tblefi_charge_intents')){
   if($this->failInsert||$this->intents)return false;
   $this->intents[]=['id'=>1,'invoice_id'=>$args[0],'issuer_id'=>null,'kind'=>$args[1],'sandbox'=>$args[2],'account_hash'=>$args[3],'amount_minor'=>$args[4],'currency'=>'BRL','state'=>'dispatched','provider_id'=>null];
  }elseif(str_starts_with($sql,'UPDATE tblefi_charge_intents SET state=?')){
   foreach($this->intents as &$i)if($i['id']===$args[2]&&$i['state']===$args[3]){$i['state']=$args[0];$i['provider_id']=$args[1];$this->affected=1;}unset($i);
  }elseif(str_starts_with($sql,"UPDATE tblefi_charge_intents SET state='unknown'")){
   foreach($this->intents as &$i)if($i['id']===$args[0]&&in_array($i['state'],['dispatched','provider_created','payment_dispatched'],true)){$i['state']='unknown';$this->affected=1;}unset($i);
  }elseif(str_starts_with($sql,'SELECT state,provider_id FROM tblefi_charge_intents'))$rows=array_values(array_filter($this->intents,fn($i)=>$i['id']===$args[0]));
  else return parent::query($sql,$args);
  $this->queries[]=[$sql,$args];return new EfiResult($rows);
 }
}
$db=new EfiIntentDb();$intents=new EfiChargeIntent($db,'tbl');$remotePosts=0;
$permit=$intents->reserve('pix',$options,$db->invoice);check($permit['dispatch']&&$db->commits===1&&$db->_trans_depth===0,'dispatch permission only after confirmed commit');++$remotePosts;
denied(fn()=>$intents->reserve('pix',$options,$db->invoice),'duplicate request cannot dispatch');
denied(fn()=>$intents->reserve('card',$options,$db->invoice),'different modality cannot race same invoice');
$intents->complete($permit,$provider,$options,$db->invoice);$retry=$intents->reserve('pix',$options,$db->invoice);if($retry['dispatch'])++$remotePosts;
check(!$retry['dispatch']&&$remotePosts===1,'bound replay is read-only, one provider POST');
$intents->uncertain($permit);check($db->intents[0]['state']==='bound','uncertain never overwrites binding');
foreach([['client_id'=>'other','sandbox'=>true],['client_id'=>'synthetic_client','sandbox'=>false]] as $other)denied(fn()=>$intents->reserve('pix',$other,$db->invoice),'different account/environment cannot issue second intent');
foreach(['failInsert','failCommit','ambiguousCommit','raw'] as $failure){$d=new EfiIntentDb();$d->$failure=true;$i=new EfiChargeIntent($d,'tbl');denied(fn()=>$i->reserve('pix',$options,$d->invoice),'no dispatch after '.$failure);}
$d=new EfiIntentDb();$d->_trans_depth=1;denied(fn()=>(new EfiChargeIntent($d,'tbl'))->reserve('pix',$options,$d->invoice),'outer CI transaction denied');
$d=new EfiIntentDb();$d->autocommit='0';denied(fn()=>(new EfiChargeIntent($d,'tbl'))->reserve('pix',$options,$d->invoice),'autocommit0 denied');
$d=new EfiIntentDb();$d->engine='MyISAM';denied(fn()=>(new EfiChargeIntent($d,'tbl'))->reserve('pix',$options,$d->invoice),'nontransactional intent denied');
foreach(['dispatched','provider_created','payment_dispatched','unknown'] as $state){$d=new EfiIntentDb();$i=new EfiChargeIntent($d,'tbl');$p=$i->reserve('boleto',$options,$d->invoice);$d->intents[0]['state']=$state;denied(fn()=>$i->reserve('boleto',$options,$d->invoice),'no automatic rePOST after '.$state);}
$d=new EfiIntentDb();$i=new EfiChargeIntent($d,'tbl');$p=$i->reserve('boleto',$options,$d->invoice);$i->providerCreated($p,'987');check($d->intents[0]['state']==='provider_created'&&$d->intents[0]['provider_id']==='987','first boleto ID durably retained');$i->boletoPayment($p,'987');check($d->intents[0]['state']==='payment_dispatched','second POST fenced first');$i->complete($p,'987',$options,$d->invoice);check($d->intents[0]['state']==='bound','boleto completion bound');
foreach([6,2,5] as $status){$d=new EfiIntentDb();$d->invoice['status']=$status;denied(fn()=>(new EfiChargeIntent($d,'tbl'))->reserve('pix',$options,$d->invoice),'draft/paid/cancelled creation denied');}
foreach(['receipts','credits'] as $type){$d=new EfiIntentDb();$d->$type=[['amount'=>'0.01']];denied(fn()=>(new EfiChargeIntent($d,'tbl'))->reserve('pix',$options,$d->invoice),'partial receipt/credit not charged in full');}
// The second boleto dispatch has the same durability guard, not merely an UPDATE.
$d=new EfiIntentDb();$i=new EfiChargeIntent($d,'tbl');$p=$i->reserve('boleto',$options,$d->invoice);$i->providerCreated($p,'987');$d->_trans_depth=1;
denied(fn()=>$i->boletoPayment($p,'987'),'second POST denied inside outer transaction');$d->_trans_depth=0;$d->raw=true;
denied(fn()=>$i->boletoPayment($p,'987'),'second POST denied inside raw transaction');$d->raw=false;$d->failCommit=true;
denied(fn()=>$i->boletoPayment($p,'987'),'second POST denied on unconfirmed commit');
check($d->intents[0]['state']==='provider_created','no confirmed second dispatch after commit failure');
// A known provider identity survives a subsequent binding failure for manual reconciliation.
$d=new EfiIntentDb();$i=new EfiChargeIntent($d,'tbl');$p=$i->reserve('pix',$options,$d->invoice);$d->failInsert=true;
denied(fn()=>$i->complete($p,$provider,$options,$d->invoice),'binding failure explicit');$i->uncertain($p);
check($d->intents[0]['provider_id']===$provider&&$d->intents[0]['state']==='unknown','known provider ID retained despite failed binding');
echo "Efí dispatch intent synthetic: {$checks} cumulative contracts PASS; no provider or database engine.\n";
