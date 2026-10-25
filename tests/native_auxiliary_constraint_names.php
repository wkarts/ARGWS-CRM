<?php
require dirname(__DIR__).'/application/services/NativeCompanyAuxiliarySchema.php';
require dirname(__DIR__).'/application/services/NativeCompanyTaskChecklistSchema.php';
$all=[];$count=0;
foreach(['tbl','essential_0123456789_','essential_9876543210_']as$p){
 foreach((NativeCompanyAuxiliarySchema::definitions($p)+NativeCompanyTaskChecklistSchema::definitions($p))as$table=>$parts){
  foreach($parts as$part)if(str_contains($part,'FOREIGN KEY')){
   if(!preg_match('/^CONSTRAINT `([A-Za-z0-9_]{1,64})` FOREIGN KEY /D',$part,$m))throw new RuntimeException('Unbounded or implicit FK');
   if(isset($all[$m[1]]))throw new RuntimeException('Constraint collision');$all[$m[1]]=true;$count++;
  }
 }
 if(NativeCompanyAuxiliarySchema::definitions($p)!==NativeCompanyAuxiliarySchema::definitions($p))throw new RuntimeException('Unstable names');
}
if(strlen('essential_0123456789_company_announcement_contact_dismissals_ibfk_1')<=64)throw new RuntimeException('Missing original boundary reproduction');
echo "Auxiliary FK names: {$count} deterministic distinct bounded edges; real MySQL validation remains required.\n";
