<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
/** Public native currency reference catalogue; no company balances, writes or implicit conversion. */
final class NativeCompanyCurrencyReference
{
    private IssuerResourceAccess $access;
    public function __construct(private $db,private string $p,private int $issuer,private int $actor)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        IssuerPolicy::id($issuer);IssuerPolicy::id($actor);$this->access=new IssuerResourceAccess($db,$p);$this->member();
    }
    private function member():void{if(!$this->access->member($this->issuer,$this->actor))throw new DomainException('company_catalog_denied');}
    public function currencies():array
    {
        $this->member();$rows=IssuerStorage::query($this->db,'SELECT id,name,symbol,decimal_separator,thousand_separator,placement,isdefault FROM '.$this->p.'currencies ORDER BY id')->result_array();$this->member();return$rows;
    }
    /** Public currency IDs in use by clients visible in this issuer, never a global customer count. */
    public function clientCurrencyIds():array
    {
        $this->member();$primary=$this->access->primaryId()===$this->issuer;
        $predicate='(o.owner_issuer_id=? OR EXISTS(SELECT 1 FROM '.$this->p.'issuers_client_links l WHERE l.client_id=c.userid AND l.issuer_id=?)'.($primary?' OR o.client_id IS NULL':'').')';
        $sql='SELECT DISTINCT c.default_currency FROM '.$this->p.'clients c LEFT JOIN '.$this->p.'issuers_clients o ON o.client_id=c.userid WHERE c.default_currency>0 AND '.$predicate.' ORDER BY c.default_currency';
        $rows=IssuerStorage::query($this->db,$sql,[$this->issuer,$this->issuer])->result_array();$this->member();$again=IssuerStorage::query($this->db,$sql,[$this->issuer,$this->issuer])->result_array();if($rows!==$again)throw new DomainException('company_catalog_stale');$valid=array_map('intval',array_column($this->currencies(),'id'));$ids=[];foreach($rows as$r){$id=(int)$r['default_currency'];if(!in_array($id,$valid,true))throw new DomainException('company_catalog_invalid_currency');$ids[]=$id;}return$ids;
    }
    public function currency($id):array
    {
        $id=IssuerPolicy::id($id);foreach($this->currencies()as$row)if((int)$row['id']===$id)return$row;throw new DomainException('company_catalog_invalid_currency');
    }
    public function baseCurrency():array
    {
        $this->member();$rows=IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.'currencies WHERE isdefault=1')->result_array();
        if(count($rows)!==1)throw new DomainException('company_catalog_invalid_currency');return$this->currency($rows[0]['id']);
    }
}
