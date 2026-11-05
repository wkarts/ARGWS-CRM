<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanySalesTable.php';
require_once __DIR__.'/NativeCompanySalesDirectory.php';

final class NativeCompanySalesTableFactory
{
    public function __construct(private $db,private string $p){}
    public function data(int $issuer,int $actor,string $kind):array
    {
        $reader=new NativeCompanySalesDirectory($this->db,$this->p);$data=$reader->catalogue($issuer,$actor,$kind);
        $statuses=range(1,$kind==='estimates'?5:6);
        $options=['status'=>array_map(static fn($status)=>['value'=>(string)$status,'label'=>NativeCompanySalesTable::statusLabel($kind,$status)],$statuses),
            'year'=>array_map(static fn($row)=>['value'=>(string)$row['year'],'label'=>(string)$row['year']],$data['years']),
            $kind==='estimates'?'sale_agent':'assigned'=>array_map(static fn($row)=>['value'=>(string)$row['sale_agent'],'label'=>$row['name']],$data['agents'])];
        if($kind==='proposals')$options['rel_type']=[['value'=>'lead','label'=>_l('proposal_for_lead')],['value'=>'customer','label'=>_l('proposal_for_customer')]];
        return $data+['options'=>$options,'statuses'=>$statuses,'definitions'=>$reader->customDefinitions($issuer,$actor,$kind)];
    }
    public function make(int $issuer,int $actor,string $identifier='estimates',array $savedFilters=[]):NativeCompanySalesTable
    {
        $data=$this->data($issuer,$actor,$identifier);
        return new NativeCompanySalesTable($this->db,$this->p,$issuer,$actor,$identifier,$data['options'],$savedFilters,$data['definitions']);
    }
}
