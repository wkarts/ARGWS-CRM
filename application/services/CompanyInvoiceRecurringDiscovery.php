<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyInvoiceRecurringDiscoverySchema.php';
require_once __DIR__.'/CompanyInvoiceRecurringDates.php';

/** Durable bounded round robin. Discovery never authorizes emission; each stored actor is checked by run(). */
final class CompanyInvoiceRecurringDiscovery
{
    public function __construct(private $db,private string$p){}
    private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private static function encode(array$v):string{return json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
    private static function integer($v,int$min=0):int{if(!(is_int($v)||is_string($v)&&preg_match('/^(0|[1-9][0-9]*)$/D',$v))||(int)$v<$min||(int)$v>2147483647)throw new RuntimeException('issuer_invoice_recurring_discovery_corrupt');return(int)$v;}
    private function row():?array
    {
        $rows=$this->rows('SELECT * FROM '.$this->p.'company_invoice_schedule_discovery FOR UPDATE');if(!$rows)return null;if(count($rows)!==1)throw new RuntimeException('issuer_invoice_recurring_discovery_corrupt');$r=$rows[0];foreach(['id','cursor_id','high_water_id','revision']as$k)$r[$k]=self::integer($r[$k],in_array($k,['id','revision'],true)?1:0);
        if($r['id']!==1||$r['cursor_id']>$r['high_water_id']||!is_string($r['batch_hash'])||!preg_match('/^[a-f0-9]{64}$/D',$r['batch_hash']))throw new RuntimeException('issuer_invoice_recurring_discovery_corrupt');
        $metadata=self::encode(['cursor_id'=>$r['cursor_id'],'high_water_id'=>$r['high_water_id'],'revision'=>$r['revision'],'batch_hash'=>$r['batch_hash']]);
        $audit=$this->rows('SELECT actor_id,event,issuer_id,subject_id,metadata_json FROM '.$this->p.'issuers_audit WHERE id=? FOR UPDATE',[$r['audit_id']])[0]??null;
        if(!$audit||(int)$audit['actor_id']!==0||$audit['event']!=='company_invoice_schedule_discovered'||$audit['issuer_id']!==null||(int)$audit['subject_id']!==1||$audit['metadata_json']!==$metadata)throw new RuntimeException('issuer_invoice_recurring_discovery_corrupt');return$r;
    }
    private function highWater(string$today):int{return self::integer($this->rows("SELECT COALESCE(MAX(id),0) n FROM {$this->p}company_invoice_schedules WHERE state IN ('active','processing') AND next_date<=?",[$today])[0]['n']);}
    private function section(string$today,int$after,int$through,int$limit,array$excluded=[]):array
    {
        if($limit<1||$through<1)return[];$where='';$args=[$after,$through,$today];if($excluded){$where=' AND id NOT IN ('.implode(',',array_fill(0,count($excluded),'?')).')';$args=array_merge($args,$excluded);}
        return$this->rows("SELECT issuer_id,actor_id,id FROM {$this->p}company_invoice_schedules WHERE id>? AND id<=? AND state IN ('active','processing') AND next_date<=?".$where.' ORDER BY id LIMIT '.$limit,$args);
    }
    public function reserve(string$today,int$limit):array
    {
        CompanyInvoiceRecurringDates::date($today);if($today>gmdate('Y-m-d')||$limit<1||$limit>100)throw new DomainException('company_invoice_recurring_invalid');CompanyInvoiceRecurringDiscoverySchema::assertValid($this->db,$this->p);
        return IssuerTransaction::run($this->db,$this->p,['issuers_lock','issuers_audit','company_invoice_schedules','company_invoice_schedule_discovery'],function()use($today,$limit){
            $old=$this->row();$cursor=$old['cursor_id']??0;$high=$old['high_water_id']??$this->highWater($today);$refs=$this->section($today,$cursor,$high,$limit);
            if(count($refs)<$limit){$high=$this->highWater($today);$head=$this->section($today,0,$high,$limit-count($refs),array_column($refs,'id'));$refs=array_merge($refs,$head);}
            if(!$refs)return[];$last=end($refs);$cursor=self::integer($last['id'],1);if($cursor>$high)throw new RuntimeException('issuer_invoice_recurring_discovery_corrupt');$revision=($old['revision']??0)+1;if($revision>2147483647)throw new RuntimeException('issuer_invoice_recurring_discovery_exhausted');
            $batchHash=hash('sha256',self::encode($refs));$metadata=self::encode(['cursor_id'=>$cursor,'high_water_id'=>$high,'revision'=>$revision,'batch_hash'=>$batchHash]);$now=gmdate('Y-m-d H:i:s');
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,metadata_json,created_at) VALUES(0,?,NULL,1,?,?)',['company_invoice_schedule_discovered',$metadata,$now]);$audit=(string)$this->db->insert_id();if(!preg_match('/^[1-9][0-9]*$/D',$audit))throw new RuntimeException('issuer_invoice_recurring_discovery_unconfirmed');
            if($old)IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_invoice_schedule_discovery SET cursor_id=?,high_water_id=?,revision=?,batch_hash=?,audit_id=?,updated_at=? WHERE id=1 AND revision=?',[$cursor,$high,$revision,$batchHash,$audit,$now,$old['revision']]);
            else IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_invoice_schedule_discovery(id,cursor_id,high_water_id,revision,batch_hash,audit_id,created_at,updated_at) VALUES(1,?,?,?,?,?,?,?)',[$cursor,$high,$revision,$batchHash,$audit,$now,$now]);
            $saved=$this->row();if(!$saved||$saved['cursor_id']!==$cursor||$saved['high_water_id']!==$high||$saved['revision']!==$revision||$saved['batch_hash']!==$batchHash||(string)$saved['audit_id']!==$audit)throw new RuntimeException('issuer_invoice_recurring_discovery_unconfirmed');return$refs;
        });
    }
}
