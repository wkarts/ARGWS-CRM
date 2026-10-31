<?php
declare(strict_types=1);
define('BASEPATH',__DIR__);define('APPPATH',dirname(__DIR__,2).'/application/');
require APPPATH.'vendor/neitanod/forceutf8/src/ForceUTF8/Encoding.php';
function tc(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
function &get_instance(){return $GLOBALS['ci'];}
class CompanyRequestScope {static bool $native=true,$sealed=true;static function nativeFor($ci){return self::$native&&$ci===$GLOBALS['ci'];}static function sealedFor($ci){return self::$sealed&&$ci===$GLOBALS['ci'];}}
class PortalRequestScope {static $owner=null;static bool $resolved=false,$sealed=true;static function nativeFor($ci){return self::$resolved&&self::$owner===$ci;}static function sealedFor($ci){return self::nativeFor($ci)&&self::$sealed;}}
$GLOBALS['hookCalls']=[];
function hooks(){if(CompanyRequestScope::$native||PortalRequestScope::nativeFor($GLOBALS['ci']))throw new RuntimeException('global hook forbidden');return new class{function apply_filters($name,$data){$GLOBALS['hookCalls'][]=$name;if($name==='before_get_language_text'&&$data['line']==='legacy_alias')$data['line']='label';return$data;}};}
function html_escape($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function is_html($s){return strip_tags($s)!==$s;}
// Form value boundary only; renderer and translations below are the production helpers.
function set_value($name,$value){return html_escape($value);}
$ci=(object)['lang'=>new class{public array $map=['label'=>'Native field','welcome'=>'Hello %s','pair'=>'%s / %s','quote'=>'A "quote"','html'=>'<strong>Native</strong>','invalid'=>'%d %d'];function line($key,$log=true){return $this->map[$key]??false;}}];
require APPPATH.'helpers/general_helper.php';require APPPATH.'helpers/fields_helper.php';require APPPATH.'helpers/template_helper.php';
tc(_l('label')==='Native field'&&_l('welcome','company A')==='Hello company A','native formatting shared');
tc(_l('pair',['A','B'])==='A / B','native array formatting');tc(_l('invalid','one')==='%d %d','native invalid formatter fallback preserved');
tc(_l('quote')==='A &quot;quote&quot;'&&_l('html')==='<strong>Native</strong>','same native escaping rules');
tc(_l('unknown')==='unknown'&&_l('unknown_db_value')==='db_translate_not_found','same missing translation semantics');
$html=render_input('subject','label','<unsafe>', 'text',['title'=>'welcome']);
tc(str_contains($html,'class="form-control"')&&str_contains($html,'Native field')&&str_contains($html,'&lt;unsafe&gt;'),'actual render_input consumes actual isolated _l');
tc($GLOBALS['hookCalls']===[],'native helpers execute zero global hooks');
CompanyRequestScope::$sealed=false;try{_l('label');throw new LogicException('unsealed accepted');}catch(DomainException $e){tc($e->getMessage()==='native_translation_context_invalid','unsealed rejected before language/provider');}CompanyRequestScope::$sealed=true;

$dirty='<p>Native <strong>rich text</strong><script>alert(1)</script><a href="javascript:bad()">unsafe</a></p>';
$clean=html_purify($dirty);tc($clean===NativeCompanyContentHtml::purify($dirty)&&!str_contains($clean,'script')&&!str_contains($clean,'javascript:'),'render uses exact writer purifier, no alternate sanitizer');
tc(html_purify($clean)===$clean,'writer to view richtext roundtrip idempotent');
$textarea=render_textarea('message','label',$clean,[],[],'','tinymce');tc(str_contains($textarea,'textarea')&&str_contains($textarea,html_escape($clean)),'real richtext field uses actual canonical purifier');
tc(html_purify(null)===null&&html_purify('')==='','empty helper returns unchanged');
CompanyRequestScope::$sealed=false;try{html_purify('<b>nonempty</b>');throw new LogicException('unsealed HTML accepted');}catch(DomainException $e){tc($e->getMessage()==='native_html_context_invalid','richtext unsealed denied');}CompanyRequestScope::$sealed=true;
CompanyRequestScope::$native=false;tc(_l('legacy_alias')==='Native field','legacy pre-hook remains active');tc($GLOBALS['hookCalls']===['before_get_language_text','after_get_language_text'],'both legacy hooks unchanged');
$GLOBALS['hookCalls']=[];PortalRequestScope::$owner=$ci;PortalRequestScope::$resolved=true;
tc(_l('pair',['A','B'])==='A / B'&&html_purify($dirty)===$clean,'resolved portal uses identical shared formatter and purifier');
tc($GLOBALS['hookCalls']===[],'resolved portal never loads global hooks');
PortalRequestScope::$sealed=false;foreach([fn()=>_l('label'),fn()=>html_purify($dirty)]as$call){try{$call();throw new LogicException('unsealed portal accepted');}catch(DomainException $e){tc(str_contains($e->getMessage(),'context_invalid'),'unsealed portal denied');}}PortalRequestScope::$sealed=true;
PortalRequestScope::$owner=new stdClass();tc(_l('legacy_alias')==='Native field','different CI object does not receive portal attestation');
PortalRequestScope::$owner=$ci;PortalRequestScope::$resolved=false;tc(_l('legacy_alias')==='Native field','candidate alone does not bypass legacy formatter hooks');
echo "Native translation: actual general/fields helpers, native scalar/array/fallback/escaping, real input DOM, zero global hooks, unsealed negative and legacy hooks passed. Language/value boundaries are explicit fixtures.\n";
