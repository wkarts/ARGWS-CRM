<?php

defined('BASEPATH') or exit('No direct script access allowed');
require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyService.php';
require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyReceivedReceipt.php';
require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyFeeExpense.php';

/** Named-company UI only. The legacy gateway and its six tabs remain intact. */
class Company_asaas extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if(!class_exists('CompanyRequestScope',false)||!CompanyRequestScope::sealedFor($this)||!CompanySettingsRuntime::active()){show_error('Acesso negado.',403);return;}
        $this->load->library('encryption');
    }
    public function index()
    {
        $this->output->set_header('Cache-Control: no-store');
        $issuer=CompanySettingsRuntime::issuerId();$actor=CompanySettingsRuntime::staffId();$service=new \Asaas\CompanyService($this->db,db_prefix());$error=null;$saved=null;$oneTime=null;
        try{$service->authorize($issuer,$actor,'view');}catch(Throwable $e){show_error('Integração indisponível ou acesso negado.',403);return;}
        try{
            if($this->input->method(true)==='POST'){
                $post=$this->input->post(null,false);$action=$post['action']??'';
                if(!is_string($action))throw new DomainException('asaas_company_invalid_input');
                $decrypt=fn($value)=>$this->encryption->decrypt($value);
                switch($action){
                    case 'save_account':$service->saveAccount($issuer,$actor,(string)($post['environment']??''),(string)($post['account_id']??''),(string)($post['api_key']??''),(string)($post['revision']??''),fn($value)=>$this->encryption->encrypt($value));$saved='Credencial protegida. Confira a conta por API antes de emitir.';break;
                    case 'verify':$service->verifyAccount($issuer,$actor,$post['account_row']??null,(string)($post['revision']??''),$decrypt);$saved='A credencial confirmou a carteira configurada para este ambiente.';break;
                    case 'rotate':$oneTime=$service->rotateWebhook($issuer,$actor,$post['account_row']??null,(string)($post['revision']??''));$saved='Token local gerado. Cadastre o webhook manualmente no Asaas; nenhuma chamada de cadastro remoto foi executada.';break;
                    case 'disable':$service->disableAccount($issuer,$actor,$post['account_row']??null,(string)($post['revision']??''));$saved='Conta desabilitada. O token anterior foi revogado.';break;
                    case 'enable':$service->enableAccount($issuer,$actor,$post['account_row']??null,(string)($post['revision']??''));$saved='Conta habilitada para nova verificação; gere outro token após conferir a carteira.';break;
                    case 'automation':$service->configureAutomation($issuer,$actor,$post['account_row']??null,(string)($post['revision']??''),($post['enabled']??null)==='1');$saved='Política de conciliação automática atualizada para sua identidade e esta conta.';break;
                    case 'fee':if(($post['confirm']??null)!=='1')throw new DomainException('asaas_company_confirmation_required');(new \Asaas\CompanyFeeExpense($this->db,db_prefix()))->post($issuer,$actor,$post['origin_id']??null,$post['proof_id']??null,$post['category_id']??null);$saved='Tarifa registrada como despesa da empresa com evidência canônica e vínculo imutável.';break;
                    case 'issue':$service->issue($issuer,$actor,$post['invoice_id']??null,$post['account_row']??null,(string)($post['customer_id']??''),(string)($post['billing_type']??''),(string)($post['invoice_revision']??''),(string)($post['request_key']??''),$decrypt);$saved='Emissão observada. A criação da cobrança não comprova pagamento.';break;
                    case 'query':$service->query($issuer,$actor,$post['origin_id']??null,$decrypt,null,(string)($post['recovery_id']??''));$saved='Consulta canônica persistida; nenhum pagamento foi lançado por esta ação.';break;
                    case 'settle':
                        if(($post['confirm']??null)!=='1')throw new DomainException('asaas_company_confirmation_required');
                        (new \app\services\InvoicePaymentWriter($this->db))->recordCompanyProviderReceipt($issuer,$actor,new \Asaas\CompanyReceivedReceipt(IssuerPolicy::id($post['origin_id']??null),IssuerPolicy::id($post['proof_id']??null)));$saved='Recebimento nominal aplicado pelo escritor financeiro. Tarifas observadas não são despesas contabilizadas.';break;
                    default:throw new DomainException('asaas_company_invalid_input');
                }
            }
            $data=$service->overview($issuer,$actor);
        }catch(DomainException $e){$this->output->set_status_header(str_contains($e->getMessage(),'stale')?409:422);$error='Não foi possível concluir. Confira a empresa, permissões, ambiente, revisão e estado da operação. Recarregue antes de repetir.';$data=$service->overview($issuer,$actor);}
        catch(Throwable $e){$this->output->set_status_header(503);$error='Operação não confirmada. Uma emissão incerta não deve ser repetida: consulte a cobrança remota para retomá-la.';try{$data=$service->overview($issuer,$actor);}catch(Throwable $ignored){show_error('Integração indisponível. Confira a ativação e as migrations do módulo.',503);return;}}
        $invoice=null;$invoiceId=$this->input->get('invoice');if($invoiceId){try{$id=IssuerPolicy::id($invoiceId);$invoices=new CompanyInvoices($this->db,db_prefix());$invoice=['id'=>$id,'data'=>$invoices->document($issuer,$actor,$id)->data(),'revision'=>$invoices->revision($issuer,$actor,$id),'request_key'=>hash('sha256','company-asaas-issue:'.$issuer.':'.$id)];}catch(Throwable $ignored){show_error('Fatura indisponível nesta empresa.',403);return;}}
        $base=(string)config_item('base_url');$parts=parse_url($base);$trusted=is_array($parts)&&($parts['scheme']??'')==='https'&&!isset($parts['user'])&&!isset($parts['pass'])&&!isset($parts['query'])&&!isset($parts['fragment']);
        $this->load->view('admin/companies/asaas',$data+['error'=>$error,'saved'=>$saved,'one_time'=>$oneTime,'invoice'=>$invoice,'can_configure'=>CompanySettingsRuntime::canResource('settings','edit'),'can_fee'=>CompanySettingsRuntime::canResource('expenses','view')&&CompanySettingsRuntime::canResource('expenses','create')&&CompanySettingsRuntime::canResource('payments','create'),'can_settle'=>CompanySettingsRuntime::canResource('invoices','view')&&CompanySettingsRuntime::canResource('payments','create'),'can_issue'=>CompanySettingsRuntime::canResource('invoices','edit')&&CompanySettingsRuntime::canResource('invoices','view')&&CompanySettingsRuntime::canResource('customers','view'),'context_token'=>CompanyRequestScope::token(),'action_url'=>CompanyRequestScope::adminUrl($this,'company_asaas'),'callback_base'=>$trusted?rtrim($base,'/').'/company_asaas_callback/integration/':null]);
    }
}
