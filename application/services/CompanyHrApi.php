<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyHr.php';

/** Finite native HR API. Bearer authentication supplies company and actor. */
final class CompanyHrApi
{
    public const SCOPES=[
        'hr:read'=>[['hr','view']],
        'hr:write'=>[['hr','view'],['hr','create'],['hr','edit']],
        'hr:configure'=>[['hr','view'],['hr','configure']],
        'hr:sensitive:read'=>[['hr','view'],['hr_sensitive','view']],
        'hr:sensitive:write'=>[['hr','view'],['hr_sensitive','view'],['hr_sensitive','edit']],
        'hr:sensitive:approve'=>[['hr','view'],['hr_sensitive','view'],['hr_sensitive','approve']],
        'hr:contracts:read'=>[['hr','view'],['hr_contracts','view'],['hr_sensitive','view']],
        'hr:contracts:write'=>[['hr','view'],['hr_contracts','view'],['hr_contracts','create'],['hr_contracts','edit'],['hr_sensitive','view']],
        'hr:training:read'=>[['hr','view'],['hr_training','view']],
        'hr:training:write'=>[['hr','view'],['hr_training','view'],['hr_training','configure']],
        'hr:training:assign'=>[['hr','view'],['hr_training','assign']],
        'hr:files:read'=>[['hr','view'],['hr_files','view']],
        'hr:files:write'=>[['hr','view'],['hr_files','view'],['hr_files','create'],['hr_files','delete']],
        'hr:access:manage'=>[['hr','view'],['hr_portal','manage']],
        'hr:import'=>[['hr','view'],['hr','import'],['hr','create']],
        'hr:reports'=>[['hr','view'],['hr_reports','view'],['hr','export']],
    ];
    public const ENDPOINTS=['hr_structure','hr_employees','hr_profiles','hr_records','hr_contracts','hr_courses','hr_assignments','hr_accesses','hr_files','hr_reports','hr_imports','hr_articles','hr_documents'];
    public function __construct(private $db,private string $p){}
    private static function fields(array $input,array $allowed):void{if(array_diff(array_keys($input),$allowed))throw new DomainException('company_api_invalid_input');}
    private static function text(array $input,string $field):string{if(!is_string($input[$field]??null))throw new DomainException('company_api_invalid_input');return $input[$field];}
    private static function bytes(array $input,int $limit):string
    {
        $encoded=self::text($input,'content_base64');if(strlen($encoded)>4*(int)ceil($limit/3)||!preg_match('/^[A-Za-z0-9+\/]*={0,2}$/D',$encoded))throw new DomainException('company_api_invalid_input');$bytes=base64_decode($encoded,true);if($bytes===false||strlen($bytes)>$limit||base64_encode($bytes)!==$encoded)throw new DomainException('company_api_invalid_input');return $bytes;
    }
    private function consumer(string $secret,string $scope):array{return CompanyHr::forApi($this->db,$this->p,$secret,$scope);}
    public function read(string $secret,string $endpoint,$id=null,array $query=[]):array
    {
        if(!in_array($endpoint,self::ENDPOINTS,true)||$endpoint==='hr_imports')throw new DomainException('company_api_invalid_input');
        $fields=match($endpoint){'hr_employees'=>['after'],'hr_records','hr_contracts','hr_assignments','hr_accesses'=>['employee_id'],'hr_files'=>['purpose','object_id'],'hr_reports'=>['name'],default=>[]};self::fields($query,$fields);
        if(in_array($endpoint,['hr_structure','hr_records','hr_courses','hr_assignments','hr_accesses','hr_reports','hr_articles'],true)&&$id!==null)throw new DomainException('company_api_invalid_input');
        $scope=match($endpoint){'hr_profiles','hr_records'=>'hr:sensitive:read','hr_contracts','hr_documents'=>'hr:contracts:read','hr_courses','hr_assignments'=>'hr:training:read','hr_accesses'=>'hr:access:manage','hr_files'=>'hr:files:read','hr_reports'=>'hr:reports',default=>'hr:read'};[$s,$who]=$this->consumer($secret,$scope);$i=$who['issuer_id'];$a=$who['actor_id'];
        $data=match($endpoint){
            'hr_structure'=>$s->structureSnapshot($i,$a),
            'hr_employees'=>$id===null?$s->employees($i,$a,$query['after']??0):['record'=>$s->getEmployee($i,$a,IssuerPolicy::id($id))],
            'hr_profiles'=>['record'=>$s->getProfile($i,$a,IssuerPolicy::id($id))],
            'hr_records'=>['items'=>$s->records($i,$a,IssuerPolicy::id($query['employee_id']??null))],
            'hr_contracts'=>$id===null?['items'=>$s->contracts($i,$a,IssuerPolicy::id($query['employee_id']??null))]:['record'=>$s->getContract($i,$a,IssuerPolicy::id($id))],
            'hr_courses'=>['items'=>$s->courses($i,$a)],
            'hr_assignments'=>['items'=>$s->assignments($i,$a,IssuerPolicy::id($query['employee_id']??null))],
            'hr_accesses'=>['items'=>$s->accesses($i,$a,IssuerPolicy::id($query['employee_id']??null))],
            'hr_files'=>$id===null?['items'=>$s->files($i,$a,self::text($query,'purpose'),IssuerPolicy::id($query['object_id']??null))]:throw new DomainException('company_api_invalid_input'),
            'hr_reports'=>$s->report($i,$a,self::text($query,'name'),true),
            'hr_articles'=>['items'=>$s->articles($i,$a)],
            'hr_documents'=>['document'=>$s->contractDocument($i,$a,IssuerPolicy::id($id))],
        };return ['issuer_id'=>$i,'resource'=>$endpoint]+$data;
    }
    public function file(string $secret,string $purpose,$object,$file):array{[$s,$w]=$this->consumer($secret,'hr:files:read');return $s->getFile($w['issuer_id'],$w['actor_id'],$purpose,IssuerPolicy::id($object),IssuerPolicy::id($file));}
    public function export(string $secret,string $name):string{[$s,$w]=$this->consumer($secret,'hr:reports');return $s->reportCsv($w['issuer_id'],$w['actor_id'],$name);}
    public function write(string $secret,string $endpoint,$id,array $body,string $idempotency):array
    {
        if(!in_array($endpoint,self::ENDPOINTS,true)||!preg_match('/^[a-f0-9]{64}$/D',$idempotency))throw new DomainException('company_api_invalid_input');self::fields($body,['action','revision','input']);$action=self::text($body,'action');$input=$body['input']??[];if(!is_array($input))throw new DomainException('company_api_invalid_input');
        $allowed=match($endpoint){'hr_structure','hr_employees','hr_courses','hr_articles'=>['create','update'],'hr_profiles'=>['update'],'hr_records'=>['create','update','approve','reject','void'],'hr_contracts'=>['create','update','activate','cancel'],'hr_assignments'=>['assign','cancel'],'hr_accesses'=>['issue','revoke'],'hr_files'=>['upload','hide'],'hr_imports'=>['review','commit'],default=>[]};if(!in_array($action,$allowed,true))throw new DomainException('company_api_invalid_input');
        $scope=match($endpoint){'hr_structure','hr_articles'=>'hr:configure','hr_employees'=>'hr:write','hr_profiles'=>'hr:sensitive:write','hr_records'=>in_array($action,['approve','reject'],true)?'hr:sensitive:approve':'hr:sensitive:write','hr_contracts'=>'hr:contracts:write','hr_courses'=>'hr:training:write','hr_assignments'=>'hr:training:assign','hr_accesses'=>'hr:access:manage','hr_files'=>'hr:files:write','hr_imports'=>'hr:import'};
        [$s,$w]=$this->consumer($secret,$scope);$i=$w['issuer_id'];$a=$w['actor_id'];$key=hash('sha256','hr-api|'.$i.'|'.$a.'|'.$endpoint.'|'.$action.'|'.$idempotency);
        $creates=in_array($action,['create','assign','issue','review','commit'],true);if($creates){if($id!==null||array_key_exists('revision',$body))throw new DomainException('company_api_invalid_input');$revision=null;}else{$id=IssuerPolicy::id($id);$revision=IssuerPolicy::id($body['revision']??null);}
        if($endpoint==='hr_structure'){
            $kind=self::text($input,'kind');$data=$input;unset($data['kind']);$value=$s->saveStructure($i,$a,$kind,$id,$revision,$data,$key);
        }elseif($endpoint==='hr_employees')$value=$s->saveEmployee($i,$a,$id,$revision,$input,$key);
        elseif($endpoint==='hr_profiles')$value=$s->saveProfile($i,$a,$id,$revision,$input,$key);
        elseif($endpoint==='hr_records'){
            if(in_array($action,['create','update'],true)){self::fields($input,['employee_id','kind','data']);if(!is_array($input['data']??null))throw new DomainException('company_api_invalid_input');$value=$s->saveRecord($i,$a,IssuerPolicy::id($input['employee_id']??null),self::text($input,'kind'),$id,$revision,$input['data'],$key);}
            else{if($input!==[])throw new DomainException('company_api_invalid_input');$value=$s->recordState($i,$a,$id,$revision,match($action){'approve'=>'approved','reject'=>'rejected','void'=>'void'},$key);}
        }elseif($endpoint==='hr_contracts'){
            if(in_array($action,['create','update'],true))$value=$s->saveContract($i,$a,$id,$revision,$input,$key);else{if($input!==[])throw new DomainException('company_api_invalid_input');$value=$s->contractState($i,$a,$id,$revision,$action==='activate'?'active':'cancelled',$key);}
        }elseif($endpoint==='hr_courses')$value=$s->saveCourse($i,$a,$id,$revision,$input,$key);
        elseif($endpoint==='hr_assignments'){
            if($action==='assign'){self::fields($input,['employee_id','course_id','due_local','timezone','notify_opt_in']);if(!in_array($input['notify_opt_in']??0,[0,1],true))throw new DomainException('company_api_invalid_input');$value=$s->assignCourse($i,$a,IssuerPolicy::id($input['employee_id']??null),IssuerPolicy::id($input['course_id']??null),self::text($input,'due_local'),self::text($input,'timezone'),($input['notify_opt_in']??0)===1,$key);}
            else{if($input!==[])throw new DomainException('company_api_invalid_input');$value=$s->cancelAssignment($i,$a,$id,$revision,$key);}
        }elseif($endpoint==='hr_accesses'){
            if($action==='issue'){self::fields($input,['purpose','object_id','hours']);if(!is_int($input['hours']??null))throw new DomainException('company_api_invalid_input');$value=$s->createAccess($i,$a,self::text($input,'purpose'),IssuerPolicy::id($input['object_id']??null),$input['hours'],$key);}
            else{if($input!==[])throw new DomainException('company_api_invalid_input');$value=$s->revokeAccess($i,$a,$id,$revision,$key);}
        }elseif($endpoint==='hr_files'){
            if($action==='upload'){self::fields($input,['purpose','filename','content_base64']);$value=$s->addFile($i,$a,self::text($input,'purpose'),$id,$revision,self::text($input,'filename'),self::bytes($input,2097152),$key);}
            else{self::fields($input,['purpose','object_id']);$value=$s->hideFile($i,$a,self::text($input,'purpose'),IssuerPolicy::id($input['object_id']??null),$id,$revision,$key);}
        }elseif($endpoint==='hr_articles')$value=$s->saveArticle($i,$a,$id,$revision,$input,$key);
        else{self::fields($input,$action==='review'?['content_base64']:['content_base64','review_hash']);$csv=self::bytes($input,262144);$value=$action==='review'?$s->reviewImport($i,$a,$csv):$s->importEmployees($i,$a,$csv,self::text($input,'review_hash'),$key);}
        return ['issuer_id'=>$i,'resource'=>$endpoint,'result'=>$value];
    }
}
