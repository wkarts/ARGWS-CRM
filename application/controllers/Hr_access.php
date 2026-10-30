<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Hr_access extends CI_Controller
{
    public function index($secret='')
    {
        if(!class_exists('CompanyHrAccessScope',false)||!CompanyHrAccessScope::active()){show_404();return;}$verb=$this->input->method(true);if(!in_array($verb,['GET','HEAD','POST'],true)){show_error('Método não permitido.',405);return;}
        require_once APPPATH.'services/CompanyHr.php';$this->output->set_header('Cache-Control: no-store')->set_header('Referrer-Policy: no-referrer')->set_header('X-Content-Type-Options: nosniff')->set_header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
        $csrf=$this->session->userdata('company_hr_participant_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_hr_participant_csrf',$csrf);}$s=new CompanyHr($this->db,db_prefix());$error=null;$result=null;$view=null;
        try{
            if(!is_string($secret))throw new DomainException('issuer_hr_access_denied');$query=$this->input->get(null,false)??[];if(!is_array($query)||array_diff(array_keys($query),['file']))throw new DomainException('issuer_hr_access_denied');
            if(isset($query['file'])){if($verb==='POST')throw new DomainException('issuer_hr_access_denied');$f=$s->accessFile($secret,$query['file']);$this->output->set_header("Content-Security-Policy: sandbox; default-src 'none'")->set_header('Content-Disposition: attachment; filename="material.'.($f['mime']==='image/png'?'png':'pdf').'"; filename*=UTF-8\'\''.rawurlencode($f['filename']))->set_content_type($f['mime'])->set_output($verb==='HEAD'?'':$f['bytes']);return;}
            if($verb==='POST'){$posted=$this->input->post('participant_csrf',false);if(!is_string($posted)||!hash_equals($csrf,$posted)){show_error('Recarregue este formulário antes de responder.',403);return;}if((int)($this->input->server('CONTENT_LENGTH')??0)>65536)throw new DomainException('issuer_hr_invalid');$key=$this->input->post('request_key',false);$answers=$this->input->post('answers',false);if(!is_string($key)||!is_array($answers))throw new DomainException('issuer_hr_invalid');if(isset($answers['acknowledge'])&&$answers===['acknowledge'=>'1'])$answers=['acknowledge'=>1];$result=$s->accessSubmit($secret,$this->input->post('revision',false),$answers,$key);}
            $view=$s->accessView($secret);
        }catch(DomainException $e){$denied=str_ends_with($e->getMessage(),'denied')||str_ends_with($e->getMessage(),'expired');$this->output->set_status_header($denied?403:422);$error=$denied?'Este acesso não está disponível. Solicite um novo link à equipe responsável.':'Confira as respostas e a revisão do registro. A tentativa não foi confirmada.';}
        catch(Throwable $e){log_message('error','HR participant access unconfirmed; secret and personal data omitted.');$this->output->set_status_header(503);$error='Não foi possível confirmar o acesso. Tente novamente após revisar o registro com a equipe responsável.';}
        $this->load->view('hr_access',['view'=>$view,'error'=>$error,'result'=>$result,'participant_csrf'=>$csrf,'request_key'=>bin2hex(random_bytes(32)),'action_url'=>$this->config->site_url('company_hr_access/index/'.$secret)]);
    }
}
