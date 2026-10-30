<?php
require __DIR__.'/banco_brasil_local_noncash_v3.php';
require_once APPPATH.'services/CompanyCreditSchema.php';
foreach(['creditnotes','creditnote_refunds'] as $name){
 preg_match('/CREATE TABLE `tbl'.preg_quote($name,'/').'` \(.*?\) ENGINE=InnoDB[^;]*;/s',$native,$m);if(!$m)throw new RuntimeException('native fixture missing '.$name);
 $sql=str_replace('`tbl'.$name.'`','`'.$p.$name.'`',$m[0]);$sql=str_replace('`id` int NOT NULL,','`id` '.($db->driver()==='mysql'?'int NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT').',',$sql);
 if($db->driver()==='sqlite'){$sql=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$sql);$sql=preg_replace('/enum\([^)]*\)/','varchar(50)',$sql);}$db->query($sql);
}

CompanyCreditSchema::ensure($db,$p);$db->data_cache=[];
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/RestoredLocalNoncashFunds.php';
$realCredit=$writer->issueCompanyCredit($a,7,['client_id'=>$ca,'currency'=>1,'date'=>CompanyLocalNoncash::today(),'items'=>[['description'=>'Actual owned independent credit','qty'=>'1.00','rate'=>'105.53']],'note'=>'Canonical native credit; no money inferred from BB'],hash('sha256','V3-real-credit'));
function v3Funds(string$tag,string$kind,string$amount,array$extra=[]):array{global$s,$a,$v3,$declared,$restored;$key=hash('sha256','V3-funds-'.$tag);$review=['declaration_key'=>$declared,'restoration_key'=>$restored,'invoice_revision'=>CompanyLocalNoncashSettlements::revision($s->document($a,7,$v3['invoice'])->data()),'amount'=>$amount,'date'=>CompanyLocalNoncash::today(),'reference'=>'Independent '.$tag,'reason'=>'Actual independent LOCAL native movement; no bank or external operation','evidence_hash'=>hash('sha256','actual-independent-evidence-'.$tag),'local_only_confirmed'=>'1','received_independently_confirmed'=>'1']+$extra;return[$key,new \BancoBrasil\RestoredLocalNoncashFunds($v3['invoice'],$v3['origin'],$v3['query'],$key,$kind,$review),$review];}
[$cashKey,$cashSource,$cashReview]=v3Funds('cash-one','manual_receipt','1.00');$cash=$writer->recordCompanyRestoredLocalFunds($a,7,$cashSource);
essentialCheck($s->position($a,7,$v3['invoice'])['receivable']==='104.53','independent native cash after restoration reduces only actual debt');
essentialCheck($writer->recordCompanyRestoredLocalFunds($a,7,new \BancoBrasil\RestoredLocalNoncashFunds($v3['invoice'],$v3['origin'],$v3['query'],$cashKey,'manual_receipt',$cashReview))===$cash,'independent receipt exact retry remains idempotent');
[$applyKey,$applySource]=v3Funds('credit-twenty','credit_apply','20.00',['credit_id'=>$realCredit]);$writer->recordCompanyRestoredLocalFunds($a,7,$applySource);
essentialCheck($s->position($a,7,$v3['invoice'])['receivable']==='84.53','real native credit application after restoration uses owned available credit');
[$unapplyKey,$unapplySource]=v3Funds('credit-back-five','credit_unapply','5.00',['credit_id'=>$realCredit,'parent_key'=>$applyKey]);$writer->recordCompanyRestoredLocalFunds($a,7,$unapplySource);
essentialCheck($s->position($a,7,$v3['invoice'])['receivable']==='89.53','real credit unapply reopens exactly five');
[$reverseKey,$reverseSource]=v3Funds('cash-back-forty','manual_reverse','0.40',['payment_id'=>$cash]);$writer->recordCompanyRestoredLocalFunds($a,7,$reverseSource);
essentialCheck($s->position($a,7,$v3['invoice'])['receivable']==='89.93','partial native receipt reversal reopens exactly forty cents');
[$closingKey,$closingSource]=v3Funds('cash-close','manual_receipt','89.93');$closingCash=$writer->recordCompanyRestoredLocalFunds($a,7,$closingSource);
essentialCheck($s->position($a,7,$v3['invoice'])['receivable']==='0.00','real independent cash can close restored debt');
essentialCheck($writer->declareCompanyLocalNoncash($a,7,$localSource($v3))===$declared&&$s->position($a,7,$v3['invoice'])['received']==='90.53','old declaration retry cannot consume or duplicate real funds');
[$undoClosingKey,$undoClosingSource]=v3Funds('cash-close-reverse','manual_reverse','89.93',['payment_id'=>$closingCash]);$writer->recordCompanyRestoredLocalFunds($a,7,$undoClosingSource);
[$undoRestKey,$undoRestSource]=v3Funds('cash-rest-reverse','manual_reverse','0.60',['payment_id'=>$cash]);$writer->recordCompanyRestoredLocalFunds($a,7,$undoRestSource);
[$unapplyRestKey,$unapplyRestSource]=v3Funds('credit-back-fifteen','credit_unapply','15.00',['credit_id'=>$realCredit,'parent_key'=>$applyKey]);$writer->recordCompanyRestoredLocalFunds($a,7,$unapplyRestSource);
essentialCheck($s->position($a,7,$v3['invoice'])['receivable']==='105.53'&&$s->position($a,7,$v3['invoice'])['received']==='0.00','all native reversals restore exact debt and retain immutable original funds');
essentialCheck($writer->recordCompanyRestoredLocalFunds($a,7,new \BancoBrasil\RestoredLocalNoncashFunds($v3['invoice'],$v3['origin'],$v3['query'],$cashKey,'manual_receipt',$cashReview))===$cash&&$s->position($a,7,$v3['invoice'])['receivable']==='105.53','old independent receipt retry cannot resurrect reversed projection');
echo 'NEW Noncash V3 independent native funds: '.$GLOBALS['essentialChecks'].' cumulative SQL checks; synthetic evidence only.'.PHP_EOL;
