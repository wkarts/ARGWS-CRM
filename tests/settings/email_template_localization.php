<?php
/** Real production helpers against disposable SQLite/MySQL; never sends email. */
require __DIR__.'/company_settings.php';
require_once APPPATH.'services/EmailTemplateReconciler.php';
$start=$GLOBALS['essentialChecks'];
$idDefinition=$db->driver()==='sqlite'?'INTEGER PRIMARY KEY AUTOINCREMENT':'INT NOT NULL AUTO_INCREMENT PRIMARY KEY';
$db->query("CREATE TABLE {$p}emailtemplates(emailtemplateid {$idDefinition},issuer_id INT NULL,type TEXT NOT NULL,slug VARCHAR(100) NOT NULL,language VARCHAR(40),name TEXT NOT NULL,subject TEXT NOT NULL,message TEXT NOT NULL,fromname TEXT NOT NULL,fromemail VARCHAR(100),plaintext INT NOT NULL DEFAULT 0,active INT NOT NULL DEFAULT 0,`order` INT NOT NULL) ENGINE=InnoDB");
unset($db->data_cache['table_names']);
$insert=function(array $row)use($db,$p){$columns=array_keys($row);$db->query('INSERT INTO '.$p.'emailtemplates (`'.implode('`,`',$columns).'`) VALUES ('.implode(',',array_fill(0,count($columns),'?')).')',array_values($row));return $db->insert_id();};
$get=function($issuer,$slug,$language)use($db,$p){return $db->query('SELECT * FROM '.$p.'emailtemplates WHERE '.($issuer===null?'issuer_id IS NULL':'issuer_id='.(int)$issuer).' AND slug=? AND language=?',[$slug,$language])->row_array();};
$defaults=json_decode(file_get_contents(APPPATH.'services/email_templates_ptbr/company_defaults.json'),true,512,JSON_THROW_ON_ERROR);
foreach($defaults as $row)$insert($row+['issuer_id'=>null]);
essentialCheck(EmailTemplateReconciler::upgrade($db,$p)===82,'fresh source seed creates all 82 PT-BR variants');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}emailtemplates")->row()->n===164,'English is retained alongside PT-BR');
foreach($defaults as $row){
 $localized=$get(null,$row['slug'],'portuguese_br');
 essentialCheck($localized['subject']===EmailTemplatesPtBr::get($row['slug'])['subject'] && $localized['message']===EmailTemplatesPtBr::translateMessage($row['message']),'correct localized core source '.$row['slug']);
 $english=$get(null,$row['slug'],'english');
 essentialCheck($english['name']===$row['name'] && $english['subject']===$row['subject'] && $english['message']===$row['message'],'original English retained '.$row['slug']);
}
essentialCheck(EmailTemplateReconciler::upgrade($db,$p)===0,'replay performs no localization writes');
$base=$defaults[0];$slug=$base['slug'];
$a=$base;$a['language']='portuguese_br';$a['issuer_id']=41;$a['subject']='Assunto personalizado A';$a['message']='Texto exclusivo A {companyname}';$a['active']=0;$a['fromname']='Remetente A';$a['fromemail']='a@example.invalid';$a['plaintext']=1;
$aId=$insert($a);$beforeA=$get(41,$slug,'portuguese_br');
$b=$base;$b['language']='portuguese_br';$b['issuer_id']=82;$b['active']=0;$b['fromname']='Remetente B';$b['fromemail']='b@example.invalid';$bId=$insert($b);
$customEnglish=$defaults[1];$customEnglish['issuer_id']=41;$customEnglish['subject']='Customer-defined English';$customEnglish['message']='Customer-owned English message';$insert($customEnglish);
$blank=$defaults[2];$blank['issuer_id']=41;$blank['language']='portuguese_br';$blank['message']='';$insert($blank);
$named=$defaults[3];$named['issuer_id']=41;$named['language']='portuguese_br';$named['name'].=' [portuguese_br]';$insert($named);
$foreign=$defaults[4];$foreign['issuer_id']=41;$foreign['language']='french';$foreign['subject']='Objet français';$foreign['message']='Texte français';$insert($foreign);
$foreignBefore=$get(41,$foreign['slug'],'french');
CompanySettingsRuntime::run($db,$p,41,12,function()use($db,$p,$get,$slug,$b){
 EmailTemplateReconciler::synchronize($db,$p);
 essentialCheck($get(82,$slug,'portuguese_br')['message']===$b['message'],'A admin synchronization never rewrites B');
});
EmailTemplateReconciler::upgrade($db,$p);
$afterA=$get(41,$slug,'portuguese_br');
essentialCheck($afterA['subject']===$beforeA['subject'] && $afterA['message']===$beforeA['message'],'custom subject/body preserved byte for byte');
foreach(['active','fromname','fromemail','plaintext','issuer_id','language','emailtemplateid'] as $field)essentialCheck($afterA[$field]===$beforeA[$field],'A preserves '.$field);
$afterB=$get(82,$slug,'portuguese_br');
essentialCheck($afterB['message']===EmailTemplatesPtBr::translateMessage($b['message']) && $afterB['active']==0 && $afterB['fromemail']==='b@example.invalid','B known defaults translated without enabling or changing sender');
essentialCheck($get(41,$customEnglish['slug'],'portuguese_br')===[],'custom English is not copied and mislabeled as Portuguese');
essentialCheck($get(41,$blank['slug'],'portuguese_br')['message']==='','intentional blank remains blank');
essentialCheck($get(41,$named['slug'],'portuguese_br')['name']===EmailTemplatesPtBr::get($named['slug'])['name'],'known migration124 name suffix is localized');
essentialCheck($get(41,$foreign['slug'],'french')===$foreignBefore,'legitimate French content/metadata untouched');
// Every known literal/derived historical variant preserves HTML and merge fields.
foreach(EmailTemplatesPtBr::sourceDefinitions() as $sourceSlug=>$sources)foreach($sources as $definition){
 $row=['slug'=>$sourceSlug,'type'=>$definition['type'],'language'=>'portuguese_br','name'=>$definition['source_name'],'subject'=>$definition['source_subject'],'message'=>$definition['source_message']];
 $translated=array_replace($row,EmailTemplateReconciler::defaultOnlyChanges($row,$sources));
 preg_match_all('/<[^>]*>/u',$row['message'],$html);preg_match_all('/<[^>]*>/u',$translated['message'],$newHtml);
 foreach(['message','subject'] as $field){preg_match_all('/\{[^{}]+\}/u',$row[$field],$old);preg_match_all('/\{[^{}]+\}/u',$translated[$field],$new);essentialCheck($old===$new,'merge fields unchanged '.$sourceSlug.' '.$field);}
 essentialCheck($html===$newHtml,'HTML unchanged '.$sourceSlug);
}
// Module factory helper creates actual English/PT-BR, including core call-sites and historical aliases.
require APPPATH.'helpers/email_templates_helper.php';
$module=EmailTemplatesPtBr::moduleTemplates()['remind_user_check_in'];
essentialCheck(create_email_template($module['source_subject'],$module['source_message'],$module['type'],$module['source_name'],'remind_user_check_in')!==false,'module source creation succeeds');
essentialCheck($get(null,'remind_user_check_in','english')['message']===$module['source_message'],'new source keeps English metadata');
essentialCheck($get(null,'remind_user_check_in','portuguese_br')['message']===EmailTemplatesPtBr::translateMessage($module['source_message']),'new source localizes PT-BR');
essentialCheck(create_email_template($module['source_subject'],$module['source_message'],$module['type'],$module['source_name'],'remind_user_check_in')===false,'module create is idempotent');
create_email_template('Custom English subject','Unknown source body','custom','Unknown source','unknown-module-source');
essentialCheck($get(null,'unknown-module-source','english')['message']==='Unknown source body' && $get(null,'unknown-module-source','portuguese_br')===[],'uncataloged source not mislabeled Portuguese');
// Webhook defaults: usable fresh variants, without rewriting deliberate blanks/customizations.
$webhook=EmailTemplatesPtBr::moduleTemplates()['webhook-failed'];
$createWebhook=static fn()=>create_email_template($webhook['source_subject'],$webhook['source_message'],$webhook['type'],$webhook['source_name'],'webhook-failed');
essentialCheck($createWebhook()!==false,'new webhook default is created');
foreach(['english','portuguese_br'] as $language) {
 $row=$get(null,'webhook-failed',$language);
 essentialCheck(trim($row['message'])!=='' && trim($row['subject'])!=='','webhook variant has usable content '.$language);
 essentialCheck(!str_contains($row['message'],'{webhook_response_code}'),'default does not email raw webhook diagnostics '.$language);
}
essentialCheck($createWebhook()===false,'webhook bootstrap replay is idempotent');
require_once APPPATH.'services/EmailTemplateLocale.php';
$webhookLookups=[];
$webhookLookup=function($language)use($get,&$webhookLookups){$webhookLookups[]=$language;$row=$get(null,'webhook-failed',$language);return $row?(object)$row:null;};
$chosenWebhook=EmailTemplateLocale::select($webhookLookup,'','portuguese_br');
essentialCheck($chosenWebhook->language==='portuguese_br' && $webhookLookups===['portuguese_br'],'PT-BR application configuration selects only Portuguese webhook variant');
$webhookLookups=[];
$chosenWebhook=EmailTemplateLocale::select($webhookLookup,'english','portuguese_br');
essentialCheck($chosenWebhook->language==='english' && $webhookLookups===['english'],'explicit recipient language remains respected without duplicate selection');

