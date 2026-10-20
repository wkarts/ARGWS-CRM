<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyApiTokens.php';require_once __DIR__.'/CompanyFinancialApiSchema.php';

/** A typed veto only: authority still belongs to each canonical financial writer. */
final class CompanyFinancialApiGuard
{
    public const OPERATIONS=[
        'manual_payment'=>'payments:create','manual_reverse'=>'payments:reverse',
        'credit_issue'=>'credits:create','credit_apply'=>'credits:apply','credit_unapply'=>'credits:unapply',
        'credit_refund'=>'credits:refund','credit_refund_reverse'=>'credits:refund_reverse','credit_void'=>'credits:void'
    ];
    private bool $audited=false;
    private bool $replayed=false;
    private bool $finished=false;
    private CompanyApiTokens $tokens;
    public function __construct(private $db,private string $p,private string $secret,private int $issuer,private int $actor,private string $operation,private array $target,private string $key,private string $inputHash)
    {
        if(!isset(self::OPERATIONS[$operation])||(!preg_match('/^[a-f0-9]{64}$/D',$key)||!preg_match('/^[a-f0-9]{64}$/D',$inputHash)))throw new DomainException('company_api_invalid_input');
        CompanyApiSchema::assertValid($db,$p);CompanyFinancialApiSchema::assertValid($db,$p);$this->tokens=new CompanyApiTokens($db,$p);$who=$this->tokens->resolve($secret,self::OPERATIONS[$operation]);
        if($who['issuer_id']!==$issuer||$who['actor_id']!==$actor)throw new DomainException('company_api_scope_denied');
    }
    public function tables():array{return ['company_api_tokens','company_api_token_requests','company_financial_api_requests'];}
    private function validate($db,string $p,int $issuer,int $actor,string $operation,array $target,string $key):void
    {
        if($db!==$this->db||$p!==$this->p||$issuer!==$this->issuer||$actor!==$this->actor||$operation!==$this->operation||$target!==$this->target||!hash_equals($this->key,$key))throw new DomainException('company_api_scope_denied');
        IssuerTransaction::assertOwned($db,$p,array_merge($this->tables(),['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_audit']));
        $this->tokens->guard($this->secret,self::OPERATIONS[$operation],$issuer,$actor);
    }
    public function beforeInOwned($db,string $p,int $issuer,int $actor,string $operation,array $target,string $key):void
    {
        $this->validate($db,$p,$issuer,$actor,$operation,$target,$key);
        if($this->audited)throw new DomainException('company_api_scope_denied');
        if(!$this->audited){
            $rows=IssuerStorage::query($db,'SELECT * FROM '.$p.'company_financial_api_requests WHERE request_key=? FOR UPDATE',[$key])->result_array();
            $old=$rows[0]??null;if($old&&!IssuerStorage::query($db,'SELECT token_id FROM '.$p.'company_api_tokens WHERE token_id=? AND issuer_id=? AND actor_id=?',[$old['token_id'],$issuer,$actor])->result_array())throw new DomainException('company_api_request_conflict');if($old&&((int)$old['issuer_id']!==$issuer||(int)$old['actor_id']!==$actor||$old['operation']!==$operation||!hash_equals($old['input_hash'],$this->inputHash)||$old['target_json']!==json_encode($target,JSON_THROW_ON_ERROR)))throw new DomainException('company_api_request_conflict');$this->replayed=$old!==null;
            $this->tokens->usedInOwnedTransaction($this->secret,self::OPERATIONS[$operation],$issuer,$actor,$operation,$key,$target);$this->audited=true;
            $this->validate($db,$p,$issuer,$actor,$operation,$target,$key);
        }
    }
    public function afterInOwned($db,string $p,int $issuer,int $actor,string $operation,array $target,string $key):void
    {
        $this->validate($db,$p,$issuer,$actor,$operation,$target,$key);
        if(!$this->audited||$this->finished)throw new DomainException('company_api_scope_denied');
        if(!$this->replayed){$who=$this->tokens->resolve($this->secret,self::OPERATIONS[$operation],true);IssuerStorage::query($db,'INSERT INTO '.$p.'company_financial_api_requests(request_key,issuer_id,actor_id,operation,input_hash,target_json,token_id,created_at) VALUES(?,?,?,?,?,?,?,?)',[$key,$issuer,$actor,$operation,$this->inputHash,json_encode($target,JSON_THROW_ON_ERROR),$who['token_id'],gmdate('Y-m-d H:i:s')]);}
        $row=IssuerStorage::query($db,'SELECT r.* FROM '.$p.'company_financial_api_requests r JOIN '.$p.'company_api_tokens t ON t.token_id=r.token_id AND t.issuer_id=r.issuer_id AND t.actor_id=r.actor_id WHERE r.request_key=? AND r.issuer_id=? AND r.actor_id=?',[$key,$issuer,$actor])->row_array();if(!$row||$row['operation']!==$operation||!hash_equals($row['input_hash'],$this->inputHash)||$row['target_json']!==json_encode($target,JSON_THROW_ON_ERROR))throw new RuntimeException('issuer_api_write_unconfirmed');
        $this->verifyNativeInOwned($operation,$target,$key);
        $this->validate($db,$p,$issuer,$actor,$operation,$target,$key);$this->finished=true;
    }
    /** API-receipt insertion is the final write; recheck canonical evidence after it too. */
    private function verifyNativeInOwned(string $operation,array $target,string $key):void
    {
        $invoice=$target['invoice_id']??null;$credit=$target['credit_id']??null;
        if($operation==='credit_issue'){$row=IssuerStorage::query($this->db,'SELECT credit_id FROM '.$this->p.'company_credit_documents WHERE request_key=? AND issuer_id=? AND actor_id=?',[$key,$this->issuer,$this->actor])->row_array();if(!$row)throw new RuntimeException('issuer_api_write_unconfirmed');$credit=IssuerPolicy::id($row['credit_id']);}
        if($invoice!==null)(new CompanyInvoiceBalance($this->db,$this->p))->inOwned($this->issuer,$this->actor,$invoice);
        if($credit!==null)(new CompanyCredits($this->db,$this->p))->inOwnedTransaction($this->issuer,$this->actor,$credit);
    }
    public function replayed():bool{return $this->replayed;}
}
