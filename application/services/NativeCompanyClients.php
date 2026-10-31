<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyClients.php';
require_once __DIR__.'/CompanyContacts.php';

/** Data adapter for the existing /admin/clients renderer. No session, hooks or legacy models. */
final class NativeCompanyClients
{
    private IssuerResourceAccess $access;
    public function __construct(private $db, private string $p, private int $issuer, private int $actor)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $p)) throw new InvalidArgumentException('Invalid prefix');
        IssuerPolicy::id($issuer); IssuerPolicy::id($actor);
        $this->access = new IssuerResourceAccess($db, $p);
    }
    private function authorize(string $cap = 'view'): void
    {
        if (!$this->access->can($this->issuer, $this->actor, 'customers', $cap)) throw new DomainException('company_client_access_denied');
    }
    private function directoryIds():?array
    {
        $access=new CompanyCustomerAccess($this->db,$this->p);if(!$access->canReadDirectory($this->issuer,$this->actor))throw new DomainException('company_client_access_denied');
        return $this->access->can($this->issuer,$this->actor,'customers','view')?null:$access->assignedClientIds($this->issuer,$this->actor);
    }
    private function directoryRestriction(?array $ids,string $column):string{return $ids===null?'':($ids===[]?' AND 1=0':' AND '.$column.' IN ('.implode(',',array_fill(0,count($ids),'?')).')');}
    private function confirmDirectory(?array $ids):void{if($this->directoryIds()!==$ids)throw new DomainException('company_client_access_denied');}
    private function rows(string $sql, array $args = []): array { return IssuerStorage::query($this->db, $sql, $args)->result_array(); }
    private function visible(): string
    {
        return '(o.owner_issuer_id=? OR EXISTS (SELECT 1 FROM '.$this->p.'issuers_client_links l WHERE l.client_id=c.userid AND l.issuer_id=?))';
    }
    /** Presentation preference only: never grants a tab or changes resource authorization. */
    public function filterProfileTabs(int $client,array $tabs):array
    {
        require_once __DIR__.'/NativeCompanyClientsSettings.php';
        $check=function()use($client):void{if(!(new CompanyClients($this->db,$this->p))->get($this->issuer,$this->actor,$client))throw new DomainException('company_client_access_denied');};$check();
        if(array_diff(array_keys($tabs),array_keys(NativeCompanyClientsSettings::TABS)))throw new DomainException('native_clients_settings_invalid');
        $store=new CompanySettingsStorage($this->db,$this->p);$value=$store->exists($this->issuer,'visible_customer_profile_tabs')?$store->get($this->issuer,'visible_customer_profile_tabs'):'all';
        $visible=NativeCompanyClientsSettings::visibleTabs($value);$filtered=array_filter($tabs,static fn($key)=>$visible[$key],ARRAY_FILTER_USE_KEY);
        $check();return$filtered;
    }
    public function summary(): array
    {
        $ids=$this->directoryIds();
        $counts = $this->rows('SELECT COUNT(*) total,COALESCE(SUM(c.active=1),0) active,COALESCE(SUM(c.active=0),0) inactive FROM '.$this->p.'clients c JOIN '.$this->p.'issuers_clients o ON o.client_id=c.userid WHERE '.$this->visible().$this->directoryRestriction($ids,'c.userid'), [$this->issuer,$this->issuer,...($ids??[])])[0];
        // A shared identity never grants contacts, including their aggregate counts.
        $contacts = $this->rows('SELECT COALESCE(SUM(p.active=1),0) active_contacts,COALESCE(SUM(p.active=0),0) inactive_contacts,COALESCE(SUM(p.last_login LIKE ?),0) logged_today FROM '.$this->p.'contacts p JOIN '.$this->p.'issuers_clients o ON o.client_id=p.userid WHERE o.owner_issuer_id=?'.$this->directoryRestriction($ids,'p.userid'), [date('Y-m-d').'%', $this->issuer,...($ids??[])])[0];
        $this->confirmDirectory($ids);
        return array_map('intval', $counts + $contacts);
    }
    public static function request(array $input): array
    {
        $integer = static function ($value, int $max): int {
            if ((!is_int($value) && !is_string($value)) || !preg_match('/^(0|[1-9][0-9]*)$/D', (string)$value) || strlen((string)$value)>9 || (int)$value>$max) throw new DomainException('company_client_invalid_page');
            return (int)$value;
        };
        $draw=$integer($input['draw']??0, 100000000); $start=$integer($input['start']??0, 1000000);
        $length=$integer($input['length']??25,100); if($length<1)throw new DomainException('company_client_invalid_page');
        if(isset($input['search'])&&!is_array($input['search']))throw new DomainException('company_client_invalid_input');
        if(isset($input['order'])&&(!is_array($input['order'])||(isset($input['order'][0])&&!is_array($input['order'][0]))))throw new DomainException('company_client_invalid_page');
        $search=$input['search']['value']??'';
        if(!is_string($search)||strlen($search)>191||preg_match('/[\x00-\x1f]/',$search))throw new DomainException('company_client_invalid_input');
        $order=$input['order'][0]??['column'=>1,'dir'=>'asc'];
        $column=$integer($order['column']??1,8);$direction=$order['dir']??'asc';
        if(!in_array($direction,['asc','desc'],true))throw new DomainException('company_client_invalid_page');
        // These are actual native table columns; custom SQL/filter expressions are never accepted.
        $columns=[1=>'c.userid',2=>'c.company',3=>'primary_firstname',4=>'primary_email',5=>'phonenumber',6=>'c.active',8=>'datecreated'];
        if(!isset($columns[$column])){$column=1;}
        return compact('draw','start','length','search','direction')+['order'=>$columns[$column]];
    }
    public function table(array $input): array
    {
        $q=self::request($input);$ids=$this->directoryIds();
        $from=' FROM '.$this->p.'clients c JOIN '.$this->p.'issuers_clients o ON o.client_id=c.userid WHERE '.$this->visible().$this->directoryRestriction($ids,'c.userid');
        $args=[$this->issuer,$this->issuer,...($ids??[])];
        $total=(int)$this->rows('SELECT COUNT(*) n'.$from,$args)[0]['n'];
        $needle='%'.str_replace(['!','%','_'],['!!','!%','!_'],trim($q['search'])).'%';
        $filter=" AND (c.company LIKE ? ESCAPE '!' OR c.vat LIKE ? ESCAPE '!' OR (o.owner_issuer_id=? AND EXISTS(SELECT 1 FROM ".$this->p."contacts q WHERE q.userid=c.userid AND q.is_primary=1 AND (q.firstname LIKE ? ESCAPE '!' OR q.lastname LIKE ? ESCAPE '!' OR q.email LIKE ? ESCAPE '!'))))";
        $searchArgs=[$needle,$needle,$this->issuer,$needle,$needle,$needle];
        $filtered=(int)$this->rows('SELECT COUNT(*) n'.$from.$filter,[...$args,...$searchArgs])[0]['n'];
        // Private fields are selected only for the owner, never for shared identities.
        $primary=[];foreach(['firstname','lastname','email','id']as$field)$primary[]='CASE WHEN o.owner_issuer_id='.(int)$this->issuer.' THEN (SELECT q.'.$field.' FROM '.$this->p.'contacts q WHERE q.userid=c.userid AND q.is_primary=1 ORDER BY q.id LIMIT 1) ELSE NULL END primary_'.$field;
        $rows=$this->rows('SELECT '.implode(',',$primary).',c.userid,c.company,c.vat,c.active,o.owner_issuer_id,CASE WHEN o.owner_issuer_id=? THEN c.phonenumber ELSE NULL END phonenumber,CASE WHEN o.owner_issuer_id=? THEN c.datecreated ELSE NULL END datecreated'.$from.$filter.' ORDER BY '.$q['order'].' '.$q['direction'].',c.userid ASC LIMIT '.$q['length'].' OFFSET '.$q['start'],[$this->issuer,$this->issuer,...$args,...$searchArgs]);
        foreach($rows as&$row){$row['groups']=[];if((int)$row['owner_issuer_id']===$this->issuer){if((int)$this->rows('SELECT COUNT(*) n FROM '.$this->p.'contacts WHERE userid=? AND is_primary=1',[$row['userid']])[0]['n']>1)throw new DomainException('company_contact_invalid_primary');$row['groups']=$this->rows('SELECT g.id,g.name FROM '.$this->p.'customer_groups m JOIN '.$this->p.'customers_groups g ON g.id=m.groupid JOIN '.$this->p.'company_customer_group_owners o ON o.group_id=g.id WHERE m.customer_id=? AND o.issuer_id=? ORDER BY g.name,g.id',[$row['userid'],$this->issuer]);}}unset($row);
        $this->confirmDirectory($ids);
        return ['draw'=>$q['draw'],'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'rows'=>$rows];
    }
    /** Existing native form names; ownership, provider fields and unknown values are rejected. */
    public function save(array $input, $client = null): int
    {
        $meta=['request_key','revision','company_clients_csrf','save_and_add_contact','custom_fields','native_customer_fields_revision'];
        $allowed=array_merge(['company','vat','brazil_profile','groups_in'],array_keys(CompanyClientProfile::NATIVE),CompanyClientNativeFields::fields(),$meta);
        if(array_diff(array_keys($input),$allowed))throw new DomainException('company_client_invalid_input');
        $profile=$input['brazil_profile']??[];
        if(!is_array($profile))throw new DomainException('company_client_invalid_input');
        $profile=array_merge($profile,array_intersect_key($input,CompanyClientProfile::NATIVE));
        $data=['company'=>$input['company']??null,'vat'=>$input['vat']??'','profile'=>$profile,'native_profile'=>array_intersect_key($input,array_flip(CompanyClientNativeFields::fields()))];
        $data['groups_in']=$input['groups_in']??[];
        $service=new CompanyClients($this->db,$this->p);
        if($client!==null&&!(new CompanyCustomerAccess($this->db,$this->p))->can($this->issuer,$this->actor,IssuerPolicy::id($client),'edit'))throw new DomainException('company_client_access_denied');
        $participant=null;$tables=[];$participantInput=[];
        {
            require_once __DIR__.'/NativeCompanyCustomFields.php';
            $fieldRevision=$input['native_customer_fields_revision']??null;
            if(!is_string($fieldRevision)||!preg_match('/^[a-f0-9]{64}$/D',$fieldRevision))throw new DomainException('company_client_stale');
            $submitted=$input['custom_fields']??[];
            if(!is_array($submitted)||array_diff(array_keys($submitted),['customers'])||!is_array($submitted['customers']??[]))throw new DomainException('native_custom_field_invalid');
            $submitted=$submitted['customers']??[];$fields=new NativeCompanyCustomFields($this->db,$this->p);
            if($client===null)$fields->validateValues($this->issuer,$this->actor,'customers',$submitted,'create');else $fields->validateValuesForResource($this->issuer,$this->actor,'customers',$client,$submitted,'edit');
            $tables=$fields->tableNames();$participantInput=['custom_fields'=>$submitted,'fields_revision'=>$fieldRevision];
            $participant=function($id,$writer)use($fields,$submitted,$fieldRevision,$client):void{
                $before=$client===null?[]:$fields->storedInput($this->issuer,$this->actor,'customers',$id,'edit');
                if(!hash_equals($fieldRevision,hash('sha256',json_encode($before,JSON_THROW_ON_ERROR))))throw new DomainException('company_client_stale');
                if($client===null)$fields->saveCreatedInOwnedTransaction($this->issuer,$this->actor,'customers',$id,$submitted,$writer);
                else $fields->saveInOwnedTransaction($this->issuer,$this->actor,'customers',$id,$submitted);
            };
        }
        if($client===null){
            $key=$input['request_key']??null;
            if(!is_string($key)||!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_client_invalid_input');
            return $service->create($this->issuer,$this->actor,$data,$key,$participant,$tables,$participantInput);
        }
        $revision=$input['revision']??null;
        if(!is_string($revision)||!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new DomainException('company_client_stale');
        $service->update($this->issuer,$this->actor,$client,$data,$revision,$participant,$tables);
        return IssuerPolicy::id($client);
    }
    public function fieldData($client=null):array
    {
        require_once __DIR__.'/NativeCompanyCustomFields.php';$fields=new NativeCompanyCustomFields($this->db,$this->p);
        $values=$client===null?[]:$fields->storedInput($this->issuer,$this->actor,'customers',$client);
        return ['native_customer_fields_revision'=>hash('sha256',json_encode($values,JSON_THROW_ON_ERROR)),
            'native_customer_custom_fields'=>($client===null?$fields->definitions($this->issuer,$this->actor,'customers',['active'=>1],'create'):$fields->definitionsForResource($this->issuer,$this->actor,'customers',$client,['active'=>1],'view'))];
    }
    public function details($client): ?array { $details=(new CompanyClients($this->db,$this->p))->details($this->issuer,$this->actor,$client);if($details&&$details['client']['owner_issuer_id']===$this->issuer)$details['native_profile']=CompanyClientNativeFields::read($this->db,$this->p,$this->issuer,(int)$client);return $details; }
    public function groups($client): array { return (new CompanyCustomerGroups($this->db,$this->p))->memberships($this->issuer,$this->actor,IssuerPolicy::id($client)); }
    public function contacts($client): array { return (new CompanyContacts($this->db,$this->p))->page($this->issuer,$this->actor,$client); }
    public function billing($client): array
    {
        $details=$this->details($client);
        if(!$details||!isset($details['native_profile']))throw new DomainException('company_client_access_denied');
        return [array_intersect_key($details['native_profile'],array_flip(['billing_street','billing_city','billing_state','billing_zip','billing_country','shipping_street','shipping_city','shipping_state','shipping_zip','shipping_country']))];
    }
    public function duplicateName($name): bool
    {
        $this->authorize('create');
        if(!is_string($name)||strlen($name)>191||preg_match('/[\x00-\x1f]/',$name))throw new DomainException('company_client_invalid_input');
        $rows=$this->rows('SELECT c.userid FROM '.$this->p.'clients c JOIN '.$this->p.'issuers_clients o ON o.client_id=c.userid WHERE '.$this->visible().' AND c.company=? LIMIT 1',[$this->issuer,$this->issuer,trim($name)]);
        $this->authorize('create');return (bool)$rows;
    }
    public function references(?int $client=null): array
    {
        $caps=$this->capabilities($client);if(!$caps['view']&&!$caps['create']&&!$caps['edit'])throw new DomainException('company_client_access_denied');
        $data=['currencies'=>$this->rows('SELECT id,name,symbol,isdefault FROM '.$this->p.'currencies ORDER BY id'),
            'countries'=>$this->rows('SELECT country_id,short_name FROM '.$this->p.'countries ORDER BY short_name'),'groups'=>(new CompanyCustomerGroups($this->db,$this->p))->all($this->issuer,$this->actor,$client)];
        $store=new CompanySettingsStorage($this->db,$this->p);$country=$store->exists($this->issuer,'customer_default_country')?$store->get($this->issuer,'customer_default_country'):'';
        if(!preg_match('/^(?:|0|[1-9][0-9]{0,8})$/D',$country)||(!in_array($country,['','0'],true)&&!in_array($country,array_map('strval',array_column($data['countries'],'country_id')),true)))throw new DomainException('native_clients_settings_invalid');
        $data['native_customer_default_country']=$country;
        $data['native_customer_company_required']=(new CompanyClients($this->db,$this->p))->companyNameRequired($this->issuer)?'1':'0';
        $caps=$this->capabilities($client);if(!$caps['view']&&!$caps['create']&&!$caps['edit'])throw new DomainException('company_client_access_denied');return $data;
    }
    public function capabilities(?int $client=null): array
    {
        if(!$this->access->member($this->issuer,$this->actor))throw new DomainException('company_client_access_denied');$out=[];foreach(['view','create','edit','share'] as $cap)$out[$cap]=$this->access->can($this->issuer,$this->actor,'customers',$cap)||($client!==null&&in_array($cap,['view','edit'],true)&&(new CompanyCustomerAccess($this->db,$this->p))->can($this->issuer,$this->actor,$client,$cap));if($client===null)$out['view']=(new CompanyCustomerAccess($this->db,$this->p))->canReadDirectory($this->issuer,$this->actor);return $out;
    }
    private function coordinates(int$client,string$cap='view'):array
    {
        $access=new CompanyCustomerAccess($this->db,$this->p);if(!$access->can($this->issuer,$this->actor,$client,$cap))throw new DomainException('company_client_access_denied');
        $row=$this->rows('SELECT c.userid,c.latitude,c.longitude FROM '.$this->p.'clients c JOIN '.$this->p.'issuers_clients o ON o.client_id=c.userid WHERE c.userid=? AND o.owner_issuer_id=?',[$client,$this->issuer])[0]??null;
        if(!$row||!$access->can($this->issuer,$this->actor,$client,$cap))throw new DomainException('company_client_access_denied');return$row+['revision'=>hash('sha256',json_encode([$this->issuer,$row],JSON_THROW_ON_ERROR))];
    }
    public function maps(int$client):array
    {
        $row=$this->coordinates($client);$key=(new CompanySettingsStorage($this->db,$this->p))->get($this->issuer,'google_api_key');if(!preg_match('/^[A-Za-z0-9_-]{0,256}$/D',$key))throw new DomainException('company_client_map_configuration_invalid');
        if($this->coordinates($client)!==$row)throw new DomainException('company_client_stale');return$row+['google_api_key'=>$key];
    }
    public function saveCoordinates(int$client,$latitude,$longitude,string$revision):void
    {
        $values=[];if($latitude===''&&$longitude==='')$values=['latitude'=>'','longitude'=>''];else foreach(['latitude'=>[$latitude,90],'longitude'=>[$longitude,180]]as$name=>[$value,$max]){if(!is_string($value)||strlen($value)>32||!preg_match('/^-?(?:0|[1-9][0-9]{0,2})(?:\.[0-9]{1,15})?$/D',$value)||abs((float)$value)>$max)throw new DomainException('company_client_coordinates_invalid');$values[$name]=rtrim(rtrim(number_format((float)$value,8,'.',''),'0'),'.');if($values[$name]==='-0')$values[$name]='0';}
        if(!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new DomainException('company_client_stale');
        IssuerTransaction::run($this->db,$this->p,(new CompanyClients($this->db,$this->p))->tables(false),function()use($client,$revision,$values){$before=$this->coordinates($client,'edit');if(!hash_equals($before['revision'],$revision))throw new DomainException('company_client_stale');
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'clients SET latitude=?,longitude=? WHERE userid=?',[$values['latitude'],$values['longitude'],$client]);$after=$this->coordinates($client,'edit');foreach($values as$key=>$value)if((string)$after[$key]!==$value)throw new RuntimeException('issuer_client_write_unconfirmed');
            require_once __DIR__.'/NativeCompanyCustomerAudit.php';NativeCompanyCustomerAudit::record($this->db,$this->p,$this->issuer,$this->actor,$client,'native_customer_coordinates_updated');$this->coordinates($client,'edit');
        });
    }

}