$db->query("UPDATE {$p}emailtemplates SET message='Custom webhook body',subject='Custom webhook subject',active=0 WHERE slug='webhook-failed'");
$customWebhook=$db->query("SELECT * FROM {$p}emailtemplates WHERE slug='webhook-failed' ORDER BY emailtemplateid")->result_array();
essentialCheck($createWebhook()===false,'custom webhook variants are not recreated');
EmailTemplateReconciler::upgrade($db,$p);
essentialCheck($db->query("SELECT * FROM {$p}emailtemplates WHERE slug='webhook-failed' ORDER BY emailtemplateid")->result_array()===$customWebhook,'custom webhook content and disabled state survive reconciliation');
$db->query("UPDATE {$p}emailtemplates SET message='' WHERE slug='webhook-failed'");
$blankWebhook=$db->query("SELECT * FROM {$p}emailtemplates WHERE slug='webhook-failed' ORDER BY emailtemplateid")->result_array();
essentialCheck($createWebhook()===false,'existing blank webhook variants remain untouched');
EmailTemplateReconciler::upgrade($db,$p);
essentialCheck($db->query("SELECT * FROM {$p}emailtemplates WHERE slug='webhook-failed' ORDER BY emailtemplateid")->result_array()===$blankWebhook,'blank webhook body is never silently replaced');
// Explicit module migration repairs only active, recognized factory groups.
essentialCheck(EmailTemplateReconciler::repairWebhookDefaults($db,$p)===0,'disabled/custom webhook group is skipped by explicit repair');
$legacyWebhook=['issuer_id'=>501,'type'=>'webhooks','slug'=>'webhook-failed','language'=>'english','name'=>$webhook['source_name'],'subject'=>$webhook['source_subject'],'message'=>'','fromname'=>'Saved webhook sender','fromemail'=>'saved@example.invalid','active'=>1,'plaintext'=>1,'order'=>9];
$insert($legacyWebhook);
$disabledWebhook=$legacyWebhook;$disabledWebhook['issuer_id']=502;$disabledWebhook['active']=0;$insert($disabledWebhook);
$customWebhookRow=$legacyWebhook;$customWebhookRow['issuer_id']=503;$customWebhookRow['message']='User body {webhook_response_code}';$insert($customWebhookRow);
$foreignWebhook=$legacyWebhook;$foreignWebhook['issuer_id']=504;$foreignWebhook['language']='french';$insert($foreignWebhook);
$beforeDisabled=$get(502,'webhook-failed','english');$beforeCustom=$get(503,'webhook-failed','english');$beforeForeign=$get(504,'webhook-failed','french');
essentialCheck(EmailTemplateReconciler::repairWebhookDefaults($db,$p)===2,'explicit repair fills factory blank and adds missing Portuguese variant');
foreach(['english','portuguese_br'] as $language) {
 $row=$get(501,'webhook-failed',$language);
 essentialCheck($row['message']===($language==='english'?$webhook['source_message']:EmailTemplatesPtBr::translateMessage($webhook['source_message'])),'explicit repair chooses correct body '.$language);
 foreach(['fromname','fromemail','active','plaintext','order'] as $field)essentialCheck($row[$field]===$get(501,'webhook-failed','english')[$field],'repair preserves metadata '.$field);
}
essentialCheck($get(502,'webhook-failed','english')===$beforeDisabled && $get(502,'webhook-failed','portuguese_br')===[],'disabled factory group is unchanged');
essentialCheck($get(503,'webhook-failed','english')===$beforeCustom && $get(503,'webhook-failed','portuguese_br')===[],'custom body/merge fields never bypassed');
essentialCheck($get(504,'webhook-failed','french')===$beforeForeign && $get(504,'webhook-failed','english')===[],'foreign language fallback remains untouched');
essentialCheck(EmailTemplateReconciler::repairWebhookDefaults($db,$p)===0,'explicit webhook repair replay is a no-op');
// Reactivation respects the stored opposite language, including disabled defaults and custom English.
$reactivation=EmailTemplatesPtBr::moduleTemplates()['purchase-request-approval'];
$existing=['issuer_id'=>null,'type'=>$reactivation['type'],'slug'=>'purchase-request-approval','language'=>'english','name'=>$reactivation['source_name'],'subject'=>$reactivation['source_subject'],'message'=>$reactivation['source_message'],'fromname'=>'Saved sender','fromemail'=>'saved@example.invalid','active'=>0,'plaintext'=>1,'order'=>7];
$insert($existing);
create_email_template($reactivation['source_subject'],$reactivation['source_message'],$reactivation['type'],$reactivation['source_name'],'purchase-request-approval');
$created=$get(null,'purchase-request-approval','portuguese_br');
foreach(['active','plaintext','fromname','fromemail','order'] as $field)essentialCheck($created[$field]===$get(null,'purchase-request-approval','english')[$field],'reactivation preserves stored '.$field);
$db->query("DELETE FROM {$p}emailtemplates WHERE slug='purchase-request-approval' AND language='portuguese_br'");
$db->query("UPDATE {$p}emailtemplates SET message='Custom existing English' WHERE slug='purchase-request-approval'");
essentialCheck(create_email_template($reactivation['source_subject'],$reactivation['source_message'],$reactivation['type'],$reactivation['source_name'],'purchase-request-approval')===false && $get(null,'purchase-request-approval','portuguese_br')===[],'reactivation never bypasses English customization with a new Portuguese default');
$localizedDefinition=EmailTemplatesPtBr::moduleTemplates()['purchase-send-approved'];
create_email_template($localizedDefinition['subject'],EmailTemplatesPtBr::translateMessage($localizedDefinition['source_message']),$localizedDefinition['type'],$localizedDefinition['name'],'purchase-send-approved');
essentialCheck($get(null,'purchase-send-approved','portuguese_br')!==[] && $get(null,'purchase-send-approved','english')===[],'recognized Portuguese source is not mislabeled as English');
$db->query("UPDATE {$p}emailtemplates SET message='Português personalizado' WHERE slug='purchase-send-approved'");
essentialCheck(create_email_template($localizedDefinition['source_subject'],$localizedDefinition['source_message'],$localizedDefinition['type'],$localizedDefinition['source_name'],'purchase-send-approved')===false && $get(null,'purchase-send-approved','english')===[],'reactivation does not bypass custom Portuguese with a new English fallback');
$third=EmailTemplatesPtBr::moduleTemplates()['purchase-send-rejected'];
$insert(['issuer_id'=>null,'type'=>$third['type'],'slug'=>'purchase-send-rejected','language'=>'french','name'=>'Nom personnalisé','subject'=>'Objet français','message'=>'Contenu français','fromname'=>'Expéditeur','fromemail'=>'fr@example.invalid','active'=>0,'plaintext'=>0,'order'=>0]);
essentialCheck(create_email_template($third['source_subject'],$third['source_message'],$third['type'],$third['source_name'],'purchase-send-rejected')===false && $get(null,'purchase-send-rejected','english')===[] && $get(null,'purchase-send-rejected','portuguese_br')===[],'third-language-only customization does not acquire new default fallbacks');
// Native fallback is origin-bound and stops at an explicitly disabled variant, including blank bodies.
$db->query("UPDATE {$p}issuers_registry SET active=1 WHERE id=82");
$insert(array_replace($base,['issuer_id'=>41,'language'=>'english','subject'=>'English A']));
$insert(array_replace($base,['issuer_id'=>41,'language'=>'french','subject'=>'Français A']));
$db->query("UPDATE {$p}emailtemplates SET active=1 WHERE issuer_id=41 AND language='english'");
(new CompanySettingsStorage($db,$p))->updateBatch(41,12,['active_language'=>'french']);
CompanySettingsRuntime::run($db,$p,82,12,function()use($db,$p,$slug){
 $row=CompanyEmailTemplates::findForIssuer($db,$p,41,$slug,'english');essentialCheck($row->subject==='English A','explicit English recipient wins even in interactive B context');
 $row=CompanyEmailTemplates::findForIssuer($db,$p,41,$slug,'german');essentialCheck($row->subject==='Français A','missing recipient language uses configured origin A language');
 $row=CompanyEmailTemplates::findForIssuer($db,$p,41,$slug,'portuguese_br');essentialCheck($row->active==0 && $row->subject==='Assunto personalizado A','disabled requested PT-BR stops fallback');
});
$db->query("UPDATE {$p}emailtemplates SET message='' WHERE issuer_id=41 AND slug=? AND language='portuguese_br'",[$slug]);
essentialCheck(CompanyEmailTemplates::findForIssuer($db,$p,41,$slug,'portuguese_br')->active==0,'disabled blank does not fall back to enabled English');
$db->query("UPDATE {$p}emailtemplates SET active=1 WHERE issuer_id=41 AND slug=? AND language='portuguese_br'",[$slug]);
essentialCheck(CompanyEmailTemplates::findForIssuer($db,$p,41,$slug,'portuguese_br')->language==='french','enabled blank uses origin configured French');
// Injected failure after work starts rolls back the entire pass. No metadata-only success.
$db->query("UPDATE {$p}emailtemplates SET name=?,subject=?,message=? WHERE emailtemplateid=?",[$base['name'],$base['subject'],$base['message'],$aId]);
$before=$db->query("SELECT * FROM {$p}emailtemplates ORDER BY emailtemplateid")->result_array();
$db->failOn="UPDATE {$p}emailtemplates";
settingThrows(fn()=>EmailTemplateReconciler::upgrade($db,$p),'issuer_storage_failed','repair failure propagates');$db->failOn=null;
essentialCheck($db->query("SELECT * FROM {$p}emailtemplates ORDER BY emailtemplateid")->result_array()===$before,'failed reconciliation leaves stored rows unchanged');
essentialCheck(EmailTemplateReconciler::upgrade($db,$p)>0 && EmailTemplateReconciler::upgrade($db,$p)===0,'retry repairs then replay is a no-op');
$duplicate=$get(41,$slug,'portuguese_br');unset($duplicate['emailtemplateid']);$insert($duplicate);
$before=$db->query("SELECT * FROM {$p}emailtemplates ORDER BY emailtemplateid")->result_array();
settingThrows(fn()=>EmailTemplateReconciler::upgrade($db,$p),'company_email_template_ambiguous','duplicate identity fails closed');
essentialCheck($db->query("SELECT * FROM {$p}emailtemplates ORDER BY emailtemplateid")->result_array()===$before,'ambiguous input never partially translated');
echo 'Email localization: '.($GLOBALS['essentialChecks']-$start).' checks passed ('.$db->driver().').'.PHP_EOL;
