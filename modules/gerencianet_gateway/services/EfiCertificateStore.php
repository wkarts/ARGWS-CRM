<?php

declare(strict_types=1);
defined('BASEPATH') or exit('No direct script access allowed');

/** Converts uploaded PKCS#12 offline; only authenticated ciphertext is persisted. */
final class EfiCertificateStore
{
    public const MAX_BYTES = 2097152;

    public static function seal(string $bytes, string $password, $encryption): string
    {
        if ($bytes === '' || strlen($bytes) > self::MAX_BYTES || strlen($password) > 4096 || str_contains($password,"\0")) {
            throw new DomainException('efi_certificate_invalid');
        }
        $parts=[];
        try {
            if (!@openssl_pkcs12_read($bytes,$parts,$password) || !is_string($parts['pkey']??null) || !is_string($parts['cert']??null)
                || !@openssl_x509_check_private_key($parts['cert'],$parts['pkey'])) { throw new DomainException('efi_certificate_invalid'); }
            $metadata=@openssl_x509_parse($parts['cert']);
            if (!is_array($metadata) || time() < ($metadata['validFrom_time_t']??PHP_INT_MAX) || time() >= ($metadata['validTo_time_t']??0)) { throw new DomainException('efi_certificate_expired'); }
            $pem=$parts['cert']."\n";
            foreach($parts['extracerts']??[] as $certificate) { if(is_string($certificate))$pem.=$certificate."\n"; }
            $pem.=$parts['pkey']."\n";
            $cipher=$encryption->encrypt($pem);
            if(!is_string($cipher)||$cipher===''||$cipher===$pem||$encryption->decrypt($cipher)!==$pem) { throw new DomainException('efi_certificate_encryption_failed'); }
            return $cipher;
        } finally { $parts=[]; while(openssl_error_string()!==false){} }
    }

    /** A private short-lived SDK file, never media/uploads. Caller deletes in finally. */
    public static function temporary(string $cipher,$encryption): string
    {
        $pem=$encryption->decrypt($cipher);
        if(!is_string($pem)||$pem===''||strlen($pem)>self::MAX_BYTES||!str_contains($pem,'PRIVATE KEY')) { throw new DomainException('efi_certificate_unavailable'); }
        $path=tempnam(sys_get_temp_dir(),'argws-efi-');
        if(!is_string($path))throw new RuntimeException('efi_certificate_storage_failed');
        try {
            if(!chmod($path,0600)||file_put_contents($path,$pem,LOCK_EX)!==strlen($pem)||((fileperms($path)&0777)!==0600))throw new RuntimeException('efi_certificate_storage_failed');
            return $path;
        } catch(Throwable $error) { @unlink($path); throw $error; }
        finally { $pem=''; }
    }
}
