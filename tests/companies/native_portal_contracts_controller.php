<?php

/** Canonical Clients::contracts transport over real owned publication storage. */
require __DIR__.'/portal_operations_setup.php';

$grants=[];
foreach([[$a,$ca],[$b,$cb]]as[$owner,$client]){
    $contact=$contactIds[$owner];
    $access=new CompanyContactOperationAccess($db,$p,'contracts');
    $state=$access->page($owner,7,$client)[$contact];
    $access->set($owner,7,$client,$contact,$state['revision'],'1');
    $id=$portalParents['contracts'][$owner];
    $revision=(int)$contracts->get($owner,7,$id)['revision'];
    $publication->set($owner,7,'contracts',$id,$revision++,'record',null,true,$key('http-contract-record-'.$owner));
    $publication->set($owner,7,'contracts',$id,$revision++,'file',$portalProofs['contracts'][$owner],true,$key('http-contract-file-'.$owner));
    $grants[$owner]=['contact'=>$contact,'revision'=>$revision];
    $db->query('UPDATE '.$p.'contacts SET email_verified_at=? WHERE id=?',['2026-01-01 00:00:00',$contact]);
}
$discussion=new CompanyContractDiscussions($db,$p);
$comment=$discussion->staffAdd($a,7,$portalParents['contracts'][$a],$grants[$a]['revision'],'Public staff note <script>literal</script>',$key('http-contract-comment'));
$discussion->setVisible($a,7,$portalParents['contracts'][$a],$grants[$a]['revision']+1,$comment,true,$key('http-contract-comment-visible'));

require __DIR__.'/native_portal_http_fixture.php';
require_once APPPATH.'services/NativePortalIdentity.php';
require_once APPPATH.'services/ValidatesContact.php';
require_once APPPATH.'controllers/Clients.php';

final class NativeContractHttpError extends RuntimeException
{
    public function __construct(public int$status){parent::__construct('http_'.$status);}
}
function show_404(){throw new NativeContractHttpError(404);}
function show_error($message,$status=500){throw new NativeContractHttpError((int)$status);}

class NativePortalContractsFixture extends Clients
{
    public function layout($notInThemeViewFiles=false)
    {
        if($this->view!=='contracts')throw new LogicException('unexpected_contract_view');
        extract($this->data);
        include APPPATH.'views/themes/perfex/views/contracts.php';
    }
}

$ci=(new ReflectionClass(NativePortalContractsFixture::class))->newInstanceWithoutConstructor();
foreach(get_object_vars($auth)as$name=>$value)$ci->$name=$value;
$ci->db=$db;
$ci->router->class='clients';
$ci->router->method='contracts';
$ci->output=new class{
    public array$headers=[];public string$body='';public string$mime='';
    function set_header($value,$replace=true){$this->headers[]=$value;return$this;}
    function set_content_type($value){$this->mime=$value;return$this;}
    function set_output($value){$this->body=$value;return$this;}
};
$ci->data=[];
$identity=new NativePortalIdentity($db,$p,str_repeat('synthetic-',4));
$identity->establish($ci->session,$a,$sessions[$a]);
$ci->input->verb='GET';$ci->input->get=['issuer_id'=>(string)$a];$ci->input->post=[];
$start=$GLOBALS['essentialChecks'];

essentialCheck(PortalRequestScope::prepare($ci)&&PortalRequestScope::resolve($ci),'official contract list resolves verified company A contact');
$canonicalA=NativeCompanyResourceId::encode('company_contract',(string)$portalParents['contracts'][$a]);
$canonicalB=NativeCompanyResourceId::encode('company_contract',(string)$portalParents['contracts'][$b]);
ob_start();$ci->contracts();$list=ob_get_clean();
essentialCheck(str_contains($list,'native-portal-contract-list')&&str_contains($list,$canonicalA)&&!str_contains($list,$canonicalB),'official contract list exposes only A with typed identity');
$menu=NativePortalPresentation::menu(['enable_gdpr'=>'0','show_gdpr_in_customers_menu'=>'0'],$ci);
essentialCheck(isset($menu['contracts'])&&str_contains($menu['contracts']['href'],'clients/contracts?issuer_id='.$a),'permission3 advertises the implemented official destination');

