<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTicketTable.php';
require_once __DIR__.'/NativeCompanyTicketCatalog.php';
require_once __DIR__.'/NativeCompanyDepartments.php';
require_once __DIR__.'/NativeCompanyTicketCommands.php';
require_once __DIR__.'/NativeCompanyCustomFields.php';
final class NativeCompanyTicketTableFactory
{
    public function __construct(private$db,private string$p){}
    public function make(int$i,int$a,string$identifier='tickets',array$savedFilters=[]):NativeCompanyTicketTable
    {
        if($identifier!=='tickets')throw new DomainException('native_ticket_filter_invalid');$acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($i,$a,'tickets','view')||$acl->primaryId()===$i)throw new DomainException('native_ticket_access_denied');
        $admin=$acl->administrative($i,$a);$catalog=new NativeCompanyTicketCatalog($this->db,$this->p);$options=['department'=>[],'status'=>[],'priority'=>[],'service'=>[],'assigned'=>[]];
        foreach(['status','priority','service']as$kind)$options[$kind]=array_map(static fn($r)=>['value'=>$r['native_id'],'label'=>$r['name']],$catalog->list($i,$a,$kind));
        $options['department']=$admin?array_map(static fn($r)=>['value'=>$r['departmentid'],'label'=>$r['name']],(new NativeCompanyDepartments($this->db,$this->p))->catalog($i,$a)):[];
        $options['assigned']=$admin?array_map(static fn($r)=>['value'=>(int)$r['staffid'],'label'=>trim($r['firstname'].' '.$r['lastname'])],(new NativeCompanyTicketCommands($this->db,$this->p))->staffOptions($i,$a)):[];
        $definitions=(new NativeCompanyCustomFields($this->db,$this->p))->definitions($i,$a,'tickets',['active'=>1]);
        if(!$acl->can($i,$a,'tickets','view')||$acl->administrative($i,$a)!==$admin)throw new DomainException('native_ticket_access_denied');
        return new NativeCompanyTicketTable($this->db,$this->p,$i,$a,$identifier,$options,$savedFilters,$definitions);
    }
}
