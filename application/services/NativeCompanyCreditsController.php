<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Original credit-note routes with explicit company readers and the preserved financial writer. */
final class NativeCompanyCreditsController
{
    private static function context($ci): array
    {
        if (!CompanyRequestScope::nativeFor($ci)||!CompanyRequestScope::sealedFor($ci)) throw new DomainException('company_credit_access_denied');
        require_once APPPATH.'services/CompanyCredits.php';
        $context=CompanyRequestScope::context(); $issuer=$context->issuerId(); $actor=$context->staffId();
        $acl=new IssuerResourceAccess($ci->db,db_prefix());
        if (!$acl->can($issuer,$actor,'credit_notes','view')) throw new DomainException('company_credit_access_denied');
        $ci->output->set_header('Cache-Control: no-store');
        return [$issuer,$actor,$acl,new CompanyCredits($ci->db,db_prefix())];
    }
    private static function csrf($ci): string
    {
        $csrf=$ci->session->userdata('native_credit_notes_csrf');
        if (!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)) { $csrf=bin2hex(random_bytes(32)); $ci->session->set_userdata('native_credit_notes_csrf',$csrf); }
        return $csrf;
    }
    private static function capabilities(IssuerResourceAccess $acl,int $issuer,int $actor): array
    {
        $caps=[]; foreach (['create','edit','delete'] as $cap) $caps[$cap]=$acl->can($issuer,$actor,'credit_notes',$cap);
        $caps['payment_create']=$acl->can($issuer,$actor,'payments','create'); $caps['payment_delete']=$acl->can($issuer,$actor,'payments','delete');
        $caps['invoice_view']=$acl->can($issuer,$actor,'invoices','view'); $caps['customer_view']=$acl->can($issuer,$actor,'customers','view');
        return $caps;
    }
    private static function failure(Throwable $error,$jsonCi=null): void
    {
        $reason=$error->getMessage();
        $status=$error instanceof DomainException?(str_contains($reason,'denied')||str_contains($reason,'access')||str_contains($reason,'csrf')?403:(preg_match('/conflict|stale|changed|immutable|balance|history/',$reason)?409:422)):503;
        if (!$error instanceof DomainException) log_message('error','Company credit operation unconfirmed; financial values omitted.');
        $message=match($reason) {
            'company_credit_immutable'=>'O documento emitido conserva os dados e o número. Use os movimentos auditáveis disponíveis na visualização da nota.',
            'company_credit_exceeds_balance'=>'O valor excede o saldo atual da nota ou da fatura. Reabra a nota e confira os movimentos.',
            'company_credit_has_history'=>'Uma nota com movimentos conserva seu histórico. Revise os estornos disponíveis.',
            'company_credit_reference_conflict'=>'Esta referência já está registrada para a operação. Confira o histórico antes de repetir.',
            'company_credit_provider_review_required'=>'A operação desta fatura exige a integração bancária de origem.',
            'native_credit_catalog_precision_conflict'=>'O item possui preço ou tributo com precisão superior à aceita pela nota de crédito. Confira o cadastro antes de selecionar o item.',
            'company_credit_fields_stale'=>'Os campos personalizados foram alterados. Reabra o formulário para conferir os campos atuais.',
            default=>'Não foi possível confirmar a nota de crédito, seu saldo ou suas permissões. Reabra a página antes de repetir a operação.',
        };
        if($jsonCi!==null){$jsonCi->output->set_status_header($status)->set_content_type('application/json')->set_output(json_encode(['message'=>$message],JSON_THROW_ON_ERROR));return;}
        show_error($message,$status);
    }
    /** The successful URL keeps the published credit query parameter and selects the original split preview. */
    private static function mutate($ci,int $issuer,int $actor): void
    {
        CompanyRequestScope::assertNativeMutation($ci);
        $csrf=self::csrf($ci); $posted=$ci->input->post('native_credit_notes_csrf',false);
        if (!is_string($posted)||!hash_equals($csrf,$posted)) throw new DomainException('company_credit_access_denied');
        require_once APPPATH.'services/InvoicePaymentWriter.php';
        $writer=new \app\services\InvoicePaymentWriter($ci->db);
        $action=$ci->input->post('action',false); $key=$ci->input->post('request_key',false);
        if (!is_string($key)) throw new DomainException('company_credit_invalid_input');
        if ($action==='create') {
            $input=$ci->input->post('credit',false);
            if (!is_array($input)) throw new DomainException('company_credit_invalid_input');
            $form=$ci->input->post('_native_credit_form',false);
            if ($form!==null) {
                if ($form!=='1') throw new DomainException('company_credit_invalid_input');
                require_once APPPATH.'services/NativeCompanyCreditForm.php';
                $format=$ci->input->post('_native_credit_display_format',false);
                if(!is_array($format))throw new DomainException('company_credit_invalid_input');
                $input=(new NativeCompanyCreditForm($ci->db,db_prefix()))->input($issuer,$actor,$input,$format);
            }
            $id=$writer->issueCompanyCredit($issuer,$actor,$input,$key);
        } else {
            $id=IssuerPolicy::id($ci->input->post('credit_id',false));
            if ($action==='void') $writer->voidCompanyCredit($issuer,$actor,$id,$key);
            elseif (in_array($action,['apply','unapply','refund','refund_reverse'],true)) {
                $input=$ci->input->post('movement',false);
                if (!is_array($input)||array_key_exists('credit_id',$input)) throw new DomainException('company_credit_invalid_input');
                $writer->moveCompanyCredit($issuer,$actor,$action,['credit_id'=>$id]+$input,$key);
            } else throw new DomainException('company_credit_invalid_input');
        }
        CompanyRequestScope::invalidateCaches($ci);
        redirect(CompanyRequestScope::adminUrl($ci,'credit_notes?source=company&credit='.(int)$id),'location',303);
    }
    public static function handle($ci, ?string $legacyCreditId = null): void
    {
        try {
            [$issuer,$actor,$acl,$reader]=self::context($ci);
            $method=strtoupper($ci->input->method(true));
            if ($method==='POST') { self::mutate($ci,$issuer,$actor); return; }
            if (!in_array($method,['GET','HEAD'],true)) { show_error('Método não permitido.',405); return; }
            $id=$ci->input->get('credit',false);
            // Preserve official list URLs with an optional record segment and the existing query link.
            if ($id===null&&$legacyCreditId!==null&&$legacyCreditId!=='') $id=$legacyCreditId;
            if ($id!==null&&$id!=='') {
                if ($ci->input->get('pdf',false)==='1') { self::pdf($ci,$id); return; }
                $reader->get($issuer,$actor,$id); $id=IssuerPolicy::id($id);
            }
            require_once APPPATH.'services/NativeCompanyCreditTableFactory.php';
            require_once APPPATH.'services/NativeCompanyFilters.php';
            $factory=new NativeCompanyCreditTableFactory($ci->db,db_prefix());
            $table=$factory->make($issuer,$actor);
            $saved=(new NativeCompanyFilters($ci->db,db_prefix(),$issuer,$actor,$table))->listing();
            $table=$factory->make($issuer,$actor,'credit_notes',$saved);
            if ($method==='HEAD') { $ci->output->set_output(''); return; }
            $ci->load->view('admin/credit_notes/manage',['title'=>_l('credit_notes'),'table'=>$table,'credit_note_id'=>$id??'','native_credit_notes'=>true]);
        } catch (Throwable $error) { self::failure($error); }
    }
    public static function form($ci,$id=null): void
    {
        try {
            [$issuer,$actor,$acl,$reader]=self::context($ci);
            if ($id!==null&&$id!=='') { $reader->get($issuer,$actor,$id); throw new DomainException('company_credit_immutable'); }
            if (!$acl->can($issuer,$actor,'credit_notes','create')||!$acl->can($issuer,$actor,'customers','view')) throw new DomainException('company_credit_access_denied');
            $method=strtoupper($ci->input->method(true));
            if ($method==='POST') {
                if ($ci->input->post('action',false)!=='create') throw new DomainException('company_credit_invalid_input');
                self::mutate($ci,$issuer,$actor); return;
            }
            if (!in_array($method,['GET','HEAD'],true)) { show_error('Método não permitido.',405); return; }
            require_once APPPATH.'services/CompanyClients.php';
            $clients=[]; $after=0;
            do { $page=(new CompanyClients($ci->db,db_prefix()))->page($issuer,$actor,$after,100); $clients=array_merge($clients,$page['items']); $after=$page['next']; } while ($after!==null);
            if ($method==='HEAD') { $ci->output->set_output(''); return; }
            require_once APPPATH.'services/NativeCompanyCreditForm.php';
            $data=(new NativeCompanyCreditForm($ci->db,db_prefix()))->data($issuer,$actor);
            $ci->load->view('admin/credit_notes/credit_note',$data+['title'=>_l('new_credit_note'),'native_credit_form'=>true,'issuer'=>$issuer,'clients'=>$clients,'currencies'=>$reader->currencies($issuer,$actor),'native_credit_notes_csrf'=>self::csrf($ci),'capabilities'=>self::capabilities($acl,$issuer,$actor),'error'=>null]);
        } catch (Throwable $error) { self::failure($error); }
    }
    public static function preview($ci,$id): void
    {
        try {
            [$issuer,$actor,$acl,$reader]=self::context($ci);
            $method=strtoupper($ci->input->method(true));
            if (!in_array($method,['GET','HEAD'],true)) { show_error('Método não permitido.',405); return; }
            $document=$reader->getForAdmin($issuer,$actor,$id);
            $caps=self::capabilities($acl,$issuer,$actor);
            $invoices=$caps['invoice_view']?$reader->invoiceOptions($issuer,$actor,$id):[];
            $invoiceNumbers=[];
            if ($caps['invoice_view']) foreach ($document['entries'] as $entry) {
                if ($entry['invoice_id']===null||isset($invoiceNumbers[(int)$entry['invoice_id']])) continue;
                $number=IssuerStorage::query($ci->db,'SELECT i.formatted_number FROM '.db_prefix().'invoices i JOIN '.db_prefix().'issuers_invoice_documents d ON d.invoice_id=i.id AND d.issuer_id=i.issuer_id WHERE i.id=? AND i.issuer_id=? AND i.clientid=? AND i.currency=?',[$entry['invoice_id'],$issuer,$document['client_id'],$document['currency_id']])->row_array();
                if (!$number) throw new DomainException('company_credit_balance_review');
                $invoiceNumbers[(int)$entry['invoice_id']]=$number['formatted_number'];
            }
            if ($method==='HEAD') { $ci->output->set_output(''); return; }
            $ci->load->view('admin/credit_notes/credit_note_preview_template',['native_credit_preview'=>true,'document'=>$document,'invoices'=>$invoices,'invoice_numbers'=>$invoiceNumbers,'capabilities'=>$caps,'native_credit_notes_csrf'=>self::csrf($ci)]);
        } catch (Throwable $error) { self::failure($error); }
    }
    public static function pdf($ci,$id): void
    {
        try {
            [$issuer,$actor,$acl,$reader]=self::context($ci);
            if (!in_array(strtoupper($ci->input->method(true)),['GET','HEAD'],true)) { show_error('Método não permitido.',405); return; }
            $pdf=$reader->pdf($issuer,$actor,$id); $inline=$ci->input->get('output_type',false)==='I'||$ci->input->get('print',false);
            $ci->output->set_header('X-Content-Type-Options: nosniff')->set_header('Content-Disposition: '.($inline?'inline':'attachment').'; filename="nota-de-credito-'.IssuerPolicy::id($id).'.pdf"')->set_content_type('application/pdf')->set_output($pdf);
        } catch (Throwable $error) { self::failure($error); }
    }
    public static function client($ci,$id):void
    {
        try {
            [$issuer,$actor]=self::context($ci);$method=strtoupper($ci->input->method(true));
            if (!in_array($method,['GET','HEAD'],true)) { show_error('Método não permitido.',405); return; }
            require_once APPPATH.'services/NativeCompanyCreditForm.php';
            $data=(new NativeCompanyCreditForm($ci->db,db_prefix()))->client($issuer,$actor,$id);
            $ci->output->set_content_type('application/json')->set_output($method==='HEAD'?'':json_encode($data,JSON_THROW_ON_ERROR));
        } catch (Throwable $error) { self::failure($error,$ci); }
    }
    public static function catalog($ci,$id=null):void
    {
        try {
            [$issuer,$actor]=self::context($ci);$method=strtoupper($ci->input->method(true));
            if (!in_array($method,['GET','HEAD'],true)) { show_error('Método não permitido.',405); return; }
            require_once APPPATH.'services/NativeCompanyCreditCatalogLookup.php';
            $lookup=new NativeCompanyCreditCatalogLookup($ci->db,db_prefix(),$issuer,$actor);
            if($id===null){$q=$ci->input->get('q',false)??'';if(!is_string($q))throw new DomainException('company_credit_invalid_input');$data=$lookup->search($q);}
            else $data=$lookup->selected($id,$ci->input->get('currency',false));
            $ci->output->set_content_type('application/json')->set_output($method==='HEAD'?'':json_encode($data,JSON_THROW_ON_ERROR));
        } catch (Throwable $error) { self::failure($error,$ci); }
    }
    public static function status(int $status): string
    {
        $color=[1=>'#03a9f4',2=>'#84c529',3=>'#777'][$status]??null;
        if ($color===null) throw new DomainException('company_credit_balance_review');
        $label=_l([1=>'credit_note_status_open',2=>'credit_note_status_closed',3=>'credit_note_status_void'][$status]);
        return '<span class="label s-status" style="border: 1px solid '.$color.';color:'.$color.';">'.htmlspecialchars($label,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</span>';
    }
    public static function table($ci,$clientId=null): void
    {
        try {
            [$issuer,$actor,$acl]=self::context($ci); CompanyRequestScope::assertNativeMutation($ci);
            require_once APPPATH.'services/NativeCompanyCreditDirectory.php';
            require_once APPPATH.'services/NativeCompanyDataTableWire.php';
            require_once APPPATH.'services/NativeCompanyTableOrder.php';
            $input=$ci->input->post(null,false)?:[];
            unset($input['_company_context'],$input['_native_company_csrf']);
            if (isset($ci->security)) unset($input[$ci->security->get_csrf_token_name()]);
            if (isset($input['last_order_identifier'])&&!in_array($input['last_order_identifier'],['credit-notes','credit_notes'],true)) throw new DomainException('native_credit_table_invalid');
            $builder=$input['filters']??['match_type'=>'and','rules'=>[]]; unset($input['filters'],$input['last_order_identifier']);
            if (!is_array($builder)) throw new DomainException('native_credit_filter_invalid');
            $columns=new NativeCompanyCreditColumns($ci->db,db_prefix(),$issuer,$actor);
            $fields=NativeCompanyCreditDirectory::FIELDS; $types=array_fill(0,count($fields),'');
            foreach ($columns->definitions(true) as $definition) { $fields[]='custom_field_'.$definition['id']; $types[]=$definition['type']; }
            $request=NativeCompanyDataTableWire::request($input,$fields,$types);
            $page=(new NativeCompanyCreditDirectory($ci->db,db_prefix()))->listing($issuer,$actor,$request,$builder,$clientId===null||$clientId===''?null:IssuerPolicy::id($clientId));
            $e=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); $rows=[];
            foreach ($page['rows'] as $row) {
                $doc=$row['document']; $id=$doc['id']; $url=CompanyRequestScope::adminUrl($ci,'credit_notes/list_credit_notes/'.$id);
                $number='<a href="'.$e($url).'" onclick="init_credit_note('.$id.'); return false;" class="tw-font-medium">'.$e($doc['number']).'</a><div class="row-options"><a href="'.$e($url).'" onclick="init_credit_note('.$id.'); return false;">'.$e(_l('view')).'</a></div>';
                $customer=$e($doc['recipient']['name']);
                if ($acl->can($issuer,$actor,'customers','view')) $customer='<a href="'.$e(CompanyRequestScope::adminUrl($ci,'clients/client/'.$doc['client_id'])).'">'.$customer.'</a>';
                $cells=[$number,$e(_d($doc['date'])),$customer,self::status($doc['status']),$e($row['project_name']),$e($row['reference_no']),'<span class="tw-font-medium">'.$e(app_format_money($doc['amounts']['total'],$doc['currency'])).'</span>','<span class="tw-font-medium">'.$e(app_format_money($doc['available'],$doc['currency'])).'</span>'];
                foreach ($page['definitions'] as $definition) {
                    $value=$row['custom_values'][(string)$definition['id']]??'';
                    $cells[]=$e($value!==''&&in_array($definition['type'],['date_picker','date_picker_time'],true)?($definition['type']==='date_picker'?_d($value):_dt($value)):$value);
                }
                $cells['DT_RowClass']='has-row-options'; $rows[]=$cells;
            }
            $columns->recheck();
            if (isset($input['order'])&&get_option('save_last_order_for_tables')==='1') (new NativeCompanyTableOrder($ci->db,db_prefix(),$issuer,$actor))->save('credit-notes',$input['order'],count($fields));
            $ci->output->set_content_type('application/json')->set_output(json_encode(['draw'=>$request['draw'],'iTotalRecords'=>$page['total'],'iTotalDisplayRecords'=>$page['filtered'],'aaData'=>$rows],JSON_THROW_ON_ERROR));
        } catch (Throwable $error) { self::failure($error); }
    }
}
