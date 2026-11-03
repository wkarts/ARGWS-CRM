<?php

/** Official Clients projects/tickets routes over real contact publication storage. */
require __DIR__.'/portal_operations_setup.php';

foreach([[$a,$ca],[$b,$cb]]as[$owner,$client]){
    $contact=$contactIds[$owner];
    foreach(['projects','tickets']as$kind){
        $access=new CompanyContactOperationAccess($db,$p,$kind);$state=$access->page($owner,7,$client)[$contact];$access->set($owner,7,$client,$contact,$state['revision'],'1');
        $id=$portalParents[$kind][$owner];$table=$kind==='projects'?'company_projects':'company_tickets';$revision=(int)$db->query('SELECT revision FROM '.$p.$table.' WHERE issuer_id=? AND id=?',[$owner,$id])->row()->revision;
        $publication->set($owner,7,$kind,$id,$revision++,'record',null,true,$key('official-work-record-'.$kind.'-'.$owner));
        $publication->set($owner,7,$kind,$id,$revision++,'file',$portalProofs[$kind][$owner],true,$key('official-work-file-'.$kind.'-'.$owner));
        if($kind==='tickets'){$reply=(int)$db->query('SELECT id FROM '.$p.'company_ticket_replies WHERE issuer_id=? AND ticket_id=?',[$owner,$id])->row()->id;$publication->set($owner,7,$kind,$id,$revision++,'reply',$reply,true,$key('official-work-reply-'.$owner));}
    }
    $db->query('UPDATE '.$p.'contacts SET email_verified_at=? WHERE id=?',['2026-01-01 00:00:00',$contact]);
}

require __DIR__.'/native_portal_http_fixture.php';
require_once APPPATH.'services/NativePortalIdentity.php';
require_once APPPATH.'services/ValidatesContact.php';
require_once APPPATH.'controllers/Clients.php';
if(!function_exists('form_open_multipart')){function form_open_multipart($path='',$attrs=[]){return str_replace('<form ','<form enctype="multipart/form-data" ',form_open($path,$attrs));}}

final class NativeWorkHttpError extends RuntimeException{public function __construct(public int$status){parent::__construct('http_'.$status);}}
function show_404(){throw new NativeWorkHttpError(404);}function show_error($message,$status=500){throw new NativeWorkHttpError((int)$status);}
class NativePortalWorkFixture extends Clients
{
    public function layout($notInThemeViewFiles=false){extract($this->data);include APPPATH.'views/themes/perfex/views/'.$this->view.'.php';}
}

$ci=(new ReflectionClass(NativePortalWorkFixture::class))->newInstanceWithoutConstructor();foreach(get_object_vars($auth)as$name=>$value)$ci->$name=$value;$ci->db=$db;
$ci->output=new class{public array$headers=[];public string$body='',$mime='';function set_header($v,$replace=true){$this->headers[]=$v;return$this;}function set_content_type($v){$this->mime=$v;return$this;}function set_output($v){$this->body=$v;return$this;}};
$ci->data=[];$identity=new NativePortalIdentity($db,$p,str_repeat('synthetic-',4));$identity->establish($ci->session,$a,$sessions[$a]);$start=$GLOBALS['essentialChecks'];
$request=function(string$method,string$verb='GET',array$get=[],array$post=[])use($ci,$a):void{$ci->router->class='clients';$ci->router->method=$method;$ci->input->verb=$verb;$ci->input->get=$get;$ci->input->post=$post;$ci->data=[];$ci->view=null;$ci->output->body='';$ci->output->mime='';$ci->output->headers=[];essentialCheck(PortalRequestScope::prepare($ci)&&PortalRequestScope::resolve($ci),'official '.$method.' resolves native portal context');};
$canonicalProjectA=NativeCompanyResourceId::encode('company_project',(string)$portalParents['projects'][$a]);$canonicalProjectB=NativeCompanyResourceId::encode('company_project',(string)$portalParents['projects'][$b]);$canonicalTicketA=NativeCompanyResourceId::encode('company_ticket',(string)$portalParents['tickets'][$a]);$canonicalTicketB=NativeCompanyResourceId::encode('company_ticket',(string)$portalParents['tickets'][$b]);

$request('projects','GET',['issuer_id'=>(string)$a]);ob_start();$ci->projects();$projectsHtml=ob_get_clean();essentialCheck(str_contains($projectsHtml,'native-portal-project-list')&&str_contains($projectsHtml,$canonicalProjectA)&&!str_contains($projectsHtml,$canonicalProjectB),'official project list exposes only the selected company typed record');
$menu=NativePortalPresentation::menu(['enable_gdpr'=>'0','show_gdpr_in_customers_menu'=>'0'],$ci);essentialCheck(isset($menu['projects'],$menu['tickets'])&&str_contains($menu['projects']['href'],'clients/projects?issuer_id='.$a),'native6/native5 advertise verified official destinations');
$request('project');ob_start();$ci->project($canonicalProjectA);$projectHtml=ob_get_clean();essentialCheck(str_contains($projectHtml,'native-portal-project-detail')&&str_contains($projectHtml,'Explicit project description')&&str_contains($projectHtml,'group=project_files'),'official project detail renders its overview and official file tab');essentialThrows(fn()=>$ci->project((string)$portalParents['projects'][$a]),'http_404','raw colliding project id cannot replace typed official identity');

