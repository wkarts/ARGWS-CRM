<?php
declare(strict_types=1);
require_once __DIR__.'/NativePortalCustomerFiles.php';
require_once __DIR__.'/NativePortalPresentation.php';
require_once __DIR__.'/NativePortalIdentity.php';
/** Original Clients file actions, using the authenticated contact and native files identities. */
final class NativePortalCustomerFilesHttp
{
 private int$issuer;private array$binding;private NativePortalCustomerFiles$files;private string$secret;
 public function __construct(private$ci){if(!PortalRequestScope::sealedFor($ci)||strtolower($ci->router->fetch_class())!=='clients'||!in_array(strtolower($ci->router->fetch_method()),['files','upload_files','delete_file'],true))throw new DomainException('native_portal_files_denied');$this->issuer=PortalRequestScope::issuerId();$this->secret=$ci->config->item('encryption_key');$identity=(new NativePortalIdentity($ci->db,db_prefix(),$this->secret))->current($ci->session,$this->issuer);if(!$identity)throw new DomainException('native_portal_files_denied');$this->binding=$identity['binding'];$this->files=new NativePortalCustomerFiles($ci->db,db_prefix(),new CompanyPortal($ci->db,db_prefix(),$this->secret),$this->binding,$this->issuer);}
 public function index():void{if(!in_array($this->ci->input->method(true),['GET','HEAD'],true))throw new DomainException('native_portal_files_denied');$page=$this->files->page();$this->ci->data(['title'=>_l('customer_attachments'),'files'=>$page['files'],'native_portal_files'=>true,'native_file_policy'=>$page['policy'],'native_file_maximum'=>NativeCompanyFileContent::maximum(),'native_file_contact'=>$this->binding['contact_id']]);$this->ci->view('files');$this->ci->layout();}
 public function upload():void{
  PortalRequestScope::assertMutation($this->ci);$key=$this->ci->input->post('native_file_request',false);if(!is_string($key))throw new DomainException('native_portal_files_invalid');$external=$this->ci->input->post('external',false);
  if($external!==null&&$external!==false&&$external!==''){$files=$this->ci->input->post('files',false);if(!is_string($external)||!is_array($files)||array_keys($files)!==[0]||!is_array($files[0])||!is_string($files[0]['name']??null)||!is_string($files[0]['link']??null))throw new DomainException('native_portal_files_invalid');$id=$this->files->external($key,$files[0]['name'],$external,$files[0]['link']);}
  else{$file=$_FILES['file']??null;if(!is_array($file)||!is_string($file['name']??null)||!is_string($file['tmp_name']??null)||!is_int($file['error']??null)||$file['error']!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']))throw new DomainException('native_portal_files_invalid');$length=filesize($file['tmp_name']);if($length===false||$length<1||$length>NativeCompanyFileContent::maximum())throw new DomainException('native_file_content_invalid');$bytes=file_get_contents($file['tmp_name'],false,null,0,NativeCompanyFileContent::maximum()+1);if(!is_string($bytes)||strlen($bytes)!==$length)throw new DomainException('native_file_content_invalid');$id=$this->files->upload($key,$file['name'],$bytes);}
  $this->ci->output->set_header('Cache-Control: private, no-store')->set_content_type('application/json')->set_output(json_encode(['success'=>true,'file_id'=>$id,'notification'=>'recorded','mail'=>'pending'],JSON_THROW_ON_ERROR));
 }
 public function delete($id,string$type):void{
  PortalRequestScope::assertMutation($this->ci);
  if(in_array($type,['project','task'],true)){
   if(!is_string($id)||!preg_match('/^[a-f0-9]{64}$/D',$id))throw new DomainException('native_portal_file_source_required');
   $project=IssuerPolicy::id($this->ci->input->post('project_id',false));$key=$this->ci->input->post('native_file_request',false);if(!is_string($key))throw new DomainException('native_portal_files_invalid');
   if($type==='project'){require_once __DIR__.'/CompanyProjectContactFileWrite.php';(new CompanyProjectContactFileWrite($this->ci->db,db_prefix(),$this->secret))->hideNative($this->binding,$this->issuer,$project,$this->ci->input->post('project_revision',false),$this->ci->input->post('file_collection_revision',false),$id,$key);$target=NativePortalPresentation::url('clients/project/'.$project).'&group=project_files';}
   else{require_once __DIR__.'/CompanyPortalTaskWrite.php';$task=IssuerPolicy::id($this->ci->input->post('task_id',false));(new CompanyPortalTaskWrite($this->ci->db,db_prefix(),$this->secret))->hideNative($this->binding,$this->issuer,$project,$task,$this->ci->input->post('task_revision',false),$id,$key);$target=NativePortalPresentation::url('clients/project/'.$project).'&group=project_tasks&taskid='.$task;}
   set_alert('success',_l('deleted',_l('file')));redirect($target,'location',303);return;
  }
  if($type!=='general')throw new DomainException('native_portal_file_source_required');$revision=$this->ci->input->post('native_file_revision',false);if(!is_scalar($id)||!preg_match('/^[1-9][0-9]*$/D',(string)$id)||!is_string($revision))throw new DomainException('native_portal_files_invalid');$this->files->delete(IssuerPolicy::id($id),$revision);set_alert('success',_l('deleted',_l('file')));redirect(NativePortalPresentation::url('clients/files'),'location',303);
 }
}
