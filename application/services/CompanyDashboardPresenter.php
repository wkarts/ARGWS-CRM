<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerResourceAccess.php';

/** Read-only native dashboard projection. Never resolves a company from caller data or globals. */
final class CompanyDashboardPresenter
{
    private IssuerResourceAccess $acl;
    private array $permissions=[];
    private ?array $frame=null;
    private $calendar=null;
    private ?int $primary=null;
    private const FEATURES=['invoices','payments','credit_notes','expenses','reports','projects','tasks','leads','tickets','contracts','calendar','estimates','proposals','activity_log'];
    public function __construct(private $db,private string $prefix,private int $issuer,private int $actor)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('Invalid prefix');
        IssuerPolicy::id($issuer);IssuerPolicy::id($actor);
        $this->acl=new IssuerResourceAccess($db,$prefix);
    }
    private function permissions():array
    {
        if(!$this->acl->member($this->issuer,$this->actor))throw new DomainException('company_dashboard_denied');
        $out=[];foreach(self::FEATURES as $feature)$out[$feature]=$this->acl->can($this->issuer,$this->actor,$feature,'view');$out['administrative']=$this->acl->administrative($this->issuer,$this->actor);return $out;
    }
    private function rows(string $sql,array $args=[]):array
    {return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function ready(array $tables):bool
    {foreach($tables as $table)if(!$this->db->table_exists($this->prefix.$table)||!IssuerStorage::fieldExists($this->db,'issuer_id',$this->prefix.$table))return false;return true;}
    private static function missing(string $reason):array{return ['status'=>$reason,'data'=>null];}
    private static function available(array $data):array{return ['status'=>'available','data'=>$data];}
    private function section(array $features,array $tables,callable $read):array
    {
        foreach($features as $feature)if(!$this->permissions[$feature])return self::missing('denied');
        if(!$this->ready($tables))throw new DomainException('company_dashboard_schema_unavailable');
        return self::available($read()); // SQL/storage/verification errors never become zero totals.
    }
    private function statuses(string $table,array $allowed):array
    {
        $result=array_fill_keys($allowed,0);
        foreach($this->rows('SELECT status,COUNT(*) AS n FROM '.$this->prefix.$table.' WHERE issuer_id=? GROUP BY status',[$this->issuer]) as $row){
            if(!array_key_exists((string)$row['status'],$result)||!ctype_digit((string)$row['n']))throw new DomainException('company_dashboard_snapshot_invalid');
            $result[(string)$row['status']]=(int)$row['n'];
        }return $result;
    }
    private function projectStatusCounts():array
    {
        require_once __DIR__.'/NativeCompanyProjectRead.php';
        return array_replace(array_fill_keys(['active','completed','cancelled'],0),(new NativeCompanyProjectRead($this->db,$this->prefix))->ownedStatusCounts($this->issuer,$this->actor));
    }
    public function counters(?string $today=null):array
    {
        $this->frame=null;$this->calendar=null;$this->permissions=$this->permissions();$this->primary=$this->acl->primaryId();
        $today=$today??gmdate('Y-m-d');$date=DateTimeImmutable::createFromFormat('!Y-m-d',$today,new DateTimeZone('UTC'));
        if(!$date||$date->format('Y-m-d')!==$today)throw new DomainException('company_dashboard_date_invalid');
        $widgets=[];
        $widgets['invoices']=$this->section(['invoices'],['invoices'],fn():array=>$this->statuses('invoices',['1','2','3','4','5','6']));
        $widgets['projects']=$this->section(['projects'],['company_projects'],fn():array=>$this->projectStatusCounts());
        // A missing project grant hides project tasks, not separately owned standalone tasks.
        $widgets['tasks']=$this->section(['tasks'],['company_standalone_tasks'],function():array{
            $project=null;if($this->permissions['projects']){if(!$this->ready(['company_tasks']))throw new DomainException('company_dashboard_schema_unavailable');$project=$this->statuses('company_tasks',['todo','in_progress','done']);}
            $standalone=$this->statuses('company_standalone_tasks',['todo','in_progress','done']);
            return ['project'=>$project,'standalone'=>$standalone,'total'=>array_sum($project??[])+array_sum($standalone),'unfinished'=>($project['todo']??0)+($project['in_progress']??0)+$standalone['todo']+$standalone['in_progress']];
        });
        $widgets['tickets']=$this->section(['tickets'],['company_tickets'],fn():array=>$this->statuses('company_tickets',['open','resolved','closed']));
        $widgets['leads']=$this->section(['leads'],['company_leads','company_lead_stages'],function():array{
            $rows=$this->rows('SELECT s.id,s.name,COUNT(l.id) AS n FROM '.$this->prefix.'company_lead_stages s LEFT JOIN '.$this->prefix.'company_leads l ON l.issuer_id=s.issuer_id AND l.stage_id=s.id AND l.archived=0 WHERE s.issuer_id=? GROUP BY s.id,s.name ORDER BY s.id',[$this->issuer]);
            $counts=$this->rows('SELECT COUNT(*) AS total,COALESCE(SUM(CASE WHEN converted_client_id IS NOT NULL THEN 1 ELSE 0 END),0) AS converted FROM '.$this->prefix.'company_leads WHERE issuer_id=? AND archived=0',[$this->issuer])[0];
            return ['stages'=>$rows,'total'=>(int)$counts['total'],'converted'=>(int)$counts['converted']];
        });
        $this->legacyCounters($widgets);
        $this->frame=['issuer_id'=>$this->issuer,'actor_id'=>$this->actor,'permissions'=>$this->permissions,'primary_id'=>$this->primary];
        $this->confirm();
        return ['issuer_id'=>$this->issuer,'actor_id'=>$this->actor,'as_of'=>$today,'widgets'=>$widgets,'permissions'=>$this->permissions,'read_only'=>true];
    }
    /** Native legacy rows belong exclusively to the persisted principal, never to a secondary issuer. */
    private function legacyCounters(array &$widgets):void
    {
        if($this->issuer!==$this->primary)return;
        require_once __DIR__.'/ClientIssuerScope.php';require_once __DIR__.'/CustomerRelationBoundary.php';require_once __DIR__.'/TaskClientBoundary.php';require_once __DIR__.'/InvoiceIssuerScope.php';
        if($this->permissions['projects']){
            $map=['1'=>'legacy_1','2'=>'legacy_2','3'=>'legacy_3','4'=>'legacy_4','5'=>'legacy_5'];
            foreach($this->rows('SELECT p.status,COUNT(*) n FROM '.$this->prefix.'projects p WHERE '.\app\services\CustomerRelationBoundary::sql($this->db,$this->prefix,'projects','p').' GROUP BY p.status') as $r){
                $key=$map[(string)$r['status']]??'legacy_status_'.$r['status'];$widgets['projects']['data'][$key]=($widgets['projects']['data'][$key]??0)+(int)$r['n'];
            }
        }
        if($this->permissions['tasks']&&$this->permissions['projects']){
            foreach($this->rows('SELECT t.status,COUNT(*) n FROM '.$this->prefix.'tasks t WHERE '.\app\services\TaskClientBoundary::legacySql($this->db,$this->prefix,'t').' GROUP BY t.status') as $r){$widgets['tasks']['data']['total']+=(int)$r['n'];if((int)$r['status']!==5)$widgets['tasks']['data']['unfinished']+=(int)$r['n'];}
        }
        if($this->permissions['tickets']){
            foreach($this->rows('SELECT t.status,COUNT(*) n FROM '.$this->prefix.'tickets t WHERE t.merged_ticket_id IS NULL AND '.\app\services\CustomerRelationBoundary::sql($this->db,$this->prefix,'tickets','t').' GROUP BY t.status') as $r)$widgets['tickets']['data']['legacy_'.(int)$r['status']]=(int)$r['n'];
        }
        if($this->permissions['leads']){
            $leadBoundary=\app\services\ClientIssuerScope::legacySql($this->db,$this->prefix,'l','client_id');
            $legacy=$this->rows('SELECT s.id,s.name,COUNT(l.id) n FROM '.$this->prefix.'leads_status s LEFT JOIN '.$this->prefix.'leads l ON l.status=s.id AND ('.$leadBoundary.') GROUP BY s.id,s.name ORDER BY s.id');
            foreach($legacy as $row){$row['id']='legacy_'.$row['id'];$widgets['leads']['data']['stages'][]=$row;}
            // Keep the native top-stat ownership and junk predicates, independently of the status chart.
            $owner=$this->permissions['administrative']?'1=1':'(l.addedfrom=? OR l.assigned=?)';$ownerArgs=$this->permissions['administrative']?[]:[$this->actor,$this->actor];
            $converted=$this->permissions['administrative']?'l.status=1':'l.status=1 AND l.junk=0';
            $counts=$this->rows('SELECT COALESCE(SUM(CASE WHEN l.junk=0 THEN 1 ELSE 0 END),0) total,COALESCE(SUM(CASE WHEN '.$converted.' THEN 1 ELSE 0 END),0) converted FROM '.$this->prefix.'leads l WHERE ('.$leadBoundary.') AND '.$owner,$ownerArgs)[0];
            $widgets['leads']['data']['total']+=(int)$counts['total'];$widgets['leads']['data']['converted']+=(int)$counts['converted'];
        }
    }
    public function snapshot(?string $today=null):array
    {
        $snapshot=$this->counters($today);$date=new DateTimeImmutable($snapshot['as_of'],new DateTimeZone('UTC'));
        $native=$this->nativeView($snapshot['widgets'],$date);
        if($native['invoices_total']!==null)$snapshot['widgets']['invoices']=self::available($native['invoices_total']['counts']);
        $this->confirm();return $snapshot+$native;
    }
    private function nativeView(array $widgets,DateTimeImmutable $date):array
    {
        require_once __DIR__.'/NativeCompanyAuxiliaryUi.php';
        require_once __DIR__.'/NativeCompanyCalendar.php';
        require_once __DIR__.'/NativeCompanyInvoiceRead.php';
        require_once __DIR__.'/CompanyDashboardPayments.php';
        require_once __DIR__.'/NativeCompanyDashboardPreferences.php';
        $preferences=(new NativeCompanyDashboardPreferences($this->db,$this->prefix,$this->issuer,$this->actor))->read();
        $aux=new NativeCompanyAuxiliaryUi($this->db,$this->prefix,$this->issuer,$this->actor);
        $view=['todos'=>$aux->todos(false,10),'todos_finished'=>$aux->todos(true,5),
            'staff_announcements'=>$aux->announcements(20),'total_undismissed_announcements'=>$aux->undismissedAnnouncementsCount(),
            'activity_log'=>$this->permissions['reports']?$aux->activity(20):[],
            'user_dashboard_visibility'=>json_encode($preferences['visibility'],JSON_THROW_ON_ERROR)];
        $view+=$this->permissions['calendar']?NativeCompanyCalendar::dashboard($this->db,$this->prefix,$this->issuer,$this->actor):['upcoming_events'=>[],'upcoming_events_next_week'=>0,'google_ids_calendars'=>[]];
        $currencies=$this->rows('SELECT * FROM '.$this->prefix.'currencies ORDER BY id');$base=null;
        foreach($currencies as $currency)if((int)$currency['isdefault']===1){if($base!==null)throw new DomainException('company_dashboard_currency_required');$base=(object)$currency;}
        if($base===null)throw new DomainException('company_dashboard_currency_required');
        $view['currencies']=$currencies;$view['base_currency']=$base;
        $summary=$this->permissions['invoices']?(new NativeCompanyInvoiceRead($this->db,$this->prefix))->summary($this->issuer,$this->actor,$base->id,[(int)$date->format('Y')]):null;
        $percent=['invoices'=>$summary['percent_by_status']??[]];$states=['open','accepted','declined','cancelled','converting','converted'];
        foreach(['estimates','proposals'] as $kind){
            $counts=array_fill_keys($states,0);
            if($this->permissions[$kind])foreach($this->rows('SELECT state,COUNT(*) n FROM '.$this->prefix.'company_sales_documents WHERE issuer_id=? AND kind=? GROUP BY state',[$this->issuer,$kind]) as $row){
                if(!array_key_exists($row['state'],$counts))throw new DomainException('company_dashboard_snapshot_invalid');$counts[$row['state']]=(int)$row['n'];
            }
            $total=array_sum($counts);$percent[$kind]=[];foreach($counts as $state=>$count)$percent[$kind][$state]=['total'=>$total,'total_by_status'=>$count,'percent'=>$total?number_format(100*$count/$total,2,'.',''):0];
        }
        $view['estimate_statuses']=$states;$view['proposal_statuses']=$states;
        $view['projects_status_stats']=json_encode(self::chart($widgets['projects']['data']??[],['active'=>'Ativo','not_started'=>'Não iniciado','in_progress'=>'Em progresso','on_hold'=>'Em espera','completed'=>'Concluído','cancelled'=>'Cancelado','legacy_1'=>'Não iniciado (legado)','legacy_2'=>'Em progresso (legado)','legacy_3'=>'Em espera (legado)','legacy_4'=>'Concluído (legado)','legacy_5'=>'Cancelado (legado)']),JSON_THROW_ON_ERROR);
        $leadCounts=[];$leadLabels=[];foreach($widgets['leads']['data']['stages']??[] as $stage){$leadCounts[(string)$stage['id']]=(int)$stage['n'];$leadLabels[(string)$stage['id']]=$stage['name'];}
        $view['leads_status_stats']=json_encode(self::chart($leadCounts,$leadLabels),JSON_THROW_ON_ERROR);
        $ticketChart=self::chart(isset($widgets['tickets']['data']['open'])?['open'=>$widgets['tickets']['data']['open']]:[],['open'=>'Aberto']);
        $view['tickets_reply_by_status_no_json']=$ticketChart;$view['tickets_reply_by_status']=json_encode($ticketChart,JSON_THROW_ON_ERROR);
        // Historical owned tickets had no department; this is an actual unassigned population, not a fabricated department.
        $departmentRows=[];
        if($this->permissions['tickets']){
            if(IssuerStorage::fieldExists($this->db,'department_id',$this->prefix.'company_tickets')){
                $departmentRows=$this->rows('SELECT d.id,d.name,COUNT(t.id) n FROM '.$this->prefix.'company_tickets t LEFT JOIN '.$this->prefix.'company_ticket_departments d ON d.issuer_id=t.issuer_id AND d.id=t.department_id WHERE t.issuer_id=? AND t.status=? GROUP BY d.id,d.name',[$this->issuer,'open']);
            }else{$departmentRows=[['id'=>'unassigned','name'=>'Sem departamento','n'=>$widgets['tickets']['data']['open']]];}
        }
        $counts=[];$labels=[];foreach($departmentRows as $row){$id=$row['id']??'unassigned';$counts[$id]=(int)$row['n'];$labels[$id]=$row['name']??'Sem departamento';}
        $departmentChart=self::chart($counts,$labels);$view['tickets_awaiting_reply_by_department_no_json']=$departmentChart;$view['tickets_awaiting_reply_by_department']=json_encode($departmentChart,JSON_THROW_ON_ERROR);
        $view['weekly_payment_stats']=json_encode($this->permissions['invoices']&&$this->permissions['payments']?(new CompanyDashboardPayments($this->db,$this->prefix))->read($this->issuer,$this->actor,$base->id,'weekly',$date->format('Y-m-d')):null,JSON_THROW_ON_ERROR);
        $view['tickets_report']=$this->permissions['tickets']&&$this->permissions['reports']?$this->ticketReport('this_month',$date->format('Y-m-d')):[];
        $view['projects_activity']=$this->permissions['projects']?$this->projectActivity():[];
        $view['expiringContracts']=$this->permissions['contracts']?$this->expiringContracts($date):[];
        return ['view'=>$view,'widget_order'=>$preferences['order'],'auxiliary'=>['reminders_count'=>$aux->remindersCount()],
            'invoices_total'=>$summary,'finance_overview'=>$percent,'sales_statuses'=>[
                'open'=>['label'=>'Aberto','color'=>'info'],'accepted'=>['label'=>'Aceito','color'=>'success'],'declined'=>['label'=>'Recusado','color'=>'danger'],
                'cancelled'=>['label'=>'Cancelado','color'=>'muted'],'converting'=>['label'=>'Em conversão','color'=>'warning'],'converted'=>['label'=>'Convertido','color'=>'success']]];
    }
    private function projectActivity():array
    {
        require_once __DIR__.'/NativeCompanyResourceId.php';
        $rows=$this->rows('SELECT a.id,a.actor_id,a.event,a.created_at,p.id project_id,p.name project_name,s.firstname,s.lastname FROM '.$this->prefix.'issuers_audit a JOIN '.$this->prefix.'company_projects p ON p.id=a.subject_id AND p.issuer_id=a.issuer_id LEFT JOIN '.$this->prefix.'staff s ON s.staffid=a.actor_id WHERE a.issuer_id=? AND a.event IN (?,?,?,?) ORDER BY a.id DESC LIMIT 20',[$this->issuer,'company_project_created','company_project_updated','company_project_copied','company_project_deleted']);
        return array_map(static fn($r)=>['fullname'=>trim(($r['firstname']??'').' '.($r['lastname']??'')),'staff_id'=>(int)$r['actor_id'],'contact_id'=>0,'client_id'=>0,
            'dateadded'=>$r['created_at'],'description'=>$r['event'],'project_id'=>NativeCompanyResourceId::encode('company_project',(int)$r['project_id']),'project_name'=>$r['project_name'],'additional_data'=>''],$rows);
    }
    private function expiringContracts(DateTimeImmutable $date):array
    {
        require_once __DIR__.'/CompanyContracts.php';
        require_once __DIR__.'/NativeCompanyResourceId.php';
        $rows=$this->rows('SELECT id FROM '.$this->prefix.'company_contracts WHERE issuer_id=? AND status=? AND due_date>=? AND due_date<=? ORDER BY due_date,id LIMIT 501',[$this->issuer,'active',$date->format('Y-m-d'),$date->modify('+7 days')->format('Y-m-d')]);
        if(count($rows)>500)throw new DomainException('company_dashboard_period_limit');$out=[];$service=new CompanyContracts($this->db,$this->prefix);
        foreach($rows as $item){$row=$service->get($this->issuer,$this->actor,$item['id']);$snapshot=json_decode($row['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
            if(($snapshot['issuer_id']??null)!==$this->issuer||($snapshot['client']['id']??null)!==(int)$row['client_id'])throw new DomainException('company_dashboard_snapshot_invalid');
            $out[]=['id'=>NativeCompanyResourceId::encode('company_contract',(int)$row['id']),'subject'=>$row['name'],'client'=>(int)$row['client_id'],'client_name'=>$snapshot['client']['name'],'datestart'=>$row['start_date'],'dateend'=>$row['due_date']];
        }return $out;
    }
    private function assertPermissions():void
    {if($this->permissions()!==$this->permissions)throw new DomainException('company_dashboard_stale');}
    /** Revalidate after rendering and before returning any bytes, including chart/AJAX responses. */
    public function confirm():void
    {
        if($this->frame===null)throw new DomainException('company_dashboard_snapshot_required');
        $this->assertPermissions();if(array_key_exists('primary_id',$this->frame)&&$this->frame['primary_id']!==$this->acl->primaryId())throw new DomainException('company_dashboard_stale');if($this->calendar!==null)$this->calendar->confirm($this->issuer,$this->actor);
    }
    public function ticketReport(string $mode,?string $today=null):array
    {
        $this->permissions=$this->permissions();$this->primary=$this->acl->primaryId();
        $this->frame=['issuer_id'=>$this->issuer,'actor_id'=>$this->actor,'permissions'=>$this->permissions,'primary_id'=>$this->primary];
        if(!$this->permissions['tickets']||!$this->permissions['reports'])throw new DomainException('company_dashboard_denied');
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$today??gmdate('Y-m-d'),new DateTimeZone('UTC'));
        if(!$date||$date->format('Y-m-d')!==($today??gmdate('Y-m-d')))throw new DomainException('company_dashboard_date_invalid');
        [$start,$end]=match($mode){
            'this_week'=>[$date->modify('monday this week'),$date->modify('sunday this week')],
            'last_week'=>[$date->modify('monday last week'),$date->modify('sunday last week')],
            'this_month'=>[$date->modify('first day of this month'),$date->modify('last day of this month')],
            'last_month'=>[$date->modify('first day of last month'),$date->modify('last day of last month')],
            'this_year'=>[$date->setDate((int)$date->format('Y'),1,1),$date->setDate((int)$date->format('Y'),12,31)],
            'last_year'=>[$date->setDate((int)$date->format('Y')-1,1,1),$date->setDate((int)$date->format('Y')-1,12,31)],
            default=>throw new DomainException('company_dashboard_period_invalid')};
        $bounds=[$this->issuer,$start->format('Y-m-d').' 00:00:00',$end->format('Y-m-d').' 23:59:59'];
        $tickets=$this->rows('SELECT t.id,t.assignee_id,t.status,t.created_at,MIN(r.created_at) AS first_reply FROM '.$this->prefix.'company_tickets t LEFT JOIN '.$this->prefix.'company_ticket_replies r ON r.issuer_id=t.issuer_id AND r.ticket_id=t.id AND r.actor_id>0 WHERE t.issuer_id=? AND t.created_at>=? AND t.created_at<=? GROUP BY t.id,t.assignee_id,t.status,t.created_at ORDER BY t.id LIMIT 10001',$bounds);
        if(count($tickets)>10000)throw new DomainException('company_dashboard_period_limit');
        $replies=$this->rows('SELECT r.actor_id,COUNT(*) AS n FROM '.$this->prefix.'company_ticket_replies r JOIN '.$this->prefix.'company_tickets t ON t.issuer_id=r.issuer_id AND t.id=r.ticket_id WHERE r.issuer_id=? AND r.created_at>=? AND r.created_at<=? AND r.actor_id>0 GROUP BY r.actor_id',$bounds);
        // Separate legacy lineage, admitted only to the persisted principal and still constrained by client ownership.
        if($this->primary===$this->issuer){
            require_once __DIR__.'/ClientIssuerScope.php';require_once __DIR__.'/CustomerRelationBoundary.php';
            $boundary=\app\services\CustomerRelationBoundary::sql($this->db,$this->prefix,'tickets','t');
            $range=[$bounds[1],$bounds[2]];
            $legacy=$this->rows('SELECT t.ticketid AS id,t.assigned AS assignee_id,t.status,t.date AS created_at,MIN(r.date) AS first_reply FROM '.$this->prefix.'tickets t LEFT JOIN '.$this->prefix.'ticket_replies r ON r.ticketid=t.ticketid AND r.admin>0 WHERE '.$boundary.' AND t.date>=? AND t.date<=? GROUP BY t.ticketid,t.assigned,t.status,t.date ORDER BY t.ticketid LIMIT 10001',$range);
            foreach($legacy as &$row){$row['status']=match((int)$row['status']){1=>'open',5=>'closed',default=>'legacy_'.(int)$row['status']};}unset($row);
            $tickets=array_merge($tickets,$legacy);
            $replies=array_merge($replies,$this->rows('SELECT r.admin AS actor_id,COUNT(*) AS n FROM '.$this->prefix.'ticket_replies r JOIN '.$this->prefix.'tickets t ON t.ticketid=r.ticketid WHERE '.$boundary.' AND r.date>=? AND r.date<=? AND r.admin>0 GROUP BY r.admin',$range));
            if(count($tickets)>10000)throw new DomainException('company_dashboard_period_limit');
        }
        $result=[];$durations=[];
        $add=function(int $staff)use(&$result):void{
            if(isset($result[$staff]))return;
            $row=$this->rows('SELECT staffid,firstname,lastname FROM '.$this->prefix.'staff WHERE staffid=?',[$staff])[0]??null;
            if(!$row)throw new DomainException('company_dashboard_snapshot_invalid');
            $result[$staff]=(object)($row+['total_assigned'=>0,'total_open_tickets'=>0,'total_closed_tickets'=>0,'total_replies'=>0,'average_reply_time'=>'-']);
        };
        foreach($tickets as $ticket){$staff=(int)$ticket['assignee_id'];if($staff<1)continue;$add($staff);++$result[$staff]->total_assigned;
            if($ticket['status']==='open')++$result[$staff]->total_open_tickets;
            if($ticket['status']==='closed')++$result[$staff]->total_closed_tickets;
            if($ticket['first_reply']!==null){$seconds=strtotime($ticket['first_reply'].' UTC')-strtotime($ticket['created_at'].' UTC');if($seconds<0)throw new DomainException('company_dashboard_snapshot_invalid');$durations[$staff][]=$seconds;}}
        foreach($replies as $reply){$staff=(int)$reply['actor_id'];$add($staff);$result[$staff]->total_replies+=(int)$reply['n'];}
        foreach($durations as $staff=>$seconds){$avg=(int)floor(array_sum($seconds)/count($seconds));$result[$staff]->average_reply_time=$avg<60?'-':($avg<3600?intdiv($avg,60).' min':($avg<=345600?intdiv($avg,3600).' h':intdiv($avg,86400).' d'));}
        $this->confirm();ksort($result);return array_values($result);
    }
    public static function chart(array $states,array $labels):array
    {
        return ['labels'=>array_map(fn($key)=>(string)($labels[$key]??$key),array_keys($states)),'datasets'=>[['data'=>array_values($states),'backgroundColor'=>['#32c977','#2563eb','#64748b','#ef4444','#f59e0b','#7c3aed']]]];
    }
}
