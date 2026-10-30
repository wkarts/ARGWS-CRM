<?php
/** Actual immutable 458 migration on a disposable MySQL DB; optional SQLite smoke. */
declare(strict_types=1);
require __DIR__.'/native_upgrade_support.php';
$source458=file_get_contents(APPPATH.'migrations/458_version_458.php');
foreach(['CompanyClientProfileSchema::ensure','IssuerCertificateSchema::ensure']as$call)a1check(str_contains($source458,$call),'458 composes actual '.$call);
[$db,$app,$source]=a1nativeSeed();
a1check(!$db->table_exists('a1_issuer_certificate_credentials'),'456 baseline cache primed before A1 CREATE');
$result=a1nativeRun($app);
a1check($result['success']===true&&a1nativeVersion($db)===458,'actual native upgrade456→457→458 confirms persisted marker');
a1check($db->markerWrites===[457,458],'native runner tracks both contiguous steps');
a1check(count(array_filter($GLOBALS['a1_native_events'],static fn($event)=>$event==='database_updated'))===1&&IssuerCore::$reconciled===1,'confirmed native upgrade fires one completion hook/reconciliation');
a1nativeReady($db);
$mails=a1nativeMails($db);a1check(count($mails)===3,'458 leaves unrelated email variants untouched');
a1check($mails[0]['language']==='english'&&$mails[0]['message']===$source['message'],'English source is preserved');
a1check($mails[1]['subject']==='ASSUNTO PERSONALIZADO {contact_firstname}'&&$mails[1]['message']==='CORPO PERSONALIZADO {reset_password_url}','company A custom subject/body are preserved');
a1check($mails[2]['message']===$source['message']&&$mails[2]['language']==='portuguese_br'&&(int)$mails[2]['issuer_id']===20,'458 does not rewrite another company email content');
$db->query('INSERT INTO a1_company_client_profiles VALUES(?,?,?)',[10,101,'{"custom":"preserve"}']);
$certificate=a1fixture();$manager=new IssuerCertificateManager($db,'a1_',a1encryption());$manager->replace(10,2,0,$certificate['pfx'],$certificate['password']);
$material=$db->query('SELECT * FROM a1_issuer_certificate_credentials')->result_array();$audit=$db->query('SELECT * FROM a1_issuer_certificate_audit')->result_array();
$steps=$db->markerWrites;$ddl=count($db->ddl);a1check(a1nativeRun($app)['success']===true&&$db->markerWrites===$steps&&count($db->ddl)===$ddl,'already-current native runner does not repeat migrations');
// Failed tracking means DDL/data may already exist; replay actual up() is conservative.
$db->query('UPDATE a1_migrations SET version=457');$result=a1nativeRun($app);a1check($result['success']===true&&a1nativeVersion($db)===458,'actual458 replay succeeds on existing tables/data');
a1check(a1nativeMails($db)===$mails,'email replay has no duplicate or content drift');
a1check($db->query('SELECT profile_json FROM a1_company_client_profiles')->row()->profile_json==='{"custom":"preserve"}','profile replay preserves customized JSON');
a1check($db->query('SELECT * FROM a1_issuer_certificate_credentials')->result_array()===$material&&$db->query('SELECT * FROM a1_issuer_certificate_audit')->result_array()===$audit,'migration replay does not rewrite certificates or audit');
foreach(['profile','certificate','marker_false','marker_ack_without_write','readback']as$failure){
    [$db,$app]=a1nativeSeed();$before=a1nativeMails($db);
    if($failure==='profile')$db->failSql='CREATE TABLE IF NOT EXISTS a1_company_client_profiles';
    elseif($failure==='certificate')$db->failSql='CREATE TABLE IF NOT EXISTS `a1_issuer_certificate_audit`';
    elseif($failure==='marker_false')$db->failMarker=458;
    elseif($failure==='marker_ack_without_write')$db->pretendMarker=458;
    else $db->failReadback=458;
    $result=a1nativeRun($app);
    a1check($result['success']===false,'native '.$failure.' cannot claim successful upgrade');
    a1check(a1nativeVersion($db)===($failure==='readback'?458:457),'native '.$failure.' leaves truthful persisted marker');
    a1check(!in_array('database_updated',$GLOBALS['a1_native_events'],true)&&IssuerCore::$reconciled===0,'native '.$failure.' suppresses completion hook/reconciliation');
    $db->failSql=null;$db->failMarker=null;$db->pretendMarker=null;$db->failReadback=null;
    a1check(a1nativeRun($app)['success']===true&&a1nativeVersion($db)===458,'native '.$failure.' safely resumes after repair');a1nativeReady($db);
    a1check(a1nativeMails($db)===$before,'native '.$failure.' retry preserves unrelated email variants');
}
echo 'Native458 '.(getenv('A1_NATIVE_SQLITE_SMOKE')==='1'?'SQLite smoke (NOT MySQL proof)':'MySQL').": actual shipped migrations/helpers and App readback validated; minimal456 fixture, unrelated reconciliation adapted.\n";
