<?php
register_shutdown_function(static function():void{foreach($GLOBALS['essentialFixtures']??[]as$f)if($f->db->driver()==='mysql')foreach(['company_project_fixed_billing_links','company_project_fixed_billing_origins']as$t)$f->db->query('DROP TABLE IF EXISTS '.$f->prefix.$t);});
require __DIR__.'/native_project_form.php';
require_once APPPATH.'services/CompanyProjectFixedBillingSchema.php';
if($db->driver()==='mysql'){CompanyProjectFixedBillingSchema::ensure($db,$p);CompanyProjectFixedBillingSchema::ensure($db,$p);}else foreach(CompanyProjectFixedBillingSchema::definitions() as $t=>$fields){$cols=[];foreach($fields as $n=>$type)$cols[]=$n.' '.(str_contains($type,'AUTO_INCREMENT')?'INTEGER PRIMARY KEY AUTOINCREMENT':$type.(str_ends_with($type,' NULL')?'':' NOT NULL'));foreach(CompanyProjectFixedBillingSchema::indexes($t) as $k=>[$c,$non])if($k==='PRIMARY'&&!isset($fields['id']))$cols[]='PRIMARY KEY('.implode(',',$c).')';elseif($k!=='PRIMARY'&&!$non)$cols[]='UNIQUE('.implode(',',$c).')';$db->query('CREATE TABLE '.$p.$t.'('.implode(',',$cols).')');}unset($db->data_cache['table_names']);
$before=count($db->query('SELECT id FROM '.$p.'company_projects WHERE issuer_id=?',[$a])->result_array());
$full=$wire;$full['native_request_key']=$tk('native-form-full-financial-schema');$full['name']='Full installed financial guards';
essentialThrows(fn()=>$adapter->save($a,92,null,$full),'company_project_access_denied','Installed fixed-billing fence retains required task visibility');
essentialCheck(count($db->query('SELECT id FROM '.$p.'company_projects WHERE issuer_id=?',[$a])->result_array())===$before,'Denied monetary configuration rolls back native project and dependent creation');
$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,92,?,?)',[$a,'tasks','view']);
$real=$adapter->save($a,92,null,$full);$realSource=NativeCompanyResourceId::decode('project',$real);
essentialCheck((int)$realSource['source_id']>0&&count($db->query('SELECT id FROM '.$p.'company_projects WHERE issuer_id=?',[$a])->result_array())===$before+1,'Native form succeeds after actual required read grant with installed fixed-billing schema');
essentialCheck($adapter->save($a,92,null,$full)===$real,'Full financial-schema creation retains durable idempotency');
echo 'Native project form with installed financial fence: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
