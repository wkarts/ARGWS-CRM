<?php
declare(strict_types=1);
require_once __DIR__.'/NativePortalIdentity.php';
require_once __DIR__.'/NativePortalPresentation.php';
require_once __DIR__.'/CompanyPortalSales.php';

/** Accepted contact-bound proposal at the original public Proposal route. */
final class NativePortalProposalHttp
{
    public function __construct(private $ci) {}

    private function verified(array $identity,NativePortalIdentity $service,int $issuer):void
    {
        if($service->current($this->ci->session,$issuer)!==$identity)throw new DomainException('company_portal_sales_unavailable');
        $binding=$identity['binding'];
        $rows=IssuerStorage::query($this->ci->db,'SELECT email_verified_at FROM '.db_prefix().'contacts WHERE id=? AND userid=?',[$binding['contact_id'],$binding['client_id']])->result_array();
        if(count($rows)!==1||$rows[0]['email_verified_at']===null)throw new DomainException('company_portal_sales_unavailable');
    }

    public function index($id,$hash):void
    {
        $method=$this->ci->input->method(true);
        if(!PortalRequestScope::sealedFor($this->ci)||!in_array($method,['GET','HEAD','POST'],true)
            ||(!is_int($id)&&!is_string($id))||!preg_match('/^[1-9][0-9]*$/D',(string)$id)
            ||!is_string($hash)||!preg_match('/^[A-Za-z0-9]{32}$/D',$hash))throw new DomainException('company_portal_sales_unavailable');
        $issuer=PortalRequestScope::issuerId();$secret=$this->ci->config->item('encryption_key');
        $identityService=new NativePortalIdentity($this->ci->db,db_prefix(),$secret);$identity=$identityService->current($this->ci->session,$issuer);
        if(!$identity)throw new DomainException('company_portal_sales_unavailable');
        $portal=new CompanyPortal($this->ci->db,db_prefix(),$secret);$source=new CompanyPortalSales($this->ci->db,db_prefix(),$portal);
        $record=$source->document($identity['binding'],$issuer,$id);
        if($record['kind']!=='proposals'||!hash_equals($record['link_hash'],$hash)||!in_array($record['state'],['accepted','converting','converted'],true))throw new DomainException('company_portal_sales_unavailable');
        $this->verified($identity,$identityService,$issuer);
        if($method==='POST'){
            PortalRequestScope::assertMutation($this->ci);
            $post=$this->ci->input->post(null,false);$csrf=$this->ci->security->get_csrf_token_name();
            if(!is_array($post)||array_diff(array_keys($post),['action','_native_portal_csrf','issuer_id',$csrf])||($post['action']??null)!=='proposal_pdf')throw new DomainException('company_portal_sales_unavailable');
            require_once __DIR__.'/CompanySalesPdf.php';require_once APPPATH.'vendor/tecnickcom/tcpdf/tcpdf.php';
            $bytes=CompanySalesPdf::prepare($record)->Output('','S');
            if(!is_string($bytes)||!str_starts_with($bytes,'%PDF-')||$source->document($identity['binding'],$issuer,$id)!==$record)throw new DomainException('company_portal_sales_unavailable');
            $source->confirmDownload($identity['binding'],$issuer,$id,hash('sha256',$bytes));$this->verified($identity,$identityService,$issuer);
            $this->ci->output->set_header('Cache-Control: private, no-store')->set_header('X-Content-Type-Options: nosniff')
                ->set_header('Content-Disposition: attachment; filename="proposal.pdf"')->set_content_type('application/pdf')->set_output($bytes);return;
        }
        if($method==='HEAD'){$this->ci->output->set_output('');return;}
        $files=[];if($this->ci->db->table_exists(db_prefix().'company_sales_files')){require_once __DIR__.'/CompanySalesFiles.php';$files=(new CompanySalesFiles($this->ci->db,db_prefix()))->portalPage($portal,$identity['binding'],$issuer,$id);}
        $this->verified($identity,$identityService,$issuer);
        $this->ci->data(['title'=>_l('proposals'),'native_portal_proposal'=>$record,'native_portal_proposal_files'=>$files,
            'native_portal_proposal_hash'=>$hash,'native_portal_csrf'=>PortalRequestScope::csrfToken($this->ci)]);
        $this->ci->view('viewproposal');$this->ci->layout();
    }
}
