<?php
/** New119 amendment races: two real processes and observable InnoDB waiters. */
declare(strict_types=1);
require_once __DIR__.'/issuers_core_support.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/ApiService.php';
require_once BASEPATH.'libraries/Encryption.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:')){fwrite(STDERR,"Isolated MySQL/MariaDB DSN required.\n");exit(2);}
function bb439RaceDb(string $prefix):EssentialTestDb{return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$prefix);}
if(($argv[1]??'')==='worker'){
    $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);
    $work=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$db=bb439RaceDb($p);$GLOBALS['essentialPrefix']=$p;
    $cipher=new CI_Encryption(['driver'=>'openssl','cipher'=>'aes-256','mode'=>'cbc','key'=>hex2bin($work['cipher_key'])]);
    $transport=static function($method,$url,$headers,$body)use($db,$work):array{
        if($db->pdo->inTransaction())throw new LogicException('bank HTTP inside transaction');
        if(str_contains($url,'oauth/token')){usleep(250000);return ['status'=>200,'body'=>'{"access_token":"synthetic-token","token_type":"Bearer","expires_in":600}'];}
        if($method!=='PATCH')throw new LogicException('only synthetic PATCH allowed');
        if(file_put_contents($work['journal'],$work['operation']." PATCH\n",FILE_APPEND|LOCK_EX)===false)throw new RuntimeException('synthetic PATCH journal failed');
        return ['status'=>200,'body'=>json_encode(['numeroContratoCobranca'=>1234,'dataAtualizacao'=>gmdate('d.m.Y'),'horarioAtualizacao'=>'14:21:05'],JSON_THROW_ON_ERROR)];
    };
    $api=new \BancoBrasil\ApiService($db,$p,fn($value)=>$cipher->encrypt($value),fn($value)=>$cipher->decrypt($value),$transport);
    $method=match($work['operation']){'abatement'=>'amendAbatement','address'=>'amendAddress','discount'=>'amendDiscount',default=>throw new RuntimeException('unexpected operation')};
    try{echo $api->$method($work['issuer'],7,$work['origin'],$work['intent'],$work['key']);}
    catch(DomainException $error){if(!in_array($error->getMessage(),['bb_api_access_denied','bb_api_operation_in_progress','bb_api_abatement_requires_query','bb_api_address_requires_query','bb_api_discount_amend_requires_query'],true))throw $error;echo 'denied';}
    exit(0);
}
require __DIR__.'/banco_brasil_resume_439_mysql.php';
$observer=bb439RaceDb($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
$journal=tempnam(sys_get_temp_dir(),'bb119-amendment-races-');if($journal===false)throw new RuntimeException('journal unavailable');
try{
    foreach(['abatement','address','discount'] as $operation)foreach(['same_key','different_keys','revocation'] as $mode){
        $tag=$operation.':'.$mode;$raceInvoice=$s->create($a,7,$apiInput,$bb439Key('race-invoice-'.$tag));
        $raceOrigin=$bb439Api->issue($a,7,$operation==='discount'?$bb439DiscountProfile:$profile,$raceInvoice,$operation==='discount'?$bb439DiscountTitle:$address,$bb439Key('race-origin-'.$tag));
        $bb439Api->query($a,7,$raceOrigin,$bb439Key('race-query-'.$tag));
        $intent=match($operation){'abatement'=>$bb439Abat,'address'=>$bb439Address,'discount'=>$bb439Discount};
        $before=substr_count(file_get_contents($journal)," PATCH\n");$db->trans_begin();$db->query("SELECT id FROM {$p}issuers_lock WHERE id=1 FOR UPDATE");$workers=[];
        try{
            for($i=0;$i<2;++$i){$pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($process))throw new RuntimeException('worker unavailable');fwrite($pipes[0],json_encode(['issuer'=>$a,'origin'=>$raceOrigin,'operation'=>$operation,'intent'=>$intent,'key'=>$bb439Key('race-key-'.$tag.($mode==='different_keys'?$i:'')),'cipher_key'=>bin2hex($nativeKey),'journal'=>$journal],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$process,$pipes];}
            $deadline=microtime(true)+15;$waiters=0;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);
            essentialCheck($waiters>=2,'119 '.$tag.' observes both real writers waiting on InnoDB installation lock');
            if($mode==='revocation')$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7");$db->trans_commit();$outputs=[];
            foreach($workers as [$process,$pipes]){$outputs[]=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($process)===0&&$errors==='','119 '.$tag.' worker exits with no diagnostic leak');}
            if($mode==='same_key')essentialCheck($outputs[0]===$outputs[1]&&ctype_digit($outputs[0]),'119 '.$operation.' same key converges to one durable intention');
            elseif($mode==='different_keys')essentialCheck(count(array_filter($outputs,fn($v)=>$v==='denied'))===1&&count(array_filter($outputs,'ctype_digit'))===1,'119 '.$operation.' different keys produce only one actionable intention');
            else essentialCheck($outputs===['denied','denied'],'119 '.$operation.' both writers honor committed revocation after actual lock wait');
            essentialCheck(substr_count(file_get_contents($journal)," PATCH\n")===$before+($mode==='revocation'?0:1),'119 '.$tag.' transmits exactly one synthetic PATCH or none after revocation');
            essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$raceInvoice])->row()->n===0,'119 '.$tag.' never fabricates financial credit');
            $db->query("UPDATE {$p}staff SET active=1 WHERE staffid=7");
        }finally{if($db->pdo->inTransaction())$db->trans_rollback();}
    }
}finally{unlink($journal);}
echo 'BB119 reconstructed concurrency: '.$GLOBALS['essentialChecks'].' cumulative checks, 9 scenarios with two observed waiters each; synthetic bank only.'.PHP_EOL;
