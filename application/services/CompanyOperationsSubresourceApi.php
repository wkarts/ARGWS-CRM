<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyApiTokens.php';require_once __DIR__.'/CompanyProjectCopies.php';require_once __DIR__.'/CompanyProjectExtras.php';require_once __DIR__.'/CompanyOperationFiles.php';
/** Explicit native subresource adapters. Each operation retains its canonical reader/writer transaction. */
final class CompanyOperationsSubresourceApi
{
 public const FILES=[
  'project_files'=>['class'=>CompanyProjectExtras::class,'kind'=>'project','scope'=>'projects:files'],
  'task_files'=>['class'=>CompanyProjectExtras::class,'kind'=>'task','scope'=>'tasks:files'],
  'contract_files'=>['class'=>CompanyOperationFiles::class,'kind'=>'contract','scope'=>'contracts:files'],
  'ticket_files'=>['class'=>CompanyOperationFiles::class,'kind'=>'ticket','scope'=>'tickets:files'],
 ];
 public const EXTRAS=[
  'project_copies'=>['class'=>CompanyProjectCopies::class,'kind'=>'project','part'=>'copy','scope'=>'projects:copies'],
  'project_milestones'=>['kind'=>'project','part'=>'milestones','scope'=>'projects:milestones'],
  'task_dependencies'=>['kind'=>'task','part'=>'dependencies','scope'=>'tasks:dependencies'],
  'project_follow'=>['kind'=>'project','part'=>'following','scope'=>'projects:follow'],
  'task_follow'=>['kind'=>'task','part'=>'following','scope'=>'tasks:follow'],
 ];
 public const MAX_ENCODED_BYTES=2796204;
 private CompanyApiTokens $tokens;
 public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');CompanyApiSchema::assertValid($db,$p);$this->tokens=new CompanyApiTokens($db,$p);}
 private static function spec(string $endpoint):array{return self::FILES[$endpoint]??self::EXTRAS[$endpoint]??throw new DomainException('company_api_invalid_input');}
 private static function fields(array $input,array $allowed):void{if(array_diff(array_keys($input),$allowed))throw new DomainException('company_api_invalid_input');}
 private function consumer(string $secret,string $endpoint,string $mode,string $action,?string $key=null):array
 {
  $spec=self::spec($endpoint);$scope=$spec['scope'].':'.$mode;$who=$this->tokens->resolve($secret,$scope);$issuer=$who['issuer_id'];$actor=$who['actor_id'];$audited=false;
  $guard=function()use($secret,$scope,$issuer,$actor,$endpoint,$action,$key,&$audited):void{(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->tokens->guard($secret,$scope,$issuer,$actor);if(!$audited){$this->tokens->usedInOwnedTransaction($secret,$scope,$issuer,$actor,'subresource_'.$action,$key,['endpoint'=>$endpoint]);$audited=true;}};
  $class=$spec['class']??CompanyProjectExtras::class;return [$who,new $class($this->db,$this->p,$guard),$spec];
 }
 public function read(string $secret,string $endpoint,$id):array
 {
  $id=IssuerPolicy::id($id);[$who,$reader,$spec]=$this->consumer($secret,$endpoint,'read','list');$issuer=$who['issuer_id'];$actor=$who['actor_id'];
  if($reader instanceof CompanyProjectCopies)$value=$reader->preview($issuer,$actor,$id);else if(isset(self::FILES[$endpoint]))$value=$reader instanceof CompanyProjectExtras?$reader->files($issuer,$actor,$spec['kind'],$id):$reader->catalog($issuer,$actor,$spec['kind'],$id);else $value=$reader->state($issuer,$actor,$spec['kind'],$id,$spec['part']);
  return ['issuer_id'=>$issuer,'endpoint'=>$endpoint,'resource_id'=>$id]+$value;
 }
 public function file(string $secret,string $endpoint,$id,string $file):array
 {
  $id=IssuerPolicy::id($id);if(!isset(self::FILES[$endpoint])||!preg_match('/^[a-f0-9]{64}$/D',$file))throw new DomainException('company_api_invalid_input');[$who,$reader,$spec]=$this->consumer($secret,$endpoint,'read','download');$value=$reader->file($who['issuer_id'],$who['actor_id'],$spec['kind'],$id,$file);return ['issuer_id'=>$who['issuer_id'],'endpoint'=>$endpoint,'resource_id'=>$id]+$value;
 }
 private function copyProject(string $secret,$source,array $body,string $idempotency):array
 {
  $source=IssuerPolicy::id($source);if(!preg_match('/^[a-f0-9]{64}$/D',$idempotency)||array_diff(array_keys($body),['action','input'])||count($body)!==2||($body['action']??null)!=='copy_reviewed'||!is_array($body['input']??null))throw new DomainException('company_api_invalid_input');$who=$this->tokens->resolve($secret,'projects:copies:write');$key=hash('sha256','operational-project-copy|'.$who['issuer_id'].'|'.$who['actor_id'].'|'.$source.'|'.$idempotency);[$who,$writer]=$this->consumer($secret,'project_copies','write','copy_reviewed',$key);$result=$writer->create($who['issuer_id'],$who['actor_id'],$source,$body['input'],$key);return ['issuer_id'=>$who['issuer_id'],'endpoint'=>'project_copies','resource_id'=>$source]+$result;
 }
 public function write(string $secret,string $endpoint,$id,array $body,string $idempotency):array
 {
  if($endpoint==='project_copies')return $this->copyProject($secret,$id,$body,$idempotency);
  $id=IssuerPolicy::id($id);if(!preg_match('/^[a-f0-9]{64}$/D',$idempotency)||array_diff(array_keys($body),['action','revision','input'])||!is_string($body['action']??null)||!is_array($body['input']??null))throw new DomainException('company_api_invalid_input');$revision=IssuerPolicy::id($body['revision']??null);$action=$body['action'];$input=$body['input'];$spec=self::spec($endpoint);
  $who=$this->tokens->resolve($secret,$spec['scope'].':write');$key=hash('sha256','operational-subresources|'.$who['issuer_id'].'|'.$who['actor_id'].'|'.$endpoint.'|'.$action.'|'.$idempotency);[$who,$writer,$spec]=$this->consumer($secret,$endpoint,'write',$action,$key);$issuer=$who['issuer_id'];$actor=$who['actor_id'];
  if(isset(self::FILES[$endpoint])){
   if($action==='upload'){self::fields($input,['filename','content_base64']);$encoded=$input['content_base64']??null;$name=$input['filename']??null;if(!is_string($encoded)||strlen($encoded)<12||strlen($encoded)>self::MAX_ENCODED_BYTES||preg_match('/[^A-Za-z0-9+\/=]/',$encoded)||!is_string($name)||strlen($name)>191)throw new DomainException('company_api_invalid_input');$bytes=base64_decode($encoded,true);if(!is_string($bytes)||strlen($bytes)>2097152||base64_encode($bytes)!==$encoded)throw new DomainException('company_api_invalid_input');$result=$writer->upload($issuer,$actor,$spec['kind'],$id,$revision,$key,$name,$bytes);return ['issuer_id'=>$issuer,'endpoint'=>$endpoint,'resource_id'=>$result,'file_key'=>$key];}
   if($action==='hide'){self::fields($input,['file_key']);$file=$input['file_key']??null;if(!is_string($file))throw new DomainException('company_api_invalid_input');$result=$writer->hide($issuer,$actor,$spec['kind'],$id,$revision,$file,$key);}else throw new DomainException('company_api_invalid_input');
  }elseif($endpoint==='project_milestones'&&$action==='save'){self::fields($input,['name','due_date','done','milestone_id']);$milestone=$input['milestone_id']??null;unset($input['milestone_id']);$result=$writer->milestone($issuer,$actor,$id,$revision,$key,$input,$milestone);}
  elseif($endpoint==='task_dependencies'&&$action==='set'){self::fields($input,['dependencies']);if(!is_array($input['dependencies']??null))throw new DomainException('company_api_invalid_input');$result=$writer->dependencies($issuer,$actor,$id,$revision,$input['dependencies'],$key);}
  elseif(in_array($endpoint,['project_follow','task_follow'],true)&&$action==='set'){self::fields($input,['following']);if(!is_bool($input['following']??null))throw new DomainException('company_api_invalid_input');$result=$writer->follow($issuer,$actor,$spec['kind'],$id,$revision,$input['following'],$key);}
  else throw new DomainException('company_api_invalid_input');return ['issuer_id'=>$issuer,'endpoint'=>$endpoint,'resource_id'=>$result];
 }
}
