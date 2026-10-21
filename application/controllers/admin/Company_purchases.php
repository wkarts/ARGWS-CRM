<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Company_purchases extends AdminController
{
    public function index()
    {
        if (!CompanyRequestScope::sealedFor($this)) { show_error('Contexto de empresa indisponível.',503); return; }
        require_once APPPATH.'services/CompanyPurchases.php'; require_once APPPATH.'services/CompanyOperationFilesHttp.php';
        $context=CompanyRequestScope::context(); $issuer=$context->issuerId(); $actor=$context->staffId(); $service=new CompanyPurchases($this->db,db_prefix()); $acl=new IssuerResourceAccess($this->db,db_prefix());
        $this->output->set_header('Cache-Control: no-store');
        if (!$acl->can($issuer,$actor,'purchases','view')) { show_error('Sem acesso às compras desta empresa.',403); return; }
        $csrf=$this->session->userdata('company_purchases_csrf');
        if (!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)) { $csrf=bin2hex(random_bytes(32)); $this->session->set_userdata('company_purchases_csrf',$csrf); }
        $caps=[]; foreach (['view','create','edit','approve','receive','pay','portal','report','settings'] as $cap) { $caps[$cap]=$acl->can($issuer,$actor,'purchases',$cap); }
        $tab=$this->input->get('tab',false)??'requests'; $error=null; $workspace=null; $page=['items'=>[],'next'=>null]; $settings=null; $categories=[]; $files=[]; $invites=[]; $report=null; $inviteSecret=null; $editSupplier=null;
        try {
            if (!is_string($tab)||!in_array($tab,['suppliers','requests','orders','payables','report','settings'],true)) { throw new DomainException('company_purchase_invalid'); }
            $verb=$this->input->method(true);
            if ($verb==='POST') {
                $posted=$this->input->post('company_purchases_csrf',false);
                if (!is_string($posted)||!hash_equals($csrf,$posted)) { show_error('Recarregue o formulário antes de salvar.',403); return; }
                $action=$this->input->post('action',false); $key=$this->input->post('request_key',false); $input=$this->input->post('purchase',false)??[]; $id=$this->input->post('object_id',false); $revision=$this->input->post('revision',false);
                if (!is_string($action)||!is_string($key)||!is_array($input)) { throw new DomainException('company_purchase_invalid'); }
                $request=$this->input->post('request_id',false); $destination='?tab='.$tab;
                switch ($action) {
                    case 'configure': if (is_array($input['rates']??null)) { $input['rates']=array_filter($input['rates'],static fn($v)=>$v!==''); } $service->configure($issuer,$actor,$revision===null||$revision===''?null:CompanyPurchasesValues::integer($revision,1),$input,$key); break;
                    case 'supplier': $result=$service->saveSupplier($issuer,$actor,$id===null||$id===''?null:IssuerPolicy::id($id),$revision===null||$revision===''?null:CompanyPurchasesValues::integer($revision,1),$input,$key); $destination='?tab=suppliers&supplier='.$result; break;
                    case 'request': $request=$service->createRequest($issuer,$actor,$input,$key); break;
                    case 'approve': $request=$service->approveRequest($issuer,$actor,$id,$revision,$key); break;
                    case 'cancel_request': $request=$service->cancel($issuer,$actor,'request',$id,$revision,$key); break;
                    case 'quote': $service->quote($issuer,$actor,$input,$key); break;
                    case 'order': $service->createOrder($issuer,$actor,$input,$key); break;
                    case 'cancel_order': $service->cancel($issuer,$actor,'order',$id,$revision,$key); break;
                    case 'receive': if (is_array($input['lines']??null)) { $input['lines']=array_values(array_filter($input['lines'],static fn($v)=>!is_array($v)||($v['quantity']??null)!=='')); } $service->receive($issuer,$actor,$id,$revision,$input,$key); break;
                    case 'document': $service->postDocument($issuer,$actor,$input,$key); break;
                    case 'settle': if (!is_string($revision)) { throw new DomainException('company_purchase_invalid'); } $service->settle($issuer,$actor,$id,$revision,$input,$key); break;
                    case 'reverse': if (!is_string($revision)) { throw new DomainException('company_purchase_invalid'); } $service->reverseSettlement($issuer,$actor,$id,$revision,$input,$key); break;
                    case 'invite': CompanyPurchasesValues::fields($input,['supplier_id','days']); $result=$service->issueInvite($issuer,$actor,$request,$input['supplier_id']??null,CompanyPurchasesValues::integer($input['days']??null,1,30),$key); $inviteSecret=$result['secret']; $tab='requests'; break;
                    case 'revoke_invite': $service->revokeInvite($issuer,$actor,$id,$revision,$key); break;
                    case 'upload': [$name,$bytes]=CompanyOperationFilesHttp::upload(); CompanyPurchasesValues::fields($input,['supplier_id','portal_visible']); $supplier=empty($input['supplier_id'])?null:IssuerPolicy::id($input['supplier_id']); $service->upload($issuer,$actor,$request,$supplier,$name,$bytes,($input['portal_visible']??'0')==='1',$key); break;
                    case 'hide_file': $service->hideFile($issuer,$actor,$id,$revision,$key); break;
                    default: throw new DomainException('company_purchase_invalid');
                }
                if ($request!==null&&$request!=='') { $request=IssuerPolicy::id($request); $destination='?tab=requests&request='.$request; }
                CompanyRequestScope::invalidateCaches($this);
                if ($action!=='invite') { redirect(CompanyRequestScope::adminUrl($this,'company_purchases'.$destination),'location',303); return; }
                // The invitation secret is shown once in this successful POST, never in session flash or a query string.
            } elseif (!in_array($verb,['GET','HEAD'],true)) { show_error('Método não permitido.',405); return; }
            else { $request=$this->input->get('request',false); }
            $download=$this->input->get('download',false);
            if ($download!==null) { $file=$service->download($issuer,$actor,$download); CompanyOperationFilesHttp::respond($this,$file); return; }
            $pdf=$this->input->get('order_pdf',false);
            if ($pdf!==null) { $bytes=$service->orderPdf($issuer,$actor,$pdf); $this->output->set_header('Content-Disposition: attachment; filename="pedido.pdf"')->set_content_type('application/pdf')->set_output($verb==='HEAD'?'':$bytes); return; }
            $settings=$service->configuration($issuer,$actor);
            if ($tab==='report') {
                $from=$this->input->get('from',false)??gmdate('Y-m-01'); $asOf=$this->input->get('as_of',false)??gmdate('Y-m-d');
                if (!is_string($from)||!is_string($asOf)) { throw new DomainException('company_purchase_invalid'); }
                if ($this->input->get('format',false)==='csv') { $csv=$service->export($issuer,$actor,$from,$asOf); $this->output->set_header('Content-Disposition: attachment; filename="contas-a-pagar.csv"')->set_content_type('text/csv','UTF-8')->set_output($verb==='HEAD'?'':$csv); return; }
                $report=$service->report($issuer,$actor,$from,$asOf,CompanyPurchasesValues::integer($this->input->get('after',false)??0));
            } elseif ($tab!=='settings') {
                $kind=['suppliers'=>'supplier','requests'=>'request','orders'=>'order','payables'=>'payable'][$tab]; $page=$service->page($issuer,$actor,$kind,CompanyPurchasesValues::integer($this->input->get('after',false)??0),50);
            }
            $supplier=$this->input->get('supplier',false); if ($supplier!==null) { $editSupplier=$service->get($issuer,$actor,'supplier',$supplier); }
            if ($request!==null&&$request!=='') {
                $request=IssuerPolicy::id($request); $workspace=$service->workspace($issuer,$actor,$request); $files=$service->files($issuer,$actor,$request);
                if ($caps['portal']) { $invites=$service->invites($issuer,$actor,$request); }
                if ($acl->can($issuer,$actor,'expenses','view')) { $categories=(new CompanyExpenses($this->db,db_prefix()))->categories($issuer,$actor); }
            }
        } catch (DomainException|InvalidArgumentException $e) {
            $reason=$e->getMessage(); $this->output->set_status_header(str_ends_with($reason,'denied')?403:((str_contains($reason,'stale')||str_contains($reason,'conflict'))?409:422));
            $error=match($reason) {
                'company_purchase_settings_required'=>'Configure primeiro a numeração e as taxas de câmbio explícitas desta empresa.',
                'company_purchase_stale'=>'O registro mudou. Reabra a página antes de confirmar outra operação.',
                'company_purchase_denied','company_purchase_reference_denied'=>'Registro ou permissão indisponível nesta empresa.',
                'company_purchase_receipt_exceeds_order'=>'A quantidade ultrapassa o saldo ainda não recebido do pedido.',
                'company_purchase_receipt_already_billed'=>'Um recebimento selecionado já pertence a um documento fechado.',
                'company_purchase_settlement_exceeds_balance'=>'O valor ultrapassa o saldo a pagar ou a data antecede o documento.',
                'company_purchase_evidence_immutable'=>'O arquivo comprova um documento ou uma declaração e deve permanecer preservado.',
                'company_purchase_exchange_required'=>'Informe uma taxa de câmbio para a moeda da cotação antes de aprovar o pedido.',
                'company_purchase_export_limit'=>'O período excede 5.000 títulos. Reduza o período para exportar um conjunto completo.',
                'company_expense_denied'=>'Fechar um documento também exige consultar e criar despesas nesta empresa.',
                default=>'Confira os dados, as quantidades e o estado atual. Nenhuma confirmação parcial é aceita.'
            };
        } catch (Throwable $e) {
            log_message('error','Company purchases operation unconfirmed; private data and secrets omitted.'); $this->output->set_status_header(503); $error='Não foi possível confirmar a operação. Reabra a página para verificar o estado antes de tentar novamente.';
        }
        $this->load->view('admin/companies/purchases',['issuer'=>$issuer,'actor'=>$actor,'tab'=>$tab,'page'=>$page,'workspace'=>$workspace,'settings'=>$settings,'categories'=>$categories,'files'=>$files,'invites'=>$invites,'report'=>$report,'inviteSecret'=>$inviteSecret,'editSupplier'=>$editSupplier,'error'=>$error,'capabilities'=>$caps,'company_purchases_csrf'=>$csrf]);
    }
}
