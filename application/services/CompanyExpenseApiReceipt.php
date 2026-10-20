<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyApiTokens.php';require_once __DIR__.'/CompanyFinancialApiSchema.php';

/** Veto-only bearer provenance inside the native expense/file writer; reuses439 mutation receipts. */
final class CompanyExpenseApiReceipt
{
    private CompanyApiTokens $tokens;private ?array $prior=null;private ?array $priorNative=null;private bool $started=false;private bool $finished=false;
    public function __construct(private $db,private string $p,private string $secret,private string $scope,private int $issuer,private int $actor,private string $operation,private array $target,private string $key,private string $inputHash,private string $nativeOperation)
    {
        if(!in_array($operation,['expense_create','expense_update','expense_cancel','expense_category','expense_upload','expense_hide'],true)||!preg_match('/^[a-f0-9]{64}$/D',$key)||!preg_match('/^[a-f0-9]{64}$/D',$inputHash))throw new DomainException('company_api_invalid_input');CompanyFinancialApiSchema::assertValid($db,$p);$this->tokens=new CompanyApiTokens($db,$p);
    }
    public function tables():array{return['company_api_tokens','company_api_token_requests','company_financial_api_requests'];}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function authorize(int $issuer,int $actor):void
    {
        if($issuer!==$this->issuer||$actor!==$this->actor)throw new DomainException('company_api_scope_denied');IssuerTransaction::assertOwned($this->db,$this->p,array_merge($this->tables(),['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_audit','company_expenses','company_expense_files']));$this->tokens->guard($this->secret,$this->scope,$issuer,$actor);
    }
    private function apiRow():?array{return$this->rows('SELECT r.* FROM '.$this->p.'company_financial_api_requests r JOIN '.$this->p.'company_api_tokens t ON t.token_id=r.token_id AND t.issuer_id=r.issuer_id AND t.actor_id=r.actor_id WHERE r.request_key=? FOR UPDATE',[$this->key])[0]??null;}
    private function inspectApi(array $r):array
    {
        if((int)$r['issuer_id']!==$this->issuer||(int)$r['actor_id']!==$this->actor||$r['operation']!==$this->operation||!hash_equals($r['input_hash'],$this->inputHash))throw new DomainException('company_api_request_conflict');$data=json_decode($r['target_json'],true,16,JSON_THROW_ON_ERROR);if(!is_array($data)||array_keys($data)!==['target','result']||$data['target']!==$this->target||!is_array($data['result'])||json_encode($data,JSON_THROW_ON_ERROR)!==$r['target_json'])throw new DomainException('company_api_request_conflict');return$data['result'];
    }
    private function proof(array $result):array
    {
        if(in_array($this->operation,['expense_upload','expense_hide'],true)){
            $expense=IssuerPolicy::id($result['expense_id']??null);$file=$result['file_key']??null;if(!is_string($file)||!preg_match('/^[a-f0-9]{64}$/D',$file)||$expense!==($this->target['expense_id']??null))throw new RuntimeException('issuer_expense_api_unconfirmed');$parent=$this->rows('SELECT id,issuer_id,state FROM '.$this->p.'company_expenses WHERE id=? AND issuer_id=? FOR UPDATE',[$expense,$this->issuer])[0]??null;
            $r=$this->rows('SELECT * FROM '.$this->p.'company_expense_files WHERE file_key=? AND issuer_id=? AND expense_id=? FOR UPDATE',[$file,$this->issuer,$expense])[0]??null;if(!$parent||!$r||(int)$r['byte_size']<8||(int)$r['byte_size']>2097152||strlen($r['content'])!==(int)$r['byte_size']||!hash_equals($r['content_hash'],hash('sha256',$r['content']))||!in_array($r['mime'],['application/pdf','image/png'],true))throw new RuntimeException('issuer_expense_api_unconfirmed');if($this->operation==='expense_upload'&&($file!==$this->key||(int)$r['actor_id']!==$this->actor)||$this->operation==='expense_hide'&&((int)$r['active']!==0||$file!==$this->target['file_key']||$r['content_hash']!==$this->target['content_hash']))throw new RuntimeException('issuer_expense_api_unconfirmed');return['parent'=>$parent,'file'=>$r];
        }
        $id=IssuerPolicy::id($result['id']??null);$r=$this->rows('SELECT * FROM '.$this->p.'company_expense_requests WHERE request_key=? FOR UPDATE',[$this->key])[0]??null;if(!$r||(int)$r['issuer_id']!==$this->issuer||(int)$r['actor_id']!==$this->actor||$r['operation']!==$this->nativeOperation||(int)$r['result_id']!==$id||!preg_match('/^[a-f0-9]{64}$/D',$r['input_hash']))throw new RuntimeException('issuer_expense_api_unconfirmed');$table=$this->operation==='expense_category'?'company_expense_categories':'company_expenses';$record=$this->rows('SELECT * FROM '.$this->p.$table.' WHERE id=? AND issuer_id=? FOR UPDATE',[$id,$this->issuer])[0]??null;if(!$record)throw new RuntimeException('issuer_expense_api_unconfirmed');return['receipt'=>$r,'record'=>$record];
    }
    public function __invoke(string $phase,int $issuer,int $actor,$nativeResult=null):void
    {
        $this->authorize($issuer,$actor);if($phase==='before'){
            if($this->started)throw new DomainException('company_api_scope_denied');$this->prior=$this->apiRow();if($this->prior){$result=$this->inspectApi($this->prior);$this->priorNative=$this->proof($result);}else{
                if($this->rows('SELECT request_key FROM '.$this->p.'company_financial_api_requests WHERE request_key=? FOR UPDATE',[$this->key]))throw new DomainException('company_api_request_conflict');
                if($this->operation==='expense_upload'){if($this->rows('SELECT file_key FROM '.$this->p.'company_expense_files WHERE file_key=? FOR UPDATE',[$this->key]))throw new DomainException('company_api_request_conflict');}
                elseif($this->operation!=='expense_hide'&&$this->rows('SELECT request_key FROM '.$this->p.'company_expense_requests WHERE request_key=? FOR UPDATE',[$this->key]))throw new DomainException('company_api_request_conflict');
            }$this->tokens->usedInOwnedTransaction($this->secret,$this->scope,$issuer,$actor,$this->operation,$this->key,$this->target);$this->started=true;$this->authorize($issuer,$actor);return;
        }
        if($phase!=='after'||!$this->started||$this->finished)throw new DomainException('company_api_scope_denied');$result=in_array($this->operation,['expense_upload','expense_hide'],true)?['expense_id'=>$this->target['expense_id'],'file_key'=>$this->operation==='expense_upload'?$this->key:$this->target['file_key']]:['id'=>IssuerPolicy::id($nativeResult??$this->target['id']??null)];$proof=$this->proof($result);
        if($this->prior){if($this->inspectApi($this->prior)!==$result||$this->priorNative!==$proof)throw new RuntimeException('issuer_expense_api_unconfirmed');}
        else{
            if(in_array($this->operation,['expense_create','expense_update','expense_cancel'],true))foreach(['asaas_company_fee_expenses'=>'company_expense_provider_source_immutable','company_expense_billing_links'=>'company_expense_billing_immutable']as$table=>$reason)if($this->db->table_exists($this->p.$table)&&$this->rows('SELECT expense_id FROM '.$this->p.$table.' WHERE expense_id=? AND issuer_id=?'.($table==='company_expense_billing_links'?" AND state IN ('converting','converted')":'').' FOR UPDATE',[$result['id'],$issuer]))throw new DomainException($reason);
            $who=$this->tokens->resolve($this->secret,$this->scope,true);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_financial_api_requests(request_key,issuer_id,actor_id,operation,input_hash,target_json,token_id,created_at) VALUES(?,?,?,?,?,?,?,?)',[$this->key,$issuer,$actor,$this->operation,$this->inputHash,json_encode(['target'=>$this->target,'result'=>$result],JSON_THROW_ON_ERROR),$who['token_id'],gmdate('Y-m-d H:i:s')]);
        }
        $row=$this->apiRow();if(!$row||$this->inspectApi($row)!==$result||$this->proof($result)!==$proof)throw new RuntimeException('issuer_expense_api_unconfirmed');$this->authorize($issuer,$actor);$this->finished=true;
    }
    public function replayed():bool{return$this->prior!==null;}
}
