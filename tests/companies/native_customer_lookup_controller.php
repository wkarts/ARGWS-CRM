<?php
/** Original customer lookup and duplicate checks with no external provider requests. */
require __DIR__.'/native_clients_controller.php';
class NativeLookupController extends Clients {
    public int $calls=0;
    protected function queryRegistry(string $kind,string $value):array{$this->calls++;return ['provider'=>'synthetic','fields'=>['legal_name'=>'Consulta sintética','company_address_number'=>'42']];}
}
$lookup=new NativeLookupController();$lookup->db=$db;$lookup->input=new NativeClientInput();$lookup->security=new class{function get_csrf_token_name(){return 'csrf';}function get_csrf_hash(){return 'rotated-token';}};
$lookup->session=new class{public array $data=[];function userdata($key){return $this->data[$key]??null;}function set_userdata($key,$value){$this->data[$key]=$value;}};
$lookup->output=new class{public int $status=200;public string $body='';function set_status_header($v){$this->status=$v;return $this;}function set_header($v){return $this;}function set_content_type($v){return $this;}function set_output($v){$this->body=$v;return $this;}};
$lookup->input->method='POST';$lookup->input->post=['kind'=>'cnpj','value'=>'11222333000181'];CompanyRequestScope::$issuer=41;
$lookup->lookup();essentialCheck($lookup->output->status===403&&$lookup->calls===0,'lookup requires current native CSRF before provider access');
$lookup->input->post['_native_company_csrf']='synthetic-native-csrf';$lookup->output->status=200;$lookup->lookup();$response=json_decode($lookup->output->body,true,512,JSON_THROW_ON_ERROR);
essentialCheck($response['fields']['company']==='Consulta sintética'&&$response['fields']['address_number']==='42'&&$lookup->calls===1,'owned original form receives mapped provider fields');
$lookup->output->status=200;$lookup->lookup();essentialCheck($lookup->output->status===429&&$lookup->calls===1,'same session lookup rate limit prevents duplicate requests');
$lookup->session->data=[];CompanyRequestScope::$issuer=82;$lookup->input->post['client_id']=(string)$nativeA;$lookup->output->status=200;$lookup->lookup();
essentialCheck($lookup->output->status===403&&$lookup->calls===1,'other issuer cannot inspect or fill owner customer through lookup');
CompanyRequestScope::$issuer=41;$lookup->input->post=['company'=>'Native A edited','_native_company_csrf'=>'synthetic-native-csrf'];$lookup->output->status=200;$lookup->check_duplicate_customer_name();
essentialCheck(json_decode($lookup->output->body,true,512,JSON_THROW_ON_ERROR)['exists']===true,'duplicate name checks owner-visible customer under native create grant');
CompanyRequestScope::$issuer=82;$lookup->input->post['company']='Private synthetic A';$lookup->check_duplicate_customer_name();
essentialCheck(json_decode($lookup->output->body,true,512,JSON_THROW_ON_ERROR)['exists']===false,'duplicate name does not disclose unshared customer in another company');
echo "Native customer lookup and duplicate-name controller isolation passed; provider is a fixture.\n";
