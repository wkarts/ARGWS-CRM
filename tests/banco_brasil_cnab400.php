<?php
declare(strict_types=1);
require_once __DIR__.'/../modules/banco_brasil/libraries/BancoBrasil/Cnab400Generator.php';
require_once __DIR__.'/../modules/banco_brasil/libraries/BancoBrasil/Cnab400ReturnParser.php';
use BancoBrasil\{Agreement,Scope,Fields,Fields400,Record400,LayoutCatalog,Cnab400Generator,Cnab400ReturnParser};
if(!function_exists('check')){function check($v,$m){global $n;$n++;if(!$v)throw new RuntimeException($m);}function rejects($fn,$m){$ok=false;try{$fn();}catch(Throwable $e){$ok=true;}check($ok,$m);}}
function bb400Fixture(Agreement $a,string $command='06',string $nature='01'):string
{
    [$b,$bd,$c,$cd]=Fields400::account($a);
    $h=(new Record400())->put(1,19,'02RETORNO01COBRANCA')->put(27,30,$b)->put(31,31,$bd)->put(32,39,$c)->put(40,40,$cd)
        ->put(41,46,'000000')->put(47,76,str_pad('EMPRESA SINTETICA',30))->put(77,94,str_pad('001BANCODOBRASIL',18))
        ->put(95,100,'181026')->put(101,107,'0000001')->put(150,156,$a->scope->agreement)->put(395,400,'000001');
    $d=(new Record400())->put(1,17,'7'.str_repeat('0',16))->put(18,21,$b)->put(22,22,$bd)->put(23,30,$c)->put(31,31,$cd)
        ->put(32,38,$a->scope->agreement)->put(39,63,str_pad('SYNTHETIC1',25))->put(64,80,$a->ourNumber('1'))->put(81,86,'700000')
        ->put(87,88,$nature)->put(92,94,$a->variation)->put(95,105,str_repeat('0',11))->put(107,108,'17')->put(109,110,$command)
        ->put(111,116,'171026')->put(117,126,str_pad('DOC1',10))->put(147,152,'161026')->put(153,165,'0000000020000')
        ->put(166,172,'0010452')->put(173,173,'3')->put(174,175,'01')->put(176,181,in_array($command,['06','07','08','15'],true)?'191026':'      ')
        ->put(182,188,'0000050')->put(189,253,str_repeat('0',65))->put(254,266,'0000000010000')
        ->put(267,305,str_repeat('0',39))->put(306,318,'0000000009950')->put(319,332,'20'.str_repeat('0',12))
        ->put(343,390,str_repeat('0',48))->put(391,391,'1')->put(393,394,'01')->put(395,400,'000002');
    $t=(new Record400())->put(1,7,'9201001')->put(18,25,'00000001')->put(26,39,'00000000020000')->put(40,47,'00000000')
        ->put(58,87,str_repeat('0',30))->put(98,127,str_repeat('0',30))->put(138,167,str_repeat('0',30))
        ->put(218,247,str_repeat('0',30))->put(395,400,'000003');
    return implode("\r\n",[(string)$h,(string)$d,(string)$t])."\r\n";
}
function bb400Mutate(string $wire,int $line,int $position,string $value):string
{
    $lines=explode("\r\n",$wire);$lines[$line]=substr_replace($lines[$line],$value,$position-1,strlen($value));return implode("\r\n",$lines);
}
$n=0;$a400=new Agreement(new Scope(1,1,'001:00452-3:000000123873-X','sandbox','1234567'),'12ABC34501DE35','EMPRESA SINTETICA','019');
$title400=['sequence'=>'1','document'=>'DOC1','control'=>'SYNTHETIC1','issue_date'=>'2026-10-17','due_date'=>'2026-10-20','amount'=>'200.00',
    'species'=>'01','acceptance'=>'N','payer_tax_id'=>'12BBC34501DE35','payer_name'=>'PAGADOR SINTETICO','payer_address'=>'RUA TESTE 1',
    'payer_district'=>'CENTRO','payer_zip'=>'12345678','payer_city'=>'TESTE','payer_state'=>'SP'];
