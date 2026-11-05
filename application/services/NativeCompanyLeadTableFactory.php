<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyLeadTable.php';
require_once __DIR__.'/CompanyLeads.php';
final class NativeCompanyLeadTableFactory
{
    public function __construct(private $db,private string $p){}
    public function make(int $issuer,int $actor,string $identifier='company_leads',array $savedFilters=[]):NativeCompanyLeadTable
    {
        $service=new CompanyLeads($this->db,$this->p);$configuration=$service->configuration($issuer,$actor);$options=['status'=>[],'source'=>[],'assigned'=>[]];
        foreach(['status'=>'stages','source'=>'sources']as$key=>$kind)$options[$key]=array_map(static fn($r)=>['value'=>(int)$r['id'],'label'=>$r['name']],$configuration[$kind]);$options['assigned']=array_map(static fn($r)=>['value'=>(int)$r['staffid'],'label'=>trim($r['firstname'].' '.$r['lastname'])],$service->staffOptions($issuer,$actor));
        return new NativeCompanyLeadTable($this->db,$this->p,$issuer,$actor,$identifier,$options,$savedFilters);
    }
}
