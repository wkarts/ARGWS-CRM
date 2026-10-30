<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyInvoices.php';

/** Native chart shape; effective local receipts, grouped by their original receipt date. */
final class CompanyDashboardPayments
{
    public function __construct(private $db,private string $p)
    {if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function authorize(int $issuer,int $actor):void
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if(!$acl->can($issuer,$actor,'invoices','view')||!$acl->can($issuer,$actor,'payments','view'))throw new DomainException('company_dashboard_denied');
    }
    /**
     * A dashboard with no named-company invoices in its base currency can still
     * render an empty chart (and principal-company legacy receipts). In that
     * case the catalog name is presentation metadata, not evidence about a
     * financial document. Once this issuer has an invoice in that currency,
     * the immutable snapshot remains mandatory.
     */
    private function displayCurrency(int $issuer,int $currency):array
    {
        $units=new CompanyCurrencyUnits($this->db,$this->p);$unit=null;
        try{$unit=$units->resolveRead($currency);}catch(DomainException $error){if($error->getMessage()!=='company_currency_snapshot_required')throw $error;}
        $catalog=IssuerStorage::query($this->db,'SELECT id,name FROM '.$this->p.'currencies WHERE id=?',[$currency])->result_array();
        if(count($catalog)!==1||!preg_match('/^[A-Z]{3}$/D',(string)$catalog[0]['name']))throw new DomainException('company_dashboard_currency_required');
        if($unit!==null){
            if($unit['iso_code']!==$catalog[0]['name'])throw new DomainException('company_currency_catalog_changed');
            return $unit;
        }
        $sql='SELECT id FROM '.$this->p.'invoices WHERE issuer_id=? AND currency=? ORDER BY id LIMIT 1';
        $owned=IssuerStorage::query($this->db,$sql,[$issuer,$currency])->result_array();
        if($owned)throw new DomainException('company_currency_snapshot_required');
        $afterCatalog=IssuerStorage::query($this->db,'SELECT id,name FROM '.$this->p.'currencies WHERE id=?',[$currency])->result_array();
        $afterOwned=IssuerStorage::query($this->db,$sql,[$issuer,$currency])->result_array();
        if($afterCatalog!==$catalog||$afterOwned!==$owned)throw new DomainException('company_dashboard_stale');
        return ['currency_id'=>$currency,'iso_code'=>$catalog[0]['name'],'basis'=>'catalog_display_only'];
    }
    public function read($issuer,$actor,$currency,string $period,?string $today=null):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$this->authorize($issuer,$actor);
        if($currency===null||$currency==='undefined'){
            $units=IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.'currencies WHERE isdefault=1')->result_array();
            if(count($units)!==1)throw new DomainException('company_dashboard_currency_required');$currency=$units[0]['id'];
        }
        $currency=IssuerPolicy::id($currency);$unit=$this->displayCurrency($issuer,$currency);
        $date=self::date($today??gmdate('Y-m-d'));
        [$start,$end]=self::range($date,$period);
        $candidates=IssuerStorage::query($this->db,'SELECT DISTINCT i.id FROM '.$this->p.'invoices i JOIN '.$this->p.'invoicepaymentrecords p ON p.invoiceid=i.id WHERE i.issuer_id=? AND i.currency=? AND i.status NOT IN (5,6) AND p.date>=? AND p.date<=? ORDER BY i.id LIMIT 1001',[$issuer,$currency,$start,$end])->result_array();
        if(count($candidates)>1000)throw new DomainException('company_dashboard_period_limit');
        $reader=new CompanyInvoices($this->db,$this->p);$payments=[];
        foreach($candidates as $row){
            $this->authorize($issuer,$actor);
            foreach($reader->payments($issuer,$actor,$row['id']) as $payment){
                if($payment['date']<$start||$payment['date']>$end)continue;
                $payments[]=['date'=>$payment['date'],'effective_amount'=>$payment['effective_amount']];
            }
        }
        $primary=(new IssuerResourceAccess($this->db,$this->p))->primaryId();
        $legacy=[];
        if($issuer===$primary){
            $sql='SELECT p.id,p.date,p.amount FROM '.$this->p.'invoicepaymentrecords p JOIN '.$this->p.'invoices i ON i.id=p.invoiceid WHERE i.issuer_id IS NULL AND i.currency=? AND i.status NOT IN (5,6) AND p.date>=? AND p.date<=? ORDER BY p.id LIMIT 10001';
            $legacy=IssuerStorage::query($this->db,$sql,[$currency,$start,$end])->result_array();
            if(count($legacy)>10000)throw new DomainException('company_dashboard_period_limit');
            foreach($legacy as $payment)$payments[]=['date'=>$payment['date'],'effective_amount'=>$payment['amount']];
            if(IssuerStorage::query($this->db,$sql,[$currency,$start,$end])->result_array()!==$legacy)throw new DomainException('company_dashboard_stale');
        }
        if((new IssuerResourceAccess($this->db,$this->p))->primaryId()!==$primary)throw new DomainException('company_dashboard_stale');
        $this->authorize($issuer,$actor);
        return self::project($payments,$period,$date->format('Y-m-d'))+['currency'=>$unit['iso_code'],'issuer_id'=>$issuer,
            'lineages'=>['named'=>'canonical_verified_effective_receipts','legacy'=>$issuer===$primary?'native_legacy_recorded':null]];
    }
    private static function date(string $value):DateTimeImmutable
    {
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value,new DateTimeZone('UTC'));
        if(!$date||$date->format('Y-m-d')!==$value)throw new DomainException('company_dashboard_date_invalid');return $date;
    }
    private static function range(DateTimeImmutable $today,string $period):array
    {
        if($period==='weekly'){$monday=$today->modify('monday this week');return [$monday->modify('-7 days')->format('Y-m-d'),$monday->modify('+6 days')->format('Y-m-d')];}
        if($period==='monthly')return [$today->format('Y-01-01'),$today->format('Y-12-31')];
        throw new DomainException('company_dashboard_period_invalid');
    }
    /** No floats in aggregation. Chart values are decimal strings accepted by native Chart.js. */
    public static function project(array $payments,string $period,string $today):array
    {
        $date=self::date($today);[$start,$end]=self::range($date,$period);
        $weekly=$period==='weekly';$values=$weekly?[array_fill(0,7,0),array_fill(0,7,0)]:[array_fill(0,12,0)];
        $monday=$date->modify('monday this week')->format('Y-m-d');
        foreach($payments as $payment){
            $day=self::date($payment['date']);if($payment['date']<$start||$payment['date']>$end)throw new DomainException('company_dashboard_payment_outside_range');
            $amount=CompanyInvoiceAmounts::minor((string)$payment['effective_amount']);
            if($amount<0)throw new DomainException('company_dashboard_payment_invalid');
            $series=$weekly&&$payment['date']<$monday?1:0;$bucket=$weekly?(int)$day->format('N')-1:(int)$day->format('n')-1;
            if($values[$series][$bucket]>PHP_INT_MAX-$amount)throw new DomainException('company_dashboard_payment_overflow');
            $values[$series][$bucket]+=$amount;
        }
        $labels=$weekly?['Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado','Domingo']:['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
        $datasets=[];foreach($values as $i=>$data)$datasets[]=['label'=>$weekly?($i===0?'Pagamentos desta semana':'Pagamentos da semana anterior'):'Recebimentos','backgroundColor'=>$i===0?'rgba(37,155,35,0.2)':'rgba(197,61,169,0.5)','borderColor'=>$i===0?'#84c529':'#c53da9','borderWidth'=>1,'tension'=>false,'data'=>array_map([CompanyInvoiceAmounts::class,'decimal'],$data)];
        return ['labels'=>$labels,'datasets'=>$datasets,'basis'=>'current_effective_local_receipts_by_original_receipt_date_not_bank_cashflow'];
    }
}
