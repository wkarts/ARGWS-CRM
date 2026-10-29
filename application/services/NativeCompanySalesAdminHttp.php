<?php
declare(strict_types=1);
require_once __DIR__.'/CompanySales.php';
require_once __DIR__.'/CompanySalesExtrasHttp.php';
require_once __DIR__.'/CompanyClients.php';
require_once __DIR__.'/CompanyContactSalesAccess.php';
require_once __DIR__.'/NativeCompanySalesDirectory.php';

/** Complete issuer adapter for the original Estimates and Proposals admin routes. */
final class NativeCompanySalesAdminHttp
{
    public function __construct(private $ci) {}

    private static function controller(string $kind):string
    {
        return match($kind){'estimates'=>'estimates','proposals'=>'proposals',default=>throw new DomainException('company_sales_denied')};
    }

    /** DataTables remain POST/CSRF protected; pipeline reading has a separate GET contract. */
    public function table(string $kind,$clientId=''):void
    {
        if(!CompanyRequestScope::sealedFor($this->ci)){show_error('Tabela indisponível nesta empresa.',403);return;}
        try{
            CompanyRequestScope::assertNativeMutation($this->ci);$ctx=CompanyRequestScope::context();
            $post=$this->ci->input->post(null,false)?:[];$search=$post['search']['value']??($post['search']??'');
            $filter=['search'=>$search,'start'=>$post['start']??0,'length'=>$post['length']??50,'after'=>$post['after']??0];
            if($clientId!==null&&$clientId!=='')$filter['client_id']=$clientId;
            $page=(new NativeCompanySalesDirectory($this->ci->db,db_prefix()))->query($ctx->issuerId(),$ctx->staffId(),$kind,$filter);
            $e=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$rows=[];
            foreach($page['items']as$row){$link='<a href="'.$e(CompanyRequestScope::adminUrl($this->ci,$kind.'/list_'.$kind.'/'.$row['id'])).'">'.$e($row['formatted_number']).'</a>';$date=$row['payload']['date'];$due=$row['payload']['due_date'];$client=$e($row['context']['recipient']['name']);$total=$e($row['context']['currency']['name'].' '.$row['total']);
                $rows[]=$kind==='estimates'?[$link,$total,$e($row['stored_amounts']['total_tax']??$row['context']['totals']['tax']??''),$e(substr($date,0,4)),$client,'','',$e($date),$e($due),$e($row['reference_no']??''),$e($row['state'])]:[$link,$e($row['subject']??$row['formatted_number']),$client,$total,$e($date),$e($due),'',$e($row['created_at']),$e($row['state']),''];
            }
            $this->ci->output->set_header('Cache-Control: private, no-store')->set_content_type('application/json')->set_output(json_encode(['draw'=>(int)($post['draw']??0),'recordsTotal'=>$page['total'],'recordsFiltered'=>$page['filtered'],'iTotalRecords'=>$page['total'],'iTotalDisplayRecords'=>$page['filtered'],'aaData'=>$rows,'data'=>$rows,'next'=>$page['next']],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        }catch(DomainException $error){show_error('Tabela indisponível nesta empresa.',str_contains($error->getMessage(),'invalid')?422:403);}catch(Throwable $error){show_error('Não foi possível confirmar a tabela desta empresa.',503);}
    }

    public function pipelineLoadMore(string $kind):void
    {
        if(!CompanyRequestScope::sealedFor($this->ci)){show_error('Pipeline indisponível nesta empresa.',403);return;}
        $method=strtoupper($this->ci->input->method(true));
        if(!in_array($method,['GET','HEAD'],true)){$this->ci->output->set_header('Allow: GET, HEAD');show_error('Método não permitido.',405);return;}
        try{
            $ctx=CompanyRequestScope::context();$page=$this->ci->input->get('page',false)??'1';
            if((!is_string($page)&&!is_int($page))||!preg_match('/^[1-9][0-9]{0,4}$/D',(string)$page))throw new DomainException('company_sales_invalid');
            $store=new CompanySettingsStorage($this->ci->db,db_prefix());$limit=$store->get($ctx->issuerId(),$kind.'_pipeline_limit');
            $limit=ctype_digit((string)$limit)&& (int)$limit>0?min(100,(int)$limit):10;
            $sort=$this->ci->input->get('sort_by',false)?:'pipeline_order';$direction=$this->ci->input->get('sort',false)?:'ASC';
            $result=(new NativeCompanySalesDirectory($this->ci->db,db_prefix()))->query($ctx->issuerId(),$ctx->staffId(),$kind,['status'=>$this->ci->input->get('status',false)??0,'search'=>$this->ci->input->get('search',false)??'','start'=>((int)$page-1)*$limit,'length'=>$limit,'sort_by'=>$sort,'sort'=>$direction]);
            $html=$this->cards($kind,$result['items']);
            if(!(new IssuerResourceAccess($this->ci->db,db_prefix()))->can($ctx->issuerId(),$ctx->staffId(),$kind,'view'))throw new DomainException('company_sales_denied');
            $this->ci->output->set_header('Cache-Control: private, no-store')->set_content_type('text/html')->set_output($method==='HEAD'?'':$html);
        }catch(DomainException $error){show_error('Pipeline indisponível nesta empresa.',str_contains($error->getMessage(),'invalid')?422:403);}catch(Throwable $error){log_message('error','Native sales pipeline read failed; document data omitted.');show_error('Não foi possível carregar o pipeline.',503);}
    }

    private function cards(string $kind,array $documents):string
    {
        $ctx=CompanyRequestScope::context();$html='';$type=NativeCompanySalesDirectory::type($kind);$canEdit=(new IssuerResourceAccess($this->ci->db,db_prefix()))->can($ctx->issuerId(),$ctx->staffId(),$kind,'edit');
            foreach($documents as$document){
                $card=['id'=>$document['id'],'status'=>$document['native_status'],'invoiceid'=>$document['invoice_id']?:null,'invoice_id'=>$document['invoice_id']?:null,'estimate_id'=>null,'company'=>$document['context']['recipient']['name'],'proposal_to'=>$document['context']['recipient']['name'],'subject'=>$document['subject']??$document['formatted_number'],'rel_type'=>'customer','rel_id'=>$document['payload']['client_id'],'clientid'=>$document['payload']['client_id'],'total'=>$document['total'],'currency'=>$document['payload']['currency'],'currency_name'=>$document['context']['currency']['name'],'date'=>$document['payload']['date'],'expirydate'=>$document['payload']['due_date'],'open_till'=>$document['payload']['due_date'],'formatted_number'=>$document['formatted_number']];
                $html.=$this->ci->load->view('admin/'.$kind.'/pipeline/_kanban_card',[$type=>$card,'status'=>$card['status'],'native_sales_card'=>true,'native_sales_can_edit'=>$canEdit,'native_sales_tags'=>[],'native_sales_note_count'=>0],true);
            }
        return$html;
    }

    private function ownedId(string $kind,$id):int
    {
        $type=NativeCompanySalesDirectory::type($kind);$reference=NativeCompanyResourceId::decode($type,$id);
        // Old form bodies used the private-store ID. New links and forms always carry a typed ID.
        if($reference['source_kind']===$type)return $reference['source_id'];
        if($reference['source_kind']!=='company_'.$type)throw new DomainException('company_sales_denied');
        return $reference['source_id'];
    }

    public function relations(string $kind,$id,$type='customer'):void
    {
        if(!CompanyRequestScope::sealedFor($this->ci)){show_error('Relações indisponíveis nesta empresa.',403);return;}
        try{$context=CompanyRequestScope::context();$issuer=$context->issuerId();$actor=$context->staffId();$search=(string)($this->ci->input->get('search',false)??'');$rows=(new CompanySales($this->ci->db,db_prefix()))->relationOptions($issuer,$actor,$kind,$search);$this->ci->output->set_content_type('application/json')->set_output(json_encode(['data'=>$rows,'rel_id'=>(int)$id,'rel_type'=>$type],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));}
        catch(DomainException $error){show_error('Relações indisponíveis nesta empresa.',str_contains($error->getMessage(),'invalid')?422:403);}catch(Throwable $error){show_error('Não foi possível confirmar as relações.',503);}
    }

    public function pipeline(string $kind,$selected=null):void
    {
        if(!CompanyRequestScope::sealedFor($this->ci)){show_error('Pipeline indisponível.',403);return;}
        $method=strtoupper($this->ci->input->method(true));if(!in_array($method,['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
        try{
            $ctx=CompanyRequestScope::context();$reader=new NativeCompanySalesDirectory($this->ci->db,db_prefix());$store=new CompanySettingsStorage($this->ci->db,db_prefix());$size=$store->get($ctx->issuerId(),$kind.'_pipeline_limit');$size=ctype_digit((string)$size)&&(int)$size>0?min(100,(int)$size):10;$columns=[];
            foreach(range(1,$kind==='estimates'?5:6)as$status){$page=(new NativeCompanySalesDirectory($this->ci->db,db_prefix()))->query($ctx->issuerId(),$ctx->staffId(),$kind,['status'=>$status,'search'=>$this->ci->input->get('search',false)??'','sort_by'=>$this->ci->input->get('sort_by',false)?:'pipeline_order','sort'=>$this->ci->input->get('sort',false)?:'ASC','length'=>$size]);$columns[]=['status'=>$status,'total'=>$page['filtered'],'count'=>count($page['items']),'pages'=>(int)ceil($page['filtered']/$size),'html'=>$this->cards($kind,$page['items'])];}
            $html=$this->ci->load->view('admin/'.$kind.'/pipeline/pipeline',['native_sales_columns'=>$columns,'native_sales_kind'=>$kind],true);
            if(!(new IssuerResourceAccess($this->ci->db,db_prefix()))->can($ctx->issuerId(),$ctx->staffId(),$kind,'view'))throw new DomainException('company_sales_denied');
            $this->ci->output->set_header('Cache-Control: private, no-store')->set_content_type('text/html')->set_output($method==='HEAD'?'':$html);
        }catch(DomainException $e){show_error('Pipeline indisponível.',str_contains($e->getMessage(),'invalid')?422:403);}catch(Throwable $e){show_error('Não foi possível confirmar o pipeline.',503);}
    }

    public function index(string $kind,$selected=null,bool $forcePdf=false):void
    {
        if(!CompanyRequestScope::sealedFor($this->ci)){show_error('Documento indisponível nesta empresa.',403);return;}
        $controller=self::controller($kind);$context=CompanyRequestScope::context();$issuer=$context->issuerId();$actor=$context->staffId();
        $acl=new IssuerResourceAccess($this->ci->db,db_prefix());if(!$acl->can($issuer,$actor,$kind,'view')){show_error('Documento indisponível nesta empresa.',403);return;}
        $this->ci->output->set_header('Cache-Control: no-store');$sales=new CompanySales($this->ci->db,db_prefix());$reader=new NativeCompanySalesDirectory($this->ci->db,db_prefix());
        $portalPermissions=[];$search='';$mutationReview=null;$mutationMode=null;$mutationsReady=CompanySalesMutationsSchema::present($this->ci->db,db_prefix());$error=null;$detail=null;
        $directory=['items'=>[],'next'=>null];$clients=[];$currencies=[];$csrf=$this->ci->session->userdata('company_sales_csrf');
        if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->ci->session->set_userdata('company_sales_csrf',$csrf);}
        try{
            $method=$this->ci->input->method(true);
            if($method==='POST'){
                CompanyRequestScope::assertNativeMutation($this->ci);$token=$this->ci->input->post('company_sales_csrf',false);
                if(!is_string($token)||!hash_equals($csrf,$token))throw new DomainException('company_sales_denied');
                $action=$this->ci->input->post('action',false);$id=$this->ci->input->post('document_id',false);
                if($action==='legacy_update'){
                    $reference=NativeCompanyResourceId::decode(NativeCompanySalesDirectory::type($kind),$id);
                    if($reference['source_kind']!==NativeCompanySalesDirectory::type($kind))throw new DomainException('company_sales_denied');
                    $changes=$this->ci->input->post('legacy',false);$revision=$this->ci->input->post('revision',false);
                    if(!is_array($changes)||!is_string($revision))throw new DomainException('company_sales_invalid');
                    require_once __DIR__.'/NativeCompanyLegacySales.php';(new NativeCompanyLegacySales($this->ci->db,db_prefix()))->update($issuer,$actor,$kind,$reference['source_id'],$revision,$changes);
                    CompanyRequestScope::invalidateCaches($this->ci);redirect(CompanyRequestScope::adminUrl($this->ci,$controller.'/list_'.$kind.'/'.$reference['source_id']),'location',303);return;
                }
                if($action!=='create'){$id=$this->ownedId($kind,$id);$checked=$sales->document($issuer,$actor,$id);if($checked['kind']!==$kind)throw new DomainException('company_sales_denied');}
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
                if($action!=='review_copy'){CompanyRequestScope::invalidateCaches($this->ci);redirect(CompanyRequestScope::adminUrl($this->ci,$controller).'?document='.NativeCompanyResourceId::encode('company_'.NativeCompanySalesDirectory::type($kind),(int)$id),'location',303);return;}
            }elseif(!in_array($method,['GET','HEAD'],true))throw new DomainException('company_sales_method_denied');
            $id=$mutationReview?$mutationReview['id']:($selected!==null&&$selected!==''?$selected:$this->ci->input->get('document',false));
            if($id!==null&&$id!==false&&$id!==''){
                $type=NativeCompanySalesDirectory::type($kind);
                $canonical=$id;
                if($mutationReview||(($selected===null||$selected==='')&&$this->ci->input->get('source',false)!=='legacy'&&(int)$id<4294967296))$canonical=NativeCompanyResourceId::encode('company_'.$type,$id);
                $detail=$reader->document($issuer,$actor,$kind,$canonical);$id=$detail['source_id'];
                if($detail['origin']==='legacy')$mutationsReady=false;
                if($detail['origin']==='owned'&&CompanySalesExtrasHttp::download($this->ci,$issuer,$actor,$id))return;
                if($forcePdf||$this->ci->input->get('pdf',false)==='1'){
                    require_once APPPATH.'vendor/tecnickcom/tcpdf/tcpdf.php';require_once __DIR__.'/CompanySalesPdf.php';if($detail['origin']==='legacy'){require_once __DIR__.'/NativeCompanyLegacySalesPdf.php';$pdf=NativeCompanyLegacySalesPdf::prepare($detail);(new NativeCompanyLegacySales($this->ci->db,db_prefix()))->confirmPdf($issuer,$actor,$kind,$id,$detail['revision_token']);}
                    else{$pdf=CompanySalesPdf::prepare($detail);$sales->confirmPdf($issuer,$actor,$id,$detail['revision_token']);}
                    $bytes=$pdf->Output('','S');if(!is_string($bytes)||!str_starts_with($bytes,'%PDF-'))throw new RuntimeException('issuer_sales_pdf_unconfirmed');
                    $this->ci->output->set_header('Cache-Control: private, no-store')->set_header('X-Content-Type-Options: nosniff')->set_header('Content-Disposition: attachment; filename="documento.pdf"')->set_content_type('application/pdf')->set_output($method==='HEAD'?'':$bytes);return;
                }
                if($detail['origin']==='owned'&&$acl->can($issuer,$actor,'customers','view')){try{$portalPermissions=(new CompanyContactSalesAccess($this->ci->db,db_prefix(),$kind))->page($issuer,$actor,$detail['payload']['client_id']);}catch(DomainException $unavailable){$portalPermissions=[];}}
            }
            $requested=$this->ci->input->get('review',false);if($requested!==null){if(!$detail||$detail['origin']!=='owned'||!in_array($requested,['edit','copy'],true))throw new DomainException('company_sales_invalid');$mutationReview=$sales->review($issuer,$actor,$id,$requested);$mutationMode=$requested;}
            $search=$this->ci->input->get('search',false)??'';if(!is_string($search))throw new DomainException('company_sales_invalid');
            $after=$this->ci->input->get('after',false)??0;$directory=$reader->query($issuer,$actor,$kind,['after'=>$after,'search'=>$search]);
            if(($acl->can($issuer,$actor,$kind,'create')||$mutationMode==='edit'&&$acl->can($issuer,$actor,$kind,'edit'))&&$acl->can($issuer,$actor,'customers','view')){
                $clients=(new CompanyClients($this->ci->db,db_prefix()))->page($issuer,$actor,0,100)['items'];$currencies=$sales->currencies($issuer,$actor,$kind);
            }
            if($mutationReview)$mutationReview['id']=NativeCompanyResourceId::encode('company_'.NativeCompanySalesDirectory::type($kind),$mutationReview['id']);
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
