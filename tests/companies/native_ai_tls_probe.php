<?php
/** Real SDK/cURL transport probe only; never bootstraps an app/database or sets TLS options. */
if(PHP_SAPI!=='cli'||getenv('ISSUERS_BROWSER_DB_NAME')!=='argws_upgrade_ci')exit(2);
$owner=$argv[1]??'';if(!preg_match('/^[1-9][0-9]{0,8}$/D',$owner))exit(2);
$root=dirname(__DIR__,2);require $root.'/application/services/CompanyAiClient.php';
try{$result=(new CompanyAiClient())->complete('gpt-4o-mini','synthetic-private-ai-'.$owner,['subject'=>'PRIVATE_AI_SUBJECT_'.$owner,'message'=>'Synthetic authorized ticket text; no attachments or contacts','replies'=>[]],'summary');if($result!=='Synthetic private AI answer <script>window.aiShouldNeverRun=true</script>')throw new RuntimeException('synthetic_ai_reply_unconfirmed');echo "Real bundled SDK strict local TLS probe PASS.\n";}
catch(Throwable$error){fwrite(STDERR,'Production AI transport probe failed: '.get_class($error).PHP_EOL);exit(1);}
