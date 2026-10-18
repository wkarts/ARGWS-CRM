<?php
require __DIR__.'/sales_document_readers.php';
$createFunction=method_exists($pdo,'createFunction')?'createFunction':'sqliteCreateFunction';
$pdo->$createFunction('YEAR',static fn($v)=>(int)substr($v,0,4),1);
$pdo->$createFunction('CONCAT',static fn(...$v)=>implode('',$v));
$pdo->exec("ALTER TABLE q_estimates ADD COLUMN date TEXT; ALTER TABLE q_estimates ADD COLUMN sale_agent INT;
UPDATE q_estimates SET date=CASE id WHEN 1 THEN '2024-01-01' ELSE '2025-01-01' END,sale_agent=id;
ALTER TABLE q_proposals ADD COLUMN date TEXT; ALTER TABLE q_proposals ADD COLUMN assigned INT;
UPDATE q_proposals SET date=CASE id WHEN 2 THEN '2025-01-01' ELSE '2024-01-01' END,assigned=id;
UPDATE q_creditnotes SET date=CASE id WHEN 1 THEN '2024-01-01' ELSE '2025-01-01' END;
CREATE TABLE q_staff(staffid INT,firstname TEXT,lastname TEXT); INSERT INTO q_staff VALUES(1,'Legacy','Agent'),(2,'Private','Agent');");
foreach(['Estimates'=>'get_estimates_years','Proposals'=>'get_proposals_years','Credit_notes'=>'get_credits_years'] as $class=>$method){
 $m=(new ReflectionClass($class.'_model'))->newInstanceWithoutConstructor();$m->db=$model->db;
 check(array_column($m->$method(),'year')===[2024],$class.' filter years exclude private-only years');
 if($class!=='Credit_notes')check(array_column($m->get_sale_agents(),'sale_agent')===($class==='Proposals'?[1,3]:[1]),$class.' filter agents exclude private-only agents');
}
echo "Sales document filter metadata: $n checks passed\n";
function get_currency($id){return (object)['symbol'=>'R$','name'=>'BRL'];}
$pdo->exec("ALTER TABLE q_estimates ADD COLUMN status INT; ALTER TABLE q_estimates ADD COLUMN total NUMERIC;
UPDATE q_estimates SET status=1,total=CASE id WHEN 1 THEN 10 ELSE 999 END,date='".date('Y')."-01-01';");
$estimate=(new ReflectionClass(Estimates_model::class))->newInstanceWithoutConstructor();$estimate->db=$model->db;
$estimate->load=new class {function model($name){}};
$property=new ReflectionProperty(Estimates_model::class,'statuses');$property->setValue($estimate,[1]);
$totals=$estimate->get_estimates_total(['currency'=>1]);
check((float)$totals[1]['total']===10.0,'estimate total retains legacy amount and excludes private amount');
echo "Sales document aggregate: $n checks passed\n";

check($estimate->get_estimates_total(['customer_id'=>2])===[],'private aggregate customer denied before currency hydration');
check($estimate->get_estimates_total(['currency'=>"1 OR 1=1"])[1]['total']===null,'aggregate currency cannot alter SQL predicate');
echo "Sales aggregate input boundaries: $n checks passed\n";

$estimate->projects_model=new class {function get_currency($id){return false;}};
check($estimate->get_estimates_total(['project_id'=>2])===[],'denied project currency does not hydrate or calculate totals');
echo "Sales aggregate project boundary: $n checks passed\n";
