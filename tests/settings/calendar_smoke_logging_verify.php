<?php
/** Bounded READ ONLY proof that actual Calendar requests emitted safe diagnostic codes. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('ISSUERS_BROWSER_DB_NAME')!=='argws_upgrade_ci')exit(2);
define('FCPATH',dirname(__DIR__,2).'/');define('BASEPATH',FCPATH.'system/');
require FCPATH.'application/config/app-config.php';
if(APP_DB_NAME!=='argws_upgrade_ci'||!preg_match('/^(127\.0\.0\.1|argws-crm-provision-db-[0-9]+)(:[0-9]+)?$/D',APP_DB_HOSTNAME))exit(2);
if(!defined('APP_LOG_THRESHOLD')||APP_LOG_THRESHOLD!==1)throw new RuntimeException('Synthetic calendar diagnostic logging was not activated');
$files=array_slice(glob(FCPATH.'application/logs/log-*.php')?:[],-3);$codes=[];$checked=[];
foreach($files as$file){if(!is_file($file)||!is_readable($file))continue;$handle=fopen($file,'rb');if($handle===false)continue;$size=filesize($file);if($size>262144)fseek($handle,-262144,SEEK_END);$tail=stream_get_contents($handle,262144);fclose($handle);$checked[]=basename($file);if(preg_match_all('/Company calendar rejected \[((?:company|issuer|asaas|bb)_[a-z_]{1,80})\]/',$tail,$matches))$codes=array_merge($codes,$matches[1]);}
$codes=array_values(array_unique($codes));sort($codes,SORT_STRING);
if(!array_intersect($codes,['company_calendar_denied','company_calendar_invalid','company_calendar_stale','company_calendar_preferences_stale','company_calendar_source_unavailable']))throw new RuntimeException('Expected actual Calendar diagnostic codes missing from bounded native logs');
echo 'Calendar native diagnostic log PASS: '.json_encode(['read_only'=>true,'codes'=>$codes,'files'=>$checked,'maximum_tail_bytes_per_file'=>262144,'raw_contents_or_secrets_returned'=>false],JSON_THROW_ON_ERROR).PHP_EOL;
