<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyInvoiceDrafts.php';require_once __DIR__.'/CompanyApiTokens.php';

/** New opt-in scopes; the bearer can veto existing writers and never receives authority from a browser session. */
final class CompanyInvoiceDraftsApi
{
    private CompanyApiTokens $tokens;private CompanyInvoiceDrafts $drafts;
    public function __construct(private $db,private string $p){CompanyApiSchema::assertValid($db,$p);CompanyInvoiceDraftsSchema::assertValid($db,$p);$this->tokens=new CompanyApiTokens($db,$p);$this->drafts=new CompanyInvoiceDrafts($db,$p);}
    private function tables():array{return array_merge($this->drafts->tables(),['company_api_tokens','company_api_token_requests']);}
    public function read(string $secret,$id=null,$after=0):array
    {
        $who=$this->tokens->resolve($secret,'drafts:read');return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($secret,$who,$id,$after):array{$i=$who['issuer_id'];$a=$who['actor_id'];$this->tokens->guard($secret,'drafts:read',$i,$a);$this->tokens->usedInOwnedTransaction($secret,'drafts:read',$i,$a,$id===null?'draft_list':'draft_read',null,$id===null?[]:['draft_id'=>IssuerPolicy::id($id)]);$read=fn()=>$id===null?$this->drafts->pageInOwned($i,$a,$after):$this->drafts->documentInOwned($i,$a,$id);$value=$read();$this->tokens->guard($secret,'drafts:read',$i,$a);if($read()!==$value)throw new DomainException('company_api_resource_changed');return['issuer_id'=>$i]+$value;});
    }
    public function write(string $secret,string $operation,$id,array $input,string $key):array
    {
        $scope=match($operation){'create'=>'drafts:create','change'=>'drafts:edit','cancel'=>'drafts:cancel','issue'=>'drafts:issue',default=>throw new DomainException('company_api_invalid_input')};$key=CompanyInvoiceDraftsInput::key($key);$who=$this->tokens->resolve($secret,$scope);$i=$who['issuer_id'];$a=$who['actor_id'];$native=hash('sha256','company-draft-api:'.$i.':'.$a.':'.$operation.':'.$key);$audited=false;$guard=function()use($secret,$scope,$i,$a,$operation,$native,&$audited):void{$this->tokens->guard($secret,$scope,$i,$a);if(!$audited){$this->tokens->usedInOwnedTransaction($secret,$scope,$i,$a,'draft_'.$operation,$native);$audited=true;}$this->tokens->guard($secret,$scope,$i,$a);};
        if($operation==='create')$id=$this->drafts->create($i,$a,$input,$native,$guard,$this->tables());else{$id=IssuerPolicy::id($id);if((array_diff(array_keys($input),$operation==='change'?['revision','payload']:['revision'])||count($input)!==($operation==='change'?2:1)))throw new DomainException('company_api_invalid_input');$revision=CompanyInvoiceDraftsInput::revision($input['revision']??null);if($operation==='issue')$this->drafts->issue($i,$a,$id,$revision,$native,$guard,$this->tables(),fn()=>$this->tokens->guard($secret,$scope,$i,$a));else{$payload=$operation==='cancel'?null:($input['payload']??null);if($operation==='change'&&!is_array($payload))throw new DomainException('company_api_invalid_input');$this->drafts->change($i,$a,$id,$revision,$payload,$native,$guard,$this->tables());}}
        // Return only the owned identifier. A fresh read is separately authorized; no post-commit body leak.
        return['issuer_id'=>$i,'draft_id'=>$id];
    }
}
