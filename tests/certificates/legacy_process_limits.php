<?php
require __DIR__.'/support.php';
require_once APPPATH.'services/issuers/IssuerCertificateLegacyImport.php';
$previous=getenv('ARGWS_CERTIFICATE_LEGACY_IMPORT');
$method=new ReflectionMethod(IssuerCertificateLegacyImport::class,'exchange');
try {
    putenv('ARGWS_CERTIFICATE_LEGACY_IMPORT');
    a1check(IssuerCertificateLegacyImport::enabled(),'isolated compatibility is enabled by default');
    putenv('ARGWS_CERTIFICATE_LEGACY_IMPORT=0');
    a1check(!IssuerCertificateLegacyImport::enabled(),'explicit opt-out disables compatibility');
    putenv('ARGWS_CERTIFICATE_LEGACY_IMPORT=true');
    a1check(!IssuerCertificateLegacyImport::enabled(),'invalid configured values fail closed');
    foreach ([
        'stdout cap'=>'fwrite(STDOUT,str_repeat("x",4194305));',
        'stderr cap'=>'fwrite(STDERR,str_repeat("x",16385));',
        'nonzero exit'=>'exit(7);',
        'deadline'=>'sleep(30);',
        'closed channels'=>'fclose(STDIN);fclose(STDOUT);fclose(STDERR);sleep(30);',
    ] as $label=>$code) {
        $start=microtime(true);
        $code='stream_get_contents(fopen("php://fd/3","r"));stream_get_contents(STDIN);'.$code;
        a1reject(fn()=>$method->invoke(null,[PHP_BINARY,'-r',$code],'synthetic','synthetic'),'issuer_certificate_legacy_unavailable',$label.' fails closed');
        a1check(microtime(true)-$start<8,$label.' bounded cleanup');
        if (in_array($label,['deadline','closed channels'],true)) { a1check(microtime(true)-$start>=4,'deadline actually exercised'); }
    }
    $failure=new ReflectionMethod(IssuerCertificateLegacyImport::class,'failure');
    a1check($failure->invoke(null,"error:0308010C:digital envelope routines::unsupported:source:1:\n")==='issuer_certificate_algorithm_unsupported','allowlisted unsupported reason permits fallback');
    a1check($failure->invoke(null,"error:0308010C:digital envelope routines::unsupported:source:1:\nMac verify error: invalid password?\n")==='issuer_certificate_authentication_failed','authentication takes precedence over unsupported');
    a1check($failure->invoke(null,'unsupported user text')==='issuer_certificate_legacy_unavailable','unknown diagnostic never triggers fallback');
    $deadline=hrtime(true)+200000000;
    $code='stream_get_contents(fopen("php://fd/3","r"));stream_get_contents(STDIN);sleep(30);';
    $start=microtime(true);
    a1reject(fn()=>$method->invoke(null,[PHP_BINARY,'-r',$code],'synthetic','synthetic',$deadline),'issuer_certificate_legacy_unavailable','remaining shared deadline enforced');
    a1check(microtime(true)-$start<1,'second attempt cannot reset wall budget');
    $pidfile=tempnam(sys_get_temp_dir(),'a1-pid-only-'); chmod($pidfile,0600);
    try {
        $code='file_put_contents('.var_export($pidfile,true).',getmypid());stream_get_contents(fopen("php://fd/3","r"));stream_get_contents(STDIN);sleep(30);';
        a1reject(fn()=>$method->invoke(null,[PHP_BINARY,'-r',$code],'synthetic','synthetic'),'issuer_certificate_legacy_unavailable','deadline kills direct child');
        $pid=(int)file_get_contents($pidfile);
        a1check($pid>0&&!posix_kill($pid,0),'timed-out child is reaped, not orphaned');
    } finally { unlink($pidfile); }
    $commandMethod=new ReflectionMethod(IssuerCertificateLegacyImport::class,'command');
    $command=$commandMethod->invoke(null);
    $prefix=array_slice($command,0,array_search('--',$command,true)+1);
    $bounded=array_merge($prefix,[PHP_BINARY,'-n','-r','stream_get_contents(fopen("php://fd/3","r"));stream_get_contents(STDIN);echo "ready";']);
    a1check($method->invoke(null,$bounded,'synthetic','synthetic')==='ready','test process starts under exact production resource caps');
    foreach (['CPU'=>'while(true){}','address space'=>'ini_set("memory_limit","-1");str_repeat("x",268435456);'] as $label=>$code) {
        $start=microtime(true);
        $code='stream_get_contents(fopen("php://fd/3","r"));stream_get_contents(STDIN);'.$code;
        $bounded=array_merge($prefix,[PHP_BINARY,'-n','-r',$code]);
        // CPU time excludes scheduler waits. Keep this resource-cap test independent
        // of the production wall timeout, already exercised above.
        $resourceDeadline=hrtime(true)+30000000000;
        a1reject(fn()=>$method->invoke(null,$bounded,'synthetic','synthetic',$resourceDeadline),'issuer_certificate_legacy_unavailable','real prlimit '.$label.' enforced');
        a1check(microtime(true)-$start<29,'real prlimit '.$label.' terminates before test wall deadline');
        if ($label==='CPU') { a1check(microtime(true)-$start>=2,'CPU budget actually exercised'); }
    }
    // Enough data to exceed pipe capacity; child consumes both channels while output grows.
    $code='$password=stream_get_contents(fopen("php://fd/3","r"));$input=stream_get_contents(STDIN);if($password!=="synthetic\\n"||strlen($input)!==200000)exit(2);fwrite(STDOUT,"safe-synthetic-result");';
    a1check($method->invoke(null,[PHP_BINARY,'-r',$code],str_repeat('x',200000),'synthetic')==='safe-synthetic-result','separate password pipe and large input do not deadlock');
} finally {
    if ($previous===false) { putenv('ARGWS_CERTIFICATE_LEGACY_IMPORT'); } else { putenv('ARGWS_CERTIFICATE_LEGACY_IMPORT='.$previous); }
}
echo "Legacy process boundary tests complete; no real certificate used.\n";
