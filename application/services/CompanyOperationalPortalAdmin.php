<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyContactOperationAccess.php';require_once __DIR__.'/CompanyOperationPublication.php';require_once __DIR__.'/CompanyContracts.php';require_once __DIR__.'/CompanyTickets.php';require_once __DIR__.'/CompanyProjects.php';
/** Shared UI glue only; all authorization and mutations remain in owned services. */
final class CompanyOperationalPortalAdmin
{
 private static function resource($controller,int $issuer,int $actor,string $kind,$id):array{$class=match($kind){'contracts'=>CompanyContracts::class,'tickets'=>CompanyTickets::class,'projects'=>CompanyProjects::class,default=>throw new DomainException('company_operation_invalid_input')};return (new $class($controller->db,db_prefix()))->get($issuer,$actor,$id);}
 public static function handle($controller,int $issuer,int $actor,string $kind,$action,$id,$revision,string $key):bool
 {
  if(!in_array($action,['portal_permission','portal_publish'],true))return false;$row=self::resource($controller,$issuer,$actor,$kind,$id);
  if($action==='portal_permission'){$contact=$controller->input->post('contact_id',false);$token=$controller->input->post('contact_revision',false);$allowed=$controller->input->post('allowed',false)??'0';if(!is_string($token)||!is_string($allowed))throw new DomainException('company_operation_invalid_input');(new CompanyContactOperationAccess($controller->db,db_prefix(),$kind))->set($issuer,$actor,$row['client_id'],$contact,$token,$allowed);return true;}
  $type=$controller->input->post('publication_type',false);$visible=$controller->input->post('visible',false)??'0';if(!is_string($type)||!in_array($visible,['0','1'],true))throw new DomainException('company_operation_invalid_input');$child=$type==='file'?$controller->input->post('file_key',false):($type==='reply'?$controller->input->post('reply_id',false):null);(new CompanyOperationPublication($controller->db,db_prefix()))->set($issuer,$actor,$kind,$id,$revision,$type,$child,$visible==='1',$key);return true;
 }
 public static function controls($controller,int $issuer,int $actor,string $kind,array $row):array
 {
  $state=(new CompanyOperationPublication($controller->db,db_prefix()))->get($issuer,$actor,$kind,$row['id']);$state['permissions']=[];$acl=new IssuerResourceAccess($controller->db,db_prefix());$state['can_contact_edit']=$acl->can($issuer,$actor,'customers','edit');if($acl->can($issuer,$actor,'customers','view')){try{$state['permissions']=(new CompanyContactOperationAccess($controller->db,db_prefix(),$kind))->page($issuer,$actor,$row['client_id']);}catch(DomainException $denied){$state['permissions']=[];}}return $state;
 }
}
