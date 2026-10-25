<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Existing DataTables preference, isolated by company and authenticated actor. */
final class NativeCompanyTableOrder
{
    private const TABLES=['projects'=>'projects','invoices'=>'invoices','project_invoices'=>'invoices','kb-articles'=>'knowledge_base'];
    public function __construct(private $db,private string $p,private int $issuer,private int $actor)
    {if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');IssuerPolicy::id($issuer);IssuerPolicy::id($actor);}
    private function key(string $identifier):string
    {
        if(!isset(self::TABLES[$identifier]))throw new DomainException('native_table_order_invalid');
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if(!$acl->can($this->issuer,$this->actor,self::TABLES[$identifier],'view'))throw new DomainException('native_table_order_denied');
        return ($acl->primaryId()===$this->issuer?'':'issuer:'.$this->issuer.':').$identifier.'-table-last-order';
    }
    private static function normalize(array $orders,int $columns):array
    {
        if($columns<1||$columns>256||!array_is_list($orders)||!$orders||count($orders)>$columns)throw new DomainException('native_table_order_invalid');
        $result=[];$seen=[];
        foreach($orders as$row){
            if(!is_array($row)||array_diff(array_keys($row),[0,1,'column','dir','type']))throw new DomainException('native_table_order_invalid');
            $column=$row['column']??$row[0]??null;$direction=$row['dir']??$row[1]??null;
            if((!is_int($column)&&!is_string($column))||!preg_match('/^(?:0|[1-9][0-9]{0,2})$/D',(string)$column)||(int)$column>=$columns||isset($seen[(int)$column])||!in_array($direction,['asc','desc'],true))throw new DomainException('native_table_order_invalid');
            $seen[(int)$column]=true;$result[]=[(int)$column,$direction];
        }
        return $result;
    }
    public function read(string $identifier):string
    {
        $key=$this->key($identifier);$rows=IssuerStorage::query($this->db,'SELECT meta_value FROM '.$this->p.'user_meta WHERE staff_id=? AND meta_key=?',[$this->actor,$key])->result_array();
        if(count($rows)>1)throw new DomainException('native_table_order_ambiguous');
        $value='';if($rows){$decoded=json_decode((string)$rows[0]['meta_value'],true,8,JSON_THROW_ON_ERROR);if(!is_array($decoded))throw new DomainException('native_table_order_invalid');$value=json_encode(self::normalize($decoded,256),JSON_THROW_ON_ERROR);}
        if($this->key($identifier)!==$key)throw new DomainException('native_table_order_denied');return $value;
    }
    public function save(string $identifier,array $orders,int $columns):void
    {
        $normalized=self::normalize($orders,$columns);$key=$this->key($identifier);$encoded=json_encode($normalized,JSON_THROW_ON_ERROR);
        IssuerTransaction::run($this->db,$this->p,['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','user_meta'],function()use($identifier,$key,$encoded):void{
            (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($this->issuer,$this->actor);
            if($this->key($identifier)!==$key)throw new DomainException('native_table_order_denied');
            $rows=IssuerStorage::query($this->db,'SELECT umeta_id FROM '.$this->p.'user_meta WHERE staff_id=? AND meta_key=?',[$this->actor,$key])->result_array();
            if(count($rows)>1)throw new DomainException('native_table_order_ambiguous');
            if($rows)IssuerStorage::query($this->db,'UPDATE '.$this->p.'user_meta SET meta_value=? WHERE staff_id=? AND meta_key=?',[$encoded,$this->actor,$key]);
            else IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'user_meta(staff_id,meta_key,meta_value) VALUES(?,?,?)',[$this->actor,$key,$encoded]);
            if($this->read($identifier)!==$encoded)throw new RuntimeException('native_table_order_write_unconfirmed');
        });
    }
}