$ci->input->get=[];essentialCheck(PortalRequestScope::prepare($ci)&&PortalRequestScope::resolve($ci),'direct official detail recovers A from the bound session');
ob_start();$ci->contracts($canonicalA);$detail=ob_get_clean();
essentialCheck(str_contains($detail,'native-portal-contract-detail')&&str_contains($detail,'Public staff note &lt;script&gt;literal&lt;/script&gt;')&&!str_contains($detail,'<script>literal</script>'),'official detail renders published discussion as escaped text');
essentialThrows(fn()=>$ci->contracts((string)$portalParents['contracts'][$a]),'http_404','raw colliding contract ID is refused on the typed company route');
$ci->input->verb='HEAD';$ci->output->body='sentinel';ob_start();$ci->contracts($canonicalA);$head=ob_get_clean();
essentialCheck($head===''&&$ci->output->body==='','official detail HEAD authorizes the typed record without rendering response bytes');

$ci->input->verb='POST';$ci->input->post=['native_contract_action'=>'file','file_key'=>$portalProofs['contracts'][$a]];
essentialThrows(fn()=>$ci->contracts($canonicalA),'http_403','official contract file requires native portal CSRF');
$ci->input->post['_native_portal_csrf']=PortalRequestScope::csrfToken($ci);$ci->contracts($canonicalA);
essentialCheck($ci->output->body===$pdf&&$ci->output->mime==='application/pdf'&&in_array("Content-Security-Policy: sandbox; default-src 'none'",$ci->output->headers,true),'official route returns exact published private attachment with sandbox headers');

$ci->output->body='';$ci->input->post=['native_contract_action'=>'pdf','_native_portal_csrf'=>PortalRequestScope::csrfToken($ci)];$ci->contracts($canonicalA);
essentialCheck(str_starts_with($ci->output->body,'%PDF-')&&$ci->output->mime==='application/pdf','official route returns the audited immutable contract PDF');

$revision=(int)$contracts->get($a,7,$portalParents['contracts'][$a])['revision'];
$ci->input->post=['native_contract_action'=>'comment','revision'=>(string)$revision,'request_key'=>$key('http-contact-comment'),'discussion_body'=>'Contact text <img src=x onerror=alert(1)>','_native_portal_csrf'=>PortalRequestScope::csrfToken($ci)];
try{$ci->contracts($canonicalA);throw new RuntimeException('contract_comment_redirect_missing');}
catch(NativePortalRedirect$redirect){essentialCheck($redirect->status===303&&str_contains($redirect->url,'clients/contracts/'.$canonicalA.'?issuer_id='.$a),'contact comment redirects to the same typed official detail');}
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}company_contract_discussions WHERE contract_id=? AND author_type='contact'",[$portalParents['contracts'][$a]])->row()->n===1,'official discussion persists one contact-bound comment');

$identity->establish($ci->session,$b,$sessions[$b]);$ci->input->verb='GET';$ci->input->get=[];$ci->input->post=[];
essentialCheck(PortalRequestScope::prepare($ci)&&PortalRequestScope::resolve($ci)&&PortalRequestScope::issuerId()===$b,'company B resumes from its own bound contact');
essentialThrows(fn()=>$ci->contracts($canonicalA),'http_404','B cannot open a known typed A contract');
ob_start();$ci->contracts($canonicalB);$bDetail=ob_get_clean();essentialCheck(str_contains($bDetail,$canonicalB)&&!str_contains($bDetail,$canonicalA),'B official detail contains only its own typed contract');

$db->query('DELETE FROM '.$p.'contact_permissions WHERE userid=? AND permission_id=3',[$contactIds[$b]]);
essentialCheck(PortalRequestScope::prepare($ci)&&PortalRequestScope::resolve($ci),'revoked B contact remains authenticated but not authorized for contracts');
essentialThrows(fn()=>$ci->contracts($canonicalB),'http_404','permission revocation immediately blocks a saved official detail URL');
$menu=NativePortalPresentation::menu(['enable_gdpr'=>'0','show_gdpr_in_customers_menu'=>'0'],$ci);essentialCheck(!isset($menu['contracts']),'revocation removes the official contracts navigation link');

$identity->establish($ci->session,$a,$sessions[$a]);$ci->input->verb='DELETE';$ci->input->get=[];
essentialCheck(PortalRequestScope::prepare($ci)&&PortalRequestScope::resolve($ci),'A context resumes for method gate');
essentialThrows(fn()=>$ci->contracts($canonicalA),'http_405','official contract route refuses unsupported methods');

echo 'Native portal contracts controller: '.($GLOBALS['essentialChecks']-$start).' real SQL checks passed ('.$db->driver().').'.PHP_EOL;
