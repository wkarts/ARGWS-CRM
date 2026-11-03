<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyInvoiceDrafts.php';
require_once __DIR__.'/NativeCompanyInvoiceIdentity.php';
require_once __DIR__.'/NativeCompanyCustomFields.php';
require_once __DIR__.'/NativeCompanyResourceTags.php';
require_once __DIR__.'/NativeCompanyInvoiceDraftLines.php';

/** Copies native metadata into a private draft under the draft writer's transaction. */
final class NativeCompanyInvoiceDraftCopy
{
    public function __construct(private $db,private string $prefix){}
    private function recurringRows(int $issuer,int $invoice):array
    {
        if(!$this->db->table_exists($this->prefix.'company_invoice_schedule_modes'))return [];
        $rows=IssuerStorage::query($this->db,'SELECT s.*,m.mode,m.principal_invoice_id,m.mode_hash FROM '.$this->prefix.'company_invoice_schedule_modes m JOIN '.$this->prefix.'company_invoice_schedules s ON s.id=m.schedule_id AND s.issuer_id=m.issuer_id WHERE m.issuer_id=? AND m.principal_invoice_id=? ORDER BY s.id',[$issuer,$invoice])->result_array();
        return array_map(static fn($row)=>['id'=>(int)$row['id'],'fingerprint'=>hash('sha256',json_encode($row,JSON_THROW_ON_ERROR))],$rows);
    }
    private function capture(int $issuer,int $actor,int $invoice,array $plans=[]):array
    {
        $identity=new NativeCompanyInvoiceIdentity($this->db,$this->prefix);$owner=$identity->resolve($issuer,$actor,$invoice);
        if($owner['lineage']!=='named_company')throw new DomainException('native_invoice_copy_lineage_unavailable');
        $native=IssuerStorage::query($this->db,'SELECT recurring FROM '.$this->prefix.'invoices WHERE id=? AND issuer_id=?',[$invoice,$issuer])->row_array();
        if(!$native)throw new DomainException('native_invoice_copy_lineage_unavailable');$current=$this->recurringRows($issuer,$invoice);
        if((int)$native['recurring']!==0&&!$current)throw new DomainException('native_invoice_copy_recurring_required');
        if($current!==array_map(static fn($plan)=>['id'=>$plan['id'],'fingerprint'=>$plan['fingerprint']],$plans))throw new DomainException('native_invoice_copy_stale');
        $tags=(new NativeCompanyResourceTags($this->db,$this->prefix))->read($issuer,$actor,'invoice',$invoice);
        $fields=new NativeCompanyCustomFields($this->db,$this->prefix);$invoiceFields=$fields->values($issuer,$actor,'invoice',$invoice);
        $lines=IssuerStorage::query($this->db,"SELECT id FROM {$this->prefix}itemable WHERE rel_type='invoice' AND rel_id=? ORDER BY item_order,id",[$invoice])->result_array();
        if(!$lines||count($lines)>100)throw new DomainException('native_invoice_copy_invalid');$itemFields=[];
        foreach($lines as$line)$itemFields[]=['source_id'=>(int)$line['id'],'values'=>$fields->values($issuer,$actor,'items',(int)$line['id'])];
        if($identity->resolve($issuer,$actor,$invoice)!==$owner)throw new DomainException('native_invoice_copy_stale');
        return ['tags'=>array_column($tags['tags'],'name'),'tags_revision'=>$tags['revision'],'invoice_fields'=>$invoiceFields,'item_fields'=>$itemFields,'recurring'=>$plans];
    }
    public function copy(int $issuer,int $actor,$source,array $review,string $key):string
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$source=IssuerPolicy::id($source);
        $drafts=new CompanyInvoiceDrafts($this->db,$this->prefix);$fields=new NativeCompanyCustomFields($this->db,$this->prefix);$tags=new NativeCompanyResourceTags($this->db,$this->prefix);
        // Replay uses the immutable source snapshot, not today's editable metadata.
        $existing=IssuerStorage::query($this->db,'SELECT source_json,source_hash FROM '.$this->prefix.'company_invoice_drafts WHERE origin_key=? AND issuer_id=? AND created_by=?',[$key,$issuer,$actor])->row_array();
        if($existing){(new NativeCompanyInvoiceIdentity($this->db,$this->prefix))->resolve($issuer,$actor,$source);if(!is_string($existing['source_json'])||!is_string($existing['source_hash'])||!hash_equals($existing['source_hash'],hash('sha256',$existing['source_json'])))throw new DomainException('native_invoice_copy_stale');$frozen=json_decode($existing['source_json'],true,64,JSON_THROW_ON_ERROR);if(!is_array($frozen)||($frozen['invoice']['id']??null)!==$source||!is_array($frozen['native_metadata']??null))throw new DomainException('native_invoice_copy_stale');$metadata=$frozen['native_metadata'];}
        else {
            $snapshot=$this->recurringRows($issuer,$source);$plans=[];
            if($snapshot){require_once __DIR__.'/CompanyInvoiceRecurring.php';$recurring=new CompanyInvoiceRecurring($this->db,$this->prefix);
                foreach($snapshot as$row){$document=$recurring->document($issuer,$actor,$row['id']);if((int)($document['mode']['principal_invoice_id']??0)!==$source)throw new DomainException('native_invoice_copy_stale');$plans[]=$row+['mode'=>$document['mode']['mode'],'config'=>$document['config']];}
            }
            $metadata=$this->capture($issuer,$actor,$source,$plans);
        }
        $started=false;
        $guard=function(bool $replay)use($issuer,$actor,$source,$key,$drafts,$fields,$tags,$metadata,&$started):void{
            if(!$replay&&$this->capture($issuer,$actor,$source,$metadata['recurring'])!==$metadata)throw new DomainException('native_invoice_copy_stale');
            $event=IssuerStorage::query($this->db,'SELECT draft_id FROM '.$this->prefix.'company_invoice_draft_events WHERE request_key=? AND issuer_id=? AND actor_id=?',[$key,$issuer,$actor])->row_array();
            if(!$event){$started=true;return;}if($replay)return;if(!$started)throw new RuntimeException('issuer_invoice_draft_unconfirmed');
            $draft=(int)$event['draft_id'];$drafts->assertCreatedInOwned($this->db,$this->prefix,$issuer,$actor,'company_invoice_draft',$draft);
            $fields->saveCreatedInOwnedTransaction($issuer,$actor,'invoice',$draft,$metadata['invoice_fields'],$drafts);
            $lines=array_values(array_filter((new NativeCompanyInvoiceDraftLines($this->db,$this->prefix))->assertInOwned($draft),static fn($line)=>$line['draft_revision']===1));
            if(count($lines)!==count($metadata['item_fields']))throw new RuntimeException('issuer_invoice_draft_unconfirmed');
            foreach($lines as$position=>$line)$fields->saveCreatedInOwnedTransaction($issuer,$actor,'items',$line['id'],$metadata['item_fields'][$position]['values'],$drafts);
            $tags->createDraftTagsInOwnedTransaction($issuer,$actor,$draft,$metadata['tags'],$drafts);
            if($this->capture($issuer,$actor,$source,$metadata['recurring'])!==$metadata)throw new DomainException('native_invoice_copy_stale');
        };
        $tables=array_values(array_unique(array_merge($fields->tableNames(),NativeCompanyResourceTags::tableNames(),['invoices','itemable','item_tax'],NativeCompanyInvoiceDraftLines::installed($this->db,$this->prefix)?[NativeCompanyInvoiceDraftLinesSchema::TABLE,NativeCompanyInvoiceDraftLinesSchema::TRANSFERS]:[],$this->db->table_exists($this->prefix.'company_invoice_schedule_modes')?['company_invoice_schedule_modes','company_invoice_schedules']:[])));
        if(!NativeCompanyInvoiceDraftLines::installed($this->db,$this->prefix))throw new DomainException('native_invoice_copy_schema_required');
        $draft=$drafts->copyInvoice($issuer,$actor,$source,$review,$key,$metadata,$guard,$tables);
        return NativeCompanyResourceId::encode('company_invoice_draft',$draft);
    }
}
