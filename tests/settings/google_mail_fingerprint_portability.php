<?php
/** PDO typed integers and native CI/mysqli-style numeric strings must describe the same own account. */
require __DIR__.'/google_mail_oauth.php';$portabilityStart=$GLOBALS['essentialChecks'];
final class GoogleNumericStringResult{public function __construct(private$inner){}public function result_array():array{return array_map(fn($row)=>array_map(fn($v)=>is_int($v)||is_float($v)?(string)$v:$v,$row),$this->inner->result_array());}public function row(){return($this->result_array()[0]??null)!==null?(object)$this->result_array()[0]:null;}}
final class GoogleNumericStringDb{public PDO$pdo;public function __construct(private$inner){$this->pdo=$inner->pdo;}public function __call($name,$args){return$this->inner->$name(...$args);}public function query($sql,$args=[]){$result=$this->inner->query($sql,$args);return$result===false?false:new GoogleNumericStringResult($result);}}
$strings=new CompanyGoogleMailOAuth(new GoogleNumericStringDb($db),$p);
foreach([$primary,41,82]as$issuer)essentialCheck($oauth->fingerprint($issuer)===$strings->fingerprint($issuer),'same canonical Google configuration fingerprint across PDO/mysqli numeric representation for own issuer '.$issuer);
echo 'Google fingerprint driver portability: '.($GLOBALS['essentialChecks']-$portabilityStart).' added checks ('.$db->driver().'). No external traffic.'.PHP_EOL;
