<?php
declare(strict_types=1);
require_once __DIR__.'/NativePortalContacts.php';
require_once __DIR__.'/NativePortalPresentation.php';
require_once __DIR__.'/CompanyNativeContactMail.php';
require_once __DIR__.'/NativePortalVerificationMail.php';
/** Adapter for the five existing public Contacts methods, without a parallel page or staff impersonation. */
final class NativePortalContactsHttp
{
    private int $issuer;private string $secret;private array $binding;
    public function __construct(private $ci)
    {
        if(!PortalRequestScope::sealedFor($ci)||strtolower($ci->router->fetch_class())!=='contacts'||!in_array(strtolower($ci->router->fetch_method()),['index','contact','delete','delete_profile_image','contact_email_profile_unique'],true)||!in_array($ci->input->method(true),['GET','HEAD','POST'],true))throw new DomainException('native_portal_context_required');
        $this->issuer=PortalRequestScope::issuerId();$this->secret=$ci->config->item('encryption_key');$id=(new NativePortalIdentity($ci->db,db_prefix(),$this->secret))->current($ci->session,$this->issuer);if(!$id)throw new DomainException('native_portal_contacts_denied');$this->binding=$id['binding'];$this->writer()->manager();
    }
    private function writer(?int $target=null):NativePortalContacts{return new NativePortalContacts($this->ci->db,db_prefix(),$this->secret,$this->issuer,$this->binding,$target);}
    private function value(string $key):string{$v=$this->ci->input->post($key,false);if($v===null)return'';if(!is_string($v))throw new DomainException('native_portal_contacts_invalid');return$v;}
    private function decrypt(string $v):string{$this->ci->load->library('encryption');$plain=$this->ci->encryption->decrypt($v);if(!is_string($plain))throw new RuntimeException('issuer_portal_decryption_failed');return$plain;}
    public function index():void
    {
        if($this->ci->input->method(true)==='POST')throw new DomainException('native_portal_contacts_invalid');$rows=[];$defs=[];
        foreach($this->writer()->page()as$state){$row=$state['contact'];$image=$this->writer((int)$row['id'])->avatar();$row['native_image']=$image?'data:'.$image['mime'].';base64,'.base64_encode($image['content']):NativePortalPresentation::url('assets/images/user-placeholder.jpg');$row['native_revision']=$state['revision'];$row['native_custom_values']=$state['custom_field_values'];$rows[]=$row;$defs=$state['custom_fields'];}
        if(!$rows)$defs=$this->writer()->creation()['custom_fields'];
        $this->ci->data(['native_contacts'=>true,'contacts'=>$rows,'native_contacts_fields'=>$defs,'title'=>_l('customer_contacts'),'bodyclass'=>'contacts']);$this->ci->view('contacts');$this->ci->layout();
    }
    public function contact($target=null):void
    {
        $id=$target===null||$target===''?null:IssuerPolicy::id($target);$writer=$this->writer($id);
        if($this->ci->input->method(true)==='POST'){
            PortalRequestScope::assertMutation($this->ci);$values=[];foreach(['firstname','lastname','email','phonenumber','title','direction','password','send_set_password_email','donotsendwelcomeemail']as$key){$v=$this->ci->input->post($key,false);if($v!==null)$values[$key]=$v;}
            foreach(['invoice_emails','credit_note_emails','estimate_emails','contract_emails','ticket_emails','project_emails','task_emails']as$key){$v=$this->ci->input->post($key,false);if($v!==null){if(!in_array($v,[$key,'1','on'],true))throw new DomainException('native_portal_contacts_invalid');$values[$key]=1;}}
            $callingCode=$this->callingCode($writer);if($callingCode!==null&&($values['phonenumber']??null)===$callingCode)$values['phonenumber']='';
            $custom=$this->ci->input->post('custom_fields',false);if($custom!==null&&(!is_array($custom)||array_diff(array_keys($custom),['contacts'])))throw new DomainException('native_portal_contacts_invalid');$values['custom_fields']=$custom['contacts']??[];
            $bytes=null;$upload=$_FILES['profile_image']??null;if($upload!==null&&($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){if(!is_array($upload)||($upload['error']??-1)!==UPLOAD_ERR_OK||!is_string($upload['tmp_name']??null)||!is_uploaded_file($upload['tmp_name'])||!is_int($upload['size']??null)||$upload['size']>NativeCompanyContactImage::MAX_BYTES)throw new DomainException('company_contact_image_invalid');$bytes=file_get_contents($upload['tmp_name']);if(!is_string($bytes)||strlen($bytes)!==$upload['size'])throw new DomainException('company_contact_image_invalid');}
            require_once APPPATH.'third_party/phpass.php';$hash=new PasswordHash(PHPASS_HASH_STRENGTH,PHPASS_HASH_PORTABLE);$mail=new CompanyNativeContactMail($this->ci->db,db_prefix(),$this->secret);$verification=new NativePortalVerificationMail($this->ci->db,db_prefix(),$this->secret);
            $writer->save($id===null?null:$this->value('native_contact_revision'),$values,[$hash,'HashPassword'],[$hash,'CheckPassword'],function($issuer,$contact,$source,$event)use($mail,$verification){
                $slugs=[];if($event['welcome'])$slugs[]='new-client-created';if($event['set_password'])$slugs[]='contact-set-password';$mail->queueManagedInOwned($issuer,$contact,$source,$slugs,$this->ci->config->item('base_url'),fn($v)=>$this->decrypt($v));
                if($event['created']||$event['email_changed'])$verification->queueProfileInOwned($issuer,$contact,$source,$this->ci->config->item('base_url'),fn($v)=>$this->decrypt($v));
            },array_values(array_unique(array_merge($mail->tables(),$verification->tables()))),$bytes);
            set_alert('success',_l($id===null?'added_successfully':'updated_successfully',_l('contact')));redirect(NativePortalPresentation::url('contacts'),'location',303);return;
        }
        $state=$id===null?$writer->creation():$writer->read();$this->ci->native_portal_contacts=$writer;$templates=[];foreach(['new-client-created','contact-set-password']as$slug){$t=CompanyEmailTemplates::findForIssuer($this->ci->db,db_prefix(),$this->issuer,$slug,'portuguese_br');$templates[$slug]=$t&&(int)$t->active===1;}
        $callingCode=$this->callingCode($writer);
        $data=['native_contacts'=>true,'native_contact_revision'=>$state['revision']??'','native_contact_templates'=>$templates,'customer_permissions'=>array_map(fn($n)=>['id'=>$n,'name'=>CompanyNativeContacts::PERMISSIONS[$n]],$state['permissions']),'native_contact_permissions'=>$state['permissions'],'calling_code'=>$callingCode,'title'=>_l('contact'),'bodyclass'=>'contact'];
        if($id!==null){$data['my_contact']=(object)$state['contact'];$image=$writer->avatar();$data['native_contact_image']=$image?'data:'.$image['mime'].';base64,'.base64_encode($image['content']):NativePortalPresentation::url('assets/images/user-placeholder.jpg');}
        $writer->manager();$this->ci->data($data);$this->ci->view('contact');$this->ci->layout();
    }
    private function callingCode(NativePortalContacts $writer):?string
    {
        $store=new CompanySettingsStorage($this->ci->db,db_prefix());$calling=$store->exists($this->issuer,'automatic_calling_codes')?$store->get($this->issuer,'automatic_calling_codes'):'1';if(!in_array($calling,['0','1'],true))throw new DomainException('company_contact_preferences_invalid');$callingCode=null;
        if($calling==='1'){$client=$writer->manager()['client_id'];$row=IssuerStorage::query($this->ci->db,'SELECT n.calling_code FROM '.db_prefix().'clients c JOIN '.db_prefix().'countries n ON n.country_id=c.country WHERE c.userid=?',[$client])->row_array();if($row&&!empty($row['calling_code'])){if(!preg_match('/^[0-9+ -]{1,20}$/D',(string)$row['calling_code']))throw new DomainException('company_contact_preferences_invalid');$callingCode='+'.ltrim((string)$row['calling_code'],'+');}}
        $writer->manager();return$callingCode;
    }
    public function delete($client,$id):void{PortalRequestScope::assertMutation($this->ci);$writer=$this->writer(IssuerPolicy::id($id));if(IssuerPolicy::id($client)!==$writer->manager()['client_id'])throw new DomainException('native_portal_contacts_denied');$writer->delete($this->value('native_contact_revision'));redirect(NativePortalPresentation::url('contacts'),'location',303);}
    public function removeImage($id):void{PortalRequestScope::assertMutation($this->ci);$state=$this->writer(IssuerPolicy::id($id))->removeAvatar($this->value('native_contact_revision'));$this->ci->output->set_header('Cache-Control: no-store')->set_content_type('application/json')->set_output(json_encode(['success'=>true,'revision'=>$state['revision']],JSON_THROW_ON_ERROR));}
    public function email($email,$id):bool{PortalRequestScope::assertMutation($this->ci);if(!is_string($email))throw new DomainException('native_portal_contacts_invalid');return$this->writer($id===null||$id===''?null:IssuerPolicy::id($id))->emailAvailable($email);}
}
