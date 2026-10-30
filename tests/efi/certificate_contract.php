<?php
/** Locally generated synthetic PKCS12 only; no user certificate or provider handshake. */
declare(strict_types=1);
define('BASEPATH',dirname(__DIR__,2).'/system/');
require dirname(__DIR__,2).'/modules/gerencianet_gateway/services/EfiCertificateStore.php';
$checks=0;function check($ok,$name){++$GLOBALS['checks'];if(!$ok)throw new RuntimeException($name);}
function reject($run,$name){try{$run();}catch(Throwable $e){check(true,$name);return;}throw new RuntimeException('Expected rejection: '.$name);}
// Authenticated cipher test double, not a claim that this substitutes for CI_Encryption.
$cipher=new class {private string $key;function __construct(){$this->key=random_bytes(32);}function encrypt($plain){$iv=random_bytes(12);$tag='';$encrypted=openssl_encrypt($plain,'aes-256-gcm',$this->key,OPENSSL_RAW_DATA,$iv,$tag);return base64_encode($iv.$tag.$encrypted);}function decrypt($value){$raw=base64_decode($value,true);if(!is_string($raw)||strlen($raw)<29)return false;return openssl_decrypt(substr($raw,28),'aes-256-gcm',$this->key,OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));}};
$key=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
$csr=openssl_csr_new(['commonName'=>'synthetic.invalid'],$key,['digest_alg'=>'sha256']);
$cert=openssl_csr_sign($csr,null,$key,1,['digest_alg'=>'sha256']);$pfx='';
check(openssl_pkcs12_export($cert,$pfx,$key,'synthetic-password'),'synthetic PKCS12 generated');
$sealed=EfiCertificateStore::seal($pfx,'synthetic-password',$cipher);check(!str_contains($sealed,'PRIVATE KEY'),'persisted value has no plaintext PEM');
$path=EfiCertificateStore::temporary($sealed,$cipher);
try {check((fileperms($path)&0777)===0600,'SDK material owner-only');$pem=file_get_contents($path);check(str_contains($pem,'PRIVATE KEY')&&str_contains($pem,'CERTIFICATE'),'SDK PEM includes key and certificate');check(is_array(openssl_x509_parse($pem)),'SDK certificate parser accepts combined PEM');}
finally {unlink($path);}
reject(fn()=>EfiCertificateStore::seal($pfx,'wrong',$cipher),'wrong password');
reject(fn()=>EfiCertificateStore::seal('not-p12','',$cipher),'invalid content');
reject(fn()=>EfiCertificateStore::seal(str_repeat('x',EfiCertificateStore::MAX_BYTES+1),'',$cipher),'oversize');
reject(fn()=>EfiCertificateStore::temporary('tampered',$cipher),'cipher tamper');
echo "Efí synthetic offline certificate contracts: {$checks} PASS; CI crypto integration and provider TLS remain separate.\n";
