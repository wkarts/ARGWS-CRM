<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyResourceReferences.php';
require_once __DIR__.'/NativeCompanyCalendarPreferences.php';
require_once __DIR__.'/NativeCompanyLegacySalesBoundary.php';
require_once __DIR__.'/TaskClientBoundary.php';
require_once __DIR__.'/CustomerRelationBoundary.php';
require_once __DIR__.'/InvoiceIssuerScope.php';

/** Historical lineage belongs only to the proven installation company. No global model/hooks. */
final class NativeCompanyLegacyCalendarRead
{
    private ?array $frame=null;
    public function __construct(private $db,private string $p)
    {if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function authority(int $i,int $a):array
    {
        CompanyCalendarPreferences::authorize($this->db,$this->p,$i,$a);
        $acl=new IssuerResourceAccess($this->db,$this->p);
        $grants=[];foreach(['invoices','estimates','proposals','projects','tasks','contracts']as$feature)$grants[$feature]=$acl->can($i,$a,$feature,'view');
        return ['primary'=>$acl->primaryId()===$i,'grants'=>$grants];
    }
    private function capture(int $i,int $a,array $filter,array $values):array
    {
        $authority=$this->authority($i,$a);$sources=[];$events=[];
        if(!$authority['primary'])return compact('authority','sources','events');
        $enabled=static fn($key)=>!$filter['filtered']||isset($filter['flags'][$key]);
        $defs=[
            'invoices'=>['invoice','number','duedate','calendar_invoice_color','Fatura ','invoices/list_invoices/'],
            'estimates'=>['estimate','number','COALESCE(expirydate,date)','calendar_estimate_color','Orçamento ','estimates/list_estimates/'],
            'proposals'=>['proposal','subject','COALESCE(open_till,date)','calendar_proposal_color','','proposals/list_proposals/'],
            'projects'=>['project','name','COALESCE(deadline,start_date)','calendar_project_color','','projects/view/'],
            'tasks'=>['task','name','COALESCE(duedate,startdate)','calendar_task_color','','tasks/view/'],
            'contracts'=>['contract','subject','COALESCE(dateend,datestart)','calendar_contract_color','','contracts/contract/'],
        ];
        foreach($defs as$table=>[$kind,$title,$date,$color,$label,$path]){
            if(!$enabled($table)||!$authority['grants'][$table]||($values['show_'.$table.'_on_calendar']??'0')!=='1'||!$this->db->table_exists($this->p.$table))continue;
            $legacy=IssuerStorage::fieldExists($this->db,'issuer_id',$this->p.$table)?'x.issuer_id IS NULL AND ':'';
            $boundary=match($table){
                'invoices'=>\app\services\ClientIssuerScope::legacySql($this->db,$this->p,'x','clientid'),
                'estimates','proposals','contracts'=>NativeCompanyLegacySalesBoundary::sql($this->db,$this->p,$table,'x'),
                'projects'=>\app\services\CustomerRelationBoundary::sql($this->db,$this->p,$table,'x'),
                'tasks'=>\app\services\TaskClientBoundary::legacySql($this->db,$this->p,'x'),
            };
            $state=match($table){'invoices'=>' AND x.status NOT IN (2,5)','estimates','proposals'=>' AND x.status NOT IN (2,3)','projects'=>' AND x.status NOT IN (4,5)','tasks'=>' AND x.status<>5','contracts'=>' AND x.trash=0'};
            $args=[$filter['start']->format('Y-m-d'),$filter['end']->format('Y-m-d')];
            if($table==='tasks'){
                // Unknown extension relations have no proven company lineage.
                $boundary.=" AND (x.rel_type IS NULL OR x.rel_type='' OR x.rel_type IN ('customer','project','invoice','estimate','contract','expense','ticket','proposal'))";
                if(!$authority['grants']['projects'])$state.=" AND (x.rel_type IS NULL OR x.rel_type<>'project')";
                if(($values['calendar_only_assigned_tasks']??'0')==='1'){$state.=' AND EXISTS(SELECT 1 FROM '.$this->p.'task_assigned ca WHERE ca.taskid=x.id AND ca.staffid=?)';$args[]=$a;}
            }
            $sql='SELECT x.id,x.'.$title.' AS title,'.$date.' AS calendar_date FROM '.$this->p.$table.' x WHERE '.$legacy.'('.$boundary.') AND '.$date.'>=? AND '.$date.'<?'.$state.' ORDER BY calendar_date,x.id LIMIT 501';
            $rows=$this->rows($sql,$args);$sources[$table]=[$sql,$args,$rows];
            foreach($rows as$row){
                $id=IssuerPolicy::id($row['id']);$canonical=NativeCompanyResourceId::encode($kind,$id);
                // The same resolver as the native direct URL verifies relationships and ACL.
                NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,$kind,$canonical,'view');
                $day=DateTimeImmutable::createFromFormat('!Y-m-d',(string)$row['calendar_date']);
                if(!$day||$day->format('Y-m-d')!==$row['calendar_date']||(!is_string($row['title'])&&!is_int($row['title']))||preg_match('//u',(string)$row['title'])!==1)throw new DomainException('native_calendar_legacy_invalid');
                $events[]=['id'=>'legacy_'.$table.':'.$id,'title'=>$label.$row['title'],'start'=>$row['calendar_date'],'allDay'=>true,'color'=>$values[$color]??'#03A9F4','native_path'=>$path.$canonical];
            }
            if(count($events)>500)throw new DomainException('native_calendar_range_too_large');
        }
        if($this->db->table_exists($this->p.'reminders')){
            $acl=new IssuerResourceAccess($this->db,$this->p);
            $where='r.date>=? AND r.date<?';$args=[$filter['start']->format('Y-m-d H:i:s'),$filter['end']->format('Y-m-d H:i:s')];
            if(IssuerStorage::fieldExists($this->db,'issuer_id',$this->p.'reminders'))$where.=' AND r.issuer_id IS NULL';
            if(!$acl->administrative($i,$a)){$where.=' AND (r.creator=? OR r.staff=?)';$args[]=$a;$args[]=$a;}
            if(($values['hide_notified_reminders_from_calendar']??'1')==='1')$where.=' AND r.isnotified=0';
            // Read identifiers before any stored description or target name.
            $sql='SELECT r.id,r.rel_type,r.rel_id,r.staff,r.creator,r.date FROM '.$this->p.'reminders r WHERE '.$where.' ORDER BY r.date,r.id LIMIT 501';
            $candidates=$this->rows($sql,$args);if(count($candidates)>500)throw new DomainException('native_calendar_range_too_large');$proofs=[];
            foreach($candidates as$row){
                $type=$row['rel_type'];if(!in_array($type,NativeCompanyCalendarPreferences::REMINDERS,true)||!$enabled($type.'_reminders')||($values['show_'.$type.'_reminders_on_calendar']??'0')!=='1')continue;
                $kind=$type==='customer'?'client':$type;
                if(!$acl->member($i,(int)$row['staff']))continue;
                try{$relation=NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,$kind,$row['rel_id'],'view');}catch(DomainException $denied){continue;}
                if($relation['source_kind']!==($kind==='client'?'client':$kind))continue;
                $detail=$this->rows('SELECT r.description,s.firstname,s.lastname FROM '.$this->p.'reminders r JOIN '.$this->p.'staff s ON s.staffid=r.staff WHERE r.id=?',[$row['id']])[0]??null;
                if(!$detail)throw new DomainException('native_calendar_stale');
                if(NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,$kind,$row['rel_id'],'view')!==$relation)throw new DomainException('native_calendar_stale');
                $proofs[]=[$row,$relation,$detail];$title=((int)$row['staff']===$a?'':'('.$detail['firstname'].' '.$detail['lastname'].') ').mb_substr($detail['description'],0,60).'...';
                $events[]=['id'=>'legacy_reminder:'.$row['id'],'title'=>$title,'start'=>$row['date'],'color'=>$values['calendar_reminder_color']??'#03A9F4','native_path'=>$relation['url']];
            }
            $sources['reminders']=[$sql,$args,$candidates,$proofs];
        }
        if(count($events)>1000)throw new DomainException('native_calendar_range_too_large');
        if($this->authority($i,$a)!==$authority)throw new DomainException('native_calendar_stale');
        return compact('authority','sources','events');
    }
    public function read(int $i,int $a,array $filter,array $values):array
    {
        $this->frame=null;$frame=$this->capture($i,$a,$filter,$values);$this->frame=[$i,$a,$filter,$values,$frame];$this->confirm($i,$a);return$frame['events'];
    }
    public function confirm(int $i,int $a):void
    {
        if(!$this->frame||$this->frame[0]!==$i||$this->frame[1]!==$a||$this->capture($i,$a,$this->frame[2],$this->frame[3])!==$this->frame[4])throw new DomainException('native_calendar_stale');
    }
}
