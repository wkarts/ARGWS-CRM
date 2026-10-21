<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** All requests use the isolated company shell; there is no download/export action. */
class Company_certificates extends AdminController
{
    public function index($requestedIssuer = null)
    {
        $this->output->set_header('Cache-Control: no-store')->set_header('Pragma: no-cache')->set_header('Referrer-Policy: no-referrer');
        if (!CompanyRequestScope::sealedFor($this)) { show_error('Contexto de empresa indisponível.',503); return; }
        require_once APPPATH.'services/issuers/IssuerCertificateManager.php';
        $context=CompanyRequestScope::context(); $issuer=$context->issuerId(); $actor=$context->staffId();
        $url=CompanyRequestScope::adminUrl($this,'company_certificates/index/'.$issuer);
        $method=$this->input->method(true);
        if (!in_array($method,['GET','HEAD','POST'],true)) { show_error('Método não permitido.',405); return; }
        try {
            if ($issuer===null || ($requestedIssuer!==null && IssuerPolicy::id($requestedIssuer)!==$issuer)) {
                show_error('Selecione esta empresa no seletor antes de gerenciar seu certificado.',409); return;
            }
            $acl=new IssuerResourceAccess($this->db,db_prefix());
            if (!$acl->can($issuer,$actor,'settings','edit')) { show_error('Sem permissão para administrar o certificado desta empresa.',403); return; }
        } catch (DomainException $error) { show_error('Empresa inválida.',403); return; }
        $csrf=$this->session->userdata('issuer_certificate_csrf');
        if (!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)) { $csrf=bin2hex(random_bytes(32)); $this->session->set_userdata('issuer_certificate_csrf',$csrf); }
        if ($method==='POST') {
            $submitted=$this->input->post('issuer_certificate_csrf',false);
            if (!is_string($submitted)||!hash_equals($csrf,$submitted)) { show_error('Recarregue o formulário antes de continuar.',403); return; }
            try { $context->assertToken(CompanyRequestScope::submittedToken()); }
            catch (DomainException $error) { show_error('A empresa desta aba mudou. Recarregue a página.',409); return; }
        }
        $error=null;
        try {
            // Use the existing application key; never create, rotate or save a key here.
            $key=$this->config->item('encryption_key');
            if (!is_string($key)||strlen($key)!==32) { throw new DomainException('issuer_certificate_crypto_unavailable'); }
            $this->load->library('encryption');
            $manager=new IssuerCertificateManager($this->db,db_prefix(),$this->encryption);
            if ($method==='POST') {
                $revision=$this->input->post('revision',false);
                if (!is_string($revision)||!preg_match('/^(0|[1-9][0-9]{0,9})$/D',$revision)) { throw new DomainException('issuer_certificate_stale'); }
                $action=$this->input->post('action',false);
                if ($action==='replace') {
                    $manager->authorize($issuer,$actor);
                    $password=$this->input->post('certificate_password',false);
                    if (!is_string($password)) { throw new DomainException('issuer_certificate_invalid_upload'); }
                    $pfx=$this->readUpload($_FILES['certificate_file']??null);
                    $manager->replace($issuer,$actor,(int)$revision,$pfx,$password);
                    unset($pfx,$password);
                    $notice='Certificado armazenado de forma cifrada para esta empresa.';
                } elseif ($action==='remove') {
                    $manager->remove($issuer,$actor,(int)$revision);
                    $notice='Material do certificado removido do cadastro ativo. Backups seguem a retenção operacional.';
                } else { throw new DomainException('issuer_certificate_invalid_upload'); }
                $this->session->set_flashdata('issuer_certificate_notice',$notice);
                redirect($url,'location',303); return;
            }
        } catch (DomainException $exception) {
            $reason=$exception->getMessage();
            $status=$reason==='issuer_certificate_denied'?403:($reason==='issuer_certificate_stale'?409:422);
            $this->output->set_status_header($status);
            $messages=[
                'issuer_certificate_denied'=>'Seu acesso mudou. Reabra a empresa.',
                'issuer_certificate_stale'=>'Este cadastro mudou em outra aba. Recarregue antes de enviar novamente.',
                'issuer_certificate_invalid_upload'=>'Envie um arquivo .pfx ou .p12 de até 2 MiB e confira a senha.',
                'issuer_certificate_parse_failed'=>'Não foi possível abrir o PFX/P12. Confira a senha e o formato. Algoritmos antigos podem não ser suportados; solicite uma exportação compatível ao responsável.',
                'issuer_certificate_key_required'=>'O arquivo precisa conter o certificado e sua chave privada correspondente.',
                'issuer_certificate_expired'=>'O certificado enviado está expirado. O cadastro anterior foi preservado.',
                'issuer_certificate_not_yet_valid'=>'O certificado enviado ainda não está válido. O cadastro anterior foi preservado.',
                'issuer_certificate_document_mismatch'=>'O CNPJ do certificado não corresponde ao cadastro desta empresa. A substituição foi bloqueada.',
                'issuer_certificate_document_invalid'=>'O CNPJ do certificado é inválido ou ambíguo. A substituição foi bloqueada.',
                'issuer_certificate_crypto_unavailable'=>'Cifra indisponível. O administrador deve conferir a chave já existente do CRM e a extensão OpenSSL.',
                'issuer_certificate_envelope_invalid'=>'Não foi possível verificar a integridade do certificado armazenado. Substitua ou remova o cadastro após revisão administrativa.',
            ];
            $error=$messages[$reason]??'Operação não confirmada. Confira o cadastro e suas permissões.';
        } catch (Throwable $exception) {
            $this->output->set_status_header(503); $error='Operação não confirmada. Confira a atualização do núcleo antes de repetir.';
            log_message('error','Company certificate operation unavailable; submitted values and crypto errors omitted.');
        }
        try {
            if (!isset($manager)) { show_error($error??'Certificado indisponível.',503); return; }
            $page=$manager->page($issuer,$actor);
            $this->load->view('admin/companies/certificates',['page'=>$page,'csrf'=>$csrf,'error'=>$error,'notice'=>$this->session->flashdata('issuer_certificate_notice')]);
        } catch (DomainException $exception) { show_error('Sem acesso ao certificado ou integridade não confirmada. Consulte o administrador.',403); }
        catch (Throwable $exception) { show_error('Cadastro de certificados indisponível. Confira a atualização do núcleo.',503); }
    }
    private function readUpload($upload): string
    {
        if (!is_array($upload)||!is_int($upload['error']??null)||$upload['error']!==UPLOAD_ERR_OK
            ||!is_string($upload['name']??null)||!preg_match('/\.(pfx|p12)$/iD',$upload['name'])
            ||!is_int($upload['size']??null)||$upload['size']<1||$upload['size']>IssuerCertificateParser::MAX_BYTES
            ||!is_string($upload['tmp_name']??null)||!is_uploaded_file($upload['tmp_name'])) { throw new DomainException('issuer_certificate_invalid_upload'); }
        $bytes=@file_get_contents($upload['tmp_name'],false,null,0,IssuerCertificateParser::MAX_BYTES+1);
        if (!is_string($bytes)||$bytes===''||strlen($bytes)>IssuerCertificateParser::MAX_BYTES) { throw new DomainException('issuer_certificate_invalid_upload'); }
        return $bytes;
    }
}
