<?php
/** Real MySQL/owned transactions/writer; all provider operations are fake and explicitly counted. */
if(!str_starts_with((string)getenv('ISSUERS_TEST_DSN'),'mysql:'))throw new RuntimeException('Efí company checkout requires disposable MySQL fixture');
require_once dirname(__DIR__).'/issuers_core_support.php';
require_once APP_MODULES_PATH.'gerencianet_gateway/services/CompanyCheckout.php';
if(($argv[1]??'')==='reservation-worker'){
    $prefix=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$prefix))throw new RuntimeException('invalid owned fixture prefix');
    $arguments=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);
    $db=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$prefix);$GLOBALS['essentialPrefix']=$prefix;
    $db->query('SET SESSION innodb_lock_wait_timeout=20');
    $factory=static fn($account)=>new class{function request($operation,$params=[],$body=[]):array{echo "POST\n";flush();throw new RuntimeException('synthetic ambiguous remote response');}};
    $service=new \Efi\CompanyCheckout($db,$prefix,new \Efi\CompanyAccounts($db,$prefix),$factory);
    try{$service->issue($arguments['issuer'],7,$arguments['invoice'],$arguments['account'],$arguments['revision'],$arguments['kind'],$arguments['kind']==='boleto'?['phone'=>'11999999999']:[]);throw new LogicException('race must end in review');}
    catch(Throwable $error){if(!in_array($error->getMessage(),['efi_company_creation_unconfirmed','efi_company_existing_origin_requires_review'],true))throw $error;echo "REVIEW\n";}
    exit(0);
}
require __DIR__.'/invoices.php';
require_once APP_MODULES_PATH.'gerencianet_gateway/services/CompanyCheckout.php';
require_once APP_MODULES_PATH.'gerencianet_gateway/services/CompanyReceivedReceipt.php';
$start=$GLOBALS['essentialChecks'];
\Efi\CompanySchema::ensure($db,$p,true);\Efi\CompanySchema::ensure($db,$p,false);
register_shutdown_function(static function()use($db,$p):void{foreach(array_reverse(\Efi\CompanySchema::tables()) as $table)$db->pdo->exec('DROP TABLE IF EXISTS '.$p.$table);});
$db->query("INSERT INTO {$p}modules(module_name,installed_version,active) VALUES('gerencianet_gateway','5.1.5',1)");
(new CompanyCurrencyUnits($db,$p))->captureObservedUsed();
$encryption=new class{function encrypt($s){return 'synthetic:'.base64_encode($s);}function decrypt($s){return str_starts_with($s,'synthetic:')?base64_decode(substr($s,10),true):false;}};
$accounts=new \Efi\CompanyAccounts($db,$p);$accountIds=[];$revisions=[];
foreach([$a,$b] as $issuer){$accountIds[$issuer]=$accounts->save($issuer,7,'sandbox','new','synthetic-client-'.$issuer,'synthetic-secret','synthetic-pix',null,$encryption);$revisions[$issuer]=$accounts->overview($issuer,7)[0]['revision'];}
$factoryCalls=0;$calls=[];$responses=[];$throwPost=false;$afterQuery=null;$afterPost=null;
$factory=function(array $row)use(&$calls,&$responses,&$throwPost,&$afterQuery,&$afterPost,&$factoryCalls,$encryption){++$factoryCalls;\Efi\CompanyAccounts::credentials($row,$encryption);return new class($row,$calls,$responses,$throwPost,$afterQuery,$afterPost){
    private $calls;private $responses;private $throwPost;private $afterQuery;private $afterPost;
    function __construct(private array $row,&$calls,&$responses,&$throwPost,&$afterQuery,&$afterPost){$this->calls=&$calls;$this->responses=&$responses;$this->throwPost=&$throwPost;$this->afterQuery=&$afterQuery;$this->afterPost=&$afterPost;}
    function request(string $operation,array $params=[],array $body=[]):array{
        $this->calls[]=[$this->row['issuer_id'],$this->row['environment'],$operation];
        if($operation==='pixCreateImmediateCharge'){
            if($this->throwPost)throw new RuntimeException('synthetic uncertain remote outcome');
            $id=str_pad((string)count($this->calls),26,'a',STR_PAD_LEFT);$this->responses[$id]=['txid'=>$id,'status'=>'ATIVA','valor'=>['original'=>$body['valor']['original']],'loc'=>['id'=>count($this->calls)]];if($this->afterPost!==null)($this->afterPost)();return $this->responses[$id];
        }
        if(in_array($operation,['createCharge','createOneStepCharge'],true)){if($this->throwPost)throw new RuntimeException('synthetic uncertain remote outcome');$id=(string)(1000+count($this->calls));$this->responses[$id]=['code'=>200,'data'=>['charge_id'=>(int)$id,'total'=>$body['items'][0]['value'],'status'=>'waiting']];return $this->responses[$id];}
        if($operation==='definePayMethod'){$id=(string)$params['id'];$this->responses[$id]['data']['payment']=['banking_billet'=>['link'=>'https://visualizacao.gerencianet.com.br/emissao/synthetic','barcode'=>str_repeat('1',47)]];return $this->responses[$id];}
        if($operation==='detailCharge')return $this->responses[(string)$params['id']];
        if($operation==='pixGenerateQRCode')return ['qrcode'=>'000201'.str_repeat('1',100),'imagemQrcode'=>'untrusted-not-exposed'];
        if($operation==='pixDetailCharge'){if($this->afterQuery!==null)($this->afterQuery)();return $this->responses[$params['txid']];}
        throw new RuntimeException('Unexpected fake operation');
    }
};};
$service=new \Efi\CompanyCheckout($db,$p,$accounts,$factory);$origins=[];$proofs=[];
foreach([$a,$b] as $issuer){
    $invoice=$s->create($issuer,7,array_replace($input,['client_id'=>$issuer===$a?$ca:$cb]),hash('sha256','efi-named-'.$issuer));
    $origin=$service->issue($issuer,7,$invoice,$accountIds[$issuer],$revisions[$issuer],'pix');$origins[$issuer]=$origin;$before=count($calls);
    essentialCheck($service->issue($issuer,7,$invoice,$accountIds[$issuer],$revisions[$issuer],'pix')===$origin&&count($calls)===$before,'bound retry does not repeat remote mutation');
    $row=$db->query('SELECT * FROM '.$p.'efi_company_origins WHERE id=?',[$origin])->row_array();$id=$row['provider_id'];
    $responses[$id]['status']='CONCLUIDA';$responses[$id]['pix']=[['txid'=>$id,'valor'=>$responses[$id]['valor']['original'],'endToEndId'=>str_repeat('E',32),'horario'=>'2026-10-23T15:00:00Z']];
    $proofs[$issuer]=$service->query($issuer,7,$origin);
}
$instrument=$service->instruments($a,7,$origins[$a]);essentialCheck(array_keys($instrument)===['pix_copy_paste']&&str_starts_with($instrument['pix_copy_paste'],'000201'),'Pix instrument is bounded text without HTML/image response');
$before=count($calls);essentialThrows(fn()=>$service->instruments($b,7,$origins[$a]),'efi_company_origin_denied','cross-owner instrument denied');essentialCheck(count($calls)===$before,'instrument owner failure makes no provider call');
$before=count($calls);essentialThrows(fn()=>$service->query($b,7,$origins[$a]),'efi_company_origin_denied','A origin is not readable by B');essentialCheck(count($calls)===$before,'cross owner rejects before transport');
essentialThrows(fn()=>$service->settle($b,7,$origins[$a],$proofs[$a]),'efi_company_origin_denied','A proof cannot settle under B');
foreach(['boleto','card'] as $kind){
    $invoice=$s->create($a,7,$input,hash('sha256','efi-named-'.$kind));
    $checkoutInput=['email'=>'synthetic@example.invalid','phone'=>'11999999999'];
    if($kind==='card')$checkoutInput+=['birth'=>'1990-01-01','payment_token'=>'synthetic-ephemeral-token','billing_address'=>['street'=>'Synthetic street','number'=>'1','neighborhood'=>'Synthetic','zipcode'=>'01001000','city'=>'Synthetic','state'=>'SP']];
    $origin=$service->issue($a,7,$invoice,$accountIds[$a],$revisions[$a],$kind,$checkoutInput);
    $row=$db->query('SELECT * FROM '.$p.'efi_company_origins WHERE id=?',[$origin])->row_array();essentialCheck($row['state']==='bound','three-method fake issuance binds only confirmed amount');
    essentialCheck(!str_contains(json_encode($row),'synthetic-ephemeral-token'),'card token is never persisted in origin');
    $proof=$service->query($a,7,$origin);essentialThrows(fn()=>$service->settle($a,7,$origin,$proof),'efi_company_receipt_requires_review','unproven boleto/card settlement cannot write finances');
    if($kind==='boleto')essentialCheck(array_keys($service->instruments($a,7,$origin))===['boleto_barcode','boleto_url'],'boleto exposes only validated instrument');
}
// Full service dispatch preserves opaque decimal IDs even above PHP_INT_MAX.
$largeIds=[];$largeId='99999999999999999999';
$largeFactory=static function(array $account)use(&$largeIds,$largeId){return new class($largeIds,$largeId){
    private $seen;private int $amount=0;
    function __construct(&$seen,private string $id){$this->seen=&$seen;}
    function request(string $operation,array $params=[],array $body=[]):array{
        if($operation==='createCharge')$this->amount=$body['items'][0]['value'];
        else{$this->seen[]=[$operation,$params['id']??null];if(($params['id']??null)!==$this->id)throw new RuntimeException('divergent provider identifier dispatched');}
        return ['code'=>200,'data'=>['charge_id'=>$this->id,'total'=>$this->amount,'status'=>'waiting']];
    }
};};
$largeService=new \Efi\CompanyCheckout($db,$p,$accounts,$largeFactory);
$largeInvoice=$s->create($a,7,$input,hash('sha256','efi-large-provider-id'));
$largeOrigin=$largeService->issue($a,7,$largeInvoice,$accountIds[$a],$revisions[$a],'boleto',['email'=>'synthetic@example.invalid','phone'=>'11999999999']);
// Query transport instance derives the exact amount from the persisted origin, not a guessed fixture total.
$largeAmount=(int)$db->query('SELECT amount_minor FROM '.$p.'efi_company_origins WHERE id=?',[$largeOrigin])->row()->amount_minor;
$largeQuery=new \Efi\CompanyCheckout($db,$p,$accounts,static function(array $account)use(&$largeIds,$largeId,$largeAmount){return new class($largeIds,$largeId,$largeAmount){private $seen;function __construct(&$seen,private string $id,private int $amount){$this->seen=&$seen;}function request(string $operation,array $params=[],array $body=[]):array{$this->seen[]=[$operation,$params['id']??null];if(($params['id']??null)!==$this->id)throw new RuntimeException('divergent provider identifier dispatched');return ['code'=>200,'data'=>['charge_id'=>$this->id,'total'=>$this->amount,'status'=>'waiting']];}};});
$largeQuery->query($a,7,$largeOrigin);
essentialCheck($largeIds===[['definePayMethod',$largeId],['detailCharge',$largeId]],'zero POST/GET uses saturated or divergent provider identity');
$beforeReceipts=$db->query('SELECT * FROM '.$p.'invoicepaymentrecords ORDER BY id')->result_array();
$beforeJournal=$db->query('SELECT * FROM '.$p.'company_financial_events ORDER BY issuer_id,issuer_sequence')->result_array();
foreach(['invoicepaymentrecords','efi_company_receipts'] as $failedTable){
    $trigger=$p.'efi_fail_insert';$db->query("CREATE TRIGGER `{$trigger}` BEFORE INSERT ON `{$p}{$failedTable}` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic efi receipt failure'");
    try{
        essentialThrows(fn()=>$service->settle($a,7,$origins[$a],$proofs[$a]),'issuer_storage_failed','real SQL trigger aborts '.$failedTable.' INSERT');
        essentialCheck($db->query('SELECT * FROM '.$p.'invoicepaymentrecords ORDER BY id')->result_array()===$beforeReceipts,'real trigger rolls native receipt back');
        essentialCheck($db->query('SELECT * FROM '.$p.'company_financial_events ORDER BY issuer_id,issuer_sequence')->result_array()===$beforeJournal,'real trigger rolls financial journal back');
        essentialCheck(!$db->query('SELECT origin_id FROM '.$p.'efi_company_receipts WHERE origin_id=?',[$origins[$a]])->result_array(),'real trigger leaves no provider receipt link');
    }finally{$db->query("DROP TRIGGER `{$trigger}`");}
}
$payment=$service->settle($a,7,$origins[$a],$proofs[$a]);$journal=$db->query('SELECT * FROM '.$p.'company_financial_events ORDER BY issuer_id,issuer_sequence')->result_array();
essentialCheck($service->settle($a,7,$origins[$a],$proofs[$a])===$payment,'exact financial replay returns same payment');
essentialCheck($db->query('SELECT * FROM '.$p.'company_financial_events ORDER BY issuer_id,issuer_sequence')->result_array()===$journal,'replay does not duplicate financial journal');
essentialCheck($db->query('SELECT paymentmode FROM '.$p.'invoicepaymentrecords WHERE id=?',[$payment])->row()->paymentmode==='efi_company_received','explicit Efí mode, not BB default');
$afterQuery=function()use($accounts,$b,$accountIds,$revisions):void{$accounts->revoke($b,7,$accountIds[$b],$revisions[$b]);};
essentialThrows(fn()=>$service->query($b,7,$origins[$b]),'efi_company_result_quarantined','revocation during query prevents financial authority');$afterQuery=null;
$quarantine=$db->query('SELECT state,result_json FROM '.$p.'efi_company_proofs WHERE origin_id=? ORDER BY id DESC LIMIT 1',[$origins[$b]])->row_array();essentialCheck($quarantine['state']==='quarantine'&&isset(json_decode($quarantine['result_json'],true)['attempt_hash']),'revoked GET result persists technical proof with frozen attempt hash');
essentialThrows(fn()=>$service->instruments($b,7,$origins[$b]),'efi_company_stale','revoked instrument denied');
essentialThrows(fn()=>$service->settle($b,7,$origins[$b],$proofs[$b]),'efi_company_stale','revocation after query prevents applying proof');
// An ambiguous POST retains its fence and cannot be sent again.
$invoice=$s->create($a,7,$input,hash('sha256','efi-unknown'));$throwPost=true;
essentialThrows(fn()=>$service->issue($a,7,$invoice,$accountIds[$a],$revisions[$a],'pix'),'efi_company_creation_unconfirmed','provider timeout is ambiguous');$before=count($calls);$throwPost=false;
essentialThrows(fn()=>$service->issue($a,7,$invoice,$accountIds[$a],$revisions[$a],'pix'),'efi_company_existing_origin_requires_review','unknown outcome does not retry');essentialCheck(count($calls)===$before,'no second POST after unknown outcome');
// Revocation DURING a completed remote POST retains its known ID without granting finance.
$production=$accounts->save($a,7,'production','new','synthetic-production-A','synthetic-secret','synthetic-pix',null,$encryption);
$productionRow=$db->query('SELECT revision FROM '.$p.'efi_company_accounts WHERE id=?',[$production])->row_array();
$invoice=$s->create($a,7,$input,hash('sha256','efi-revoked-post'));
$afterPost=function()use($accounts,$a,$production,$productionRow):void{$accounts->revoke($a,7,$production,$productionRow['revision']);};
essentialThrows(fn()=>$service->issue($a,7,$invoice,$production,$productionRow['revision'],'pix'),'efi_company_creation_unconfirmed','revoked POST result cannot be bound');$afterPost=null;
$retained=$db->query('SELECT * FROM '.$p.'efi_company_origins WHERE invoice_id=?',[$invoice])->row_array();
essentialCheck($retained['state']==='quarantined'&&is_string($retained['provider_id'])&&strlen($retained['provider_id'])===26,'known provider ID retained despite revocation');
essentialCheck(!$db->query('SELECT id FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$invoice])->result_array(),'quarantine writes no financial receipt');
$before=count($calls);essentialThrows(fn()=>$service->issue($a,7,$invoice,$production,$productionRow['revision'],'pix'),'efi_company_stale','revoked attempt cannot retry');essentialCheck(count($calls)===$before,'revoked attempt makes no second mutation');
// Actual caller transaction remains owned by caller and never dispatches.
$db->pdo->beginTransaction();try{essentialThrows(fn()=>$service->query($a,7,$origins[$a]),'issuer_outer_transaction_not_allowed','raw transaction denied');essentialCheck($db->pdo->inTransaction(),'caller transaction remains active');}finally{$db->pdo->rollBack();}
essentialCheck($db->query('SELECT * FROM '.$p.'invoices WHERE id=42')->result_array()===$legacyBefore,'named adapter does not touch legacy NULL invoice');
// Real proof storage fault after authenticated synthetic GET is not a successful observation.
$proofBefore=$db->query('SELECT * FROM '.$p.'efi_company_proofs ORDER BY id')->result_array();$trigger=$p.'efi_fail_proof';
$db->query("CREATE TRIGGER `{$trigger}` BEFORE INSERT ON `{$p}efi_company_proofs` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic efi proof failure'");
try{essentialThrows(fn()=>$service->query($a,7,$origins[$a]),'issuer_storage_failed','real proof INSERT trigger fails closed');essentialCheck($db->query('SELECT * FROM '.$p.'efi_company_proofs ORDER BY id')->result_array()===$proofBefore,'no partial append after proof SQL failure');}
finally{$db->query("DROP TRIGGER `{$trigger}`");}
// Two real processes/connections contend at the canonical issuer row lock. Only one
// reaches the fake POST; the deliberately ambiguous response makes retries review-only.
$raceInvoice=$s->create($a,7,$input,hash('sha256','efi-real-reservation-race'));
$observer=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);
$blocker=(int)$db->query('SELECT CONNECTION_ID() AS id')->row()->id;$workers=[];
$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');
try{
    foreach(['pix','boleto'] as $kind){
        $pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'reservation-worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($proc))throw new RuntimeException('Efí contention worker unavailable');
        fwrite($pipes[0],json_encode(['issuer'=>$a,'invoice'=>$raceInvoice,'account'=>$accountIds[$a],'revision'=>$revisions[$a],'kind'=>$kind],JSON_THROW_ON_ERROR));fclose($pipes[0]);
        stream_set_timeout($pipes[1],25);stream_set_timeout($pipes[2],25);$workers[]=[$proc,$pipes];
    }
    $deadline=microtime(true)+15;$waiters=0;
    do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);
    essentialCheck($waiters>=2,'two actual Efí reservation workers observed waiting at issuer lock');$db->trans_commit();$outputs=[];
    foreach($workers as [$proc,$pipes]){$outputs[]=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$stderr==='','reservation process exits without diagnostics');}
    $workers=[];essentialCheck(substr_count(implode('',$outputs),"POST\n")===1&&substr_count(implode('',$outputs),"REVIEW\n")===2,'two modalities yield exactly one counted POST and two safe review outcomes');
    $raceRows=$db->query('SELECT * FROM '.$p.'efi_company_origins WHERE invoice_id=?',[$raceInvoice])->result_array();essentialCheck(count($raceRows)===1&&$raceRows[0]['state']==='unknown','UNIQUE invoice leaves one durable ambiguous intent');
    essentialCheck(!$db->query('SELECT id FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$raceInvoice])->result_array(),'reservation race never creates payment');
}finally{
    if($db->pdo->inTransaction())$db->trans_rollback();
    foreach($workers as [$proc,$pipes]){if(is_resource($proc))proc_terminate($proc);foreach([1,2] as $fd)if(is_resource($pipes[$fd]))fclose($pipes[$fd]);if(is_resource($proc))proc_close($proc);}
}
// Non-master configurator ownership and current resource ACL are independent gates.
$db->query("INSERT INTO {$p}staff(staffid,active,admin) VALUES(51,1,0),(52,1,0)");
foreach([51,52] as $staff)$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(?,?,1)",[$b,$staff]);
foreach([['settings','view'],['settings','edit'],['invoices','view'],['invoices','edit'],['customers','view'],['payments','create']] as [$feature,$cap])$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,51,?,?)',[$b,$feature,$cap]);
essentialCheck(!(new IssuerResourceAccess($db,$p))->isMaster(51),'test configurator is explicitly not installation master');
$ownedAccount=$accounts->save($b,51,'production','new','synthetic-owned-B','synthetic-secret','synthetic-pix',null,$encryption);
$ownedRow=$db->query('SELECT * FROM '.$p.'efi_company_accounts WHERE id=?',[$ownedAccount])->row_array();
$ownedInvoice=$s->create($b,7,array_replace($input,['client_id'=>$cb]),hash('sha256','efi-nonmaster-configurator'));
$ownedOrigin=$service->issue($b,51,$ownedInvoice,$ownedAccount,$ownedRow['revision'],'pix');
$before=count($calls);$beforeFactory=$factoryCalls;
essentialThrows(fn()=>$service->query($b,52,$ownedOrigin),'efi_company_access_denied','member without invoice grant cannot query');
essentialCheck(count($calls)===$before&&$factoryCalls===$beforeFactory,'unauthorized actor denied before factory and SDK');
$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=? AND staff_id=51 AND feature='settings' AND capability='edit'",[$b]);
essentialThrows(fn()=>$service->query($b,7,$ownedOrigin),'efi_company_account_revoked','configurator lost settings/edit: even master caller cannot use account');
essentialCheck(count($calls)===$before&&$factoryCalls===$beforeFactory,'revoked owner grant denies before factory and SDK');
$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,51,'settings','edit')",[$b]);
$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=51");
essentialThrows(fn()=>$service->query($b,7,$ownedOrigin),'efi_company_account_revoked','inactive configurator cannot lend account authority to master');
essentialCheck(count($calls)===$before&&$factoryCalls===$beforeFactory,'inactive owner denies before transport');$db->query("UPDATE {$p}staff SET active=1 WHERE staffid=51");
// Deliberate disposable-DB corruption: frozen origin must reject an environment swap.
$db->query("UPDATE {$p}efi_company_accounts SET environment='staging' WHERE id=?",[$ownedAccount]);
try{essentialThrows(fn()=>$service->query($b,7,$ownedOrigin),'efi_company_account_conflict','current account environment cannot replace frozen origin environment');essentialCheck(count($calls)===$before&&$factoryCalls===$beforeFactory,'environment mismatch denies before factory');}
finally{$db->query("UPDATE {$p}efi_company_accounts SET environment='production' WHERE id=?",[$ownedAccount]);}
// Even a storage-level credential-envelope swap cannot authorize another company's SDK.
$foreignCipher=$db->query('SELECT credentials_cipher FROM '.$p.'efi_company_accounts WHERE id=?',[$accountIds[$a]])->row()->credentials_cipher;
$db->query('UPDATE '.$p.'efi_company_accounts SET credentials_cipher=? WHERE id=?',[$foreignCipher,$ownedAccount]);
try{essentialThrows(fn()=>$service->query($b,7,$ownedOrigin),'efi_company_credentials_unavailable','foreign encrypted envelope rejected');essentialCheck(count($calls)===$before,'foreign envelope never reaches SDK request');}
finally{$db->query('UPDATE '.$p.'efi_company_accounts SET credentials_cipher=? WHERE id=?',[$ownedRow['credentials_cipher'],$ownedAccount]);}
// A valid explicit credential rotation does not silently adopt an existing origin.
$accounts->save($b,51,'production',$ownedRow['revision'],'synthetic-owned-B','synthetic-secret-rotated','synthetic-pix',null,$encryption);
$beforeFactory=$factoryCalls;essentialThrows(fn()=>$service->query($b,51,$ownedOrigin),'efi_company_stale','old origin remains bound to original account revision');
essentialThrows(fn()=>$service->issue($b,51,$ownedInvoice,$ownedAccount,$ownedRow['revision'],'pix'),'efi_company_stale','old requested revision cannot issue after rotation');
essentialCheck(count($calls)===$before&&$factoryCalls===$beforeFactory,'rotation negatives do not call SDK or hydrate credentials');
echo 'Efí named checkout/writer MySQL: '.($GLOBALS['essentialChecks']-$start).' added checks PASS; fake transport only'.PHP_EOL;
