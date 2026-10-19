<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyCurrencyUnitSchema.php';require_once __DIR__.'/issuers/IssuerTransaction.php';require_once __DIR__.'/issuers/IssuerPolicy.php';

/** Currency IDs acquire an immutable ISO meaning when used by a named-company document. */
final class CompanyCurrencyUnits
{
    public const AT_ISSUE='captured_at_issue';public const BASELINE='observed_at_upgrade_not_original_proof';
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public function tables():array{$tables=['issuers_lock','issuers_registry','currencies','company_currency_units'];foreach(['issuers_invoice_documents','company_credit_documents'] as $table)if($this->db->table_exists($this->p.$table))$tables[]=$table;return $tables;}
    private function activeBoundary():void
    {
        try{IssuerTransaction::assertOwned($this->db,$this->p,['issuers_lock','currencies','company_currency_units']);}catch(RuntimeException $e){throw new RuntimeException('issuer_currency_owned_transaction_required');}if(!$this->rows('SELECT id FROM '.$this->p.'issuers_lock WHERE id=1 FOR UPDATE'))throw new RuntimeException('issuer_currency_write_unconfirmed');
    }
    private static function digest(int $id,string $iso,string $basis,string $when):string{return hash('sha256',json_encode([$id,$iso,$basis,$when],JSON_THROW_ON_ERROR));}
    private function decode(array $row,int $id):array
    {
        if((int)$row['currency_id']!==$id||!preg_match('/^[A-Z]{3}$/D',$row['iso_code'])||!in_array($row['basis'],[self::AT_ISSUE,self::BASELINE],true)||!hash_equals($row['source_hash'],self::digest($id,$row['iso_code'],$row['basis'],$row['recorded_at'])))throw new DomainException('company_currency_snapshot_invalid');$row['currency_id']=$id;return $row;
    }
    /** No fallback to the mutable global catalog; callers authorize document ownership first. */
    public function resolveRead($currency):array
    {
        $id=IssuerPolicy::id($currency);$row=$this->rows('SELECT * FROM '.$this->p.'company_currency_units WHERE currency_id=?',[$id])[0]??null;if(!$row)throw new DomainException('company_currency_snapshot_required');return $this->decode($row,$id);
    }
    /** Veto-only helper in the caller's owned writer transaction. Never begins/commits/rolls back. */
    public function assertWrite($currency):array
    {
        $id=IssuerPolicy::id($currency);$this->activeBoundary();CompanyCurrencyUnitSchema::assertValid($this->db,$this->p);$live=$this->rows('SELECT id,name FROM '.$this->p.'currencies WHERE id=? FOR UPDATE',[$id])[0]??null;if(!$live||!is_string($live['name'])||!preg_match('/^[A-Z]{3}$/D',$live['name']))throw new DomainException('company_currency_catalog_changed');$row=$this->rows('SELECT * FROM '.$this->p.'company_currency_units WHERE currency_id=? FOR UPDATE',[$id])[0]??null;if(!$row)$row=$this->capture($id,$live['name'],self::AT_ISSUE);$row=$this->decode($row,$id);if($row['iso_code']!==$live['name'])throw new DomainException('company_currency_catalog_changed');return $row;
    }
    private function capture(int $id,string $iso,string $basis):array
    {
        $when=gmdate('Y-m-d H:i:s');$hash=self::digest($id,$iso,$basis,$when);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_currency_units(currency_id,iso_code,basis,recorded_at,source_hash) VALUES(?,?,?,?,?)',[$id,$iso,$basis,$when,$hash]);$row=$this->rows('SELECT * FROM '.$this->p.'company_currency_units WHERE currency_id=?',[$id])[0]??null;if(!$row||$row['source_hash']!==$hash)throw new RuntimeException('issuer_currency_write_unconfirmed');return $row;
    }
    private function sources():array
    {
        $sources=[];foreach(['invoices','creditnotes','company_sales_documents','company_recurring'] as $table)if($this->db->table_exists($this->p.$table)&&IssuerStorage::fieldExists($this->db,'issuer_id',$this->p.$table))$sources[]=$table;return $sources;
    }
    private function usedIds():array
    {
        $ids=[];foreach($this->sources() as $table){$from=$this->p.$table.' s JOIN '.$this->p.'issuers_registry r ON r.id=s.issuer_id';if($table==='invoices'){$from.=' JOIN '.$this->p.'issuers_invoice_documents d ON d.invoice_id=s.id AND d.issuer_id=s.issuer_id';}elseif($table==='creditnotes'){$from.=' JOIN '.$this->p.'company_credit_documents d ON d.credit_id=s.id AND d.issuer_id=s.issuer_id';}if($table==='company_recurring'){$rows=$this->rows('SELECT s.payload_json FROM '.$from);foreach($rows as $row){try{$payload=json_decode($row['payload_json'],true,32,JSON_THROW_ON_ERROR);$ids[]=IssuerPolicy::id($payload['currency']??null);}catch(Throwable $e){throw new DomainException('company_currency_snapshot_required');}}}else foreach($this->rows('SELECT DISTINCT s.currency FROM '.$from) as $row)$ids[]=IssuerPolicy::id($row['currency']);}return array_values(array_unique($ids));
    }
    /** Upgrade observes current mapping only. It does not claim to recover the historical original ISO. */
    public function captureObservedUsed():void
    {
        CompanyCurrencyUnitSchema::assertValid($this->db,$this->p);IssuerTransaction::run($this->db,$this->p,array_merge($this->tables(),$this->sources()),function():void{foreach($this->usedIds() as $id){$row=$this->rows('SELECT * FROM '.$this->p.'company_currency_units WHERE currency_id=? FOR UPDATE',[$id])[0]??null;if($row){$this->decode($row,$id);continue;}$live=$this->rows('SELECT name FROM '.$this->p.'currencies WHERE id=? FOR UPDATE',[$id])[0]??null;if(!$live||!preg_match('/^[A-Z]{3}$/D',$live['name']))throw new DomainException('company_currency_snapshot_required');$this->capture($id,$live['name'],self::BASELINE);}});
    }
    /** Guard catalog writes with the same lock used by company issuance; legacy-only IDs remain editable. */
    public function editCatalog($currency,string $newIso,callable $operation)
    {
        $id=IssuerPolicy::id($currency);CompanyCurrencyUnitSchema::assertValid($this->db,$this->p);return IssuerTransaction::run($this->db,$this->p,array_merge($this->tables(),$this->sources()),function()use($id,$newIso,$operation){$live=$this->rows('SELECT name FROM '.$this->p.'currencies WHERE id=? FOR UPDATE',[$id])[0]??null;$row=$this->rows('SELECT * FROM '.$this->p.'company_currency_units WHERE currency_id=? FOR UPDATE',[$id])[0]??null;if(!$row&&in_array($id,$this->usedIds(),true)){if(!$live||!preg_match('/^[A-Z]{3}$/D',$live['name']))throw new DomainException('company_currency_snapshot_required');$row=$this->capture($id,$live['name'],self::BASELINE);}if($row&&$this->decode($row,$id)['iso_code']!==$newIso)throw new DomainException('company_currency_catalog_locked');return $operation();});
    }
    public function deleteCatalog($currency,callable $operation)
    {
        $id=IssuerPolicy::id($currency);CompanyCurrencyUnitSchema::assertValid($this->db,$this->p);return IssuerTransaction::run($this->db,$this->p,array_merge($this->tables(),$this->sources()),function()use($id,$operation){$this->rows('SELECT id FROM '.$this->p.'currencies WHERE id=? FOR UPDATE',[$id]);if($this->rows('SELECT currency_id FROM '.$this->p.'company_currency_units WHERE currency_id=? FOR UPDATE',[$id])||in_array($id,$this->usedIds(),true))throw new DomainException('company_currency_catalog_locked');return $operation();});
    }
}
