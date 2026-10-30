<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyLeads.php';

/** Explicit endpoints and capabilities. Tokens derive issuer/actor; JSON cannot override either. */
final class CompanyLeadsApi
{
    public const SCOPES=[
        'leads:read'=>[['leads','view']],
        'leads:write'=>[['leads','view'],['leads','create'],['leads','edit']],
        'leads:archive'=>[['leads','view'],['leads','delete']],
        'leads:convert'=>[['leads','view'],['leads','edit'],['customers','view'],['customers','create']],
        'leads:configure'=>[['leads','view'],['leads','edit']],
        'leads:files:read'=>[['leads','view']],
        'leads:files:write'=>[['leads','view'],['leads','edit']],
        'leads:import'=>[['leads','view'],['leads','create']],
        'leads:notices'=>[['leads','view']],
    ];
    public const ENDPOINTS=['leads','lead_stages','lead_sources','lead_assignees','lead_files','lead_imports','lead_inbox','lead_exports'];
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');}
    private function consumer(string $secret,string $scope):array{return CompanyLeads::forApi($this->db,$this->p,$secret,$scope);}
    private static function fields(array $input,array $allowed):void{if(array_diff(array_keys($input),$allowed))throw new DomainException('company_api_invalid_input');}
    public function read(string $secret,string $endpoint,$id=null,$after=0,string $search=''):array
    {
        if(!in_array($endpoint,self::ENDPOINTS,true)||$endpoint==='lead_exports')throw new DomainException('company_api_invalid_input');
        if($endpoint!=='leads'&&$search!=='')throw new DomainException('company_api_invalid_input');
        $scope=match($endpoint){'lead_files'=>'leads:files:read','lead_imports'=>'leads:import','lead_inbox'=>'leads:notices',default=>'leads:read'};[$s,$who]=$this->consumer($secret,$scope);$i=$who['issuer_id'];$a=$who['actor_id'];
        if(in_array($endpoint,['lead_stages','lead_sources','lead_assignees','lead_inbox'],true)&&$id!==null)throw new DomainException('company_api_invalid_input');
        if($endpoint==='leads')$data=$id===null?$s->page($i,$a,$after,50,$search):['record'=>$s->get($i,$a,$id)];
        elseif($endpoint==='lead_stages'||$endpoint==='lead_sources')$data=['items'=>$s->configuration($i,$a)[$endpoint==='lead_stages'?'stages':'sources']];
        elseif($endpoint==='lead_assignees')$data=['items'=>$s->staffOptions($i,$a)];
        elseif($endpoint==='lead_files')$data=['items'=>$s->get($i,$a,IssuerPolicy::id($id))['files']];
        elseif($endpoint==='lead_imports'){if(!is_string($id))throw new DomainException('company_api_invalid_input');$data=$s->importBatch($i,$a,$id);}
        else $data=$s->inbox($i,$a,$after);
        return ['issuer_id'=>$i,'resource'=>$endpoint]+$data;
    }
    public function file(string $secret,$lead,string $file):array{[$s,$who]=$this->consumer($secret,'leads:files:read');return $s->file($who['issuer_id'],$who['actor_id'],$lead,$file);}
    public function export(string $secret,string $search=''):string{[$s,$who]=$this->consumer($secret,'leads:read');return $s->exportCsv($who['issuer_id'],$who['actor_id'],$search);}
    private static function bytes(array $input,int $limit):string
    {
        $encoded=$input['content_base64']??null;if(!is_string($encoded)||strlen($encoded)>4*(int)ceil($limit/3)||!preg_match('/^[A-Za-z0-9+\/]*={0,2}$/D',$encoded))throw new DomainException('company_api_invalid_input');$bytes=base64_decode($encoded,true);if($bytes===false||base64_encode($bytes)!==$encoded||strlen($bytes)>$limit)throw new DomainException('company_api_invalid_input');return $bytes;
    }
    public function write(string $secret,string $endpoint,$id,array $body,string $idempotency):array
    {
        if(!in_array($endpoint,self::ENDPOINTS,true)||!preg_match('/^[a-f0-9]{64}$/D',$idempotency)||array_diff(array_keys($body),['action','revision','input'])||!is_string($body['action']??null)||!is_array($body['input']??[]))throw new DomainException('company_api_invalid_input');$action=$body['action'];$input=$body['input']??[];
        $scope=match($endpoint){'leads'=>match($action){'create','update','move','note'=>'leads:write','archive','restore'=>'leads:archive','convert'=>'leads:convert',default=>throw new DomainException('company_api_invalid_input')},'lead_stages','lead_sources'=>'leads:configure','lead_files'=>'leads:files:write','lead_imports'=>'leads:import','lead_inbox'=>'leads:notices',default=>throw new DomainException('company_api_invalid_input')};
        [$s,$who]=$this->consumer($secret,$scope);$i=$who['issuer_id'];$a=$who['actor_id'];$key=hash('sha256','leads-api|'.$i.'|'.$a.'|'.$endpoint.'|'.$action.'|'.$idempotency);
        if($endpoint==='lead_imports'){
            if(array_key_exists('revision',$body))throw new DomainException('company_api_invalid_input');
            if($action==='preview'){if($id!==null)throw new DomainException('company_api_invalid_input');self::fields($input,['filename','content_base64','stage_id','source_id']);if(!is_string($input['filename']??null))throw new DomainException('company_api_invalid_input');$value=$s->previewImport($i,$a,$input['filename'],self::bytes($input,262144),$input['stage_id']??null,$input['source_id']??null,$key);}
            elseif($action==='commit'){self::fields($input,['preview_hash']);if(!is_string($id)||!is_string($input['preview_hash']??null))throw new DomainException('company_api_invalid_input');$value=$s->commitImport($i,$a,$id,$input['preview_hash'],$key);}
            else throw new DomainException('company_api_invalid_input');
        }elseif($endpoint==='lead_inbox'){
            if($action!=='acknowledge'||$input!==[]||array_key_exists('revision',$body))throw new DomainException('company_api_invalid_input');$value=$s->acknowledge($i,$a,IssuerPolicy::id($id),$key);
        }else{
            if($action==='create'){if($id!==null||array_key_exists('revision',$body))throw new DomainException('company_api_invalid_input');$revision=null;}
            else{$id=IssuerPolicy::id($id);$revision=IssuerPolicy::id($body['revision']??null);}
            if($endpoint==='lead_stages'||$endpoint==='lead_sources'){
                if(!in_array($action,['create','update'],true))throw new DomainException('company_api_invalid_input');$value=$s->configure($i,$a,$endpoint==='lead_stages'?'stage':'source',$id,$revision,$input,$key);
            }elseif($endpoint==='lead_files'){
                if($action==='upload'){self::fields($input,['filename','content_base64']);if(!is_string($input['filename']??null))throw new DomainException('company_api_invalid_input');$value=$s->addFile($i,$a,$id,$revision,$input['filename'],self::bytes($input,2097152),$key);}
                elseif($action==='hide'){self::fields($input,['file_key']);if(!is_string($input['file_key']??null))throw new DomainException('company_api_invalid_input');$value=$s->hideFile($i,$a,$id,$revision,$input['file_key'],$key);}else throw new DomainException('company_api_invalid_input');
            }elseif($action==='create')$value=$s->create($i,$a,$input,$key);
            elseif($action==='update')$value=$s->update($i,$a,$id,$revision,$input,$key);
            elseif($action==='move'){self::fields($input,['stage_id']);$value=$s->move($i,$a,$id,$revision,$input['stage_id']??null,$key);}
            elseif($action==='note'){self::fields($input,['body']);if(!is_string($input['body']??null))throw new DomainException('company_api_invalid_input');$value=$s->note($i,$a,$id,$revision,$input['body'],$key);}
            else{if($input!==[])throw new DomainException('company_api_invalid_input');$value=$action==='convert'?$s->convert($i,$a,$id,$revision,$key):$s->archive($i,$a,$id,$revision,$action==='archive',$key);}
        }
        return ['issuer_id'=>$i,'resource'=>$endpoint,'result'=>$value];
    }
}
