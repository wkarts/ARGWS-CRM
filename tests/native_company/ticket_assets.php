<?php
declare(strict_types=1);
require __DIR__.'/presentation.php';
CompanyRequestScope::$native=true;CompanyRequestScope::$issuer=2;CompanyRequestScope::$token='2:revision';NativeCompanyAdminPresentation::prepare($ci);
$ci->router=new class{public string$class='tickets',$method='add';function fetch_class(){return$this->class;}function fetch_method(){return$this->method;}};
foreach(['add','ticket','index']as$method){$ci->router->method=$method;$scripts=NativeCompanyAdminPresentation::scripts($ci);check(count(array_keys($scripts,'assets/js/tickets.js',true))===1,'same native tickets source exactly once');check(!in_array('assets/js/main.js',$scripts,true),'no global main fallback');$app=NativeCompanyAdminPresentation::appConfig($ci);check($app['options']['is_ai_provider_enabled']===false&&$app['options']['ai_provider_unavailable_reason']==='company_scoped_provider_not_implemented','provider absence explicit rather than inherited global setting');check(isset($app['lang']['warn_ticket_thread_too_long']),'real language key bound');}
foreach(['priority','delete','service']as$method){$ci->router->method=$method;check(!in_array('assets/js/tickets.js',NativeCompanyAdminPresentation::scripts($ci),true),'catalog/config pages do not start full ticket interactions');}
$ci->router->class='clients';$ci->router->method='index';check(!in_array('assets/js/tickets.js',NativeCompanyAdminPresentation::scripts($ci),true),'no unrelated controller asset');
CompanyRequestScope::$native=false;check(!in_array('assets/js/tickets.js',NativeCompanyAdminPresentation::scripts($ci),true),'legacy asset path retains its existing loader');
echo "Ticket assets: exact attested route source once, no global main, explicit missing scoped AI provider and native language contract passed; browser remains separate.\n";
