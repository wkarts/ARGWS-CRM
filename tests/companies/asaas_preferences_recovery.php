<?php
/** Real155 service boundaries and historical149 recovery; all bank transport is simulated. */
require __DIR__.'/asaas_positive.php';
$recoveryStart=$GLOBALS['essentialChecks'];
$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.5.5','asaas']);

$newInvoice=fn(string $tag)=>$s->create($b,7,array_replace($input,['client_id'=>$customerB]),hash('sha256','preferences-recovery-invoice-'.$tag));
$issue=function(int $invoice,string $tag,?callable $transport=null)use($asaas,$b,$accounts,$s,$dec,$mock):int{
    return $asaas->issue($b,7,$invoice,$accounts[$b],'cus_b','PIX',$s->revision($b,7,$invoice),hash('sha256','preferences-recovery-origin-'.$tag),$dec,$transport??$mock);
};

// A revocation after canonical wallet GET must stop before reservation or payment POST.
foreach(['enabled','verified_at'] as $field){
    $invoice=$newInvoice('revoked-'.$field);$before=$db->query('SELECT * FROM '.$p.'asaas_company_accounts WHERE id=?',[$accounts[$b]])->row_array();$posts=0;
    $transport=function($env,$method,$path,$payload,$secret)use($mock,$db,$p,$accounts,$b,$field,&$posts){
        if($method==='POST'&&$path==='payments')$posts++;
        $response=$mock($env,$method,$path,$payload,$secret);
        if($path==='wallets/')$db->query('UPDATE '.$p.'asaas_company_accounts SET '.$field.'='.($field==='enabled'?'0':'NULL').' WHERE id=?',[$accounts[$b]]);
        return $response;
    };
    try{
        essentialThrows(fn()=>$issue($invoice,'revoked-'.$field,$transport),$field==='enabled'?'asaas_company_account_unverified':'asaas_company_stale','account '.$field.' is rechecked after provider GET');
        essentialCheck($posts===0&&!$db->query('SELECT id FROM '.$p.'asaas_payment_origins WHERE invoice_id=?',[$invoice])->row(),'revoked '.$field.' leaves zero payment POST and zero reserved origin');
    }finally{
        $db->query('UPDATE '.$p.'asaas_company_accounts SET enabled=?,verified_at=? WHERE id=?',[$before['enabled'],$before['verified_at'],$accounts[$b]]);
    }
    essentialCheck($db->query('SELECT * FROM '.$p.'asaas_company_accounts WHERE id=?',[$accounts[$b]])->row_array()===$before,'revocation fixture restores exact account bytes');
}

