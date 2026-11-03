<?php
/** Pure projection contract. No database, provider, clock dependency or HTTP. */
define('BASEPATH',dirname(__DIR__).'/system/');
define('APPPATH',dirname(__DIR__).'/application/');
require_once __DIR__.'/../application/services/CompanyDashboardPayments.php';
function dashboardChartCheck($ok,string $name):void{if(!$ok)throw new RuntimeException($name);echo "PASS $name\n";}
final class DashboardCurrencyResult
{
    public function __construct(private array $rows){}
    public function result_array():array{return $this->rows;}
}
final class DashboardCurrencyDb
{
    public bool $db_debug=true;public bool $save_queries=true;
    public array $catalog=[1=>['id'=>1,'name'=>'BRL']];public array $snapshots=[];public array $invoices=[];
    public $afterInvoiceRead=null;
    public function query(string $sql,array $params=[]):DashboardCurrencyResult
    {
        if(str_contains($sql,'FROM tp_company_currency_units')){$rows=isset($this->snapshots[(int)$params[0]])?[$this->snapshots[(int)$params[0]]]:[];}
        elseif(str_contains($sql,'FROM tp_currencies')){$rows=isset($this->catalog[(int)$params[0]])?[$this->catalog[(int)$params[0]]]:[];}
        elseif(str_contains($sql,'FROM tp_invoices')){$rows=array_values(array_filter($this->invoices,static fn($row)=>(int)$row['issuer_id']===(int)$params[0]&&(int)$row['currency']===(int)$params[1]));if($this->afterInvoiceRead){$callback=$this->afterInvoiceRead;$this->afterInvoiceRead=null;$callback($this);}}
        else throw new RuntimeException('Unexpected dashboard currency SQL');
        return new DashboardCurrencyResult($rows);
    }
}
function dashboardDisplayCurrency(DashboardCurrencyDb $db,int $issuer=1,int $currency=1):array
{
    $service=new CompanyDashboardPayments($db,'tp_');$method=new ReflectionMethod(CompanyDashboardPayments::class,'displayCurrency');return $method->invoke($service,$issuer,$currency);
}
$display=dashboardDisplayCurrency(new DashboardCurrencyDb());dashboardChartCheck($display['iso_code']==='BRL'&&$display['basis']==='catalog_display_only','empty issuer currency uses validated live catalog for dashboard display');
$used=new DashboardCurrencyDb();$used->invoices=[['id'=>41,'issuer_id'=>1,'currency'=>1]];try{dashboardDisplayCurrency($used);throw new RuntimeException('missing snapshot accepted for issuer invoice');}catch(DomainException $error){dashboardChartCheck($error->getMessage()==='company_currency_snapshot_required','issuer invoice still requires immutable currency snapshot');}
$frozen=new DashboardCurrencyDb();$frozen->catalog[92]=['id'=>92,'name'=>'EUR'];$recorded='2026-01-02 03:04:05';$basis=CompanyCurrencyUnits::BASELINE;$frozen->snapshots[92]=['currency_id'=>92,'iso_code'=>'EUR','basis'=>$basis,'recorded_at'=>$recorded,'source_hash'=>hash('sha256',json_encode([92,'EUR',$basis,$recorded],JSON_THROW_ON_ERROR))];$display=dashboardDisplayCurrency($frozen,1,92);dashboardChartCheck($display['iso_code']==='EUR'&&$display['basis']===$basis,'valid immutable unit remains the dashboard authority');
$tampered=new DashboardCurrencyDb();$tampered->snapshots[1]=['currency_id'=>1,'iso_code'=>'BRL','basis'=>CompanyCurrencyUnits::BASELINE,'recorded_at'=>'2026-01-02 03:04:05','source_hash'=>str_repeat('0',64)];try{dashboardDisplayCurrency($tampered);throw new RuntimeException('tampered currency snapshot accepted');}catch(DomainException $error){dashboardChartCheck($error->getMessage()==='company_currency_snapshot_invalid','tampered snapshot does not fall back to mutable catalog');}
$stale=new DashboardCurrencyDb();$stale->afterInvoiceRead=static function(DashboardCurrencyDb $db):void{$db->catalog[1]['name']='EUR';};try{dashboardDisplayCurrency($stale);throw new RuntimeException('catalog race accepted');}catch(DomainException $error){dashboardChartCheck($error->getMessage()==='company_dashboard_stale','currency catalog change during empty-chart read is rejected');}
$rows=[['date'=>'2026-10-19','effective_amount'=>'0.10'],['date'=>'2026-10-19','effective_amount'=>'0.20'],['date'=>'2026-10-12','effective_amount'=>'4.15'],['date'=>'2026-10-25','effective_amount'=>'0.00']];
$chart=CompanyDashboardPayments::project($rows,'weekly','2026-10-25');
dashboardChartCheck($chart['datasets'][0]['data'][0]==='0.30','decimal aggregation, no floating sum');
dashboardChartCheck($chart['datasets'][1]['data'][0]==='4.15','previous week separate');
dashboardChartCheck(count($chart['labels'])===7&&count($chart['datasets'])===2,'native weekly contract');
dashboardChartCheck($chart['datasets'][0]['data'][6]==='0.00','reversed receipt remains zero effective, not raw');
$monthly=CompanyDashboardPayments::project([['date'=>'2026-01-01','effective_amount'=>'12.34'],['date'=>'2026-12-31','effective_amount'=>'99.01']],'monthly','2026-10-25');
dashboardChartCheck(count($monthly['labels'])===12&&$monthly['datasets'][0]['data'][0]==='12.34'&&$monthly['datasets'][0]['data'][11]==='99.01','calendar-year bounds');
foreach([
    [[['date'=>'2026-02-30','effective_amount'=>'1.00']],'monthly','2026-10-25'],
    [[['date'=>'2026-10-11','effective_amount'=>'1.00']],'weekly','2026-10-25'],
    [[['date'=>'2026-10-19','effective_amount'=>'-1.00']],'weekly','2026-10-25'],
    [[['date'=>'2026-10-19','effective_amount'=>'1e2']],'weekly','2026-10-25'],
    [[], 'arbitrary','2026-10-25'],[[],'weekly','2026-02-30'],
] as $i=>$args){try{CompanyDashboardPayments::project(...$args);throw new RuntimeException('accepted invalid projection '.$i);}catch(DomainException $e){echo "PASS invalid projection $i\n";}}
echo "Native dashboard payment projection passed; no bank cash-flow claim.\n";
