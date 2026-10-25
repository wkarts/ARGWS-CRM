<?php

defined('BASEPATH') or exit('No direct script access allowed');
require_once FCPATH.'modules/gerencianet_gateway/services/CompanyAccounts.php';
require_once FCPATH.'modules/gerencianet_gateway/services/CompanyCheckout.php';
require_once FCPATH.'modules/gerencianet_gateway/services/CompanyTransport.php';

/** Named-company administration only; never routes through the legacy gateway. */
class Company_efi extends AdminController
{
    public function index()
    {
        if(!CompanyRequestScope::sealedFor($this)||!CompanySettingsRuntime::active()){show_error('Contexto de empresa indisponível.',403);return;}
        $issuer=CompanySettingsRuntime::issuerId();$actor=CompanySettingsRuntime::staffId();
        $this->output->set_header('Cache-Control: no-store, private')->set_header('Pragma: no-cache')->set_header('Referrer-Policy: no-referrer');
        $this->load->library('encryption');$accounts=new \Efi\CompanyAccounts($this->db,db_prefix());
        $checkout=new \Efi\CompanyCheckout($this->db,db_prefix(),$accounts,fn(array $row)=>new \Efi\CompanyTransport($row,$this->encryption));
        $csrf=$this->session->userdata('company_efi_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_efi_csrf',$csrf);}
        $error=null;$saved=null;$instrument=null;$method=$this->input->method(true);
        try{
            if(!in_array($method,['GET','HEAD','POST'],true)){show_error('Método não permitido.',405);return;}
            if($method==='POST'){
                $post=$this->input->post(null,false);if(!is_array($post)||!is_string($post['company_efi_csrf']??null)||!hash_equals($csrf,$post['company_efi_csrf'])){show_error('Reabra o formulário desta empresa.',403);return;}
                $text=static function(string $key)use($post):string{if(!is_string($post[$key]??null))throw new DomainException('efi_company_input_invalid');return $post[$key];};
                $action=$text('action');
                if(($post['confirm']??null)!=='1')throw new DomainException('efi_company_confirmation_required');
                if($action==='save'){
                    $accounts->authorize($issuer,$actor,'configure');$certificate=null;
                    if(isset($_FILES['certificate'])&&($_FILES['certificate']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
                        $upload=$_FILES['certificate'];if(($upload['error']??null)!==UPLOAD_ERR_OK||!is_string($upload['tmp_name']??null)||!is_uploaded_file($upload['tmp_name'])||($upload['size']??0)>EfiCertificateStore::MAX_BYTES)throw new DomainException('efi_certificate_invalid');
                        $bytes=file_get_contents($upload['tmp_name']);if(!is_string($bytes))throw new DomainException('efi_certificate_invalid');$certificate=EfiCertificateStore::seal($bytes,$text('certificate_password'),$this->encryption);$bytes='';
                    }
                    $accounts->save($issuer,$actor,$text('environment'),$text('revision'),$text('client_id'),$text('client_secret'),$text('pix_key'),$certificate,$this->encryption);$saved='Conta própria protegida. A revisão mudou; esta ação não testa o provedor nem emite cobrança.';
                }elseif($action==='revoke'){$accounts->revoke($issuer,$actor,IssuerPolicy::id($text('account_id')),$text('revision'));$saved='Conta revogada localmente. Histórico preservado; provas anteriores não autorizam novas baixas.';}
                elseif($action==='issue'){
                    $kind=$text('kind');if(!in_array($kind,['pix','boleto'],true))throw new DomainException('efi_company_card_tokenization_unavailable');
                    $input=[];if($kind==='boleto')foreach(['email','phone'] as $field)$input[$field]=$text($field);
                    $id=$checkout->issue($issuer,$actor,IssuerPolicy::id($text('invoice_id')),IssuerPolicy::id($text('account_id')),$text('revision'),$kind,$input);$saved='Origem '.$id.' registrada. Emissão não comprova pagamento; consulte a origem antes de conciliar.';
                }elseif($action==='instruments'){$instrument=$checkout->instruments($issuer,$actor,IssuerPolicy::id($text('origin_id')));$saved='Instrumento consultado na conta e ambiente desta origem.';}elseif($action==='query'){$id=$checkout->query($issuer,$actor,IssuerPolicy::id($text('origin_id')));$saved='Consulta registrada como prova '.$id.'. Nenhum pagamento foi lançado por esta ação.';}
                elseif($action==='settle'){$id=$checkout->settle($issuer,$actor,IssuerPolicy::id($text('origin_id')),IssuerPolicy::id($text('proof_id')));$saved='Recebimento '.$id.' confirmado pelo escritor financeiro da empresa.';}
                else throw new DomainException('efi_company_input_invalid');
            }
        }catch(DomainException $e){$this->output->set_status_header(422);$error='Ação não confirmada. Confira permissões, empresa, ambiente, revisão e estado da origem. Reabra a página; não repita emissão incerta.';}
        catch(Throwable $e){$this->output->set_status_header(503);$error='Operação indisponível ou resultado incerto. Não reenvie a cobrança; preserve a origem para revisão.';}
        try{
            $canConfigure=CompanySettingsRuntime::canResource('settings','view')&&CompanySettingsRuntime::canResource('settings','edit');
            $canViewConfig=CompanySettingsRuntime::canResource('settings','view');$canObserve=CompanySettingsRuntime::canResource('invoices','view');
            if(!$canViewConfig&&!$canObserve){show_error('Acesso negado nesta empresa.',403);return;}
            $profiles=$canViewConfig?$accounts->overview($issuer,$actor):[];$activity=$canObserve?$checkout->overview($issuer,$actor):[];
            $this->load->view('admin/companies/efi',['instrument'=>$instrument,'choices'=>$canObserve?$accounts->choices($issuer,$actor):[],'profiles'=>$profiles,'activity'=>$activity,'csrf'=>$csrf,'error'=>$error,'saved'=>$saved,'can_configure'=>$canConfigure,'can_issue'=>$canObserve&&CompanySettingsRuntime::canResource('invoices','edit')&&CompanySettingsRuntime::canResource('customers','view'),'can_settle'=>$canObserve&&CompanySettingsRuntime::canResource('payments','create')]);
        }catch(DomainException $e){show_error('Efí indisponível ou acesso revogado nesta empresa.',403);}catch(Throwable $e){show_error('Confira as migrations e a ativação local do módulo Efí.',503);}
    }
}
