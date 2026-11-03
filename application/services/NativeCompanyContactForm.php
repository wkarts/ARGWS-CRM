<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyNativeContacts.php';
require_once __DIR__.'/CompanyNativeContactMail.php';
require_once __DIR__.'/NativeCompanyContactPreferences.php';
require_once __DIR__.'/NativeCompanyContactImage.php';
require_once __DIR__.'/NativeCompanyCustomFields.php';
/** Same native contact form, explicit owner DTO and atomic participants. */
final class NativeCompanyContactForm
{
    public function __construct(private $db,private string $p,private int $issuer,private int $actor){}
    private function fieldsRevision(array $values):string{return hash('sha256',json_encode($values,JSON_THROW_ON_ERROR));}
    public function data(int $client,?int $contact=null):array
    {
        $native=new CompanyNativeContacts($this->db,$this->p);$meta=$native->metadata($this->issuer,$this->actor,$client,$contact===null?'create':'view');
        $prefs=(new NativeCompanyContactPreferences($this->db,$this->p))->read($this->issuer,$this->actor,$client);$calling=null;
        if($prefs['calling']&&(int)$meta['country']>0){$country=IssuerStorage::query($this->db,'SELECT calling_code FROM '.$this->p.'countries WHERE country_id=?',[$meta['country']])->row_array();$code=(string)($country['calling_code']??'');if($code!==''&&!preg_match('/^[0-9+ -]{1,20}$/D',$code))throw new DomainException('company_contact_invalid_input');if($code!=='')$calling='+'.ltrim($code,'+');}
        $data=['native_customers'=>true,'customer_id'=>$client,'contactid'=>$contact??'','native_contact_company'=>$meta['company'],'native_contact_primary_count'=>$meta['primary_count'],'native_contact_revision'=>'','native_contact_permissions'=>[],'native_contact_defaults'=>$prefs['permissions'],'calling_code'=>$calling];
        foreach(['new-client-created'=>'native_contact_welcome_active','contact-set-password'=>'native_contact_setup_active']as$slug=>$key){$template=CompanyEmailTemplates::findForIssuer($this->db,$this->p,$this->issuer,$slug,'portuguese_br');$data[$key]=$template&&(int)$template->active===1;}
        if($contact!==null){$dto=$native->get($this->issuer,$this->actor,$client,$contact);$data['contact']=(object)$dto['contact'];$data['native_contact_revision']=$dto['revision'];$data['native_contact_permissions']=$dto['permissions'];}
        $fields=new NativeCompanyCustomFields($this->db,$this->p);$values=$contact===null?[]:$fields->storedInput($this->issuer,$this->actor,'contacts',$contact);$data['native_contact_fields_revision']=$this->fieldsRevision($values);return$data;
    }
    public function save(int $client,?int $contact,array $input,string $secret,string $base,callable $decrypt,callable $hashPassword,?string $imageBytes=null):array
    {
        $native=new CompanyNativeContacts($this->db,$this->p);$meta=$native->metadata($this->issuer,$this->actor,$client,$contact===null?'create':'edit');
        $revision=$input['native_contact_revision']??'';$fieldRevision=$input['native_contact_fields_revision']??'';
        if(!is_string($revision)||!is_string($fieldRevision)||!preg_match('/^[a-f0-9]{64}$/D',$fieldRevision)||($contact!==null&&!preg_match('/^[a-f0-9]{64}$/D',$revision)))throw new DomainException('company_client_stale');
        if(isset($input['contactid'])&&(string)$input['contactid']!==(string)($contact??''))throw new DomainException('company_client_access_denied');
        if(isset($input['native_customer_id'])&&(string)$input['native_customer_id']!==(string)$client)throw new DomainException('company_client_access_denied');
        foreach(['fakeusernameremembered','fakepasswordremembered']as$field)if(isset($input[$field])&&$input[$field]!=='')throw new DomainException('company_contact_invalid_input');
        $welcome=empty($input['donotsendwelcomeemail']);$setup=!empty($input['send_set_password_email']);
        foreach(['donotsendwelcomeemail','send_set_password_email']as$field)if(isset($input[$field])&&!in_array($input[$field],['on','1',1],true))throw new DomainException('company_contact_invalid_input');
        $submitted=$input['custom_fields']??[];if(!is_array($submitted)||array_diff(array_keys($submitted),['contacts'])||!is_array($submitted['contacts']??[]))throw new DomainException('native_custom_field_invalid');$submitted=$submitted['contacts']??[];
        unset($input['native_contact_revision'],$input['native_contact_fields_revision'],$input['contactid'],$input['native_customer_id'],$input['fakeusernameremembered'],$input['fakepasswordremembered'],$input['donotsendwelcomeemail'],$input['send_set_password_email'],$input['custom_fields']);
        if($contact===null&&($input['password']??'')===''&&!$setup)throw new DomainException('company_contact_password_required');
        if($contact!==null){$old=$native->get($this->issuer,$this->actor,$client,$contact);if((int)$old['contact']['is_primary']===1&&!array_key_exists('is_primary',$input))$input['is_primary']=1;}
        $custom=new NativeCompanyCustomFields($this->db,$this->p);if($contact===null)$custom->validateValuesForContactCreation($this->issuer,$this->actor,$client,$submitted);else $custom->validateValuesForResource($this->issuer,$this->actor,'contacts',$contact,$submitted,'edit');
        $image=$imageBytes===null?null:NativeCompanyContactImage::normalize($imageBytes);
        $mail=new CompanyNativeContactMail($this->db,$this->p,$secret);$slugs=[];if($contact===null&&$welcome)$slugs[]='new-client-created';if($setup)$slugs[]='contact-set-password';
        $tables=$custom->tableNames();if($slugs)$tables=array_merge($tables,$mail->tables());if($image!==null)$tables=array_merge($tables,NativeCompanyContactImage::tables());
        $id=$native->save($this->issuer,$this->actor,$client,$input,$hashPassword,$contact,$revision,function($id,$cap,$writer)use($custom,$submitted,$fieldRevision,$contact,$image,$mail,$slugs,$client,$base,$decrypt):void{
            $oldFields=$contact===null?[]:$custom->storedInput($this->issuer,$this->actor,'contacts',$id,'edit');if(!hash_equals($fieldRevision,$this->fieldsRevision($oldFields)))throw new DomainException('company_client_stale');
            if($cap==='create')$custom->saveCreatedInOwnedTransaction($this->issuer,$this->actor,'contacts',$id,$submitted,$writer);else $custom->saveInOwnedTransaction($this->issuer,$this->actor,'contacts',$id,$submitted);
            if($image!==null)(new NativeCompanyContactImage($this->db,$this->p))->writeInOwned($this->issuer,$this->actor,$client,$id,$image,$cap);
            if($slugs)$mail->queueInOwned($this->issuer,$this->actor,$client,$id,$slugs,$base,$decrypt,$cap);
        },$tables);
        $meta=$native->metadata($this->issuer,$this->actor,$client,$contact===null?'create':'edit');
        return['success'=>true,'id'=>$id,'has_primary_contact'=>$meta['primary_count']>0,'is_individual'=>$meta['company']===''&&$meta['total']===1,'proposal_warning'=>false];
    }
}
