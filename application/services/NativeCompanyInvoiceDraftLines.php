<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyInvoiceDraftLinesSchema.php';
require_once __DIR__.'/CompanyInvoiceDraftsInput.php';
require_once __DIR__.'/CompanyInvoices.php';
/** Derived identity for every proven draft revision. Original events remain authoritative. */
final class NativeCompanyInvoiceDraftLines
{
    public function __construct(private $db,private string $p){NativeCompanyInvoiceDraftLinesSchema::definitions($p);}
    public static function tables():array{return ['company_invoice_draft_lines','company_invoice_draft_line_transfers','company_invoice_drafts','company_invoice_draft_events','issuers_audit','company_invoice_requests','invoices','itemable','item_tax'];}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private static function fail():never{throw new RuntimeException('issuer_invoice_draft_lines_unconfirmed');}
    public static function installed($db,string $p):bool
    {
        NativeCompanyInvoiceDraftLinesSchema::definitions($p);$c=$db->pdo??$db->conn_id??null;$sqlite=$c instanceof PDO&&$c->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        $rows=IssuerStorage::query($db,$sqlite?"SELECT name FROM sqlite_master WHERE type='table' AND name IN (?,?)":'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (?,?)',[$p.NativeCompanyInvoiceDraftLinesSchema::TABLE,$p.NativeCompanyInvoiceDraftLinesSchema::TRANSFERS])->result_array();
        if(count($rows)!==0&&count($rows)!==2)self::fail();return count($rows)===2;
    }
    public static function metadataTables($db,string $p):array
    {
        NativeCompanyInvoiceDraftLinesSchema::definitions($p);$c=$db->pdo??$db->conn_id??null;$sqlite=$c instanceof PDO&&$c->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        $rows=IssuerStorage::query($db,$sqlite?"SELECT name FROM sqlite_master WHERE type='table' AND name IN (?,?)":'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (?,?)',[$p.'company_custom_fields',$p.'company_custom_field_values'])->result_array();
        if(count($rows)===0)return[];if(count($rows)!==2)self::fail();require_once __DIR__.'/NativeCompanyCustomFields.php';return(new NativeCompanyCustomFields($db,$p))->tableNames();
    }
    /** All revisions require independent audit proof; no fabricated historical source or inferred ownership. */
    private function headers(int $draft):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,self::tables());
        $current=$this->rows('SELECT * FROM '.$this->p.'company_invoice_drafts WHERE id=? FOR UPDATE',[$draft])[0]??null;if(!$current)self::fail();
        foreach(['id','issuer_id','created_by','revision','client_id','currency_id','invoice_id','issue_actor','issue_revision']as$k)$current[$k]=$current[$k]===null?null:(int)$current[$k];
        $events=$this->rows('SELECT * FROM '.$this->p.'company_invoice_draft_events WHERE draft_id=? AND issuer_id=? FOR UPDATE',[$draft,$current['issuer_id']]);$headers=[];
        foreach($events as$event){
            if(!hash_equals(hash('sha256',$event['data_json']),$event['data_hash']))self::fail();$data=CompanyInvoiceDraftsInput::decode($event['data_json']);$header=$data['header']??null;
            if(!is_array($header)||array_keys($data)!==['header']||($header['id']??null)!==$draft||($header['issuer_id']??null)!==$current['issuer_id']||($header['last_event_key']??null)!==$event['request_key']||!is_int($header['revision']??null)||$header['revision']<1||isset($headers[$header['revision']]))self::fail();
            foreach(['payload','context']as$part)if(!hash_equals(hash('sha256',$header[$part.'_json']),$header[$part.'_hash']))self::fail();
            $payload=CompanyInvoiceDraftsInput::decode($header['payload_json']);$context=CompanyInvoiceDraftsInput::decode($header['context_json']);
            if(CompanyInvoiceDraftsInput::normalize($payload)!==$payload||$payload['client_id']!==$header['client_id']||$payload['currency']!==$header['currency_id']||($context['issuer_id']??null)!==$header['issuer_id']||($context['recipient']['client_id']??null)!==$header['client_id'])self::fail();
            $metadata=CompanyInvoiceDraftsInput::json(['request_key'=>$event['request_key'],'operation'=>$event['operation'],'input_hash'=>$event['input_hash'],'data_hash'=>$event['data_hash']]);
            $audit=$this->rows('SELECT issuer_id,actor_id,subject_id,event,metadata_json FROM '.$this->p.'issuers_audit WHERE id=? FOR UPDATE',[$event['audit_id']])[0]??[];
            if((int)($audit['issuer_id']??0)!==$header['issuer_id']||(int)($audit['actor_id']??0)!==(int)$event['actor_id']||(int)($audit['subject_id']??0)!==$draft||($audit['event']??null)!=='company_invoice_draft_'.$event['operation']||($audit['metadata_json']??null)!==$metadata)self::fail();
            $headers[$header['revision']]=$header;
        }
        ksort($headers);if($current['revision']<1||count($headers)!==$current['revision']||array_keys($headers)!==range(1,$current['revision'])||end($headers)!==$current)self::fail();return$headers;
    }
    private function expected(array $headers):array
    {
        $out=[];foreach($headers as$revision=>$header){$payload=CompanyInvoiceDraftsInput::decode($header['payload_json']);foreach($payload['items']as$position=>$line){$json=json_encode($line,JSON_THROW_ON_ERROR);$out[]=['issuer_id'=>$header['issuer_id'],'draft_id'=>$header['id'],'draft_revision'=>$revision,'position'=>$position+1,'event_key'=>$header['last_event_key'],'line_json'=>$json,'line_hash'=>hash('sha256',$json)];}}return$out;
    }
    private static function normalize(array $row):array{foreach(['id','issuer_id','draft_id','draft_revision','position']as$key)$row[$key]=(int)$row[$key];return$row;}
    public function syncInOwned(int $draft):void
    {
        NativeCompanyInvoiceDraftLinesSchema::assertValid($this->db,$this->p);$headers=$this->headers($draft);
        foreach($this->expected($headers)as$expected){$existing=$this->rows('SELECT * FROM '.$this->p.NativeCompanyInvoiceDraftLinesSchema::TABLE.' WHERE issuer_id=? AND draft_id=? AND draft_revision=? AND position=? FOR UPDATE',[$expected['issuer_id'],$draft,$expected['draft_revision'],$expected['position']]);
            if(!$existing){IssuerStorage::query($this->db,'INSERT INTO '.$this->p.NativeCompanyInvoiceDraftLinesSchema::TABLE.' (`'.implode('`,`',array_keys($expected)).'`) VALUES('.implode(',',array_fill(0,count($expected),'?')).')',array_values($expected));if((int)$this->db->insert_id()<1)self::fail();}
            elseif(count($existing)!==1||array_diff_key(self::normalize($existing[0]),['id'=>true])!==$expected)self::fail();
        }
        $this->assertInOwned($draft);if($this->headers($draft)!==$headers)self::fail();
    }
    public function assertInOwned(int $draft):array
    {
        $expected=$this->expected($this->headers($draft));$rows=$this->rows('SELECT * FROM '.$this->p.NativeCompanyInvoiceDraftLinesSchema::TABLE.' WHERE draft_id=? ORDER BY draft_revision,position',[$draft]);
        if(count($rows)!==count($expected))self::fail();$out=[];foreach($rows as$i=>$row){$row=self::normalize($row);if($row['id']<1||array_diff_key($row,['id'=>true])!==$expected[$i])self::fail();$out[]=$row;}return$out;
    }
    /** Source positions refer to the exact canonical payload whose INSERT return IDs the writer attests. */
    public function transferInOwned(int $issuer,int $actor,int $draft,int $revision,int $invoice,CompanyInvoices $writer,string $requestKey):string
    {
        $all=$this->assertInOwned($draft);$lines=array_values(array_filter($all,static fn($r)=>$r['issuer_id']===$issuer&&$r['draft_revision']===$revision));
        $proof=$writer->createdLineIdsInOwned($issuer,$actor,$invoice,$requestKey);
        if(!$lines||count($lines)!==count($proof))self::fail();
        foreach($lines as$offset=>$line){$entry=$proof[$offset];if($line['line_json']!==json_encode($entry['line'],JSON_THROW_ON_ERROR)||!hash_equals($line['line_hash'],$entry['line_hash']))self::fail();
            $metadata=CompanyInvoiceDraftsInput::json(['draft_id'=>$draft,'draft_revision'=>$revision,'draft_line_id'=>$line['id'],'event_key'=>$line['event_key'],'invoice_id'=>$invoice,'itemable_id'=>$entry['itemable_id'],'line_hash'=>$line['line_hash']]);
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(issuer_id,actor_id,subject_id,event,metadata_json,created_at) VALUES(?,?,?,?,?,?)',[$issuer,$actor,$draft,'native_invoice_draft_line_transferred',$metadata,gmdate('Y-m-d H:i:s')]);$audit=(int)$this->db->insert_id();if($audit<1)self::fail();
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.NativeCompanyInvoiceDraftLinesSchema::TRANSFERS.'(draft_line_id,issuer_id,invoice_id,itemable_id,line_hash,audit_id) VALUES(?,?,?,?,?,?)',[$line['id'],$issuer,$invoice,$entry['itemable_id'],$line['line_hash'],$audit]);
        }
        if(self::metadataTables($this->db,$this->p)){
            $fields=new NativeCompanyCustomFields($this->db,$this->p);
            foreach($lines as$offset=>$line)$fields->saveCreatedInvoiceLineInOwnedTransaction($issuer,$actor,$invoice,$requestKey,$proof[$offset]['itemable_id'],NativeCompanyResourceId::encode('company_invoice_draft_line',$line['id']),$writer);
        }
        return $this->transferReceiptInOwned($issuer,$actor,$draft,$revision,$invoice);
    }
    public function transferReceiptInOwned(int $issuer,int $actor,int $draft,int $revision,int $invoice):string
    {
        $lines=array_values(array_filter($this->assertInOwned($draft),static fn($r)=>$r['issuer_id']===$issuer&&$r['draft_revision']===$revision));if(!$lines)self::fail();$receipt=[];
        foreach($lines as$line){$transfer=$this->rows('SELECT * FROM '.$this->p.NativeCompanyInvoiceDraftLinesSchema::TRANSFERS.' WHERE draft_line_id=?',[$line['id']])[0]??null;
            if(!$transfer||(int)$transfer['issuer_id']!==$issuer||(int)$transfer['invoice_id']!==$invoice||(int)$transfer['itemable_id']<1||$transfer['line_hash']!==$line['line_hash'])self::fail();
            $metadata=CompanyInvoiceDraftsInput::json(['draft_id'=>$draft,'draft_revision'=>$revision,'draft_line_id'=>$line['id'],'event_key'=>$line['event_key'],'invoice_id'=>$invoice,'itemable_id'=>(int)$transfer['itemable_id'],'line_hash'=>$line['line_hash']]);
            $audit=$this->rows('SELECT issuer_id,actor_id,subject_id,event,metadata_json FROM '.$this->p.'issuers_audit WHERE id=?',[$transfer['audit_id']])[0]??[];
            if((int)($audit['issuer_id']??0)!==$issuer||(int)($audit['actor_id']??0)!==$actor||(int)($audit['subject_id']??0)!==$draft||($audit['event']??null)!=='native_invoice_draft_line_transferred'||($audit['metadata_json']??null)!==$metadata)self::fail();
            $receipt[]=['source'=>$line['id'],'target'=>(int)$transfer['itemable_id'],'hash'=>$line['line_hash'],'audit'=>(int)$transfer['audit_id']];
        }
        return CompanyInvoiceDraftsInput::hash($receipt);
    }
    /** Migration-only; never invents transfers for already-issued historical invoices. */
    public function backfill():void
    {
        NativeCompanyInvoiceDraftLinesSchema::assertValid($this->db,$this->p);
        IssuerTransaction::run($this->db,$this->p,self::tables(),function(){
            $ids=$this->rows('SELECT id FROM '.$this->p.'company_invoice_drafts ORDER BY id FOR UPDATE');foreach($ids as$r)$this->syncInOwned((int)$r['id']);
            if($this->rows('SELECT l.id FROM '.$this->p.NativeCompanyInvoiceDraftLinesSchema::TABLE.' l LEFT JOIN '.$this->p.'company_invoice_drafts d ON d.id=l.draft_id WHERE d.id IS NULL'))self::fail();
            if($this->rows('SELECT id FROM '.$this->p.'company_invoice_drafts ORDER BY id')!==$ids)self::fail();
        });
    }
}
