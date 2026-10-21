<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';

/** Own creation defaults, including the principal. Never inherits legacy options. */
final class CompanyTaskPreferences
{
    public const STATUS='default_task_status';
    public const ASSIGN='new_task_auto_assign_current_member';
    private const NODE='task_creation_preferences';
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public function tables():array{return ['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_audit'];}
    public static function prepare(array $settings):array
    {
        if(array_keys($settings)!==[self::STATUS,self::ASSIGN]||!in_array($settings[self::STATUS],['todo','in_progress'],true)||!in_array($settings[self::ASSIGN],['0','1'],true))throw new DomainException('company_task_preferences_invalid');
        return $settings;
    }
    private function row(int $issuer):array
    {
        $row=IssuerStorage::query($this->db,'SELECT id,active,settings_json FROM '.$this->p.'issuers_registry WHERE id=? FOR UPDATE',[$issuer])->row_array();
        if(!$row||(int)$row['active']!==1)throw new DomainException('company_task_preferences_denied');return $row;
    }
    private static function document(string $json):object
    {
        try{$doc=json_decode($json,false,32,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new DomainException('company_task_preferences_invalid');}
        if(!$doc instanceof stdClass)throw new DomainException('company_task_preferences_invalid');
        if(property_exists($doc,self::NODE)){
            $v=$doc->{self::NODE};
            if(!$v instanceof stdClass||array_keys(get_object_vars($v))!==['format','revision',self::STATUS,self::ASSIGN]||$v->format!==1||!is_int($v->revision)||$v->revision<1||$v->revision>2147483647||!in_array($v->{self::STATUS},['todo','in_progress'],true)||!in_array($v->{self::ASSIGN},['0','1'],true))throw new DomainException('company_task_preferences_invalid');
        }
        return $doc;
    }
    /** Caller independently proves current tasks create/view authority. No session lookup. */
    public static function readInOwnedTransaction($db,string $p,int $issuer):array
    {
        $issuer=IssuerPolicy::id($issuer);IssuerTransaction::assertOwned($db,$p,['issuers_registry']);$self=new self($db,$p);$row=$self->row($issuer);$doc=self::document((string)$row['settings_json']);$v=$doc->{self::NODE}??null;
        return ['issuer_id'=>$issuer,'revision'=>$v?->revision??0,self::STATUS=>$v?->{self::STATUS}??'todo',self::ASSIGN=>$v?->{self::ASSIGN}??'1','frame_hash'=>hash('sha256',json_encode([(int)$row['id'],(int)$row['active'],(string)$row['settings_json']],JSON_THROW_ON_ERROR))];
    }
    public static function confirmInOwnedTransaction($db,string $p,int $issuer,array $expected):void
    {
        if(self::readInOwnedTransaction($db,$p,$issuer)!==$expected)throw new RuntimeException('issuer_task_preferences_unconfirmed');
    }
    private function authorize(int $issuer,int $actor,string $cap):void
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if(!$acl->can($issuer,$actor,'settings','view')||!$acl->can($issuer,$actor,'settings',$cap))throw new DomainException('company_task_preferences_denied');
    }
    public function page($issuer,$actor):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$this->authorize($issuer,$actor,'view');
        return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($issuer,$actor){(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,'view');$v=self::readInOwnedTransaction($this->db,$this->p,$issuer);$this->authorize($issuer,$actor,'view');return ['revision'=>$v['revision'],self::STATUS=>$v[self::STATUS],self::ASSIGN=>$v[self::ASSIGN]];});
    }
    public function save($issuer,$actor,int $revision,array $settings):void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);self::prepare($settings);if($revision<0||$revision>2147483647)throw new DomainException('company_task_preferences_invalid');$this->authorize($issuer,$actor,'edit');
        IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($issuer,$actor,$revision,$settings){
            (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,'edit');$row=$this->row($issuer);$doc=self::document((string)$row['settings_json']);$old=$doc->{self::NODE}??null;
            if(($old?->revision??0)!==$revision)throw new DomainException('company_task_preferences_stale');
            if($old&&$old->{self::STATUS}===$settings[self::STATUS]&&$old->{self::ASSIGN}===$settings[self::ASSIGN])return;
            if($revision>=2147483647)throw new DomainException('company_task_preferences_invalid');
            $doc->{self::NODE}=(object)['format'=>1,'revision'=>$revision+1,self::STATUS=>$settings[self::STATUS],self::ASSIGN=>$settings[self::ASSIGN]];$json=json_encode($doc,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'issuers_registry SET settings_json=? WHERE id=?',[$json,$issuer]);
            $expected=['issuer_id'=>$issuer,'revision'=>$revision+1,self::STATUS=>$settings[self::STATUS],self::ASSIGN=>$settings[self::ASSIGN],'frame_hash'=>hash('sha256',json_encode([$issuer,1,$json],JSON_THROW_ON_ERROR))];
            self::confirmInOwnedTransaction($this->db,$this->p,$issuer,$expected);
            $now=gmdate('Y-m-d H:i:s');IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,NULL,?)',[$actor,'company_task_preferences_saved',$issuer,$now]);$audit=(int)$this->db->insert_id();
            if($audit<1||count(IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND actor_id=? AND event=? AND issuer_id=? AND subject_id IS NULL AND created_at=?',[$audit,$actor,'company_task_preferences_saved',$issuer,$now])->result_array())!==1)throw new RuntimeException('issuer_task_preferences_unconfirmed');
            self::confirmInOwnedTransaction($this->db,$this->p,$issuer,$expected);$this->authorize($issuer,$actor,'edit');
        });
    }
}
