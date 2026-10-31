<?php
require __DIR__.'/../issuers_core_support.php';require_once APPPATH.'services/issuers/IssuerRegistry.php';require_once APPPATH.'services/NativeCompanyTimerPolicy.php';
register_shutdown_function('essentialCleanup');$f=essentialFixture('1.0.2',1);$db=$f->db;$p=$f->prefix;IssuerCore::reconcile($db,$p);IssuerCore::bindInstallationMaster($db,$p,7);$a=(new IssuerRegistry($db,$p))->saveIssuer(7,['legal_name'=>'Timer policy A','tax_id'=>'11222333000181']);
$db->query('INSERT INTO '.$p.'options(name,value) VALUES(?,?)',[CompanyTaskTimerStopPreferences::AUTO_STOP,'1']);
$reader=new NativeCompanyTimerPolicy($db,$p);$legacy=$reader->read(1,7);essentialCheck($legacy['auto_stop']===true&&$legacy['source']==='principal_legacy','principal preserves configured legacy preference');
$secondary=$reader->read($a,7);essentialCheck($secondary['auto_stop']===false&&$secondary['source']==='canonical_default','secondary uses own canonical default, not legacy value');
(new CompanyTaskTimerStopPreferences($db,$p))->save(1,7,0,[CompanyTaskTimerStopPreferences::AUTO_STOP=>'0']);$override=$reader->read(1,7);essentialCheck($override['auto_stop']===false&&$override['source']==='owned_override'&&$override['revision']===1,'explicit owned zero is not absence');
$db->query('DROP TABLE '.$p.'options');unset($db->data_cache['table_names']);
// The real fixture driver records/rejects exactly the option data query, not metadata queries.
$db->query('CREATE TABLE '.$p.'options(name VARCHAR(191) PRIMARY KEY,value TEXT NOT NULL)');unset($db->data_cache['table_names']);
$db->failOn='SELECT name,value FROM '.$p.'options';
essentialCheck($reader->read($a,7)['source']==='canonical_default','empty global option data does not influence secondary');
essentialCheck($reader->read(1,7)['source']==='owned_override','owned principal override does not read legacy option');
$db->failOn=null;
$db->query('UPDATE '.$p.'issuers_registry SET settings_json=? WHERE id=1',['{}']);essentialThrows(fn()=>$reader->read(1,7),'native_timer_policy_invalid','missing principal source is rejected rather than invented');
echo "Native timer policy: {$GLOBALS['essentialChecks']} checks passed.\n";
