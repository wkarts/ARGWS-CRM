<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyPaymentRecords.php';
require_once __DIR__.'/NativeCompanyInvoicePaymentForm.php';
require_once __DIR__.'/NativeCompanyInvoiceFormInput.php';
require_once __DIR__.'/CompanyFinancialDocumentPdf.php';
require_once __DIR__.'/CompanySettingsFormats.php';
/** Finite original Payments route composition. No global payment model in a secondary company. */
final class NativeCompanyPaymentsHttp
{
    private static function post($ci):array{
        CompanyRequestScope::assertNativeMutation($ci);$post=$ci->input->post(null,false)?:[];
        unset($post['_company_context'],$post['_native_company_csrf']);if(isset($ci->security))unset($post[$ci->security->get_csrf_token_name()]);return$post;
    }
    public static function handle($ci,string $action,$id=null):void{
        try{
            if(!CompanyRequestScope::nativeFor($ci)||!CompanyRequestScope::sealedFor($ci))throw new DomainException('native_payment_denied');
            $ctx=CompanyRequestScope::context();$i=$ctx->issuerId();$a=$ctx->staffId();if($i===null)throw new DomainException('native_payment_denied');
            $p=db_prefix();$service=new NativeCompanyPaymentRecords($ci->db,$p);$verb=strtoupper($ci->input->method(true));$acl=new IssuerResourceAccess($ci->db,$p);$ci->output->set_header('Cache-Control: no-store');
            if(in_array($action,['index','list_payments'],true)){
                if(!in_array($verb,['GET','HEAD'],true))throw new DomainException('native_payment_method_denied');
                if(!$acl->can($i,$a,'payments','view')||!$acl->can($i,$a,'invoices','view'))throw new DomainException('native_payment_denied');
                $ci->load->view('admin/payments/manage',['title'=>_l('payments'),'native_payments'=>true]);return;
            }
            if($action==='table'){
                $post=self::post($ci);foreach(['draw','start','length']as$field)if(isset($post[$field])&&(!is_string($post[$field])||!preg_match('/^(0|[1-9][0-9]*)$/D',$post[$field])||($field==='start'&&strlen($post[$field])>18)))throw new DomainException('native_payment_table_invalid');
                $start=(int)($post['start']??0);$length=(int)($post['length']??25);if($length<1||$length>500)throw new DomainException('native_payment_table_invalid');
                $searchData=$post['search']??[];if(!is_array($searchData))throw new DomainException('native_payment_table_invalid');$search=$searchData['value']??'';if(!is_string($search)||strlen($search)>500||!in_array($searchData['regex']??false,[false,'false','0',0],true))throw new DomainException('native_payment_table_invalid');
                $fields=['id','formatted_number','paymentmethod','transactionid','company','amount','date'];$orders=$post['order']??[['column'=>'0','dir'=>'desc']];if(!is_array($orders)||!array_is_list($orders)||count($orders)>7)throw new DomainException('native_payment_table_invalid');$mapped=[];
                foreach($orders as$order){$column=(string)($order['column']??'');if(!isset($fields[$column])||!in_array($order['dir']??null,['asc','desc'],true))throw new DomainException('native_payment_table_invalid');$mapped[]=['field'=>$fields[$column],'dir'=>$order['dir']];}
                $client=$id===null||$id===''?null:IssuerPolicy::id($id);$page=$service->listingPage($i,$a,$client,$start,$length,$search,$mapped);$out=[];$e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
                foreach($page['rows']as$row)$out[]=['<a href="'.$e(CompanyRequestScope::adminUrl($ci,'payments/payment/'.$row['id'])).'">'.$e($row['id']).'</a>','<a href="'.$e(CompanyRequestScope::adminUrl($ci,'invoices/list_invoices/'.$row['invoiceid'])).'">'.$e($row['formatted_number']).'</a>',$e($row['paymentmethod']?:$row['paymentmode']),$e($row['transactionid']),$e($row['company']),$e($row['currency'].' '.$row['amount']),$e($row['date'])];
                $ci->output->set_content_type('application/json')->set_output(json_encode(['draw'=>(int)($post['draw']??0),'iTotalRecords'=>$page['total'],'iTotalDisplayRecords'=>$page['filtered'],'aaData'=>$out],JSON_THROW_ON_ERROR));return;
            }
            if($action==='batch_payment_modal'){
                if(!in_array($verb,['GET','HEAD'],true)||!$acl->can($i,$a,'payments','create'))throw new DomainException('native_payment_denied');
                $invoices=[];$customers=[];$reader=new CompanyInvoices($ci->db,$p);$after=0;
                do{$page=$reader->page($i,$a,$after);foreach($page['items']as$row){if(!in_array((int)$row['status'],[1,3,4],true))continue;$form=(new NativeCompanyInvoicePaymentForm($ci->db,$p))->read($i,$a,$row['id']);if(CompanyInvoiceAmounts::minor($form['balance']['receivable'])<1||!$form['payment_modes'])continue;
                    $doc=$form['invoice'];$invoices[]=(object)['id'=>$row['id'],'clientid'=>$doc['recipient']['client_id'],'company'=>$doc['recipient']['name'],'formatted_number'=>$doc['number'],'total_left_to_pay'=>$form['balance']['receivable'],'currency'=>$doc['currency'],'allowed_payment_modes'=>array_map(static fn($m)=>(object)$m,$form['payment_modes']),'native_revision'=>$form['revision']];$customers[$doc['recipient']['client_id']]=(object)['userid'=>$doc['recipient']['client_id'],'company'=>$doc['recipient']['name']];if(count($invoices)===50)break;}$after=$page['next'];}while($after!==null&&count($invoices)<50);
                $ci->load->view('admin/payments/batch_payment_modal',['invoices'=>$invoices,'customers'=>array_values($customers),'native_payments'=>true,'native_batch_key'=>bin2hex(random_bytes(32))]);return;
            }
            if($action==='add_batch_payment'){
                $post=self::post($ci);if(array_diff(array_keys($post),['invoice','request_key','client_filter','do_not_send_invoice_payment_recorded'])||!is_array($post['invoice']??null)||count($post['invoice'])>50||!is_string($post['request_key']??null))throw new DomainException('company_payment_batch_invalid');
                // Filtering the original modal disables rows, so browser form indexes may have gaps.
                foreach(array_keys($post['invoice'])as$index)if(!is_int($index)||$index<0||$index>49)throw new DomainException('company_payment_batch_invalid');
                ksort($post['invoice'],SORT_NUMERIC);
                $format=(new CompanySettingsStorage($ci->db,$p))->get($i,'dateformat');$items=[];$notice=!isset($post['do_not_send_invoice_payment_recorded']);
                foreach($post['invoice']as$row){if(!is_array($row)||array_diff(array_keys($row),['invoiceid','date','paymentmode','transactionid','amount','native_revision','mode_revision','note']))throw new DomainException('company_payment_batch_invalid');if(($row['amount']??'')==='')continue;
                    $items[]=['invoice_id'=>IssuerPolicy::id($row['invoiceid']??null),'input'=>['amount'=>$row['amount'],'date'=>NativeCompanyInvoiceFormInput::date($row['date']??null,$format),'reference'=>$row['transactionid']??'','note'=>$row['note']??''],'mode'=>$row['paymentmode']??null,'mode_revision'=>$row['mode_revision']??null,'invoice_revision'=>$row['native_revision']??null,'notice'=>$notice];
                }
                $ids=(new \app\services\InvoicePaymentWriter($ci->db))->recordCompanyManualBatch($i,$a,$items,$post['request_key']);$queued=0;$pending=0;
                if($notice){require_once __DIR__.'/CompanyInvoiceDelivery.php';$ci->load->library('encryption');$delivery=new CompanyInvoiceDelivery($ci->db,$p);
                    foreach($items as$n=>$item){try{$contacts=$delivery->state($i,$a,$item['invoice_id']);$revision=(new CompanyInvoices($ci->db,$p))->revision($i,$a,$item['invoice_id']);if(!$contacts['recipients']||$contacts['recipients_truncated'])$pending++;foreach($contacts['recipients']as$contact){$delivery->queuePaymentNotice($i,$a,$item['invoice_id'],$contact['id'],$revision,hash('sha256','payment-batch:'.$post['request_key'].':'.$n),$ci->config->base_url(),fn($v)=>$ci->encryption->decrypt($v));$queued++;}}catch(Throwable $error){$pending++;}}
                }
                set_alert($pending?'warning':'success',count($ids).' pagamentos registrados. '.$queued.' avisos enfileirados.'.($pending?' Há avisos pendentes de revisão.':'').' Nenhum envio externo foi confirmado.');redirect(CompanyRequestScope::adminUrl($ci,'payments'),'location',303);return;
            }
            $data=$service->read($i,$a,$id);
            if($action==='pdf'){
                if(!in_array($verb,['GET','HEAD'],true))throw new DomainException('native_payment_method_denied');$store=new CompanySettingsStorage($ci->db,$p);$format=$store->exists($i,'pdf_format_payment')?$store->get($i,'pdf_format_payment'):'A4-PORTRAIT';$bytes=CompanyFinancialDocumentPdf::payment($data,$format);
                if($service->read($i,$a,$id)!==$data||($store->exists($i,'pdf_format_payment')?$store->get($i,'pdf_format_payment'):'A4-PORTRAIT')!==$format)throw new DomainException('native_payment_stale');
                $ci->output->set_header('X-Content-Type-Options: nosniff')->set_header('Content-Disposition: '.($ci->input->get('output_type',false)==='I'||$ci->input->get('print',false)?'inline':'attachment').'; filename="pagamento-'.(int)$id.'.pdf"')->set_content_type('application/pdf')->set_output($bytes);return;
            }
            if($action==='delete'){
                $post=self::post($ci);(new NativeCompanyInvoicePaymentReversal($ci->db,$p))->submit($i,$a,$id,$data['invoice']['invoice_id'],$post);redirect(CompanyRequestScope::adminUrl($ci,'payments/payment/'.(int)$id),'location',303);return;
            }
            if($action==='payment'){
                if($verb==='POST'){$post=self::post($ci);if(array_diff(array_keys($post),['note','revision','request_key'])||!is_string($post['note']??null)||!is_string($post['revision']??null)||!is_string($post['request_key']??null))throw new DomainException('native_payment_invalid');$service->saveNote($i,$a,$id,$post['revision'],$post['note'],$post['request_key']);redirect(CompanyRequestScope::adminUrl($ci,'payments/payment/'.(int)$id),'location',303);return;}
                if(!in_array($verb,['GET','HEAD'],true))throw new DomainException('native_payment_method_denied');$caps=[];foreach(['edit','delete']as$cap)$caps[$cap]=$acl->can($i,$a,'payments',$cap);
                $record=$data['payment'];$doc=$data['invoice'];$payment=(object)($record+['paymentid'=>(int)$id,'invoiceid'=>$doc['invoice_id'],'name'=>$record['paymentmethod']?:$record['paymentmode']]);$payment->amount=$record['effective_amount'];$payment->note=$data['note'];$payment->invoice=(object)['id'=>$doc['invoice_id'],'clientid'=>$doc['recipient']['client_id'],'currency_name'=>$doc['currency'],'date'=>$doc['date'],'total'=>$doc['totals']['total'],'status'=>$doc['status']];
                $html=$ci->load->view('admin/payments/payment',['native_payment'=>$data,'native_payment_caps'=>$caps,'payment'=>$payment,'payment_modes'=>[],'title'=>'Pagamento #'.(int)$id],true);if($service->read($i,$a,$id)!==$data)throw new DomainException('native_payment_stale');$ci->output->set_output($html);return;
            }
            throw new DomainException('native_payment_invalid');
        }catch(Throwable $error){$reason=$error->getMessage();$status=$error instanceof DomainException?(preg_match('/stale|conflict|exceeds|balance/',$reason)?409:(preg_match('/denied|csrf|access/',$reason)?403:422)):503;show_error('Não foi possível confirmar este pagamento, sua revisão ou suas permissões.',$status);}
    }
}
