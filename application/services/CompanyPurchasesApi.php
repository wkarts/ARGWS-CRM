<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyPurchases.php';
require_once __DIR__.'/CompanyApiTokens.php';

/** Finite API: company and acting authority come only from the live persisted token. */
final class CompanyPurchasesApi
{
    public const SCOPES=[
        'purchases:read'=>[['purchases','view']],
        'purchases:write'=>[['purchases','view'],['purchases','create'],['purchases','edit']],
        'purchases:approve'=>[['purchases','view'],['purchases','approve']],
        'purchases:receive'=>[['purchases','view'],['purchases','receive']],
        'purchases:post'=>[['purchases','view'],['purchases','approve'],['expenses','view'],['expenses','create']],
        'purchases:pay'=>[['purchases','view'],['purchases','pay']],
        'purchases:portal'=>[['purchases','view'],['purchases','portal']],
        'purchases:report'=>[['purchases','view'],['purchases','report']],
        'purchases:settings'=>[['purchases','view'],['purchases','settings']],
    ];
    public const RESOURCES=['suppliers','requests','quotes','orders','receipts','documents','payables','events','files','settings','reports','invites','order_pdf','export'];
    private const KINDS=['suppliers'=>'supplier','requests'=>'request','quotes'=>'quote','orders'=>'order','receipts'=>'receipt','documents'=>'document','payables'=>'payable','events'=>'event'];
    public function __construct(private $db,private string $p) {}

    public function read(string $secret,string $resource,$id=null,array $query=[])
    {
        if (!in_array($resource,self::RESOURCES,true)) { throw new DomainException('company_api_invalid_input'); }
        CompanyPurchasesValues::fields($query,['after','from','as_of']);
        $scope=in_array($resource,['reports','export'],true)?'purchases:report':($resource==='invites'?'purchases:portal':'purchases:read');
        [$s,$who]=CompanyPurchases::forApi($this->db,$this->p,$secret,$scope); $i=$who['issuer_id']; $a=$who['actor_id'];
        if (in_array($resource,['reports','export'],true)) {
            if ($id!==null||!is_string($query['from']??null)||!is_string($query['as_of']??null)||($resource==='export'&&isset($query['after']))) { throw new DomainException('company_api_invalid_input'); }
            return $resource==='export'?$s->export($i,$a,$query['from'],$query['as_of']):$s->report($i,$a,$query['from'],$query['as_of'],CompanyPurchasesValues::integer($query['after']??0));
        }
        if (array_diff(array_keys($query),['after'])) { throw new DomainException('company_api_invalid_input'); }
        if (in_array($resource,['files','invites','order_pdf'],true)) {
            if ($query!==[]) { throw new DomainException('company_api_invalid_input'); }
            $id=IssuerPolicy::id($id);
            return match($resource) { 'files'=>$s->files($i,$a,$id),'invites'=>$s->invites($i,$a,$id),default=>$s->orderPdf($i,$a,$id) };
        }
        if ($resource==='settings') { if ($id!==null||$query!==[]) { throw new DomainException('company_api_invalid_input'); } return $s->configuration($i,$a); }
        $kind=self::KINDS[$resource]??null; if ($kind===null) { throw new DomainException('company_api_invalid_input'); }
        if ($id!==null) { if ($query!==[]) { throw new DomainException('company_api_invalid_input'); } return $s->get($i,$a,$kind,IssuerPolicy::id($id)); }
        return $s->page($i,$a,$kind,CompanyPurchasesValues::integer($query['after']??0));
    }

    public function file(string $secret,$id): array
    {
        [$s,$who]=CompanyPurchases::forApi($this->db,$this->p,$secret,'purchases:read'); return $s->download($who['issuer_id'],$who['actor_id'],IssuerPolicy::id($id));
    }

