<?php
declare(strict_types=1);

require_once __DIR__.'/NativeCompanyInvoiceIdentity.php';
require_once __DIR__.'/NativeCompanyResourceId.php';
require_once __DIR__.'/CompanyInvoices.php';

/** Read-only building block for the original invoice preview; it does not admit the route. */
final class NativeCompanyInvoicePreviewData
{
    public function __construct(private $db,private string $prefix) {}

    /** Escape captured issuer and recipient identities for the native preview. */
    public static function addressHtml(array $identity,bool $recipient=false,bool $shipping=false):string
    {
        $address=$recipient?($identity[$shipping?'shipping':'billing']??[]):$identity;
        if(!is_array($address))throw new DomainException('native_invoice_preview_invalid');
        $values=$recipient?[$identity['name']??'',$identity['tax_id']??'']:[$identity['legal_name']??'',$identity['tax_id']??''];
        foreach(['address','street','city','state','postcode','zip','country_code','country','phone','email']as$field)if(isset($address[$field]))$values[]=$address[$field];
        $out=[];foreach($values as$value){if(!is_string($value)&&!is_int($value))throw new DomainException('native_invoice_preview_invalid');$value=trim((string)$value);if($value!=='')$out[]=htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
        return implode('<br>',$out);
    }

    /** Native files retain their canonical IDs and existing actions, scoped to the invoice. */
    public function attachments(int $issuer,int $actor,$invoice):array
    {
        require_once __DIR__.'/NativeCompanyInvoiceFiles.php';
        $reference=NativeCompanyResourceId::decode('invoice',$invoice);
        if($reference['source_kind']!=='invoice')throw new DomainException('native_invoice_preview_lineage_unavailable');
        $identity=new NativeCompanyInvoiceIdentity($this->db,$this->prefix);$before=$identity->resolve($issuer,$actor,$reference['source_id']);
        $files=(new NativeCompanyInvoiceFiles($this->db,$this->prefix))->page($issuer,$actor,$before['invoice_id']);
        if($identity->resolve($issuer,$actor,$before['invoice_id'])!==$before)throw new DomainException('native_invoice_preview_changed');
        return $files;
    }

    /** Original preview badge counts only pending reminders assigned to this actor. */
    public function reminderBadge(int $issuer,int $actor,$invoice):int
    {
        require_once __DIR__.'/NativeCompanyAuxiliaryUi.php';
        $identity=new NativeCompanyInvoiceIdentity($this->db,$this->prefix);$before=$identity->resolve($issuer,$actor,$invoice);
        $ui=new NativeCompanyAuxiliaryUi($this->db,$this->prefix,$issuer,$actor);$count=0;$start=0;
        do{$page=$ui->reminderTable('invoice',$before['invoice_id'],$start,100,'',true);foreach($page['rows']as$row)if((int)$row['staff']===$actor)$count++;$start+=count($page['rows']);
            if($start>100000||($start<$page['filtered']&&count($page['rows'])===0))throw new DomainException('native_invoice_preview_changed');
        }while($start<$page['filtered']);
        if($identity->resolve($issuer,$actor,$before['invoice_id'])!==$before)throw new DomainException('native_invoice_preview_changed');return$count;
    }
    public function notes(int $issuer,int $actor,$invoice):array
    {
        require_once __DIR__.'/NativeCompanyAuxiliaryUi.php';$identity=new NativeCompanyInvoiceIdentity($this->db,$this->prefix);$before=$identity->resolve($issuer,$actor,$invoice);
        $rows=(new NativeCompanyAuxiliaryUi($this->db,$this->prefix,$issuer,$actor))->notes('invoice',$before['invoice_id']);
        if($identity->resolve($issuer,$actor,$before['invoice_id'])!==$before)throw new DomainException('native_invoice_preview_changed');return$rows;
    }
    public function metadata(int $issuer,int $actor,$invoice):array
    {
        require_once __DIR__.'/NativeCompanyInvoiceCustomColumns.php';require_once __DIR__.'/NativeCompanyResourceTags.php';
        $identity=new NativeCompanyInvoiceIdentity($this->db,$this->prefix);$before=$identity->resolve($issuer,$actor,$invoice);
        $tags=(new NativeCompanyResourceTags($this->db,$this->prefix))->read($issuer,$actor,'invoice',$before['invoice_id']);
        $columns=new NativeCompanyInvoiceCustomColumns($this->db,$this->prefix,$issuer,$actor);$definitions=$columns->definitions();$values=$columns->values($before['invoice_id']);$fields=[];
        foreach($definitions as$field)if((int)($field['show_on_pdf']??0)===1){$value=$values[(string)$field['id']]??'';if(!is_string($value))throw new DomainException('native_invoice_preview_invalid');if($value!=='')$fields[]=['name'=>$field['name'],'value'=>$value];}
        $columns->recheck();if($identity->resolve($issuer,$actor,$before['invoice_id'])!==$before)throw new DomainException('native_invoice_preview_changed');
        return ['tags'=>array_column($tags['tags'],'name'),'tags_revision'=>$tags['revision'],'pdf_fields'=>$fields];
    }
    public function recurring(int $issuer,int $actor,$invoice):array
    {
        require_once __DIR__.'/CompanyInvoiceRecurring.php';
        $identity=new NativeCompanyInvoiceIdentity($this->db,$this->prefix);$before=$identity->resolve($issuer,$actor,$invoice);
        if($before['lineage']!=='named_company')throw new DomainException('native_invoice_preview_lineage_unavailable');
        $id=$before['invoice_id'];$query=fn()=>IssuerStorage::query($this->db,'SELECT DISTINCT schedule_id FROM '.$this->prefix.'company_invoice_schedule_modes WHERE issuer_id=? AND principal_invoice_id=? UNION SELECT DISTINCT schedule_id FROM '.$this->prefix.'company_invoice_schedule_runs WHERE issuer_id=? AND invoice_id=? ORDER BY schedule_id',[$issuer,$id,$issuer,$id])->result_array();
        $sources=$query();$service=new CompanyInvoiceRecurring($this->db,$this->prefix);$result=[];
        foreach($sources as$source){$schedule=$service->document($issuer,$actor,(int)$source['schedule_id']);$isPrincipal=(int)($schedule['mode']['principal_invoice_id']??0)===$id;$children=[];$current=false;
            foreach($schedule['runs']as$run){$child=(int)($run['canonical_invoice_id']??0);if($child===$id)$current=true;if($isPrincipal&&$child>0){$identity->resolve($issuer,$actor,$child);$children[]=['invoice_id'=>$child,'run_date'=>$run['run_date'],'state'=>$run['state']];}}
            if(!$isPrincipal&&!$current)throw new DomainException('native_invoice_preview_changed');
            $result[]=['schedule_id'=>(int)$source['schedule_id'],'principal'=>$isPrincipal,'state'=>$schedule['state'],'next_date'=>$schedule['next_date'],'completed_cycles'=>(int)$schedule['completed_cycles'],'children'=>$children];
        }
        if($query()!==$sources||$identity->resolve($issuer,$actor,$id)!==$before)throw new DomainException('native_invoice_preview_changed');return$result;
    }

    public function read(int $issuer,int $actor,$invoice):array
    {
        $reference=NativeCompanyResourceId::decode('invoice',$invoice);
        if($reference['source_kind']!=='invoice')throw new DomainException('native_invoice_preview_lineage_unavailable');
        $identity=new NativeCompanyInvoiceIdentity($this->db,$this->prefix);
        $owner=$identity->resolve($issuer,$actor,$reference['source_id']);
        // Legacy NULL-owner invoices and private drafts need their own complete adapters.
        if($owner['lineage']!=='named_company')throw new DomainException('native_invoice_preview_lineage_unavailable');
        $service=new CompanyInvoices($this->db,$this->prefix);
        $id=$owner['invoice_id'];
        $revision=$service->revision($issuer,$actor,$id);
        $document=$service->document($issuer,$actor,$id)->data();
        $lines=$service->previewItems($issuer,$actor,$id);
        $balance=$service->position($issuer,$actor,$id);
        $payments=$service->payments($issuer,$actor,$id);
        // Different service transactions do not form a single database snapshot. Refuse a
        // mixed result when any independently read portion changed while assembling it.
        if($identity->resolve($issuer,$actor,$id)!==$owner
            ||$service->revision($issuer,$actor,$id)!==$revision
            ||$service->document($issuer,$actor,$id)->data()!==$document
            ||$service->previewItems($issuer,$actor,$id)!==$lines
            ||$service->position($issuer,$actor,$id)!==$balance
            ||$service->payments($issuer,$actor,$id)!==$payments)
            throw new DomainException('native_invoice_preview_changed');
        return ['identity'=>$owner,'revision'=>$revision,'document'=>$document,'lines'=>$lines,'balance'=>$balance,'payments'=>$payments];
    }

    /**
     * Compose the data expected by the original admin preview without loading any
     * global invoice, client, payment or credit model.  The returned transaction
     * is still rendered by App_items_table; its typed native presenter disables
     * the legacy hooks and revalidates every persisted line.
     */
    public function presentation(int $issuer,int $actor,$invoice):array
    {
        $data=$this->read($issuer,$actor,$invoice);$id=$data['document']['invoice_id'];
        require_once __DIR__.'/NativeCompanyInvoiceItemsPresentation.php';
        require_once __DIR__.'/NativeCompanyInvoiceCommunicationState.php';
        $items=NativeCompanyInvoiceItemsPresentation::load($this->db,$this->prefix,$issuer,$actor,$id);
        $transaction=$items->transaction();$document=$data['document'];$position=$data['balance'];
        $adjustments=(new CompanyInvoices($this->db,$this->prefix))->adjustments($issuer,$actor,$id);
        $communication=(new NativeCompanyInvoiceCommunicationState($this->db,$this->prefix))->state($issuer,$actor,$id);
        $payments=[];foreach($data['payments']as$row){
            $method=trim((string)($row['paymentmethod']??''));
            if($method==='')$method=trim((string)($row['paymentmode']??''));
            $payments[]=[
            'paymentid'=>(int)$row['id'],'invoiceid'=>$id,
            'name'=>$method,
            'paymentmethod'=>'','transactionid'=>(string)$row['transactionid'],'date'=>$row['date'],
            'amount'=>$row['effective_amount'],'native_original_amount'=>$row['original_amount'],
            'native_reversed_amount'=>$row['reversed_amount'],
            ];
        }
        foreach([
            'clientid'=>(int)$document['recipient']['client_id'],'currency_name'=>$document['currency'],
            'duedate'=>$document['due_date'],'subtotal'=>$document['totals']['subtotal'],
            'discount_total'=>$document['totals']['discount_total'],'adjustment'=>$document['totals']['adjustment'],
            'total'=>$document['totals']['total'],'total_left_to_pay'=>$position['receivable'],
            'clientnote'=>$document['note'],'terms'=>$document['terms'],'payments'=>$payments,
            'attachments'=>$this->attachments($issuer,$actor,$id),'include_shipping'=>$document['recipient']['shipping']===null?0:1,
            'show_shipping_on_invoice'=>$document['recipient']['shipping']===null?0:1,
            'discount_percent'=>$adjustments['discount_mode']==='percent'?$adjustments['discount_value']:'0.00',
            'discount_type'=>$adjustments['discount_type'],'sent'=>$communication['sent'],'datesend'=>$communication['datesend'],
            'cancel_overdue_reminders'=>$communication['cancel_overdue_reminders'],'recurring'=>0,'is_recurring_from'=>null,
            'project_id'=>0,'project_data'=>null,'sale_agent'=>0,'scheduled_email'=>null,'addedfrom'=>0,'hash'=>'',
        ]as$key=>$value)$transaction->$key=$value;
        // A presentation assembled over multiple bounded reads must still end on
        // the same immutable invoice revision and communication state.
        if(!hash_equals($data['revision'],(new CompanyInvoices($this->db,$this->prefix))->revision($issuer,$actor,$id))
            ||(new NativeCompanyInvoiceCommunicationState($this->db,$this->prefix))->state($issuer,$actor,$id)!==$communication)
            throw new DomainException('native_invoice_preview_changed');
        $credits=[];
        if($this->db->table_exists($this->prefix.'company_credit_entries')){
            require_once __DIR__.'/CompanyCredits.php';
            try{$credits=(new CompanyCredits($this->db,$this->prefix))->appliedToInvoice($issuer,$actor,$id);}
            catch(DomainException$error){if($error->getMessage()!=='company_credit_access_denied')throw$error;}
        }
        return ['invoice'=>$transaction,'native_invoice_preview_data'=>$data,
            'native_invoice_metadata'=>$this->metadata($issuer,$actor,$id),
            'native_invoice_communication'=>$communication,
            'native_invoice_reminder_badge'=>$this->reminderBadge($issuer,$actor,$id),
            'native_invoice_recurring'=>$this->recurring($issuer,$actor,$id),
            'applied_credits'=>$credits,'totalNotes'=>count($this->notes($issuer,$actor,$id))];
    }
}