$request('tickets');ob_start();$ci->tickets();$ticketsHtml=ob_get_clean();essentialCheck(str_contains($ticketsHtml,'native-portal-ticket-list')&&str_contains($ticketsHtml,$canonicalTicketA)&&!str_contains($ticketsHtml,$canonicalTicketB),'official ticket list exposes only the selected company records');
$request('ticket');ob_start();$ci->ticket($canonicalTicketA);$ticketHtml=ob_get_clean();essentialCheck(str_contains($ticketHtml,'native-portal-ticket-detail')&&str_contains($ticketHtml,'Never automatically publish internal reply')&&str_contains($ticketHtml,'proof.pdf'),'official ticket detail renders only explicitly published reply and file');

$revision=(int)$db->query('SELECT revision FROM '.$p.'company_tickets WHERE issuer_id=? AND id=?',[$a,$portalParents['tickets'][$a]])->row()->revision;
$request('ticket','POST',[],['native_ticket_action'=>'reply','revision'=>(string)$revision,'request_key'=>$key('official-ticket-reply'),'message'=>'Official contact reply']);essentialThrows(fn()=>$ci->ticket($canonicalTicketA),'http_403','official reply requires native portal CSRF');
$ci->input->post['_native_portal_csrf']=PortalRequestScope::csrfToken($ci);try{$ci->ticket($canonicalTicketA);throw new RuntimeException('ticket_reply_redirect_missing');}catch(NativePortalRedirect$redirect){essentialCheck($redirect->status===303&&str_contains($redirect->url,'clients/ticket/'.$canonicalTicketA.'?issuer_id='.$a),'reply returns to the same typed official ticket');}
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}company_portal_support_replies WHERE ticket_id=? AND contact_id=?",[$portalParents['tickets'][$a],$contactIds[$a]])->row()->n===1,'official reply persists with exact contact origin');

$request('ticket','POST',[],['native_ticket_action'=>'file','file_key'=>$portalProofs['tickets'][$a],'_native_portal_csrf'=>PortalRequestScope::csrfToken($ci)]);$ci->ticket($canonicalTicketA);essentialCheck($ci->output->body===$pdf&&$ci->output->mime==='application/pdf'&&in_array("Content-Security-Policy: sandbox; default-src 'none'",$ci->output->headers,true),'official ticket returns published bytes with sandbox headers');

$request('open_ticket');ob_start();$ci->open_ticket();$openHtml=ob_get_clean();essentialCheck(str_contains($openHtml,'native-portal-open-ticket')&&str_contains($openHtml,'_native_portal_csrf'),'official open-ticket form uses native CSRF and original route');
$request('open_ticket','POST',[],['subject'=>'New official support','message'=>'Created through Clients','priority'=>'normal','request_key'=>$key('official-ticket-open'),'_native_portal_csrf'=>PortalRequestScope::csrfToken($ci)]);try{$ci->open_ticket();throw new RuntimeException('ticket_open_redirect_missing');}catch(NativePortalRedirect$redirect){essentialCheck($redirect->status===303&&str_contains($redirect->url,'clients/ticket/'),'official ticket creation redirects to typed detail');}

$identity->establish($ci->session,$b,$sessions[$b]);$request('project');essentialThrows(fn()=>$ci->project($canonicalProjectA),'http_404','company B cannot open a known A project');ob_start();$ci->project($canonicalProjectB);$bProject=ob_get_clean();essentialCheck(str_contains($bProject,$canonicalProjectB)&&!str_contains($bProject,$canonicalProjectA),'company B official project remains isolated');
$request('ticket');essentialThrows(fn()=>$ci->ticket($canonicalTicketA),'http_404','company B cannot open a known A ticket');ob_start();$ci->ticket($canonicalTicketB);$bTicket=ob_get_clean();essentialCheck(str_contains($bTicket,$canonicalTicketB)&&!str_contains($bTicket,$canonicalTicketA),'company B official ticket remains isolated');

$db->query('DELETE FROM '.$p.'contact_permissions WHERE userid=? AND permission_id IN (5,6)',[$contactIds[$b]]);$request('projects');essentialThrows(fn()=>$ci->projects(),'http_404','revoked project grant blocks saved list URL');$menu=NativePortalPresentation::menu(['enable_gdpr'=>'0','show_gdpr_in_customers_menu'=>'0'],$ci);essentialCheck(!isset($menu['projects'],$menu['tickets']),'revocation removes both official work links');

echo 'Native portal official projects/tickets controller: '.($GLOBALS['essentialChecks']-$start).' real SQL checks passed ('.$db->driver().').'.PHP_EOL;
