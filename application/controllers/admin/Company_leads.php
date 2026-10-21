<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Native sealed surface for private leads. No legacy leads model, transport or module hydration. */
class Company_leads extends AdminController
{
    private function context():?array
    {
        if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto de empresa indisponível.',503);return null;}require_once APPPATH.'services/CompanyLeads.php';$ctx=CompanyRequestScope::context();$i=$ctx->issuerId();$a=$ctx->staffId();$acl=new IssuerResourceAccess($this->db,db_prefix());if(!$acl->can($i,$a,'leads','view')){show_error('Sem permissão para leads desta empresa.',403);return null;}$this->output->set_header('Cache-Control: no-store')->set_header('X-Content-Type-Options: nosniff');return [$i,$a,$acl,new CompanyLeads($this->db,db_prefix())];
    }
    private function upload(string $field,int $limit):array
    {
        $f=$_FILES[$field]??null;if(!is_array($f)||($f['error']??null)!==UPLOAD_ERR_OK||!is_int($f['size']??null)||$f['size']>$limit||!is_string($f['name']??null)||!is_string($f['tmp_name']??null)||!is_uploaded_file($f['tmp_name']))throw new DomainException('company_lead_upload_invalid');$bytes=file_get_contents($f['tmp_name'],false,null,0,$limit+1);if(!is_string($bytes)||strlen($bytes)!==$f['size'])throw new DomainException('company_lead_upload_invalid');return [$f['name'],$bytes];
    }
    public function index()
    {
        $ctx=$this->context();if($ctx===null)return;[$issuer,$actor,$acl,$s]=$ctx;$method=$this->input->method(true);if(!in_array($method,['GET','HEAD','POST'],true)){show_error('Método não permitido.',405);return;}
        $csrf=$this->session->userdata('company_leads_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_leads_csrf',$csrf);}
        $error=null;$detail=null;$batch=null;$configuration=['stages'=>[],'sources'=>[]];$directory=['items'=>[],'next'=>null];$inbox=['items'=>[],'next'=>null];$staff=[];$search='';$state='active';$stageFilter=null;
        try{
            if($method==='POST'){$token=$this->input->post('company_leads_csrf',false);if(!is_string($token)||!hash_equals($csrf,$token)){show_error('Recarregue o formulário antes de salvar.',403);return;}$action=$this->input->post('action',false);$key=$this->input->post('request_key',false);if(!is_string($key))throw new DomainException('company_lead_invalid_input');$id=$this->input->post('lead_id',false);$revision=$this->input->post('revision',false);$redirect='';
                if(in_array($action,['create','update'],true)){$input=$this->input->post('lead',false);if(!is_array($input))throw new DomainException('company_lead_invalid_input');if(($input['reminder_local']??'')==='')$input['reminder_timezone']='';$id=$action==='create'?$s->create($issuer,$actor,$input,$key):$s->update($issuer,$actor,$id,$revision,$input,$key);$redirect='?lead='.$id;}
                elseif($action==='move'){$id=$s->move($issuer,$actor,$id,$revision,$this->input->post('stage_id',false),$key);$redirect='?lead='.$id;}
                elseif($action==='note'){$body=$this->input->post('body',false);if(!is_string($body))throw new DomainException('company_lead_invalid_input');$id=$s->note($issuer,$actor,$id,$revision,$body,$key);$redirect='?lead='.$id;}
                elseif(in_array($action,['archive','restore'],true)){$id=$s->archive($issuer,$actor,$id,$revision,$action==='archive',$key);$redirect='?lead='.$id;}
                elseif($action==='convert'){$result=$s->convert($issuer,$actor,$id,$revision,$key);$redirect='?lead='.$result['lead_id'];}
                elseif($action==='configure'){$kind=$this->input->post('kind',false);$input=$this->input->post('configuration',false);$configId=$this->input->post('config_id',false);if(!is_string($kind)||!is_array($input))throw new DomainException('company_lead_invalid_input');$configId=$configId===null||$configId===''?null:IssuerPolicy::id($configId);$s->configure($issuer,$actor,$kind,$configId,$configId===null?null:IssuerPolicy::id($revision),$input,$key);$redirect='#configuration';}
                elseif($action==='file_upload'){[$name,$bytes]=$this->upload('lead_file',2097152);$s->addFile($issuer,$actor,$id,$revision,$name,$bytes,$key);$redirect='?lead='.(int)$id.'#files';}
                elseif($action==='file_hide'){$file=$this->input->post('file_key',false);if(!is_string($file))throw new DomainException('company_lead_invalid_input');$s->hideFile($issuer,$actor,$id,$revision,$file,$key);$redirect='?lead='.(int)$id.'#files';}
                elseif($action==='import_preview'){[$name,$bytes]=$this->upload('lead_csv',262144);$result=$s->previewImport($issuer,$actor,$name,$bytes,$this->input->post('stage_id',false),$this->input->post('source_id',false),$key);$redirect='?batch='.$result['batch_id'].'#import-preview';}
                elseif($action==='import_commit'){$batchId=$this->input->post('batch_id',false);$hash=$this->input->post('preview_hash',false);if(!is_string($batchId)||!is_string($hash))throw new DomainException('company_lead_invalid_input');$s->commitImport($issuer,$actor,$batchId,$hash,$key);$redirect='?batch='.$batchId.'#import-preview';}
                elseif($action==='notice_ack'){$s->acknowledge($issuer,$actor,$this->input->post('notice_id',false),$key);$redirect='#inbox';}
                else throw new DomainException('company_lead_invalid_input');CompanyRequestScope::invalidateCaches($this);redirect(CompanyRequestScope::adminUrl($this,'company_leads'.$redirect),'location',303);return;
            }
        }catch(DomainException $e){$reason=$e->getMessage();if(str_ends_with($reason,'denied')){show_error('Lead, referência ou ação indisponível nesta empresa.',403);return;}$this->output->set_status_header(str_ends_with($reason,'stale')||str_ends_with($reason,'conflict')?409:422);$error=match($reason){'company_lead_stale'=>'O lead ou a configuração mudou. Reabra antes de salvar.','company_lead_request_conflict'=>'Este envio já foi utilizado com outros dados. Reabra o formulário.','company_lead_import_stale'=>'A prévia expirou, foi confirmada ou sua configuração mudou. Gere uma nova prévia.','company_lead_invalid_reminder'=>'Informe data, hora e fuso válidos. Horários inexistentes ou ambíguos na mudança de horário de verão exigem outra escolha.','company_contact_email_conflict'=>'Já existe um contato com este e-mail nesta empresa. Revise os cadastros antes de converter.','company_client_identity_conflict'=>'O documento informado já pertence a um cliente. Revise a identidade antes de converter.','company_lead_conversion_stage'=>'Mova o lead para um status do tipo Ganho antes de converter.','company_lead_reference_in_use'=>'Há leads ativos nesta configuração. Mova ou arquive esses leads antes de desativá-la.','company_lead_category_immutable'=>'O tipo de um status existente é preservado. Crie outro status para outro tipo.','company_operation_file_invalid','company_lead_upload_invalid'=>'Confira o arquivo: PDF até 2 MiB; PNG/JPEG até 512 KiB e 1600 pixels; CSV até 256 KiB.','company_operation_file_limit'=>'Limite por lead: 20 arquivos e 20 MiB no histórico, incluindo ocultos.','company_lead_import_invalid'=>'CSV inválido. Use UTF-8, até 100 linhas e o cabeçalho indicado.','company_lead_closed'=>'O lead está arquivado ou já foi convertido. Seu histórico está preservado.',default=>'Confira dados e referências. Nenhuma alteração desta tentativa foi confirmada.'};}
        catch(Throwable $e){log_message('error','Company leads operation unconfirmed; no private data or filenames logged.');$this->output->set_status_header(503);$error='Não foi possível confirmar a operação. Reabra antes de tentar novamente.';}
        try{$search=$this->input->get('search',false)??'';$state=$this->input->get('state',false)??'active';if(!is_string($search)||!is_string($state))throw new DomainException('company_lead_invalid_input');$filter=$this->input->get('stage',false);$stageFilter=$filter===null||$filter===''?null:IssuerPolicy::id($filter);$id=$this->input->get('lead',false);if($id!==null)$detail=$s->get($issuer,$actor,$id);$batchId=$this->input->get('batch',false);if($batchId!==null){if(!is_string($batchId))throw new DomainException('company_lead_invalid_input');$batch=$s->importBatch($issuer,$actor,$batchId);}$configuration=$s->configuration($issuer,$actor);$directory=$s->page($issuer,$actor,$this->input->get('after',false)??0,50,$search,$state,$stageFilter);$staff=$s->staffOptions($issuer,$actor);$inbox=$s->inbox($issuer,$actor,$this->input->get('notice_after',false)??0);}
        catch(DomainException $e){show_error('Lead, prévia ou referência indisponível nesta empresa.',str_ends_with($e->getMessage(),'denied')?403:422);return;}
        catch(Throwable $e){log_message('error','Company leads read unavailable; no private contents logged.');show_error('Não foi possível carregar os leads desta empresa.',503);return;}
        $caps=[];foreach(['view','create','edit','delete'] as $cap)$caps[$cap]=$acl->can($issuer,$actor,'leads',$cap);$caps['convert']=$caps['edit']&&$acl->can($issuer,$actor,'customers','view')&&$acl->can($issuer,$actor,'customers','create');
        $this->load->view('admin/companies/leads',['issuer'=>$issuer,'actor'=>$actor,'detail'=>$detail,'directory'=>$directory,'configuration'=>$configuration,'staff_options'=>$staff,'inbox'=>$inbox,'batch'=>$batch,'search'=>$search,'state'=>$state,'stage_filter'=>$stageFilter,'capabilities'=>$caps,'error'=>$error,'company_leads_csrf'=>$csrf,'request_key'=>bin2hex(random_bytes(32))]);
    }
    public function file($id='',$key='')
    {
        $ctx=$this->context();if($ctx===null)return;[$issuer,$actor,$acl,$s]=$ctx;$method=$this->input->method(true);if(!in_array($method,['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
        try{$blob=$s->file($issuer,$actor,$id,$key);$this->output->set_header("Content-Security-Policy: sandbox; default-src 'none'")->set_header('Content-Disposition: attachment; filename="anexo.'.($blob['mime']==='image/png'?'png':'pdf').'"; filename*=UTF-8\'\''.rawurlencode($blob['filename']))->set_header('Content-Length: '.strlen($blob['bytes']))->set_content_type($blob['mime'])->set_output($method==='HEAD'?'':$blob['bytes']);}
        catch(DomainException $e){show_error('Arquivo indisponível nesta empresa.',403);}
        catch(Throwable $e){log_message('error','Company leads file unconfirmed; no private data logged.');show_error('Não foi possível confirmar o arquivo.',503);}
    }
    public function export()
    {
        $ctx=$this->context();if($ctx===null)return;[$issuer,$actor,$acl,$s]=$ctx;$method=$this->input->method(true);if(!in_array($method,['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
        try{$search=$this->input->get('search',false)??'';$state=$this->input->get('state',false)??'active';if(!is_string($search)||!is_string($state))throw new DomainException('company_lead_invalid_input');$csv=$s->exportCsv($issuer,$actor,$search,$state);$this->output->set_header('Content-Disposition: attachment; filename="leads.csv"')->set_content_type('text/csv','UTF-8')->set_output($method==='HEAD'?'':$csv);}
        catch(DomainException $e){show_error($e->getMessage()==='company_lead_export_limit'?'A exportação aceita até 1.000 leads. Refine a busca.':'Exportação indisponível nesta empresa.',422);}
        catch(Throwable $e){log_message('error','Company leads export unconfirmed; no private data logged.');show_error('Não foi possível confirmar a exportação.',503);}
    }
}
