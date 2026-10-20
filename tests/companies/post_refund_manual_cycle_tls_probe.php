<?php
/** Real production CompanyClient/cURL probe; no TLS option overrides in test code. */
if(PHP_SAPI!=='cli'||getenv('ISSUERS_BROWSER_DB_NAME')!=='argws_upgrade_ci')exit(2);
define('FCPATH',dirname(__DIR__,2).'/');require FCPATH.'modules/asaas/libraries/Asaas/CompanyClient.php';
$letter=$argv[1]??'a';if(!in_array($letter,['a','b'],true))exit(2);
try{$response=(new \Asaas\CompanyClient('sandbox','synthetic-refund-browser-'.$letter))->request('GET','wallets/');if(($response['hasMore']??true)!==false||count($response['data']??[])!==1||!is_string($response['data'][0]['id']??null))throw new RuntimeException('Synthetic wallet protocol unconfirmed');echo json_encode(['verified_tls_wallet_get'=>true,'wallet'=>$response['data'][0]['id']],JSON_THROW_ON_ERROR).PHP_EOL;}
catch(Throwable $error){fwrite(STDERR,'Production client probe failed: '.$error->getMessage().PHP_EOL);exit(1);}
