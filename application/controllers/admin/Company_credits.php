<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Credit GUI is sealed independently; no legacy financial model or mail hooks are loaded. */
class Company_credits extends AdminController
{
    public function index()
    {
        if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto de empresa indisponível.',503);return;}
        require_once APPPATH.'services/CompanyCredits.php';require_once APPPATH.'services/CompanyClients.php';require_once APPPATH.'services/InvoicePaymentWriter.php';
        $context=CompanyRequestScope::context();$issuer=$context->issuerId();$actor=$context->staffId();$acl=new IssuerResourceAccess($this->db,db_prefix());$reader=new CompanyCredits($this->db,db_prefix());$writer=new \app\services\InvoicePaymentWriter($this->db);
        $this->output->set_header('Cache-Control: no-store');if(!$acl->can($issuer,$actor,'credit_notes','view')){show_error('Sem permissão para consultar notas de crédito desta empresa.',403);return;}
        $csrf=$this->session->userdata('company_credits_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_credits_csrf',$csrf);}
        $error=null;$document=null;$page=['items'=>[],'next'=>null];$clients=[];$currencies=[];$invoices=[];
        try{
            if($this->input->method(true)==='POST'){
                $posted=$this->input->post('company_credits_csrf',false);if(!is_string($posted)||!hash_equals($csrf,$posted)){show_error('Recarregue o formulário antes de salvar.',403);return;}
                $action=$this->input->post('action',false);$key=$this->input->post('request_key',false);if(!is_string($key))throw new DomainException('company_credit_invalid_input');
                if($action==='create'){$input=$this->input->post('credit',false);if(!is_array($input))throw new DomainException('company_credit_invalid_input');$id=$writer->issueCompanyCredit($issuer,$actor,$input,$key);}
                else{$id=IssuerPolicy::id($this->input->post('credit_id',false));if($action==='void')$writer->voidCompanyCredit($issuer,$actor,$id,$key);elseif(in_array($action,['apply','unapply','refund','refund_reverse'],true)){$input=$this->input->post('movement',false);if(!is_array($input)||array_key_exists('credit_id',$input))throw new DomainException('company_credit_invalid_input');$writer->moveCompanyCredit($issuer,$actor,$action,['credit_id'=>$id]+$input,$key);}else throw new DomainException('company_credit_invalid_input');}
                CompanyRequestScope::invalidateCaches($this);redirect(CompanyRequestScope::adminUrl($this,'company_credits?credit='.(int)$id),'location',303);return;
            }elseif(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
            $id=$this->input->get('credit',false);if($id!==null){if($this->input->get('pdf',false)==='1'){$pdf=$reader->pdf($issuer,$actor,$id);$this->output->set_header('X-Content-Type-Options: nosniff');$this->output->set_header('Content-Disposition: attachment; filename="nota-de-credito-'.IssuerPolicy::id($id).'.pdf"');$this->output->set_content_type('application/pdf')->set_output($pdf);return;}$document=$reader->get($issuer,$actor,$id);$invoices=$reader->invoiceOptions($issuer,$actor,$id);}
            $page=$reader->page($issuer,$actor,$this->input->get('after',false)??0);$currencies=$reader->currencies($issuer,$actor);
            if($acl->can($issuer,$actor,'customers','view'))$clients=(new CompanyClients($this->db,db_prefix()))->page($issuer,$actor,0,100)['items'];
        }catch(DomainException $e){$reason=$e->getMessage();if(in_array($reason,['company_credit_access_denied','issuer_access_denied'],true)){show_error('Nota de crédito indisponível ou ação não autorizada nesta empresa.',403);return;}$this->output->set_status_header(in_array($reason,['company_credit_request_conflict','company_credit_reference_conflict'],true)?409:422);$error=match($reason){'company_credit_request_conflict'=>'Este envio já foi utilizado com outros dados. Reabra o formulário.','company_credit_reference_conflict'=>'Esta referência já está registrada para a mesma operação. Revise o histórico antes de repetir.','company_credit_exceeds_balance'=>'O valor excede o saldo da nota ou da fatura. Reabra e confira os valores atuais.','company_credit_balance_review'=>'O histórico e os valores exigem revisão. Nenhuma operação foi confirmada nesta tentativa.','company_credit_provider_review_required'=>'Esta fatura tem origem bancária. A operação exige o fluxo da integração de origem.','company_credit_target_mismatch'=>'A fatura deve pertencer à mesma empresa, cliente e moeda da nota.','company_credit_has_history'=>'Uma nota com movimentos não pode ser cancelada; revise ou estorne os movimentos elegíveis.','company_credit_invalid_reversal'=>'O movimento original não é elegível ou o valor excede seu saldo.','company_credit_invalid_state'=>'Esta nota ou fatura não permite a operação solicitada.',default=>'Confira cliente, data, moeda, itens, valores e evidências. Nenhuma alteração desta tentativa foi aplicada.'};}
        catch(Throwable $e){log_message('error','Company credit operation failed; no financial data logged.');$this->output->set_status_header(503);$error='Não foi possível confirmar a operação. Recarregue antes de tentar novamente.';}
        $capabilities=[];foreach(['create','edit','delete'] as $cap)$capabilities[$cap]=$acl->can($issuer,$actor,'credit_notes',$cap);$capabilities['payment_create']=$acl->can($issuer,$actor,'payments','create');$capabilities['payment_delete']=$acl->can($issuer,$actor,'payments','delete');$capabilities['invoice_view']=$acl->can($issuer,$actor,'invoices','view');
        $this->load->view('admin/companies/credits',['issuer'=>$issuer,'directory'=>$page,'document'=>$document,'invoices'=>$invoices,'clients'=>$clients,'currencies'=>$currencies,'capabilities'=>$capabilities,'error'=>$error,'company_credits_csrf'=>$csrf]);
    }
}
