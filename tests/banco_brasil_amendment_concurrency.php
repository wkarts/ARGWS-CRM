<?php
/** Two actual database writers and one observable PATCH intent; bank transport remains synthetic. */
require_once __DIR__.'/issuers_core_support.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/ApiService.php';
require_once BASEPATH.'libraries/Encryption.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:'))exit(2);
function amendRaceDb(string $p):EssentialTestDb{return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);}
if(($argv[1]??'')==='worker'){
    $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$input=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$db=amendRaceDb($p);$GLOBALS['essentialPrefix']=$p;
    $cipher=new CI_Encryption(['driver'=>'openssl','cipher'=>'aes-256','mode'=>'cbc','key'=>hex2bin($input['cipher_key'])]);
    $transport=static function($method,$url,$headers,$body)use($db,$input):array{
        if($db->pdo->inTransaction())throw new LogicException('HTTP inside transaction');
        if(str_contains($url,'oauth/token')){usleep(250000);return ['status'=>200,'body'=>'{"access_token":"synthetic-token","token_type":"Bearer","expires_in":600}'];}
        if($method!=='PATCH')throw new LogicException('unexpected non-PATCH request');
        if(file_put_contents($input['journal'],"synthetic PATCH\n",FILE_APPEND|LOCK_EX)===false)throw new RuntimeException('journal failed');
        return ['status'=>200,'body'=>json_encode(['numeroContratoCobranca'=>1234,'dataAtualizacao'=>date('d.m.Y'),'horarioAtualizacao'=>'13:20:00'])];
    };
    $api=new \BancoBrasil\ApiService($db,$p,fn($v)=>$cipher->encrypt($v),fn($v)=>$cipher->decrypt($v),$transport);
    try{echo $api->amendDue($input['issuer'],7,$input['origin'],$input['change'],$input['key']);}
    catch(DomainException $e){if(!in_array($e->getMessage(),['bb_api_access_denied','bb_api_operation_in_progress','bb_api_amendment_requires_query'],true))throw $e;echo 'denied';}exit(0);
}
require __DIR__.'/banco_brasil_api_amendment.php';$observer=amendRaceDb($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
$journal=tempnam(sys_get_temp_dir(),'bb-amend-race-');if($journal===false)throw new RuntimeException('journal unavailable');
try{
    foreach(['same_key','different_keys','revocation'] as $mode){
        $inv=$s->create($a,7,$apiInput,hash('sha256','amend-race-invoice-'.$mode));$orig=$amendApi->issue($a,7,$profile,$inv,$address,hash('sha256','amend-race-issue-'.$mode));$amendApi->query($a,7,$orig,hash('sha256','amend-race-query-'.$mode));
        $change=['due_date'=>(new DateTimeImmutable($apiInput['due_date']))->modify('+4 days')->format('Y-m-d'),'reason'=>'Synthetic concurrent extension'];
        $before=substr_count(file_get_contents($journal),"synthetic PATCH\n");$db->trans_begin();$db->query("SELECT id FROM {$p}issuers_lock WHERE id=1 FOR UPDATE");$workers=[];
        try{
            for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($proc))throw new RuntimeException('worker unavailable');fwrite($pipes[0],json_encode(['issuer'=>$a,'origin'=>$orig,'change'=>$change,'key'=>hash('sha256','amend-race-'.$mode.($mode==='different_keys'?$i:'')),'cipher_key'=>bin2hex($nativeKey),'journal'=>$journal],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
            $deadline=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);essentialCheck($waiters>=2,'both amendment writers visibly await the installation lock');
            if($mode==='revocation')$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7");$db->trans_commit();$out=[];
            foreach($workers as [$proc,$pipes]){$out[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$err==='','amendment worker exits without diagnostic leakage');}
            if($mode==='same_key')essentialCheck($out[0]===$out[1]&&ctype_digit($out[0]),'same-key race shares one durable amendment');
            if($mode==='different_keys')essentialCheck(count(array_filter($out,fn($v)=>$v==='denied'))===1&&count(array_filter($out,'ctype_digit'))===1,'competing keys cannot send two instructions for one origin');
            if($mode==='revocation')essentialCheck($out===['denied','denied'],'both waiting amendment workers observe actor revocation');
            essentialCheck(substr_count(file_get_contents($journal),"synthetic PATCH\n")===$before+($mode==='revocation'?0:1),'exactly one PATCH for live authority and zero after revocation');
            $db->query("UPDATE {$p}staff SET active=1 WHERE staffid=7");
        }finally{if($db->pdo->inTransaction())$db->trans_rollback();}
    }
}finally{unlink($journal);}
echo 'BB amendment concurrency: '.$GLOBALS['essentialChecks'].' cumulative actual DB checks; synthetic transport only.'.PHP_EOL;
