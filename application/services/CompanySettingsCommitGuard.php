<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';

/** Internal final-state proof for a settings transaction; never exposes stored values. */
final class CompanySettingsCommitGuard
{
    private $db;
    private string $prefix;
    private int $issuer;
    private int $actor;
    private string $capability;
    private bool $primary;
    private array $expected;
    private array $audits=[];
    private IssuerResourceAccess $access;

    public function __construct($db,string $prefix,int $issuer,int $actor,string $capability)
    {
        $this->db=$db;$this->prefix=$prefix;$this->issuer=$issuer;$this->actor=$actor;$this->capability=$capability;
        $this->access=new IssuerResourceAccess($db,$prefix);
        IssuerTransaction::assertOwned($db,$prefix,['issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff']);
        $this->authority();
        $primary=$this->access->primaryId();
        if($primary===null)throw new DomainException('company_settings_primary_unresolved');
        $this->primary=$primary===$issuer;
        if($this->primary)IssuerTransaction::assertOwned($db,$prefix,['options']);
        $this->expected=$this->frame();
    }
    private function rows(string $sql,array $params=[]):array
    {
        return IssuerStorage::query($this->db,$sql,$params)->result_array();
    }
    private static function normalize(array $row):array
    {
        return array_map(static fn($value)=>$value===null?null:(string)$value,$row);
    }
    private function authority():void
    {
        $this->access->lockFinancialIdentity($this->issuer,$this->actor);
        $this->rows('SELECT * FROM '.$this->prefix.'issuers_installation_identity WHERE id=1 FOR UPDATE');
        if(!$this->access->can($this->issuer,$this->actor,'settings',$this->capability))throw new DomainException('company_settings_access_denied');
    }
    private function frame():array
    {
        $issuer=$this->rows('SELECT * FROM '.$this->prefix.'issuers_registry WHERE id=? FOR UPDATE',[$this->issuer])[0]??null;
        if(!$issuer||(int)$issuer['active']!==1)throw new DomainException('company_settings_issuer_unavailable');
        $identity=$this->rows('SELECT * FROM '.$this->prefix.'issuers_installation_identity WHERE id=1 FOR UPDATE')[0]??null;
        if(!$identity)throw new DomainException('company_settings_primary_unresolved');
        $options=[];
        if($this->primary)foreach($this->rows('SELECT * FROM '.$this->prefix.'options ORDER BY name FOR UPDATE') as $row)$options[$row['name']]=self::normalize($row);
        ksort($options,SORT_STRING);
        return ['issuer'=>self::normalize($issuer),'identity'=>self::normalize($identity),'options'=>$options];
    }
    public function issuerFields(array $values):void
    {
        if(array_diff(array_keys($values),['settings_json','legal_name','tax_id','identity_status']))throw new LogicException('Invalid settings frame');
        foreach($values as $key=>$value)$this->expected['issuer'][$key]=$value===null?null:(string)$value;
    }
    /** Confirms value, requested autoload and every untouched column of an existing option. */
    public function writeOption(string $operation,string $name,string $value,$autoload=null):void
    {
        if(!$this->primary||!in_array($operation,['add','update','delete'],true))throw new LogicException('Invalid settings frame');
        $table=$this->prefix.'options';
        $old=$this->expected['options'][$name]??null;
        if($operation==='delete'){
            IssuerStorage::query($this->db,'DELETE FROM '.$table.' WHERE name=?',[$name]);
            $expected=null;
        }elseif($old!==null){
            $expected=$old;$expected['value']=$value;
            $set='value=?';$args=[$value];
            if($autoload!==null&&array_key_exists('autoload',$old)){$set.=',autoload=?';$args[]=(int)$autoload;$expected['autoload']=(string)(int)$autoload;}
            $args[]=$name;
            IssuerStorage::query($this->db,'UPDATE '.$table.' SET '.$set.' WHERE name=?',$args);
        }else{
            $hasAutoload=IssuerStorage::fieldExists($this->db,'autoload',$table);
            if($hasAutoload)IssuerStorage::query($this->db,'INSERT INTO '.$table.'(name,value,autoload) VALUES(?,?,?)',[$name,$value,$autoload===null?1:(int)$autoload]);
            else IssuerStorage::query($this->db,'INSERT INTO '.$table.'(name,value) VALUES(?,?)',[$name,$value]);
            $inserted=$this->rows('SELECT * FROM '.$table.' WHERE name=? FOR UPDATE',[$name])[0]??null;
            if(!$inserted||(string)$inserted['value']!==$value||($hasAutoload&&(int)$inserted['autoload']!==($autoload===null?1:(int)$autoload)))throw new DomainException('company_settings_write_unconfirmed');
            $expected=self::normalize($inserted);
        }
        $actual=$this->rows('SELECT * FROM '.$table.' WHERE name=? FOR UPDATE',[$name])[0]??null;
        if(($actual===null?null:self::normalize($actual))!==$expected)throw new DomainException('company_settings_write_unconfirmed');
        if($expected===null)unset($this->expected['options'][$name]);else $this->expected['options'][$name]=$expected;
        ksort($this->expected['options'],SORT_STRING);
    }
    public function confirm():void
    {
        $this->authority();
        if($this->frame()!==$this->expected)throw new DomainException('company_settings_write_unconfirmed');
        foreach($this->audits as $id=>$expected){
            $row=$this->rows('SELECT * FROM '.$this->prefix.'issuers_audit WHERE id=? FOR UPDATE',[$id])[0]??null;
            if($row===null||self::normalize($row)!==$expected)throw new DomainException('company_settings_write_unconfirmed');
        }
    }
    /** The audit is part of the confirmed write; names and stored settings never enter it. */
    public function audit(string $event):void
    {
        IssuerTransaction::assertOwned($this->db,$this->prefix,['issuers_audit']);
        if(!preg_match('/^company_(?:settings_(?:add|update|delete|batch_updated)|identity_updated)$/D',$event))throw new LogicException('Invalid settings audit');
        $before=(int)($this->rows('SELECT MAX(id) id FROM '.$this->prefix.'issuers_audit')[0]['id']??0);
        $time=date('Y-m-d H:i:s');
        IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,NULL,?)',[$this->actor,$event,$this->issuer,$time]);
        $id=(int)$this->db->insert_id();
        $row=$this->rows('SELECT * FROM '.$this->prefix.'issuers_audit WHERE id=? FOR UPDATE',[$id])[0]??null;
        if($id<=$before||!$row||(int)$row['actor_id']!==$this->actor||$row['event']!==$event||(int)$row['issuer_id']!==$this->issuer||$row['subject_id']!==null||$row['created_at']!==$time||($row['metadata_json']??null)!==null)throw new DomainException('company_settings_write_unconfirmed');
        $this->audits[$id]=self::normalize($row);
    }
}
