<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyInvoiceRecurringModesSchema.php';
require_once __DIR__.'/CompanyInvoiceRecurringDraftsSchema.php';

/** Explicit immutable choices. Absence means the exact historical438 contract. */
final class CompanyInvoiceRecurringModes
{
    public function __construct(private $db,private string $p){}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private static function hash(array $value):string{return hash('sha256',json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}
    public static function split(array $spec):array
    {
        $present=array_key_exists('mode',$spec)||array_key_exists('principal_invoice_id',$spec);
        $mode=$spec['mode']??'issue';$principal=$spec['principal_invoice_id']??0;
        unset($spec['mode'],$spec['principal_invoice_id']);
        if(!in_array($mode,['issue','draft'],true)||!(is_int($principal)||is_string($principal)&&preg_match('/^(0|[1-9][0-9]{0,9})$/D',$principal))||(int)$principal<0||(int)$principal>2147483647)throw new DomainException('company_invoice_recurring_invalid');
        if($mode==='draft'&&in_array($spec['send']??false,[true,1,'1'],true))throw new DomainException('company_invoice_recurring_draft_send');
        return[$spec,$present?['mode'=>$mode,'principal_invoice_id'=>(int)$principal]:null];
    }
    public function enabled():bool
    {
        $a=$this->db->table_exists($this->p.'company_invoice_schedule_modes');$b=$this->db->table_exists($this->p.'company_invoice_schedule_drafts');
        if(!$a&&!$b)return false;
        if(!$a||!$b)throw new RuntimeException('issuer_invoice_recurring_modes_schema_invalid');
        CompanyInvoiceRecurringModesSchema::assertValid($this->db,$this->p);CompanyInvoiceRecurringDraftsSchema::assertValid($this->db,$this->p);return true;
    }
    public function tables():array
    {
        if(!$this->enabled())return[];
        $tables=array_merge(array_keys(CompanyInvoiceRecurringModesSchema::definitions()),array_keys(CompanyInvoiceRecurringDraftsSchema::definitions()),['invoicepaymentrecords','credits'],(new CompanyInvoiceBalance($this->db,$this->p))->tables());
        require_once __DIR__.'/CompanyInvoiceDrafts.php';$tables=array_merge($tables,(new CompanyInvoiceDrafts($this->db,$this->p))->tables());return array_values(array_unique($tables));
    }
    /** Caller already owns the complete schedule boundary. */
    public function capture(int $i,int $a,array $row,array $choice):array
    {
        if(!$this->enabled())throw new DomainException('company_invoice_recurring_modes_unavailable');
        $payload=json_decode($row['payload_json'],true,32,JSON_THROW_ON_ERROR);$principal=$choice['principal_invoice_id'];$fingerprint='';
        if($principal>0)$fingerprint=self::hash($this->principal($i,$principal,$payload));
        $out=['issuer_id'=>$i,'schedule_id'=>(int)$row['id'],'actor_id'=>$a,'mode'=>$choice['mode'],'principal_invoice_id'=>$principal?:null,'principal_hash'=>$fingerprint];
        foreach(['payload_hash','context_hash','config_hash','transport_hash']as $k)$out[$k]=$row[$k];
        $out['mode_hash']=self::hash($out);return$out;
    }
    public function store(array $row):void
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_invoice_schedule_modes (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));
    }
    public function row(array $schedule):?array
    {
        if(!$this->enabled())return null;IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());
        $i=(int)$schedule['issuer_id'];$id=(int)$schedule['id'];
        $row=$this->rows('SELECT * FROM '.$this->p.'company_invoice_schedule_modes WHERE issuer_id=? AND schedule_id=? FOR UPDATE',[$i,$id])[0]??null;
        if(!$row)return null;
        foreach(['issuer_id','schedule_id','actor_id','principal_invoice_id']as$k)if($row[$k]!==null)$row[$k]=(int)$row[$k];
        $h=$row['mode_hash'];unset($row['mode_hash']);
        if(!is_string($h)||!hash_equals($h,self::hash($row))||(int)$row['actor_id']!==(int)$schedule['actor_id']||!in_array($row['mode'],['issue','draft'],true)||(($row['principal_invoice_id']===null)!==($row['principal_hash']==='')))throw new RuntimeException('issuer_invoice_recurring_modes_corrupt');
        foreach(['payload_hash','context_hash','config_hash','transport_hash']as$k)if(!hash_equals($row[$k],$schedule[$k]))throw new RuntimeException('issuer_invoice_recurring_modes_corrupt');
        $row['mode_hash']=$h;return$row;
    }
    private function principal(int $i,int $id,array $payload):array
    {
        $r=$this->rows('SELECT i.id,i.issuer_id,i.clientid,i.currency,i.date,i.duedate,i.total,d.formatted_number,d.legal_identity_json,d.recipient_identity_json FROM '.$this->p.'invoices i JOIN '.$this->p.'issuers_invoice_documents d ON d.invoice_id=i.id AND d.issuer_id=i.issuer_id WHERE i.id=? AND i.issuer_id=? FOR UPDATE',[$id,$i])[0]??null;
        if(!$r||(int)$r['clientid']!==(int)$payload['client_id']||(int)$r['currency']!==(int)$payload['currency'])throw new DomainException('company_invoice_recurring_principal_denied');
        foreach(['id','issuer_id','clientid','currency']as$k)$r[$k]=(int)$r[$k];
        $r['total']=CompanyInvoiceAmounts::decimal(CompanyInvoiceAmounts::minor((string)$r['total']));return$r;
    }
    /** Evaluate the declared principal, never the previous recurring child. */
    public function principalPaid(int $i,int $a,array $schedule,?array $mode):bool
    {
        if(!$mode||$mode['principal_invoice_id']===null)return true;
        $payload=json_decode($schedule['payload_json'],true,32,JSON_THROW_ON_ERROR);$id=(int)$mode['principal_invoice_id'];
        if(!hash_equals($mode['principal_hash'],self::hash($this->principal($i,$id,$payload))))throw new DomainException('company_invoice_recurring_principal_changed');
        $status=$this->rows('SELECT status FROM '.$this->p.'invoices WHERE id=? AND issuer_id=? FOR UPDATE',[$id,$i])[0]??null;
        $position=(new CompanyInvoiceBalance($this->db,$this->p))->inOwned($i,$a,$id);
        return $status&&(int)$status['status']===2&&CompanyInvoiceAmounts::minor($position['receivable'])===0;
    }
    public function draft(int $i,int $a,array $schedule,array $run,?int $viewer=null):?array
    {
        if(!$this->enabled())return null;IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());
        $link=$this->rows('SELECT * FROM '.$this->p.'company_invoice_schedule_drafts WHERE request_key=? FOR UPDATE',[$run['request_key']])[0]??null;if(!$link){$q=$this->rows('SELECT id FROM '.$this->p.'company_invoice_drafts WHERE issuer_id=? AND origin_key=? FOR UPDATE',[$i,$run['request_key']])[0]??null;if(!$q)return null;require_once __DIR__.'/CompanyInvoiceDrafts.php';$d=(new CompanyInvoiceDrafts($this->db,$this->p))->documentInOwned($i,$viewer??$a,(int)$q['id']);if($d['origin_key']!==$run['request_key']||(int)$d['created_by']!==$a)throw new RuntimeException('issuer_invoice_recurring_drafts_corrupt');$this->assertDraftCreation($i,$a,$d,$this->payload($schedule,$run['run_date']));$d['_linked']=false;return$d;}
        if((int)$link['issuer_id']!==$i||(int)$link['actor_id']!==$a||(int)$link['schedule_id']!==(int)$schedule['id']||$link['run_date']!==$run['run_date'])throw new RuntimeException('issuer_invoice_recurring_drafts_corrupt');
        require_once __DIR__.'/CompanyInvoiceDrafts.php';$d=(new CompanyInvoiceDrafts($this->db,$this->p))->documentInOwned($i,$viewer??$a,(int)$link['draft_id']);
        if($d['origin_key']!==$run['request_key']||(int)$d['created_by']!==$a)throw new RuntimeException('issuer_invoice_recurring_drafts_corrupt');
        // The creation payload remains the immutable binding. A reviewed draft can later be edited explicitly.
        $expected=$this->payload($schedule,$run['run_date']);if(!hash_equals($link['payload_hash'],self::hash($expected)))throw new RuntimeException('issuer_invoice_recurring_drafts_corrupt');
        $this->assertDraftCreation($i,$a,$d,$expected);$d['_linked']=true;return$d;
    }
    public function linkDraft(int $i,int $a,array $schedule,array $run,int $id):void
    {
        $key=$run['request_key'];$old=$this->draft($i,$a,$schedule,$run);
        if($old&&$old['_linked']){if((int)$old['id']!==$id)throw new DomainException('company_invoice_recurring_conflict');return;}
        require_once __DIR__.'/CompanyInvoiceDrafts.php';$d=(new CompanyInvoiceDrafts($this->db,$this->p))->documentInOwned($i,$a,$id);$payload=$this->payload($schedule,$run['run_date']);
        if($d['origin_key']!==$key||(int)$d['created_by']!==$a)throw new DomainException('company_invoice_recurring_conflict');$this->assertDraftCreation($i,$a,$d,$payload);
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_invoice_schedule_drafts (request_key,issuer_id,schedule_id,actor_id,run_date,draft_id,payload_hash,created_at) VALUES (?,?,?,?,?,?,?,?)',[$key,$i,$schedule['id'],$a,$run['run_date'],$id,self::hash($payload),gmdate('Y-m-d H:i:s')]);
        if(!$this->draft($i,$a,$schedule,$run))throw new RuntimeException('issuer_invoice_recurring_unconfirmed');
    }
    private function assertDraftCreation(int $i,int $a,array $draft,array $payload):void
    {
        // Bind the original immutable creation, not a later legitimate draft edit.
        $r=$this->rows('SELECT creation_hash FROM '.$this->p.'company_invoice_drafts WHERE issuer_id=? AND id=? FOR UPDATE',[$i,$draft['id']])[0]??null;
        $expected=CompanyInvoiceDraftsInput::hash(['payload'=>CompanyInvoiceDraftsInput::normalize($payload),'source'=>null]);
        if(!$r||(int)$draft['created_by']!==$a||!hash_equals($expected,$r['creation_hash']))throw new RuntimeException('issuer_invoice_recurring_drafts_corrupt');
    }
    private function payload(array $schedule,string $date):array
    {
        $p=json_decode($schedule['payload_json'],true,32,JSON_THROW_ON_ERROR);$days=(int)(new DateTimeImmutable($p['date'],new DateTimeZone('UTC')))->diff(new DateTimeImmutable($p['due_date'],new DateTimeZone('UTC')))->format('%a');$p['date']=$date;$p['due_date']=(new DateTimeImmutable($date,new DateTimeZone('UTC')))->modify('+'.$days.' days')->format('Y-m-d');return$p;
    }
}