$invoice=$newInvoice('protected');$origin=$issue($invoice,'protected');
$meta=$db->query('SELECT * FROM '.$p.'asaas_company_origin_meta WHERE origin_id=?',[$origin])->row_array();
$snapshot=json_decode($meta['snapshot_json'],true,32,JSON_THROW_ON_ERROR);
$profile=$db->query('SELECT * FROM '.$p.'asaas_company_origin_profiles WHERE origin_id=?',[$origin])->row_array();
essentialCheck(($snapshot['asaas_profile_contract']??null)==='company_profile_v1'&&$profile!==[],'new155 origin anchors its required sidecar in hashed invoice metadata');
$assertDenied=function(string $label)use($asaas,$b,$origin,$invoice,$issue,$dec,$mock,&$calls):void{
    $before=count($calls);
    essentialThrows(fn()=>$asaas->query($b,7,$origin,$dec,$mock),'asaas_company_profile_snapshot_divergence',$label.' blocks observation');
    essentialThrows(fn()=>$issue($invoice,'protected'),'asaas_company_profile_snapshot_divergence',$label.' blocks idempotent replay');
    essentialCheck(count($calls)===$before,$label.' fails before any provider call');
};
$db->query('DELETE FROM '.$p.'asaas_company_origin_profiles WHERE origin_id=?',[$origin]);
$assertDenied('missing155 sidecar');
$db->query('INSERT INTO '.$p.'asaas_company_origin_profiles(origin_id,issuer_id,account_row,snapshot_json,snapshot_hash,created_at) VALUES(?,?,?,?,?,?)',array_values($profile));
$db->query('UPDATE '.$p.'asaas_company_origin_profiles SET snapshot_hash=? WHERE origin_id=?',[str_repeat('0',64),$origin]);
$assertDenied('tampered155 sidecar hash');
$db->query('UPDATE '.$p.'asaas_company_origin_profiles SET snapshot_hash=? WHERE origin_id=?',[$profile['snapshot_hash'],$origin]);
$foreign=json_decode($profile['snapshot_json'],true,16,JSON_THROW_ON_ERROR);$foreign['issuer_id']=$a;
$foreignJson=json_encode($foreign,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$db->query('UPDATE '.$p.'asaas_company_origin_profiles SET snapshot_json=?,snapshot_hash=? WHERE origin_id=?',[$foreignJson,hash('sha256',$foreignJson),$origin]);
$assertDenied('rehashed foreign155 sidecar');
$db->query('UPDATE '.$p.'asaas_company_origin_profiles SET snapshot_json=?,snapshot_hash=? WHERE origin_id=?',[$profile['snapshot_json'],$profile['snapshot_hash'],$origin]);
essentialCheck($issue($invoice,'protected')===$origin,'restored valid155 evidence returns original origin without another POST');

// Materialize the historical149 serialized shape: no155 contract and no155 sidecar.
// This is fixture construction, never a runtime migration or automatic adoption path.
$historicalInvoice=$newInvoice('historical');$historicalOrigin=$issue($historicalInvoice,'historical');
$historicalMeta=$db->query('SELECT * FROM '.$p.'asaas_company_origin_meta WHERE origin_id=?',[$historicalOrigin])->row_array();
$historicalSnapshot=json_decode($historicalMeta['snapshot_json'],true,32,JSON_THROW_ON_ERROR);unset($historicalSnapshot['asaas_profile_contract']);
$historicalJson=json_encode($historicalSnapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$db->query('UPDATE '.$p.'asaas_company_origin_meta SET snapshot_json=?,snapshot_hash=? WHERE origin_id=?',[$historicalJson,hash('sha256',$historicalJson),$historicalOrigin]);
$db->query('DELETE FROM '.$p.'asaas_company_origin_profiles WHERE origin_id=?',[$historicalOrigin]);
$historicalMeta=$db->query('SELECT * FROM '.$p.'asaas_company_origin_meta WHERE origin_id=?',[$historicalOrigin])->row_array();
$historicalKeys=['id','issuer_id','clientid','hash','date','duedate','total','currency','currency_name','legal_identity_json','recipient_identity_json','formatted_number'];
essentialCheck(array_keys($historicalSnapshot)===$historicalKeys,'historical149 snapshot retains its original exact ordered contract');
$remote=$db->query('SELECT remote_id FROM '.$p.'asaas_payment_origins WHERE id=?',[$historicalOrigin])->row()->remote_id;
unset($responses[$remote]['description']); // Historical bank evidence never needed the new profile field.
foreach(['1.5.5','1.5.0'] as $version){
    $db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',[$version,'asaas']);
    $before=count($calls);essentialCheck($issue($historicalInvoice,'historical')===$historicalOrigin&&count($calls)===$before,'historical149 retry stays network-free under '.$version);
    $posts=count(array_filter($calls,fn($call)=>$call[1]==='POST'&&$call[2]==='payments'));
    essentialCheck($asaas->query($b,7,$historicalOrigin,$dec,$mock)>0,'historical149 observation remains available under '.$version);
    essentialCheck(count(array_filter($calls,fn($call)=>$call[1]==='POST'&&$call[2]==='payments'))===$posts,'historical149 query never creates a remote payment under '.$version);
    essentialCheck($db->query('SELECT * FROM '.$p.'asaas_company_origin_meta WHERE origin_id=?',[$historicalOrigin])->row_array()===$historicalMeta&&!$db->query('SELECT origin_id FROM '.$p.'asaas_company_origin_profiles WHERE origin_id=?',[$historicalOrigin])->row(),'historical149 query/replay do not adopt or rewrite its evidence under '.$version);
}
// Losing the whole additive sidecar table must not downgrade a155 origin to legacy either.
$db->query('DROP TABLE '.$p.'asaas_company_origin_profiles');unset($db->data_cache['table_names']);
$assertDenied('missing155 sidecar table after rollback');
essentialCheck($issue($historicalInvoice,'historical')===$historicalOrigin&&$asaas->query($b,7,$historicalOrigin,$dec,$mock)>0,'genuine historical149 remains usable when the155 sidecar table is unavailable');

echo 'Company Asaas155 revocation/snapshot recovery: '.($GLOBALS['essentialChecks']-$recoveryStart).' added; '.$GLOBALS['essentialChecks'].' cumulative passed ('.$db->driver().'). Provider simulated.'.PHP_EOL;
