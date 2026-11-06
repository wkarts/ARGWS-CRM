<?php
require __DIR__.'/asaas_preferences_controller.php';
$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.5.5','asaas']);
$before=$policy->page($a,7);$beforeB=$policy->page($b,7);$account=$asaas->accounts($a,7)[0];
$post=['action'=>'save_card_settings','environment'=>'sandbox','enabled'=>'1','profile_revision'=>$before['profiles']['sandbox']['revision'],'account_revision'=>$account['revision']];
$controller->input->verb='POST';$controller->input->posted=$post;$controller->output->status=200;$run();
essentialCheck($controller->output->status===409&&$policy->page($a,7)===$before,'card settings missing CSRF cannot write');
$post['company_asaas_preferences_csrf']=$controller->session->data['company_asaas_preferences_csrf'];$controller->input->posted=$post;$controller->output->status=200;$run();$after=$policy->page($a,7);
essentialCheck($controller->output->status===200&&$after['profiles']['sandbox']['allow_card']==='1','dedicated card setting persists');
foreach(['label','description','allow_pix','allow_boleto'] as $field)essentialCheck($before['profiles']['sandbox'][$field]===$after['profiles']['sandbox'][$field],'card setting preserves '.$field);
essentialCheck($before['profiles']['production']===$after['profiles']['production']&&$policy->page($b,7)===$beforeB,'card settings preserve other environment and company');
$controller->output->status=200;$run();essentialCheck($controller->output->status===409&&$policy->page($a,7)===$after,'stale card form cannot overwrite concurrent profile edit');
$post['profile_revision']=$after['profiles']['sandbox']['revision'];$post['enabled']=['1'];$controller->input->posted=$post;$controller->output->status=200;$run();essentialCheck($controller->output->status===422&&$policy->page($a,7)===$after,'malformed card choice rejected');
$post['enabled']='0';$controller->input->posted=$post;$controller->output->status=200;$run($b);essentialCheck($policy->page($b,7)===$beforeB,'foreign account revision cannot update B card choice');
$controller->input->posted=$post;$controller->output->status=200;$run($a,21);essentialCheck($policy->page($a,7)===$after,'view-only actor cannot configure card');
echo "Dedicated card settings: scoped save, CAS, CSRF, ACL, environment isolation and preservation passed; no payment execution.\n";
