<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/CompanyCalendarSources.php';
/** Own calendar presentation/opt-ins, including principal; never reads legacy options. */
final class CompanyCalendarPreferences
{
    public const NODE='company_calendar';
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public static function defaults():array{return ['calendar_events_limit'=>'4','default_view_calendar'=>'dayGridMonth','calendar_first_day'=>'1','calendar_only_assigned_tasks'=>'1','show_invoices_on_calendar'=>'0','show_estimates_on_calendar'=>'0','show_proposals_on_calendar'=>'0','show_projects_on_calendar'=>'0','show_tasks_on_calendar'=>'0','show_contracts_on_calendar'=>'0','calendar_invoice_color'=>'#2563EB','calendar_estimate_color'=>'#7C3AED','calendar_proposal_color'=>'#0891B2','calendar_project_color'=>'#059669','calendar_task_color'=>'#BE123C','calendar_contract_color'=>'#B45309'];}
    public static function prepare(array $values):array
    {
        $defaults=self::defaults();$keys=array_keys($values);sort($keys);$expected=array_keys($defaults);sort($expected);if($keys!==$expected)throw new DomainException('company_calendar_preferences_invalid');$out=[];
        foreach($defaults as$key=>$default){$v=$values[$key];if(!is_string($v))throw new DomainException('company_calendar_preferences_invalid');
            $ok=match($key){'calendar_events_limit'=>preg_match('/^(?:[1-9]|[1-4][0-9]|50)$/D',$v)===1,'default_view_calendar'=>in_array($v,['dayGridMonth','dayGridWeek','dayGridDay','timeGridWeek','timeGridDay'],true),'calendar_first_day'=>preg_match('/^[0-6]$/D',$v)===1,default=>str_ends_with($key,'_color')?preg_match('/^#[A-Fa-f0-9]{6}$/D',$v)===1:in_array($v,['0','1'],true)};
            if(!$ok)throw new DomainException('company_calendar_preferences_invalid');$out[$key]=str_ends_with($key,'_color')?strtoupper($v):$v;
        }return$out;
    }
    public function tables():array{return ['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_audit'];}
    public static function authorize($db,string $p,int $issuer,int $actor,bool $configure=false):void
    {
        $acl=new IssuerResourceAccess($db,$p);if(!$acl->can($issuer,$actor,'calendar','view')||($configure&&(!$acl->can($issuer,$actor,'calendar','configure')||!$acl->can($issuer,$actor,'settings','view')||!$acl->can($issuer,$actor,'settings','edit'))))throw new DomainException('company_calendar_denied');
    }
    private function row(int $issuer):array{$r=IssuerStorage::query($this->db,'SELECT id,active,settings_json FROM '.$this->p.'issuers_registry WHERE id=? FOR UPDATE',[$issuer])->row_array();if(!$r||(int)$r['active']!==1)throw new DomainException('company_calendar_denied');return$r;}
    private static function document(string $json):object
    {
        try{$doc=json_decode($json,false,32,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new DomainException('company_calendar_preferences_invalid');}if(!$doc instanceof stdClass)throw new DomainException('company_calendar_preferences_invalid');
        if(property_exists($doc,self::NODE)){$n=$doc->{self::NODE};if(!$n instanceof stdClass||array_keys(get_object_vars($n))!==['format','revision','values']||$n->format!==1||!is_int($n->revision)||$n->revision<1||$n->revision>2147483647||!$n->values instanceof stdClass)throw new DomainException('company_calendar_preferences_invalid');$v=get_object_vars($n->values);if(self::prepare($v)!==$v)throw new DomainException('company_calendar_preferences_invalid');}return$doc;
    }
    /** Internal only: caller proves current actor and source authority. */
    public static function readInOwnedTransaction($db,string $p,int $issuer):array
    {
        $issuer=IssuerPolicy::id($issuer);IssuerTransaction::assertOwned($db,$p,['issuers_registry']);$self=new self($db,$p);$row=$self->row($issuer);$doc=self::document((string)$row['settings_json']);$n=$doc->{self::NODE}??null;
        return ['issuer_id'=>$issuer,'revision'=>$n?->revision??0,'values'=>$n?get_object_vars($n->values):self::defaults(),'frame_hash'=>hash('sha256',json_encode([(int)$row['id'],(int)$row['active'],(string)$row['settings_json']],JSON_THROW_ON_ERROR))];
    }
    public static function confirmInOwnedTransaction($db,string $p,int $issuer,array $expected):void{if(self::readInOwnedTransaction($db,$p,$issuer)!==$expected)throw new RuntimeException('issuer_calendar_preferences_unconfirmed');}
    public function page($issuer,$actor):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);self::authorize($this->db,$this->p,$issuer,$actor);return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($issuer,$actor){(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);self::authorize($this->db,$this->p,$issuer,$actor);$v=self::readInOwnedTransaction($this->db,$this->p,$issuer);self::confirmInOwnedTransaction($this->db,$this->p,$issuer,$v);self::authorize($this->db,$this->p,$issuer,$actor);return['revision'=>$v['revision'],'values'=>$v['values']];});
    }
    public function save($issuer,$actor,int $revision,array $values):void
    {
        IssuerTransaction::run($this->db,$this->p,$this->tables(),fn()=>$this->saveInOwnedTransaction($issuer,$actor,$revision,$values));
    }
    public function saveInOwnedTransaction($issuer,$actor,int $revision,array $values):void
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$values=self::prepare($values);if($revision<0||$revision>=2147483647)throw new DomainException('company_calendar_preferences_invalid');self::authorize($this->db,$this->p,$issuer,$actor,true);
(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);self::authorize($this->db,$this->p,$issuer,$actor,true);foreach(CompanyCalendarSources::definitions()as$source=>$definition)if($values[$definition['toggle']]==='1'&&!CompanyCalendarSources::available($this->db,$this->p,$source))throw new DomainException('company_calendar_source_unavailable');$old=self::readInOwnedTransaction($this->db,$this->p,$issuer);if($old['revision']!==$revision)throw new DomainException('company_calendar_preferences_stale');if($old['revision']>0&&$old['values']===$values){self::confirmInOwnedTransaction($this->db,$this->p,$issuer,$old);self::authorize($this->db,$this->p,$issuer,$actor,true);return;}
            $doc=self::document((string)$this->row($issuer)['settings_json']);$doc->{self::NODE}=(object)['format'=>1,'revision'=>$revision+1,'values'=>(object)$values];$json=json_encode($doc,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);IssuerStorage::query($this->db,'UPDATE '.$this->p.'issuers_registry SET settings_json=? WHERE id=?',[$json,$issuer]);$expected=['issuer_id'=>$issuer,'revision'=>$revision+1,'values'=>$values,'frame_hash'=>hash('sha256',json_encode([$issuer,1,$json],JSON_THROW_ON_ERROR))];self::confirmInOwnedTransaction($this->db,$this->p,$issuer,$expected);
            $now=gmdate('Y-m-d H:i:s');IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,NULL,?)',[$actor,'company_calendar_preferences_saved',$issuer,$now]);$id=(int)$this->db->insert_id();$connection=$this->db->pdo??$this->db->conn_id??null;$sqlite=$connection instanceof PDO&&$connection->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';$affected=IssuerStorage::query($this->db,$sqlite?'SELECT changes() n':'SELECT ROW_COUNT() n')->row_array();if((string)($affected['n']??'')!=='1')throw new RuntimeException('issuer_calendar_preferences_unconfirmed');if($id<1||count(IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND actor_id=? AND event=? AND issuer_id=? AND subject_id IS NULL AND created_at=?',[$id,$actor,'company_calendar_preferences_saved',$issuer,$now])->result_array())!==1)throw new RuntimeException('issuer_calendar_preferences_unconfirmed');self::confirmInOwnedTransaction($this->db,$this->p,$issuer,$expected);self::authorize($this->db,$this->p,$issuer,$actor,true);
    }

}
