<?php
declare(strict_types=1);
require_once __DIR__.'/../modules/banco_brasil/libraries/BancoBrasil/Cnab240Generator.php';
require_once __DIR__.'/../modules/banco_brasil/libraries/BancoBrasil/Cnab240ReturnParser.php';
use BancoBrasil\{Scope,Agreement,Fields,Record,Cnab240Generator,Cnab240ReturnParser};
$n=0;
function check($ok,$label){global $n;$n++;if(!$ok)throw new RuntimeException($label);}
function rejects(callable $fn,$label){try{$fn();}catch(Throwable $e){check(true,$label);return;}check(false,$label);}
$scope=new Scope(1,2,'001:00452-3:000000123873-X','sandbox','1234567');
$a=new Agreement($scope,'12ABC34501DE35','EMPRESA SINTETICA','019');
$title=['sequence'=>'1234567890','document'=>'DOC0001','control'=>'ORIGIN0001','issue_date'=>'2026-10-01','due_date'=>'2026-11-01',
 'amount'=>'100.01','species'=>'17','acceptance'=>'N','payer_tax_id'=>'12345678901','payer_name'=>'PAGADOR SINTETICO',
 'payer_address'=>'RUA TESTE 123','payer_district'=>'CENTRO','payer_zip'=>'70000000','payer_city'=>'BRASILIA','payer_state'=>'DF'];
$g=new Cnab240Generator();$bytes=$g->generate($a,1,'2026-10-18',[$title]);$lines=explode("\r\n",rtrim($bytes,"\r\n"));
check(count($lines)===6,'six records');foreach($lines as $line)check(strlen($line)===240,'240 bytes');
check(substr($lines[0],18,14)==='12ABC34501DE35','alphanumeric CNPJ preserved');
check(substr($lines[1],18,15)==='012ABC34501DE35','lot CNPJ zero padding');
check(substr($lines[0],163,3)==='084'&&substr($lines[1],13,3)==='043','wire versions are not manual version');
check(substr($lines[1],51,2)==='TS','explicit test marker');
check(substr($lines[2],37,20)==='12345671234567890   ','official BB page11 number vector + alignment');
check($lines[2][57]==='7','wallet17 simple wire code7');
check(substr($lines[2],85,15)==='000000000010001','exact cents');
check($lines[2][117]==='3'&&$lines[2][220]==='3','interest exemption requires bank terms and no protest');
check(substr($lines[4],17,6)==='000004'&&substr($lines[5],23,6)==='000006','trailer counts');
check($a->ourNumber('0000000001')==='12345670000000001','leading zero sequence');
check(Fields::taxId('12.abc.345/01de-35')==='12ABC34501DE35','tax punctuation normalization preserves letters');
check(Fields::taxId('12ABC34501DE35')!==Fields::taxId('12BBC34501DE35'),'no identity collision');
check(Fields::amount(Fields::cents('9999999999999.99'))==='9999999999999.99','maximum amount exact');
foreach(['1','1.001','1e2','1,00','-1.00','01.00','99999999999999.99'] as $bad)rejects(fn()=>Fields::cents($bad),'invalid money');
foreach(['2026-02-29','2026-13-01','0000-01-01','2026-1-01'] as $bad)rejects(fn()=>Fields::date($bad),'invalid date');
foreach(['123A','-123','1e2',' 123'] as $bad)rejects(fn()=>Fields::digits($bad,10),'no numeric stripping');
foreach(['ação','TOO LONG','A\nB','a','',' ',' A','A '] as $bad)rejects(fn()=>Fields::alpha($bad,3),'explicit alpha validation');
rejects(fn()=>(new Record())->put(1,3,'001')->put(3,4,'00'),'overlapping fields');
rejects(fn()=>new Scope(0,2,$scope->accountId,'sandbox','1234567'),'legacy issuer unsupported');
rejects(fn()=>new Scope(1,0,$scope->accountId,'sandbox','1234567'),'connection required');
rejects(fn()=>new Scope(1,2,$scope->accountId,'','1234567'),'environment explicit');
rejects(fn()=>new Agreement($scope,'12ABC34501DE35','EMPRESA SINTETICA','019','11'),'wallet unsupported');
rejects(fn()=>$g->generate($a,1,'2026-10-18',[$title,$title]),'duplicate original title');
foreach(['amount'=>'0.00','species'=>'99','due_date'=>'2026-09-01','issue_date'=>'2026-10-19','payer_zip'=>'0000000A',
 'payer_state'=>'XX','payer_name'=>str_repeat('A',38),'payer_district'=>str_repeat('A',13)] as $key=>$value){$bad=$title;$bad[$key]=$value;rejects(fn()=>$g->generate($a,1,'2026-10-18',[$bad]),'title validation '.$key);}
