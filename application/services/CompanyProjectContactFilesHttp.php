<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyProjectContactFileWrite.php';require_once __DIR__.'/CompanyProjectContactFileReceipts.php';require_once __DIR__.'/CompanyProjectContactFileBundle.php';require_once __DIR__.'/CompanyOperationFilesHttp.php';
/** Finite actions after native + portal CSRF; receipt/file locators grant no authority. */
final class CompanyProjectContactFilesHttp
{
    public const ACTIONS=['project_contact_file_upload','project_contact_file_hide','project_contact_file_download','project_contact_files_bundle'];
    public static function handle($c,array $session,int $issuer,string $action):bool
    {
        if(!in_array($action,self::ACTIONS,true))return false;$project=$c->input->post('project_id',false);$secret=(string)$c->config->item('encryption_key');
        try{
            if($action==='project_contact_file_download'){$key=$c->input->post('file_receipt_key',false);if(!is_string($key))throw new DomainException('company_project_file_unavailable');$reader=new CompanyProjectContactFileReceipts($c->db,db_prefix(),$secret);$file=$reader->file($session,$issuer,$project,$key);$reader->confirmFile($session,$issuer,$project,$key,$file['content_hash']);self::headers($c);$c->output->set_header('Content-Disposition: attachment; filename="anexo.'.($file['mime']==='image/png'?'png':'pdf').'"; filename*=UTF-8\'\''.rawurlencode($file['filename']));$c->output->set_content_type($file['mime'])->set_output($file['content']);return true;}
            if($action==='project_contact_files_bundle'){$reader=new CompanyProjectContactFileBundle($c->db,db_prefix(),$secret);$file=$reader->bundle($session,$issuer,$project);$reader->confirmBundle($session,$issuer,$project,$file['manifest_hash']);self::headers($c);$c->output->set_header('Content-Disposition: attachment; filename="projeto-'.IssuerPolicy::id($project).'-arquivos.zip"');$c->output->set_content_type('application/zip')->set_output($file['content']);return true;}
            $key=$c->input->post('request_key',false);if(!is_string($key))throw new DomainException('company_project_file_invalid');$writer=new CompanyProjectContactFileWrite($c->db,db_prefix(),$secret);$revision=$c->input->post('project_revision',false);$collection=$c->input->post('file_collection_revision',false);
            if($action==='project_contact_file_upload'){[$name,$bytes]=CompanyOperationFilesHttp::upload();$writer->upload($session,$issuer,$project,$revision,$collection,$key,$name,$bytes);}
            else{$file=$c->input->post('file_key',false);if(!is_string($file))throw new DomainException('company_project_file_unavailable');$writer->hide($session,$issuer,$project,$revision,$collection,$file,$key);}
        }catch(DomainException $denied){$status=match($denied->getMessage()){'company_project_file_stale','company_project_file_request_conflict'=>409,'company_project_file_limit','company_operation_file_limit'=>429,'company_project_file_invalid','company_operation_file_invalid'=>422,default=>404};if($status===404){show_404();return true;}show_error($status===409?'O projeto ou os arquivos mudaram. Reabra seu resultado antes de enviar novamente.':($status===429?'Limite de 20 arquivos ou 20 MiB neste projeto, incluindo ocultos. Consulte a equipe.':'Envie um PDF até 2 MiB ou PNG/JPEG até 512 KiB pelo formulário de anexo.'),$status);return true;}
        redirect($c->config->site_url('company_portal/index/'.$issuer).'?operation_kind=projects&resource_id='.IssuerPolicy::id($project).'&project_file_receipt='.$key,'location',303);return true;
    }
    private static function headers($c):void{$c->output->set_header('Cache-Control: no-store');$c->output->set_header('X-Content-Type-Options: nosniff');$c->output->set_header("Content-Security-Policy: sandbox; default-src 'none'");}
}
