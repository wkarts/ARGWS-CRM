<?php

declare(strict_types=1);

/** Invitations are persisted origins, not a vendor ID or company supplied by an HTTP caller. */
trait CompanyPurchasesPortal
{
    public static function forPortal($db,string $p,string $secret): array
    {
        if (!preg_match('/^purco_([a-f0-9]{32})_[a-f0-9]{64}$/D',$secret,$m)) { throw new DomainException('company_purchase_portal_denied'); }
        $s=new self($db,$p);
        $r=$s->rows('SELECT * FROM '.$p.'company_purchase_invites WHERE token_id=?',[$m[1]])[0]??null;
        if (!$r||!hash_equals($r['secret_hash'],hash('sha256',$secret))) { throw new DomainException('company_purchase_portal_denied'); }
        $s->portal=['id'=>(int)$r['id'],'issuer_id'=>(int)$r['issuer_id'],'actor_id'=>(int)$r['created_by'],'request_id'=>(int)$r['request_id'],'supplier_id'=>(int)$r['supplier_id'],'revision'=>(int)$r['revision'],'secret_hash'=>hash('sha256',$secret)];
        // No data is returned until the first fully locked operation validates current authority.
        return [$s,['issuer_id'=>$s->portal['issuer_id'],'actor_id'=>$s->portal['actor_id']]];
    }

