<?php
/** Real custom-field definitions, renderer and transactional contact writer.
 * CI form-validation callbacks and the input-only rendering primitive remain
 * the small seams inherited from the ownership fixture; choices render here
 * through the actual select/multiselect/checkbox branches and DOM parser.
 */
require __DIR__.'/native_custom_field_helper_scope.php';
if(!function_exists('_l')){function _l($key,...$args){return $key;}}
if(!function_exists('set_select')){function set_select($name,$value){return '';}}
if(!function_exists('set_checkbox')){function set_checkbox($name,$value){return '';}}
if(!function_exists('slug_it')){function slug_it($value){return preg_replace('/[^a-z0-9]+/i','-',strtolower($value));}}
if(!function_exists('app_generate_hash')){function app_generate_hash(){static $n=0;return 'synthetic'.++$n;}}
if(!function_exists('e')){function e($value){return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}}
if(!function_exists('startsWith')){function startsWith($value,$prefix){return str_starts_with($value,$prefix);}}
$start=$GLOBALS['essentialChecks'];
$grant=$access->assignments($a,7,101);$access->assign($a,7,101,[51],$grant['revision']);
$literal='<img src=x onerror="fixture()"> & "double" \'single\'';
$label='Literal <svg onload="fixture()"> & "label"';
$choiceIds=[];$definitions=[];
foreach(['select','multiselect','checkbox']as$type){
    $def=NativeCompanyCustomFields::definition(['fieldto'=>'contacts','name'=>$label.' '.$type,'type'=>$type,'options'=>'Plain, '.$literal,'required'=>1]);
    $db->query("INSERT INTO {$p}company_custom_fields(issuer_id,fieldto,slug,definition_json,revision) VALUES(?,?,?,?,1)",[$a,'contacts','literal_'.$type,json_encode($def,JSON_THROW_ON_ERROR)]);
    $choiceIds[$type]=NativeCompanyResourceId::encode('company_custom_field',$db->insert_id());
    $definitions[$type]=$def;
}
$submitted=[$ids['contacts']=>'edited',$choiceIds['select']=>$literal,$choiceIds['multiselect']=>[$literal],$choiceIds['checkbox']=>[$literal]];
$contact=['firstname'=>'Assigned fields','lastname'=>'Contact','email'=>'assigned-fields@example.invalid','permissions'=>[]];
$revision=$native->get($a,51,101,$id)['revision'];
$native->save($a,51,101,$contact,'password_hash',$id,$revision,fn($ref)=>$fields->saveInOwnedTransaction($a,51,'contacts',$ref,$submitted),$fields->tableNames());
$html=render_custom_fields('contacts',$id);
$dom=new DOMDocument();$previous=libxml_use_internal_errors(true);
try{$dom->loadHTML('<?xml encoding="UTF-8"><form>'.$html.'</form>');}finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
$xpath=new DOMXPath($dom);
essentialCheck($xpath->query('//img|//svg|//script|//*[@onerror or @onload or @onfocus or @autofocus]')->length===0,'literal field labels/options never create executable markup or attributes');
$posted=[$ids['contacts']=>'edited'];
foreach(['select','multiselect']as$type){
    $wire=$choiceIds[$type];$name='custom_fields[contacts]['.$wire.']'.($type==='multiselect'?'[]':'');
    $select=$xpath->query('//select[@name="'.$name.'"]')->item(0);
    essentialCheck($select instanceof DOMElement,$type.' retains the canonical form name');
    $options=$xpath->query('./option',$select);$last=$options->item($options->length-1);
    essentialCheck($last->getAttribute('value')===$literal&&$last->textContent===$literal,$type.' option text and browser-decoded value preserve every character');
    essentialCheck($last->hasAttribute('selected'),$type.' restores the actual selected value');
    $text=$xpath->query('../label',$select)->item(0)->textContent;
    essentialCheck(str_contains($text,$label.' '.$type)&&str_contains($text,'*'),$type.' label is literal while required indicator remains markup');
    essentialCheck($select->getAttribute('data-fieldid')===$wire&&$select->hasAttribute('data-custom-field-required'),$type.' identity and required attributes remain intact');
    $posted[$wire]=$type==='multiselect'?[$last->getAttribute('value')]:$last->getAttribute('value');
}
$checkboxes=$xpath->query('//input[@type="checkbox" and @name="custom_fields[contacts]['.$choiceIds['checkbox'].'][]"]');
essentialCheck($checkboxes->length===2,'checkbox options preserve their original count');
$checkbox=$checkboxes->item(1);
essentialCheck($checkbox->getAttribute('value')===$literal&&$checkbox->hasAttribute('checked'),'native checkbox preserves quotes and the selected value without rewriting the contract');
$checkboxLabel=$xpath->query('//label[@for="'.$checkbox->getAttribute('id').'"]')->item(0);
essentialCheck($checkboxLabel->textContent===$literal,'checkbox option label is literal text');
essentialCheck($xpath->query('//input[@type="hidden" and @value="cfk_hidden"]')->length===2,'checkbox absence markers remain present');
$posted[$choiceIds['checkbox']]=['cfk_hidden',$checkbox->getAttribute('value')];
parse_str(http_build_query(['custom_fields'=>['contacts'=>$posted]],'', '&',PHP_QUERY_RFC3986),$decoded);
$revision=$native->get($a,51,101,$id)['revision'];
$native->save($a,51,101,$contact,'password_hash',$id,$revision,fn($ref)=>$fields->saveInOwnedTransaction($a,51,'contacts',$ref,$decoded['custom_fields']['contacts']),$fields->tableNames());
essentialCheck($fields->storedInput($a,51,'contacts',$id)===$submitted,'browser-decoded and URL-encoded values commit exactly through the real writer');
$linkDefinition=NativeCompanyCustomFields::definition(['fieldto'=>'contacts','name'=>$label.' link','type'=>'link']);
$db->query("INSERT INTO {$p}company_custom_fields(issuer_id,fieldto,slug,definition_json,revision) VALUES(?,?,?,?,1)",[$a,'contacts','literal_link',json_encode($linkDefinition,JSON_THROW_ON_ERROR)]);
$linkId=NativeCompanyResourceId::encode('company_custom_field',$db->insert_id());
$linkValue='https://example.invalid/?q="/><img src=x onerror="fixture()">';
$withLink=$submitted+[$linkId=>$linkValue];$revision=$native->get($a,51,101,$id)['revision'];
$native->save($a,51,101,$contact,'password_hash',$id,$revision,fn($ref)=>$fields->saveInOwnedTransaction($a,51,'contacts',$ref,$withLink),$fields->tableNames());
$linked=new DOMDocument();$previous=libxml_use_internal_errors(true);
try{$linked->loadHTML('<?xml encoding="UTF-8">'.render_custom_fields('contacts',$id));}finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
$linkedPath=new DOMXPath($linked);
essentialCheck($linkedPath->query('//img|//svg|//*[@onerror or @onload]')->length===0,'link labels remain literal in the same shared form');
$linkNode=$linkedPath->query('//div[contains(@class,"cf-hyperlink")]')->item(0);
$nested=new DOMDocument();$previous=libxml_use_internal_errors(true);
try{$nested->loadHTML('<?xml encoding="UTF-8">'.$linkNode->getAttribute('data-value'));}finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
$nestedPath=new DOMXPath($nested);$anchor=$nestedPath->query('//a')->item(0);
essentialCheck($nestedPath->query('//img|//*[@onerror]')->length===0&&$anchor->getAttribute('href')===$linkValue&&$anchor->textContent===$linkValue,'decoded hyperlink template preserves its URL as data without introducing markup');
essentialCheck($fields->storedInput($a,51,'contacts',$id)===$withLink,'hyperlink presentation never rewrites stored metadata');
CompanyRequestScope::$issuer=$b;
essentialThrows(fn()=>render_custom_fields('contacts',$id),'native_custom_field_denied','foreign company cannot use literal field choices to hydrate another resource');
CompanyRequestScope::$issuer=$a;
echo 'Native custom-field choice transport: '.($GLOBALS['essentialChecks']-$start).' checks passed.'.PHP_EOL;
