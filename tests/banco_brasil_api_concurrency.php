<?php
/** Two real MySQL/MariaDB writers + synthetic transport: one durable reservation and at most one POST. */
require_once __DIR__.'/issuers_core_support.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/ApiService.php';
require_once APPPATH.'services/CompanyInvoices.php';
require_once BASEPATH.'libraries/Encryption.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:')){fwrite(STDERR,"An isolated MySQL DSN is required.\n");exit(2);}
function apiRaceConnection(string $prefix): EssentialTestDb
{
    return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$prefix);
}
if(($argv[1]??'')==='worker'){
    $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);
    $input=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$db=apiRaceConnection($p);$GLOBALS['essentialPrefix']=$p;
    $cipher=new CI_Encryption(['driver'=>'openssl','cipher'=>'aes-256','mode'=>'cbc','key'=>hex2bin($input['cipher_key'])]);
    $transport=static function($method,$url,$headers,$body)use($db,$input):array{
        if($db->pdo->inTransaction())throw new LogicException('HTTP inside SQL transaction');
        if(str_contains($url,'oauth/token')){usleep(250000);return ['status'=>200,'body'=>'{"access_token":"synthetic-token","token_type":"Bearer","expires_in":600}'];}
        if($method!=='POST'||!str_contains($url,'/boletos?'))throw new LogicException('unexpected request');
        if(file_put_contents($input['journal'],"synthetic POST\n",FILE_APPEND|LOCK_EX)===false)throw new RuntimeException('journal unavailable');
        // The remote effect is deliberately uncertain. A competing writer must not repeat it.
        throw new RuntimeException('synthetic timeout after submission');
    };
    $api=new \BancoBrasil\ApiService($db,$p,fn($v)=>$cipher->encrypt($v),fn($v)=>$cipher->decrypt($v),$transport);
    try{echo $api->issue($input['issuer'],7,$input['profile'],$input['invoice'],$input['address'],$input['key']);}
    catch(DomainException $e){if($e->getMessage()==='bb_api_access_denied')echo 'denied';else throw $e;}
    catch(RuntimeException $e){if($e->getMessage()!=='bb_api_result_unconfirmed_query_origin')throw $e;echo $db->query("SELECT id FROM {$p}bb_origins WHERE invoice_id=?",[$input['invoice']])->row()->id;}
    exit(0);
}
require __DIR__.'/banco_brasil_api_mysql.php';
$observer=apiRaceConnection($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
function apiRace(array $input,?callable $beforeRelease=null):array
{
    global $db,$observer,$p,$blocker;
    $db->trans_begin();$db->query("SELECT id FROM {$p}issuers_lock WHERE id=1 FOR UPDATE");$workers=[];
    try{
        for($i=0;$i<2;$i++){$pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($process))throw new RuntimeException('worker unavailable');fwrite($pipes[0],json_encode($input,JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$process,$pipes];}
        $deadline=microtime(true)+15;$waiters=0;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);
        essentialCheck($waiters>=2,'two API writers observed waiting on native installation row lock');if($beforeRelease)$beforeRelease();$db->trans_commit();$outputs=[];
        foreach($workers as [$process,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($process)===0&&$err==='','API worker completes without diagnostic leakage');$outputs[]=$out;}return $outputs;
    }finally{if($db->pdo->inTransaction())$db->trans_rollback();}
}
$journal=tempnam(sys_get_temp_dir(),'bb-api-synthetic-');if($journal===false)throw new RuntimeException('journal unavailable');
try{
    $raceInvoice=$s->create($a,7,$apiInput,str_repeat('d',64));$before=(int)$db->query("SELECT next_number FROM {$p}bb_number_reservations")->row()->next_number;
    $raceInput=['issuer'=>$a,'profile'=>$profile,'invoice'=>$raceInvoice,'address'=>$address,'key'=>str_repeat('d',64),'cipher_key'=>bin2hex($nativeKey),'journal'=>$journal];$out=apiRace($raceInput);
    essentialCheck($out[0]===$out[1]&&ctype_digit($out[0]),'concurrent API retries return one immutable origin');
    essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}bb_origins WHERE invoice_id=?",[$raceInvoice])->row()->n===1&&(int)$db->query("SELECT next_number FROM {$p}bb_number_reservations")->row()->next_number===$before+1,'one API number consumed under actual competing writers');
    essentialCheck(file_get_contents($journal)==="synthetic POST\n",'one and only one simulated charge POST despite concurrent workers and timeout');
    $revoked=$s->create($a,7,$apiInput,str_repeat('e',64));$raceInput['invoice']=$revoked;$raceInput['key']=str_repeat('e',64);
    essentialCheck(apiRace($raceInput,fn()=>$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7"))===['denied','denied'],'both waiting API writers honor revocation before reservation');
    essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}bb_origins WHERE invoice_id=?",[$revoked])->row()->n===0&&file_get_contents($journal)==="synthetic POST\n",'revoked race leaves no bank origin, number consumption or second POST');
}finally{unlink($journal);}
echo 'BB API concurrency: '.$GLOBALS['essentialChecks'].' cumulative actual DB checks; synthetic HTTP only.'.PHP_EOL;
