<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanySalesTableFactory.php';
require_once __DIR__.'/NativeCompanyFilters.php';

/** Scoped DTO for the original 4.0.2 split lists and quick statistics. */
final class NativeCompanySalesManage
{
    public function __construct(private $db,private string $p){}
    public function data(int $issuer,int $actor,string $kind,$selected=''):array
    {
        $factory=new NativeCompanySalesTableFactory($this->db,$this->p);$catalogue=$factory->data($issuer,$actor,$kind);
        $table=new NativeCompanySalesTable($this->db,$this->p,$issuer,$actor,$kind,$catalogue['options'],[],$catalogue['definitions']);
        $filters=$this->db->table_exists($this->p.'company_native_filters')?(new NativeCompanyFilters($this->db,$this->p,$issuer,$actor,$table))->listing():[];
        $table=new NativeCompanySalesTable($this->db,$this->p,$issuer,$actor,$kind,$catalogue['options'],$filters,$catalogue['definitions']);$total=array_sum(array_column($catalogue['counts'],'total'));$counts=[];
        foreach($catalogue['counts'] as $count)$counts[(int)$count['status']]=(int)$count['total'];
        $stats=[];foreach($catalogue['statuses'] as $status)$stats[$status]=['total'=>$total,'total_by_status'=>$counts[$status]??0,
            'percent'=>$total>0?round(($counts[$status]??0)/$total*100,2):0];
        return ['title'=>_l($kind),'native_company_sales_manage'=>true,'switch_pipeline'=>1,
            'proposal_id'=>$kind==='proposals'?$selected:'','estimateid'=>$kind==='estimates'?$selected:'',
            'table'=>$table,'estimates_table'=>$table,'estimate_statuses'=>$catalogue['statuses'],
            'estimates_years'=>$catalogue['years'],'estimates_sale_agents'=>$catalogue['agents'],
            'native_sales_statistics'=>$stats,'native_sales_custom_fields'=>array_values(array_filter($catalogue['definitions'],static fn($d)=>(int)$d['show_on_table']===1))];
    }
}
