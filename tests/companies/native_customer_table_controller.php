<?php
/** Original DataTables controller with real company-scoped reader and SQLite/MySQL storage. */
require __DIR__.'/native_clients_controller.php';
$controller->input->method='POST';$controller->input->post=['draw'=>'3','start'=>'0','length'=>'25','search'=>['value'=>'Native'],'order'=>[['column'=>'2','dir'=>'asc']]];
CompanyRequestScope::$issuer=41;$controller->table();$table=json_decode($controller->output->body,true,512,JSON_THROW_ON_ERROR);
essentialCheck($table['draw']===3&&count($table['data'])===1&&str_contains($table['data'][0][2],'Native A edited'),'native customer table returns the owned row');
essentialCheck(!str_contains($controller->output->body,'Native company B'),'customer table excludes foreign company rows');
CompanyRequestScope::$issuer=82;$controller->table();$table=json_decode($controller->output->body,true,512,JSON_THROW_ON_ERROR);
essentialCheck(count($table['data'])===2,'other company sees its own row and explicitly shared identity');
$shared=array_values(array_filter($table['data'],fn($row)=>str_contains($row[2],'Native A edited')))[0];
essentialCheck($shared[3]===''&&$shared[4]===''&&$shared[5]===''&&$shared[8]==='','shared identity exposes no other company private contact or profile details');
$controller->input->method='GET';try{$controller->table();throw new RuntimeException('GET table accepted');}catch(NativeClientHttpError$e){essentialCheck($e->getCode()===405,'native customer table accepts only POST');}
CompanyRequestScope::$issuer=41;
echo "Native customer DataTables controller isolation passed.\n";
