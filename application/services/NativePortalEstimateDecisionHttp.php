<?php

declare(strict_types=1);
require_once __DIR__.'/NativePortalIdentity.php';
require_once __DIR__.'/CompanyPortalSales.php';
require_once __DIR__.'/NativeCompanyEstimateAutoConvert.php';

/** Original estimate POST boundary backed by the contact-scoped decision writer. */
final class NativePortalEstimateDecisionHttp
{
    public function __construct(private $ci){}
    private function input(string $name):string
    {
        $value=$this->ci->input->post($name,false);if(!is_string($value))throw new DomainException('native_estimate_decision_invalid');return $value;
    }
    public function decide($id,$hash):array
    {
        if(!PortalRequestScope::sealedFor($this->ci)||strtolower($this->ci->router->fetch_class())!=='estimate'||strtolower($this->ci->router->fetch_method())!=='index'||$this->ci->input->method(true)!=='POST'||(!is_int($id)&&!is_string($id))||!preg_match('/^[1-9][0-9]*$/D',(string)$id)||!is_string($hash)||!preg_match('/^[a-f0-9]{32}$/D',$hash))throw new DomainException('company_portal_sales_unavailable');
        PortalRequestScope::assertMutation($this->ci);
        $issuer=PortalRequestScope::issuerId();$secret=$this->ci->config->item('encryption_key');$identities=new NativePortalIdentity($this->ci->db,db_prefix(),$secret);$identity=$identities->current($this->ci->session,$issuer);if(!$identity)throw new DomainException('company_portal_sales_unavailable');
        $binding=$identity['binding'];$verified=IssuerStorage::query($this->ci->db,'SELECT email_verified_at FROM '.db_prefix().'contacts WHERE id=? AND userid=?',[$binding['contact_id'],$binding['client_id']])->result_array();if(count($verified)!==1||$verified[0]['email_verified_at']===null)throw new DomainException('company_portal_sales_unavailable');
        $portal=new CompanyPortal($this->ci->db,db_prefix(),$secret);$sales=new CompanyPortalSales($this->ci->db,db_prefix(),$portal);$document=$sales->document($binding,$issuer,(int)$id);
        if($document['kind']!=='estimates'||!hash_equals($document['link_hash'],$hash))throw new DomainException('company_portal_sales_unavailable');
        $action=match($this->input('estimate_action')){'4','accepted'=>'accepted','3','declined'=>'declined',default=>throw new DomainException('native_estimate_decision_invalid')};
        $revision=$this->input('native_estimate_revision');$key=$this->input('native_request_key');if(!preg_match('/^[a-f0-9]{64}$/D',$revision)||!preg_match('/^[a-f0-9]{64}$/D',$key)||!hash_equals($document['publication_revision'],$revision))throw new DomainException('company_sales_stale');
        $signature=null;$postedSignature=$this->ci->input->post('signature',false);if($action==='accepted'&&in_array($document['state'],['accepted','converting'],true)&&is_array($document['signature']??null)&&$postedSignature===null){$stored=$document['signature'];$signature=['image'=>$stored['image'],'firstname'=>$stored['firstname'],'lastname'=>$stored['lastname'],'email'=>$stored['email'],'title'=>$stored['title'],'ip'=>$stored['ip']];}elseif($action==='accepted'&&$document['state']==='open'&&($document['signature_required']||$postedSignature!==null)){$ip=$this->ci->input->ip_address();if(!is_string($ip)||filter_var($ip,FILTER_VALIDATE_IP)===false)throw new DomainException('native_estimate_signature_invalid');$title=$this->ci->input->post('acceptance_title',false);if($title!==null&&!is_string($title))throw new DomainException('native_estimate_signature_invalid');$signature=['image'=>$this->input('signature'),'firstname'=>$this->input('acceptance_firstname'),'lastname'=>$this->input('acceptance_lastname'),'email'=>$this->input('acceptance_email'),'title'=>$title??'','ip'=>$ip];}
        if($identities->current($this->ci->session,$issuer)!==$identity)throw new DomainException('company_portal_sales_unavailable');
        $sales->decide($binding,$issuer,(int)$id,$action,$revision,$key,$signature);
        $conversion=['requested'=>false,'completed'=>true];
        if($action==='accepted'){try{$conversion=(new NativeCompanyEstimateAutoConvert($this->ci->db,db_prefix(),$portal))->process($binding,$issuer,(int)$id,$key);}catch(Throwable$failure){$current=$sales->document($binding,$issuer,(int)$id);if($current['state']==='converted')$conversion=['requested'=>true,'completed'=>true];else{$retry=$sales->autoConversionRetry($binding,$issuer,(int)$id);if(!$retry||!hash_equals($retry['revision'],$revision)||!hash_equals($retry['request_key'],$key))throw$failure;$conversion=['requested'=>true,'completed'=>false];}}}
        if($identities->current($this->ci->session,$issuer)!==$identity)throw new DomainException('company_portal_sales_unavailable');
        $saved=$sales->document($binding,$issuer,(int)$id);if(!in_array($saved['state'],$action==='accepted'?['accepted','converting','converted']:['declined'],true))throw new RuntimeException('issuer_portal_write_unconfirmed');
        return ['decision'=>$action,'state'=>$saved['state'],'conversion'=>$conversion];
    }
}
