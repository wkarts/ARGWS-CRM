<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Personal native layout, namespaced by company; never reads legacy/global preference keys. */
final class NativeCompanyDashboardPreferences
{
    public const WIDGETS=['top_stats','finance_overview','user_data','upcoming_events','calendar','payments_chart','todos','leads_chart','projects_chart','tickets_chart','projects_activity','contracts_expiring','tickets_report'];
    public const CONTAINERS=['top-12','middle-left-6','middle-right-6','left-8','right-4','bottom-left-4','bottom-middle-4','bottom-right-4'];
    public function __construct(private $db,private string $p,private int $issuer,private int $actor)
    {if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');IssuerPolicy::id($issuer);IssuerPolicy::id($actor);}
    private function authorize():void{if(!(new IssuerResourceAccess($this->db,$this->p))->member($this->issuer,$this->actor))throw new DomainException('native_dashboard_preferences_denied');}
    private function key():string{return 'company_dashboard:'.$this->issuer.':v1';}
    public static function order(array $input):array
    {
        if(array_diff(array_keys($input),self::CONTAINERS))throw new DomainException('native_dashboard_preferences_invalid');$seen=[];$out=[];
        foreach($input as $container=>$widgets){if($widgets==='empty')$widgets=[];if(!is_array($widgets)||!array_is_list($widgets))throw new DomainException('native_dashboard_preferences_invalid');
            $out[$container]=[];foreach($widgets as $widget){if(!is_string($widget)||!str_starts_with($widget,'widget-')||!in_array(substr($widget,7),self::WIDGETS,true)||isset($seen[$widget]))throw new DomainException('native_dashboard_preferences_invalid');$seen[$widget]=true;$out[$container][]=$widget;}
        }return $out;
    }
    public static function visibility(array $input):array
    {
        if(!array_is_list($input)||count($input)>count(self::WIDGETS))throw new DomainException('native_dashboard_preferences_invalid');$seen=[];$out=[];
        foreach($input as $row){if(!is_array($row)||array_diff(array_keys($row),['id','visible'])||!isset($row['id'],$row['visible'])||!in_array($row['id'],self::WIDGETS,true)||!in_array($row['visible'],[0,1,'0','1'],true)||isset($seen[$row['id']]))throw new DomainException('native_dashboard_preferences_invalid');$seen[$row['id']]=true;$out[]=['id'=>$row['id'],'visible'=>(int)$row['visible']];}return $out;
    }
    public function read():array
    {
        $this->authorize();$rows=IssuerStorage::query($this->db,'SELECT meta_value FROM '.$this->p.'user_meta WHERE staff_id=? AND meta_key=?',[$this->actor,$this->key()])->result_array();
        if(count($rows)>1)throw new DomainException('native_dashboard_preferences_ambiguous');$value=$rows?json_decode($rows[0]['meta_value'],true,32,JSON_THROW_ON_ERROR):['order'=>[],'visibility'=>[]];
        if(!is_array($value)||array_diff(array_keys($value),['order','visibility'])||!isset($value['order'],$value['visibility']))throw new DomainException('native_dashboard_preferences_invalid');
        $out=['order'=>self::order($value['order']),'visibility'=>self::visibility($value['visibility'])];$this->authorize();return $out;
    }
    public function save(string $part,array $value=[]):void
    {
        if(!in_array($part,['order','visibility','reset'],true))throw new DomainException('native_dashboard_preferences_invalid');
        if($part==='order')$value=self::order($value);elseif($part==='visibility')$value=self::visibility($value);$this->authorize();
        IssuerTransaction::run($this->db,$this->p,['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','user_meta'],function()use($part,$value):void{
            (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($this->issuer,$this->actor);$current=$this->read();
            if($part==='reset')$current=['order'=>[],'visibility'=>[]];else$current[$part]=$value;$encoded=json_encode($current,JSON_THROW_ON_ERROR);
            $rows=IssuerStorage::query($this->db,'SELECT umeta_id FROM '.$this->p.'user_meta WHERE staff_id=? AND meta_key=?',[$this->actor,$this->key()])->result_array();
            if($rows)IssuerStorage::query($this->db,'UPDATE '.$this->p.'user_meta SET meta_value=? WHERE staff_id=? AND meta_key=?',[$encoded,$this->actor,$this->key()]);
            else IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'user_meta(staff_id,meta_key,meta_value) VALUES(?,?,?)',[$this->actor,$this->key(),$encoded]);
            if($this->read()!==$current)throw new RuntimeException('native_dashboard_preferences_write_unconfirmed');
        });
    }
}
