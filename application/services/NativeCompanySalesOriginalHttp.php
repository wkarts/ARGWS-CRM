<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanySalesOriginalForm.php';
require_once __DIR__.'/NativeCompanySalesClientData.php';

/** Only the existing, attested admin routes enter the original sales form. */
final class NativeCompanySalesOriginalHttp
{
    public function __construct(private $ci){}
    private function json(array $data,int $status=200):void
    {$this->ci->output->set_status_header($status)->set_header('Cache-Control: private, no-store')->set_content_type('application/json')->set_output(strtoupper($this->ci->input->method(true))==='HEAD'?'':json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT));}
    public function form(string $kind,$id=''):void
    {
        if(!CompanyRequestScope::sealedFor($this->ci)){show_error('Formulário indisponível nesta empresa.',403);return;}
        $method=strtoupper($this->ci->input->method(true));$post=$method==='POST';$level=ob_get_level();
        if(!in_array($method,['GET','HEAD','POST'],true)){$this->ci->output->set_header('Allow: GET, HEAD, POST');show_error('Método não permitido.',405);return;}
        try{
            $type=$kind==='estimates'?'estimate':'proposal';
            if(!is_string($id))throw new DomainException('native_sales_editor_invalid');
            if($id!==''&&NativeCompanyResourceId::decode($type,$id)['source_kind']===$type){require_once __DIR__.'/NativeCompanySalesAdminHttp.php';(new NativeCompanySalesAdminHttp($this->ci))->index($kind,$id,false,'workspace');return;}
            $ctx=CompanyRequestScope::context();$form=new NativeCompanySalesOriginalForm($this->ci->db,db_prefix(),$kind);
            if($post){
                CompanyRequestScope::assertNativeMutation($this->ci);$wire=$this->ci->input->post(null,false)?:[];
                unset($wire['_company_context'],$wire['_native_company_csrf']);if(isset($this->ci->security))unset($wire[$this->ci->security->get_csrf_token_name()]);
                if(($wire['_native_sales_id']??null)!==$id)throw new DomainException('native_sales_editor_invalid');
                $saved=$form->save($ctx->issuerId(),$ctx->staffId(),$wire);$url=admin_url($kind.'/'.($kind==='estimates'?'list_estimates':'list_proposals').'/'.$saved['id']);
                if($this->ci->input->is_ajax_request())$this->json(['success'=>true,'id'=>$saved['id'],'url'=>$url]);else redirect($url,'location',303);
                return;
            }
            $customer=$this->ci->input->get($kind==='estimates'?'customer_id':'rel_id',false);
            if($kind==='proposals'&&$customer!==null&&$customer!==''&&$this->ci->input->get('rel_type',false)!=='customer')throw new DomainException('native_proposal_customer_required');
            $data=$form->data($ctx->issuerId(),$ctx->staffId(),$id,$customer);$html=$this->ci->load->view('admin/'.$kind.'/'.$type,$data,true);$data['native_sales_editor_items']->assertCurrent();
            $this->ci->output->set_header('Cache-Control: private, no-store')->set_output($method==='HEAD'?'':$html);
        }catch(Throwable $error){
            while(ob_get_level()>$level)ob_end_clean();
            $message=match($error->getMessage()){
                'native_proposal_customer_required'=>'O contrato atual de propostas exige um cliente vinculado à empresa. A referência com Lead precisa de decisão sobre esse vínculo.',
                'native_sales_form_delivery_requires_acceptance'=>'O envio existente exige documento aceito e contato autorizado. Salve o documento e use o fluxo de aceite e envio.',
                'native_sales_status_requires_decision'=>'Aceite e recusa usam as ações de decisão do documento para preservar os registros de auditoria.',
                'native_sales_form_request_conversion_required'=>'A conversão deste pedido de orçamento precisa do vínculo de origem; os dados não foram gravados.',
                'company_sales_stale','native_sales_metadata_stale','native_sales_sequence_stale','native_sales_item_fields_stale','native_sales_editor_changed'=>'Os dados ou a numeração mudaram. Atualize o formulário e revise a versão atual antes de salvar.',
                default=>$error instanceof DomainException?'Confira os campos, os valores e as permissões desta empresa. Nenhuma alteração foi confirmada.':'Não foi possível confirmar o formulário desta empresa.'};
            $status=match($error->getMessage()){
                'native_company_csrf_invalid','company_context_membership_revoked','company_sales_denied','company_sales_recipient_denied','native_sales_editor_denied','native_custom_field_denied','company_client_access_denied'=>403,
                'company_context_mismatch','company_sales_stale','native_sales_metadata_stale','native_sales_sequence_stale','native_sales_item_fields_stale','native_sales_editor_changed'=>409,
                default=>$error instanceof DomainException?422:503};
            if($post&&$this->ci->input->is_ajax_request())$this->json(['success'=>false,'message'=>$message],$status);else show_error($message,$status);
        }
    }
    public function clientData(string $kind,$customer,$relation='customer'):void
    {
        if(!CompanyRequestScope::sealedFor($this->ci)){show_error('Seleção indisponível.',403);return;}
        if(!in_array(strtoupper($this->ci->input->method(true)),['GET','HEAD'],true)){$this->ci->output->set_header('Allow: GET, HEAD');show_error('Método não permitido.',405);return;}
        try{
            if($relation!=='customer')throw new DomainException('native_proposal_customer_required');
            $ctx=CompanyRequestScope::context();$id=$this->ci->input->get('native_sales_id',false)??'';$revision=$this->ci->input->get('native_sales_revision',false)??'';
            if(!is_string($id)||!is_string($revision))throw new DomainException('native_sales_editor_invalid');
            $data=(new NativeCompanySalesClientData($this->ci->db,db_prefix()))->read($ctx->issuerId(),$ctx->staffId(),$kind,$customer,$id,$revision);$this->json($data);
        }catch(DomainException $error){$this->json(['message'=>'Cliente indisponível neste contexto.'],403);}catch(Throwable $error){$this->json(['message'=>'Não foi possível confirmar os dados deste cliente.'],503);}
    }
}
