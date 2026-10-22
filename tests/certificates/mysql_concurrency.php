<?php
require __DIR__.'/support.php';
if (!getenv('A1_TEST_DSN') || !function_exists('pcntl_fork')) { fwrite(STDERR,"MySQL DSN and pcntl are required; concurrency was NOT executed.\n"); exit(2); }
$db=a1db();$a=a1fixture();$manager=new IssuerCertificateManager($db,'a1_',a1encryption());$manager->replace(10,2,0,$a['pfx'],$a['password']);
/** Use separate fresh MySQL connections; no inherited PDO socket is used after fork. */
function a1connection(): IssuerTestDb { return new IssuerTestDb(a1mysql()); }
function a1race(string $operation,array $fixture): array
{
    $dir=sys_get_temp_dir().'/a1-concurrency-'.bin2hex(random_bytes(6));mkdir($dir,0700);$pids=[];
    for($i=0;$i<2;++$i){
        $pid=pcntl_fork();if($pid===-1)throw new RuntimeException('fork failed');
        if($pid===0){
            while(!is_file($dir.'/go'))usleep(10000);
            try{$db=a1connection();$manager=new IssuerCertificateManager($db,'a1_',a1encryption());
                if($operation==='replace')$manager->replace(10,2,1,$fixture['pfx'],$fixture['password']);
                else $manager->remove(10,2,2);
                $result='ok';
            }catch(Throwable $error){$result=$error->getMessage();}
            file_put_contents($dir.'/result-'.$i,$result);exit(0);
        }$pids[]=$pid;
    }
    touch($dir.'/go');foreach($pids as$pid){pcntl_waitpid($pid,$status);if(pcntl_wexitstatus($status)!==0)throw new RuntimeException('child failed');}
    $results=[file_get_contents($dir.'/result-0'),file_get_contents($dir.'/result-1')];foreach(glob($dir.'/*')as$file)unlink($file);rmdir($dir);sort($results);return $results;
}
// Close parent handles before forking to avoid inherited MySQL connection shutdown effects.
unset($manager,$db);
a1check(a1race('replace',$a)===['issuer_certificate_stale','ok'],'MySQL concurrent replacements have one winner');
$db=a1connection();a1check((int)a1row($db,10)['revision']===2,'MySQL revision advanced exactly once');unset($db);
a1check(a1race('remove',$a)===['issuer_certificate_stale','ok'],'MySQL concurrent removals have one winner');
$db=a1connection();a1check(a1row($db,10)['pfx_cipher']===''&&(int)a1row($db,10)['revision']===3,'MySQL removal atomic and monotonic');
$manager=new IssuerCertificateManager($db,'a1_',a1encryption());$manager->replace(10,2,3,$a['pfx'],$a['password']);unset($manager,$db);
// Hold the same existing ownership lock as staff/registry writers, revoke access,
// and prove a waiting certificate writer rechecks the committed permission state.
$dir=sys_get_temp_dir().'/a1-revoke-'.bin2hex(random_bytes(6));mkdir($dir,0700);
$pid=pcntl_fork();if($pid===-1)throw new RuntimeException('fork failed');
if($pid===0){while(!is_file($dir.'/go'))usleep(10000);try{$db=a1connection();$manager=new IssuerCertificateManager($db,'a1_',a1encryption());$manager->replace(10,2,4,$a['pfx'],$a['password']);$result='unexpected';}catch(Throwable$e){$result=$e->getMessage();}file_put_contents($dir.'/result',$result);exit(0);}
$db=a1connection();$db->trans_begin();$db->query('SELECT id FROM a1_issuers_lock WHERE id=1 FOR UPDATE');touch($dir.'/go');usleep(300000);$db->query("DELETE FROM a1_issuers_resource_permissions WHERE issuer_id=10 AND staff_id=2 AND feature='settings'");$db->trans_commit();pcntl_waitpid($pid,$status);
a1check(file_get_contents($dir.'/result')==='issuer_certificate_denied','MySQL revocation while waiting for lock denies mutation');
a1check((int)a1row($db,10)['revision']===4,'revoked writer leaves previous material intact');foreach(glob($dir.'/*')as$file)unlink($file);rmdir($dir);
echo "MySQL/InnoDB concurrency assertions complete.\n";
