<?php

declare(strict_types=1);

/** Offline PKCS#12 inspection. It does not establish ICP-Brasil trust or revocation. */
final class IssuerCertificateParser
{
    public const MAX_BYTES = 2097152;
    public const MAX_PASSWORD_BYTES = 4096;

    public static function inspect(string $pfx, string $password, string $taxId, ?int $now = null): array
    {
        if ($pfx === '' || strlen($pfx) > self::MAX_BYTES || strlen($password) > self::MAX_PASSWORD_BYTES || strpos($password, "\0") !== false) {
            throw new DomainException('issuer_certificate_invalid_upload');
        }
        if (!extension_loaded('openssl')) { throw new DomainException('issuer_certificate_crypto_unavailable'); }
        $parts = []; $private = null;
        try {
            self::drainErrors();
            if (!@openssl_pkcs12_read($pfx, $parts, $password)) {
                $reason=self::readFailure();
                require_once __DIR__.'/IssuerCertificateLegacyImport.php';
                if ($reason!=='issuer_certificate_algorithm_unsupported' || !IssuerCertificateLegacyImport::enabled()) { throw new DomainException($reason); }
                $parts=IssuerCertificateLegacyImport::read($pfx,$password);
            }
            if (!is_string($parts['cert'] ?? null) || !is_string($parts['pkey'] ?? null)) {
                throw new DomainException('issuer_certificate_key_required');
            }
            // check_private_key alone compares public material, so load a private key first.
            $private = @openssl_pkey_get_private($parts['pkey']);
            if ($private === false || !@openssl_x509_check_private_key($parts['cert'], $private)) {
                throw new DomainException('issuer_certificate_key_required');
            }
            $cert = @openssl_x509_parse($parts['cert'], false);
            if (!is_array($cert) || !isset($cert['validFrom_time_t'], $cert['validTo_time_t'])) {
                throw new DomainException('issuer_certificate_parse_failed');
            }
            $now = $now ?? time();
            if ($now < $cert['validFrom_time_t']) { throw new DomainException('issuer_certificate_not_yet_valid'); }
            if ($now >= $cert['validTo_time_t']) { throw new DomainException('issuer_certificate_expired'); }
            $fingerprint = @openssl_x509_fingerprint($parts['cert'], 'sha256');
            if (!is_string($fingerprint) || !preg_match('/^[a-f0-9]{64}$/D', $fingerprint)) { throw new DomainException('issuer_certificate_parse_failed'); }
            $document = self::cnpj($parts['cert']);
            $canonical = strtoupper(preg_replace('/[.\/\-\s]/', '', $taxId));
            if ($document !== null && (!preg_match('/^[A-Z0-9]{12}[0-9]{2}$/D', $canonical) || !hash_equals($document, $canonical))) {
                throw new DomainException('issuer_certificate_document_mismatch');
            }
            return [
                'fingerprint_sha256'=>$fingerprint, 'subject'=>self::summary($cert['subject'] ?? []),
                'issuer'=>self::summary($cert['issuer'] ?? []), 'serial'=>self::text((string)($cert['serialNumberHex'] ?? ''), 128),
                'valid_from'=>gmdate('Y-m-d H:i:s', (int)$cert['validFrom_time_t']),
                'valid_to'=>gmdate('Y-m-d H:i:s', (int)$cert['validTo_time_t']),
                'document'=>$document, 'document_status'=>$document === null ? 'unverified' : 'matched',
            ];
        } catch (DomainException $error) { throw $error; }
        catch (Throwable $error) { throw new DomainException('issuer_certificate_parse_failed'); }
        finally { $parts = []; $private = null; self::drainErrors(); }
    }

    /** Consume diagnostics locally; never expose error text, filenames or input values. */
    private static function readFailure(): string
    {
        $unsupported = false; $authentication = false;
        while (($error = openssl_error_string()) !== false) {
            // Exact library/reason allowlist, compatible with OpenSSL 1.1/3 error strings.
            // An unknown error is deliberately not interpreted as an incorrect password.
            $unsupported = $unsupported || preg_match('/^error:[0-9a-f]+:(?:digital envelope routines:[^:]*:unsupported|PKCS12 routines:[^:]*:unsupported algorithm)$/iD', $error) === 1;
            $authentication = $authentication || preg_match('/^error:[0-9a-f]+:PKCS12 routines:[^:]*:mac verify failure$/iD', $error) === 1;
        }
        if ($unsupported) { return 'issuer_certificate_algorithm_unsupported'; }
        if ($authentication) { return 'issuer_certificate_authentication_failed'; }
        return 'issuer_certificate_parse_failed';
    }

