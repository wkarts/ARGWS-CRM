<?php
/** Real Lead partials/date helper and PHP successful controls. Custom-field renderer is an explicit empty spy here; MySQL tests exercise it. */
require __DIR__.'/native_lead_original_transport.php';
require_once APPPATH.'helpers/fields_helper.php';
function set_value($name,$value=''){return e($value);}
function render_custom_fields($fieldto,$id=false,$where=[],$params=[]){if($fieldto!=='leads'||$where!==[]||!in_array($params['native_purpose']??null,['create','edit'],true))throw new RuntimeException('Incorrect Lead field renderer contract');return'';}
function leadMetadataControls(DOMXPath $dom):array
{
    $pairs=[];foreach($dom->query('//*[@id="lead_form"]//input|//*[@id="lead_form"]//select|//*[@id="lead_form"]//textarea')as$node){
        $name=$node->getAttribute('name');if($name===''||$node->hasAttribute('disabled')||in_array($node->getAttribute('type'),['submit','button','file'],true)||(in_array($node->getAttribute('type'),['checkbox','radio'],true)&&!$node->hasAttribute('checked')))continue;
        if($node->nodeName==='select'){$options=$dom->query('.//option[@selected]',$node);if(!$options->length&&!$node->hasAttribute('multiple'))$options=$dom->query('.//option[1]',$node);$values=[];foreach($options as$option)if(!$option->hasAttribute('disabled'))$values[]=$option->getAttribute('value');}
        else $values=[$node->nodeName==='textarea'?$node->textContent:$node->getAttribute('value')];
        foreach($values as$value)$pairs[]=rawurlencode($name).'='.rawurlencode($value);
    }parse_str(implode('&',$pairs),$out);unset($out['_native_company_csrf'],$out['native_leads_csrf']);return$out;
}
$start=$checks;$formats=['dateformat'=>'d/m/Y|%d/%m/%Y','time_format'=>'24'];$currency=['id'=>1,'name'=>'BRL','symbol'=>'R$','decimal_separator'=>',','thousand_separator'=>'.','placement'=>'before'];
$metadata=CompanyLeadMetadata::normalize(['version'=>1,'form_revision'=>str_repeat('f',64),'formats'=>$formats,'title'=>'Diretor <script>literal</script>','website'=>'https://example.invalid/?a=1&b=2','address'=>"Rua <literal>\nNúmero 1",'city'=>'São Paulo','state'=>'SP','zip'=>'01234-567','country'=>30,'default_language'=>'portuguese_br','lead_value'=>'-1234.56','lastcontact'=>'2026-11-05 10:11:12','tags'=>['Cliente, com vírgula','Literal <tag>']]);
$native=['revision'=>$metadata['form_revision'],'lead_revision'=>3,'values'=>CompanyLeadMetadata::values($metadata,$currency,'2026-11-05 11:00:00'),'definitions'=>[],'custom_fields'=>[],'tags'=>['tags'=>[['id'=>'1','name'=>$metadata['tags'][0],'order'=>0],['id'=>'2','name'=>$metadata['tags'][1],'order'=>1]],'revision'=>str_repeat('b',64)],'currency'=>$currency,'formats'=>$formats,'countries'=>[['country_id'=>30,'short_name'=>'Brazil'],['country_id'=>74,'short_name'=>'France']],'languages'=>['portuguese_br']];
$extended=$data+['native_lead_fields'=>$native];$extended['detail']=$detail;$extended['openEdit']=true;$html=leadRender('admin/leads/lead',$extended);$dom=leadDom($html);$wire=leadMetadataControls($dom);$decoded=NativeCompanyLeadInput::decode($wire,$canonical);
foreach(array_keys(CompanyLeadMetadata::TEXT)as$name)$check($decoded['input']['native_fields'][$name]===$metadata[$name],'Actual original control preserves '.$name);
$check($decoded['input']['native_fields']['lead_value']==='-1234.56'&&$decoded['input']['native_fields']['country']===30,'Number and country travel through the actual named controls');
$check($wire['lastcontact']==='05/11/2026 10:11:12'&&$decoded['input']['native_fields']['lastcontact']==='2026-11-05 10:11:12','Original localized date helper decodes with the form-captured format');
$check($decoded['input']['native_fields']['tags']===$metadata['tags'],'Repeated tag fields preserve commas and literal markup without delimiter loss');
$check($decoded['input']['native_fields']['formats']===$formats&&$decoded['input']['native_fields']['form_revision']===$native['revision'],'Captured formatting and review commitment are actual successful controls');
$check($dom->query('//script')->length===0&&$dom->query('//input[@name="is_public"]')->length===0,'Extended modal adds no executable content or unresolved public-access control');
$check($dom->query('//*[@id="title"]/ancestor::div[contains(@class,"col-md-6")]')->length===1&&$dom->query('//*[@id="address"]/ancestor::div[contains(@class,"col-md-6")]')->length===1,'Original two-column edit layout houses the restored fields');
$check(!array_key_exists('contacted_today',$wire)&&!array_key_exists('custom_contact_date',$wire),'Edit submits original lastcontact only');
$oldWire=$wire;foreach(['native_lead_fields','native_lead_fields_revision','native_lead_dateformat','native_lead_timeformat','title','website','address','city','state','zip','default_language','country','lead_value','lastcontact','tags','custom_fields']as$name)unset($oldWire[$name]);
$check(!isset(NativeCompanyLeadInput::decode($oldWire,$canonical)['input']['native_fields']),'Historical original-wire payload retains its old normalized shape');
$bad=$wire;unset($bad['native_lead_fields']);$reject(fn()=>NativeCompanyLeadInput::decode($bad,$canonical),'New controls without version marker are rejected, never silently dropped');
$bad=$wire;$bad['custom_fields']=['customers'=>[]];$reject(fn()=>NativeCompanyLeadInput::decode($bad,$canonical),'Foreign custom-field namespace rejected');
$bad=$wire;$bad['contacted_today']='1';$reject(fn()=>NativeCompanyLeadInput::decode($bad,$canonical),'Creation-only clock flag cannot rewrite last contact on edit');
$bad=$wire;$bad['tags']='Comma, joined';$reject(fn()=>NativeCompanyLeadInput::decode($bad,$canonical),'Tag wire must retain its typed repeated fields');
$bad=$wire;$bad['lead_value']='1.0001';$reject(fn()=>NativeCompanyLeadInput::decode($bad,$canonical),'Monetary transport cannot silently round extra precision');
$new=$extended;$new['detail']=null;$new['canonical_id']='';$new['native_lead_fields']['values']=null;$new['native_lead_fields']['tags']['tags']=[];$new['native_lead_fields']['lead_revision']=null;
$createDom=leadDom(leadRender('admin/leads/lead',$new));$createWire=leadMetadataControls($createDom);$created=NativeCompanyLeadInput::decode($createWire);
$check($created['action']==='create'&&$created['input']['native_fields']['contacted_today']===1&&$created['input']['native_fields']['lastcontact']===null,'Original create checkbox commits a clock instruction, not an unstable client timestamp');
$custom=$createWire;unset($custom['contacted_today']);$custom['custom_contact_date']='04/11/2026 09:30:00';$check(NativeCompanyLeadInput::decode($custom)['input']['native_fields']['lastcontact']==='2026-11-04 09:30:00','Original alternate contacted date reaches normalized input');
$bad=$createWire;$bad['custom_contact_date']='04/11/2026 09:30:00';$reject(fn()=>NativeCompanyLeadInput::decode($bad),'Contradictory today/custom-date form is rejected');
$field=NativeCompanyResourceId::encode('company_custom_field',17);$fieldWire=$wire;$fieldWire['custom_fields']=['leads'=>[$field=>['Literal "<choice>"','Second choice']]];$check(NativeCompanyLeadInput::decode($fieldWire,$canonical)['input']['native_fields']['custom_fields'][$field]===['Literal "<choice>"','Second choice'],'Typed literal custom-field arrays are preserved by the real decoder');
$read=$extended;$read['capabilities']['edit']=false;$read['capabilities']['delete']=false;$read['capabilities']['convert']=false;$read['openEdit']=false;$read['native_lead_fields']['definitions']=[['id'=>$field,'type'=>'link','name'=>'Private <field>']];$read['native_lead_fields']['custom_fields'][$field]='<a href="javascript:alert(1)"><img src=x onerror=alert(2)>Literal link</a>';
$readHtml=leadRender('admin/leads/lead',$read);$readDom=leadDom($readHtml);$check($readDom->query('//img|//script|//a[starts-with(@href,"javascript:")]')->length===0&&str_contains($readHtml,'Literal link')&&!str_contains($readHtml,'data-native-lead-form'),'Read-only metadata preserves labels and strips executable link behavior');
$lead_row=$detail+['canonical_id'=>$canonical,'source_name'=>'Direct','status_name'=>'Open','assigned_name'=>'Assigned A','total_notes'=>1,'total_files'=>0,'lead_value'=>'-1234.56','currency'=>$currency,'lastcontact'=>'2026-11-05 10:11:12','tags'=>$native['tags']['tags']];$row=[];$capabilities=$data['capabilities'];require APPPATH.'views/admin/leads/native_table_row.php';
$check($row[6]==='R$-1.234,56'&&str_contains($row[7],'Cliente, com vírgula')&&!str_contains($row[7],'<tag>')&&$row[11]==='2026-11-05 10:11:12','Actual table cells display exact money, literal tags and persisted contact');
$number=NativeCompanyLeadRules::compile(['match_type'=>'and','rules'=>[['id'=>'lead_value','type'=>'NumberRule','operator'=>'between','value'=>['-1234.56','9999999999999.99']]]],'2026-11-05');$check($number['params']===['-1234.56','9999999999999.99'],'Actual numeric filter retains signed full precision as bound strings');
echo 'Original Lead metadata transport/views: '.($checks-$start).' added checks passed; empty custom-field renderer is an explicit spy, no browser claim.'.PHP_EOL;
