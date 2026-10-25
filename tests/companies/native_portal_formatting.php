<?php
ob_start();
require __DIR__.'/native_portal_scope.php';
require APPPATH.'helpers/general_helper.php';
ob_end_flush();
function get_option($key){throw new LogicException('public formatter must not use ambient options');}
$start=$GLOBALS['essentialChecks'];$GLOBALS['CI']=$ci;$GLOBALS['essentialHooks']=new class{function __call($m,$args){throw new LogicException('public formatter must not call hooks');}};
$primary=(int)$db->query('SELECT primary_issuer_id FROM '.$p.'issuers_installation_identity WHERE id=1')->row()->primary_issuer_id;
$settings=new CompanySettingsStorage($db,$p);foreach([$a=>['d/m/Y|%d/%m/%Y','24'],$b=>['m-d-Y|%m-%d-%Y','12'],$primary=>['Y-m-d|%Y-%m-%d','24']]as$issuer=>$formats){$settings->mutate($issuer,7,'update','dateformat',$formats[0],null);$settings->mutate($issuer,7,'update','time_format',$formats[1],null);}
$ci->router->class='authentication';$ci->router->method='login';$ci->input->post=[];$ci->input->verb='GET';$ci->input->get=['issuer_id'=>(string)$a];PortalRequestScope::prepare($ci);PortalRequestScope::resolve($ci);
essentialCheck(_d('2026-10-25')==='25/10/2026'&&_dt('2026-10-25 13:05:06')==='25/10/2026 13:05:06','original public date helpers use only company A format');
essentialCheck(to_sql_date('25/10/2026')==='2026-10-25','original public SQL-date parser uses same issuer format');
$ci->input->get=['issuer_id'=>(string)$b];PortalRequestScope::prepare($ci);PortalRequestScope::resolve($ci);essentialCheck(_d('2026-10-25')==='10-25-2026'&&_dt('2026-10-25 13:05:06')==='10-25-2026 1:05 PM','company B formatting never inherits A or staff session');
NativePortalIdentity::clear($ci->session);$ci->input->get=[];PortalRequestScope::prepare($ci);PortalRequestScope::resolve($ci);essentialCheck(_d('2026-10-25')==='2026-10-25','absent issuer formats using proven persisted principal only');
$GLOBALS['CI']=clone $ci;essentialThrows(fn()=>_d('2026-10-25'),'native_format_denied','different request object cannot reuse portal formatting seal');$GLOBALS['CI']=$ci;
PortalRequestScope::prepare($ci);essentialThrows(fn()=>to_sql_date('2026-10-25'),'native_format_denied','candidate before identity resolution never falls back to global helper');
echo 'Native portal original date helpers: '.($GLOBALS['essentialChecks']-$start).' actual PHP/SQLite checks passed.'.PHP_EOL;
