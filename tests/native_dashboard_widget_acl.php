<?php
declare(strict_types=1);
define('BASEPATH',__DIR__);$checks=0;
function _l($x){return $x;}function create_widget_id(){return 'native';}function admin_url($x=''){return '/admin/'.$x;}function form_hidden($k,$v){return '<input name="'.$k.'">';}function render_datatable($a,$b,$c=[],$d=[]){echo '<table data-table="'.$b.'"></table>';}function get_table_last_order($x){return [];}function AdminTicketsTableStructure($name='', $bulk=false, ?array $native=null){$GLOBALS['ticketStructureCalls'][]=[$name,$bulk,$native];return '<table data-table="tickets"></table>';}function e($x){return htmlspecialchars((string)$x,ENT_QUOTES,'UTF-8');}
final class NativeWidgetRender {public object $load;public array $loaded=[];public function __construct(){$this->load=$this;}public function view($path){$this->loaded[]=$path;echo '<table data-view="'.e($path).'"></table>';}public function render(array $permissions, ?array $ticketStructure=null):string{$company_dashboard=['permissions'=>$permissions,'auxiliary'=>['reminders_count'=>0]];$native_dashboard_ticket_structure=$ticketStructure;$total_undismissed_announcements=0;$activity_log=[];ob_start();include __DIR__.'/../application/views/admin/dashboard/widgets/user_data.php';return ob_get_clean();}}
function check($v,$label){global $checks;if(!$v)throw new RuntimeException($label);++$checks;}
foreach([[true,true,'home_tab_tasks'],[false,true,'home_my_projects'],[false,false,'home_my_reminders'],[true,false,'home_tab_tasks']]as[$tasks,$projects,$first]){$renderer=new NativeWidgetRender;$html=$renderer->render(['tasks'=>$tasks,'projects'=>$projects,'tickets'=>false,'reports'=>false]);$dom=new DOMDocument;@$dom->loadHTML($html);$xpath=new DOMXPath($dom);$active=$xpath->query('//div[@role="tabpanel" and contains(concat(" ",normalize-space(@class)," ")," active ")]');check($active->length===1&&$active->item(0)->getAttribute('id')===$first,'one permitted active pane');check(($xpath->query('//*[@id="home_tab_tasks"]')->length===1)===$tasks,'task pane follows task ACL');check(($xpath->query('//*[@id="home_my_projects"]')->length===1)===$projects,'project pane follows project ACL');check(in_array('admin/tasks/_table',$renderer->loaded,true)===$tasks,'unauthorized task partial not loaded');check($xpath->query('//*[@id="home_tab_tickets" or @id="home_tab_activity"]')->length===0,'denied report/tickets not initialized');}
$GLOBALS['ticketStructureCalls']=[];$ticketStructure=['order'=>'[[1,"desc"]]','hidden_columns'=>'[]','services'=>'1','custom_fields'=>[],'can_edit'=>false];$renderer=new NativeWidgetRender;$renderer->render(['tasks'=>false,'projects'=>false,'tickets'=>true,'reports'=>false],$ticketStructure);check($GLOBALS['ticketStructureCalls']===[['',false,$ticketStructure]],'native dashboard passes company-owned ticket preferences into the original table helper');
try{$renderer->render(['tasks'=>false,'projects'=>false,'tickets'=>true,'reports'=>false]);throw new RuntimeException('native ticket table without a company projection rendered');}catch(DomainException $error){check($error->getMessage()==='native_dashboard_ticket_structure_required','native dashboard cannot fall back to global ticket metadata');}
echo "Native dashboard widget ACL: $checks checks passed.\n";
$company_dashboard=['widgets'=>['projects'=>['status'=>'available','data'=>['active'=>2,'completed'=>0,'cancelled'=>0,'legacy_2'=>1]],'invoices'=>['status'=>'denied'],'leads'=>['status'=>'denied'],'tasks'=>['status'=>'denied']]];
ob_start();include __DIR__.'/../application/views/admin/dashboard/widgets/top_stats.php';$topHtml=ob_get_clean();$dom=new DOMDocument;@$dom->loadHTML($topHtml);$xpath=new DOMXPath($dom);$projectText=preg_replace('/\s+/',' ',trim($xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," quick-stats-projects ")]')->item(0)->textContent));
check(str_contains($projectText,'1 / 3'),'native top statistic counts one actual in-progress row, not two historically active rows');
check($xpath->query('//*[contains(@class,"quick-stats-invoices") or contains(@class,"quick-stats-leads")]')->length===0,'denied counters do not hydrate legacy data');
echo "Native top stats real DOM: $checks cumulative checks passed.\n";

