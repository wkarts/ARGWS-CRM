<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyKnowledgePresentation.php';
/** Adapter for the existing admin Knowledge_base controller, URLs and templates. */
final class NativeCompanyKnowledgeController
{
    private NativeCompanyKnowledge $kb;private int $issuer;private int $actor;
    public function __construct(private $ci)
    {
        if(!CompanyRequestScope::nativeFor($ci)||!CompanyRequestScope::sealedFor($ci))throw new DomainException('issuer_native_context_required');
        $ctx=CompanyRequestScope::context();$this->issuer=(int)$ctx->issuerId();$this->actor=(int)$ctx->staffId();
        if($this->issuer<1||$this->actor<1)throw new DomainException('issuer_knowledge_denied');
        $this->kb=new NativeCompanyKnowledge($ci->db,db_prefix());
    }
    private function render(string $view,array $extra=[]):void
    {
        NativeCompanyAdminPresentation::prepare($this->ci);
        $data=(new NativeCompanyKnowledgePresentation($this->ci->db,db_prefix()))->catalog($this->issuer,$this->actor);
        $data['native_knowledge_context']=CompanyRequestScope::token();$data['native_knowledge_csrf']=CompanyRequestScope::nativeCsrfToken($this->ci);
        $this->ci->load->view('admin/knowledge_base/'.$view,array_merge($data,$extra));
    }
    private function json($data):void {$this->ci->output->set_content_type('application/json')->set_output(json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));}
    private function mutation():array
    {
        CompanyRequestScope::assertNativeMutation($this->ci);$input=$this->ci->input->post(null,false);if(!is_array($input))throw new DomainException('issuer_knowledge_input_invalid');
        // Central mutation guard validates transport values before removing them from business input.
        foreach(['_company_context','_native_company_csrf'] as$key)unset($input[$key]);
        if(isset($this->ci->security))unset($input[$this->ci->security->get_csrf_token_name()]);
        return $input;
    }
    private function form(array $input,bool $article,$id):array
    {
        unset($input['revision'],$input['id'],$input['article_add_edit']);$slug=$article?'slug':'group_slug';$label=$article?'subject':'name';
        if($id===null){
            if(!isset($input[$label])||!is_string($input[$label]))throw new DomainException('issuer_knowledge_input_invalid');
            $input[$slug]=\app\services\utilities\Str::slug($input[$label],['lang'=>'portuguese_br']);
            $existing=$article?$this->kb->articles($this->issuer,$this->actor):$this->kb->groups($this->issuer,$this->actor);$used=array_column($existing,$slug);$base=$input[$slug];$suffix=2;while(in_array($input[$slug],$used,true))$input[$slug]=$base.'-'.$suffix++;
        }elseif($article&&!isset($input['article_order'])){$input['article_order']=$this->kb->article($this->issuer,$this->actor,$id)['article_order'];}
        return $input;
    }
    public function index():void
    {
        if(!$this->ci->input->is_ajax_request()){$this->render('articles',['bodyclass'=>'top-tabs kan-ban-body','title'=>_l('kb_string')]);return;}
        $input=$this->mutation();require_once __DIR__.'/NativeCompanyDataTableWire.php';$tableOrders=$input['order']??[];$remember=isset($input['last_order_identifier'],$input['order']);
        if(isset($input['last_order_identifier'])&&$input['last_order_identifier']!=='kb-articles')throw new DomainException('issuer_knowledge_input_invalid');unset($input['last_order_identifier']);
        $groups=$this->kb->groups($this->issuer,$this->actor);$filter=[];foreach($groups as$g){$key='kb_group_'.$g['groupid'];if(array_key_exists($key,$input)){if(!in_array($input[$key],['',$key],true))throw new DomainException('issuer_knowledge_input_invalid');if($input[$key]===$key)$filter[]=$g['groupid'];unset($input[$key]);}}
        $query=NativeCompanyDataTableWire::request($input,['subject','group_name','datecreated']);$records=$this->kb->articles($this->issuer,$this->actor);$acl=new IssuerResourceAccess($this->ci->db,db_prefix());$edit=$acl->can($this->issuer,$this->actor,'knowledge_base','edit')||$acl->can($this->issuer,$this->actor,'knowledge_base','create');
        if(!$edit)$records=array_values(array_filter($records,static fn($r)=>(int)$r['active']===1));$total=count($records);
        $records=array_values(array_filter($records,static function($r)use($filter,$query){if($filter&&!in_array($r['articlegroup'],$filter,true))return false;if($query['search']==='')return true;foreach(['subject','group_name','datecreated']as$key)if(mb_stripos((string)$r[$key],$query['search'],0,'UTF-8')!==false)return true;return false;}));
        usort($records,static function($a,$b)use($query){foreach($query['orders']as$order){$result=strnatcasecmp((string)$a[$order['field']],(string)$b[$order['field']]);if($result!==0)return$order['direction']==='asc'?$result:-$result;}return strcmp($a['articleid'],$b['articleid']);});$filtered=count($records);if($query['length']!==-1)$records=array_slice($records,$query['start'],$query['length']);
        $rows=[];$aColumns=['subject','articlegroup','datecreated'];$native_knowledge_issuer=$this->issuer;
        foreach($records as$aRow){$aRow['name']=$aRow['group_name'];$rows[]=require APPPATH.'views/admin/knowledge_base/table_row.php';}
        if(!$acl->can($this->issuer,$this->actor,'knowledge_base','view'))throw new DomainException('issuer_knowledge_denied');
        if($remember && get_option('save_last_order_for_tables')==='1'){require_once __DIR__.'/NativeCompanyTableOrder.php';(new NativeCompanyTableOrder($this->ci->db,db_prefix(),$this->issuer,$this->actor))->save('kb-articles',$tableOrders,3);}
        $this->json(['draw'=>$query['draw'],'iTotalRecords'=>$total,'iTotalDisplayRecords'=>$filtered,'aaData'=>$rows]);
    }
    public function article($id=''):void
    {
        $id=$id===''?null:$id;
        if(strtoupper($this->ci->input->method())==='POST'){
            $input=$this->mutation();$record=$this->kb->save($this->issuer,$this->actor,true,$this->form($input,true,$id),$id,$input['revision']??null);
            redirect(admin_url('knowledge_base/article/'.$record['articleid']));return;
        }
        $record=$id===null?null:$this->kb->article($this->issuer,$this->actor,$id);
        $this->render('article',array_merge(['bodyclass'=>'kb-article','title'=>$id===null?_l('add_new',_l('kb_article')):_l('edit',_l('kb_article')).' '.$record['subject']],$record===null?[]:['article'=>(object)$record]));
    }
    public function view(string $slug):void
    {
        $article=$this->kb->bySlug($this->issuer,$this->actor,$slug);$this->render('view',['article'=>(object)$article,'related_articles'=>$this->kb->related($this->issuer,$this->actor,$article['articleid']),'title'=>$article['subject']]);
    }
    public function manageGroups():void{$this->render('manage_groups',['title'=>_l('als_kb_groups')]);}
    public function group():void
    {
        $input=$this->mutation();$id=isset($input['id'])&&$input['id']!==''?$input['id']:null;
        $record=$this->kb->save($this->issuer,$this->actor,false,$this->form($input,false,$id),$id,$input['revision']??null);$this->json(['id'=>$record['groupid'],'success'=>true,'name'=>$record['name'],'revision'=>$record['revision_token']]);
    }
    public function getArticle($id):void{$this->json($this->kb->article($this->issuer,$this->actor,$id));}
    public function feedback():void{$input=$this->mutation();$ok=$this->kb->feedback($this->issuer,$this->actor,$input['articleid']??null,$input['answer']??'');$this->json(['success'=>$ok,'message'=>_l($ok?'clients_article_voted_thanks_for_feedback':'clients_article_only_1_vote_today')]);}
    public function status(bool $article,$id,$status):void{$input=$this->mutation();$row=$this->kb->change($this->issuer,$this->actor,$article,$id,$input['revision']??'',['active'=>$status]);$this->json(['success'=>true,'revision'=>$row['revision_token']]);}
    public function delete(bool $article,$id):void{$input=$this->mutation();$this->kb->delete($this->issuer,$this->actor,$article,$id,$input['revision']??'');$this->json(['success'=>true]);}
    public function reorder(bool $articles):void
    {
        $input=$this->mutation();$order=$input['order']??null;$revisions=$input['revisions']??null;if(!is_array($order)||!array_is_list($order)||!is_array($revisions)||count($order)!==count($revisions))throw new DomainException('issuer_knowledge_input_invalid');
        $rows=[];foreach($order as$i=>$pair){if(!is_array($pair)||!array_is_list($pair)||count($pair)!==2)throw new DomainException('issuer_knowledge_input_invalid');$row=['id'=>$pair[0],'order'=>$pair[1],'revision'=>$revisions[$i]??null];if($articles)$row['group']=$input['groupid']??null;$rows[]=$row;}
        $records=$this->kb->reorder($this->issuer,$this->actor,$articles,$rows);$this->json(['success'=>true,'revisions'=>array_column($records,'revision_token')]);
    }
    public function color():void{$input=$this->mutation();$row=$this->kb->change($this->issuer,$this->actor,false,$input['group_id']??null,$input['revision']??'',['color'=>$input['color']??null]);$this->json(['success'=>true,'revision'=>$row['revision_token']]);}
}
