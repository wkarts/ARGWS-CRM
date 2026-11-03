<?php
$domain=getenv('NATIVE_RELATION_SALES')?:'estimate';
if($domain==='credit_note')require __DIR__.'/../companies/credits_operations.php';else require __DIR__.'/../companies/sales.php';
$domain=getenv('NATIVE_RELATION_SALES')?:'estimate';
require_once APPPATH.'services/NativeCompanyRelationSales.php';
$r=new NativeCompanyRelationSales($db,$p);$n=0;
$check=function($ok,$message)use(&$n){essentialCheck($ok,$message);++$n;};
$list=$r->options($a,7,$domain);$check(count($list)>0,'native sales relation lists authorized real documents');
$first=$list[0];$check($r->options($a,7,$domain,'',$first['id'])===[$first],'selected relation retains canonical identity and exact native label');
$check($r->options($a,7,$domain,$first['name'])!==[],'formatted native number is searchable');
$check($r->options($a,7,$domain,'%_')===[],'search treats wildcard characters literally');
try{$r->options($b,7,$domain,'',$first['id']);throw new RuntimeException('foreign relation accepted');}catch(DomainException $e){++$n;}
$before=$db->query('SELECT COUNT(*) n FROM '.$p.'issuers_audit')->row()->n;$r->options($a,7,$domain);$check($before===$db->query('SELECT COUNT(*) n FROM '.$p.'issuers_audit')->row()->n,'relation read creates no audit or financial mutation');
try{$r->options($a,7,$domain,"bad\nquery");throw new RuntimeException('control accepted');}catch(DomainException $e){++$n;}
echo "Native sales relations {$domain}: {$n} checks passed ({$db->driver()}).\n";
// Real native historical table shapes. These are disposable fixture records, never an adoption.
$primary=(new IssuerResourceAccess($db,$p))->primaryId();
foreach(['number_padding_prefixes'=>'5','proposal_number_prefix'=>'PRO-']as$name=>$value){$db->query('DELETE FROM '.$p.'options WHERE name=?',[$name]);$db->query('INSERT INTO '.$p.'options(name,value) VALUES(?,?)',[$name,$value]);}
$historical=990001;$db->query("INSERT INTO {$p}clients(userid,company,datecreated,addedfrom,active) VALUES(?,'Historical relation recipient','2020-01-01',7,1)",[$historical]);
$table=['credit_note'=>'creditnotes','estimate'=>'estimates','proposal'=>'proposals'][$domain];
if($domain==='credit_note'){$legacyId=4200;$db->query("UPDATE {$p}creditnotes SET clientid=?,prefix='CR-',number_format=2,number=999,date='2026-10-19' WHERE id=?",[$historical,$legacyId]);$expected='CR-2026/00999';}
else{
 preg_match('/CREATE TABLE `tbl'.preg_quote($table,'/').'` \(.*?\) ENGINE=InnoDB[^;]*;/s',$native,$m);if(!$m)throw new RuntimeException('native fixture missing '.$table);
 $sql=str_replace('`tbl'.$table.'`','`'.$p.$table.'`',$m[0]);if($db->driver()==='sqlite'){$sql=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$sql);$sql=preg_replace('/enum\([^)]*\)/','varchar(50)',$sql);}$db->query($sql);$legacyId=999001;
 if($domain==='estimate'){$db->query("INSERT INTO {$p}estimates(id,addedfrom,clientid,number,prefix,number_format,datecreated,date,currency,subtotal,total,discount_type,include_shipping) VALUES(?,7,?,999,'EST-',3,?,'2026-10-19',1,7,7,'after_tax',0)",[$legacyId,$historical,date('Y-m-d H:i:s')]);$expected='EST-00999-26';}
 else{$db->query("CREATE TABLE {$p}leads(id INT PRIMARY KEY,client_id INT NULL,issuer_id INT NULL)");$db->query("INSERT INTO {$p}proposals(id,datecreated,addedfrom,subject,content,date,rel_type,rel_id,proposal_to,currency,total,subtotal,discount_percent,discount_total,hash,status) VALUES(?,'2026-10-19 10:00:00',7,'Historical proposal','Body','2026-10-19','customer',?,'Historical relation recipient',1,7,7,0,0,'11111111111111111111111111111111',1)",[$legacyId,$historical]);$expected='PRO-999001';}
}
$check($r->options($primary,7,$domain,'',$legacyId)[0]['name']===$expected,'principal preserves the actual historical native number format');
try{$r->options($a,7,$domain,'',$legacyId);throw new RuntimeException('secondary read historical');}catch(DomainException $e){++$n;}
$field=$domain==='proposal'?'rel_id':'clientid';$db->query('UPDATE '.$p.$table.' SET '.$field.'=? WHERE id=?',[$ca,$legacyId]);
try{$r->options($primary,7,$domain,'',$legacyId);throw new RuntimeException('historical row followed named customer');}catch(DomainException $e){++$n;}
echo "Native sales relation historical {$domain}: {$n} total checks passed.\n";
if(!class_exists('CompanyRequestScope',false)){
final class CompanyRequestScope{public static int $issuer;public static bool $allow=true;public static function assertNativeMutation($ci):void{if(!self::$allow)throw new DomainException('native_post_csrf_denied');}public static function context():object{return new class{public function issuerId():int{return CompanyRequestScope::$issuer;}public function staffId():int{return 7;}};}}
}else{throw new RuntimeException('fixture scope collision');}
CompanyRequestScope::$issuer=$a;require_once APPPATH.'services/NativeCompanyRelationEndpoints.php';
$ci=new stdClass();$ci->db=$db;$ci->input=new class{public array $data=[];public function post($k){return$this->data[$k]??null;}};$ci->input->data=['type'=>$domain,'rel_id'=>$first['id']];
$check(NativeCompanyRelationEndpoints::read($ci)===[$first],'original Misc payload dispatches concrete sales reader');
CompanyRequestScope::$allow=false;essentialThrows(fn()=>NativeCompanyRelationEndpoints::read($ci),'native_post_csrf_denied','boundary rejects before concrete reader');++$n;
echo "Native sales endpoint {$domain}: {$n} total checks passed; transport boundary double, real reader/storage.\n";
