<?php
/** Regression for the exact subprocess GET fixture; SQLite is not concurrency evidence. */
require __DIR__.'/asaas_jobs_fees.php';
require_once __DIR__.'/asaas_job_transport_fixture.php';
$transportStart=$GLOBALS['essentialChecks'];
$config=$asaas->accounts($b,7)[0];$asaas->configureAutomation($b,7,$accounts[$b],$config['revision'],true);
$workerTransport=asaasJobTransportFixture($db,$p);
foreach(['missing','mismatch','valid'] as $mode){
    [$invoice,$origin,$job,$remote]=asaasAsyncEvent($b,$customerB,'cus_b','worker-description-'.$mode);
    $transport=static function(...$args)use($workerTransport,$mode):array{
        $response=$workerTransport(...$args);
        if(str_starts_with($args[2],'payments/')){
            if($mode==='missing')unset($response['description']);
            elseif($mode==='mismatch')$response['description']='different synthetic description';
        }
        return $response;
    };
    if($mode==='valid'){
        asaasFixtureWithPreferences($b,7,$accounts[$b],static function()use($db,$p,$b,$asaas):void{
            $policy=new \Asaas\CompanyPreferences($db,$p);$page=$policy->page($b,7);$account=$asaas->accounts($b,7)[0];
            $policy->saveProfile($b,7,'sandbox',array_replace(\Asaas\CompanyPreferences::DEFAULTS,['description'=>'Changed after issuance']),$page['profiles']['sandbox']['revision'],$account['revision']);
        });
        essentialCheck($db->query('SELECT description FROM '.$p.'asaas_company_profiles WHERE issuer_id=? AND environment=?',[$b,'sandbox'])->row()->description==='Changed after issuance','later profile change is actually persisted before worker GET');
    }
    $result=$worker->runOnce($dec,$transport,1);
    $state=$db->query('SELECT state,last_code FROM '.$p.'asaas_company_jobs WHERE event_id=?',[$job])->row_array();
    $payments=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$invoice])->row()->n;
    if($mode!=='valid'){
        essentialCheck($result===['completed'=>0,'retry'=>0,'review'=>1]&&$state['state']==='review_required'&&$state['last_code']==='canonical_or_authorization_review','missing/mismatched worker description stays in review: '.$mode);
        essentialCheck($payments===0,'conflicting fixture response cannot bypass canonical receipt: '.$mode);
    }else{
        $profile=json_decode($db->query('SELECT snapshot_json FROM '.$p.'asaas_company_origin_profiles WHERE origin_id=?',[$origin])->row()->snapshot_json,true,16,JSON_THROW_ON_ERROR);
        essentialCheck($profile['description']!=='Changed after issuance'&&$workerTransport('sandbox','GET','payments/'.$remote,null,'key-B')['description']===$profile['description'],'worker GET echoes the immutable origin description despite a later profile change');
        essentialCheck($result===['completed'=>1,'retry'=>0,'review'=>0]&&$state['state']==='completed'&&$payments===1,'correct worker fixture completes exactly one canonical receipt');
        essentialCheck($worker->runOnce($dec,$workerTransport,1)['completed']===0&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$invoice])->row()->n===1,'completed fixture job replays without a second receipt');
    }
}
echo 'Company Asaas worker transport: '.($GLOBALS['essentialChecks']-$transportStart).' regression checks passed ('.$db->driver().'); provider simulated, no concurrency claim.'.PHP_EOL;