$bad=$title;$bad['interest']='2.00';rejects(fn()=>$g->generate($a,1,'2026-10-18',[$bad]),'unsupported financial field not ignored');
$bad=$title;$bad['amount']=100.01;rejects(fn()=>$g->generate($a,1,'2026-10-18',[$bad]),'float refused');
$prod=new Agreement(new Scope(1,2,$scope->accountId,'production','1234567'),$a->taxId,$a->name,'019');
check(substr(explode("\r\n",$g->generate($prod,1,'2026-10-18',[$title]))[1],51,2)==='  ','production codec uses no test flag');
rejects(fn()=>$scope->assertSame($prod->scope),'scope isolation');
// Synthetic return is constructed from manual positions independently of the generator's detail records.
function returnBytes(Agreement $a,array $remittance,string $code='06',string $paid='000000000010001',string $op='T'):string{
 $h=substr_replace($remittance[0],'2',142,1);$l=substr_replace($remittance[1],$op,8,1);
 $t=(new Record())->put(1,17,'0010001300001T '.$code)->put(18,22,$a->branch)->put(23,23,$a->branchDigit)
 ->put(24,35,$a->account)->put(36,36,$a->accountDigit)->put(38,57,'12345671234567890   ')->put(58,58,'7')
 ->put(59,73,str_pad('DOC0001',15))->put(74,81,'01112026')->put(82,96,'000000000010001')->put(106,130,str_pad('ORIGIN0001',25))
 ->put(131,132,'09')->put(199,213,'000000000000001')->put(214,223,'61        ');
 $u=(new Record())->put(1,17,'0010001300002U '.$code)->put(18,77,str_repeat('0',60))->put(78,92,$paid)
 ->put(93,107,'000000000010000')->put(108,137,str_repeat('0',30))->put(138,145,'18102026')->put(146,153,'19102026');
 return implode("\r\n",[$h,$l,(string)$t,(string)$u,$remittance[4],$remittance[5]])."\r\n";
}
$p=new Cnab240ReturnParser();$ret=returnBytes($a,$lines);$result=$p->parse($a,$ret);$e=$result['events'][0];
check($e['paid_amount']==='100.01'&&$e['net_amount']==='100.00','paid differs from net');
check($e['occurrence_date']==='2026-10-18'&&$e['credit_date']==='2026-10-19','occurrence differs from credit');
check($e['financial_effect']==='not_applied'&&$e['requires_review']===true,'no implicit settlement');
check($e['observation']==='settlement_reported','06 is observation');
check($p->parse($a,$ret)['events'][0]['event_key']===$e['event_key'],'deterministic idempotency');
foreach(['93'=>'operational_down_reported','50'=>'cheque_pending_clearance','44'=>'cheque_returned','09'=>'removal_reported','02'=>'registration_reported','99'=>'unmapped_occurrence'] as $code=>$label){
 $event=$p->parse($a,returnBytes($a,$lines,(string)$code))['events'][0];check($event['observation']===$label,'distinct occurrence '.$code);check($event['financial_effect']==='not_applied','no payment '.$code);
}
check($p->parse($a,returnBytes($a,$lines,'06','000000000000000'))['events'][0]['paid_amount']==='0.00','zero paid never replaced with nominal');
check($p->parse($a,returnBytes($a,$lines,'06','000000000010001','P'))['events'][0]['observation']==='operational_down_reported','operation P reviewed');
foreach([str_replace("\r\n","\n",$ret),"\xEF\xBB\xBF".$ret,substr($ret,0,-2),str_replace('PAGADOR','PAGADÓR',$ret)."x",str_replace('00100013','23700013',$ret)] as $bad)rejects(fn()=>$p->parse($a,$bad),'invalid bytes/encoding/bank');
function mutateReturn(string $ret,int $row,int $pos,string $value):string{$l=explode("\r\n",rtrim($ret,"\r\n"));$l[$row]=substr_replace($l[$row],$value,$pos-1,strlen($value));return implode("\r\n",$l)."\r\n";}
foreach([[0,164,'110'],[1,14,'060'],[1,34,'999999999'],[1,52,'  '],[2,18,'00999'],[2,38,'9999999'],[2,58,'4'],[2,106,str_repeat(' ',25)],
 [2,131,'ZZ'],[2,131,'02'],[2,131,'00'],[3,108,'X'],[3,123,'X'],[3,9,'00003'],[3,14,'T'],[3,16,'09'],[3,78,'A'],[3,138,'31022026'],[4,18,'000005'],[5,18,'000002'],[5,24,'000007']] as [$row,$pos,$v]){
 rejects(fn()=>$p->parse($a,mutateReturn($ret,$row,$pos,$v)),'strict control '.$row.':'.$pos);
}
rejects(fn()=>$p->parse($prod,$ret),'cross environment file');
// Identical bank event in a new file retains semantic key, while file hash changes.
$other=$p->parse($a,mutateReturn($ret,0,158,'000002'));
check($other['file_hash']!==$result['file_hash']&&$other['events'][0]['event_key']===$e['event_key'],'repackaged event dedup');
foreach(['85','86'] as $code)rejects(fn()=>$p->parse($a,returnBytes($a,$lines,$code)),'overloaded agent field unsupported');
foreach(['document','control','payer_name','payer_address','payer_district','payer_city'] as $key){$bad=$title;$bad[$key]=' ';rejects(fn()=>$g->generate($a,1,'2026-10-18',[$bad]),'mandatory field not blank '.$key);}
$bad=$title;$bad['sequence']='1234567891';$bad['control']=' ORIGIN0001';rejects(fn()=>$g->generate($a,1,'2026-10-18',[$title,$bad]),'no control padding collision');
$extras=$p->parse($a,mutateReturn(mutateReturn($ret,3,108,'000000000000123'),3,123,'000000000000456'))['events'][0];
check($extras['currency']==='BRL'&&$extras['other_expenses']==='1.23'&&$extras['other_credits']==='4.56','currency and financial extras retained exactly');
echo "OK {$n} Banco do Brasil CNAB240 checks\n";
