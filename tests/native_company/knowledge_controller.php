<?php
declare(strict_types=1);
require __DIR__.'/knowledge_write.php';
require_once __DIR__.'/../../application/services/NativeCompanyKnowledgeController.php';
$db->query('UPDATE tblissuers_staff SET can_view=1 WHERE issuer_id=2 AND staff_id=17');
function db_prefix(){return 'tbl';}function admin_url($path=''){return '/sub/admin/'.$path;}function redirect($path){$GLOBALS['redirect']=$path;}function _l($key,...$args){return $key;}
final class CompanyRequestScope {static bool $sealed=true;static bool $mutation=true;static int $issuer=2;static function nativeFor($ci){return true;}static function sealedFor($ci){return self::$sealed;}static function context(){return new class{function issuerId(){return CompanyRequestScope::$issuer;}function staffId(){return 17;}};}static function assertNativeMutation($ci){if(!self::$mutation||$ci->input->method()!=='POST')throw new DomainException('csrf_denied');}}
final class KnowledgeInput {public array $data=[];public string $verb='GET';function post($key=null,$filter=false){return $key===null?$this->data:($this->data[$key]??null);}function method(){return $this->verb;}function is_ajax_request(){return true;}}
final class KnowledgeOutput{public string $body='';function set_content_type($t){kc($t==='application/json','JSON MIME');return $this;}function set_output($v){$this->body=$v;return $this;}}
$ci=(object)['db'=>$db,'input'=>new KnowledgeInput(),'output'=>new KnowledgeOutput()];$adapter=new NativeCompanyKnowledgeController($ci);
$adapter->getArticle($a);kc(json_decode($ci->output->body,true)['articleid']===$a,'real reader data JSON');$deny(fn()=>$adapter->getArticle($b));
$row=$kb->article(2,17,$a);$ci->input->data=['revision'=>$row['revision_token']];$deny(fn()=>$adapter->status(true,$a,'0'));
$ci->input->verb='POST';CompanyRequestScope::$mutation=false;$deny(fn()=>$adapter->status(true,$a,'0'));CompanyRequestScope::$mutation=true;
$adapter->status(true,$a,'0');kc($kb->article(2,17,$a)['active']===0,'POST valid CSRF/context mutates owned row');$deny(fn()=>$adapter->status(true,$a,'1'));
$row=$kb->article(2,17,$a);$ci->input->data=['revision'=>$row['revision_token'],'subject'=>'Edited through native form','slug'=>$row['slug'],'description'=>'<p>same native form</p>','articlegroup'=>$row['articlegroup']];$order=$row['article_order'];$adapter->article($a);kc($kb->article(2,17,$a)['article_order']===$order,'native form does not reset kanban order');kc($GLOBALS['redirect']==='/sub/admin/knowledge_base/article/'.$a,'same native article route');
if(!defined('BASEPATH'))define('BASEPATH',true);if(!defined('APPPATH'))define('APPPATH',__DIR__.'/../../application/');
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}function _dt($v){return$v;}function staff_can($cap,$feature){global$db;return(new IssuerResourceAccess($db,'tbl'))->can(2,17,$feature,$cap);}function site_url($v=''){return '/sub/'.$v;}
$ci->input->data=['draw'=>'4','start'=>'50','length'=>'-1','columns'=>[],'search'=>['value'=>'Edited','regex'=>'false'],'last_order_identifier'=>'kb-articles'];foreach(range(0,2)as$i)$ci->input->data['columns'][]=['data'=>(string)$i,'name'=>'','searchable'=>'true','orderable'=>'true','search'=>['value'=>'','regex'=>'false']];
$adapter->index();$wire=json_decode($ci->output->body,true);kc($wire['draw']===4&&$wire['iTotalDisplayRecords']===1&&count($wire['aaData'])===1,'same native DataTables wire ALL/search/shared row');kc(str_contains($wire['aaData'][0][0],'knowledge_base/article/'.$a),'row uses same native edit URL');
$ci->input->data['kb_group_999']='kb_group_999';$deny(fn()=>$adapter->index());
CompanyRequestScope::$sealed=false;$deny(fn()=>new NativeCompanyKnowledgeController($ci));CompanyRequestScope::$sealed=true;CompanyRequestScope::$issuer=0;$deny(fn()=>new NativeCompanyKnowledgeController($ci));
CompanyRequestScope::$issuer=2;CompanyRequestScope::$sealed=true;
class AdminController {public $db,$input,$output,$load;function __construct(){global$ci;$this->db=$ci->db;$this->input=$ci->input;$this->output=$ci->output;$this->load=new class{function model($name){throw new RuntimeException('Global knowledge model loaded');}};}}
require_once __DIR__.'/../../application/controllers/admin/Knowledge_base.php';
$controller=new Knowledge_base();$controller->get_article_by_id_ajax($a);kc(json_decode($ci->output->body,true)['articleid']===$a,'actual native controller dispatch uses owned reader without global model');$deny(fn()=>$controller->get_article_by_id_ajax($b));
echo "Knowledge controller adapter actual SQLite: read ACL, sealed context, POST/CSRF, stale revision and native-form order preserved; route wiring pending\n";
