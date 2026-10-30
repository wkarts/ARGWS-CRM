<?php
declare(strict_types=1);
require_once __DIR__.'/NativePortalIdentity.php';
require_once __DIR__.'/NativePortalPresentation.php';
require_once __DIR__.'/CompanyPortalSales.php';

/** Canonical Clients::proposals list backed only by the bound contact grant. */
final class NativePortalProposalsHttp
{
    public function __construct(private $ci) {}

    public function index(): void
    {
        if (!PortalRequestScope::sealedFor($this->ci)
            || !in_array($this->ci->input->method(true), ['GET','HEAD'], true)) {
            throw new DomainException('company_portal_sales_unavailable');
        }
        $issuer=PortalRequestScope::issuerId();
        $identityService=new NativePortalIdentity($this->ci->db,db_prefix(),$this->ci->config->item('encryption_key'));
        $identity=$identityService->current($this->ci->session,$issuer);
        if(!$identity){redirect(NativePortalPresentation::url('authentication/login'),'location',303);return;}
        $portal=new CompanyPortal($this->ci->db,db_prefix(),$this->ci->config->item('encryption_key'));
        $source=new CompanyPortalSales($this->ci->db,db_prefix(),$portal);
        $items=$source->page($identity['binding'],$issuer,'proposals');
        if($items===null){redirect(NativePortalPresentation::url('clients'),'location',303);return;}
        $rows=[];
        foreach($items as$row){
            if($row['kind']!=='proposals'||!in_array($row['state'],['accepted','converting','converted'],true))throw new DomainException('company_portal_sales_unavailable');
            $rows[]=['id'=>(int)$row['id'],'number'=>$row['formatted_number'],'date'=>$row['date'],'open_till'=>$row['due_date'],
                'total'=>(string)$row['total'],'state'=>$row['state'],'hash'=>$row['link_hash']];
        }
        if($identityService->current($this->ci->session,$issuer)!==$identity)throw new DomainException('company_portal_sales_unavailable');
        if($this->ci->input->method(true)==='HEAD'){$this->ci->output->set_output('');return;}
        $this->ci->data(['title'=>_l('proposals'),'native_portal_proposals'=>$rows]);
        $this->ci->view('proposals');$this->ci->layout();
    }
}
