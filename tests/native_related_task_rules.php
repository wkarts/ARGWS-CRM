<?php
require __DIR__.'/../application/services/NativeCompanyRelatedTaskRules.php';
$n=0;$check=static function($v)use(&$n){if(!$v)throw new RuntimeException('Task filter contract');$n++;};
$rule=static fn($id,$op,$value)=>['match_type'=>'and','rules'=>[['id'=>$id,'type'=>NativeCompanyRelatedTaskRules::TYPES[$id],'operator'=>$op,'value'=>$value]]];
foreach([['name','contains','task',['name'=>'A task'],true],['name','not_contains','task',['name'=>'A task'],false],['status','in',['3'],['status'=>3],true],['assigned','not_in',['17'],['assigned'=>[18]],true],['my_tasks','equal','0',['my_tasks'=>false],true],['billable','equal','0',['billable'=>null],false],['duedate','is_empty','',['duedate'=>null],true],['duedate','is_not_empty','',['duedate'=>null],false],['startdate','between',['2026-10-01','2026-10-31'],['startdate'=>'2026-10-25'],true]]as[$id,$op,$v,$facts,$expected])$check(NativeCompanyRelatedTaskRules::matches($rule($id,$op,$v),$facts,'2026-10-25')===$expected);
foreach([['status','in',['6']],['priority','in',['0']],['assigned','in',['1 OR 1=1']],['my_tasks','equal','yes'],['name','raw','x'],['startdate','equal','2026-02-31']]as[$id,$op,$v]){try{NativeCompanyRelatedTaskRules::normalizeBuilder($rule($id,$op,$v));throw new LogicException('Invalid filter accepted');}catch(DomainException$e){$n++;}}
$localized=NativeCompanyRelatedTaskRules::normalizeBuilder($rule('startdate','equal','25/10/2026'),'d/m/Y|%d/%m/%Y');$check($localized['rules'][0]['value']==='2026-10-25');
$check(NativeCompanyRelatedTaskRules::matches(['match_type'=>'and','rules'=>[]],[],'2026-10-25'));echo 'Related native task filters: '.$n." checks passed.\n";

// Match the original task concepts against actual SQLite comparisons. A task
// without the relevant date or already completed does not match either value.
$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE task_filter_fixture(startdate TEXT, duedate TEXT, status INTEGER NOT NULL)');
$insert=$pdo->prepare('INSERT INTO task_filter_fixture VALUES(?,?,?)');
foreach([['2026-10-24','2026-10-24',1],['2026-10-25','2026-10-25',1],['2026-10-26','2026-10-26',1],[null,null,1],['2026-10-24','2026-10-24',5],['2026-10-26','2026-10-26',5]]as$row)$insert->execute($row);
$rows=$pdo->query('SELECT rowid,startdate,duedate,status FROM task_filter_fixture')->fetchAll(PDO::FETCH_ASSOC);
$comparisons=['todays_tasks'=>['startdate','=','!='],'duedate_passed'=>['duedate','<','>='],'upcoming_tasks'=>['startdate','>','<=']];
foreach($comparisons as$id=>[$column,$positive,$negative])foreach(['equal','not_equal']as$operator)foreach(['0','1']as$value){
 $effective=($value==='1')!==($operator==='not_equal');$comparator=$effective?$positive:$negative;
 $q=$pdo->prepare('SELECT CASE WHEN status != 5 AND '.$column.' '.$comparator.' ? THEN 1 ELSE 0 END FROM task_filter_fixture WHERE rowid=?');
 foreach($rows as$row){$q->execute(['2026-10-25',$row['rowid']]);$expected=(bool)$q->fetchColumn();$facts=['status'=>(int)$row['status'],'startdate'=>$row['startdate'],'duedate'=>$row['duedate'],$id=>false];$check(NativeCompanyRelatedTaskRules::matches($rule($id,$operator,$value),$facts,'2026-10-25')===$expected);}
}
echo 'Task date filters match SQLite for prior/current/future, missing and completed tasks: '.$n." cumulative checks passed.\n";
