<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyExpenses.php';require_once __DIR__.'/CompanyExpenseFiles.php';require_once __DIR__.'/CompanyExpenseApiReceipt.php';

/** Explicit company expense API. Costs and evidence reuse existing writers; no cash or provider instructions. */
final class CompanyExpenseApi
{
    private CompanyApiTokens $tokens;
    public function __construct(private $db,private string $p){CompanyApiSchema::assertValid($db,$p);CompanyFinancialApiSchema::assertValid($db,$p);$this->tokens=new CompanyApiTokens($db,$p);}
    private static function canonical($v){if(!is_array($v))return$v;if(!array_is_list($v))ksort($v,SORT_STRING);foreach($v as&$child)$child=self::canonical($child);unset($child);return$v;}
    private static function fields(array $v,array $allowed):void{if(array_diff(array_keys($v),$allowed))throw new DomainException('company_api_invalid_input');}
    private static function key(string $key):void{if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_api_invalid_input');}
    private function consumer(string $secret,string $scope,string $operation,array $target,array $input,string $key,string $native):array
    {
        self::key($key);$who=$this->tokens->resolve($secret,$scope);$request=hash('sha256','company-expense-api|'.$who['issuer_id'].'|'.$who['actor_id'].'|'.$operation.'|'.$key);$hash=hash('sha256',json_encode(self::canonical([$operation,$target,$input]),JSON_THROW_ON_ERROR));$guard=new CompanyExpenseApiReceipt($this->db,$this->p,$secret,$scope,$who['issuer_id'],$who['actor_id'],$operation,$target,$request,$hash,$native);return[$who,$guard,$request];
    }
    private function readWith(string $secret,string $action,callable $work):array
    {
        $who=$this->tokens->resolve($secret,'expenses:read');$service=new CompanyExpenses($this->db,$this->p);return IssuerTransaction::run($this->db,$this->p,array_merge($service->tables(),['company_api_tokens','company_api_token_requests']),function()use($secret,$action,$work,$who,$service):array{$i=$who['issuer_id'];$a=$who['actor_id'];(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($i,$a);$this->tokens->guard($secret,'expenses:read',$i,$a);$this->tokens->usedInOwnedTransaction($secret,'expenses:read',$i,$a,$action);$data=$work($service,$i,$a);$this->tokens->guard($secret,'expenses:read',$i,$a);if($work($service,$i,$a)!==$data)throw new DomainException('company_api_resource_changed');return['issuer_id'=>$i]+$data;});
    }
    public function read(string $secret,$id=null,$after=0,string $search=''):array
    {
        if($id!==null)$id=IssuerPolicy::id($id);$after=$after===0||$after==='0'?0:IssuerPolicy::id($after);return$this->readWith($secret,$id===null?'expense_list':'expense_read',function($service,$i,$a)use($id,$after,$search):array{if($id===null)return$service->page($i,$a,$after,50,$search);$record=$service->get($i,$a,$id);if(!$record)throw new DomainException('company_expense_denied');return['record'=>$record];});
    }
    public function categories(string $secret,$id=null):array
    {
        if($id!==null)$id=IssuerPolicy::id($id);return$this->readWith($secret,'expense_category_list',function($service,$i,$a)use($id):array{$rows=$service->categories($i,$a);if($id===null)return['items'=>$rows];foreach($rows as$row)if((int)$row['id']===$id)return['record'=>$row];throw new DomainException('company_expense_denied');});
    }
    public function write(string $secret,$id,array $body,string $key):array
    {
        self::fields($body,['action','input','revision']);$action=$body['action']??null;$input=$body['input']??[];if(!is_string($action)||!is_array($input))throw new DomainException('company_api_invalid_input');$scope=match($action){'create'=>'expenses:create','update'=>'expenses:edit','cancel'=>'expenses:cancel',default=>throw new DomainException('company_api_invalid_input')};if($action==='create'){if($id!==null||array_key_exists('revision',$body))throw new DomainException('company_api_invalid_input');}else{$id=IssuerPolicy::id($id);$revision=$body['revision']??null;if(!is_string($revision)||!preg_match('/^[a-f0-9]{64}$/D',$revision)||$action==='cancel'&&$input!==[])throw new DomainException('company_api_invalid_input');}
        [$who,$guard,$native]=$this->consumer($secret,$scope,'expense_'.$action,['id'=>$id],$body,$key,$action==='update'?'update':$action);$writer=new CompanyExpenses($this->db,$this->p,$guard,$guard->tables());if($action==='create')$id=$writer->create($who['issuer_id'],$who['actor_id'],$input,$native);else$writer->change($who['issuer_id'],$who['actor_id'],$id,$revision,$native,$action==='cancel'?null:$input);return['issuer_id'=>$who['issuer_id'],'expense_id'=>$id,'replayed'=>$guard->replayed()];
    }
    public function category(string $secret,$id,array $body,string $key):array
    {
        self::fields($body,['action','input','revision']);$action=$body['action']??null;$input=$body['input']??null;if(!in_array($action,['create','update'],true)||!is_array($input))throw new DomainException('company_api_invalid_input');self::fields($input,['name','active']);$name=$input['name']??null;$active=$input['active']??true;if(!is_string($name)||!is_bool($active))throw new DomainException('company_api_invalid_input');$revision=null;if($action==='create'){if($id!==null||array_key_exists('revision',$body))throw new DomainException('company_api_invalid_input');}else{$id=IssuerPolicy::id($id);$revision=$body['revision']??null;if(!is_string($revision)||!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new DomainException('company_api_invalid_input');}
        [$who,$guard,$native]=$this->consumer($secret,'expenses:categories:write','expense_category',['id'=>$id],$body,$key,'category');$id=(new CompanyExpenses($this->db,$this->p,$guard,$guard->tables()))->saveCategory($who['issuer_id'],$who['actor_id'],$name,$native,$id,$revision,$active);return['issuer_id'=>$who['issuer_id'],'category_id'=>$id,'replayed'=>$guard->replayed()];
    }
    private function fileReader(string $secret,string $action):array
    {
        $who=$this->tokens->resolve($secret,'expenses:files:read');$used=false;$guard=function($phase,$i,$a)use($secret,$who,$action,&$used):void{if($who['issuer_id']!==$i||$who['actor_id']!==$a)throw new DomainException('company_api_scope_denied');$this->tokens->guard($secret,'expenses:files:read',$i,$a);if(!$used){$this->tokens->usedInOwnedTransaction($secret,'expenses:files:read',$i,$a,$action);$used=true;}$this->tokens->guard($secret,'expenses:files:read',$i,$a);};return[$who,new CompanyExpenseFiles($this->db,$this->p,$guard,['company_api_tokens','company_api_token_requests'])];
    }
    public function files(string $secret,$expense):array{$expense=IssuerPolicy::id($expense);[$who,$reader]=$this->fileReader($secret,'expense_file_list');return['issuer_id'=>$who['issuer_id'],'expense_id'=>$expense,'items'=>$reader->page($who['issuer_id'],$who['actor_id'],$expense)];}
    public function file(string $secret,$expense,string $file):array{$expense=IssuerPolicy::id($expense);self::key($file);[$who,$reader]=$this->fileReader($secret,'expense_file_download');return['issuer_id'=>$who['issuer_id'],'expense_id'=>$expense]+$reader->download($who['issuer_id'],$who['actor_id'],$expense,$file);}
    public function fileWrite(string $secret,$expense,array $body,string $key):array
    {
        $expense=IssuerPolicy::id($expense);self::fields($body,['action','input']);$action=$body['action']??null;$input=$body['input']??null;if(!in_array($action,['upload','hide'],true)||!is_array($input))throw new DomainException('company_api_invalid_input');$target=['expense_id'=>$expense];
        if($action==='upload'){self::fields($input,['filename','content_base64']);$name=$input['filename']??null;$encoded=$input['content_base64']??null;if(!is_string($name)||strlen($name)>191||!is_string($encoded)||strlen($encoded)<12||strlen($encoded)>2796204||preg_match('/[^A-Za-z0-9+\/=]/',$encoded))throw new DomainException('company_api_invalid_input');$bytes=base64_decode($encoded,true);if(!is_string($bytes)||strlen($bytes)>2097152||base64_encode($bytes)!==$encoded)throw new DomainException('company_api_invalid_input');}
        else{self::fields($input,['file_key','content_hash']);$file=$input['file_key']??null;$hash=$input['content_hash']??null;if(!is_string($file)||!is_string($hash))throw new DomainException('company_api_invalid_input');self::key($file);self::key($hash);$target+=['file_key'=>$file,'content_hash'=>$hash];}
        [$who,$guard,$native]=$this->consumer($secret,'expenses:files:write','expense_'.$action,$target,$body,$key,$action);$writer=new CompanyExpenseFiles($this->db,$this->p,$guard,$guard->tables());if($action==='upload')$file=$writer->upload($who['issuer_id'],$who['actor_id'],$expense,$native,$name,$bytes);else$writer->hide($who['issuer_id'],$who['actor_id'],$expense,$file,$hash);return['issuer_id'=>$who['issuer_id'],'expense_id'=>$expense,'file_key'=>$file,'replayed'=>$guard->replayed()];
    }
}
