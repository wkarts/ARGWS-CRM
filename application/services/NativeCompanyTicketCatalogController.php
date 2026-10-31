<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTicketCatalog.php';
require_once __DIR__.'/NativeCompanyDataTableWire.php';
final class NativeCompanyTicketCatalogController
{
    private int $issuer;private int $actor;private NativeCompanyTicketCatalog $catalog;
    public function __construct(private $ci)
    {
        if(!CompanyRequestScope::nativeFor($ci)||!CompanyRequestScope::sealedFor($ci))throw new DomainException('native_ticket_catalog_denied');$context=CompanyRequestScope::context();$this->issuer=(int)$context->issuerId();$this->actor=(int)$context->staffId();$this->authorize(false);$this->catalog=new NativeCompanyTicketCatalog($ci->db,db_prefix());
    }
    private function authorize(bool $admin=true):void{$acl=new IssuerResourceAccess($this->ci->db,db_prefix());if(!$acl->member($this->issuer,$this->actor)||($admin?!$acl->administrative($this->issuer,$this->actor):(!$acl->administrative($this->issuer,$this->actor)&&!$acl->can($this->issuer,$this->actor,'tickets','view'))))throw new DomainException('native_ticket_catalog_denied');}
    private function mutation():array
    {
        CompanyRequestScope::assertNativeMutation($this->ci);$this->authorize();$data=$this->ci->input->post(null,false);if(!is_array($data))throw new DomainException('native_ticket_catalog_input_invalid');unset($data['_company_context'],$data['_native_company_csrf']);if(isset($this->ci->security))unset($data[$this->ci->security->get_csrf_token_name()]);return$data;
    }
    private function json(array $data):void{$this->ci->output->set_content_type('application/json')->set_output(json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));}
    public function priorities():void
    {
        $this->authorize();$rows=$this->catalog->list($this->issuer,$this->actor,'priority');NativeCompanyAdminPresentation::prepare($this->ci);$this->authorize();$this->ci->load->view('admin/tickets/priorities/manage',['priorities'=>$rows,'native_ticket_catalog'=>true,'native_ticket_context'=>CompanyRequestScope::token(),'native_ticket_csrf'=>CompanyRequestScope::nativeCsrfToken($this->ci),'title'=>_l('ticket_priorities')]);
    }
    public function statuses():void
    {
        $this->authorize();$rows=$this->catalog->list($this->issuer,$this->actor,'status');$counts=$this->catalog->statusCounts($this->issuer,$this->actor);foreach($rows as&$row)$row['native_total']=$counts['counts'][$row['native_id']];unset($row);NativeCompanyAdminPresentation::prepare($this->ci);$this->authorize();$this->ci->load->view('admin/tickets/tickets_statuses/manage',['statuses'=>$rows,'native_ticket_catalog'=>true,'native_ticket_unclassified'=>$counts['historical_owned'],'native_ticket_context'=>CompanyRequestScope::token(),'native_ticket_csrf'=>CompanyRequestScope::nativeCsrfToken($this->ci),'title'=>_l('ticket_statuses')]);
    }
    public function table(string $kind):void
    {
        $this->authorize();if(!in_array($kind,['service','predefined_reply'],true))throw new DomainException('native_ticket_catalog_kind_invalid');
        $data=$this->ci->input->is_ajax_request()?$this->mutation():null;$rows=$this->catalog->list($this->issuer,$this->actor,$kind);$folder=$kind==='service'?'services':'predefined_replies';
        if($data===null){NativeCompanyAdminPresentation::prepare($this->ci);$this->ci->load->view('admin/tickets/'.$folder.'/manage',['native_ticket_catalog'=>true,'native_ticket_context'=>CompanyRequestScope::token(),'native_ticket_csrf'=>CompanyRequestScope::nativeCsrfToken($this->ci),'title'=>_l($folder)]);return;}
        $aColumns=$kind==='service'?['serviceid','name']:['name'];$query=NativeCompanyDataTableWire::request($data,array_merge($aColumns,['options']));foreach($query['orders']as$order)if($order['field']==='options')throw new DomainException('native_ticket_catalog_input_invalid');$total=count($rows);
        $rows=array_values(array_filter($rows,static function($r)use($query,$aColumns){if($query['search']==='')return true;foreach($aColumns as$key)if(mb_stripos((string)$r[$key],$query['search'],0,'UTF-8')!==false)return true;return false;}));$filtered=count($rows);
        usort($rows,static function($a,$b)use($query){foreach($query['orders']as$order){$cmp=strnatcasecmp((string)$a[$order['field']],(string)$b[$order['field']]);if($cmp!==0)return$order['direction']==='asc'?$cmp:-$cmp;}return strcmp($a['native_id'],$b['native_id']);});if($query['length']!==-1)$rows=array_slice($rows,$query['start'],$query['length']);
        $native_ticket_catalog=true;$out=[];foreach($rows as$aRow)$out[]=require APPPATH.'views/admin/tickets/'.$folder.'/table_row.php';$this->authorize();$this->json(['draw'=>$query['draw'],'iTotalRecords'=>$total,'iTotalDisplayRecords'=>$filtered,'aaData'=>$out]);
    }
    public function predefined($id=''):void
    {
        $this->authorize();if($this->ci->input->post()){$data=$this->mutation();$revision=$data['revision']??null;$area=isset($data['ticket_area']);unset($data['revision'],$data['ticket_area']);$record=$this->catalog->save($this->issuer,$this->actor,'predefined_reply',$data,$id===''?null:$id,$revision);if($area){$this->json(['success'=>true,'id'=>$record['native_id']]);return;}redirect(CompanyRequestScope::adminUrl($this->ci,'tickets/predefined_reply/'.$record['native_id']),'location',303);return;}
        $row=$id===''?null:$this->catalog->get($this->issuer,$this->actor,'predefined_reply',$id);NativeCompanyAdminPresentation::prepare($this->ci);$data=['native_ticket_catalog'=>true,'native_ticket_context'=>CompanyRequestScope::token(),'native_ticket_csrf'=>CompanyRequestScope::nativeCsrfToken($this->ci),'title'=>_l($row?'edit':'add_new',_l('predefined_reply'))];if($row!==null)$data['predefined_reply']=(object)$row;$this->authorize();$this->ci->load->view('admin/tickets/predefined_replies/reply',$data);
    }
    public function predefinedRead($id):void{$this->json($this->catalog->get($this->issuer,$this->actor,'predefined_reply',$id));}
    public function save(string $kind):void
    {
        $data=$this->mutation();$id=($data['id']??'')!==''?$data['id']:null;$revision=$data['revision']??null;unset($data['id'],$data['revision']);if($kind==='service')unset($data['ticket_area']);$record=$this->catalog->save($this->issuer,$this->actor,$kind,$data,$id,$revision);$this->json(['success'=>true,'id'=>$record['native_id'],'revision'=>$record['revision_token'],'name'=>$record['name']]);
    }
    public function delete(string $kind,$id):void{$data=$this->mutation();if(array_diff(array_keys($data),['revision']))throw new DomainException('native_ticket_catalog_input_invalid');$this->catalog->delete($this->issuer,$this->actor,$kind,$id,$data['revision']??'');$this->json(['success'=>true]);}
}
