<?php

declare(strict_types=1);
require_once __DIR__.'/CompanySalesBase.php';

/** Immutable quotes/proposals with explicit decisions and resumable canonical invoice conversion. */
final class CompanySales extends CompanySalesBase
{
    private static function kind(string $kind):string{if(!in_array($kind,['estimates','proposals'],true))throw new DomainException('company_sales_invalid');return $kind;}
    public function create($issuer,$actor,string $kind,array $input,string $key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$kind=self::kind($kind);$payload=self::input($input);self::key($key);$hash=self::hash([$kind,$payload]);$this->auth($issuer,$actor,$kind,'create');
        return $this->tx($issuer,$actor,function()use($issuer,$actor,$kind,$payload,$key,$hash){
            $this->auth($issuer,$actor,$kind,'view');$this->auth($issuer,$actor,$kind,'create');
            $old=$this->rows('SELECT id,issuer_id,actor_id,input_hash FROM '.$this->prefix.'company_sales_documents WHERE request_key=? FOR UPDATE',[$key])[0]??null;
            if($old){if((int)$old['issuer_id']!==$issuer||(int)$old['actor_id']!==$actor||!hash_equals($old['input_hash'],$hash))throw new DomainException('company_sales_conflict');return (int)$old['id'];}
            $context=$this->context($issuer,$actor,$payload['client_id'],$payload['currency']);$series=$kind==='estimates'?'EST':'PROP';
            $seq=$this->rows('SELECT next_number FROM '.$this->prefix.'issuers_sequences WHERE issuer_id=? AND series=? FOR UPDATE',[$issuer,$series])[0]??null;$number=$seq?(int)$seq['next_number']:1;if($number<1||$number>2147483647)throw new DomainException('company_sales_sequence_exhausted');
            if($seq)IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'issuers_sequences SET next_number=? WHERE issuer_id=? AND series=?',[$number+1,$issuer,$series]);else$this->insert('issuers_sequences',['issuer_id'=>$issuer,'series'=>$series,'next_number'=>2]);
            $amounts=CompanyInvoiceAmounts::calculate($payload['items'],array_intersect_key($payload,array_flip(['discount_mode','discount_value','discount_type','adjustment'])));$this->insert('company_sales_documents',['issuer_id'=>$issuer,'actor_id'=>$actor,'conversion_actor_id'=>0,'client_id'=>$payload['client_id'],'currency'=>$payload['currency'],'kind'=>$kind,'number'=>$number,'state'=>'open','payload_json'=>self::encode($payload),'context_json'=>self::encode($context),'origin_hash'=>self::hash($context),'total'=>$amounts['total'],'request_key'=>$key,'input_hash'=>$hash,'invoice_id'=>0,'created_at'=>date('Y-m-d H:i:s')]);$id=(int)$this->db->insert_id();
            if(!$id||!$this->rows('SELECT id FROM '.$this->prefix.'company_sales_documents WHERE id=? AND issuer_id=? AND input_hash=? AND origin_hash=?',[$id,$issuer,$hash,self::hash($context)]))throw new RuntimeException('issuer_sales_write_unconfirmed');$this->audit($issuer,$actor,$id,'company_sales_created');$this->auth($issuer,$actor,$kind,'create');return $id;
        });
    }
    private function owned(int $issuer,int $actor,int $id,bool $lock=false):array
    {
        // Kind and owner only are inspected before resource-specific authorization and hydration.
        $ref=$this->rows('SELECT kind FROM '.$this->prefix.'company_sales_documents WHERE issuer_id=? AND id=?'.($lock?' FOR UPDATE':''),[$issuer,$id])[0]??null;if(!$ref)throw new DomainException('company_sales_denied');$this->auth($issuer,$actor,self::kind($ref['kind']));
        $row=$this->rows('SELECT * FROM '.$this->prefix.'company_sales_documents WHERE issuer_id=? AND id=?'.($lock?' FOR UPDATE':''),[$issuer,$id])[0]??null;if(!$row)throw new DomainException('company_sales_denied');$payload=$this->decode($row['payload_json']);$context=$this->decode($row['context_json']);if(!hash_equals($row['origin_hash'],self::hash($context))||!hash_equals($row['input_hash'],self::hash([$row['kind'],$payload]))||(int)($context['issuer_id']??0)!==$issuer||(int)($payload['client_id']??0)!==(int)$row['client_id']||(int)($payload['currency']??0)!==(int)$row['currency'])throw new RuntimeException('issuer_sales_write_unconfirmed');return $row;
    }
    public function document($issuer,$actor,$id):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$r=$this->owned($issuer,$actor,$id);$r['payload']=$this->decode($r['payload_json']);$r['context']=$this->decode($r['context_json']);unset($r['payload_json'],$r['context_json'],$r['origin_hash'],$r['request_key'],$r['input_hash']);$this->auth($issuer,$actor,$r['kind']);return $r;
    }
    public function page($issuer,$actor,string $kind,$after=0,string $search=''):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$kind=self::kind($kind);$this->auth($issuer,$actor,$kind);if($after!==0)$after=IssuerPolicy::id($after);
        if(strlen($search)>191||preg_match('//u',$search)!==1)throw new DomainException('company_sales_invalid');$like='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';$r=$this->rows("SELECT id,kind,number,state,total,invoice_id,created_at FROM ".$this->prefix."company_sales_documents WHERE issuer_id=? AND kind=? AND id>? AND (payload_json LIKE ? ESCAPE '!' OR context_json LIKE ? ESCAPE '!') ORDER BY id LIMIT 51",[$issuer,$kind,$after,$like,$like]);$this->auth($issuer,$actor,$kind);return ['items'=>array_slice($r,0,50),'next'=>count($r)>50?(int)$r[49]['id']:null];
    }
    public function decide($issuer,$actor,$id,string $decision):void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);if(!in_array($decision,['accepted','declined','cancelled'],true))throw new DomainException('company_sales_invalid');
        $this->tx($issuer,$actor,function()use($issuer,$actor,$id,$decision){$r=$this->owned($issuer,$actor,$id,true);$this->auth($issuer,$actor,$r['kind'],$decision==='cancelled'?'delete':'edit');if($r['state']===$decision)return;if($r['state']!=='open')throw new DomainException('company_sales_state_conflict');IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'company_sales_documents SET state=? WHERE id=? AND issuer_id=?',[$decision,$id,$issuer]);if(($this->owned($issuer,$actor,$id,true)['state']??null)!==$decision)throw new RuntimeException('issuer_sales_write_unconfirmed');$this->audit($issuer,$actor,$id,'company_sales_'.$decision);$this->auth($issuer,$actor,$r['kind'],$decision==='cancelled'?'delete':'edit');});
    }
    public function convert($issuer,$actor,$id):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);
        $claim=$this->tx($issuer,$actor,function()use($issuer,$actor,$id){$r=$this->owned($issuer,$actor,$id,true);$this->auth($issuer,$actor,$r['kind'],'edit');$this->auth($issuer,$actor,'invoices','create');if($r['state']==='converted')return $r;if(!in_array($r['state'],['accepted','converting'],true))throw new DomainException('company_sales_state_conflict');if((int)$r['conversion_actor_id']!==$actor&&$r['state']==='converting')throw new DomainException('company_sales_conflict');$payload=$this->decode($r['payload_json']);$this->assertOriginOrReceipt($issuer,$actor,$payload,$r['origin_hash'],$this->decode($r['context_json']),hash('sha256','company-sales-invoice:'.$issuer.':'.$id));IssuerStorage::query($this->db,"UPDATE ".$this->prefix."company_sales_documents SET state='converting',conversion_actor_id=? WHERE issuer_id=? AND id=?",[$actor,$issuer,$id]);if($this->owned($issuer,$actor,$id,true)['state']!=='converting')throw new RuntimeException('issuer_sales_write_unconfirmed');$this->audit($issuer,$actor,$id,'company_sales_conversion_claimed');return $r;});
        if($claim['state']==='converted'){(new CompanyInvoices($this->db,$this->prefix))->document($issuer,$actor,$claim['invoice_id']);return (int)$claim['invoice_id'];}
        $payload=$this->decode($claim['payload_json']);$guard=function()use($issuer,$actor,$id,$payload,$claim){(new IssuerResourceAccess($this->db,$this->prefix))->lockFinancialIdentity($issuer,$actor);$r=$this->owned($issuer,$actor,$id,true);$this->auth($issuer,$actor,$r['kind'],'edit');if(!in_array($r['state'],['converting','converted'],true)||(int)$r['conversion_actor_id']!==$actor||!hash_equals($claim['origin_hash'],$r['origin_hash']))throw new DomainException('company_sales_conflict');$this->assertOriginOrReceipt($issuer,$actor,$payload,$r['origin_hash'],$this->decode($r['context_json']),hash('sha256','company-sales-invoice:'.$issuer.':'.$id));};
        $invoice=(new CompanyInvoices($this->db,$this->prefix))->create($issuer,$actor,$payload,hash('sha256','company-sales-invoice:'.$issuer.':'.$id),$guard);
        return $this->tx($issuer,$actor,function()use($issuer,$actor,$id,$invoice){$r=$this->owned($issuer,$actor,$id,true);$this->auth($issuer,$actor,$r['kind'],'edit');if($r['state']==='converted'&&(int)$r['invoice_id']===$invoice)return $invoice;if($r['state']!=='converting'||(int)$r['conversion_actor_id']!==$actor)throw new DomainException('company_sales_conflict');IssuerStorage::query($this->db,"UPDATE ".$this->prefix."company_sales_documents SET state='converted',invoice_id=? WHERE issuer_id=? AND id=?",[$invoice,$issuer,$id]);$saved=$this->owned($issuer,$actor,$id,true);if($saved['state']!=='converted'||(int)$saved['invoice_id']!==$invoice)throw new RuntimeException('issuer_sales_write_unconfirmed');$this->audit($issuer,$actor,$id,'company_sales_converted');return $invoice;});
    }
    public function confirmPdf($issuer,$actor,$id):void{$issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$this->tx($issuer,$actor,function()use($issuer,$actor,$id){$this->owned($issuer,$actor,$id,true);$this->audit($issuer,$actor,$id,'company_sales_pdf_downloaded');$this->owned($issuer,$actor,$id,true);});}
}
