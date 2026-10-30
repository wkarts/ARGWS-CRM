<?php
declare(strict_types=1);
require __DIR__.'/presentation.php';
CompanyRequestScope::$native=true;CompanyRequestScope::$issuer=2;CompanyRequestScope::$token='2:revision';NativeCompanyAdminPresentation::prepare($ci);
$ci->router=new class{public string$class='utilities',$method='calendar';function fetch_class(){return$this->class;}function fetch_method(){return$this->method;}};
$s=NativeCompanyAdminPresentation::scripts($ci);$calendar='assets/js/native-admin-calendar.js';$plugin='assets/plugins/fullcalendar/lib/main.min.js';
check(count(array_keys($s,$calendar,true))===1,'one shared calendar source');check(array_search($plugin,$s,true)<array_search($calendar,$s,true),'FullCalendar precedes its real shared handlers');check(in_array('assets/plugins/fullcalendar/lib/locales/pt-br.js',$s,true),'same explicit locale plugin');check(in_array('assets/plugins/fullcalendar/lib/main.min.css',NativeCompanyAdminPresentation::css($ci),true),'original calendar CSS');
check(!in_array('assets/js/main.js',$s,true),'no global main fallback');$app=NativeCompanyAdminPresentation::appConfig($ci);check(isset($app['lang']['calendar_expand'],$app['lang']['filter_by']),'calendar language keys explicit');
$ci->router->method='backup';check(!in_array($plugin,NativeCompanyAdminPresentation::scripts($ci),true),'no unrelated plugin loading');check(!in_array('assets/plugins/fullcalendar/lib/main.min.css',NativeCompanyAdminPresentation::css($ci),true),'no unrelated calendar styling');
CompanyRequestScope::$native=false;check(!in_array($plugin,NativeCompanyAdminPresentation::scripts($ci),true),'legacy retains existing calendar asset loader');
CompanyRequestScope::$native=true;$ci->router->class='dashboard';$ci->router->method='index';
$scripts=NativeCompanyAdminPresentation::scripts($ci);
check(in_array('assets/plugins/Chart.js/Chart.bundle.min.js',$scripts,true),'native dashboard charts load their original Chart.js dependency');
check(count(array_keys($scripts,'assets/plugins/Chart.js/Chart.bundle.min.js',true))===1,'dashboard does not duplicate Chart.js');
$calendarGranted=(new IssuerResourceAccess($ci->db,db_prefix()))->can(CompanyRequestScope::$issuer,17,'calendar','view');
check(in_array($plugin,$scripts,true)===$calendarGranted,'dashboard FullCalendar follows current calendar permission');
ob_start();require APPPATH.'views/admin/utilities/event_mount.php';$eventMount=ob_get_clean();check(substr_count($eventMount,'id="event"')===1,'shared original event mount has exactly one empty host');
foreach(['scripts.php','helpers_bottom.php']as$footer){$source=file_get_contents(APPPATH.'views/admin/includes/'.$footer);check(substr_count($source,"views/admin/utilities/event_mount.php")===1&&!str_contains($source,'id="event"'),'native and legacy footer each reuse the same original mount rather than duplicate modal markup');}
echo "Native calendar asset wiring passed: exact attested route, shared source, original plugin/locale/CSS, no global main. Adapter and browser remain separate gates.\n";
