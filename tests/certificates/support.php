<?php

declare(strict_types=1);
require_once dirname(__DIR__).'/issuers_test_support.php';
if (!defined('BASEPATH')) { define('BASEPATH', dirname(__DIR__,2).'/system/'); }
if (!defined('APPPATH')) { define('APPPATH', dirname(__DIR__,2).'/application/'); }
function log_message($level,$message): void { $GLOBALS['a1_logs'][]=[$level,$message]; }
function config_item($key) { return $key==='encryption_key'?str_repeat('s',32):null; }
function show_error($message,$status=500): void { throw new RuntimeException('http_'.$status); }
require_once BASEPATH.'libraries/Encryption.php';
require_once APPPATH.'services/issuers/IssuerCertificateManager.php';
function a1check(bool $condition,string $label): void { if (!$condition) { throw new RuntimeException('FAIL: '.$label); } echo "PASS: $label\n"; }
function a1reject(callable $operation,string $reason,string $label): void
{
    try { $operation(); } catch (Throwable $error) { a1check($error->getMessage()===$reason,$label.' ['.$error->getMessage().']'); return; }
    throw new RuntimeException('FAIL: '.$label.' did not reject');
}
function a1encryption(): CI_Encryption { return new CI_Encryption(['driver'=>'openssl','cipher'=>'aes-128','mode'=>'cbc','key'=>config_item('encryption_key')]); }
function a1fixture(?string $cnpj='11222333000181',int $days=30,string $commonName='SYNTHETIC A1 TEST ONLY'): array
{
    $file=tempnam(sys_get_temp_dir(),'a1-test-'); chmod($file,0600);
    $config="[req]\ndistinguished_name=dn\nprompt=no\n[dn]\nCN=Test\n[extensions]\nbasicConstraints=critical,CA:FALSE\nkeyUsage=critical,digitalSignature\n";
    if ($cnpj!==null) { $config.="subjectAltName=otherName:2.16.76.1.3.3;UTF8:".$cnpj."\n"; }
    file_put_contents($file,$config);
    try {
        $key=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
        $csr=openssl_csr_new(['commonName'=>$commonName,'organizationName'=>'Synthetic Only'],$key,['config'=>$file,'digest_alg'=>'sha256']);
        $cert=openssl_csr_sign($csr,null,$key,$days,['config'=>$file,'x509_extensions'=>'extensions','digest_alg'=>'sha256'],123);
        if (!$cert||!openssl_pkcs12_export($cert,$pfx,$key,'synthetic-password')) { throw new RuntimeException('synthetic_fixture_failed'); }
        openssl_x509_export($cert,$pem); openssl_pkey_export($key,$private);
        return ['pfx'=>$pfx,'certificate'=>$pem,'private'=>$private,'password'=>'synthetic-password'];
    } finally { unlink($file); }
}
function a1validateDsn(string $dsn): void
{
    if (!str_starts_with($dsn,'mysql:')) { throw new RuntimeException('Only isolated issuer_a1_tests DB is allowed'); }
    $databases=[];
    foreach (explode(';',substr($dsn,6)) as $part) {
        $pair=explode('=',$part,2);
        if (strtolower(trim($pair[0]))==='dbname') { $databases[]=trim($pair[1]??''); }
    }
    if ($databases!==['issuer_a1_tests']) { throw new RuntimeException('Only isolated issuer_a1_tests DB is allowed'); }
}
function a1mysql(): PDO
{
    $dsn=(string)getenv('A1_TEST_DSN'); a1validateDsn($dsn);
    $pdo=new PDO($dsn,getenv('A1_TEST_USER')?:'root',getenv('A1_TEST_PASSWORD')?:'');
    if ($pdo->query('SELECT DATABASE()')->fetchColumn()!=='issuer_a1_tests') { throw new RuntimeException('Only isolated issuer_a1_tests DB is allowed'); }
    return $pdo;
}
function a1db(): IssuerTestDb
{
    $dsn=getenv('A1_TEST_DSN');
    if ($dsn!==false&&$dsn!=='') {
        $pdo=a1mysql();
    } else { $pdo=new PDO('sqlite::memory:'); }
    $db=new IssuerTestDb($pdo);
    if ($db->driver==='sqlite') { $db->query('PRAGMA foreign_keys=ON'); }
    foreach (['issuer_certificate_audit','issuer_certificate_credentials','issuers_resource_permissions','issuers_staff','issuers_installation_identity','issuers_lock','issuers_registry','staff'] as $suffix) { $db->query('DROP TABLE IF EXISTS a1_'.$suffix); }
    $end=' ENGINE=InnoDB';
    $db->query('CREATE TABLE a1_staff (staffid int PRIMARY KEY,admin int NOT NULL,active int NOT NULL)'.$end);
    $db->query('CREATE TABLE a1_issuers_registry (id int unsigned PRIMARY KEY,legal_name varchar(191) NOT NULL,tax_id varchar(20),active int NOT NULL,identity_status varchar(20) NOT NULL)'.$end);
    $db->query('CREATE TABLE a1_issuers_lock (id int PRIMARY KEY)'.$end);
    $db->query('CREATE TABLE a1_issuers_staff (issuer_id int unsigned,staff_id int,can_view int,PRIMARY KEY(issuer_id,staff_id))'.$end);
    $db->query('CREATE TABLE a1_issuers_resource_permissions (issuer_id int unsigned,staff_id int,feature varchar(100),capability varchar(100),PRIMARY KEY(issuer_id,staff_id,feature,capability))'.$end);
    $db->query('CREATE TABLE a1_issuers_installation_identity (id int PRIMARY KEY,master_staff_id int,primary_issuer_id int unsigned)'.$end);
    $db->query('INSERT INTO a1_staff VALUES(1,1,1),(2,0,1),(3,1,1),(4,0,1)');
    $db->query("INSERT INTO a1_issuers_registry VALUES(10,'SYNTHETIC A','11222333000181',1,'complete'),(20,'SYNTHETIC B','22333444000155',1,'complete')");
    $db->query('INSERT INTO a1_issuers_lock VALUES(1)');
    $db->query('INSERT INTO a1_issuers_installation_identity VALUES(1,1,10)');
    $db->query('INSERT INTO a1_issuers_staff VALUES(10,2,1),(20,2,1),(10,3,1),(10,4,1)');
    $db->query("INSERT INTO a1_issuers_resource_permissions VALUES(10,2,'settings','edit'),(20,2,'settings','edit'),(10,4,'settings','view')");
    IssuerCertificateSchema::ensure($db,'a1_');
    return $db;
}
function a1row(IssuerTestDb $db,int $issuer): ?array { return $db->query('SELECT * FROM a1_issuer_certificate_credentials WHERE issuer_id=?',[$issuer])->result_array()[0]??null; }
/** Unsigned-MAC PKCS#12 is a TEST fixture for missing/mismatched private material. */
function a1pkcs12(?string $private,string $certificate): string
{
    $tlv=static function(int $tag,string $value):string { $size=strlen($value); $length=$size<128?chr($size):ltrim(pack('N',$size),"\0"); if($size>=128)$length=chr(128+strlen($length)).$length; return chr($tag).$length.$value; };
    $seq=static fn($value)=>$tlv(0x30,$value); $explicit=static fn($value)=>$tlv(0xa0,$value); $oct=static fn($value)=>$tlv(4,$value);
    $oid=static fn($bytes)=>$tlv(6,hex2bin($bytes));
    $der=static fn($pem)=>base64_decode(preg_replace('/-----[^-]+-----|\s/','',$pem),true);
    $bags='';
    if($private!==null)$bags.=$seq($oid('2a864886f70d010c0a0101').$explicit($der($private)));
    $bags.=$seq($oid('2a864886f70d010c0a0103').$explicit($seq($oid('2a864886f70d01091601').$explicit($oct($der($certificate))))));
    $data=static fn($content)=>$seq($oid('2a864886f70d010701').$explicit($oct($content)));
    return $seq($tlv(2,"\x03").$data($seq($data($seq($bags)))));
}
