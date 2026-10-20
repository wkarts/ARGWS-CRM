<?php
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