function init_head(){echo '<!doctype html><html><head></head><body>';}
function init_tail(){}
final class DashboardCalendarCompositionRender
{
    public object $load;public array $loaded=[];public array $widgetData=[];
    public function __construct(){$this->load=$this;}
    public function view($path,$data=[]):void{$this->loaded[]=$path;if($path==='admin/dashboard/widgets/calendar')$this->widgetData=$data;}
    public function render(bool $allowed,bool $configured,bool $selected=true):string
    {
        $company_dashboard=['permissions'=>['calendar'=>$allowed],'widget_order'=>[],'view'=>['native_calendar'=>$configured]];
        $native_calendar=$configured;$native_calendar_config=['options'=>['timezone'=>'America/Bahia'],'calendar_ids'=>[]];
        if(!$selected){unset($company_dashboard);$company_dashboard_selection_required=true;}
        ob_start();include __DIR__.'/../application/views/admin/dashboard/dashboard.php';return ob_get_clean();
    }
}
foreach([[false,false],[false,true],[true,false],[true,true]]as[$allowed,$configured]){
    $page=new DashboardCalendarCompositionRender;$html=$page->render($allowed,$configured);
    check(in_array('admin/utilities/calendar_template',$page->loaded,true)===($allowed&&$configured),'original dashboard modal requires both a current calendar grant and scoped configuration');
    if($allowed&&$configured)check(str_contains($html,'window.nativeCalendarConfig=')&&($page->widgetData['native_calendar']??null)===true,'same dashboard page and original calendar widget receive scoped calendar configuration');
}
$page=new DashboardCalendarCompositionRender;$page->render(false,false,false);
check(!in_array('admin/utilities/calendar_template',$page->loaded,true),'no company selected never composes the event mutation form');
echo "Dashboard calendar composition: $checks cumulative checks; actual dashboard PHP, framework/partial I/O doubled.\n";

// Render the real finance widget with native textual states and real legacy
// helpers: previously the progress bar passed text to a numeric-only helper,
// and the links emitted status=open, which the original directory rejects.
define('APPPATH', __DIR__ . '/../application/');
require_once APPPATH . 'helpers/estimates_helper.php';
require_once APPPATH . 'helpers/proposals_helper.php';
$salesCases = [
    'open' => [1, 1, 'info'],
    'accepted' => [4, 3, 'success'],
    'declined' => [3, 2, 'danger'],
    'cancelled' => [3, 2, 'muted'],
    'converting' => [4, 3, 'warning'],
    'converted' => [4, 3, 'success'],
];
$company_dashboard = [
    'permissions' => ['invoices' => false, 'estimates' => true, 'proposals' => true],
    'finance_overview' => ['estimates' => [], 'proposals' => []],
    'sales_statuses' => [],
];
$estimate_statuses = $proposal_statuses = array_keys($salesCases);
foreach ($salesCases as $state => [$estimateStatus, $proposalStatus, $color]) {
    $company_dashboard['sales_statuses'][$state] = ['label' => $state, 'color' => $color];
    foreach (['estimates', 'proposals'] as $kind) {
        $company_dashboard['finance_overview'][$kind][$state] = ['total_by_status' => 1, 'percent' => 16.67];
    }
}
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
try {
    ob_start();
    include APPPATH . 'views/admin/dashboard/widgets/finance_overview.php';
    $financeHtml = ob_get_clean();
} finally {
    restore_error_handler();
}
$dom = new DOMDocument;
@$dom->loadHTML($financeHtml);
$xpath = new DOMXPath($dom);
$links = $xpath->query('//a[contains(@href,"list_estimates?status=") or contains(@href,"list_proposals?status=")]');
$bars = $xpath->query('//div[@role="progressbar"]');
check($links->length === 12 && $bars->length === 12, 'both original sales widgets render every native state without PHP diagnostics');
foreach (['estimates', 'proposals'] as $kindIndex => $kind) {
    foreach (array_values($salesCases) as $stateIndex => $expected) {
        $index = $kindIndex * 6 + $stateIndex;
        check($links->item($index)->getAttribute('href') === '/admin/' . $kind . '/list_' . $kind . '?status=' . $expected[$kindIndex], 'dashboard links use canonical numeric sales filters');
        check(str_contains($bars->item($index)->getAttribute('class'), 'progress-bar-' . $expected[2] . ' '), 'native bar uses the scoped status color');
    }
}
echo "Dashboard finance real DOM: $checks cumulative checks passed.\n";
