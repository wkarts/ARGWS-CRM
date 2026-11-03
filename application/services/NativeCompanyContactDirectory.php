<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyNativeContacts.php';
require_once __DIR__.'/NativeCompanyCustomFields.php';
require_once __DIR__.'/NativeCompanyConsents.php';
require_once __DIR__.'/CompanySettingsStorage.php';
require_once __DIR__.'/NativeCompanyClients.php';
/** Native all-contacts projection: owner relation and exact customer grant are part of every query. */
final class NativeCompanyContactDirectory
{
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function scope(int$i,int$a):array
    {
        $access=new CompanyCustomerAccess($this->db,$this->p);if(!$access->canReadDirectory($i,$a))throw new DomainException('company_client_access_denied');
        $where='o.owner_issuer_id=?';$args=[$i];
        if(!(new IssuerResourceAccess($this->db,$this->p))->can($i,$a,'customers','view')){
            $ids=$access->assignedClientIds($i,$a);if(!$ids)throw new DomainException('company_client_access_denied');
            $where.=' AND c.userid IN ('.implode(',',array_fill(0,count($ids),'?')).')';$args=array_merge($args,$ids);
        }
        return[$where,$args];
    }
    public function metadata(int$i,int$a):array
    {
        [$where,$args]=$this->scope($i,$a);$first=$this->rows('SELECT c.userid FROM '.$this->p.'clients c JOIN '.$this->p.'issuers_clients o ON o.client_id=c.userid WHERE '.$where.' ORDER BY c.userid LIMIT 1',$args)[0]??null;
        $defs=$first?(new NativeCompanyCustomFields($this->db,$this->p))->definitionsForClientContacts($i,$a,(int)$first['userid'],['show_on_table'=>1]):[];
        $store=new CompanySettingsStorage($this->db,$this->p);$enabled=$store->get($i,'enable_gdpr')==='1'&&$store->get($i,'gdpr_enable_consent_for_contacts')==='1';$purposes=[];
        if($enabled){NativeCompanyConsentSchema::assertValid($this->db,$this->p);foreach($this->rows('SELECT id,name FROM '.$this->p.'company_native_consent_purposes WHERE issuer_id=? ORDER BY name DESC,id',[$i])as$r)$purposes[]=['id'=>NativeCompanyResourceId::encode('company_consent_purpose',(int)$r['id']),'name'=>$r['name']];}
        $this->scope($i,$a);return['custom_fields'=>$defs,'consent_enabled'=>$enabled,'purposes'=>$purposes];
    }
    public function page(int$i,int$a,array$input):array
    {
        [$scope,$args]=$this->scope($i,$a);$meta=$this->metadata($i,$a);$parsed=$input;$parsed['order'][0]['column']=0;$q=NativeCompanyClients::request($parsed);
        $join=' FROM '.$this->p.'contacts t JOIN '.$this->p.'clients c ON c.userid=t.userid JOIN '.$this->p.'issuers_clients o ON o.client_id=c.userid WHERE '.$scope;
        $total=(int)$this->rows('SELECT COUNT(*) n'.$join,$args)[0]['n'];$filtered=$join;$filterArgs=$args;
        if($q['search']!==''){$needle='%'.str_replace(['!','%','_'],['!!','!%','!_'],$q['search']).'%';$filtered.=" AND (t.firstname LIKE ? ESCAPE '!' OR t.lastname LIKE ? ESCAPE '!' OR t.email LIKE ? ESCAPE '!' OR c.company LIKE ? ESCAPE '!')";$filterArgs=array_merge($filterArgs,[$needle,$needle,$needle,$needle]);}
        $filter=$input['custom_view']??'';if(!is_string($filter))throw new DomainException('company_client_invalid_page');
        if($filter!==''){
            if(!$meta['consent_enabled']||!str_starts_with($filter,'consent_')||!in_array(substr($filter,8),array_column($meta['purposes'],'id'),true))throw new DomainException('company_client_invalid_page');
            $ref=NativeCompanyResourceId::decode('consent_purpose',substr($filter,8));
            $filtered.=" AND EXISTS (SELECT 1 FROM {$this->p}company_native_consents n WHERE n.issuer_id=? AND n.contact_id=t.id AND n.purpose_id=? AND n.id=(SELECT MAX(z.id) FROM {$this->p}company_native_consents z WHERE z.issuer_id=n.issuer_id AND z.contact_id=n.contact_id AND z.purpose_id=n.purpose_id) AND JSON_EXTRACT(n.record_json,'$.action') IN (?,?))";
            $filterArgs=array_merge($filterArgs,[$i,$ref['source_id'],'opt-in','"opt-in"']);
        }
        $columns=['t.firstname','t.lastname'];if($meta['consent_enabled'])$columns[]='t.id';$columns=array_merge($columns,['t.email','c.company','t.phonenumber','t.title','t.last_login','t.active']);
        $col=$input['order'][0]['column']??0;if((!is_int($col)&&!is_string($col))||!preg_match('/^(0|[1-9][0-9]{0,3})$/D',(string)$col)||(int)$col>=count($columns)+count($meta['custom_fields']))throw new DomainException('company_client_invalid_page');
        $orderArgs=[];if((int)$col<count($columns))$order=$columns[(int)$col];else{$field=$meta['custom_fields'][(int)$col-count($columns)];$ref=NativeCompanyResourceId::decode('custom_field',$field['id']);if($ref['source_kind']!=='company_custom_field')throw new DomainException('native_custom_field_denied');$order="(SELECT v.value FROM {$this->p}company_custom_field_values v WHERE v.issuer_id=? AND v.field_source_kind='company_custom_field' AND v.field_id=? AND v.source_kind='contact' AND v.source_id=t.id)";$orderArgs=[$i,$ref['source_id']];}
        $count=(int)$this->rows('SELECT COUNT(*) n'.$filtered,$filterArgs)[0]['n'];
        $rows=$this->rows('SELECT t.id,t.userid,t.firstname,t.lastname,t.email,t.title,t.phonenumber,t.active,t.last_login,t.is_primary,t.profile_image,c.company,c.active client_active,c.registration_confirmed,(SELECT COUNT(*) FROM '.$this->p.'contacts count_contacts WHERE count_contacts.userid=c.userid) total_contacts'.$filtered.' ORDER BY '.$order.' '.$q['direction'].',t.id ASC LIMIT '.$q['length'].' OFFSET '.$q['start'],array_merge($filterArgs,$orderArgs));
        $contacts=new CompanyNativeContacts($this->db,$this->p);foreach($rows as&$r)$r['revision']=$contacts->get($i,$a,(int)$r['userid'],(int)$r['id'])['revision'];unset($r);
        if($this->scope($i,$a)!==[$scope,$args])throw new DomainException('company_client_access_denied');
        return['draw'=>$q['draw'],'recordsTotal'=>$total,'recordsFiltered'=>$count,'rows'=>$rows]+$meta;
    }
}
