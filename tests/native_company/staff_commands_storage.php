<?php
require __DIR__.'/staff_read.php';
CompanyStaffSchema::ensure($db,$p);CompanyStaffSchema::ensure($db,$p);
$service=new CompanyStaff($db,$p);$before=$service->get(41,7,14);$id=$service->update(41,7,14,$before['revision'],['firstname'=>'Native updated'],['tasks'=>['view']],false,hash('sha256','synthetic-update'));
essentialCheck($id===14&&$service->get(41,7,14)['firstname']==='Native updated','existing CompanyStaff writer executes against actual SQLite owned transaction');
settingThrows(fn()=>$service->update(82,7,14,$before['revision'],['firstname'=>'Foreign'],[],false,hash('sha256','synthetic-foreign')),'issuer_staff_denied','foreign update never changes shared identity');
echo "Native staff command schema replay and real existing writer passed.\n";
