<?php
/** Synthetic0/7/9 source copied byte-for-byte from old banco_brasil_cnab400.php functions; pure wire only, no suites executed. */
use BancoBrasil\{Agreement,Scope,Fields400,Record400};
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
/** Synthetic records only; requires the unchanged old400 fixture for its original0/7/9 bytes. */
function bb400AuxSequences(array$lines):string{foreach($lines as$i=>&$line)$line=substr_replace($line,str_pad((string)($i+1),6,'0',STR_PAD_LEFT),394,6);unset($line);return implode("\r\n",$lines)."\r\n";}
function bb400AuxRecord(string$service,array$fields=[]):string{
 $line='5'.$service.str_repeat(' ',397);
 if($service==='01'){$raw=$fields['email']??'<script>synthetic</script>@example.invalid';if(strlen($raw)>137)throw new LogicException('fixture email too long');$line=substr_replace($line,'00',3,2);$line=substr_replace($line,str_pad($raw,137),5,137);}
 elseif($service==='10'){$key=$fields['key']??'http://127.0.0.1:9/private?<script>synthetic</script>';$txid=$fields['txid']??'SYNTHETIC-TXID';if(strlen($key)>77||strlen($txid)>35)throw new LogicException('fixture field too long');$line=substr_replace($line,$fields['occurrence']??'12',3,2);$line=substr_replace($line,str_pad($key,77),140,77);$line=substr_replace($line,str_pad($txid,35),217,35);}
 return$line;
}
function bb400AuxWire(BancoBrasil\Agreement$a,array$services=['01','10'],array$fields=[],array$parents=[1]):string{
 $old=explode("\r\n",substr(bb400Fixture($a),0,-2));$lines=[$old[0]];
 foreach($parents as$n){$main=substr_replace($old[1],$a->ourNumber((string)$n),63,17);$main=substr_replace($main,str_pad('SYNTHETIC'.$n,25),38,25);$lines[]=$main;foreach($services as$service)$lines[]=bb400AuxRecord($service,$fields);}
 $trailer=substr_replace($old[2],str_pad((string)count($parents),8,'0',STR_PAD_LEFT),17,8);$lines[]=$trailer;return bb400AuxSequences($lines);
}
