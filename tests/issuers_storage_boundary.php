<?php
require __DIR__.'/../application/services/issuers/IssuerResourceAccess.php';
require __DIR__.'/../application/services/issuers/IssuerRegistry.php';
class BoundaryDb {
 public bool $db_debug=true; public bool $save_queries=true; public bool $fail=false; public array $seen=[];
 function query($sql,$params=[]){$this->seen[]=[$this->db_debug,$this->save_queries];if($this->db_debug||$this->save_queries)throw new LogicException('unredacted query');if($this->fail)throw new RuntimeException('SQL secret synthetic');return new class{function result_array(){return [['staffid'=>17,'admin'=>1,'active'=>1]];}};}
 function field_exists($field,$table){$this->seen[]=[$this->db_debug,$this->save_queries];if($this->db_debug||$this->save_queries)throw new LogicException('unredacted metadata');return true;}
}
$n=0;function boundaryCheck($yes,$label){global$n;$n++;if(!$yes)throw new RuntimeException($label);}
$db=new BoundaryDb();$acl=new IssuerResourceAccess($db,'synthetic_');boundaryCheck($acl->isMaster(17),'master read succeeded');boundaryCheck($db->db_debug&&$db->save_queries,'read flags restored');
(new IssuerRegistry($db,'synthetic_'))->requireAdmin(17);boundaryCheck($db->db_debug&&$db->save_queries,'registry reads flags restored');
$db->fail=true;try{$acl->isMaster(17);throw new LogicException('failure absent');}catch(RuntimeException $error){boundaryCheck($error->getMessage()==='issuer_storage_failed','storage error excludes SQL');boundaryCheck($error->getPrevious()===null,'no previous exception exposes SQL');}
boundaryCheck($db->db_debug&&$db->save_queries,'failure restores flags');foreach($db->seen as$flags)boundaryCheck($flags===[false,false],'every actual query/metadata call redacted');
echo "Issuer storage boundary: $n checks passed.\n";