$gen400=new Cnab400Generator();$candidate=$gen400->candidate($a400,1,'2026-10-18',[$title400]);$lines400=explode("\r\n",rtrim($candidate['content'],"\r\n"));
check($candidate['available']===true&&$candidate['configurable']===true&&!$candidate['transmission_allowed']&&!$candidate['publication_blocked']&&$candidate['operational']===false&&$candidate['unresolved']===['interest_exemption_requires_bank_profile','homologation_per_connection'],'candidate remains available/configurable while external gates stay explicit');
check(count($lines400)===3,'0/7/9 only');foreach($lines400 as $i=>$l){check(strlen($l)===400,'400 exact bytes');check(substr($l,394,6)===str_pad((string)($i+1),6,'0',STR_PAD_LEFT),'record sequence');}
check(substr($lines400[0],0,19)==='01TESTE  01COBRANCA','sandbox explicit TESTE');
check(substr($lines400[0],26,14)==='0452300123873X','400 account not 240 widths');
check(substr($lines400[0],46,30)===str_pad('EMPRESA SINTETICA',30),'400 alpha left aligned');
check(substr($lines400[0],94,6)==='181026','DDMMAA');check(substr($lines400[0],129,7)==='1234567','remittance agreement position130');
check(substr($lines400[1],3,14)==='12ABC34501DE35','issuer alphanumeric CNPJ preserved');
check(substr($lines400[1],220,14)==='12BBC34501DE35','payer alphanumeric CNPJ preserved');
check(substr($lines400[1],63,17)==='12345670000000001','7+10 Nosso Numero no DV');
check(substr($lines400[1],84,2)===LayoutCatalog::CNAB400_BRL_CURRENCY_FIELD,'CBR641 BRL profile has no foreign-currency marker');
check(substr($lines400[1],101,5)==='     ','simple collection blank code');
check(substr($lines400[1],126,13)==='0000000020000','exact integer cents');check(substr($lines400[1],156,4)==='0700'&&$lines400[1][393]==='N','no protest and partial explicitly refused');
check(substr($lines400[1],160,13)===LayoutCatalog::CNAB400_DAILY_INTEREST_FIELD,'CBR641 daily-interest field exact and gated by bank profile');
foreach(['amount'=>'100000000000.00','document'=>'LONGERTHAN10','payer_name'=>'SINTÉTICO','control'=>' X','species'=>'19','payer_state'=>'XX'] as $key=>$value){$bad=$title400;$bad[$key]=$value;rejects(fn()=>$gen400->candidate($a400,1,'2026-10-18',[$bad]),'lossy/unsupported title '.$key);}
rejects(fn()=>$gen400->candidate($a400,1,'2026-10-18',[$title400,$title400]),'duplicate remittance');
rejects(fn()=>Fields400::account(new Agreement(new Scope(1,1,'001:10452-3:000000123873-X','sandbox','1234567'),'12345678901','TEST','019')),'no truncation of branch');
rejects(fn()=>Fields400::account(new Agreement(new Scope(1,1,'001:00452-3:000100123873-X','sandbox','1234567'),'12345678901','TEST','019')),'no truncation of account');
check(Fields400::parseDate('010100')==='2000-01-01'&&Fields400::parseDate('311299')==='2099-12-31','explicit century no date pivot');
rejects(fn()=>Fields400::parseDate('290223'),'invalid date');check(Fields400::parseDate('000000',true)===null,'missing date not today');
$parser400=new Cnab400ReturnParser();$wire400=bb400Fixture($a400);$parsed400=$parser400->parse($a400,$wire400);$e400=$parsed400['events'][0];
check($parsed400['records']===3&&count($parsed400['events'])===1,'return parsed');
check($parsed400['environment_evidence']==='not_present_in_file'&&$parsed400['issuer_tax_id_evidence']==='not_present_in_file','no invented account/issuer/environment proof');
check($e400['currency']===null&&$e400['declared_currency']==='BRL','currency declaration distinct from evidence');
check($e400['paid_amount']==='100.00'&&$e400['nominal_amount']==='200.00','partial never replaced by nominal');
check($e400['posting_amount']==='99.50'&&$e400['net_amount']===null&&$e400['posting_direction']==='2','posting not relabelled as net');
check($e400['occurrence_date']==='2026-10-17'&&$e400['credit_date']==='2026-10-19','effective dates separate');
check($e400['financial_effect']==='not_applied'&&$e400['requires_review'],'no financial effect');
check($parsed400['trailer_raw_totals']['simple']['total_raw']==='00000000020000','trailer raw width preserved');
check($parsed400['trailer_total_interpretation']==='unverified_position_picture_disagreement','manual disagreement explicit');
$moneyWire=$wire400;foreach([[182,7,'0000123'],[189,13,'0000000000234'],[202,13,'0000000000345'],[215,13,'0000000000456'],[228,13,'0000000000567'],[241,13,'0000000000678'],[254,13,'0000000000789'],[267,13,'0000000000890'],[280,13,'0000000000901'],[293,13,'0000000001012'],[306,13,'0000000001123']] as [$pos,$width,$value]){$moneyWire=bb400Mutate($moneyWire,1,$pos,$value);}
$money=$parser400->parse($a400,$moneyWire)['events'][0];
check([$money['fees'],$money['other_expenses'],$money['discount_interest'],$money['iof'],$money['abatement'],$money['discount'],$money['paid_amount'],$money['interest'],$money['other_credits'],$money['unused_abatement'],$money['posting_amount']]===['1.23','2.34','3.45','4.56','5.67','6.78','7.89','8.90','9.01','10.12','11.23'],'CBR643 July/2023 monetary positions 182-318 remain distinct');
$zero=$parser400->parse($a400,bb400Mutate($wire400,1,254,str_repeat('0',13)))['events'][0];check($zero['paid_amount']==='0.00','zero paid not nominal fallback');
foreach(['93'=>'operational_down_reported','46'=>'cheque_pending_clearance','44'=>'cheque_returned','07'=>'partial_receipt_reported','20'=>'debit_instruction_reported','50'=>'unmapped_occurrence'] as $code=>$observation){check($parser400->parse($a400,bb400Fixture($a400,(string)$code))['events'][0]['observation']===$observation,'400 code '.$code);}
foreach(['04','10','11'] as $nature)check($parser400->parse($a400,bb400Fixture($a400,'06',$nature))['events'][0]['observation']==='cheque_pending_clearance','cheque nature '.$nature);
foreach(['02','46','93'] as $code)rejects(fn()=>$parser400->parse($a400,bb400Mutate(bb400Fixture($a400,$code),1,176,'191026')),'credit date forbidden for command '.$code);
foreach(['39','85','86'] as $code)rejects(fn()=>$parser400->parse($a400,bb400Fixture($a400,(string)$code)),'overloaded other receipts '.$code);
foreach([[0,27,'9999'],[0,150,'7654321'],[0,395,'000002'],[1,32,'7654321'],[1,64,'76543210000000001'],[1,92,'020'],[1,81,'4'],[1,333,'1'],[1,395,'000003'],[2,395,'000004'],[2,58,'00000001'],[1,111,'311326'],[1,254,'-000000010000']] as [$line,$pos,$value])rejects(fn()=>$parser400->parse($a400,bb400Mutate($wire400,$line,$pos,$value)),'scope/profile/date/amount rejected '.$line.'/'.$pos);
foreach(['2','3','5'] as $kind)rejects(fn()=>$parser400->parse($a400,bb400Mutate($wire400,1,1,$kind)),'auxiliary requires separate decoder '.$kind);
rejects(fn()=>$parser400->parse($a400,str_replace("\r\n","\n",$wire400)),'LF not accepted');
rejects(fn()=>$parser400->parse($a400,"\xEF\xBB\xBF".$wire400),'BOM not accepted');
rejects(fn()=>$parser400->parse($a400,substr($wire400,0,-3)."\r\n"),'short record rejected');
check($parser400->parse($a400,bb400Mutate($wire400,0,101,'0000002'))['events'][0]['event_key']===$e400['event_key'],'repackaged event identity stable');
check(LayoutCatalog::get(LayoutCatalog::CNAB400)['channel']==='cnab400','catalog explicit400');
echo "OK {$n} Banco do Brasil CNAB400 checks\n";
