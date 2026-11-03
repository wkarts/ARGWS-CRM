<?php
/** Real pre-hydration scope and original Download controller over owned files. */
require __DIR__.'/native_customer_files.php';
require_once APPPATH.'services/CompanyRequestScope.php';
#[AllowDynamicProperties] class App_Controller {}
class NativeDownloadNotFound extends RuntimeException {}
function show_404(){throw new NativeDownloadNotFound('not found',404);}
function show_error($message,$status=500){throw new CompanyRequestDenied('scope denied',$status);}
require APPPATH.'controllers/Download.php';
class NativeDownloadSession {
    public array $data=['staff_logged_in'=>true,'staff_user_id'=>12,'company_context_selection'=>['staff_id'=>12,'issuer_id'=>41]];
    function userdata($key){return $this->data[$key]??null;}
    function set_userdata($key,$value=null){if(is_array($key))$this->data=array_replace($this->data,$key);else $this->data[$key]=$value;}
    function unset_userdata($key){unset($this->data[$key]);}
}
class NativeDownloadInput {
    public string $verb='GET';public array $headers=[];
    function is_cli_request(){return false;}
    function method($uppercase=false){return $uppercase?$this->verb:strtolower($this->verb);}
    function post($name=null,$filter=false){return null;}
    function get($name,$filter=false){return null;}
    function get_request_header($name,$filter=false){return $this->headers[$name]??null;}
}
$download=(new ReflectionClass(Download::class))->newInstanceWithoutConstructor();$download->db=$db;$download->session=new NativeDownloadSession();$download->input=new NativeDownloadInput();
$download->router=new class{function fetch_directory(){return '';}function fetch_module(){return '';}function fetch_class(){return 'download';}function fetch_method(){return 'file';}};
$download->uri=new class{function segment($n){return [1=>'download',2=>'file',3=>'client'][$n]??null;}};
$download->output=new class{public string $body='';public array $headers=[];function set_header($value){$this->headers[]=$value;return $this;}function set_content_type($value){$this->headers[]='Content-Type: '.$value;return $this;}function set_output($value){$this->body=$value;return $this;}};
$GLOBALS['CI']=$download;CompanyRequestScope::beforeHydration($download);
essentialCheck(CompanyRequestScope::nativeFor($download)&&CompanyRequestScope::context()->issuerId()===41,'real pre-hydration scope seals original public controller to selected company');
$download->file('client',$portalDto['attachment_key']);
essentialCheck($download->output->body===$bytes&&in_array('Cache-Control: private, no-store',$download->output->headers,true),'original URL returns owner bytes with no-store');
CompanyRequestScope::finish();$download->input->verb='HEAD';$download->output->body='';CompanyRequestScope::beforeHydration($download);$download->file('client',$portalDto['attachment_key']);
essentialCheck($download->output->body==='','HEAD never returns protected bytes');
CompanyRequestScope::finish();$download->input->verb='GET';$download->session->data['company_context_selection']=['staff_id'=>12,'issuer_id'=>82];CompanyRequestScope::beforeHydration($download);
try{$download->file('client',$portalDto['attachment_key']);throw new RuntimeException('cross-company download accepted');}catch(NativeDownloadNotFound$e){essentialCheck($e->getCode()===404,'foreign company cannot download owned file');}
CompanyRequestScope::finish();
echo "Original customer download scope and controller passed against owned SQLite data.\n";
