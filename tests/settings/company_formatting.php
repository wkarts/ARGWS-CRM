<?php
/** Native formatting functions with synthetic company storage, never production data. */
ob_start();
require __DIR__.'/company_settings.php';
$db->query("UPDATE {$p}issuers_registry SET active=1 WHERE id=82");
require APPPATH.'helpers/settings_helper.php';
require APPPATH.'helpers/general_helper.php';
foreach ([41=>['d/m/Y|%d/%m/%Y','24','18/10/2026','18/10/2026 15:04:05'],82=>['Y-m-d|%Y-%m-%d','12','2026-10-18','2026-10-18 3:04 PM']] as $issuer=>$format) {
    CompanySettingsRuntime::run($db,$p,$issuer,12,function () use ($format) {
        CompanySettingsRuntime::update('dateformat',$format[0]); CompanySettingsRuntime::update('time_format',$format[1]);
        essentialCheck(_d('2026-10-18')===$format[2], 'native date formatting uses selected company');
        essentialCheck(_dt('2026-10-18 15:04:05')===$format[3], 'native datetime formatting uses selected company');
        essentialCheck(to_sql_date($format[2])==='2026-10-18', 'native input date parsing uses selected company');
    });
}
CompanySettingsRuntime::run($db,$p,41,12,function () { essentialCheck(_d('2026-10-18')==='18/10/2026','returning to A retains its format after B'); });
echo 'Native company formatting: '.$GLOBALS['essentialChecks'].' total checks passed.'.PHP_EOL;
