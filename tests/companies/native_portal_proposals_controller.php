<?php
/** Canonical proposal portal list/detail/PDF/file routes on real scoped SQLite data. */
require __DIR__.'/sales_extras.php';
require __DIR__.'/native_portal_http_fixture.php';
require_once APPPATH.'services/NativePortalIdentity.php';
require_once APPPATH.'services/ValidatesContact.php';
require_once APPPATH.'controllers/Clients.php';
require_once APPPATH.'controllers/Proposal.php';
require_once APPPATH.'controllers/Download.php';
if(!function_exists('show_404')){function show_404(){throw new DomainException('fixture_http_404');}}

class NativeProposalsListFixture extends Clients
{
    public function layout($notInThemeViewFiles=false)
    {
        if($this->view!=='proposals')throw new LogicException('unexpected proposals view');
        extract($this->data);include APPPATH.'views/themes/perfex/views/proposals.php';
    }
}
class NativeProposalDetailFixture extends Proposal
{
    public function layout($notInThemeViewFiles=false)
    {
        if($this->view!=='viewproposal')throw new LogicException('unexpected proposal view');
        extract($this->data);include APPPATH.'views/themes/perfex/views/viewproposal.php';
    }
}

$start=$GLOBALS['essentialChecks'];
$proposalId=$documents[$a]['proposals'];$proposalFile=$fileKeys[$proposalId];
$db->query('UPDATE '.$p.'contacts SET email_verified_at=? WHERE id IN (?,?)',['2026-01-01 00:00:00',$contactA,$contactB]);
$sessionA=$portal->login($a,$person['email'],'changed-password','password_verify');
$sessionB=$portal->login($b,$person['email'],'synthetic-portal-password','password_verify');
$proposalRecord=$portalSales->document($sessionA,$a,$proposalId);

$list=(new ReflectionClass(NativeProposalsListFixture::class))->newInstanceWithoutConstructor();
foreach(get_object_vars($auth)as$key=>$value)$list->$key=$value;
$list->db=$db;$list->router->class='clients';$list->router->method='proposals';$list->input->verb='GET';$list->input->get=['issuer_id'=>(string)$a];$list->input->post=[];$list->data=[];
(new NativePortalIdentity($db,$p,str_repeat('synthetic-',4)))->establish($list->session,$a,$sessionA);
essentialCheck(PortalRequestScope::prepare($list)&&PortalRequestScope::resolve($list),'canonical proposal list resolves the verified bound contact');
ob_start();$list->proposals();$listHtml=ob_get_clean();
essentialCheck(str_contains($listHtml,'proposal/'.$proposalId.'/'.$proposalRecord['link_hash'])&&!str_contains($listHtml,'encrypted-sales-'),'canonical proposal list links only the opaque authorized document');

$detail=(new ReflectionClass(NativeProposalDetailFixture::class))->newInstanceWithoutConstructor();
foreach(get_object_vars($list)as$key=>$value)$detail->$key=$value;
$detail->router->class='proposal';$detail->router->method='index';$detail->input->verb='GET';$detail->input->get=[];$detail->input->post=[];$detail->data=[];
$detail->security=new class{function get_csrf_token_name(){return'csrf_test_name';}};
$detail->output=new class{public array$headers=[];public string$body='';function set_header($v){$this->headers[]=$v;return$this;}function set_content_type($v){$this->headers[]='Content-Type: '.$v;return$this;}function set_output($v){$this->body=$v;return$this;}};
essentialCheck(PortalRequestScope::prepare($detail)&&PortalRequestScope::resolve($detail)&&PortalRequestScope::issuerId()===$a,'canonical proposal detail recovers the bound issuer');
ob_start();$detail->index((string)$proposalId,$proposalRecord['link_hash']);$detailHtml=ob_get_clean();
$proposalDisplay=CompanySalesPreferences::presentation('proposals',$proposalRecord['number'],$proposalRecord['context'],'',$proposalRecord['payload']['date']);
essentialCheck(str_contains($detailHtml,$proposalDisplay['formatted_number'])&&str_contains($detailHtml,'synthetic-proof.pdf')&&str_contains($detailHtml,'proposal_pdf'),'proposal detail renders its immutable snapshot, PDF action and published attachment');
essentialThrows(fn()=>$detail->index((string)$proposalId,str_repeat('f',32)),'fixture_http_404','wrong opaque proposal hash is denied');

$detail->input->verb='POST';$detail->input->post=['action'=>'proposal_pdf'];
essentialThrows(fn()=>$detail->index((string)$proposalId,$proposalRecord['link_hash']),'fixture_http_404','proposal PDF requires native portal CSRF');
$detail->input->post['_native_portal_csrf']=PortalRequestScope::csrfToken($detail);$detail->index((string)$proposalId,$proposalRecord['link_hash']);
essentialCheck(str_starts_with($detail->output->body,'%PDF-'),'canonical proposal route returns the audited private PDF');

$download=(new ReflectionClass(Download::class))->newInstanceWithoutConstructor();
foreach(get_object_vars($detail)as$key=>$value)$download->$key=$value;
$download->router->class='download';$download->router->method='file';$download->input->verb='GET';$download->input->post=[];
$download->input->get=['issuer_id'=>(string)$a,'_native_portal'=>'1','proposal_id'=>(string)$proposalId,'proposal_hash'=>$proposalRecord['link_hash']];
$download->output=new class{public array$headers=[];public string$body='';function set_header($v){$this->headers[]=$v;return$this;}function set_content_type($v){$this->headers[]='Content-Type: '.$v;return$this;}function set_output($v){$this->body=$v;return$this;}};
essentialCheck(PortalRequestScope::prepare($download)&&PortalRequestScope::resolve($download),'published proposal attachment enters the canonical scoped download');
$download->file('sales_attachment',$proposalFile);essentialCheck($download->output->body===$bytes,'proposal attachment returns the exact published bytes');
$download->input->get['proposal_hash']=str_repeat('f',32);essentialThrows(fn()=>$download->file('sales_attachment',$proposalFile),'fixture_http_404','wrong proposal hash cannot read the attachment');
$download->input->get['proposal_hash']=$proposalRecord['link_hash'];$download->input->get['invoice_id']='1';essentialThrows(fn()=>$download->file('sales_attachment',$proposalFile),'fixture_http_404','mixed proposal and invoice selectors fail closed');
unset($download->input->get['invoice_id']);$download->input->get['issuer_id']=(string)$b;(new NativePortalIdentity($db,$p,str_repeat('synthetic-',4)))->establish($download->session,$b,$sessionB);
essentialCheck(PortalRequestScope::prepare($download)&&PortalRequestScope::resolve($download)&&PortalRequestScope::issuerId()===$b,'secondary company resolves only its own portal context');
essentialThrows(fn()=>$download->file('sales_attachment',$proposalFile),'fixture_http_404','secondary company cannot download the known proposal attachment');

(new NativePortalIdentity($db,$p,str_repeat('synthetic-',4)))->establish($detail->session,$b,$sessionB);$detail->router->class='proposal';$detail->router->method='index';$detail->input->verb='GET';$detail->input->get=[];$detail->input->post=[];
essentialCheck(PortalRequestScope::prepare($detail)&&PortalRequestScope::resolve($detail),'secondary proposal context is sealed');
essentialThrows(fn()=>$detail->index((string)$proposalId,$proposalRecord['link_hash']),'fixture_http_404','secondary company cannot read a known proposal URL');

echo 'Native portal proposal controllers: '.($GLOBALS['essentialChecks']-$start).' real SQL checks passed ('.$db->driver().').'.PHP_EOL;
