<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';

/** Exact native contact file-delete policy, private to each owner including the principal. */
final class CompanyProjectFileSettings
{
    public const KEY='allow_contact_to_delete_files';
    private const NODE='project_file_preferences';
    public function __construct(private$db,private string$p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public static function readerTables():array{return ['issuers_registry'];}
    public function tables():array{return ['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_audit'];}
    private function row(int$issuer):array{$row=IssuerStorage::query($this->db,'SELECT id,active,settings_json FROM '.$this->p.'issuers_registry WHERE id=? FOR UPDATE',[$issuer])->row_array();if(!$row||(int)$row['active']!==1)throw new DomainException('company_project_file_settings_denied');return$row;}
    private static function document(string$json):object
    {
        try{$doc=json_decode($json,false,32,JSON_THROW_ON_ERROR);}catch(Throwable$e){throw new DomainException('company_project_file_settings_invalid');}
        if(!$doc instanceof stdClass)throw new DomainException('company_project_file_settings_invalid');
        if(property_exists($doc,self::NODE)){$pref=$doc->{self::NODE};if(!$pref instanceof stdClass||array_keys(get_object_vars($pref))!==['format','revision',self::KEY]||$pref->format!==1||!is_int($pref->revision)||$pref->revision<1||$pref->revision>2147483647||!in_array($pref->{self::KEY},['0','1'],true))throw new DomainException('company_project_file_settings_invalid');}
        return$doc;
    }
    /** Caller must prove contact/project/file ownership and native permissions independently. */
    public static function allowContactDeleteInOwnedTransaction($db,string$prefix,int$issuer):bool
    {
        $self=new self($db,$prefix);$issuer=IssuerPolicy::id($issuer);IssuerTransaction::assertOwned($db,$prefix,self::readerTables());$doc=self::document((string)$self->row($issuer)['settings_json']);return isset($doc->{self::NODE})&&$doc->{self::NODE}->{self::KEY}==='1';
    }
    private function authorize(int$issuer,int$actor,string$cap):void{if(!(new IssuerResourceAccess($this->db,$this->p))->can($issuer,$actor,'settings',$cap))throw new DomainException('company_project_file_settings_denied');}
    public function page($issuer,$actor):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$this->authorize($issuer,$actor,'view');return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($issuer,$actor){$this->authorize($issuer,$actor,'view');$doc=self::document((string)$this->row($issuer)['settings_json']);$pref=$doc->{self::NODE}??null;return ['revision'=>$pref?->revision??0,self::KEY=>$pref?->{self::KEY}??'0'];});
    }
    public function save($issuer,$actor,int$revision,array$settings):void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);if(array_keys($settings)!==[self::KEY]||!in_array($settings[self::KEY],['0','1'],true)||$revision<0)throw new DomainException('company_project_file_settings_invalid');$this->authorize($issuer,$actor,'edit');
        IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($issuer,$actor,$revision,$settings){$this->authorize($issuer,$actor,'edit');$doc=self::document((string)$this->row($issuer)['settings_json']);$old=$doc->{self::NODE}??null;if(($old?->revision??0)!==$revision)throw new DomainException('company_project_file_settings_stale');if($old&&$old->{self::KEY}===$settings[self::KEY])return;if($revision>=2147483647)throw new DomainException('company_project_file_settings_invalid');$doc->{self::NODE}=(object)['format'=>1,'revision'=>$revision+1,self::KEY=>$settings[self::KEY]];$json=json_encode($doc,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);IssuerStorage::query($this->db,'UPDATE '.$this->p.'issuers_registry SET settings_json=? WHERE id=?',[$json,$issuer]);if((string)$this->row($issuer)['settings_json']!==$json)throw new RuntimeException('issuer_project_file_settings_write_unconfirmed');$now=date('Y-m-d H:i:s');IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,NULL,?)',[$actor,'company_project_file_settings_saved',$issuer,$now]);$id=(int)$this->db->insert_id();if(!IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND actor_id=? AND event=? AND issuer_id=? AND subject_id IS NULL AND created_at=?',[$id,$actor,'company_project_file_settings_saved',$issuer,$now])->row_array())throw new RuntimeException('issuer_project_file_settings_write_unconfirmed');$this->authorize($issuer,$actor,'edit');});
    }
}
