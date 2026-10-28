<?php
declare(strict_types=1);
require_once __DIR__.'/CompanySales.php';
require_once __DIR__.'/CompanySalesExtrasHttp.php';
require_once __DIR__.'/CompanyClients.php';
require_once __DIR__.'/CompanyContactSalesAccess.php';

/** Complete issuer adapter for the original Estimates and Proposals admin routes. */
final class NativeCompanySalesAdminHttp
{
    public function __construct(private $ci) {}

    private static function controller(string $kind):string
    {
        return match($kind){'estimates'=>'estimates','proposals'=>'proposals',default=>throw new DomainException('company_sales_denied')};
    }

    public function index(string $kind,$selected=null,bool $forcePdf=false):void
    {
        if(!CompanyRequestScope::sealedFor($this->ci)){show_error('Documento indisponível nesta empresa.',403);return;}
        $controller=self::controller($kind);$context=CompanyRequestScope::context();$issuer=$context->issuerId();$actor=$context->staffId();
        $acl=new IssuerResourceAccess($this->ci->db,db_prefix());if(!$acl->can($issuer,$actor,$kind,'view')){show_error('Documento indisponível nesta empresa.',403);return;}
        $this->ci->output->set_header('Cache-Control: no-store');$sales=new CompanySales($this->ci->db,db_prefix());
        $portalPermissions=[];$search='';$mutationReview=null;$mutationMode=null;$mutationsReady=CompanySalesMutationsSchema::present($this->ci->db,db_prefix());$error=null;$detail=null;
        $directory=['items'=>[],'next'=>null];$clients=[];$currencies=[];$csrf=$this->ci->session->userdata('company_sales_csrf');
        if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->ci->session->set_userdata('company_sales_csrf',$csrf);}
        try{
            $method=$this->ci->input->method(true);
            if($method==='POST'){
                CompanyRequestScope::assertNativeMutation($this->ci);$token=$this->ci->input->post('company_sales_csrf',false);
                if(!is_string($token)||!hash_equals($csrf,$token))throw new DomainException('company_sales_denied');
                $action=$this->ci->input->post('action',false);$id=$this->ci->input->post('document_id',false);
                if(CompanySalesExtrasHttp::post($this->ci,$issuer,$actor,$id,$action)){}
                elseif(in_array($action,['edit','copy','review_copy'],true)){
                    $source=$sales->document($issuer,$actor,$id);if($source['kind']!==$kind)throw new DomainException('company_sales_denied');
                    $input=$this->ci->input->post('invoice',false);$review=$this->ci->input->post('review',false);$key=$this->ci->input->post('request_key',false);
                    if(!is_array($input)||!is_array($review)||!is_string($key))throw new DomainException('company_sales_invalid');
                    if($action==='review_copy'){$mutationReview=$sales->review($issuer,$actor,$id,'copy',$input);if(($review['source_revision']??null)!==$mutationReview['source_revision'])throw new DomainException('company_sales_stale');$mutationMode='copy_confirm';}
                    else $id=$action==='edit'?$sales->edit($issuer,$actor,$id,$input,$review,$key):$sales->copy($issuer,$actor,$id,$input,$review,$key);
                }elseif($action==='create'){
                    $input=$this->ci->input->post('invoice',false);$key=$this->ci->input->post('request_key',false);if(!is_array($input)||!is_string($key))throw new DomainException('company_sales_invalid');
                    $id=$sales->create($issuer,$actor,$kind,$input,$key);
                }elseif($action==='portal_permission'){
                    $document=$sales->document($issuer,$actor,$id);if($document['kind']!==$kind)throw new DomainException('company_sales_denied');
                    $contact=$this->ci->input->post('contact_id',false);$revision=$this->ci->input->post('revision',false);$allowed=$this->ci->input->post('allowed',false)??'0';
                    if(!is_string($revision)||!is_string($allowed))throw new DomainException('company_sales_invalid');
                    (new CompanyContactSalesAccess($this->ci->db,db_prefix(),$kind))->set($issuer,$actor,$document['payload']['client_id'],$contact,$revision,$allowed);
                }elseif($action==='convert'){
                    $revision=$this->ci->input->post('revision',false);if($mutationsReady&&!is_string($revision))throw new DomainException('company_sales_invalid');$sales->convert($issuer,$actor,$id,$revision);
                }elseif(in_array($action,['accepted','declined','cancelled'],true)){
                    $revision=$this->ci->input->post('revision',false);if($mutationsReady&&!is_string($revision))throw new DomainException('company_sales_invalid');$sales->decide($issuer,$actor,$id,$action,$revision);
                }else throw new DomainException('company_sales_invalid');
                if($action!=='review_copy'){CompanyRequestScope::invalidateCaches($this->ci);redirect(CompanyRequestScope::adminUrl($this->ci,$controller).'?document='.(int)$id,'location',303);return;}
            }elseif(!in_array($method,['GET','HEAD'],true))throw new DomainException('company_sales_method_denied');
            $id=$mutationReview?$mutationReview['id']:($selected!==null&&$selected!==''?$selected:$this->ci->input->get('document',false));
            if($id!==null&&$id!==false&&$id!==''){
                $detail=$sales->document($issuer,$actor,$id);if($detail['kind']!==$kind)throw new DomainException('company_sales_denied');
                if(CompanySalesExtrasHttp::download($this->ci,$issuer,$actor,$id))return;
                if($forcePdf||$this->ci->input->get('pdf',false)==='1'){
                    require_once APPPATH.'vendor/tecnickcom/tcpdf/tcpdf.php';require_once __DIR__.'/CompanySalesPdf.php';$pdf=CompanySalesPdf::prepare($detail);$sales->confirmPdf($issuer,$actor,$id,$detail['revision_token']);
                    $bytes=$pdf->Output('','S');if(!is_string($bytes)||!str_starts_with($bytes,'%PDF-'))throw new RuntimeException('issuer_sales_pdf_unconfirmed');
                    $this->ci->output->set_header('Cache-Control: private, no-store')->set_header('X-Content-Type-Options: nosniff')->set_header('Content-Disposition: attachment; filename="documento.pdf"')->set_content_type('application/pdf')->set_output($method==='HEAD'?'':$bytes);return;
                }
                if($acl->can($issuer,$actor,'customers','view')){try{$portalPermissions=(new CompanyContactSalesAccess($this->ci->db,db_prefix(),$kind))->page($issuer,$actor,$detail['payload']['client_id']);}catch(DomainException $unavailable){$portalPermissions=[];}}
            }
            $requested=$this->ci->input->get('review',false);if($requested!==null){if(!$detail||!in_array($requested,['edit','copy'],true))throw new DomainException('company_sales_invalid');$mutationReview=$sales->review($issuer,$actor,$id,$requested);$mutationMode=$requested;}
            $search=$this->ci->input->get('search',false)??'';if(!is_string($search))throw new DomainException('company_sales_invalid');
            $after=$this->ci->input->get('after',false)??0;$directory=$sales->page($issuer,$actor,$kind,$after,$search);
            if(($acl->can($issuer,$actor,$kind,'create')||$mutationMode==='edit'&&$acl->can($issuer,$actor,$kind,'edit'))&&$acl->can($issuer,$actor,'customers','view')){
                $clients=(new CompanyClients($this->ci->db,db_prefix()))->page($issuer,$actor,0,100)['items'];$currencies=$sales->currencies($issuer,$actor,$kind);
            }
            if($method==='HEAD'){$this->ci->output->set_output('');return;}
        }catch(DomainException $exception){
            $reason=$exception->getMessage();if($reason==='company_sales_method_denied'){show_error('Método não permitido.',405);return;}if(str_contains($reason,'denied')){show_error('Documento indisponível nesta empresa.',403);return;}
            $this->ci->output->set_status_header(str_contains($reason,'conflict')||str_contains($reason,'stale')?409:422);
            $error=match($reason){'company_sales_file_invalid'=>'Envie PDF até 2 MiB ou PNG/JPEG até 512 KiB e 1600 pixels.','company_sales_file_limit'=>'O documento atingiu o limite de anexos.','company_sales_delivery_template_required'=>'Ative o modelo próprio de envio e preserve o link privado.','company_sales_delivery_sender_required','company_mail_configuration_required','company_mail_secret_unavailable'=>'Complete a configuração SMTP desta empresa antes de agendar.','company_sales_stale'=>'O documento mudou. Reabra antes de continuar.',default=>'Confira os campos, permissões e estado do documento.'};
        }catch(Throwable $exception){log_message('error','Official company sales operation unconfirmed; no document values logged.');$this->ci->output->set_status_header(503);$error='Operação não confirmada. Reabra o documento antes de continuar.';}
        $this->ci->load->view('admin/'.$controller.'/manage',['native_company_sales_workspace'=>true,'native_sales_controller'=>$controller,'mutation_review'=>$mutationReview,'mutation_mode'=>$mutationMode,
            'mutations_ready'=>$mutationsReady,'portal_permissions'=>$portalPermissions,'can_contact_edit'=>$acl->can($issuer,$actor,'customers','edit'),'search'=>$search,'kind'=>$kind,'issuer'=>$issuer,
            'detail'=>$detail,'directory'=>$directory,'clients'=>$clients,'currencies'=>$currencies,'error'=>$error,'company_sales_csrf'=>$csrf,'can_create'=>$acl->can($issuer,$actor,$kind,'create'),
            'can_edit'=>$acl->can($issuer,$actor,$kind,'edit'),'can_delete'=>$acl->can($issuer,$actor,$kind,'delete'),'can_convert'=>$acl->can($issuer,$actor,'invoices','create')]);
    }
}
