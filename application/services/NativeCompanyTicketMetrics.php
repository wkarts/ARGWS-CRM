<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTicketRead.php';
/** Owned ticket metrics resolve real status and department catalogs, never the storage origin marker. */
final class NativeCompanyTicketMetrics
{
    public function __construct(private$db,private string$p){}
    private function collect(int$i,int$a,?array$range=null):array
    {
        $reader=new NativeCompanyTicketRead($this->db,$this->p);return IssuerTransaction::run($this->db,$this->p,$reader->tables(),function()use($reader,$i,$a,$range){
            $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($i,$a,'tickets','view')||($range!==null&&!$acl->can($i,$a,'reports','view')))throw new DomainException('company_dashboard_denied');
            $where='t.issuer_id=? AND '.NativeCompanyTicketVisibility::predicate($this->db,$this->p,'t.issuer_id','t.id');$args=[$i];if($range!==null){$where.=' AND t.created_at>=? AND t.created_at<=?';$args=array_merge($args,$range);}
            $rows=IssuerStorage::query($this->db,'SELECT t.id FROM '.$this->p.'company_tickets t WHERE '.$where.' ORDER BY t.id LIMIT 10001',$args)->result_array();if(count($rows)>10000)throw new DomainException('company_dashboard_period_limit');$out=[];
            foreach($rows as$r){try{$out[]=$reader->metricInOwnedTransaction($i,$a,NativeCompanyResourceId::encode('company_ticket',(int)$r['id']));}catch(DomainException$e){if($e->getMessage()!=='native_ticket_department_denied')throw$e;}}
            if(!$acl->can($i,$a,'tickets','view')||($range!==null&&!$acl->can($i,$a,'reports','view')))throw new DomainException('company_dashboard_denied');return$out;
        });
    }
    /** Same native summary chips: count only authorized, unmerged and undeleted sources. */
    public function nativeSummary(int$i,int$a):array
    {
        $reader=new NativeCompanyTicketRead($this->db,$this->p);return IssuerTransaction::run($this->db,$this->p,$reader->tables(),function()use($reader,$i,$a){
            $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($i,$a,'tickets','view')||$acl->primaryId()===$i)throw new DomainException('native_ticket_denied');
            $catalog=new NativeCompanyTicketCatalog($this->db,$this->p);$statuses=$catalog->list($i,$a,'status');$counts=[];foreach($statuses as$status)$counts[$status['native_id']]=0;$historical=[];
            $rows=IssuerStorage::query($this->db,'SELECT t.id FROM '.$this->p.'company_tickets t WHERE t.issuer_id=? AND '.NativeCompanyTicketVisibility::predicate($this->db,$this->p,'t.issuer_id','t.id').' AND NOT EXISTS(SELECT 1 FROM '.$this->p.NativeCompanyTicketMergeSchema::LINKS.' m WHERE m.issuer_id=t.issuer_id AND m.child_id=t.id) ORDER BY t.id LIMIT 10001',[$i])->result_array();if(count($rows)>10000)throw new DomainException('native_ticket_summary_limit');
            foreach($rows as$r){try{$metric=$reader->metricInOwnedTransaction($i,$a,NativeCompanyResourceId::encode('company_ticket',(int)$r['id']));}catch(DomainException$e){if($e->getMessage()==='native_ticket_department_denied')continue;throw$e;}
                if($metric['status_id']===null)$historical[$metric['status']]=($historical[$metric['status']]??0)+1;
                elseif(!array_key_exists($metric['status_id'],$counts))throw new DomainException('native_ticket_reference_invalid');else$counts[$metric['status_id']]++;
            }
            if($catalog->list($i,$a,'status')!==$statuses||!$acl->can($i,$a,'tickets','view'))throw new DomainException('native_ticket_changed');return['statuses'=>$statuses,'counts'=>$counts,'historical'=>$historical];
        });
    }
    /** Seven days in the native Monday-to-Sunday chart; no global ticket count. */
    public function weeklyOpeningCounts(int$i,int$a,string$monday):array
    {
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$monday);if(!$date||$date->format('Y-m-d')!==$monday||$date->format('N')!=='1')throw new DomainException('native_ticket_week_invalid');
        $reader=new NativeCompanyTicketRead($this->db,$this->p);return IssuerTransaction::run($this->db,$this->p,$reader->tables(),function()use($i,$a,$reader,$date){
            $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($i,$a,'tickets','view')||$acl->primaryId()===$i)throw new DomainException('native_ticket_denied');$start=$date->format('Y-m-d').' 00:00:00';$end=$date->modify('+7 days')->format('Y-m-d').' 00:00:00';
            $ids=IssuerStorage::query($this->db,'SELECT t.id FROM '.$this->p.'company_tickets t WHERE t.issuer_id=? AND t.created_at>=? AND t.created_at<? AND '.NativeCompanyTicketVisibility::predicate($this->db,$this->p,'t.issuer_id','t.id').' AND NOT EXISTS(SELECT 1 FROM '.$this->p.NativeCompanyTicketMergeSchema::LINKS.' m WHERE m.issuer_id=t.issuer_id AND m.child_id=t.id) ORDER BY t.id LIMIT 10001',[$i,$start,$end])->result_array();if(count($ids)>10000)throw new DomainException('native_ticket_summary_limit');$counts=array_fill(0,7,0);$snapshots=[];
            foreach($ids as$row){$id=NativeCompanyResourceId::encode('company_ticket',(int)$row['id']);try{$metric=$reader->metricInOwnedTransaction($i,$a,$id);}catch(DomainException$e){if($e->getMessage()==='native_ticket_department_denied')continue;throw$e;}$day=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$metric['created_at']);if(!$day||$day->format('Y-m-d H:i:s')!==$metric['created_at'])throw new DomainException('native_ticket_date_invalid');$counts[(int)$day->format('N')-1]++;$snapshots[$id]=$metric;}
            foreach($snapshots as$id=>$metric)if($reader->metricInOwnedTransaction($i,$a,(string)$id)!==$metric)throw new DomainException('native_ticket_changed');if(!$acl->can($i,$a,'tickets','view'))throw new DomainException('native_ticket_denied');return$counts;
        });
    }
    public function summary(int$i,int$a):array
    {
        $rows=$this->collect($i,$a);$counts=[];$labels=[];$departments=[];
        foreach($rows as$r){$code=$r['status'];$counts[$code]=($counts[$code]??0)+1;$labels[$code]=$r['status_label'];
            if($code==='open'){$key=$r['department_id']??'unassigned';if(!isset($departments[$key]))$departments[$key]=['id'=>$key,'name'=>$r['department_name']??'Sem departamento','n'=>0];$departments[$key]['n']++;}}
        ksort($counts);ksort($labels);ksort($departments);return['counts'=>$counts,'labels'=>$labels,'departments'=>array_values($departments)];
    }
    public function reportSources(int$i,int$a,string$start,string$end):array
    {
        foreach([$start,$end]as$v){$d=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$v);if(!$d||$d->format('Y-m-d H:i:s')!==$v)throw new DomainException('company_dashboard_period_invalid');}if($end<$start)throw new DomainException('company_dashboard_period_invalid');
        $reader=new NativeCompanyTicketRead($this->db,$this->p);return IssuerTransaction::run($this->db,$this->p,$reader->tables(),function()use($reader,$i,$a,$start,$end){
            // No nested transaction: selection and all source checks belong to this exact issuer frame.
            $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($i,$a,'tickets','view')||!$acl->can($i,$a,'reports','view'))throw new DomainException('company_dashboard_denied');
            $candidates=IssuerStorage::query($this->db,'SELECT t.id FROM '.$this->p.'company_tickets t WHERE t.issuer_id=? AND '.NativeCompanyTicketVisibility::predicate($this->db,$this->p,'t.issuer_id','t.id').' ORDER BY t.id LIMIT 10001',[$i])->result_array();if(count($candidates)>10000)throw new DomainException('company_dashboard_period_limit');$tickets=[];$replyCounts=[];
            foreach($candidates as$c){$id=NativeCompanyResourceId::encode('company_ticket',(int)$c['id']);try{$metric=$reader->metricInOwnedTransaction($i,$a,$id);}catch(DomainException$e){if($e->getMessage()==='native_ticket_department_denied')continue;throw$e;}
                $replies=IssuerStorage::query($this->db,'SELECT r.id,r.actor_id,r.created_at FROM '.$this->p.'company_ticket_replies r WHERE r.issuer_id=? AND r.ticket_id=? AND r.actor_id>0 AND '.NativeCompanyTicketVisibility::predicate($this->db,$this->p,'r.issuer_id','r.id',true).' ORDER BY r.created_at,r.id',[$i,$metric['id']])->result_array();
                if($metric['created_at']>=$start&&$metric['created_at']<=$end)$tickets[]=$metric+['first_reply'=>$replies[0]['created_at']??null];
                foreach($replies as$r)if($r['created_at']>=$start&&$r['created_at']<=$end)$replyCounts[(int)$r['actor_id']]=($replyCounts[(int)$r['actor_id']]??0)+1;
                if($reader->metricInOwnedTransaction($i,$a,$id)!==$metric)throw new DomainException('native_ticket_changed');
            }
            if(!$acl->can($i,$a,'tickets','view')||!$acl->can($i,$a,'reports','view'))throw new DomainException('company_dashboard_denied');$replies=[];ksort($replyCounts);foreach($replyCounts as$actor=>$n)$replies[]=['actor_id'=>$actor,'n'=>$n];return['tickets'=>$tickets,'replies'=>$replies];
        });
    }
}
