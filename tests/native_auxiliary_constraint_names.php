<?php
require dirname(__DIR__).'/application/services/NativeCompanyAuxiliarySchema.php';
require dirname(__DIR__).'/application/services/NativeCompanyTaskChecklistSchema.php';
require dirname(__DIR__).'/application/services/NativeCompanyTicketCatalogSchema.php';
$all=[];$count=0;
foreach(['tbl','essential_0123456789_','essential_9876543210_']as$p){
 foreach((NativeCompanyAuxiliarySchema::definitions($p)+NativeCompanyTaskChecklistSchema::definitions($p)+NativeCompanyTicketCatalogSchema::definitions($p))as$table=>$parts){
  foreach($parts as$part)if(str_contains($part,'FOREIGN KEY')){
   if(!preg_match('/^CONSTRAINT `([A-Za-z0-9_]{1,64})` FOREIGN KEY /D',$part,$m))throw new RuntimeException('Unbounded or implicit FK');
   if(isset($all[$m[1]]))throw new RuntimeException('Constraint collision');$all[$m[1]]=true;$count++;
  }
 }
 if(NativeCompanyAuxiliarySchema::definitions($p)!==NativeCompanyAuxiliarySchema::definitions($p))throw new RuntimeException('Unstable names');
}
if(strlen('essential_0123456789_company_announcement_contact_dismissals_ibfk_1')<=64)throw new RuntimeException('Missing original boundary reproduction');
echo "Auxiliary FK names: {$count} deterministic distinct bounded edges; real MySQL validation remains required.\n";
// Census every 474 helper, not only the three previously failing table families.
$root=dirname(__DIR__);$migration=file_get_contents($root.'/application/migrations/474_version_474.php');
preg_match_all("/'([A-Za-z][A-Za-z0-9]*(?:Schema|Fence))'/",$migration,$found);$helpers=array_values(array_unique($found[1]));$resolved=0;$literal=0;
foreach($helpers as$class){
 $path=$root.'/application/services/'.$class.'.php';if(!is_file($path))throw new RuntimeException('Missing migration helper');
 require_once $path;$source=file_get_contents($path);
 preg_match_all('/(?:CONSTRAINT|(?:UNIQUE )?KEY|INDEX) `([A-Za-z0-9_]+)`/',$source,$names);
 foreach($names[1]as$name){if(strlen($name)>64)throw new RuntimeException('Literal constraint/index exceeds MySQL limit');$literal++;}
 preg_match_all("/['\"](company_[a-z0-9_]+)['\"]/",$source,$tableNames);
 foreach($tableNames[1]as$table)if(strlen('essential_0123456789_'.$table)>64)throw new RuntimeException('Owned table exceeds MySQL limit');
 if(!method_exists($class,'definitions'))continue;$method=new ReflectionMethod($class,'definitions');if(!$method->isPublic()||!$method->isStatic()||$method->getNumberOfRequiredParameters()>1)continue;
 foreach(['tbl','essential_0123456789_','essential_9876543210_']as$prefix){
  $definitions=$class::definitions($prefix);if(!is_array($definitions)||$definitions!==$class::definitions($prefix))throw new RuntimeException('Unstable DDL definitions');
  foreach($definitions as$table=>$parts){
   if(!is_string($table)||(!is_array($parts)&&!is_string($parts)))throw new RuntimeException('Unexpected DDL definition shape: '.$class);
   $sql=is_array($parts)?implode(',', $parts):$parts;
   preg_match_all('/(?:CONSTRAINT\s+`?([A-Za-z0-9_]+)`?\s+)?FOREIGN\s+KEY\s*\(/i',$sql,$foreign,PREG_SET_ORDER);
   foreach($foreign as$index=>$edge){$name=($edge[1]??'')!==''?$edge[1]:$prefix.$table.'_ibfk_'.($index+1);if(strlen($name)>64)throw new RuntimeException('Implicit or explicit FK exceeds MySQL limit: '.$class);}
   preg_match_all('/(?:UNIQUE\s+)?(?:KEY|INDEX)\s+`?([A-Za-z0-9_]+)`?\s*\(/i',$sql,$indexes);
   foreach($indexes[1]as$name)if(strlen($name)>64)throw new RuntimeException('Resolved index exceeds MySQL limit');
  }
 }
 $resolved++;
}
echo count($helpers)." migration helpers audited; {$resolved} actual definition builders at three prefixes; {$literal} literal index/constraint identifiers checked.\n";
