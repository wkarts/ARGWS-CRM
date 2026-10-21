<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyPostRefundAsaasManualSource.php';require_once __DIR__.'/CompanyPostRefundManualReversalEntries.php';require_once __DIR__.'/CompanyInvoices.php';require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyRefunds.php';
/** Explicit review outside TX followed by an independent newly received manual cash declaration. */
final class CompanyPostRefundManual extends \Asaas\CompanyRefundStorage
{
    public function tables():array{return array_values(array_unique(array_merge(parent::tables(),(new CompanyAsaasRefundPostings($this->db,$this->p))->tables(true),['company_post_refund_manual_receipts','company_invoice_requests','itemable','item_tax'],CompanyPostRefundManualReversalEntries::tables($this->db,$this->p))));}
    public function page($issuer,$actor,$invoice,bool$forReversal=false):array
    {
        $i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);$id=IssuerPolicy::id($invoice);if(!(new IssuerResourceAccess($this->db,$this->p))->can($i,$a,'payments','create'))$forReversal=true;CompanyPostRefundManualSchema::ensure($this->db,$this->p,false);
        return$this->run($i,$a,fn()=>$this->inOwned($i,$a,$id,$forReversal));
    }
    public function inOwned(int$i,int$a,int$id,bool$forReversal=false):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$this->auth($i,$a,true);
            $version=$this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]['installed_version']??'0';if(version_compare($version,'1.5.3','<'))throw new DomainException('company_post_refund_module_required');
            if(!(new IssuerResourceAccess($this->db,$this->p))->can($i,$a,'payments',$forReversal?'delete':'create'))throw new DomainException('company_payment_access_denied');
            $doc=$this->rows('SELECT i.total,i.status,i.currency,d.formatted_number number FROM '.$this->p.'invoices i JOIN '.$this->p.'issuers_invoice_documents d ON d.invoice_id=i.id AND d.issuer_id=i.issuer_id WHERE i.issuer_id=? AND i.id=? FOR UPDATE',[$i,$id])[0]??null;if(!$doc)throw new DomainException('company_post_refund_access_denied');
            $postings=(new CompanyAsaasRefundPostings($this->db,$this->p))->forInvoiceInOwned($i,$a,$id);if(count($postings)!==1)throw new DomainException('company_post_refund_full_posting_required');$originalPayment=(int)array_key_first($postings);$d=$postings[$originalPayment];$r=$this->requestRow($i,$d['refund_request_key']);[$s,$account]=$this->assertIntentCurrent($i,$a,$r,false);
            $pos=(new CompanyInvoiceBalance($this->db,$this->p))->inOwned($i,$a,$id);
            $out=['invoice_id'=>$id,'invoice_number'=>$doc['number'],'status'=>(int)$doc['status'],'total'=>$pos['total'],'currency'=>(new CompanyCurrencyUnits($this->db,$this->p))->resolveRead($doc['currency'])['iso_code'],'revision'=>(new CompanyInvoices($this->db,$this->p))->revision($i,$a,$id),'refund_request'=>$d['refund_request_key'],'origin_id'=>$s['origin_id'],'account_id'=>$s['account_id'],'environment'=>$s['environment'],'remote_id'=>$s['remote_id'],'original_payment'=>$originalPayment,'declared_refund'=>$d['returned_paid'],'observation_id'=>null,'observed_at'=>null,'remote_status'=>null,'can_confirm'=>false,'manual_recorded'=>false,'new_payment_id'=>null,'manual_reference'=>null,'manual_request_key'=>null,'manual_reversal'=>null,'can_reverse'=>false];
            if($r['latest_observation']!==null){$o=$this->rows('SELECT * FROM '.$this->p.'asaas_company_refund_observations WHERE id=? AND issuer_id=? AND request_key=?',[$r['latest_observation'],$i,$r['request_key']])[0]??null;if(!$o)throw new RuntimeException('issuer_post_refund_manual_unconfirmed');$safe=json_decode($o['result_json'],true,32,JSON_THROW_ON_ERROR);if($o['source_hash']!==$r['source_hash']||$o['account_revision']!==$r['account_revision']||!hash_equals($o['evidence_hash'],\Asaas\CompanyRefundContract::hash($safe)))throw new RuntimeException('issuer_post_refund_manual_unconfirmed');
                $out['observation_id']=(int)$o['id'];$out['observed_at']=$o['created_at'];$out['remote_status']=$safe['status'];$out['can_confirm']=$o['operation']==='query'&&$safe['status']==='REFUNDED'&&$o['created_at']>=gmdate('Y-m-d H:i:s',time()-300)&&$o['created_at']<=gmdate('Y-m-d H:i:s')&&in_array((int)$doc['status'],[1,4],true)&&$pos['received']==='0.00'&&$pos['applied_credit']==='0.00'&&$pos['noncash_reductions']==='0.00';
                if($this->rows('SELECT created_at FROM '.$this->p.'asaas_company_events WHERE account_row=? AND remote_id=? AND created_at>=? FOR UPDATE',[$account['id'],$s['remote_id'],$o['created_at']]))$out['can_confirm']=false;
            }
            $row=$this->rows('SELECT * FROM '.$this->p.'company_post_refund_manual_receipts WHERE issuer_id=? AND invoice_id=?',[$i,$id])[0]??null;
            if($row){$x=json_decode($row['input_json'],true,32,JSON_THROW_ON_ERROR);$receipt=$x['receipt']??[];$native=$this->rows('SELECT n.*,c.request_key,c.input_hash,c.actor_id FROM '.$this->p.'invoicepaymentrecords n JOIN '.$this->p.'company_payment_receipts c ON c.payment_id=n.id AND c.invoice_id=n.invoiceid AND c.issuer_id=? WHERE n.id=? AND n.invoiceid=?',[$i,$row['payment_id'],$id])[0]??null;
                $payload=['invoiceid'=>$id,'amount'=>$d['returned_paid'],'paymentmode'=>'company_manual','paymentmethod'=>'Recebimento manual','date'=>$receipt['date']??null,'transactionid'=>$receipt['reference']??null,'note'=>trim($receipt['note']??'')];$meta=\Asaas\CompanyRefundContract::encode(['invoice_id'=>$id,'request_key'=>$row['request_key'],'input_hash'=>$row['input_hash'],'posting_key'=>$row['posting_key'],'payment_id'=>(int)$row['payment_id']]);
                if(!$native||$row['external_reference_key']!==CompanyPostRefundAsaasManualSource::referenceKey($i,$receipt['reference']??'')||(int)$row['original_payment_id']!==$originalPayment||$row['posting_key']!==CompanyAsaasRefundPostings::key($i,$originalPayment,$s['receipt_key'])||(int)($x['actor_id']??0)!==(int)$row['created_by']||(int)($x['issuer_id']??0)!==$i||(int)($x['invoice_id']??0)!==$id||($x['refund_request']??null)!==$d['refund_request_key']||($x['request_key']??null)!==$row['request_key']||($receipt['amount']??null)!==$d['returned_paid']||!hash_equals($row['input_hash'],hash('sha256',$row['input_json']))||!hash_equals($row['record_hash'],CompanyPostRefundAsaasManualSource::receiptHeader($row))||$native['request_key']!==$row['request_key']||(int)$native['actor_id']!==(int)$row['created_by']||!hash_equals($native['input_hash'],hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR)))||count($this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=?',[$i,$row['created_by'],$s['origin_id'],'company_post_refund_manual_recorded',$meta]))!==1)throw new DomainException('company_post_refund_manual_conflict');
                $reversal=CompanyPostRefundManualReversalEntries::readInOwned($this->db,$this->p,$i,$id,$row,$native);if($reversal!==null)$native['amount']=$payload['amount'];
                foreach($payload as$f=>$v)if($f==='invoiceid'?(int)$native[$f]!==$v:($f==='amount'?CompanyInvoiceAmounts::decimal(CompanyInvoiceAmounts::minor((string)$native[$f]))!==$v:$native[$f]!==$v))throw new DomainException('company_post_refund_manual_conflict');
                $out['manual_request_key']=$row['request_key'];$out['manual_reversal']=$reversal;$out['can_reverse']=$this->db->table_exists($this->p.'company_post_refund_manual_reversals')&&$reversal===null&&in_array((int)$doc['status'],[2],true)&&$pos['received']===$d['returned_paid']&&$pos['applied_credit']==='0.00'&&$pos['noncash_reductions']==='0.00'&&(new IssuerResourceAccess($this->db,$this->p))->can($i,$a,'payments','delete');$out['manual_recorded']=true;$out['new_payment_id']=(int)$row['payment_id'];$out['manual_reference']=$receipt['reference'];$out['can_confirm']=false;
            }
            $this->auth($i,$a,true);return$out;
    }
    public function review($issuer,$actor,$invoice,callable$decrypt,?callable$transport=null):array{$v=$this->page($issuer,$actor,$invoice);if($v['manual_recorded'])return$v;(new \Asaas\CompanyRefunds($this->db,$this->p))->refresh($issuer,$actor,$v['refund_request'],$decrypt,$transport);return$this->page($issuer,$actor,$invoice);}
    public function record($issuer,$actor,$invoice,string$request,$observation,string$revision,string$key,array$receipt,string$evidenceHash,string$reason,bool$confirmed):int
    {
        $i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);$id=IssuerPolicy::id($invoice);$o=IssuerPolicy::id($observation);$source=new CompanyPostRefundAsaasManualSource($this->db,$this->p,$i,$a,$id,$request,$o,$revision,$key,$receipt,$evidenceHash,$reason,$confirmed);
        return(new \app\services\InvoicePaymentWriter($this->db))->recordCompanyManualAfterAsaasFullRefund($i,$a,$source);
    }
    public function reverse($issuer,$actor,$invoice,string$manualKey,string$revision,string$key,array$entry,string$evidence,bool$confirmed):string
    {
        require_once __DIR__.'/CompanyPostRefundManualReversalSource.php';
        $source=new CompanyPostRefundManualReversalSource($this->db,$this->p,IssuerPolicy::id($issuer),IssuerPolicy::id($actor),IssuerPolicy::id($invoice),$manualKey,$revision,$key,$entry,$evidence,$confirmed);
        return(new \app\services\InvoicePaymentWriter($this->db))->reverseCompanyManualAfterAsaasFullRefund($issuer,$actor,$source);
    }

}
