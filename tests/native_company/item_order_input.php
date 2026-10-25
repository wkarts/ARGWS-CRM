<?php
require_once dirname(__DIR__,2).'/application/services/NativeCompanyItemOrderEndpoints.php';
function orderCheck($ok){if(!$ok)throw new RuntimeException('order test failed');}
orderCheck(NativeCompanyItemOrderEndpoints::orderedIds([['73','1'],[71,2]])===[73,71]);
foreach([[],[[1,1],[1,2]],[[1,2]],[[1,1],[2,3]],[[1,'01']],[[1,1,'extra']],['bad'],[[1,1.0]],[[0,1]],array_fill(0,1001,[1,1])]as$data){$denied=false;try{NativeCompanyItemOrderEndpoints::orderedIds($data);}catch(DomainException$e){$denied=true;}orderCheck($denied);}
echo "Native item order input 11 checks PASS\n";