    public function write(string $secret,string $resource,$id,array $body,string $idempotency)
    {
        CompanyPurchasesValues::fields($body,['action','revision','input']);
        $action=$body['action']??null; $input=$body['input']??null;
        if (!is_string($action)||!is_array($input)||!preg_match('/^[A-Za-z0-9_.:-]{8,128}$/D',$idempotency)) { throw new DomainException('company_api_invalid_input'); }
        $scope=match($resource.':'.$action) {
            'suppliers:create','suppliers:update','requests:create','quotes:create','files:upload','files:hide'=>'purchases:write',
            'requests:approve','requests:cancel','orders:create','orders:cancel'=>'purchases:approve',
            'receipts:create'=>'purchases:receive','documents:post'=>'purchases:post',
            'payables:settle','events:reverse'=>'purchases:pay',
            'invites:issue','invites:revoke'=>'purchases:portal','settings:save'=>'purchases:settings',
            default=>throw new DomainException('company_api_invalid_input')
        };
        [$s,$who]=CompanyPurchases::forApi($this->db,$this->p,$secret,$scope); $i=$who['issuer_id']; $a=$who['actor_id'];
        $key=hash('sha256','purchase-api|'.$who['token_id'].'|'.$resource.'|'.$action.'|'.$idempotency);
        $revision=$body['revision']??null;
        if (in_array($resource.':'.$action,['suppliers:create','requests:create','quotes:create','orders:create','documents:post'],true)) {
            if ($id!==null||array_key_exists('revision',$body)) { throw new DomainException('company_api_invalid_input'); }
        } elseif ($resource!=='settings') { $id=IssuerPolicy::id($id); }
        $result=match($resource.':'.$action) {
            'suppliers:create'=>$s->saveSupplier($i,$a,null,null,$input,$key),
            'suppliers:update'=>$s->saveSupplier($i,$a,$id,CompanyPurchasesValues::integer($revision,1),$input,$key),
            'requests:create'=>$s->createRequest($i,$a,$input,$key),
            'requests:approve'=>$input===[]?$s->approveRequest($i,$a,$id,$revision,$key):throw new DomainException('company_api_invalid_input'),
            'requests:cancel'=>$input===[]?$s->cancel($i,$a,'request',$id,$revision,$key):throw new DomainException('company_api_invalid_input'),
            'quotes:create'=>$s->quote($i,$a,$input,$key),'orders:create'=>$s->createOrder($i,$a,$input,$key),
            'orders:cancel'=>$input===[]?$s->cancel($i,$a,'order',$id,$revision,$key):throw new DomainException('company_api_invalid_input'),
            'receipts:create'=>$s->receive($i,$a,$id,$revision,$input,$key),'documents:post'=>$s->postDocument($i,$a,$input,$key),
            'payables:settle'=>is_string($revision)?$s->settle($i,$a,$id,$revision,$input,$key):throw new DomainException('company_api_invalid_input'),
            'events:reverse'=>is_string($revision)?$s->reverseSettlement($i,$a,$id,$revision,$input,$key):throw new DomainException('company_api_invalid_input'),
            'files:upload'=>$this->upload($s,$i,$a,$id,$body,$key),'files:hide'=>$input===[]?$s->hideFile($i,$a,$id,$revision,$key):throw new DomainException('company_api_invalid_input'),
            'invites:issue'=>$this->invite($s,$i,$a,$id,$body,$key),'invites:revoke'=>$input===[]?$s->revokeInvite($i,$a,$id,$revision,$key):throw new DomainException('company_api_invalid_input'),
            'settings:save'=>$id===null?$s->configure($i,$a,$revision===null?null:CompanyPurchasesValues::integer($revision,1),$input,$key):throw new DomainException('company_api_invalid_input'),
        };
        return ['issuer_id'=>$i,'resource'=>$resource,'action'=>$action,'result'=>$result];
    }

    private function upload(CompanyPurchases $s,int $i,int $a,int $id,array $body,string $key): int
    {
        $input=$body['input']; CompanyPurchasesValues::fields($input,['filename','content_base64','supplier_id','portal_visible']);
        if (array_key_exists('revision',$body)||!is_string($input['filename']??null)||!is_string($input['content_base64']??null)||strlen($input['content_base64'])>2796204) { throw new DomainException('company_api_invalid_input'); }
        $bytes=base64_decode($input['content_base64'],true);
        if (!is_string($bytes)||base64_encode($bytes)!==$input['content_base64']) { throw new DomainException('company_api_invalid_input'); }
        $supplier=($input['supplier_id']??null)===null?null:IssuerPolicy::id($input['supplier_id']); $visible=CompanyPurchasesValues::integer($input['portal_visible']??0,0,1)===1;
        return $s->upload($i,$a,$id,$supplier,$input['filename'],$bytes,$visible,$key);
    }

    private function invite(CompanyPurchases $s,int $i,int $a,int $id,array $body,string $key): array
    {
        if (array_key_exists('revision',$body)) { throw new DomainException('company_api_invalid_input'); }
        CompanyPurchasesValues::fields($body['input'],['supplier_id','days']);
        return $s->issueInvite($i,$a,$id,IssuerPolicy::id($body['input']['supplier_id']??null),CompanyPurchasesValues::integer($body['input']['days']??null,1,30),$key);
    }
}
