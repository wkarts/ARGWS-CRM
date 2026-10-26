<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyCalendarPreferences.php';require_once __DIR__.'/CompanyCalendarSources.php';
require_once __DIR__.'/CompanyInvoices.php';require_once __DIR__.'/CompanySales.php';require_once __DIR__.'/CompanyProjects.php';require_once __DIR__.'/CompanyTasks.php';require_once __DIR__.'/CompanyStandaloneTasks.php';require_once __DIR__.'/CompanyContracts.php';
/** Read-only private calendar. Candidate identifiers precede canonical hydration; final frame is request-local. */
final class CompanyCalendarRead
{
    private ?array $frame=null;
    public function __construct(private $db,private string $p,private bool $nativePreferences=false){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private static function date($v):string{if(!is_string($v)||!($d=DateTimeImmutable::createFromFormat('!Y-m-d',$v))||$d->format('Y-m-d')!==$v)throw new DomainException('company_calendar_invalid');return$v;}
    public static function filters(array $input):array
    {
        if(array_diff(array_keys($input),['start','end','client_id','title','sources']))throw new DomainException('company_calendar_invalid');$start=self::date($input['start']??null);$end=self::date($input['end']??null);if($end<$start||(new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days>62)throw new DomainException('company_calendar_invalid');$client=$input['client_id']??'';$client=$client===''?null:IssuerPolicy::id($client);$title=$input['title']??'';if(!is_string($title)||strlen($title)>191||preg_match('//u',$title)!==1||preg_match('/[\x00-\x1f\x7f]/',$title))throw new DomainException('company_calendar_invalid');$sources=$input['sources']??array_keys(CompanyCalendarSources::definitions());if(!is_array($sources)||!array_is_list($sources))throw new DomainException('company_calendar_invalid');foreach($sources as$source)if(!is_string($source))throw new DomainException('company_calendar_invalid');if(array_diff($sources,array_keys(CompanyCalendarSources::definitions()))||count($sources)!==count(array_unique($sources)))throw new DomainException('company_calendar_invalid');sort($sources);return['start'=>$start,'end'=>$end,'client_id'=>$client,'title'=>trim($title),'sources'=>$sources];
    }
    private function tables():array
    {
        $t=(new CompanyCalendarPreferences($this->db,$this->p))->tables();if($this->nativePreferences)$t[]='options';foreach(['invoices','issuers_invoice_documents','company_sales_documents','company_sales_revisions','company_sales_copy_requests','company_projects','company_tasks','company_standalone_tasks','company_contracts','clients','issuers_clients','issuers_client_links']as$name)if($this->db->table_exists($this->p.$name))$t[]=$name;return array_values(array_unique($t));
    }
    private function transaction(callable $work){return IssuerTransaction::run($this->db,$this->p,$this->tables(),$work);}
    private function clientFilter(int $issuer,int $actor,?int $client):void
    {
        if($client===null)return;$acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($issuer,$actor,'customers','view'))throw new DomainException('company_calendar_denied');
        // Identifiers only: never returns another customer's name for a guessed filter.
        if(count($this->rows('SELECT c.userid FROM '.$this->p.'clients c JOIN '.$this->p.'issuers_clients o ON o.client_id=c.userid WHERE c.userid=? AND (o.owner_issuer_id=? OR EXISTS(SELECT 1 FROM '.$this->p.'issuers_client_links l WHERE l.client_id=c.userid AND l.issuer_id=?)) FOR UPDATE',[$client,$issuer,$issuer]))!==1)throw new DomainException('company_calendar_denied');
    }
    private function candidates(int $issuer,int $actor,array $f,array $v):array
    {
        $out=[];$total=0;$conn=$this->db->pdo??$this->db->conn_id??null;$sqlite=$conn instanceof PDO&&$conn->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        foreach(CompanyCalendarSources::definitions()as$source=>$d){if(!in_array($source,$f['sources'],true)||$v[$d['toggle']]!=='1'||!CompanyCalendarSources::allowed($this->db,$this->p,$issuer,$actor,$source))continue;
            if(!CompanyCalendarSources::available($this->db,$this->p,$source))throw new DomainException('company_calendar_source_unavailable');$args=[$issuer,$f['start'],$f['end']];
            if($source==='invoices'){$sql='SELECT i.id FROM '.$this->p.'invoices i JOIN '.$this->p.'issuers_invoice_documents d ON d.invoice_id=i.id AND d.issuer_id=i.issuer_id WHERE i.issuer_id=? AND i.duedate>=? AND i.duedate<=?';if($f['client_id']!==null){$sql.=' AND i.clientid=?';$args[]=$f['client_id'];}$sql.=' ORDER BY i.duedate,i.id';}
            elseif(in_array($source,['estimates','proposals'],true)){$due=$sqlite?"json_extract(payload_json,'$.due_date')":"JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.due_date'))";$sql='SELECT id FROM '.$this->p.'company_sales_documents WHERE issuer_id=? AND '.$due.'>=? AND '.$due.'<=? AND kind=?';$args[]=$source;if($f['client_id']!==null){$sql.=' AND client_id=?';$args[]=$f['client_id'];}$sql.=' ORDER BY id';}
            elseif($source==='project_tasks'){$sql='SELECT t.id FROM '.$this->p.'company_tasks t JOIN '.$this->p.'company_projects p ON p.id=t.project_id AND p.issuer_id=t.issuer_id WHERE t.issuer_id=? AND t.due_date>=? AND t.due_date<=?';if($v['calendar_only_assigned_tasks']==='1'){$sql.=' AND t.assignee_id=?';$args[]=$actor;}if($f['client_id']!==null){$sql.=' AND p.client_id=?';$args[]=$f['client_id'];}$sql.=' ORDER BY t.due_date,t.id';}
            else{$sql='SELECT id FROM '.$this->p.$d['table'].' WHERE issuer_id=? AND due_date>=? AND due_date<=?';if($source==='standalone_tasks'){if($f['client_id']!==null)continue;if($v['calendar_only_assigned_tasks']==='1'){$sql.=' AND assignee_id=?';$args[]=$actor;}}elseif($f['client_id']!==null){$sql.=' AND client_id=?';$args[]=$f['client_id'];}$sql.=' ORDER BY due_date,id';}
            $rows=$this->rows($sql.' LIMIT 501 FOR UPDATE',$args);$total+=count($rows);if($total>500)throw new DomainException('company_calendar_limit');$out[$source]=array_map(static fn($r)=>IssuerPolicy::id($r['id']),$rows);
        }return$out;
    }
    private function sourceFrame(int $issuer,int $actor,string $source,int $id):string
    {
        if(!CompanyCalendarSources::allowed($this->db,$this->p,$issuer,$actor,$source))throw new DomainException('company_calendar_denied');$d=CompanyCalendarSources::definitions()[$source];
        // Read only the owning reference before touching document content.
        $extra=in_array($source,['estimates','proposals'],true)?' AND kind=?':'';$args=[$id,$issuer];if($extra!=='')$args[]=$source;$ref=$this->rows('SELECT id,issuer_id FROM '.$this->p.$d['table'].' WHERE id=? AND issuer_id=?'.$extra.' FOR UPDATE',$args);if(count($ref)!==1)throw new DomainException('company_calendar_denied');
        $row=$this->rows('SELECT * FROM '.$this->p.$d['table'].' WHERE id=? AND issuer_id=?'.$extra.' FOR UPDATE',$args)[0]??null;if(!$row)throw new DomainException('company_calendar_denied');$parts=[$row];
        if($source==='invoices'){$snapshot=$this->rows('SELECT * FROM '.$this->p.'issuers_invoice_documents WHERE invoice_id=? AND issuer_id=? FOR UPDATE',[$id,$issuer]);if(count($snapshot)!==1)throw new DomainException('company_calendar_snapshot_invalid');$parts[]=$snapshot;}
        elseif(in_array($source,['estimates','proposals'],true)&&$this->db->table_exists($this->p.'company_sales_revisions')){$parts[]=$this->rows('SELECT * FROM '.$this->p.'company_sales_revisions WHERE issuer_id=? AND document_id=? ORDER BY revision_no FOR UPDATE',[$issuer,$id]);$parts[]=$this->rows("SELECT id,actor_id,event,metadata_json FROM {$this->p}issuers_audit WHERE issuer_id=? AND subject_id=? AND event IN ('company_sales_creation_recorded','company_sales_revised') ORDER BY id FOR UPDATE",[$issuer,$id]);}
        elseif($source==='project_tasks'){$parent=$this->rows('SELECT id,issuer_id,client_id,revision FROM '.$this->p.'company_projects WHERE id=? AND issuer_id=? FOR UPDATE',[$row['project_id'],$issuer]);if(count($parent)!==1)throw new DomainException('company_calendar_denied');$parts[]=$parent;}
        return hash('sha256',json_encode($parts,JSON_THROW_ON_ERROR));
    }
    /** The official feed consumes the same Settings snapshot inside this owned transaction. */
    private function preferences(int $issuer,int $actor):array
    {
        if(!$this->nativePreferences)return CompanyCalendarPreferences::readInOwnedTransaction($this->db,$this->p,$issuer);
        require_once __DIR__.'/NativeCompanyCalendarPreferences.php';$snapshot=(new NativeCompanyCalendarPreferences($this->db,$this->p))->valuesInOwnedTransaction($issuer,$actor);
        return ['revision'=>$snapshot['revision'],'values'=>array_replace($snapshot['calendar_values'],$snapshot['values']),'native_snapshot'=>$snapshot];
    }
    private function capture(int $issuer,int $actor,array $f):array
    {
        return$this->transaction(function()use($issuer,$actor,$f){$acl=new IssuerResourceAccess($this->db,$this->p);$acl->lockFinancialIdentity($issuer,$actor);CompanyCalendarPreferences::authorize($this->db,$this->p,$issuer,$actor);$prefs=$this->preferences($issuer,$actor);$this->clientFilter($issuer,$actor,$f['client_id']);$available=[];foreach(CompanyCalendarSources::definitions()as$source=>$definition)if($prefs['values'][$definition['toggle']]==='1'&&CompanyCalendarSources::allowed($this->db,$this->p,$issuer,$actor,$source))$available[]=$source;$ids=$this->candidates($issuer,$actor,$f,$prefs['values']);$frames=[];foreach($ids as$source=>$list)foreach($list as$id)$frames[$source.':'.$id]=$this->sourceFrame($issuer,$actor,$source,$id);if($this->preferences($issuer,$actor)!==$prefs)throw new DomainException('company_calendar_stale');$this->clientFilter($issuer,$actor,$f['client_id']);foreach(array_keys($ids)as$source)if(!CompanyCalendarSources::allowed($this->db,$this->p,$issuer,$actor,$source))throw new DomainException('company_calendar_denied');CompanyCalendarPreferences::authorize($this->db,$this->p,$issuer,$actor);return['issuer'=>$issuer,'actor'=>$actor,'filters'=>$f,'preferences'=>$prefs,'ids'=>$ids,'sources'=>$frames,'available'=>$available];});
    }
    private static function title($v):string{if(!is_string($v)||strlen($v)<1||strlen($v)>240||preg_match('//u',$v)!==1||preg_match('/[\x00-\x1f\x7f]/',$v))throw new DomainException('company_calendar_snapshot_invalid');return$v;}
    private function hydrate(int $issuer,int $actor,string $source,int $id):array
    {
        if(!CompanyCalendarSources::allowed($this->db,$this->p,$issuer,$actor,$source))throw new DomainException('company_calendar_denied');
        if($source==='invoices'){$r=(new CompanyInvoices($this->db,$this->p))->document($issuer,$actor,$id)->data();if($r['invoice_id']!==$id||$r['issuer_id']!==$issuer)throw new DomainException('company_calendar_snapshot_invalid');return['title'=>self::title('Fatura '.$r['number']),'date'=>self::date($r['due_date']),'client_id'=>IssuerPolicy::id($r['recipient']['client_id']),'state'=>(string)$r['status'],'path'=>'company_invoices?invoice='.$id];}
        if(in_array($source,['estimates','proposals'],true)){$r=(new CompanySales($this->db,$this->p))->document($issuer,$actor,$id);if((int)$r['id']!==$id||(int)$r['issuer_id']!==$issuer||$r['kind']!==$source||(int)$r['context']['issuer_id']!==$issuer||(int)$r['context']['recipient']['client_id']!==(int)$r['client_id'])throw new DomainException('company_calendar_snapshot_invalid');return['title'=>self::title(($source==='estimates'?'Orçamento ':'Proposta ').$r['formatted_number']),'date'=>self::date($r['payload']['due_date']),'client_id'=>IssuerPolicy::id($r['client_id']),'state'=>$r['state'],'path'=>'company_sales?kind='.$source.'&document='.$id];}
        $service=match($source){'projects'=>new CompanyProjects($this->db,$this->p),'project_tasks'=>new CompanyTasks($this->db,$this->p),'standalone_tasks'=>new CompanyStandaloneTasks($this->db,$this->p),'contracts'=>new CompanyContracts($this->db,$this->p)};$r=$service->get($issuer,$actor,$id);if((int)$r['id']!==$id||(int)$r['issuer_id']!==$issuer||(int)$r['revision']<1)throw new DomainException('company_calendar_snapshot_invalid');$client=isset($r['client_id'])?IssuerPolicy::id($r['client_id']):null;
        if($source==='project_tasks'){$parent=(new CompanyProjects($this->db,$this->p))->get($issuer,$actor,$r['project_id']);$client=IssuerPolicy::id($parent['client_id']);}
        if($source==='contracts'&&$r['number']!==null){$s=json_decode($r['snapshot_json'],true,32,JSON_THROW_ON_ERROR);foreach(['issuer_id'=>$issuer,'number'=>(int)$r['number']]as$k=>$v)if((int)($s[$k]??0)!==$v)throw new DomainException('company_calendar_snapshot_invalid');if((int)($s['client']['id']??0)!==$client)throw new DomainException('company_calendar_snapshot_invalid');foreach(['name','start_date','due_date']as$k)if(($s[$k]??null)!==$r[$k])throw new DomainException('company_calendar_snapshot_invalid');}
        // Canonical portal tasks use 0 for awaiting staff assignment; it is not a staff identity.
        $path=match($source){'projects'=>'company_projects?project='.$id,'project_tasks'=>'company_tasks?project='.(int)$r['project_id'].'&task='.$id,'standalone_tasks'=>'company_standalone_tasks?task='.$id,'contracts'=>'company_contracts?contract='.$id};return['title'=>self::title($r['name']),'date'=>self::date($r['due_date']),'client_id'=>$client,'state'=>(string)$r['status'],'assignee_id'=>isset($r['assignee_id'])?($source==='project_tasks'&&in_array($r['assignee_id'],[0,'0'],true)?null:IssuerPolicy::id($r['assignee_id'])):null,'path'=>$path];
    }
    public function page($issuer,$actor,array $input):array
    {
        $this->frame=null;$issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$f=self::filters($input);CompanyCalendarPreferences::authorize($this->db,$this->p,$issuer,$actor);$frame=$this->capture($issuer,$actor,$f);$v=$frame['preferences']['values'];$events=[];$counts=[];
        foreach($frame['ids']as$source=>$ids){$counts[$source]=0;foreach($ids as$id){$e=$this->hydrate($issuer,$actor,$source,$id);if($e['date']<$f['start']||$e['date']>$f['end']||($f['client_id']!==null&&$e['client_id']!==$f['client_id'])||(in_array($source,['project_tasks','standalone_tasks'],true)&&$v['calendar_only_assigned_tasks']==='1'&&($e['assignee_id']??null)!==$actor))throw new DomainException('company_calendar_stale');if($f['title']!==''&&stripos($e['title'],$f['title'])===false)continue;$events[]=['id'=>$source.':'.$id,'source'=>$source,'title'=>$e['title'],'date'=>$e['date'],'state'=>$e['state'],'color'=>$v[CompanyCalendarSources::definitions()[$source]['color']],'path'=>$e['path']];$counts[$source]++;}}
        usort($events,static fn($a,$b)=>[$a['date'],$a['source'],$a['id']]<=>[$b['date'],$b['source'],$b['id']]);$this->frame=$frame;$this->confirm($issuer,$actor);return['revision'=>$frame['preferences']['revision'],'preferences'=>$v,'filters'=>$f,'available_sources'=>$frame['available'],'events'=>$events,'counts'=>$counts,'total'=>count($events)];
    }
    /** Call again after encoding/rendering, immediately before any response bytes leave the controller. */
    public function confirm($issuer,$actor):void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$f=$this->frame;if(!$f||$f['issuer']!==$issuer||$f['actor']!==$actor)throw new DomainException('company_calendar_denied');if($this->capture($issuer,$actor,$f['filters'])!==$f)throw new DomainException('company_calendar_stale');
    }
}
