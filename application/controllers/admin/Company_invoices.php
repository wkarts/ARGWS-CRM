<?php

defined('BASEPATH') or exit('No direct script access allowed');
class Company_invoices extends AdminController
{
    public function index()
    {
        if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto indisponível.',503);return;}
        require_once APPPATH.'services/CompanyInvoices.php';require_once APPPATH.'services/CompanyClients.php';require_once APPPATH.'services/CompanyInvoiceDelivery.php';
        $context=CompanyRequestScope::context();$issuer=$context->issuerId();$actor=$context->staffId();$acl=new IssuerResourceAccess($this->db,db_prefix());
        if(!$acl->can($issuer,$actor,'invoices','view')){show_error('Sem permissão para faturas desta empresa.',403);return;}
        $this->output->set_header('Cache-Control: no-store');$s=new CompanyInvoices($this->db,db_prefix());
        $csrf=$this->session->userdata('company_invoices_csrf');if(!is_string($csrf)||strlen($csrf)!==64){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_invoices_csrf',$csrf);}
        $delivery=null;$deliveryBlocked=false;$canSend=$acl->can($issuer,$actor,'invoices','edit')&&$acl->can($issuer,$actor,'customers','view');
        $error=null;$document=null;$payments=[];$page=['items'=>[],'next'=>null];$clients=[];$currencies=[];$revision=null;$adjustments=[];
        try{
            if($this->input->method(true)==='POST'){
                $posted=$this->input->post('company_invoices_csrf',false);if(!is_string($posted)||!hash_equals($csrf,$posted)){show_error('Recarregue antes de salvar.',403);return;}
                $action=$this->input->post('action',false);$id=$this->input->post('invoice_id',false);
                if($action==='create'){$input=$this->input->post('invoice',false);$key=$this->input->post('request_key',false);if(!is_array($input)||!is_string($key))throw new DomainException('company_invoice_invalid_input');$id=$s->create($issuer,$actor,$input,$key);}
                elseif($action==='payment'){
                    $input=$this->input->post('payment',false);$key=$this->input->post('request_key',false);if(!is_array($input)||!is_string($key))throw new DomainException('company_payment_invalid_input');
                    (new \app\services\InvoicePaymentWriter($this->db))->recordCompanyManual($issuer,$actor,$id,$input,$key);
                }elseif($action==='notify'){
                    $contact=$this->input->post('contact_id',false);$rev=$this->input->post('revision',false);$key=$this->input->post('request_key',false);
                    if(!is_string($rev)||!is_string($key))throw new DomainException('company_invoice_delivery_invalid');
                    $this->load->library('encryption');
                    (new CompanyInvoiceDelivery($this->db,db_prefix()))->queue($issuer,$actor,$id,$contact,$rev,$key,$this->config->base_url(),fn($value)=>$this->encryption->decrypt($value));
                }elseif(in_array($action,['update','cancel'],true)){
                    $input=$action==='cancel'?null:$this->input->post('invoice',false);$rev=$this->input->post('revision',false);
                    if(($action==='update'&&!is_array($input))||!is_string($rev))throw new DomainException('company_invoice_invalid_input');$s->change($issuer,$actor,$id,$rev,$input);
                }else throw new DomainException('company_invoice_invalid_input');
                CompanyRequestScope::invalidateCaches($this);redirect(CompanyRequestScope::adminUrl($this,'company_invoices?invoice='.(int)$id),'location',303);return;
            }elseif(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
            $id=$this->input->get('invoice',false);
            if($id!==null){
                $dto=$s->document($issuer,$actor,$id);$document=$dto->data();$adjustments=$s->adjustments($issuer,$actor,$id);$payments=$s->payments($issuer,$actor,$id);$revision=$s->revision($issuer,$actor,$id);
                if($this->input->get('pdf',false)==='1'){
                    require_once APPPATH.'vendor/tecnickcom/tcpdf/tcpdf.php';require_once APPPATH.'libraries/pdf/Issuer_invoice_pdf.php';
                    $pdf=(new Issuer_invoice_pdf($dto))->prepare();
                    $s->confirmPdfDownload($issuer,$actor,$id);
                    $pdf->Output('fatura-'.(int)$id.'.pdf','D');return;
                }
                if($canSend){
                    try{$delivery=(new CompanyInvoiceDelivery($this->db,db_prefix()))->state($issuer,$actor,$id);}
                    catch(DomainException $unavailable){$deliveryBlocked=true;}
                }
            }
            $page=$s->page($issuer,$actor,$this->input->get('after',false)??0);$currencies=$s->currencies($issuer,$actor);
            if($acl->can($issuer,$actor,'invoices','create')&&$acl->can($issuer,$actor,'customers','view'))$clients=(new CompanyClients($this->db,db_prefix()))->page($issuer,$actor,0,100)['items'];
        }catch(DomainException $e){
            if(in_array($e->getMessage(),['company_invoice_access_denied','issuer_access_denied','company_payment_access_denied','issuer_document_scope_mismatch','company_invoice_delivery_denied'],true)){show_error('Fatura indisponível ou ação não autorizada nesta empresa.',403);return;}
            $this->output->set_status_header(str_contains($e->getMessage(),'stale')||str_contains($e->getMessage(),'conflict')?409:422);
            $error=match($e->getMessage()){
                'company_invoice_stale'=>'A fatura mudou. Reabra antes de salvar ou agendar a notificação.',
                'company_invoice_delivery_template_required'=>'Ative o template de envio de fatura desta empresa e mantenha o campo {invoice_link}.',
                'company_invoice_delivery_sender_required','company_mail_configuration_required','company_mail_secret_unavailable'=>'Complete a configuração de e-mail desta empresa antes de agendar.',
                'issuer_document_invalid_profile','issuer_document_profile_required'=>'Complete nome, documento e endereço da empresa nas Definições antes de emitir.',
                'company_invoice_provider_review_required','company_payment_provider_review_required'=>'Esta fatura possui origem bancária. Use a revisão e a conciliação da integração de origem.',
                'company_invoice_immutable'=>'A origem, moeda ou estado financeiro impede esta alteração. Nenhum cancelamento bancário foi solicitado.',
                'company_payment_exceeds_balance'=>'O recebimento excede o saldo atual da fatura.',
                'company_payment_reference_conflict'=>'Esta referência já possui recebimento nesta fatura. Confira o lançamento antes de tentar novamente.',
                default=>'Não foi possível concluir. Confira os dados, o estado e as permissões. Esta tentativa não aplicou alterações.',
            };
        }catch(Throwable $e){log_message('error','Company invoice operation unconfirmed; no document or payment values logged.');$this->output->set_status_header(503);$error='Não foi possível confirmar a operação. Reabra a fatura antes de tentar novamente.';}
        $this->load->view('admin/companies/invoices',['issuer'=>$issuer,'document'=>$document,'delivery'=>$delivery,'delivery_blocked'=>$deliveryBlocked,'can_send'=>$canSend,'adjustments'=>$adjustments,'payments'=>$payments,'directory'=>$page,'clients'=>$clients,'currencies'=>$currencies,'revision'=>$revision,'error'=>$error,'company_invoices_csrf'=>$csrf,'request_key'=>bin2hex(random_bytes(32)),'can_create'=>$acl->can($issuer,$actor,'invoices','create'),'can_edit'=>$acl->can($issuer,$actor,'invoices','edit'),'can_payment'=>$acl->can($issuer,$actor,'payments','create')]);
    }
}
