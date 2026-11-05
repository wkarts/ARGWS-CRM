<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanySalesProjectionSchema.php';
require_once __DIR__.'/CompanyInvoiceLines.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';

/** Rebuildable amounts for SQL ordering/filtering. Never changes a document or allocates a number. */
final class NativeCompanySalesProjection
{
    public function __construct(private $db, private string $p)
    {
        NativeCompanySalesProjectionSchema::definitions($p);
    }
    public static function tables():array { return ['company_sales_documents', 'company_sales_projection']; }
    private function rows(string $sql,array $params=[]):array
    {
        return IssuerStorage::query($this->db,$sql,$params)->result_array();
    }
    private static function fail():never { throw new RuntimeException('issuer_sales_projection_unconfirmed'); }
    private static function json(array $value):string
    {
        return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
    private static function derive(array $row):array
    {
        $payload=json_decode($row['payload_json'],true,32,JSON_THROW_ON_ERROR);
        $context=json_decode($row['context_json'],true,32,JSON_THROW_ON_ERROR);
        if(!is_array($payload)||!is_array($context)||!in_array($row['kind'],['estimates','proposals'],true)
            ||!hash_equals($row['input_hash'],hash('sha256',self::json([$row['kind'],$payload])))
            ||!hash_equals($row['origin_hash'],hash('sha256',self::json($context)))
            ||(int)($context['issuer_id']??0)!==(int)$row['issuer_id']
            ||(int)($payload['client_id']??0)!==(int)$row['client_id']
            ||(int)($payload['currency']??0)!==(int)$row['currency']) self::fail();
        $money=CompanyInvoiceLines::calculate($payload['items'],array_intersect_key($payload,array_flip([
            'discount_mode','discount_value','discount_type','adjustment','calculation_contract',
        ])));
        if(CompanyInvoiceAmounts::minor((string)$row['total'])!==CompanyInvoiceAmounts::minor($money['total'])) self::fail();
        $data=['id'=>(int)$row['id'],'issuer_id'=>(int)$row['issuer_id'],'payload_hash'=>$row['input_hash'],
            'subtotal'=>$money['subtotal'],'total_tax'=>$money['tax']];
        return array_slice($data,0,3,true)+['projection_hash'=>hash('sha256',self::json($data))]+array_slice($data,3,null,true);
    }
    private static function normalize(array $row):array
    {
        $row['id']=(int)$row['id'];$row['issuer_id']=(int)$row['issuer_id'];
        foreach(['subtotal','total_tax'] as $key) $row[$key]=CompanyInvoiceAmounts::decimal(CompanyInvoiceAmounts::minor((string)$row[$key]));
        return $row;
    }
    /** The canonical writer calls this after its audited source row has been confirmed. */
    public function syncInOwned(array $source):void
    {
        IssuerTransaction::assertOwned($this->db,$this->p,self::tables());
        $actual=$this->rows('SELECT * FROM '.$this->p.'company_sales_documents WHERE issuer_id=? AND id=? FOR UPDATE',[$source['issuer_id'],$source['id']])[0]??null;
        if(!$actual) self::fail();
        foreach($source as $key=>$value) if(!array_key_exists($key,$actual)||($value===null?null:(string)$value)!==($actual[$key]===null?null:(string)$actual[$key])) self::fail();
        $expected=self::derive($actual);$table=$this->p.NativeCompanySalesProjectionSchema::TABLE;
        $prior=$this->rows('SELECT * FROM '.$table.' WHERE id=? FOR UPDATE',[$expected['id']]);
        if($prior){
            if((int)$prior[0]['issuer_id']!==$expected['issuer_id']) self::fail();
            IssuerStorage::query($this->db,'UPDATE '.$table.' SET payload_hash=?,projection_hash=?,subtotal=?,total_tax=? WHERE issuer_id=? AND id=?',[
                $expected['payload_hash'],$expected['projection_hash'],$expected['subtotal'],$expected['total_tax'],$expected['issuer_id'],$expected['id'],
            ]);
        }else IssuerStorage::query($this->db,'INSERT INTO '.$table.' (id,issuer_id,payload_hash,projection_hash,subtotal,total_tax) VALUES(?,?,?,?,?,?)',array_values($expected));
        $written=$this->rows('SELECT * FROM '.$table.' WHERE id=?',[$expected['id']])[0]??[];
        if(!$written||self::normalize($written)!==$expected
            ||$this->rows('SELECT * FROM '.$this->p.'company_sales_documents WHERE issuer_id=? AND id=?',[$source['issuer_id'],$source['id']])[0]!==$actual) self::fail();
    }
    /** One small joined read detects missing/stale/tampered projections before SQL counts. No document hydration here. */
    public function assertFrame(int $issuer,int $actor,string $kind):void
    {
        if(!in_array($kind,['estimates','proposals'],true)) throw new DomainException('company_sales_invalid');
        NativeCompanySalesProjectionSchema::assertValid($this->db,$this->p);
        IssuerTransaction::run($this->db,$this->p,self::tables(),function()use($issuer,$actor,$kind){
            $acl=new IssuerResourceAccess($this->db,$this->p);
            if(!$acl->can($issuer,$actor,$kind,'view')) throw new DomainException('company_sales_denied');
            // Check the reverse owner edge as well: a corrupt projection must not
            // become visible to the company to which it was incorrectly moved.
            if($this->rows('SELECT p.id FROM '.$this->p.'company_sales_projection p LEFT JOIN '.$this->p.'company_sales_documents d ON d.id=p.id WHERE p.issuer_id=? AND (d.id IS NULL OR d.issuer_id<>p.issuer_id) LIMIT 1',[$issuer]))self::fail();
            $rows=$this->rows('SELECT d.input_hash,p.* FROM '.$this->p.'company_sales_documents d LEFT JOIN '.$this->p.'company_sales_projection p ON p.id=d.id AND p.issuer_id=d.issuer_id WHERE d.issuer_id=? AND d.kind=? ORDER BY d.id',[$issuer,$kind]);
            foreach($rows as $row){
                if($row['id']===null||!hash_equals($row['input_hash'],$row['payload_hash']))self::fail();
                unset($row['input_hash']);$row=self::normalize($row);$hash=$row['projection_hash'];unset($row['projection_hash']);
                if(!hash_equals($hash,hash('sha256',self::json($row))))self::fail();
            }
            if(!$acl->can($issuer,$actor,$kind,'view'))throw new DomainException('company_sales_denied');
        });
    }
    /** Recalculate only a document already hydrated/verified by the canonical reader. */
    public function assertDocument(int $issuer,array $document):void
    {
        $row=$this->rows('SELECT * FROM '.$this->p.'company_sales_projection WHERE issuer_id=? AND id=?',[$issuer,$document['source_id']])[0]??null;
        if(!$row)self::fail();$row=self::normalize($row);
        $money=CompanyInvoiceLines::calculate($document['payload']['items'],array_intersect_key($document['payload'],array_flip([
            'discount_mode','discount_value','discount_type','adjustment','calculation_contract',
        ])));
        if($row['subtotal']!==$money['subtotal']||$row['total_tax']!==$money['tax'])self::fail();
    }
    /** Migration-only, transactional and replayable; corrupted source data aborts the entire backfill. */
    public function backfill():void
    {
        NativeCompanySalesProjectionSchema::assertValid($this->db,$this->p);
        IssuerTransaction::run($this->db,$this->p,self::tables(),function(){
            $source=$this->rows('SELECT * FROM '.$this->p.'company_sales_documents ORDER BY id FOR UPDATE');
            foreach($source as $row) $this->syncInOwned($row);
            if($source!==$this->rows('SELECT * FROM '.$this->p.'company_sales_documents ORDER BY id')
                ||$this->rows('SELECT p.id FROM '.$this->p.'company_sales_projection p LEFT JOIN '.$this->p.'company_sales_documents d ON d.id=p.id WHERE d.id IS NULL')) self::fail();
        });
    }
}
