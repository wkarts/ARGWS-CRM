<?php
require __DIR__.'/native_contact_controller.php';
require_once APPPATH.'services/NativeCompanyConsents.php';
NativeCompanyConsentSchema::ensure($db,$p);unset($db->data_cache['table_names']);
$start=$GLOBALS['essentialChecks'];$settings=new CompanySettingsStorage($db,$p);
foreach(['enable_gdpr','gdpr_enable_consent_for_contacts']as$key)$settings->mutate($a,7,'update',$key,'1');
$consents=new NativeCompanyConsents($db,$p);$purpose=$consents->savePurpose($a,7,['name'=>'Synthetic owned purpose','description'=>'No external collection']);
$controller->input->verb='POST';$controller->input->body=['draw'=>'4','length'=>'25','order'=>[['column'=>'2','dir'=>'asc']],'search'=>['value'=>'Form']];$controller->contacts($client);$table=json_decode($controller->output->body,true);
essentialCheck(count($table['data'][0])===8&&str_contains($table['data'][0][1],'Synthetic owned purpose')&&str_contains($table['data'][0][2],'mailto:'),'owned consent column and shifted sort preserve native column positions');
$metadata=$service->metadata($a,7,$client);$data=['native_customers'=>true,'client'=>(object)['userid'=>$client,'registration_confirmed'=>1],'native_contact_metadata'=>$metadata,'native_contact_create'=>true,'native_contact_consent_enabled'=>true,'native_contact_table_fields'=>[]];
function is_empty_customer_company($id){throw new LogicException('legacy customer helper forbidden');}
function staff_can(...$args){throw new LogicException('legacy staff ACL forbidden');}
function is_customer_admin(...$args){throw new LogicException('legacy customer assignment forbidden');}
function is_gdpr(){throw new LogicException('legacy GDPR settings forbidden');}
function render_datatable($columns,$class){echo '<table class="table-'.$class.'"><thead>';foreach($columns as$c)echo '<th>'.e(is_array($c)?$c['name']:$c).'</th>';echo '</thead></table>';}
$renderer=new class{public $session;function render($data){extract($data);ob_start();try{include APPPATH.'views/admin/clients/groups/contacts.php';return ob_get_contents();}finally{ob_end_clean();}}};$renderer->session=new class{function flashdata($k){return null;}};
$html=$renderer->render($data);essentialCheck(str_contains($html,'table-contacts')&&str_contains($html,'new-contact')&&str_contains($html,'gdpr_consent'),'original contacts tab renders with owned DTO and no global helpers');
$data['native_contact_create']=false;$html=$renderer->render($data);essentialCheck(!str_contains($html,'class="btn btn-primary new-contact'),'read-only does not receive creation action');
$settings->mutate($a,7,'update','gdpr_enable_consent_for_contacts','0');essentialCheck(!$service->consentEnabled($a,7,$client),'configuration revision immediately hides consent surface in own company');
essentialThrows(fn()=>$service->consentEnabled($b,7,$client),'company_client_access_denied','foreign company cannot read contact tab configuration');
CompanyCustomerAdminSchema::ensure($db,$p);foreach(['firstname','lastname']as$field)if(!$db->field_exists($field,$p.'staff'))$db->query('ALTER TABLE '.$p.'staff ADD '.$field.' varchar(191)');$db->query("INSERT INTO {$p}staff(staffid,active,admin) VALUES(51,1,0)");$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(?,51,1),(?,51,1)",[$a,$b]);$access=new CompanyCustomerAccess($db,$p);$grant=$access->assignments($a,7,$client);$access->assign($a,7,$client,[51],$grant['revision']);
essentialCheck(count($consents->purposes($a,51,$id))===1,'assigned customer contact may read exact consent purposes without global customer permission');
essentialThrows(fn()=>$consents->purposes($a,51),'company_client_access_denied','contact assignment cannot administer purpose catalog');
$grant=$access->assignments($a,7,$client);$access->assign($a,7,$client,[],$grant['revision']);essentialThrows(fn()=>$consents->purposes($a,51,$id),'company_client_access_denied','revoked assignment cannot read consent history');
echo 'Native original contact tab: '.($GLOBALS['essentialChecks']-$start).' checks passed.'.PHP_EOL;
