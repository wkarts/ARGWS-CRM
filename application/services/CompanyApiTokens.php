<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyApiSchema.php';require_once __DIR__.'/CompanyReports.php';

/** Independent company API credentials. Never consumes a legacy module API token. */
final class CompanyApiTokens
{
    public const SCOPES=['projects:copies:read'=>[['projects','view'],['projects','create'],['customers','view']],'projects:copies:write'=>[['projects','view'],['projects','create'],['customers','view']],'invoices:read'=>[['invoices','view']],'invoices:pdf'=>[['invoices','view']],'invoices:create'=>[['invoices','view'],['invoices','create'],['customers','view']],'reports:read'=>CompanyReports::CAPABILITIES,'projects:read'=>[['projects','view']],'projects:write'=>[['projects','view'],['projects','create'],['projects','edit'],['customers','view']],'tasks:read'=>[['tasks','view']],'tasks:write'=>[['tasks','view'],['tasks','create'],['tasks','edit']],'contracts:read'=>[['contracts','view']],'contracts:write'=>[['contracts','view'],['contracts','create'],['contracts','edit'],['customers','view']],'tickets:read'=>[['tickets','view']],'tickets:write'=>[['tickets','view'],['tickets','create'],['tickets','edit'],['customers','view']],
        'projects:files:read'=>[['projects','view']],
        'projects:files:write'=>[['projects','view'],['projects','edit']],
        'tasks:files:read'=>[['tasks','view'],['projects','view']],
        'tasks:files:write'=>[['tasks','view'],['projects','view'],['tasks','edit']],
        'contracts:files:read'=>[['contracts','view']],
        'contracts:files:write'=>[['contracts','view'],['contracts','edit']],
        'tickets:files:read'=>[['tickets','view']],
        'tickets:files:write'=>[['tickets','view'],['tickets','edit']],
        'projects:milestones:read'=>[['projects','view']],
        'projects:milestones:write'=>[['projects','view'],['projects','edit']],
        'tasks:dependencies:read'=>[['tasks','view'],['projects','view']],
        'tasks:dependencies:write'=>[['tasks','view'],['projects','view'],['tasks','edit']],
        'projects:follow:read'=>[['projects','view']],
        'projects:follow:write'=>[['projects','view']],
        'tasks:follow:read'=>[['tasks','view'],['projects','view']],
        'tasks:follow:write'=>[['tasks','view'],['projects','view']]
        ,'drafts:read'=>[['invoices','view'],['customers','view']],'drafts:create'=>[['invoices','view'],['invoices','create'],['customers','view']],'drafts:edit'=>[['invoices','view'],['invoices','edit'],['customers','view']],'drafts:cancel'=>[['invoices','view'],['invoices','delete'],['customers','view']],'drafts:issue'=>[['invoices','view'],['invoices','create'],['customers','view']],
        'payments:read'=>[['invoices','view']],
        'payments:create'=>[['invoices','view'],['payments','create']],
        'payments:reverse'=>[['invoices','view'],['payments','delete']],
        'credits:read'=>[['credit_notes','view']],
        'credits:pdf'=>[['credit_notes','view']],
        'credits:create'=>[['credit_notes','view'],['credit_notes','create'],['customers','view']],
        'credits:apply'=>[['credit_notes','view'],['credit_notes','edit'],['payments','create'],['invoices','view']],
        'credits:unapply'=>[['credit_notes','view'],['credit_notes','edit'],['payments','delete'],['invoices','view']],
        'credits:refund'=>[['credit_notes','view'],['credit_notes','edit'],['payments','create']],
        'credits:refund_reverse'=>[['credit_notes','view'],['credit_notes','edit'],['payments','delete']],
        'credits:void'=>[['credit_notes','view'],['credit_notes','delete']]
    ];
    private IssuerResourceAccess $acl;
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');$this->acl=new IssuerResourceAccess($db,$p);}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function authorize(int $issuer,int $actor):void{if(!$this->acl->can($issuer,$actor,'company_api','manage'))throw new DomainException('company_api_access_denied');}
    private function scope(int $issuer,int $actor,string $scope):void{if(!isset(self::SCOPES[$scope]))throw new DomainException('company_api_scope_denied');foreach(self::SCOPES[$scope] as [$feature,$cap])if(!$this->acl->can($issuer,$actor,$feature,$cap))throw new DomainException('company_api_scope_denied');}
    private function transaction(int $issuer,int $actor,callable $work)
    {
        $this->authorize($issuer,$actor);CompanyApiSchema::assertValid($this->db,$this->p);return IssuerTransaction::run($this->db,$this->p,['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','company_api_tokens','company_api_token_requests','issuers_audit'],function()use($issuer,$actor,$work){$this->acl->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor);$result=$work();$this->authorize($issuer,$actor);return $result;});
    }
    private static function now():string{return gmdate('Y-m-d H:i:s');}
    private static function key(string $key):void{if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_api_invalid_input');}
    private function audit(int $issuer,int $actor,string $id,string $event,array $extra=[]):void{$json=json_encode(['token_id'=>$id]+$extra,JSON_THROW_ON_ERROR);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$actor,$event,$issuer,$issuer,date('Y-m-d H:i:s'),$json]);$audit=(int)$this->db->insert_id();if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND event=? AND metadata_json=?',[$audit,$issuer,$event,$json]))throw new RuntimeException('issuer_api_write_unconfirmed');}
    public function issue($issuer,$actor,string $label,array $scopes,int $days,string $key,?string $rotate=null):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);self::key($key);if($label===''||trim($label)!==$label||strlen($label)>100||preg_match('//u',$label)!==1||preg_match('/[\x00-\x1f\x7f]/',$label)||$days<1||$days>90||count($scopes)<1||count($scopes)>count(self::SCOPES))throw new DomainException('company_api_invalid_input');foreach($scopes as $scope)if(!is_string($scope)||!isset(self::SCOPES[$scope]))throw new DomainException('company_api_invalid_input');$scopes=array_values(array_unique($scopes));sort($scopes,SORT_STRING);if($rotate!==null&&!preg_match('/^[a-f0-9]{32}$/D',$rotate))throw new DomainException('company_api_invalid_input');$hash=hash('sha256',json_encode([$label,$scopes,$days,$rotate],JSON_THROW_ON_ERROR));
        return $this->transaction($issuer,$actor,function()use($issuer,$actor,$label,$scopes,$days,$key,$rotate,$hash):array{
            foreach($scopes as $scope)$this->scope($issuer,$actor,$scope);$old=$this->rows('SELECT * FROM '.$this->p.'company_api_token_requests WHERE request_key=? FOR UPDATE',[$key])[0]??null;if($old){if((int)$old['issuer_id']!==$issuer||(int)$old['actor_id']!==$actor||!hash_equals($old['input_hash'],$hash))throw new DomainException('company_api_request_conflict');return ['token_id'=>$old['token_id'],'secret'=>null,'replayed'=>true];}
            if($rotate!==null){$prior=$this->rows('SELECT * FROM '.$this->p.'company_api_tokens WHERE token_id=? AND issuer_id=? AND actor_id=? FOR UPDATE',[$rotate,$issuer,$actor])[0]??null;if(!$prior||!hash_equals($prior['scopes_json'],json_encode($scopes,JSON_THROW_ON_ERROR)))throw new DomainException('company_api_access_denied');if((int)$prior['active']!==1)throw new DomainException('company_api_request_conflict');IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_api_tokens SET active=0 WHERE token_id=? AND issuer_id=? AND actor_id=?',[$rotate,$issuer,$actor]);if($this->rows('SELECT token_id FROM '.$this->p.'company_api_tokens WHERE token_id=? AND active=1',[$rotate]))throw new RuntimeException('issuer_api_write_unconfirmed');}
            $count=(int)$this->rows('SELECT COUNT(*) n FROM '.$this->p.'company_api_tokens WHERE issuer_id=? AND actor_id=? AND active=1 AND expires_at>?',[$issuer,$actor,self::now()])[0]['n'];if($count>=20)throw new DomainException('company_api_token_limit');$id=bin2hex(random_bytes(16));$secret='crmco_'.$id.'_'.bin2hex(random_bytes(32));$digest=hash('sha256',$secret);$expires=gmdate('Y-m-d H:i:s',time()+$days*86400);$json=json_encode($scopes,JSON_THROW_ON_ERROR);
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_api_tokens(token_id,issuer_id,actor_id,token_hash,label,scopes_json,active,expires_at,created_at,last_used_at) VALUES(?,?,?,?,?,?,1,?,?,NULL)',[$id,$issuer,$actor,$digest,$label,$json,$expires,self::now()]);
            if(!$this->rows('SELECT token_id FROM '.$this->p.'company_api_tokens WHERE token_id=? AND issuer_id=? AND actor_id=? AND token_hash=? AND scopes_json=? AND active=1 AND expires_at=?',[$id,$issuer,$actor,$digest,$json,$expires]))throw new RuntimeException('issuer_api_write_unconfirmed');IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_api_token_requests(request_key,issuer_id,actor_id,input_hash,token_id,created_at) VALUES(?,?,?,?,?,?)',[$key,$issuer,$actor,$hash,$id,self::now()]);if(!$this->rows('SELECT request_key FROM '.$this->p.'company_api_token_requests WHERE request_key=? AND input_hash=? AND token_id=?',[$key,$hash,$id]))throw new RuntimeException('issuer_api_write_unconfirmed');$this->audit($issuer,$actor,$id,$rotate?'company_api_token_rotated':'company_api_token_created',['scopes'=>$scopes]);if($rotate)$this->audit($issuer,$actor,$rotate,'company_api_token_revoked');return ['token_id'=>$id,'secret'=>$secret,'replayed'=>false,'expires_at'=>$expires];
        });
    }
    public function revoke($issuer,$actor,string $id):void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new DomainException('company_api_invalid_input');$this->transaction($issuer,$actor,function()use($issuer,$actor,$id):void{$row=$this->rows('SELECT active FROM '.$this->p.'company_api_tokens WHERE token_id=? AND issuer_id=? AND actor_id=? FOR UPDATE',[$id,$issuer,$actor])[0]??null;if(!$row)throw new DomainException('company_api_access_denied');if((int)$row['active']===0)return;IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_api_tokens SET active=0 WHERE token_id=? AND issuer_id=? AND actor_id=?',[$id,$issuer,$actor]);if($this->rows('SELECT token_id FROM '.$this->p.'company_api_tokens WHERE token_id=? AND active=1',[$id]))throw new RuntimeException('issuer_api_write_unconfirmed');$this->audit($issuer,$actor,$id,'company_api_token_revoked');});
    }
    public function page($issuer,$actor):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$this->authorize($issuer,$actor);$rows=$this->rows('SELECT token_id,label,scopes_json,active,expires_at,created_at,last_used_at FROM '.$this->p.'company_api_tokens WHERE issuer_id=? AND actor_id=? ORDER BY created_at DESC,token_id LIMIT 100',[$issuer,$actor]);foreach($rows as &$row){$row['scopes']=json_decode($row['scopes_json'],true,16,JSON_THROW_ON_ERROR);unset($row['scopes_json']);}unset($row);$this->authorize($issuer,$actor);return $rows;
    }
    /** Authentication is rechecked by every in-transaction consumer, not cached by session. */
    public function resolve(string $secret,string $scope,bool $lock=false):array
    {
        if(!preg_match('/^crmco_([a-f0-9]{32})_[a-f0-9]{64}$/D',$secret,$m))throw new DomainException('company_api_unauthenticated');$row=$this->rows('SELECT * FROM '.$this->p.'company_api_tokens WHERE token_id=?'.($lock?' FOR UPDATE':''),[$m[1]])[0]??null;
        if(!$row||!hash_equals($row['token_hash'],hash('sha256',$secret))||(int)$row['active']!==1||$row['expires_at']<=self::now())throw new DomainException('company_api_unauthenticated');
        $issuer=IssuerPolicy::id($row['issuer_id']);$actor=IssuerPolicy::id($row['actor_id']);$this->authorize($issuer,$actor);$scopes=json_decode($row['scopes_json'],true,16,JSON_THROW_ON_ERROR);if(!is_array($scopes)||!in_array($scope,$scopes,true))throw new DomainException('company_api_scope_denied');$this->scope($issuer,$actor,$scope);return ['token_id'=>$row['token_id'],'issuer_id'=>$issuer,'actor_id'=>$actor,'scope'=>$scope];
    }
    /** A veto-only guard for existing canonical writers/readers; may not start or commit a transaction. */
    public function guard(string $secret,string $scope,int $issuer,int $actor):void
    {
        $row=$this->resolve($secret,$scope,true);if($row['issuer_id']!==$issuer||$row['actor_id']!==$actor)throw new DomainException('company_api_scope_denied');
    }
    public function usedInOwnedTransaction(string $secret,string $scope,int $issuer,int $actor,string $action,?string $requestKey=null,array $context=[]):void
    {
        IssuerTransaction::assertOwned($this->db,$this->p,['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_audit','company_api_tokens','company_api_token_requests']);$row=$this->resolve($secret,$scope,true);if($row['issuer_id']!==$issuer||$row['actor_id']!==$actor)throw new DomainException('company_api_scope_denied');$now=self::now();IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_api_tokens SET last_used_at=? WHERE token_id=? AND active=1',[$now,$row['token_id']]);if(!$this->rows('SELECT token_id FROM '.$this->p.'company_api_tokens WHERE token_id=? AND issuer_id=? AND actor_id=? AND active=1 AND last_used_at=?',[$row['token_id'],$issuer,$actor,$now]))throw new RuntimeException('issuer_api_write_unconfirmed');$this->audit($issuer,$actor,$row['token_id'],'company_api_request_authorized',['action'=>$action,'request_key'=>$requestKey,'context'=>$context]);
    }
    public function availableScopes($issuer,$actor):array{$issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$this->authorize($issuer,$actor);$result=[];foreach(array_keys(self::SCOPES) as $scope){try{$this->scope($issuer,$actor,$scope);$result[]=$scope;}catch(DomainException $e){}}return $result;}
}
