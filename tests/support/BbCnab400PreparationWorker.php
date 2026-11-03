<?php
/** Worker for disposable-DB concurrency test. No bank/provider calls. */
declare(strict_types=1);
require_once dirname(__DIR__).'/issuers_core_support.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/Cnab400Preparation.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:'))exit(2);
$prefix=$argv[1]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$prefix))exit(2);
$payload=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);
$db=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$prefix);
try{
    $result=(new \BancoBrasil\Cnab400Preparation($db,$prefix))->prepare($payload['issuer'],$payload['actor'],$payload['connection'],$payload['binding'],$payload['invoice'],$payload['key']);
    echo (string)$result['id'];
}catch(DomainException $error){
    if($error->getMessage()==='bb400_preparation_access_denied')echo 'denied';else throw $error;
}
