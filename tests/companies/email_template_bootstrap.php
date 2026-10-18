<?php
require dirname(__DIR__).'/company_context.php';
require APPPATH.'models/Emails_model.php';
require APPPATH.'controllers/admin/Emails.php';
$c=new CompanyContext($db,$p,$s,8);$c->resolve();$c->select(54,$c->csrf(),$c->token());
$db->query("INSERT INTO {$p}issuers_resource_permissions VALUES(54,8,'email_templates','view'),(54,8,'email_templates','edit')");
$ci=contextEnvironment($f,$s,'emails','index');hooks()->trace=[];
$editor=new Emails();
essentialCheck(CompanyRequestScope::sealedFor($editor),'actual email controller attested before hydration');
essentialCheck($editor->load->autoload['libraries']===[] && $editor->load->autoload['model']===[],'editor does not autoload transport or legacy App');
essentialCheck(hooks()->trace===[],'actual email model constructor does not call global initialization hooks');
essentialCheck(CompanySettingsRuntime::canResource('email_templates','edit'),'resource ACL permits this company editor');
$token=CompanyRequestScope::token();
$data=['company_identity'=>['legal_name'=>'Company <script>unsafe</script>'],'selected'=>(object)['emailtemplateid'=>10,'name'=>'<b>Reset</b>','subject'=>'Subject','fromname'=>'','fromemail'=>'','message'=>'</textarea><script>unsafe()</script>','active'=>1,'plaintext'=>0],'templates'=>[],'can_edit'=>true,'saved'=>false,'error'=>null,'context_token'=>$token,'list_url'=>CompanyRequestScope::adminUrl($editor,'emails'),'edit_url'=>CompanyRequestScope::adminUrl($editor,'emails/email_template/')];
$html=(new ContextViewRenderer())->render('admin/emails/company_scoped',$data);
essentialCheck(str_contains($html,'id="header"') && str_contains($html,'id="menu"'),'actual editor retains CRM layout');
essentialCheck(!str_contains($html,'<script>unsafe') && str_contains($html,'&lt;/textarea&gt;'),'stored template and identity HTML are escaped in editor');
essentialCheck(str_contains($html,'name="_company_context" value="'.$token.'"'),'editor POST carries original company token');
$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=54 AND staff_id=8 AND feature='email_templates' AND capability='edit'");
essentialCheck(!CompanySettingsRuntime::canResource('email_templates','edit'),'resource revocation is observed during request');
$ci=contextEnvironment($f,$s,'emails','email_template','POST');$_POST=['_company_context'=>'stale'];
expectStatus(fn()=>new Emails(),409);
foreach(['sent_smtp_test_email','enable','disable','enable_by_type','disable_by_type','clear_queued_emails'] as $method){$ci=contextEnvironment($f,$s,'emails',$method);expectStatus(fn()=>new Emails(),403);}
CompanyRequestScope::finish();
echo "Native template editor bootstrap, resource ACL, token, view escaping and blocked transports passed.\n";
