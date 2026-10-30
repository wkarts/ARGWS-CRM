<?php
declare(strict_types=1);
define('APPPATH',dirname(__DIR__).'/application/');
require __DIR__.'/../modules/asaas/libraries/Asaas/WebhookTokenSchema.php';
class TokenSchemaResult {public function __construct(private PDOStatement $statement){}public function row_array(){return $this->statement->fetch(PDO::FETCH_ASSOC)?:[];}public function result_array(){return $this->statement->fetchAll(PDO::FETCH_ASSOC);}}
class TokenSchemaDb {
 public bool $db_debug=true,$save_queries=true;public array $data_cache=[];
 public function __construct(public PDO $pdo){}
 public function query($sql,$params=[]){$statement=$this->pdo->prepare($sql);$statement->execute($params);return new TokenSchemaResult($statement);}
 public function table_exists($table){return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?(bool)$this->query('SELECT name FROM sqlite_master WHERE type=? AND name=?',['table',$table])->row_array():(bool)$this->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->row_array();}
 public function field_exists($name,$table){return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?in_array($name,array_column($this->query('PRAGMA table_info(`'.$table.'`)')->result_array(),'name'),true):(bool)$this->query('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',[$table,$name])->row_array();}
}
$pdo=new PDO(getenv('ASAAS_TEST_MYSQL_DSN')?:'sqlite::memory:',getenv('ASAAS_TEST_MYSQL_USER')?:null,getenv('ASAAS_TEST_MYSQL_PASSWORD')?:null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$db=new TokenSchemaDb($pdo);$prefix='token_'.bin2hex(random_bytes(6)).'_';$mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';$checks=0;
function tokenCheck($condition,$label){global $checks;if(!$condition)throw new RuntimeException($label);$checks++;}
try{
 foreach(['asaas_webhook_integrations','asaas_company_accounts'] as $suffix){$table=$prefix.$suffix;$db->query('CREATE TABLE `'.$table.'` (id int PRIMARY KEY,token_hash char(64) NULL)'.($mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':''));$db->query('INSERT INTO `'.$table.'` (id,token_hash) VALUES(1,?)',[str_repeat('a',64)]);}
 \Asaas\WebhookTokenSchema::ensure($db,$prefix);\Asaas\WebhookTokenSchema::ensure($db,$prefix);\Asaas\WebhookTokenSchema::ensure($db,$prefix,false);
 foreach(['asaas_webhook_integrations','asaas_company_accounts'] as $suffix){$row=$db->query('SELECT * FROM `'.$prefix.$suffix.'`')->row_array();tokenCheck($row['token_cipher']===null&&$row['token_hash']===str_repeat('a',64),'migration never invents recoverable tokens or changes legacy digest');$db->query('UPDATE `'.$prefix.$suffix.'` SET token_cipher=? WHERE id=1',['synthetic-ciphertext']);}
 \Asaas\WebhookTokenSchema::ensure($db,$prefix);
 foreach(['asaas_webhook_integrations','asaas_company_accounts'] as $suffix){tokenCheck($db->query('SELECT token_cipher FROM `'.$prefix.$suffix.'` WHERE id=1')->row_array()['token_cipher']==='synthetic-ciphertext','schema replay preserves existing ciphertext');}
 tokenCheck($db->db_debug&&$db->save_queries,'schema query boundary restores debug/log configuration');
 echo 'Webhook token additive schema: '.$checks.' checks passed ('.$pdo->getAttribute(PDO::ATTR_DRIVER_NAME).'). No token generation or data backfill.'.PHP_EOL;
}finally{foreach(['asaas_webhook_integrations','asaas_company_accounts'] as $suffix)$db->query('DROP TABLE IF EXISTS `'.$prefix.$suffix.'`');}
