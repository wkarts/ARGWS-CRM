<?php

declare(strict_types=1);
namespace BancoBrasil;

/** Evidence from TLS variables populated by the server, never from HTTP request headers. */
final class MtlsEvidence
{
    private function __construct(public readonly string $fingerprint){}
    public static function fromNativeTls(array $server,bool $nativeTlsConfigured):self
    {
        if(!$nativeTlsConfigured||($server['HTTPS']??null)!=='on'||($server['SSL_CLIENT_VERIFY']??null)!=='SUCCESS'||!is_string($server['SSL_CLIENT_CERT']??null)||strlen($server['SSL_CLIENT_CERT'])>32768)throw new \DomainException('bb_webhook_transport_denied');
        $pem=$server['SSL_CLIENT_CERT'];$cert=@openssl_x509_read($pem);$info=$cert?@openssl_x509_parse($cert):false;$fingerprint=$cert?@openssl_x509_fingerprint($cert,'sha256'):false;
        $now=time();if(!$info||!is_string($fingerprint)||!preg_match('/^[a-f0-9]{64}$/D',$fingerprint)||($info['validFrom_time_t']??PHP_INT_MAX)>$now||($info['validTo_time_t']??0)<=$now)throw new \DomainException('bb_webhook_transport_denied');
        return new self($fingerprint);
    }
}
