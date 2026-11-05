<?php
/** In-memory synthetic upload only; called inside the disposable browser fixture. */
if (PHP_SAPI!=='cli'||getenv('ISSUERS_BROWSER_DB_NAME')!=='argws_upgrade_ci') { exit(2); }
require __DIR__.'/support.php';
$tax=$argv[1]??'';
if ($tax!==''&&!preg_match('/^[A-Z0-9]{12}[0-9]{2}$/D',$tax)) { exit(2); }
$fixture=a1fixture($tax===''?null:$tax);
if (($argv[2]??'modern')==='legacy') {
    // Synthetic key/certificate travel only through pipes, exactly as the upload parser does.
    $process=proc_open(['/usr/bin/openssl','pkcs12','-export','-legacy','-passout','fd:3'],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w'],3=>['pipe','r']],$pipes);
    if(!is_resource($process))throw new RuntimeException('synthetic_export_unavailable');
    fwrite($pipes[3],$fixture['password']."\n");fclose($pipes[3]);
    fwrite($pipes[0],$fixture['private'].$fixture['certificate']);fclose($pipes[0]);
    $legacy=stream_get_contents($pipes[1]);fclose($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[2]);
    if(proc_close($process)!==0||$legacy==='')throw new RuntimeException('synthetic_export_failed');
    $fixture['pfx']=$legacy;
}
echo json_encode(['pfx'=>base64_encode($fixture['pfx']),'password'=>$fixture['password']],JSON_THROW_ON_ERROR);
