<?php

declare(strict_types=1);
require_once __DIR__.'/IssuerCertificateParser.php';

/** Uses the CRM's authenticated CI encryption. No plaintext-export or callback API. */
final class IssuerCertificateVault
{
    public function __construct(private CI_Encryption $encryption) {}

    public function seal(int $issuer, int $revision, array $metadata, string $pfx, string $password): array
    {
        $result = [];
        foreach (['pfx'=>$pfx,'password'=>$password] as $purpose=>$value) {
            $plain = json_encode($this->context($issuer,$revision,$metadata,$purpose) + ['value'=>base64_encode($value)], JSON_THROW_ON_ERROR);
            $cipher = $this->encryption->encrypt($plain);
            if (!is_string($cipher) || $cipher === '' || $cipher === $plain) { throw new DomainException('issuer_certificate_crypto_unavailable'); }
            $result[$purpose.'_cipher'] = $cipher;
        }
        $this->assertBound($issuer,$revision,$metadata,$result);
        return $result;
    }

    /** Only validates an authenticated envelope; never returns the contents. */
    public function assertBound(int $issuer, int $revision, array $metadata, array $ciphers): void
    {
        try {
            foreach (['pfx','password'] as $purpose) {
                $cipher = $ciphers[$purpose.'_cipher'] ?? null;
                if (!is_string($cipher) || $cipher === '' || strlen($cipher) > 4000000) { throw new RuntimeException(); }
                $plain = $this->encryption->decrypt($cipher);
                if (!is_string($plain)) { throw new RuntimeException(); }
                $doc = json_decode($plain, true, 8, JSON_THROW_ON_ERROR);
                $context = $this->context($issuer,$revision,$metadata,$purpose);
                if (!is_array($doc) || array_keys($doc) !== array_merge(array_keys($context), ['value'])) { throw new RuntimeException(); }
                foreach ($context as $key=>$value) { if ($doc[$key] !== $value) { throw new RuntimeException(); } }
                $value = is_string($doc['value']) ? base64_decode($doc['value'], true) : false;
                if ($value === false || strlen($value) > ($purpose === 'pfx' ? IssuerCertificateParser::MAX_BYTES : IssuerCertificateParser::MAX_PASSWORD_BYTES)
                    || ($purpose === 'pfx' && $value === '')) { throw new RuntimeException(); }
                unset($plain,$doc,$value);
            }
        } catch (Throwable $error) { throw new DomainException('issuer_certificate_envelope_invalid'); }
    }
    private function context(int $issuer, int $revision, array $metadata, string $purpose): array
    {
        if ($issuer < 1 || $revision < 1 || !preg_match('/^[a-f0-9]{64}$/D', $metadata['fingerprint_sha256'] ?? '')) { throw new DomainException('issuer_certificate_envelope_invalid'); }
        return ['format'=>1,'issuer_id'=>$issuer,'revision'=>$revision,'fingerprint_sha256'=>$metadata['fingerprint_sha256'],
            'purpose'=>'issuer-certificate-'.$purpose,'metadata_hash'=>hash('sha256',json_encode($metadata,JSON_THROW_ON_ERROR))];
    }
}
