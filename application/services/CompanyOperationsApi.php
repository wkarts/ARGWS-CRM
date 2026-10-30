<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyApiTokens.php';require_once __DIR__.'/CompanyProjects.php';require_once __DIR__.'/CompanyTasks.php';require_once __DIR__.'/CompanyStandaloneTasks.php';require_once __DIR__.'/CompanyContracts.php';require_once __DIR__.'/CompanyTickets.php';
require_once __DIR__.'/CompanyTaskRecurring.php';
/** Native operational API. Bearer issuer/actor are persisted; no browser/default-company authority. */
final class CompanyOperationsApi
{
    private CompanyApiTokens $tokens;
    private const DOMAINS=['projects'=>[CompanyProjects::class,'projects'],'tasks'=>[CompanyTasks::class,'tasks'],'standalone_tasks'=>[CompanyStandaloneTasks::class,'tasks'],'contracts'=>[CompanyContracts::class,'contracts'],'tickets'=>[CompanyTickets::class,'tickets'],'task_schedules'=>[CompanyTaskRecurring::class,'tasks'],'standalone_schedules'=>[CompanyTaskRecurring::class,'tasks']];
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');CompanyApiSchema::assertValid($db,$p);$this->tokens=new CompanyApiTokens($db,$p);}
    private function domain(string $domain):array{if(!isset(self::DOMAINS[$domain]))throw new DomainException('company_api_invalid_input');return self::DOMAINS[$domain];}
    private function consumer(string $secret,string $domain,string $mode,string $action,?string $requestKey=null):array
    {
        [$class,$feature]=$this->domain($domain);$scope=$feature.':'.$mode;$who=$this->tokens->resolve($secret,$scope);$issuer=$who['issuer_id'];$actor=$who['actor_id'];$audited=false;
        $guard=function()use($secret,$scope,$issuer,$actor,$action,$requestKey,$domain,&$audited):void{(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->tokens->guard($secret,$scope,$issuer,$actor);if(!$audited){$this->tokens->usedInOwnedTransaction($secret,$scope,$issuer,$actor,$action,$requestKey,['domain'=>$domain]);$audited=true;}};
        return [$who,new $class($this->db,$this->p,$guard)];
    }
    public function read(string $secret,string $domain,$id=null,$after=0,$project=null,string $search=''):array
    {
        if($id!==null)$id=IssuerPolicy::id($id);[$who,$service]=$this->consumer($secret,$domain,'read',$id===null?'operation_list':'operation_read');
        if(in_array($domain,['task_schedules','standalone_schedules'],true)){
            if($project!==null||$search!=='')throw new DomainException('company_api_invalid_input');$kind=$domain==='task_schedules'?'project':'standalone';
            $result=$id===null?$service->page($who['issuer_id'],$who['actor_id'],$kind,$after):['record'=>$service->get($who['issuer_id'],$who['actor_id'],$kind,$id)];return ['issuer_id'=>$who['issuer_id'],'domain'=>$domain]+$result;
        }
        if($id!==null)$result=['record'=>$service->get($who['issuer_id'],$who['actor_id'],$id)];elseif($domain==='tasks')$result=$service->page($who['issuer_id'],$who['actor_id'],IssuerPolicy::id($project),$after);elseif(in_array($domain,['contracts','tickets'],true))$result=$service->page($who['issuer_id'],$who['actor_id'],$after,$search);else $result=$service->page($who['issuer_id'],$who['actor_id'],$after);
        return ['issuer_id'=>$who['issuer_id'],'domain'=>$domain]+$result;
    }
    private static function payload(array $body):array
    {
        if(array_diff(array_keys($body),['action','revision','input'])||!is_string($body['action']??null)||!is_array($body['input']??[]))throw new DomainException('company_api_invalid_input');return $body['input']??[];
    }
    private static function fields(array $input,array $allowed):void{if(array_diff(array_keys($input),$allowed))throw new DomainException('company_api_invalid_input');}
    public function write(string $secret,string $domain,$id,array $body,string $idempotency):array
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$idempotency))throw new DomainException('company_api_invalid_input');$input=self::payload($body);$action=$body['action'];$this->domain($domain);$create=$action==='create';if($create){if($id!==null||array_key_exists('revision',$body))throw new DomainException('company_api_invalid_input');}else{$id=IssuerPolicy::id($id);$revision=IssuerPolicy::id($body['revision']??null);}
        // The namespaced key survives credential rotation for the same persisted actor/company.
        [$featureClass,$feature]=$this->domain($domain);$who=$this->tokens->resolve($secret,$feature.':write');$key=hash('sha256','operations-api|'.$who['issuer_id'].'|'.$who['actor_id'].'|'.$domain.'|'.$action.'|'.$idempotency);[$who,$service]=$this->consumer($secret,$domain,'write','operation_'.$action,$key);$issuer=$who['issuer_id'];$actor=$who['actor_id'];
        if(in_array($domain,['task_schedules','standalone_schedules'],true)){
            $kind=$domain==='task_schedules'?'project':'standalone';
            if($create){self::fields($input,['source_id','source_revision','calendar']);if(count($input)!==3||!is_array($input['calendar']??null))throw new DomainException('company_api_invalid_input');$result=$service->create($issuer,$actor,$kind,$input['source_id'],$input['source_revision'],$input['calendar'],$key,['token_id'=>$who['token_id'],'scope'=>'tasks:write']);}
            elseif(in_array($action,['pause','resume','cancel'],true)){if($input!==[])throw new DomainException('company_api_invalid_input');$result=$service->transition($issuer,$actor,$kind,$id,$revision,$action,$key);}
            else throw new DomainException('company_api_invalid_input');
        }
        elseif($create)$result=$service->create($issuer,$actor,$input,$key);
        elseif($domain==='projects'&&$action==='update')$result=$service->update($issuer,$actor,$id,$revision,$input,$key);
        elseif(in_array($domain,['tasks','standalone_tasks'],true)){
            if($action==='update')$result=$service->update($issuer,$actor,$id,$revision,$input,$key);
            elseif(in_array($action,['comment','start','stop'],true)){self::fields($input,$action==='comment'?['body']:[]);$bodyText=$input['body']??'';if(!is_string($bodyText))throw new DomainException('company_api_invalid_input');$result=$service->activity($issuer,$actor,$id,$revision,$action,$key,$bodyText);}
            else throw new DomainException('company_api_invalid_input');
        }elseif($domain==='contracts'){
            if($action==='update')$result=$service->update($issuer,$actor,$id,$revision,$input,$key);elseif(in_array($action,['activate','cancel','copy'],true)){if($input!==[])throw new DomainException('company_api_invalid_input');$result=$service->transition($issuer,$actor,$id,$revision,$action,$key);}else throw new DomainException('company_api_invalid_input');
        }elseif($domain==='tickets'){
            if($action==='reply'){self::fields($input,['body']);if(!is_string($input['body']??null))throw new DomainException('company_api_invalid_input');$result=$service->reply($issuer,$actor,$id,$revision,$input['body'],$key);}
            elseif($action==='change'){self::fields($input,['assignee_id','status','priority']);if(!is_string($input['status']??null)||!is_string($input['priority']??null))throw new DomainException('company_api_invalid_input');$result=$service->change($issuer,$actor,$id,$revision,$input['assignee_id']??null,$input['status'],$input['priority'],$key);}else throw new DomainException('company_api_invalid_input');
        }else throw new DomainException('company_api_invalid_input');
        return ['issuer_id'=>$issuer,'domain'=>$domain,'id'=>$result];
    }
}
