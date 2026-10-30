<?php
declare(strict_types=1);
require __DIR__.'/../../application/services/NativeCompanyTicketRules.php';
$n=0;function checkRule(bool$v,string$label):void{global$n;if(!$v)throw new RuntimeException($label);$n++;}
$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->exec('CREATE TABLE normalized(id INT,subject TEXT,department TEXT,status TEXT,priority TEXT,service TEXT,merged_ticket_id TEXT,assigned INT)');
$ids=[];foreach(['department','status','priority','service']as$k)$ids[$k]=NativeCompanyResourceId::encode('company_ticket_'.$k,1);
$insert=$pdo->prepare('INSERT INTO normalized VALUES(?,?,?,?,?,?,?,?)');$insert->execute([1,'100% own_ ticket',$ids['department'],$ids['status'],$ids['priority'],$ids['service'],null,17]);$insert->execute([2,'Other ticket',$ids['department'],NativeCompanyResourceId::encode('company_ticket_status',2),$ids['priority'],$ids['service'],'99',0]);
$rule=fn($id,$op,$value)=>['id'=>$id,'type'=>NativeCompanyTicketRules::TYPES[$id],'operator'=>$op,'value'=>$value];
$query=function(array$rules,string$match='and')use($pdo){$c=NativeCompanyTicketRules::compile(['match_type'=>$match,'rules'=>$rules],17);$s=$pdo->prepare('SELECT id FROM normalized q'.($c['sql']?' WHERE '.$c['sql']:'').' ORDER BY id');$s->execute($c['params']);return$s->fetchAll(PDO::FETCH_COLUMN);};
checkRule($query([])===[1,2],'empty catalog builder');
foreach(['department','priority','service']as$kind)checkRule($query([$rule($kind,'equal',$ids[$kind])])===[1,2],$kind.' canonical equals');
checkRule($query([$rule('status','in',[$ids['status']])])===[1],'canonical status in');checkRule($query([$rule('status','not_in',[$ids['status']])])===[2],'canonical status not in');
foreach([['contains','%', [1]],['contains','_', [1]],['not_contains','%', [2]],['begins_with','100',[1]],['ends_with','ticket',[1,2]],['equal','Other ticket',[2]],['not_equal','Other ticket',[1]]]as[$op,$v,$want])checkRule($query([$rule('subject',$op,$v)])===$want,'literal subject '.$op.$v);
checkRule($query([$rule('merged',null,'1')])===[2],'native Boolean with null operator');checkRule($query([$rule('merged','','0')])===[1],'native Boolean false');
checkRule($query([$rule('my_tickets','equal','1')])===[1],'assigned actor bound parameter');checkRule($query([$rule('my_tickets','equal','0')])===[2],'not assigned actor');
checkRule($query([$rule('assigned','is_empty',null)])===[2],'unassigned');checkRule($query([$rule('assigned','is_not_empty','')])===[1],'assigned');checkRule($query([$rule('assigned','equal','17')])===[1],'selected staff');
checkRule($query([$rule('subject','equal','Other ticket'),$rule('my_tickets','equal','1')],'or')===[1,2],'or grouping');
$bad=[$rule('department','equal','1 OR 1=1'),$rule('department','equal',NativeCompanyResourceId::encode('company_ticket_status',1)),$rule('subject','raw','SELECT secret'),$rule('assigned','equal','0'),$rule('assigned','is_empty','17'),$rule('merged','not_equal','1'),$rule('my_tickets','equal',true),$rule('status','in',[]),$rule('subject','contains',"bad\0value"),$rule('subject','equal','x')+['has_dynamic_value'=>true],['id'=>'SQL','type'=>'TextRule','value'=>'x']];
foreach($bad as$r){try{NativeCompanyTicketRules::compile(['match_type'=>'and','rules'=>[$r]],17);throw new LogicException('invalid rule accepted');}catch(DomainException$e){checkRule($e->getMessage()==='native_ticket_filter_invalid','closed grammar rejects invalid rule');}}
checkRule($query([$rule('subject','equal',"' OR 1=1 --")])===[],'SQL injection remains literal parameter');echo "Native ticket filter rules: $n real SQLite checks passed. Endpoint and presentation wiring remain pending.\n";
