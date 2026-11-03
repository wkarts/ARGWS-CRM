<?php
/** Real issuer-bound facts only; original detail composition remains unadmitted. */
require __DIR__.'/native_project_page.php';
require_once APPPATH.'services/NativeCompanyProjectDetail.php';
if($db->driver()==='mysql'&&!$db->table_exists($p.'currencies')){$db->query('CREATE TABLE '.$p.'currencies(id INT NOT NULL PRIMARY KEY,name VARCHAR(100)) ENGINE=InnoDB');unset($db->data_cache['table_names']);}
$reader=new NativeCompanyProjectDetail($db,$p);$canonical=NativeCompanyResourceId::encode('company_project',$pa);
$facts=$reader->read($a,7,$canonical);
essentialCheck((int)$facts['project']['id']===$pa&&(int)$facts['project']['issuer_id']===$a,'original canonical project maps to owned project');
essentialCheck((int)$facts['customer']['source_id']===$ca,'customer identity resolved through current issuer authority');
essentialCheck($facts['reference']['canonical_id']===$canonical,'project identity retains canonical source namespace');
essentialCheck($facts['status']['state']==='completed'&&$facts['status']['id']===NativeCompanyWorkStatusCatalog::id('project','completed'),'status reflects actual project lifecycle');
$fresh=$ps->create($a,7,array_replace($input,['name'=>'Undeclared phase']),$key('project-detail-undeclared'));
$freshFacts=$reader->read($a,7,NativeCompanyResourceId::encode('company_project',$fresh));
essentialCheck($freshFacts['status']['state']==='active','historical active state is not invented as a more specific workflow');
essentialThrows(fn()=>$reader->read($b,7,$canonical),'native_resource_reference_denied','foreign issuer cannot access project facts');
essentialThrows(fn()=>$reader->read($a,52,$canonical),'native_resource_reference_denied','actor without project view cannot read facts');
essentialThrows(fn()=>$reader->read($a,7,(string)$pp),'native_resource_reference_denied','legacy raw project id is not inferred as secondary ownership');
echo "Project detail facts: original canonical identity, issuer, actor and customer checks passed; route remains closed.\n";
