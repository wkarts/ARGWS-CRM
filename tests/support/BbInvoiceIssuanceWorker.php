<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/issuers_core_support.php';
require_once APPPATH.'services/CompanyInvoices.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:'))exit(2);
$p=$argv[1]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);
$request=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);
$db=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);
echo (new CompanyInvoices($db,$p))->create($request['issuer'],$request['actor'],$request['input'],$request['key']);