    private function guardPortal(int $issuer,int $actor,string $operation): void
    {
        $grant=$this->portal;
        if ($grant===null||$grant['issuer_id']!==$issuer||$grant['actor_id']!==$actor||!in_array($operation,['portal_read','quote_created','file_uploaded','files','download','order_pdf'],true)) { throw new DomainException('company_purchase_portal_denied'); }
        $r=$this->rows('SELECT * FROM '.$this->p.'company_purchase_invites WHERE id=? AND issuer_id=? FOR UPDATE',[$grant['id'],$issuer])[0]??null;
        if (!$r||(int)$r['created_by']!==$actor||(int)$r['request_id']!==$grant['request_id']||(int)$r['supplier_id']!==$grant['supplier_id']||(int)$r['revision']!==$grant['revision']||(int)$r['revoked']!==0||$r['expires_at']<=self::now()||!hash_equals($r['secret_hash'],$grant['secret_hash'])) { throw new DomainException('company_purchase_portal_denied'); }
        $request=$this->rows('SELECT state FROM '.$this->p.'company_purchase_requests WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$grant['request_id']])[0]??null;
        $supplier=$this->rows('SELECT active FROM '.$this->p.'company_purchase_suppliers WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$grant['supplier_id']])[0]??null;
        if (!$request||$request['state']==='cancelled'||!$supplier||(int)$supplier['active']!==1) { throw new DomainException('company_purchase_portal_denied'); }
        $this->remember('company_purchase_invites',$r);
    }

    public function portalIdentity(string $signingKey): array
    {
        if ($this->portal===null||strlen($signingKey)<32) { throw new DomainException('company_purchase_portal_denied'); }
        $this->portalView(); $identity=$this->portal;
        return $identity+['signature'=>hash_hmac('sha256','purchase-portal-session|'.CompanyPurchasesValues::json($identity),$signingKey)];
    }

    public static function forPortalSession($db,string $p,array $identity,string $signingKey): array
    {
        $signature=$identity['signature']??null; unset($identity['signature']);
        if (strlen($signingKey)<32||!is_string($signature)||array_keys($identity)!==['id','issuer_id','actor_id','request_id','supplier_id','revision','secret_hash']||!hash_equals(hash_hmac('sha256','purchase-portal-session|'.CompanyPurchasesValues::json($identity),$signingKey),$signature)) { throw new DomainException('company_purchase_portal_denied'); }
        foreach (['id','issuer_id','actor_id','request_id','supplier_id','revision'] as $field) { if (!is_int($identity[$field])||$identity[$field]<1) { throw new DomainException('company_purchase_portal_denied'); } }
        if (!is_string($identity['secret_hash'])||!preg_match('/^[a-f0-9]{64}$/D',$identity['secret_hash'])) { throw new DomainException('company_purchase_portal_denied'); }
        $s=new self($db,$p); $s->portal=$identity; return [$s,['issuer_id'=>$identity['issuer_id'],'actor_id'=>$identity['actor_id']]];
    }

    public function issueInvite($issuer,$actor,$request,$supplier,int $days,string $key): array
    {
        $request=IssuerPolicy::id($request); $supplier=IssuerPolicy::id($supplier); CompanyPurchasesValues::integer($days,1,30); $secret=null;
        $id=$this->command($issuer,$actor,'portal','invite_created',$key,[$request,$supplier,$days],function ($i,$a) use ($request,$supplier,$days,&$secret): array {
            $r=$this->object($i,'request',$request); $v=$this->object($i,'supplier',$supplier);
            if ($r['state']==='cancelled'||(int)$v['active']!==1) { throw new DomainException('company_purchase_state_conflict'); }
            $count=(int)$this->rows('SELECT COUNT(*) n FROM '.$this->p.'company_purchase_invites WHERE issuer_id=? AND request_id=? AND revoked=0 AND expires_at>?',[$i,$request,self::now()])[0]['n'];
            if ($count>=20) { throw new DomainException('company_purchase_invite_limit'); }
            $token=bin2hex(random_bytes(16)); $secret='purco_'.$token.'_'.bin2hex(random_bytes(32));
            $id=$this->insert('company_purchase_invites',['issuer_id'=>$i,'request_id'=>$request,'supplier_id'=>$supplier,'token_id'=>$token,'secret_hash'=>hash('sha256',$secret),'expires_at'=>gmdate('Y-m-d H:i:s',time()+$days*86400),'revoked'=>0,'revision'=>1,'created_by'=>$a,'created_at'=>self::now()]);
            return ['invite',$id,$id];
        });
        return ['id'=>$id,'secret'=>$secret,'replayed'=>$secret===null];
    }

    public function revokeInvite($issuer,$actor,$id,$revision,string $key): int
    {
        $id=IssuerPolicy::id($id); $revision=CompanyPurchasesValues::integer($revision,1);
        return $this->command($issuer,$actor,'portal','invite_revoked',$key,[$id,$revision],function ($i) use ($id,$revision): array {
            $r=$this->object($i,'invite',$id); $this->revision($r,$revision);
            if ((int)$r['revoked']!==0) { throw new DomainException('company_purchase_state_conflict'); }
            $this->update('company_purchase_invites',$r,['revoked'=>1,'revision'=>$revision+1]); return ['invite',$id,$id];
        });
    }

    public function invites($issuer,$actor,$request): array
    {
        $issuer=IssuerPolicy::id($issuer); $actor=IssuerPolicy::id($actor); $request=IssuerPolicy::id($request);
        return $this->transaction($issuer,$actor,'portal','invites',null,function () use ($issuer,$request): array {
            $this->object($issuer,'request',$request);
            $rows=$this->observedRows('SELECT * FROM '.$this->p.'company_purchase_invites WHERE issuer_id=? AND request_id=? ORDER BY id',[$issuer,$request]);
            foreach ($rows as &$row) { $this->remember('company_purchase_invites',$row); unset($row['secret_hash']); } unset($row); return $rows;
        });
    }

    public function portalView(): array
    {
        if ($this->portal===null) { throw new DomainException('company_purchase_portal_denied'); }
        $i=$this->portal['issuer_id']; $a=$this->portal['actor_id'];
        return $this->transaction($i,$a,'portal','portal_read',null,function () use ($i): array {
            $request=$this->object($i,'request',$this->portal['request_id']); $supplier=$this->object($i,'supplier',$this->portal['supplier_id']);
            $quotes=$this->observedRows('SELECT * FROM '.$this->p.'company_purchase_quotes WHERE issuer_id=? AND request_id=? AND supplier_id=? ORDER BY id',[$i,$request['id'],$supplier['id']]);
            foreach ($quotes as &$quote) { if (!hash_equals($quote['record_hash'],CompanyPurchasesValues::hash($quote))) { throw new RuntimeException('issuer_purchase_integrity_invalid'); } $this->remember('company_purchase_quotes',$quote); unset($quote['created_by'],$quote['invite_id']); } unset($quote);
            $orders=[];
            foreach ($this->observedRows('SELECT id FROM '.$this->p.'company_purchase_orders WHERE issuer_id=? AND request_id=? AND supplier_id=? ORDER BY id',[$i,$request['id'],$supplier['id']]) as $r) {
                $order=$this->object($i,'order',(int)$r['id']); $position=$this->received($i,$order);
                $orders[]=['id'=>(int)$order['id'],'reference'=>$order['reference'],'order_date'=>$order['order_date'],'expected_date'=>$order['expected_date'],'currency'=>$order['currency'],'base_cents'=>(int)$order['base_cents'],'tax_cents'=>(int)$order['tax_cents'],'total_cents'=>(int)$order['total_cents'],'state'=>$order['state'],'lines'=>$position['lines'],'received_quantities'=>$position['quantities']];
            }
            $documents=[];
            foreach ($this->observedRows('SELECT p.id FROM '.$this->p.'company_purchase_payables p JOIN '.$this->p.'company_purchase_orders o ON o.id=p.order_id AND o.issuer_id=p.issuer_id WHERE p.issuer_id=? AND o.request_id=? AND p.supplier_id=? ORDER BY p.id',[$i,$request['id'],$supplier['id']]) as $r) {
                $payable=$this->object($i,'payable',(int)$r['id']); $position=$this->payablePosition($i,$payable); $document=$position['document'];
                $documents[]=['reference'=>$document['reference'],'vendor_number'=>$document['vendor_number'],'document_date'=>$document['document_date'],'due_date'=>$document['due_date'],'currency'=>$payable['currency'],'total_cents'=>(int)$payable['amount_cents'],'declared_paid_cents'=>$position['declared_paid_cents'],'outstanding_cents'=>$position['outstanding_cents'],'basis'=>$position['basis']];
            }
            unset($request['created_by']);
            return ['request'=>$request,'supplier'=>['id'=>(int)$supplier['id'],'name'=>$supplier['name']],'quotes'=>$quotes,'orders'=>$orders,'documents'=>$documents];
        });
    }
}
