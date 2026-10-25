<?php
/** Pure contracts only: synthetic encryption, no credentials/provider/database. */
declare(strict_types=1);
define('BASEPATH',__DIR__.'/');define('APPPATH',dirname(__DIR__,2).'/application/');
require dirname(__DIR__,2).'/modules/gerencianet_gateway/services/CompanyAccounts.php';
function check(bool $value,string $name):void{if(!$value)throw new RuntimeException($name);}
function deny(callable $run,string $name):void{try{$run();}catch(Throwable $e){return;}throw new RuntimeException($name);}
$cipher=new class {function encrypt($v){return 'synthetic:'.base64_encode($v);}function decrypt($v){return str_starts_with($v,'synthetic:')?base64_decode(substr($v,10),true):false;}};
$row=['issuer_id'=>2,'environment'=>'sandbox','account_hash'=>hash('sha256','fake_client'),'certificate_cipher'=>null];
$envelope=['issuer_id'=>2,'environment'=>'sandbox','account_hash'=>$row['account_hash'],'client_id'=>'fake_client','client_secret'=>'fake_secret','pix_key'=>'fake_key','certificate_hash'=>hash('sha256','')];
$row['credentials_cipher']=$cipher->encrypt(json_encode($envelope));
check(Efi\CompanyAccounts::credentials($row,$cipher)===['client_id'=>'fake_client','client_secret'=>'fake_secret','pix_key'=>'fake_key'],'only scoped credentials leave envelope');
foreach(['issuer_id'=>3,'environment'=>'production','account_hash'=>str_repeat('a',64),'certificate_cipher'=>'foreign cipher'] as $key=>$value){$other=$row;$other[$key]=$value;deny(fn()=>Efi\CompanyAccounts::credentials($other,$cipher),'swapped '.$key.' rejected');}
$other=$row;$other['credentials_cipher']='invalid';deny(fn()=>Efi\CompanyAccounts::credentials($other,$cipher),'invalid cipher rejected');
$envelope['unexpected']='field';$other=$row;$other['credentials_cipher']=$cipher->encrypt(json_encode($envelope));deny(fn()=>Efi\CompanyAccounts::credentials($other,$cipher),'unexpected envelope field rejected');
$db=new class {function query($sql,$args=[]){return new class($sql){function __construct(private string $sql){}function result_array(){return str_contains($this->sql,'FROM tblmodules')?[['active'=>1,'installed_version'=>'5.1.5']]:[];}};}};
$spy=new class {public int $decrypts=0,$encrypts=0;function decrypt($v){++$this->decrypts;throw new RuntimeException('must not decrypt');}function encrypt($v){++$this->encrypts;throw new RuntimeException('must not encrypt');}};
deny(fn()=>(new Efi\CompanyAccounts($db,'tbl'))->save(2,99,'sandbox','new','fake','fake','fake','fake certificate',$spy),'unauthorized config rejected');
check($spy->decrypts===0&&$spy->encrypts===0,'unauthorized actor cannot process certificate or credentials');
$ddl=Efi\CompanySchema::statements('synthetic_');check(count($ddl)===4,'four tables');
check(str_contains($ddl['origins'],'UNIQUE KEY `invoice_origin` (`invoice_id`)'),'single intent across methods');
check(str_contains($ddl['accounts'],'UNIQUE KEY `account_environment` (`account_hash`,`environment`)'),'shared identity cannot cross companies');
deny(fn()=>Efi\CompanySchema::statements('bad-prefix'),'SQL prefix denied');
$seed=file_get_contents(dirname(__DIR__,2).'/install/database.sql');
foreach(Efi\CompanySchema::statements('tbl') as $statement)check(substr_count($seed,str_replace('CREATE TABLE IF NOT EXISTS','CREATE TABLE',$statement).';')===1,'actual PHP DDL equals fresh-install seed');

$metadata=new class {
 public string $bad='';
 function query($sql,$args=[]){
  $rows=[];
  if(str_starts_with($sql,'SELECT ENGINE'))$rows=[['ENGINE'=>$this->bad==='engine'?'MyISAM':'InnoDB','TABLE_COLLATION'=>'utf8mb4_bin']];
  elseif(preg_match('/(?:SHOW FULL COLUMNS|SHOW INDEX) FROM `synthetic_efi_company_(\w+)`/',$sql,$match)){
   $suffix=$match[1];
   if(str_starts_with($sql,'SHOW FULL COLUMNS'))foreach(Efi\CompanySchema::FIELDS[$suffix] as $field=>$type){$nullable=str_ends_with($type,'?');$type=rtrim($type,'?');$rows[]=['Field'=>$field,'Type'=>$type,'Null'=>$nullable?'YES':'NO','Collation'=>preg_match('/char|text/',$type)?'utf8mb4_bin':null,'Default'=>null,'Extra'=>$field==='id'?'auto_increment':''];}
   else foreach(Efi\CompanySchema::KEYS[$suffix] as $name=>$columns)foreach($columns as $position=>$field)$rows[]=['Non_unique'=>0,'Sub_part'=>null,'Index_type'=>'BTREE','Key_name'=>$name,'Seq_in_index'=>$position+1,'Column_name'=>$field];
   if($this->bad==='extra'&&str_starts_with($sql,'SHOW FULL COLUMNS'))$rows[0]['Extra']='NULL';
   if($this->bad==='default'&&str_starts_with($sql,'SHOW FULL COLUMNS'))$rows[0]['Default']="'NULL'";
   if($this->bad==='index'&&str_starts_with($sql,'SHOW INDEX'))array_pop($rows);
  }else throw new RuntimeException('Unexpected metadata query');
  return new class($rows){function __construct(private array $rows){}function row_array(){return $this->rows[0]??[];}function result_array(){return $this->rows;}};
 }
};
Efi\CompanySchema::ensure($metadata,'synthetic_',false);
foreach(['engine','extra','default','index'] as $bad){$metadata->bad=$bad;deny(fn()=>Efi\CompanySchema::ensure($metadata,'synthetic_',false),'invalid metadata '.$bad.' denied');}
echo "Efí scoped account pure contracts PASS; database and provider not executed.\n";
