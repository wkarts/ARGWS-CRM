<?php
/** Real original-route controller, production field helpers, DOM controls and SQL writer. Framework I/O is the seam. */
require __DIR__.'/native_contract_form_http_fixture.php';
require_once APPPATH.'services/NativeCompanyContractForm.php';
require_once APPPATH.'services/NativeCompanyAdminPresentation.php';
function is_client_logged_in(){return false;}
function set_value($name,$value=''){return e($value);}
function set_select($name,$value,$default=false){return$default?' selected="selected"':'';}
function set_checkbox($name,$value,$default=false){return$default?' checked="checked"':'';}
function app_generate_hash(){static $n=0;return hash('sha256','contract-control-'.++$n);}
function slug_it($value){return trim(strtolower(preg_replace('/[^a-zA-Z0-9]+/','-',$value)),'-');}
function startsWith($value,$prefix){return str_starts_with($value,$prefix);}
function form_error($name){return '';}
function _d($value){$store=new CompanySettingsStorage($GLOBALS['db'],$GLOBALS['p']);return(new NativeCompanyFormatting(CompanySettingsFormats::dates()[$store->get(CompanyRequestScope::$issuer,'dateformat')?:CompanySettingsCatalog::defaultValue('localization','dateformat')],'24'))->date($value);}
function _dt($value){$store=new CompanySettingsStorage($GLOBALS['db'],$GLOBALS['p']);return(new NativeCompanyFormatting(CompanySettingsFormats::dates()[$store->get(CompanyRequestScope::$issuer,'dateformat')?:CompanySettingsCatalog::defaultValue('localization','dateformat')],'24'))->datetime($value);}
require APPPATH.'helpers/template_helper.php';
require APPPATH.'helpers/fields_helper.php';
require APPPATH.'helpers/custom_fields_helper.php';
$start=$GLOBALS['essentialChecks'];$ci=$GLOBALS['CI'];$formService=new NativeCompanyContractForm($db,$p);$custom=new NativeCompanyCustomFields($db,$p);$settings=new CompanySettingsStorage($db,$p);
CompanyRequestScope::$issuer=$b;CompanyRequestScope::$actor=7;$settings->mutate($b,7,'update','dateformat','d/m/Y|%d/%m/%Y');
$reset=static function(string $verb='GET',array $post=[],array $get=[])use($ci){$ci->input->verb=$verb;$ci->input->posts=$post;$ci->input->gets=$get;$ci->output->status=200;$ci->output->body='';$GLOBALS['contractRedirect']='';};
$choices=['A "<literal>"','B & value'];$fieldIds=[];$fieldValues=[];
foreach(['input','number','textarea','select','multiselect','checkbox','date_picker','date_picker_time','colorpicker','link']as$type){
    $definition=NativeCompanyCustomFields::definition(['fieldto'=>'contracts','name'=>'Contract '.$type.' <img src=x onerror=alert(1)>','type'=>$type,'options'=>implode(', ',$choices),'required'=>1]);
    $field=$custom->saveDefinition($b,7,$definition,null,null,substr(hash('sha256','contract-form-'.$type),0,32));$fieldIds[$type]=$field['id'];
    $fieldValues[$field['id']]=match($type){'input'=>'Contract input <literal>','number'=>'12.50','textarea'=>"Description line\n<literal>",'select'=>$choices[0],'multiselect','checkbox'=>$choices,'date_picker'=>'05/11/2026','date_picker_time'=>'05/11/2026 14:35','colorpicker'=>'#12ab34','link'=>'<a href="https://example.invalid/path?x=1&amp;y=2" target="_blank">Link &lt;literal&gt;</a>'};
}
$private=$custom->saveDefinition($b,7,NativeCompanyCustomFields::definition(['fieldto'=>'contracts','name'=>'Private Contract field','type'=>'input','only_admin'=>1,'show_on_pdf'=>1,'show_on_client_portal'=>1]),null,null,substr(hash('sha256','contract-form-private'),0,32));$fieldValues[$private['id']]='ADMIN-PRIVATE-CONTRACT';
$projectIds=[];foreach([[$a,$ca,'Foreign project'],[$b,$cb,'Form project']]as[$owner,$client,$name]){
    $db->query('INSERT INTO '.$p.'company_projects(issuer_id,client_id,responsible_id,name,description,status,start_date,due_date,revision,created_at,updated_at) VALUES(?,?,7,?,?,?, ?,?,1,?,?)',[$owner,$client,$name,'','active','2026-11-05','2027-11-05','2026-11-05 00:00:00','2026-11-05 00:00:00']);$projectIds[$owner]=(int)$db->insert_id();
}
$reset();$cc->contract();$view=end($ci->load->views);
essentialCheck($ci->output->status===200&&$view[0]==='admin/contracts/contract'&&$view[1]['native_contract_form'],'new draft opens the actual original contract route and form surface');
$render=static function(array $data,string $path='admin/contracts/contract')use($ci):string{$render=function()use($data,$path){extract($data,EXTR_SKIP);include APPPATH.'views/'.$path.'.php';};ob_start();try{$render->bindTo($ci,null)();return ob_get_clean();}catch(Throwable$error){ob_end_clean();throw$error;}};
// libxml uses HTML4 raw-text parsing; script template strings are not successful controls.
$html=$render($view[1]);$prior=libxml_use_internal_errors(true);$dom=new DOMDocument();$dom->loadHTML('<?xml encoding="UTF-8">'.preg_replace('~<script\b[^>]*>.*?</script>~is','',$html));libxml_clear_errors();libxml_use_internal_errors($prior);$xpath=new DOMXPath($dom);
essentialCheck($dom->getElementById('contract-form') instanceof DOMElement&&str_contains($html,'tw-max-w-5xl')&&$dom->getElementById('contract-description') instanceof DOMElement,'real form restores original content width, information fields and separate administrative description');
essentialCheck(!$dom->getElementById('contract-new-due')->hasAttribute('required')&&$dom->getElementById('contract-new-due')->getAttribute('value')==='','optional end date starts absent instead of inventing a required due date');
essentialCheck($xpath->query('//form[@id="contract-form"]//img')->length===0,'all actual custom-field labels render literal markup without executable image nodes');
$set=static function(string$name,$value)use($xpath):void{foreach($xpath->query('//*[@name]')as$node){if($node->getAttribute('name')!==$name)continue;$type=$node->getAttribute('type');if($type==='hidden'&&is_array($value))continue;if($node->tagName==='select'){foreach($node->getElementsByTagName('option')as$option){$selected=is_array($value)?in_array($option->getAttribute('value'),$value,true):$option->getAttribute('value')===(string)$value;if($selected)$option->setAttribute('selected','selected');else$option->removeAttribute('selected');}}elseif(in_array($type,['checkbox','radio'],true)){$checked=is_array($value)?in_array($node->getAttribute('value'),$value,true):$node->getAttribute('value')===(string)$value;if($checked)$node->setAttribute('checked','checked');else$node->removeAttribute('checked');}elseif($node->tagName==='textarea')$node->nodeValue=(string)$value;else$node->setAttribute('value',(string)$value);}};
foreach(['contract[client_id]'=>$cb,'contract[name]'=>'Original contract form <literal>','contract[body]'=>'','contract[start_date]'=>'05/11/2026','contract[due_date]'=>'','contract[description]'=>'ADMIN-DESCRIPTION-CONTRACT','contract[contract_value]'=>'12.50','contract[project_revision]'=>'1']as$name=>$value)$set($name,$value);
$option=$dom->createElement('option','Form project');$option->setAttribute('value',NativeCompanyResourceId::encode('company_project',$projectIds[$b]));$option->setAttribute('selected','selected');$dom->getElementById('contract-project')->appendChild($option);
foreach($fieldValues as$field=>$value)$set('custom_fields[contracts]['.$field.']'.(is_array($value)?'[]':''),$value);
$successful=static function(DOMXPath $xpath):array{$pairs=[];foreach($xpath->query('//form[@id="contract-form"]//*[self::input or self::textarea or self::select][@name]')as$node){if($node->hasAttribute('disabled'))continue;$name=$node->getAttribute('name');$type=$node->getAttribute('type');if($name===''||in_array($type,['submit','button'],true)||(in_array($type,['checkbox','radio'],true)&&!$node->hasAttribute('checked')))continue;if($node->tagName==='select'){$selected=[];foreach($node->getElementsByTagName('option')as$option)if($option->hasAttribute('selected'))$selected[]=$option->getAttribute('value');if(!$selected&&!$node->hasAttribute('multiple')&&$node->getElementsByTagName('option')->length)$selected[]=$node->getElementsByTagName('option')->item(0)->getAttribute('value');foreach($selected as$value)$pairs[]=rawurlencode($name).'='.rawurlencode($value);}else$pairs[]=rawurlencode($name).'='.rawurlencode($node->tagName==='textarea'?$node->textContent:$node->getAttribute('value'));}parse_str(implode('&',$pairs),$post);return$post;};
$wire=$successful($xpath);
essentialCheck(array_values(array_filter($wire['custom_fields']['contracts'][$fieldIds['checkbox']],static fn($value)=>$value!=='cfk_hidden'))===$choices&&$wire['custom_fields']['contracts'][$fieldIds['multiselect']]===$choices,'real helper successful controls preserve repeated checkbox/multiselect values including literal quotes');
$settings->mutate($b,7,'update','dateformat','m/d/Y|%m/%d/%Y');$reset('POST',$wire);$cc->contract();
essentialCheck($ci->output->status===303,'actual form POST creates an owned draft through the original Contracts controller');$createdCanonical=basename(parse_url($GLOBALS['contractRedirect'],PHP_URL_PATH));$created=NativeCompanyResourceId::decode('contract',$createdCanonical)['source_id'];$document=$contracts->getForAdmin($b,7,$created);
$captured=array_column($document['native_metadata']['custom_fields'],'value','id');
essentialCheck($document['start_date']==='2026-11-05'&&$document['due_date']===null&&$document['body']===''&&$captured[$fieldIds['date_picker']]==='2026-11-05'&&$captured[$fieldIds['date_picker_time']]==='2026-11-05 14:35:00','transported dates preserve GET display semantics after settings change and permit information before content');
essentialCheck($document['native_metadata']['project']['source_id']===$projectIds[$b]&&$document['native_metadata']['description']==='ADMIN-DESCRIPTION-CONTRACT'&&count($captured)===11,'project, administrative description and all actual custom controls survive the full controller roundtrip');
essentialCheck(!str_contains(json_encode($contracts->get($b,7,$created)),'ADMIN-')&&!str_contains($document['snapshot_json'],'custom_fields'),'public get and draft snapshot contain none of the private form metadata');
$reset('POST',$wire);$cc->contract();essentialCheck($ci->output->status===303&&str_ends_with($GLOBALS['contractRedirect'],$createdCanonical),'exact successful controls replay one creation without a duplicate');
$reset();$cc->contract($createdCanonical);$editView=end($ci->load->views);$editHtml=$render($editView[1]);
essentialCheck(str_contains($editHtml,'ADMIN-DESCRIPTION-CONTRACT')&&str_contains($editHtml,'ADMIN-PRIVATE-CONTRACT')&&str_contains($editHtml,'Form project'),'editable original information form restores every authorized persisted value');
$prior=libxml_use_internal_errors(true);$editDom=new DOMDocument();$editDom->loadHTML('<?xml encoding="UTF-8">'.preg_replace('~<script\b[^>]*>.*?</script>~is','',$editHtml));libxml_clear_errors();libxml_use_internal_errors($prior);$editXpath=new DOMXPath($editDom);
// Framework hyperlink widget initialization copies its stored data-value into the hidden control.
// JavaScript interaction is separately exercised by the browser suite, not by this libxml transport check.
foreach($editXpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," cf-hyperlink ")]')as$wrapper)foreach($wrapper->getElementsByTagName('input')as$control)if($control->hasAttribute('name'))$control->setAttribute('value',$wrapper->getAttribute('data-value'));
$editWire=$successful($editXpath);$editWire['contract']['body']='PUBLIC AGREEMENT BODY';$editWire['contract']['contract_value']='';unset($editWire['custom_fields']['contracts'][$fieldIds['checkbox']]);
$reset('POST',$editWire);$cc->contract($createdCanonical);$edited=$contracts->getForAdmin($b,7,$created);$editedValues=array_column($edited['native_metadata']['custom_fields'],'value','id');
essentialCheck($ci->output->status===422&&(int)$edited['revision']===1,'unchecking a required custom field does not silently erase required data');
$editWire['custom_fields']['contracts'][$fieldIds['checkbox']]=$choices;$reset('POST',$editWire);$cc->contract($createdCanonical);$edited=$contracts->getForAdmin($b,7,$created);
essentialCheck($ci->output->status===303&&(int)$edited['revision']===2&&$edited['body']==='PUBLIC AGREEMENT BODY'&&$edited['snapshot_json']==='{}','actual edit clears the optional amount while preserving a complete valid draft');
$reset('POST',$editWire);$cc->contract($createdCanonical);essentialCheck($ci->output->status===303&&(int)$contracts->get($b,7,$created)['revision']===2,'edit retry preserves its original receipt revision');
$fresh=(new NativeCompanyContractForm($db,$p))->client($b,7,$cb,$created);$reset('GET',[],['client_id'=>(string)$cb,'contract_id'=>$createdCanonical]);$cc->client_change_data();
essentialCheck($ci->output->status===200&&json_decode($ci->output->body,true)===$fresh&&count($fresh['projects'])===1&&$fresh['projects'][0]['id']===NativeCompanyResourceId::encode('company_project',$projectIds[$b]),'real callback returns exactly the typed owned same-customer projects');
$reset('GET',[],['client_id'=>(string)$ca]);$cc->client_change_data();essentialCheck($ci->output->status===403&&!str_contains($ci->output->body,'Foreign project'),'callback rejects a foreign private customer without project data');
$reset('HEAD',[],['client_id'=>(string)$cb]);$cc->client_change_data();essentialCheck($ci->output->status===200&&$ci->output->body==='','HEAD confirms creation authority without a body');
$reset('POST');$cc->client_change_data();essentialCheck($ci->output->status===405,'callback rejects mutation verbs');
$db->query('INSERT INTO '.$p.'staff(staffid,active,admin,firstname,lastname) VALUES(27,1,0,?,?)',['Draft','creator']);$db->query('INSERT INTO '.$p.'issuers_staff(issuer_id,staff_id,can_view) VALUES(?,27,1)',[$b]);foreach([['contracts','view'],['contracts','create'],['customers','view']]as[$feature,$cap])$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,27,?,?)',[$b,$feature,$cap]);
CompanyRequestScope::$actor=27;$reset('GET',[],['client_id'=>(string)$cb]);$cc->client_change_data();$limited=json_decode($ci->output->body,true);essentialCheck($ci->output->status===200&&$limited['projects']===[]&&!$limited['can_projects'],'create-only actor gets no project data without projects.view');
$reset('GET',[],['client_id'=>(string)$cb,'contract_id'=>$createdCanonical]);$cc->client_change_data();essentialCheck($ci->output->status===403,'create permission does not open another draft edit callback');
CompanyRequestScope::$actor=7;$contracts->transition($b,7,$created,2,'activate',hash('sha256','contract-form-activate'));$public=$contracts->get($b,7,$created);
$reset('POST',$editWire);$cc->contract($createdCanonical);essentialCheck($ci->output->status===303&&$contracts->get($b,7,$created)===$public,'receipt replay remains valid after later activation without reopening the document');
$editWire['request_key']=hash('sha256','contract-form-issued-rewrite');$editWire['revision']='3';$reset('POST',$editWire);$cc->contract($createdCanonical);essentialCheck($ci->output->status===422&&$contracts->get($b,7,$created)===$public,'new edit cannot mutate emitted agreement or private metadata');
$reset('GET',[],['client_id'=>(string)$cb,'contract_id'=>$createdCanonical]);$cc->client_change_data();essentialCheck($ci->output->status===422,'issued agreement has no mutable information callback');
$reset('POST',$wire);CompanyRequestScope::$csrfValid=false;$cc->contract();CompanyRequestScope::$csrfValid=true;essentialCheck($ci->output->status===403,'new information transport retains the original sealed mutation gate');
echo 'Native contract information actual DOM/controller/private SQL: '.($GLOBALS['essentialChecks']-$start).' added / '.$GLOBALS['essentialChecks'].' cumulative PASS ('.$db->driver().'); no browser equivalence claim.'.PHP_EOL;
