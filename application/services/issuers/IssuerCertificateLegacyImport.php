<?php

declare(strict_types=1);

/** Isolated compatibility for inspection only; never changes PHP providers. */
final class IssuerCertificateLegacyImport
{
    private const MAX_OUTPUT = 4194304;
    private const MAX_ERRORS = 16384;

    public static function enabled(): bool
    {
        // Default compatibility never loads the legacy provider into PHP/TLS.
        // Explicit 0 (or an invalid configured value) keeps the operational opt-out.
        $configured = getenv('ARGWS_CERTIFICATE_LEGACY_IMPORT');
        return $configured === false || $configured === '' || $configured === '1';
    }

    public static function read(string $pfx, string $password): array
    {
        if (!self::enabled()) { throw new DomainException('issuer_certificate_algorithm_unsupported'); }
        if ($pfx === '' || strlen($pfx)>2097152 || strlen($password)>4096 || strpbrk($password,"\r\n\0")!==false) {
            // OpenSSL fd passwords are line-delimited. Never silently truncate a password.
            throw new DomainException('issuer_certificate_legacy_unavailable');
        }
        if (!function_exists('proc_open') || !is_executable('/usr/bin/openssl') || !is_executable('/usr/bin/prlimit')) {
            throw new DomainException('issuer_certificate_legacy_unavailable');
        }
        // One shared wall budget; CPU allocations sum to the original three seconds.
        $deadline=hrtime(true)+5000000000;
        try {
            $pem=self::exchange(self::command(false,1),$pfx,$password,$deadline);
        } catch (DomainException $error) {
            if ($error->getMessage()!=='issuer_certificate_algorithm_unsupported') { throw $error; }
            $pem=self::exchange(self::command(true,2),$pfx,$password,$deadline);
        }
        return self::parts($pem);
    }

    private static function parts(string $pem): array
    {
        try {
            preg_match_all('/-----BEGIN (PRIVATE KEY|RSA PRIVATE KEY|EC PRIVATE KEY)-----\s+[A-Za-z0-9+\/=\r\n]+-----END \1-----/',$pem,$keys);
            preg_match_all('/-----BEGIN CERTIFICATE-----\s+[A-Za-z0-9+\/=\r\n]+-----END CERTIFICATE-----/',$pem,$certs);
            if (count($keys[0])!==1) { throw new DomainException('issuer_certificate_key_required'); }
            $private=@openssl_pkey_get_private($keys[0][0]);
            if ($private===false) { throw new DomainException('issuer_certificate_key_required'); }
            foreach ($certs[0] as $cert) {
                if (@openssl_x509_check_private_key($cert,$private)) { return ['cert'=>$cert,'pkey'=>$keys[0][0]]; }
            }
            throw new DomainException('issuer_certificate_key_required');
        } finally { $pem=''; $keys=[]; $certs=[]; $private=null; }
    }

    private static function command(bool $legacy = false, int $cpu = 3): array
    {
        return array_merge([
            '/usr/bin/prlimit','--cpu='.$cpu,'--as=134217728','--fsize=0','--core=0','--nofile=16','--',
            '/usr/bin/openssl','pkcs12','-nodes','-passin','fd:3',
        ], $legacy ? ['-legacy'] : []);
    }

    /** Classify bounded diagnostics locally; never expose raw stderr or retry authentication. */
    private static function failure(string $errors): string
    {
        if (preg_match('/(?:^|\n)Mac verify error: invalid password\?\r?(?:\n|$)/', $errors)
            || preg_match('/:PKCS12 routines:[^:\r\n]*:mac verify failure:/i', $errors)) {
            return 'issuer_certificate_authentication_failed';
        }
        if (preg_match('/:digital envelope routines:[^:\r\n]*:unsupported:/i', $errors)
            || preg_match('/:PKCS12 routines:[^:\r\n]*:unsupported algorithm:/i', $errors)) {
            return 'issuer_certificate_algorithm_unsupported';
        }
        return 'issuer_certificate_legacy_unavailable';
    }

    /** Bounded nonblocking pipes avoid both input/output deadlocks and disk material. */
    private static function exchange(array $command,string $pfx,string $password, ?int $deadline = null): string
    {
        $pipes=[]; $process=null; $output=''; $errors=''; $exit=null;
        $input=[0=>$pfx,3=>$password."\n"]; $positions=[0=>0,3=>0];
        try {
            $deadline=$deadline ?? hrtime(true)+5000000000;
            if (hrtime(true)>=$deadline) { throw new DomainException('issuer_certificate_legacy_unavailable'); }
            // Array command bypasses the shell. No inherited configuration/module path.
            $process=@proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w'],3=>['pipe','r']],$pipes,null,['OPENSSL_CONF'=>'/dev/null']);
            if (!is_resource($process)) { throw new DomainException('issuer_certificate_legacy_unavailable'); }
            foreach ($pipes as $pipe) { stream_set_blocking($pipe,false); }
            while (true) {
                if (hrtime(true)>=$deadline) { throw new DomainException('issuer_certificate_legacy_unavailable'); }
                $read=[]; $write=[]; $except=null;
                foreach ([1,2] as $fd) { if (isset($pipes[$fd])) { $read[]=$pipes[$fd]; } }
                foreach ([0,3] as $fd) {
                    if (!isset($pipes[$fd])) { continue; }
                    if ($positions[$fd]===strlen($input[$fd])) { fclose($pipes[$fd]); unset($pipes[$fd]); }
                    else { $write[]=$pipes[$fd]; }
                }
                if ($read || $write) {
                    if (@stream_select($read,$write,$except,0,100000)===false) { throw new DomainException('issuer_certificate_legacy_unavailable'); }
                    foreach ($write as $pipe) {
                        $fd=array_search($pipe,$pipes,true);
                        $written=@fwrite($pipe,substr($input[$fd],$positions[$fd],8192));
                        if ($written===false) { throw new DomainException('issuer_certificate_legacy_unavailable'); }
                        $positions[$fd]+=$written;
                    }
                    foreach ($read as $pipe) {
                        $fd=array_search($pipe,$pipes,true); $chunk=fread($pipe,8192);
                        if ($chunk===false) { throw new DomainException('issuer_certificate_legacy_unavailable'); }
                        if ($fd===1) { $output.=$chunk; }
                        else { $errors.=$chunk; }
                        if (strlen($output)>self::MAX_OUTPUT || strlen($errors)>self::MAX_ERRORS) { throw new DomainException('issuer_certificate_legacy_unavailable'); }
                        if (feof($pipe)) { fclose($pipe); unset($pipes[$fd]); }
                    }
                }
                if (!$read && !$write) { usleep(10000); }
                $status=proc_get_status($process);
                if (!$status['running']) {
                    if ($exit===null) { $exit=$status['exitcode']; }
                    if (!isset($pipes[1])&&!isset($pipes[2])) { break; }
                }
            }
            if ($exit!==0) { throw new DomainException(self::failure($errors)); }
            if ($positions[0]!==strlen($pfx) || $positions[3]!==strlen($input[3])) {
                throw new DomainException('issuer_certificate_legacy_unavailable');
            }
            return $output;
        } finally {
            foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
            if (is_resource($process)) {
                $status=proc_get_status($process);
                if ($status['running']) { proc_terminate($process,9); }
                proc_close($process);
            }
            $input=[]; $output=''; $errors=''; $password=''; $pfx='';
        }
    }
}
