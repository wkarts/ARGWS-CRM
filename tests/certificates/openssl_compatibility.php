<?php
// Synthetic material only. Requires OpenSSL 3 CLI and its legacy provider in CI.
require __DIR__.'/support.php';
$previous=getenv('ARGWS_CERTIFICATE_LEGACY_IMPORT');
putenv('ARGWS_CERTIFICATE_LEGACY_IMPORT');
$fixture = a1fixture();
$directory = sys_get_temp_dir().'/a1-compat-'.bin2hex(random_bytes(12));
if (!mkdir($directory,0700)) { throw new RuntimeException('synthetic_directory_failed'); }
try {
    foreach (['key.pem'=>$fixture['private'],'cert.pem'=>$fixture['certificate']] as $name=>$bytes) {
        $path=$directory.'/'.$name;
        $file=fopen($path,'x'); chmod($path,0600); fwrite($file,$bytes); fclose($file);
    }
    // Only this fixture generation process enables legacy; the PHP parser does not.
    $process=proc_open(['openssl','pkcs12','-export','-legacy','-inkey',$directory.'/key.pem','-in',$directory.'/cert.pem','-out',$directory.'/legacy.p12','-passout','stdin'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if (!is_resource($process)) { throw new RuntimeException('synthetic_cli_unavailable'); }
    fwrite($pipes[0],$fixture['password']."\n"); fclose($pipes[0]);
    stream_get_contents($pipes[1]); fclose($pipes[1]);
    stream_get_contents($pipes[2]); fclose($pipes[2]);
    a1check(proc_close($process)===0,'synthetic legacy export available');
    $legacy=file_get_contents($directory.'/legacy.p12');
    a1reject(fn()=>IssuerCertificateParser::inspect($legacy,$fixture['password'],'11222333000181'),'issuer_certificate_algorithm_unsupported','legacy unavailable has distinct safe category');
    a1reject(fn()=>IssuerCertificateParser::inspect($legacy,'wrong','11222333000181'),'issuer_certificate_authentication_failed','legacy wrong password does not imply unsupported algorithm');
    a1reject(fn()=>IssuerCertificateParser::inspect('invalid','wrong','11222333000181'),'issuer_certificate_parse_failed','malformed file remains generic');
    a1check(openssl_error_string()===false,'error queue drained after failures');
    a1check(IssuerCertificateParser::inspect($fixture['pfx'],$fixture['password'],'11222333000181')['document_status']==='matched','modern upload succeeds after failed legacy request');
    // An unrelated stale crypto error must not contaminate the next diagnostic.
    @openssl_pkey_get_private('not a key');
    a1reject(fn()=>IssuerCertificateParser::inspect($fixture['pfx'],'wrong','11222333000181'),'issuer_certificate_authentication_failed','stale errors isolated between requests');
    a1check(openssl_error_string()===false,'error queue drained after authentication failure');
    putenv('ARGWS_CERTIFICATE_LEGACY_IMPORT=1');
    $modern=IssuerCertificateParser::inspect($fixture['pfx'],$fixture['password'],'11222333000181');
    a1check(IssuerCertificateParser::inspect($legacy,$fixture['password'],'11222333000181')===$modern,'opt-in legacy produces identical validated metadata');
    a1reject(fn()=>IssuerCertificateParser::inspect($legacy,'wrong','11222333000181'),'issuer_certificate_authentication_failed','opt-in does not bypass password integrity');
    a1reject(fn()=>IssuerCertificateParser::inspect($legacy,$fixture['password'],'22333444000155'),'issuer_certificate_document_mismatch','opt-in retains company binding');
    a1reject(fn()=>IssuerCertificateParser::inspect($legacy,$fixture['password'],'11222333000181',strtotime($modern['valid_to'].' UTC')),'issuer_certificate_expired','opt-in retains validity checks');
    a1reject(fn()=>IssuerCertificateLegacyImport::read($legacy,"line\nsecret"),'issuer_certificate_legacy_unavailable','newline password is never silently truncated');
    a1check(openssl_error_string()===false,'fallback parser drains crypto errors after success and failure');
    $partsMethod=new ReflectionMethod(IssuerCertificateLegacyImport::class,'parts');
    $other=a1fixture('22333444000155');
    $selected=$partsMethod->invoke(null,$other['certificate'].$fixture['certificate'].$fixture['private']);
    a1check($selected['cert']===trim($fixture['certificate']),'chain selects certificate matching private key, not first certificate');
    a1reject(fn()=>$partsMethod->invoke(null,$fixture['certificate'].$fixture['private'].$other['private']),'issuer_certificate_key_required','ambiguous multiple private keys rejected');
    a1reject(fn()=>$partsMethod->invoke(null,$fixture['certificate']),'issuer_certificate_key_required','legacy PEM missing private key rejected');
    a1reject(fn()=>$partsMethod->invoke(null,$other['certificate'].$fixture['private']),'issuer_certificate_key_required','legacy PEM key mismatch rejected');
    $db=a1db(); $manager=new IssuerCertificateManager($db,'a1_',a1encryption());
    $manager->replace(10,2,0,$legacy,$fixture['password']); $saved=a1row($db,10);
    a1check($manager->page(10,2)['revision']===1&&$saved['pfx_cipher']!==$legacy,'revision-zero legacy import stores encrypted credential');
    a1reject(fn()=>$manager->replace(10,2,1,$legacy,'wrong'),'issuer_certificate_authentication_failed','failed replacement stays atomic');
    a1check(a1row($db,10)===$saved,'failed replacement preserves cipher and revision');
    putenv('ARGWS_CERTIFICATE_LEGACY_IMPORT');
    a1reject(fn()=>IssuerCertificateParser::inspect($legacy,$fixture['password'],'11222333000181'),'issuer_certificate_algorithm_unsupported','turning opt-in off restores rejection without provider leak');
    a1check($manager->page(10,2)['revision']===1,'stored metadata remains readable without legacy subprocess');
} finally {
    if ($previous===false) { putenv('ARGWS_CERTIFICATE_LEGACY_IMPORT'); } else { putenv('ARGWS_CERTIFICATE_LEGACY_IMPORT='.$previous); }

    foreach (glob($directory.'/*') as $path) { unlink($path); }
    rmdir($directory);
}
echo "OpenSSL compatibility tests complete. Synthetic certificates only.\n";