    private static function drainErrors(): void { while (openssl_error_string() !== false) {} }
    private static function text(string $text, int $limit): string
    {
        $text = preg_replace('/[\x00-\x1f\x7f]/', ' ', $text);
        if (preg_match('//u', $text) !== 1) { return '[texto indisponível]'; }
        return mb_strcut($text, 0, $limit, 'UTF-8');
    }
    private static function summary(array $name): string
    {
        $out = [];
        foreach (['commonName','organizationName','countryName'] as $key) {
            if (is_string($name[$key] ?? null)) { $out[] = self::text($name[$key], 150); }
        }
        return implode(' / ', $out);
    }

    /** Strict bounded DER traversal of TBSCertificate.extensions.subjectAltName only. */
    private static function cnpj(string $pem): ?string
    {
        $base64 = preg_replace('/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s/', '', $pem);
        $der = base64_decode($base64, true);
        if (!is_string($der) || strlen($der) > 262144) { throw new DomainException('issuer_certificate_parse_failed'); }
        $offset = 0; $budget = 2048;
        $root = self::node($der, $offset, $budget);
        if ($offset !== strlen($der) || $root['tag'] !== 0x30) { throw new DomainException('issuer_certificate_parse_failed'); }
        $certificate = self::children($root['value'], $budget);
        if (count($certificate) !== 3 || $certificate[0]['tag'] !== 0x30) { throw new DomainException('issuer_certificate_parse_failed'); }
        $tbs = self::children($certificate[0]['value'], $budget); $extensions = null;
        foreach ($tbs as $field) {
            if ($field['tag'] === 0xa3) {
                if ($extensions !== null) { throw new DomainException('issuer_certificate_parse_failed'); }
                $explicit = self::children($field['value'], $budget);
                if (count($explicit) !== 1 || $explicit[0]['tag'] !== 0x30) { throw new DomainException('issuer_certificate_parse_failed'); }
                $extensions = self::children($explicit[0]['value'], $budget);
            }
        }
        $found = null; $sanSeen = false;
        foreach ($extensions ?? [] as $extension) {
            if ($extension['tag'] !== 0x30) { throw new DomainException('issuer_certificate_parse_failed'); }
            $fields = self::children($extension['value'], $budget);
            if (count($fields) < 2 || count($fields) > 3 || $fields[0]['tag'] !== 6) { throw new DomainException('issuer_certificate_parse_failed'); }
            if ($fields[0]['value'] !== "\x55\x1d\x11") { continue; }
            if ($sanSeen) { throw new DomainException('issuer_certificate_parse_failed'); } $sanSeen = true;
            $octets = $fields[count($fields)-1];
            if ($octets['tag'] !== 4) { throw new DomainException('issuer_certificate_parse_failed'); }
            $names = self::children($octets['value'], $budget);
            if (count($names) !== 1 || $names[0]['tag'] !== 0x30) { throw new DomainException('issuer_certificate_parse_failed'); }
            foreach (self::children($names[0]['value'], $budget) as $name) {
                if ($name['tag'] !== 0xa0) { continue; }
                $other = self::children($name['value'], $budget);
                if (count($other) !== 2 || $other[0]['tag'] !== 6 || $other[1]['tag'] !== 0xa0) { throw new DomainException('issuer_certificate_parse_failed'); }
                if ($other[0]['value'] !== "\x60\x4c\x01\x03\x03") { continue; }
                $value = self::children($other[1]['value'], $budget);
                if ($found !== null || count($value) !== 1 || !in_array($value[0]['tag'], [4,12,19,22], true)
                    || !preg_match('/^[A-Z0-9]{12}[0-9]{2}$/iD', $value[0]['value'])) { throw new DomainException('issuer_certificate_document_invalid'); }
                $found = strtoupper($value[0]['value']);
            }
        }
        return $found;
    }
    private static function children(string $data, int &$budget): array
    {
        $out = []; $offset = 0;
        while ($offset < strlen($data)) { $out[] = self::node($data, $offset, $budget); }
        return $out;
    }
    private static function node(string $data, int &$offset, int &$budget): array
    {
        if (--$budget < 0 || strlen($data)-$offset < 2) { throw new DomainException('issuer_certificate_parse_failed'); }
        $tag = ord($data[$offset++]); $length = ord($data[$offset++]);
        if (($tag & 31) === 31) { throw new DomainException('issuer_certificate_parse_failed'); }
        if ($length & 128) {
            $bytes = $length & 127;
            if ($bytes === 0 || $bytes > 4 || strlen($data)-$offset < $bytes || ord($data[$offset]) === 0) { throw new DomainException('issuer_certificate_parse_failed'); }
            $length = 0;
            for ($i=0; $i<$bytes; ++$i) { $length = ($length << 8) | ord($data[$offset++]); }
            if ($length < 128) { throw new DomainException('issuer_certificate_parse_failed'); }
        }
        if ($length > strlen($data)-$offset) { throw new DomainException('issuer_certificate_parse_failed'); }
        $value = substr($data, $offset, $length); $offset += $length;
        return ['tag'=>$tag,'value'=>$value];
    }
}
