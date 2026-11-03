<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyCustomFieldSchema.php';
require_once __DIR__.'/CompanyCustomerAccess.php';
require_once __DIR__.'/NativeCompanyResourceReferences.php';
require_once __DIR__.'/NativeCompanyInvoiceIdentity.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Owned backend for the existing custom-field forms, not an alternative renderer. */
final class NativeCompanyCustomFields
{
    private const FEATURES=['company'=>'settings','customers'=>'customers','contacts'=>'customers','staff'=>'staff','contracts'=>'contracts','tasks'=>'tasks','expenses'=>'expenses','invoice'=>'invoices','items'=>'items','items_pr'=>'items','credit_note'=>'credit_notes','estimate'=>'estimates','proposal'=>'proposals','projects'=>'projects','tickets'=>'tickets','leads'=>'leads'];
    private const FLAGS=['required','display_inline','active','show_on_pdf','show_on_ticket_form','only_admin','show_on_table','show_on_client_portal','disalow_client_to_edit'];
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public function tableNames():array{return['issuers_lock','issuers_registry','issuers_staff','issuers_installation_identity','issuers_resource_permissions','issuers_audit','staff','company_custom_fields','company_custom_field_values'];}
    private function authorize(int $issuer,int $actor,string $fieldto,string $cap='view'):IssuerResourceAccess
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if(!isset(self::FEATURES[$fieldto])||!$acl->can($issuer,$actor,self::FEATURES[$fieldto],$cap))throw new DomainException('native_custom_field_denied');return$acl;
    }
    private static function text($v,int $max,bool $empty=true):string
    {
        if(!is_string($v)||strlen($v)>$max||(!$empty&&trim($v)==='')||preg_match('//u',$v)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$v))throw new DomainException('native_custom_field_invalid');return$v;
    }
    /** Canonical storage contains only the native form fields; unknown input is rejected. */
    public static function definition(array $input):array
    {
        $keys=array_merge(['fieldto','name','type','options','field_order','bs_column','default_value'],self::FLAGS);
        if(array_diff(array_keys($input),$keys)||array_diff(['fieldto','name','type'],array_keys($input)))throw new DomainException('native_custom_field_invalid');
        $to=$input['fieldto']??null;if(!is_string($to)||!isset(self::FEATURES[$to])||$to==='items_pr')throw new DomainException('native_custom_field_invalid');
        $type=$input['type']??null;if(!in_array($type,['input','number','textarea','select','multiselect','checkbox','date_picker','date_picker_time','colorpicker','link'],true))throw new DomainException('native_custom_field_invalid');
        $out=['fieldto'=>$to,'name'=>self::text($input['name']??null,191,false),'type'=>$type,'options'=>self::text($input['options']??'',65535),'field_order'=>0,'bs_column'=>12,'default_value'=>self::text($input['default_value']??'',65535)];
        foreach(['field_order'=>[0,100000],'bs_column'=>[1,12]]as$k=>$range){$v=$input[$k]??$out[$k];if((!is_int($v)&&!is_string($v))||!preg_match('/^(?:0|[1-9][0-9]*)$/D',(string)$v)||(int)$v<$range[0]||(int)$v>$range[1])throw new DomainException('native_custom_field_invalid');$out[$k]=(int)$v;}
        foreach(self::FLAGS as$k){$v=$input[$k]??($k==='active'?1:0);if(!in_array($v,[0,1,'0','1'],true))throw new DomainException('native_custom_field_invalid');$out[$k]=(int)$v;}
        return$out;
    }
    public function definitions(int $issuer,int $actor,string $fieldto,array $filters=[],string $cap='view'):array
    {
        if(!in_array($cap,['view','create','edit'],true))throw new DomainException('native_custom_field_denied');
        return $this->readDefinitions($issuer,$actor,$fieldto,$filters,fn()=>$this->authorize($issuer,$actor,$fieldto,$cap));
    }
    /** Settings catalog contains definitions only, never any customer values or grants. */
    public function definitionsForClientSettings(int $issuer,int $actor):array
    {
        $verify=function()use($issuer,$actor):void{if(!(new IssuerResourceAccess($this->db,$this->p))->can($issuer,$actor,'settings','view'))throw new DomainException('native_custom_field_denied');};
        return $this->readDefinitions($issuer,$actor,'customers',['active'=>1],$verify);
    }
    /** Sales settings may list their own field definitions; no global field values are exposed. */
    public function definitionsForSalesSettings(int $issuer,int $actor,string $fieldto):array
    {
        if($fieldto!=='proposal')throw new DomainException('native_custom_field_denied');
        $verify=function()use($issuer,$actor):void{if(!(new IssuerResourceAccess($this->db,$this->p))->can($issuer,$actor,'settings','view'))throw new DomainException('native_custom_field_denied');};
        return $this->readDefinitions($issuer,$actor,$fieldto,['active'=>1],$verify);
    }
    public function definitionsForResource(int$issuer,int$actor,string$fieldto,$id,array$filters=[],string$cap='view'):array
    {
        if(!in_array($cap,['view','edit'],true))throw new DomainException('native_custom_field_denied');
        return$this->readDefinitions($issuer,$actor,$fieldto,$filters,fn()=>$this->resource($issuer,$actor,$fieldto,$id,$cap));
    }
    /** Invoice line fields share native definitions, never the catalogue item's ACL or identity. */
    public function invoiceItemDefinitions(int $issuer,int $actor,?int $invoice=null,array $filters=[],string $cap='view'):array
    {
        if(!in_array($cap,['view','create','edit'],true)||($invoice===null&&$cap!=='create'))throw new DomainException('native_custom_field_denied');
        $verify=$invoice===null?fn()=>$this->authorize($issuer,$actor,'invoice','create'):fn()=>(new NativeCompanyInvoiceIdentity($this->db,$this->p))->resolve($issuer,$actor,$invoice,$cap);
        return $this->readDefinitions($issuer,$actor,'items',$filters,$verify);
    }
    /** New-row fields in the real invoice editor use its canonical parent, not catalogue permissions. */
    public function invoiceEditorItemDefinitions(int $issuer,int $actor,$invoice=null,array $filters=[]):array
    {
        if($invoice===null)return$this->invoiceItemDefinitions($issuer,$actor,null,$filters,'create');
        return$this->readDefinitions($issuer,$actor,'items',$filters,fn()=>$this->resource($issuer,$actor,'invoice',$invoice,'edit'));
    }
    /** Catalogue selection reads values only under a concrete live invoice editor authority. */
    public function invoiceCatalogItem(int $issuer,int $actor,$id,NativeCompanyInvoiceCatalogLookup $lookup):array
    {
        $lookup->assertContext($this->db,$this->p,$issuer,$actor);$item=$lookup->item($id);$ref=NativeCompanyResourceId::decode('item',$id);
        $verify=function()use($lookup,$id,$item):void{$lookup->assertCurrent();if($lookup->item($id)!==$item)throw new DomainException('native_invoice_editor_mismatch');};
        $defs=$this->readDefinitions($issuer,$actor,'items',['active'=>1],$verify);$values=[];
        foreach($defs as$d){$row=IssuerStorage::query($this->db,'SELECT value FROM '.$this->p.'company_custom_field_values WHERE issuer_id=? AND field_source_kind=? AND field_id=? AND source_kind=? AND source_id=?',[$issuer,$d['field_source_kind'],$d['source_field_id'],$ref['source_kind'],$ref['source_id']])->row_array();
            if(!$row&&$d['field_source_kind']==='custom_field'&&$ref['source_kind']==='item'&&(new IssuerResourceAccess($this->db,$this->p))->primaryId()===$issuer)$row=IssuerStorage::query($this->db,'SELECT value FROM '.$this->p.'customfieldsvalues WHERE fieldid=? AND fieldto=? AND relid=?',[$d['source_field_id'],'items_pr',$ref['source_id']])->row_array();
            if($row)$values[(string)$d['id']]=(string)$row['value'];
        }$verify();return['definitions'=>$defs,'values'=>$values];
    }
    public function definitionsForClientContacts(int$issuer,int$actor,int$client,array$filters=[],string$cap='view'):array
    {
        if(!in_array($cap,['view','create','edit'],true))throw new DomainException('native_custom_field_denied');
        $verify=function()use($issuer,$actor,$client,$cap):void{if(!(new CompanyCustomerAccess($this->db,$this->p))->can($issuer,$actor,$client,$cap==='create'?'create_contact':$cap)||!IssuerStorage::query($this->db,'SELECT userid FROM '.$this->p.'clients WHERE userid=?'.($cap==='view'?'':' AND active=1'),[$client])->row_array())throw new DomainException('native_custom_field_denied');};
        return$this->readDefinitions($issuer,$actor,'contacts',$filters,$verify);
    }
    public function definitionsForContactCreation(int$issuer,int$actor,int$client,array$filters=[]):array{return$this->definitionsForClientContacts($issuer,$actor,$client,$filters,'create');}
    private function readDefinitions(int$issuer,int$actor,string$fieldto,array$filters,callable$verify):array
    {
        $verify();$acl=new IssuerResourceAccess($this->db,$this->p);NativeCompanyCustomFieldSchema::assertValid($this->db,$this->p);
        foreach($filters as$k=>$v)if(!in_array($k,self::FLAGS,true)||!in_array($v,[0,1,'0','1'],true))throw new DomainException('native_custom_field_filter_invalid');
        $definitionTo=$fieldto==='items_pr'?'items':$fieldto;
        $rows=IssuerStorage::query($this->db,'SELECT id,issuer_id,fieldto,slug,definition_json,revision FROM '.$this->p.'company_custom_fields WHERE issuer_id=? AND fieldto=? ORDER BY id',[$issuer,$definitionTo])->result_array();$out=[];
        foreach($rows as$r){$d=json_decode($r['definition_json'],true,32,JSON_THROW_ON_ERROR);if(!is_array($d)||self::definition($d)!==$d||$d['fieldto']!==$r['fieldto']||(int)$r['revision']<1)throw new RuntimeException('native_custom_field_corrupt');$out[]=$d+['id'=>NativeCompanyResourceId::encode('company_custom_field',$r['id']),'slug'=>$r['slug'],'revision'=>(int)$r['revision'],'field_source_kind'=>'company_custom_field','source_field_id'=>(int)$r['id']];}
        if($acl->primaryId()===$issuer){
            $legacy=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.'customfields WHERE fieldto=? ORDER BY field_order,id',[$definitionTo])->result_array();
            foreach($legacy as$r){$r['source_field_id']=(int)$r['id'];$r['id']=NativeCompanyResourceId::encode('custom_field',$r['source_field_id']);$r['field_source_kind']='custom_field';$r['revision']=null;$out[]=$r;}
        }
        $admin=$acl->administrative($issuer,$actor);$out=array_values(array_filter($out,static function($d)use($filters,$admin){if((int)$d['only_admin']===1&&!$admin)return false;foreach($filters as$k=>$v)if((string)$d[$k]!== (string)$v)return false;return true;}));
        usort($out,static fn($a,$b)=>[(int)$a['field_order'],(int)$a['id']]<=>[(int)$b['field_order'],(int)$b['id']]);$verify();return$out;
    }
    private function resource(int $issuer,int $actor,string $fieldto,$id,string $cap):array
    {
        if($fieldto==='items'){
            $lineReference=NativeCompanyResourceId::decode('invoice_item',$id);
            if($lineReference['source_kind']==='company_invoice_draft_line'){
                require_once __DIR__.'/CompanyInvoiceDrafts.php';
                $this->authorize($issuer,$actor,'invoice',$cap);$source=$lineReference['source_id'];
                $read=fn()=>IssuerStorage::query($this->db,'SELECT id,draft_id,draft_revision FROM '.$this->p.NativeCompanyInvoiceDraftLinesSchema::TABLE.' WHERE id=? AND issuer_id=?',[$source,$issuer])->row_array();
                $line=$read();if(!$line)throw new DomainException('native_custom_field_denied');
                $drafts=new CompanyInvoiceDrafts($this->db,$this->p);$owned=false;
                try{IssuerTransaction::assertOwned($this->db,$this->p,$drafts->tables());$owned=true;}catch(RuntimeException $error){if($error->getMessage()!=='issuer_owned_transaction_required')throw$error;}
                $document=$owned?$drafts->documentInOwned($issuer,$actor,(int)$line['draft_id']):$drafts->document($issuer,$actor,(int)$line['draft_id']);
                if($document['state']!=='open'||$document['revision']!==(int)$line['draft_revision']||$line!==$read())throw new DomainException('native_custom_field_denied');
                return ['source_kind'=>'company_invoice_draft_line','source_id'=>$source,'draft_id'=>$document['id'],'draft_revision'=>$document['revision']];
            }
            $id=$lineReference['source_id'];
            $read=fn()=>IssuerStorage::query($this->db,'SELECT id,rel_id,rel_type FROM '.$this->p.'itemable WHERE id=?',[$id])->row_array();
            $row=$read();if(!$row||$row['rel_type']!=='invoice')throw new DomainException('native_custom_field_denied');
            $parent=(new NativeCompanyInvoiceIdentity($this->db,$this->p))->resolve($issuer,$actor,$row['rel_id'],$cap);
            if($read()!==$row)throw new DomainException('native_custom_field_denied');
            return ['source_kind'=>'invoice_item','source_id'=>$id,'invoice_id'=>$parent['invoice_id'],'lineage'=>$parent['lineage']];
        }
        if(in_array($fieldto,['customers','contacts'],true)&&in_array($cap,['view','edit'],true)){
            $type=$fieldto==='customers'?'client':'contact';$ref=NativeCompanyResourceId::decode($type,$id);if($ref['source_kind']!==$type)throw new DomainException('native_custom_field_denied');
            $client=$fieldto==='customers'?(int)$ref['source_id']:(int)(IssuerStorage::query($this->db,'SELECT userid FROM '.$this->p.'contacts WHERE id=?',[$ref['source_id']])->row_array()['userid']??0);
            if($client>0&&(new CompanyCustomerAccess($this->db,$this->p))->can($issuer,$actor,$client,$cap))return$ref;
        }
        $this->authorize($issuer,$actor,$fieldto,$cap);
        if($fieldto==='items_pr'){
            $ref=NativeCompanyResourceId::decode('item',$id);if($ref['source_kind']==='company_item'){$row=IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.'company_items WHERE issuer_id=? AND id=?',[$issuer,$ref['source_id']])->row_array();}else{if((new IssuerResourceAccess($this->db,$this->p))->primaryId()!==$issuer)throw new DomainException('native_custom_field_denied');$row=IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.'items WHERE id=?',[$ref['source_id']])->row_array();}if(!$row)throw new DomainException('native_custom_field_denied');return$ref;
        }
        if($fieldto==='company'){
            if((string)$id!==(string)$issuer)throw new DomainException('native_custom_field_denied');return['source_kind'=>'company','source_id'=>$issuer];
        }
        $type=['customers'=>'client','invoice'=>'invoice','contracts'=>'contract','tasks'=>'task','projects'=>'project','tickets'=>'ticket','leads'=>'lead','expenses'=>'expense','estimate'=>'estimate','proposal'=>'proposal','credit_note'=>'credit_note','contacts'=>'contact','staff'=>'staff'][$fieldto]??null;
        if($type===null)throw new DomainException('native_custom_field_resource_unavailable');
        return NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,$type,$id,$cap);
    }
    public function values(int $issuer,int $actor,string $fieldto,$id,string $cap='view'):array
    {
        if(!in_array($cap,['view','edit'],true))throw new DomainException('native_custom_field_denied');
        $ref=$this->resource($issuer,$actor,$fieldto,$id,$cap);$defs=$this->definitionsForResource($issuer,$actor,$fieldto,$id,[],$cap);$out=[];
        foreach($defs as$d){$row=IssuerStorage::query($this->db,'SELECT value FROM '.$this->p.'company_custom_field_values WHERE issuer_id=? AND field_source_kind=? AND field_id=? AND source_kind=? AND source_id=?',[$issuer,$d['field_source_kind'],$d['source_field_id'],$ref['source_kind'],$ref['source_id']])->row_array();
            if(!$row&&$d['field_source_kind']==='custom_field'&&(new IssuerResourceAccess($this->db,$this->p))->primaryId()===$issuer&&!str_starts_with($ref['source_kind'],'company')&&($ref['source_kind']!=='invoice_item'||$ref['lineage']==='native_legacy_null')){$row=IssuerStorage::query($this->db,'SELECT value FROM '.$this->p.'customfieldsvalues WHERE fieldid=? AND fieldto=? AND relid=?',[$d['source_field_id'],$fieldto,$ref['source_id']])->row_array();}
            if($row)$out[(string)$d['id']]=(string)$row['value'];
        }
        $this->resource($issuer,$actor,$fieldto,$id,$cap);return$out;
    }
    /** Native global-search contract: administrative users only, with per-resource ownership first. */
    public function search(int $issuer,int $actor,string $query,int $limit=20):array
    {
        $query=trim(self::text($query,764,false));
        if(mb_strlen($query,'UTF-8')<2||mb_strlen($query,'UTF-8')>191||$limit<1||$limit>100)throw new DomainException('native_custom_field_invalid');
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if(!$acl->member($issuer,$actor))throw new DomainException('native_custom_field_denied');
        if(!$acl->administrative($issuer,$actor))return []; // Same native search policy, not global is_admin().
        $types=['company'=>'company','customers'=>'client','contacts'=>'contact','staff'=>'staff','contracts'=>'contract','tasks'=>'task','expenses'=>'expense','invoice'=>'invoice','items'=>'item','credit_note'=>'credit_note','estimate'=>'estimate','proposal'=>'proposal','projects'=>'project','tickets'=>'ticket','leads'=>'lead'];
        $needle='%'.str_replace(['!','%','_'],['!!','!%','!_'],$query).'%';$result=[];$seen=[];
        foreach($types as $fieldto=>$type){
            if(!$acl->can($issuer,$actor,self::FEATURES[$fieldto],'view'))continue;
            $resourceTo=$fieldto==='items'?'items_pr':$fieldto;
            foreach($this->definitions($issuer,$actor,$fieldto,['active'=>1]) as $definition){
                $streams=['owned'];if($definition['field_source_kind']==='custom_field'&&$acl->primaryId()===$issuer)$streams[]='legacy';
                foreach($streams as $stream){
                    $offset=0;
                    do{
                        if($stream==='owned')$candidates=IssuerStorage::query($this->db,'SELECT source_kind,source_id FROM '.$this->p.'company_custom_field_values WHERE issuer_id=? AND field_source_kind=? AND field_id=? AND value LIKE ?'." ESCAPE '!' ORDER BY source_kind,source_id LIMIT 50 OFFSET ".$offset,[$issuer,$definition['field_source_kind'],$definition['source_field_id'],$needle])->result_array();
                        else $candidates=IssuerStorage::query($this->db,'SELECT relid AS source_id FROM '.$this->p.'customfieldsvalues WHERE fieldid=? AND fieldto=? AND value LIKE ?'." ESCAPE '!' ORDER BY relid LIMIT 50 OFFSET ".$offset,[$definition['source_field_id'],$fieldto,$needle])->result_array();
                        foreach($candidates as $candidate){
                            try{
                                $source=$candidate['source_kind']??$type;
                                if($fieldto==='items'&&$source==='invoice_item')continue; // Invoice lines are not catalogue search results.
                                $id=$type==='company'?(string)$candidate['source_id']:NativeCompanyResourceId::encode($source,$candidate['source_id']);
                                $reference=$this->resource($issuer,$actor,$resourceTo,$id,'view');
                                if($reference['source_kind']!==$source||(string)$reference['source_id']!==(string)$candidate['source_id'])throw new DomainException('native_custom_field_denied');
                                $values=$this->values($issuer,$actor,$resourceTo,$id); // Only after ownership; honors owned override of historical values.
                                $value=$values[(string)$definition['id']]??null;
                                if($value===null||mb_stripos($value,$query,0,'UTF-8')===false)continue;
                                if($type==='company')$reference+=['url'=>'settings?group=company','name'=>$definition['name']];
                                elseif(!isset($reference['url']))$reference=NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,$type,$id,'view');
                                $key=$source.':'.$candidate['source_id'].':'.$definition['id'];if(isset($seen[$key]))continue;$seen[$key]=true;
                                $result[]=['label'=>$reference['name'],'relative_url'=>$reference['url'],'relation_type'=>$fieldto,'source_kind'=>$source,'source_id'=>$candidate['source_id']];
                                if(count($result)>=$limit)break 5;
                            }catch(DomainException $denied){continue;}
                        }
                        $offset+=50;
                    }while(count($candidates)===50);
                }
            }
        }
        if(!$acl->administrative($issuer,$actor))throw new DomainException('native_custom_field_denied');
        return $result;
    }
    public static function value(array $definition,$value):string
    {
        $type=$definition['type'];
        if(in_array($type,['checkbox','multiselect'],true)){
            if(!is_array($value))throw new DomainException('native_custom_field_invalid');$choices=array_map('trim',explode(',',$definition['options']));$picked=[];foreach($value as$v){if($v==='cfk_hidden')continue;if(!is_string($v)||!in_array($v,$choices,true)||isset($picked[$v]))throw new DomainException('native_custom_field_invalid');$picked[$v]=true;}$value=implode(', ',array_keys($picked));
        }
        $value=self::text($value,65535);if((int)$definition['required']===1&&trim($value)==='')throw new DomainException('native_custom_field_required');if($value==='')return'';
        if($type==='select'&&!in_array($value,array_map('trim',explode(',',$definition['options'])),true))throw new DomainException('native_custom_field_invalid');
        if($type==='number'&&!preg_match('/^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/D',$value))throw new DomainException('native_custom_field_invalid');
        if($type==='colorpicker'&&!preg_match('/^#[a-fA-F0-9]{6}$/D',$value))throw new DomainException('native_custom_field_invalid');
        if(in_array($type,['date_picker','date_picker_time'],true)){$format=$type==='date_picker'?'Y-m-d':'Y-m-d H:i:s';$date=DateTimeImmutable::createFromFormat('!'.$format,$value);if(!$date||$date->format($format)!==$value)throw new DomainException('native_custom_field_invalid');}
        return$value;
    }
    private function manage(int $issuer,int $actor):IssuerResourceAccess
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($issuer,$actor,'settings','view')||!$acl->can($issuer,$actor,'settings','edit'))throw new DomainException('native_custom_field_denied');return$acl;
    }
    /** Same configuration form, with an explicit company transaction and durable create replay. */
    public function saveDefinition(int $issuer,int $actor,array $input,$id=null,?int $revision=null,?string $requestKey=null):array
    {
        $definition=self::definition($input);$this->manage($issuer,$actor);NativeCompanyCustomFieldSchema::assertValid($this->db,$this->p);
        if($id===null){if(!is_string($requestKey)||!preg_match('/^[a-f0-9]{32}$/D',$requestKey))throw new DomainException('native_custom_field_request_invalid');}
        else{$ref=NativeCompanyResourceId::decode('custom_field',$id);if($ref['source_kind']!=='company_custom_field'||$revision===null||$revision<1)throw new DomainException('native_custom_field_revision_invalid');}
        return IssuerTransaction::run($this->db,$this->p,$this->tableNames(),function()use($issuer,$actor,$definition,$id,$revision,$requestKey){
            $acl=$this->manage($issuer,$actor);$acl->lockFinancialIdentity($issuer,$actor);$json=json_encode($definition,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            if($id===null){
                $slug=$definition['fieldto'].'_'.hash('sha256',$issuer.':'.$actor.':'.$requestKey);
                $old=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.'company_custom_fields WHERE issuer_id=? AND slug=? FOR UPDATE',[$issuer,$slug])->row_array();
                if($old){if($old['definition_json']!==$json)throw new DomainException('native_custom_field_request_conflict');return['id'=>NativeCompanyResourceId::encode('company_custom_field',$old['id']),'revision'=>(int)$old['revision']];}
                IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_custom_fields (issuer_id,fieldto,slug,definition_json,revision) VALUES(?,?,?,?,1)',[$issuer,$definition['fieldto'],$slug,$json]);$source=(int)$this->db->insert_id();$next=1;if($source<1)throw new RuntimeException('native_custom_field_write_unconfirmed');
            }else{
                $source=NativeCompanyResourceId::decode('custom_field',$id)['source_id'];$old=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.'company_custom_fields WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$source])->row_array();if(!$old||(int)$old['revision']!==$revision)throw new DomainException('native_custom_field_stale');
                $previous=json_decode($old['definition_json'],true,32,JSON_THROW_ON_ERROR);if($previous['fieldto']!==$definition['fieldto'])throw new DomainException('native_custom_field_relation_immutable');
                if(($previous['type']!==$definition['type']||$previous['options']!==$definition['options'])&&IssuerStorage::query($this->db,"SELECT field_id FROM {$this->p}company_custom_field_values WHERE issuer_id=? AND field_source_kind='company_custom_field' AND field_id=? LIMIT 1",[$issuer,$source])->row_array())throw new DomainException('native_custom_field_options_in_use');
                $next=$revision+1;if($next>2147483647)throw new DomainException('native_custom_field_revision_overflow');IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_custom_fields SET definition_json=?,revision=? WHERE issuer_id=? AND id=? AND revision=?',[$json,$next,$issuer,$source,$revision]);
            }
            $now=date('Y-m-d H:i:s');IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,?,?)',[$actor,'native_custom_field_saved',$issuer,$source,$now]);
            $record=IssuerStorage::query($this->db,'SELECT definition_json,revision,fieldto FROM '.$this->p.'company_custom_fields WHERE issuer_id=? AND id=?',[$issuer,$source])->row_array();
            if(!$record||$record['definition_json']!==$json||(int)$record['revision']!==$next||$record['fieldto']!==$definition['fieldto'])throw new RuntimeException('native_custom_field_write_unconfirmed');$this->manage($issuer,$actor);
            return['id'=>NativeCompanyResourceId::encode('company_custom_field',$source),'revision'=>$next];
        });
    }
    /** Convert a known persisted representation back into form input, not permissive wire parsing. */
    public function storedInput(int $issuer,int $actor,string $fieldto,$resourceId,string $cap='view'):array
    {
        $values=$this->values($issuer,$actor,$fieldto,$resourceId,$cap);$defs=[];foreach($this->definitionsForResource($issuer,$actor,$fieldto,$resourceId,[],$cap)as$d)$defs[(string)$d['id']]=$d;
        foreach($values as$id=>$value){$d=$defs[(string)$id]??null;if(!$d)throw new DomainException('native_custom_field_denied');if(in_array($d['type'],['checkbox','multiselect'],true)){$values[$id]=$value===''?[]:explode(', ',$value);if(self::value($d,$values[$id])!==$value)throw new DomainException('native_custom_field_corrupt');}}
        return$values;
    }
    /** Simulation/validation only: definitions are authorized reads; no ID allocation or mutation. */
    public function validateValues(int $issuer,int $actor,string $fieldto,array $submitted,string $cap='view'):array
    {return self::validateDefined($this->definitions($issuer,$actor,$fieldto,['active'=>1],$cap),$submitted);}
    public function validateInvoiceDraftItemValues(int $issuer,int $actor,int $draft,array $submitted):array
    {
        $canonical=NativeCompanyResourceId::encode('company_invoice_draft',$draft);
        return self::validateDefined($this->readDefinitions($issuer,$actor,'items',['active'=>1],fn()=>$this->resource($issuer,$actor,'invoice',$canonical,'edit')),$submitted);
    }
    public function validateInvoiceItemValuesForCreation(int $issuer,int $actor,array $submitted):array
    {return self::validateDefined($this->invoiceItemDefinitions($issuer,$actor,null,['active'=>1],'create'),$submitted);}
    public function validateValuesForResource(int$issuer,int$actor,string$fieldto,$id,array$submitted,string$cap='edit'):array
    {return self::validateDefined($this->definitionsForResource($issuer,$actor,$fieldto,$id,['active'=>1],$cap),$submitted);}
    public function validateValuesForContactCreation(int$issuer,int$actor,int$client,array$submitted):array
    {return self::validateDefined($this->definitionsForContactCreation($issuer,$actor,$client,['active'=>1]),$submitted);}
    private static function validateDefined(array$definitions,array$submitted):array
    {
        $defs=[];foreach($definitions as$d)$defs[(string)$d['id']]=$d;
        foreach($submitted as$field=>$value)if(!isset($defs[(string)$field]))throw new DomainException('native_custom_field_denied');
        $out=[];foreach($defs as$field=>$d){if(!array_key_exists($field,$submitted)){if((int)$d['required']===1)throw new DomainException('native_custom_field_required');continue;}$out[$field]=self::value($d,$submitted[$field]);}return$out;
    }
    public function saveInOwnedTransaction(int $issuer,int $actor,string $fieldto,$id,array $submitted):void
    {
        $extra=[];
        if($fieldto==='items'){$source=NativeCompanyResourceId::decode('invoice_item',$id);if($source['source_kind']==='company_invoice_draft_line'){require_once __DIR__.'/CompanyInvoiceDrafts.php';$extra=(new CompanyInvoiceDrafts($this->db,$this->p))->tables();}else{$extra=['itemable','invoices'];}}
        IssuerTransaction::assertOwned($this->db,$this->p,array_merge($this->tableNames(),$extra));$ref=$this->resource($issuer,$actor,$fieldto,$id,'edit');
        $verify=fn()=>$this->resource($issuer,$actor,$fieldto,$id,'edit');
        $this->writeValues($issuer,$actor,$fieldto,$ref,$submitted,$verify,$this->definitionsForResource($issuer,$actor,$fieldto,$id,['active'=>1],'edit'));
    }
    /** Preserve values across an explicitly paired consecutive draft-line identity, without exposing hidden fields. */
    public function inheritDraftLineValuesInOwned(int $issuer,int $actor,string $sourceId,string $targetId):void
    {
        require_once __DIR__.'/CompanyInvoiceDrafts.php';$drafts=new CompanyInvoiceDrafts($this->db,$this->p);
        IssuerTransaction::assertOwned($this->db,$this->p,array_merge($this->tableNames(),$drafts->tables()));
        $source=NativeCompanyResourceId::decode('invoice_item',$sourceId);$target=NativeCompanyResourceId::decode('invoice_item',$targetId);
        if($source['source_kind']!=='company_invoice_draft_line'||$target['source_kind']!=='company_invoice_draft_line')throw new DomainException('native_custom_field_denied');
        $parent=$this->resource($issuer,$actor,'items',$targetId,'edit');
        $old=IssuerStorage::query($this->db,'SELECT draft_id,draft_revision FROM '.$this->p.NativeCompanyInvoiceDraftLinesSchema::TABLE.' WHERE id=? AND issuer_id=?',[$source['source_id'],$issuer])->row_array();
        if(!$old||(int)$old['draft_id']!==$parent['draft_id']||(int)$old['draft_revision']+1!==$parent['draft_revision'])throw new DomainException('native_custom_field_denied');
        (new NativeCompanyInvoiceDraftLines($this->db,$this->p))->assertInOwned($parent['draft_id']);
        $read=fn($id)=>IssuerStorage::query($this->db,"SELECT field_source_kind,field_id,value,revision FROM {$this->p}company_custom_field_values WHERE issuer_id=? AND source_kind='company_invoice_draft_line' AND source_id=? ORDER BY field_source_kind,field_id",[$issuer,$id])->result_array();
        if($read($target['source_id'])!==[])throw new DomainException('native_custom_field_revision_conflict');$values=$read($source['source_id']);
        foreach($values as$row){
            if($row['field_source_kind']!=='company_custom_field'||(int)$row['revision']<1)throw new DomainException('native_custom_field_corrupt');
            $definition=IssuerStorage::query($this->db,"SELECT definition_json FROM {$this->p}company_custom_fields WHERE issuer_id=? AND id=? AND fieldto='items'",[$issuer,$row['field_id']])->row_array();if(!$definition)throw new DomainException('native_custom_field_corrupt');$d=json_decode($definition['definition_json'],true,32,JSON_THROW_ON_ERROR);
            if(!is_array($d)||self::definition($d)!==$d)throw new DomainException('native_custom_field_corrupt');$input=in_array($d['type'],['checkbox','multiselect'],true)?($row['value']===''?[]:explode(', ',$row['value'])):$row['value'];if(self::value($d,$input)!==$row['value'])throw new DomainException('native_custom_field_corrupt');
            IssuerStorage::query($this->db,"INSERT INTO {$this->p}company_custom_field_values(issuer_id,field_source_kind,field_id,source_kind,source_id,value,revision) VALUES(?,?,?,'company_invoice_draft_line',?,?,1)",[$issuer,$row['field_source_kind'],$row['field_id'],$target['source_id'],$row['value']]);
        }
        $expected=array_map(static fn($row)=>array_replace($row,['revision'=>1]),$values);$normalize=static function($rows){foreach($rows as&$r){$r['field_id']=(int)$r['field_id'];$r['revision']=(int)$r['revision'];}return$rows;};
        if($normalize($read($target['source_id']))!==$normalize($expected)||$read($source['source_id'])!==$values||$this->resource($issuer,$actor,'items',$targetId,'edit')!==$parent)throw new RuntimeException('native_custom_field_write_unconfirmed');
        if($values){$metadata=json_encode(['source_line'=>$source['source_id'],'target_line'=>$target['source_id'],'source_revision'=>(int)$old['draft_revision'],'target_revision'=>$parent['draft_revision'],'values_hash'=>hash('sha256',json_encode($normalize($values),JSON_THROW_ON_ERROR))],JSON_THROW_ON_ERROR);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(issuer_id,actor_id,subject_id,event,metadata_json,created_at) VALUES(?,?,?,?,?,?)',[$issuer,$actor,$parent['draft_id'],'native_invoice_draft_line_fields_preserved',$metadata,gmdate('Y-m-d H:i:s')]);$audit=(int)$this->db->insert_id();$receipt=IssuerStorage::query($this->db,'SELECT issuer_id,actor_id,subject_id,event,metadata_json FROM '.$this->p.'issuers_audit WHERE id=?',[$audit])->row_array();if(!$receipt||(int)$receipt['issuer_id']!==$issuer||(int)$receipt['actor_id']!==$actor||(int)$receipt['subject_id']!==$parent['draft_id']||$receipt['event']!=='native_invoice_draft_line_fields_preserved'||$receipt['metadata_json']!==$metadata)throw new RuntimeException('native_custom_field_write_unconfirmed');}
    }
    /** Transfer reviewed source metadata only to a line attested by the exact active invoice writer. */
    public function saveCreatedInvoiceLineInOwnedTransaction(int $issuer,int $actor,int $invoice,string $requestKey,int $targetLine,string $sourceLine,CompanyInvoices $writer):void
    {
        IssuerTransaction::assertOwned($this->db,$this->p,array_merge($this->tableNames(),['invoices','itemable','item_tax','company_invoice_requests']));
        $source=$this->resource($issuer,$actor,'items',$sourceLine,'view');if($source['source_kind']!=='company_invoice_draft_line')throw new DomainException('native_custom_field_creation_denied');
        $mapping=IssuerStorage::query($this->db,'SELECT invoice_id,itemable_id FROM '.$this->p.NativeCompanyInvoiceDraftLinesSchema::TRANSFERS.' WHERE issuer_id=? AND draft_line_id=?',[$issuer,$source['source_id']])->row_array();
        if(!$mapping||(int)$mapping['invoice_id']!==$invoice||(int)$mapping['itemable_id']!==$targetLine)throw new DomainException('native_custom_field_creation_denied');
        $verify=function()use($issuer,$actor,$invoice,$requestKey,$targetLine,$writer,$sourceLine):void{
            $this->resource($issuer,$actor,'items',$sourceLine,'view');
            $proof=$writer->createdLineIdsInOwned($issuer,$actor,$invoice,$requestKey);if(!in_array($targetLine,array_column($proof,'itemable_id'),true))throw new DomainException('native_custom_field_creation_denied');
        };
        $verify();$definitions=$this->invoiceItemDefinitions($issuer,$actor,null,['active'=>1],'create');$allowed=array_fill_keys(array_map(static fn($d)=>(string)$d['id'],$definitions),true);
        $submitted=array_intersect_key($this->storedInput($issuer,$actor,'items',$sourceLine),$allowed);
        $this->writeValues($issuer,$actor,'items',['source_kind'=>'invoice_item','source_id'=>$targetLine],$submitted,$verify,$definitions);
        // Visibility controls what the actor receives/edits, not whether existing metadata survives issuance.
        $readSource=fn()=>IssuerStorage::query($this->db,"SELECT field_source_kind,field_id,value,revision FROM {$this->p}company_custom_field_values WHERE issuer_id=? AND source_kind='company_invoice_draft_line' AND source_id=? ORDER BY field_source_kind,field_id FOR UPDATE",[$issuer,$source['source_id']])->result_array();$persisted=$readSource();
        foreach($persisted as$row){
            if($row['field_source_kind']!=='company_custom_field'||(int)$row['revision']<1)throw new DomainException('native_custom_field_corrupt');
            $definition=IssuerStorage::query($this->db,"SELECT definition_json FROM {$this->p}company_custom_fields WHERE issuer_id=? AND id=? AND fieldto='items'",[$issuer,$row['field_id']])->row_array();if(!$definition)throw new DomainException('native_custom_field_corrupt');$d=json_decode($definition['definition_json'],true,32,JSON_THROW_ON_ERROR);
            if(!is_array($d)||self::definition($d)!==$d)throw new DomainException('native_custom_field_corrupt');$input=in_array($d['type'],['checkbox','multiselect'],true)?($row['value']===''?[]:explode(', ',$row['value'])):$row['value'];if(self::value($d,$input)!==$row['value'])throw new DomainException('native_custom_field_corrupt');
            $args=[$issuer,$row['field_source_kind'],$row['field_id'],$targetLine];$query="SELECT value,revision FROM {$this->p}company_custom_field_values WHERE issuer_id=? AND field_source_kind=? AND field_id=? AND source_kind='invoice_item' AND source_id=?";
            $target=IssuerStorage::query($this->db,$query,$args)->row_array();if(!$target){IssuerStorage::query($this->db,"INSERT INTO {$this->p}company_custom_field_values(issuer_id,field_source_kind,field_id,source_kind,source_id,value,revision) VALUES(?,?,?,'invoice_item',?,?,1)",array_merge($args,[$row['value']]));$target=IssuerStorage::query($this->db,$query,$args)->row_array();}
            if(!$target||$target['value']!==$row['value']||(int)$target['revision']!==1)throw new RuntimeException('native_custom_field_write_unconfirmed');
        }
        if($readSource()!==$persisted)throw new RuntimeException('native_custom_field_write_unconfirmed');
        $metadata=json_encode(['source_kind'=>$source['source_kind'],'source_id'=>$source['source_id'],'target_kind'=>'invoice_item','target_id'=>$targetLine,'invoice_id'=>$invoice,'values_hash'=>hash('sha256',json_encode($persisted,JSON_THROW_ON_ERROR))],JSON_THROW_ON_ERROR);
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(issuer_id,actor_id,subject_id,event,metadata_json,created_at) VALUES(?,?,?,?,?,?)',[$issuer,$actor,$source['draft_id'],'native_invoice_line_fields_transferred',$metadata,gmdate('Y-m-d H:i:s')]);
        $audit=(int)$this->db->insert_id();$receipt=IssuerStorage::query($this->db,'SELECT issuer_id,actor_id,subject_id,event,metadata_json FROM '.$this->p.'issuers_audit WHERE id=?',[$audit])->row_array();
        if($audit<1||!$receipt||(int)$receipt['issuer_id']!==$issuer||(int)$receipt['actor_id']!==$actor||(int)$receipt['subject_id']!==$source['draft_id']||$receipt['event']!=='native_invoice_line_fields_transferred'||$receipt['metadata_json']!==$metadata)throw new RuntimeException('native_custom_field_write_unconfirmed');$verify();
    }
    /** Internal issuance transfer preserves hidden/inactive header metadata without exposing it to the actor. */
    public function transferCreatedDraftInvoiceFieldsInOwned(int $issuer,int $actor,int $draft,int $invoice,CompanyInvoices $writer):string
    {
        IssuerTransaction::assertOwned($this->db,$this->p,array_merge($this->tableNames(),(new CompanyInvoiceDrafts($this->db,$this->p))->tables()));
        $verify=function()use($issuer,$actor,$draft,$invoice,$writer):void{
            $source=$this->resource($issuer,$actor,'invoice',NativeCompanyResourceId::encode('company_invoice_draft',$draft),'view');if($source['source_kind']!=='company_invoice_draft')throw new DomainException('native_custom_field_creation_denied');
            $writer->createdLineIdsInOwned($issuer,$actor,$invoice,CompanyInvoiceDrafts::canonicalKey($issuer,$draft));
        };$verify();
        $read=fn()=>IssuerStorage::query($this->db,"SELECT field_source_kind,field_id,value,revision FROM {$this->p}company_custom_field_values WHERE issuer_id=? AND source_kind='company_invoice_draft' AND source_id=? ORDER BY field_source_kind,field_id FOR UPDATE",[$issuer,$draft])->result_array();$source=$read();
        if(IssuerStorage::query($this->db,"SELECT field_id FROM {$this->p}company_custom_field_values WHERE issuer_id=? AND source_kind='invoice' AND source_id=?",[$issuer,$invoice])->result_array())throw new DomainException('native_custom_field_creation_denied');
        foreach($source as$row){
            if($row['field_source_kind']!=='company_custom_field'||(int)$row['revision']<1)throw new DomainException('native_custom_field_corrupt');
            $record=IssuerStorage::query($this->db,"SELECT definition_json FROM {$this->p}company_custom_fields WHERE issuer_id=? AND id=? AND fieldto='invoice'",[$issuer,$row['field_id']])->row_array();if(!$record)throw new DomainException('native_custom_field_corrupt');$definition=json_decode($record['definition_json'],true,32,JSON_THROW_ON_ERROR);
            if(!is_array($definition)||self::definition($definition)!==$definition)throw new DomainException('native_custom_field_corrupt');$input=in_array($definition['type'],['checkbox','multiselect'],true)?($row['value']===''?[]:explode(', ',$row['value'])):$row['value'];if(self::value($definition,$input)!==$row['value'])throw new DomainException('native_custom_field_corrupt');
            IssuerStorage::query($this->db,"INSERT INTO {$this->p}company_custom_field_values(issuer_id,field_source_kind,field_id,source_kind,source_id,value,revision) VALUES(?,?,?,'invoice',?,?,1)",[$issuer,$row['field_source_kind'],$row['field_id'],$invoice,$row['value']]);
        }
        $actual=IssuerStorage::query($this->db,"SELECT field_source_kind,field_id,value,revision FROM {$this->p}company_custom_field_values WHERE issuer_id=? AND source_kind='invoice' AND source_id=? ORDER BY field_source_kind,field_id",[$issuer,$invoice])->result_array();
        $expected=array_map(static function($row){$row['revision']=1;return$row;},$source);
        foreach($actual as&$row)$row['revision']=(int)$row['revision'];unset($row);
        if($actual!==$expected||$source!==$read())throw new RuntimeException('native_custom_field_write_unconfirmed');$verify();return hash('sha256',json_encode($source,JSON_THROW_ON_ERROR));
    }
    /** A creation-only capability is bound to the writer's private exact transaction frame. */
    public function saveCreatedTaskInOwnedTransaction(int$issuer,int$actor,string$source,int$id,array$submitted,object$proof):void
    {
        $verify=function()use($issuer,$actor,$source,$id,$proof):void{
            if($source==='company_task'){require_once __DIR__.'/CompanyTasks.php';CompanyTasks::assertNativeTaskCreationProof($proof,$this->db,$this->p,$issuer,$actor,$id);}
            elseif($source==='company_standalone_task'){require_once __DIR__.'/CompanyStandaloneTasks.php';CompanyStandaloneTasks::assertNativeTaskCreationProof($proof,$this->db,$this->p,$issuer,$actor,$id);}
            else throw new DomainException('native_custom_field_creation_denied');
        };
        $verify();IssuerTransaction::assertOwned($this->db,$this->p,$this->tableNames());
        $this->writeValues($issuer,$actor,'tasks',['source_kind'=>$source,'source_id'=>$id],$submitted,$verify,$this->definitions($issuer,$actor,'tasks',['active'=>1],'create'));
    }
    public function saveCreatedProjectInOwnedTransaction(int $issuer,int $actor,int $project,array $submitted,object $proof):void
    {
        $verify=function()use($issuer,$actor,$project,$proof):void{CompanyProjects::assertNativeCreationProof($proof,$this->db,$this->p,$issuer,$actor,$project);};
        $verify();
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tableNames());
        $this->writeValues($issuer,$actor,'projects',['source_kind'=>'company_project','source_id'=>$project],$submitted,$verify,$this->definitions($issuer,$actor,'projects',['active'=>1],'create'));
    }
    /** Only the real final native writer can attest its own freshly inserted resource. */
    public function saveCreatedInOwnedTransaction(int $issuer,int $actor,string $fieldto,$id,array $submitted,object $writer):void
    {
        $expected=['staff'=>['NativeCompanyStaffForm','staff'],'customers'=>['CompanyClients','client'],'contacts'=>['CompanyNativeContacts','contact'],'tickets'=>['NativeCompanyTicketCommands','company_ticket'],'invoice'=>['CompanyInvoiceDrafts','company_invoice_draft'],'items'=>['CompanyInvoiceDrafts','company_invoice_draft_line'],'estimate'=>['CompanySales','company_estimate']][$fieldto]??null;
        if(in_array($fieldto,['invoice','items'],true)&&get_class($writer)==='CompanyInvoices')$expected=['CompanyInvoices',$fieldto==='invoice'?'invoice':'invoice_item'];
        if($fieldto==='items_pr')$expected=['NativeCompanyCatalog'=>['NativeCompanyCatalog','company_item'],'NativeCompanyCatalogLegacy'=>['NativeCompanyCatalogLegacy','item']][get_class($writer)]??null;
        if(!$expected||get_class($writer)!==$expected[0])throw new DomainException('native_custom_field_creation_denied');
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tableNames());
        $verify=function()use($writer,$issuer,$actor,$expected,$id,$fieldto):void{
            if(!in_array($fieldto,['contacts','items'],true))$this->authorize($issuer,$actor,$fieldto,'create');
            $writer->assertCreatedInOwned($this->db,$this->p,$issuer,$actor,$expected[1],$id);
        };
        $verify();
        $definitions=$fieldto==='items'?$this->invoiceItemDefinitions($issuer,$actor,null,['active'=>1],'create'):($fieldto==='contacts'?$this->definitionsForContactCreation($issuer,$actor,(int)(IssuerStorage::query($this->db,'SELECT userid FROM '.$this->p.'contacts WHERE id=?',[$id])->row_array()['userid']??0),['active'=>1]):$this->definitions($issuer,$actor,$fieldto,['active'=>1],'create'));
        $this->writeValues($issuer,$actor,$fieldto,['source_kind'=>$expected[1],'source_id'=>$id],$submitted,$verify,$definitions);
    }
    /** Public registration is an explicit publication policy, never a staff capability. */
    public function definitionsForRegistration(int$issuer,string$fieldto):array
    {
        if(!in_array($fieldto,['customers','contacts'],true))throw new DomainException('native_custom_field_denied');
        require_once __DIR__.'/NativePortalPreferences.php';$preferences=new NativePortalPreferences($this->db,$this->p);
        if($preferences->publicValues($issuer)['allow_registration']!=='1')throw new DomainException('native_custom_field_denied');
        $out=$this->readPublishedDefinitions($issuer,$fieldto);
        if($preferences->publicValues($issuer)['allow_registration']!=='1')throw new DomainException('native_custom_field_denied');return$out;
    }
    private function readPublishedDefinitions(int$issuer,string$fieldto):array
    {
        NativeCompanyCustomFieldSchema::assertValid($this->db,$this->p);$out=[];
        foreach(IssuerStorage::query($this->db,'SELECT id,fieldto,slug,definition_json,revision FROM '.$this->p.'company_custom_fields WHERE issuer_id=? AND fieldto=? ORDER BY id',[$issuer,$fieldto])->result_array()as$r){
            $d=json_decode($r['definition_json'],true,32,JSON_THROW_ON_ERROR);if(!is_array($d)||self::definition($d)!==$d||$d['fieldto']!==$fieldto||(int)$r['revision']<1)throw new RuntimeException('native_custom_field_corrupt');
            $out[]=$d+['id'=>NativeCompanyResourceId::encode('company_custom_field',$r['id']),'slug'=>$r['slug'],'revision'=>(int)$r['revision'],'field_source_kind'=>'company_custom_field','source_field_id'=>(int)$r['id']];
        }
        if((new CompanySettingsStorage($this->db,$this->p))->isPrimary($issuer))foreach(IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.'customfields WHERE fieldto=? ORDER BY field_order,id',[$fieldto])->result_array()as$r){$r['source_field_id']=(int)$r['id'];$r['field_source_kind']='custom_field';$r['revision']=null;$out[]=$r;}
        $out=array_values(array_filter($out,static fn($d)=>(int)$d['active']===1&&(int)$d['only_admin']===0&&(int)$d['show_on_client_portal']===1));
        usort($out,static fn($a,$b)=>[(int)$a['field_order'],(string)$a['id']]<=>[(int)$b['field_order'],(string)$b['id']]);
        return$out;
    }
    public function definitionsForPortal(int$issuer,string$fieldto,object$writer,bool$editable=false):array
    {
        if(!((get_class($writer)==='NativePortalProfile'&&in_array($fieldto,['customers','contacts'],true))||(get_class($writer)==='NativePortalContacts'&&$fieldto==='contacts')))throw new DomainException('native_custom_field_denied');
        $writer->reference($this->db,$this->p,$issuer,$fieldto,$editable);$out=$this->readPublishedDefinitions($issuer,$fieldto);
        if($editable)$out=array_values(array_filter($out,static fn($d)=>(int)$d['disalow_client_to_edit']===0));
        $writer->reference($this->db,$this->p,$issuer,$fieldto,$editable);return$out;
    }
    public function definitionsForManagedContactCreation(int$issuer,object$writer):array
    {
        if(get_class($writer)!=='NativePortalContacts')throw new DomainException('native_custom_field_denied');
        $writer->assertCreationAuthority($this->db,$this->p,$issuer);
        $out=array_values(array_filter($this->readPublishedDefinitions($issuer,'contacts'),static fn($d)=>(int)$d['disalow_client_to_edit']===0));
        $writer->assertCreationAuthority($this->db,$this->p,$issuer);return$out;
    }
    public function valuesForPortal(int$issuer,string$fieldto,object$writer):array
    {
        $defs=$this->definitionsForPortal($issuer,$fieldto,$writer);$ref=$writer->reference($this->db,$this->p,$issuer,$fieldto,false);$out=[];
        foreach($defs as$d){$row=IssuerStorage::query($this->db,'SELECT value FROM '.$this->p.'company_custom_field_values WHERE issuer_id=? AND field_source_kind=? AND field_id=? AND source_kind=? AND source_id=?',[$issuer,$d['field_source_kind'],$d['source_field_id'],$ref['source_kind'],$ref['source_id']])->row_array();
            if(!$row&&$d['field_source_kind']==='custom_field'&&(new CompanySettingsStorage($this->db,$this->p))->isPrimary($issuer))$row=IssuerStorage::query($this->db,'SELECT value FROM '.$this->p.'customfieldsvalues WHERE fieldid=? AND fieldto=? AND relid=?',[$d['source_field_id'],$fieldto,$ref['source_id']])->row_array();
            if($row)$out[(string)$d['id']]=(string)$row['value'];
        }$writer->reference($this->db,$this->p,$issuer,$fieldto,false);return$out;
    }
    public function savePortalInOwnedTransaction(int$issuer,string$fieldto,array$submitted,object$writer):void
    {
        if(!((get_class($writer)==='NativePortalProfile'&&in_array($fieldto,['customers','contacts'],true))||(get_class($writer)==='NativePortalContacts'&&$fieldto==='contacts')))throw new DomainException('native_custom_field_denied');
        IssuerTransaction::assertOwned($this->db,$this->p,['issuers_lock','company_custom_fields','company_custom_field_values']);
        $writer->assertMutationInOwned($this->db,$this->p,$issuer,$fieldto);$ref=$writer->reference($this->db,$this->p,$issuer,$fieldto,true);$defs=$this->definitionsForPortal($issuer,$fieldto,$writer,true);
        $this->writeVerifiedValues($issuer,$fieldto,$ref,$submitted,fn()=>$writer->assertMutationInOwned($this->db,$this->p,$issuer,$fieldto),$defs);
    }
    public function validateRegistration(int$issuer,string$fieldto,array$submitted):array
    {return self::validateDefined($this->definitionsForRegistration($issuer,$fieldto),$submitted);}
    /** Only the final public writer can attest both freshly inserted identities in its owned transaction. */
    public function saveRegisteredInOwnedTransaction(int$issuer,string$fieldto,int$id,array$submitted,object$writer):void
    {
        if(get_class($writer)!=='NativePortalRegistration'||!in_array($fieldto,['customers','contacts'],true))throw new DomainException('native_custom_field_creation_denied');
        IssuerTransaction::assertOwned($this->db,$this->p,['issuers_lock','company_custom_fields','company_custom_field_values']);
        $kind=$fieldto==='customers'?'client':'contact';$writer->assertRegisteredInOwned($this->db,$this->p,$issuer,$kind,$id);
        $definitions=$this->definitionsForRegistration($issuer,$fieldto);$values=self::validateDefined($definitions,$submitted);
        foreach($definitions as$d){$key=(string)$d['id'];if(!array_key_exists($key,$values))continue;
            $args=[$issuer,$d['field_source_kind'],$d['source_field_id'],$kind,$id];
            if(IssuerStorage::query($this->db,'SELECT revision FROM '.$this->p.'company_custom_field_values WHERE issuer_id=? AND field_source_kind=? AND field_id=? AND source_kind=? AND source_id=?',$args)->row_array())throw new DomainException('native_custom_field_creation_denied');
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_custom_field_values(issuer_id,field_source_kind,field_id,source_kind,source_id,value,revision) VALUES(?,?,?,?,?,?,1)',array_merge($args,[$values[$key]]));
            $row=IssuerStorage::query($this->db,'SELECT value,revision FROM '.$this->p.'company_custom_field_values WHERE issuer_id=? AND field_source_kind=? AND field_id=? AND source_kind=? AND source_id=?',$args)->row_array();
            if(!$row||(string)$row['value']!==$values[$key]||(int)$row['revision']!==1)throw new RuntimeException('native_custom_field_write_unconfirmed');
        }
        $writer->assertRegisteredInOwned($this->db,$this->p,$issuer,$kind,$id);
    }
    private function writeValues(int $issuer,int $actor,string $fieldto,array $ref,array $submitted,callable $verify,array $definitions):void
    {
        $this->writeVerifiedValues($issuer,$fieldto,$ref,$submitted,$verify,$definitions);
    }
    /** Shared storage primitive; authority is supplied only by closed staff/contact callers above. */
    private function writeVerifiedValues(int$issuer,string$fieldto,array$ref,array$submitted,callable$verify,array$definitions):void
    {
        $verify();$defs=[];foreach($definitions as$d)$defs[(string)$d['id']]=$d;
        foreach($submitted as$field=>$v)if(!isset($defs[(string)$field]))throw new DomainException('native_custom_field_denied');
        $expected=[];
        foreach($defs as$field=>$d){if(!array_key_exists($field,$submitted)){if((int)$d['required']===1)throw new DomainException('native_custom_field_required');continue;}$v=self::value($d,$submitted[$field]);$args=[$issuer,$d['field_source_kind'],$d['source_field_id'],$ref['source_kind'],$ref['source_id']];
            $old=IssuerStorage::query($this->db,'SELECT value,revision FROM '.$this->p.'company_custom_field_values WHERE issuer_id=? AND field_source_kind=? AND field_id=? AND source_kind=? AND source_id=? FOR UPDATE',$args)->row_array();
            if($old&&(string)$old['value']===$v){$expected[]=[$args,$v,(int)$old['revision']];continue;}
            $revision=$old?(int)$old['revision']+1:1;if($revision>2147483647)throw new DomainException('native_custom_field_revision_overflow');
            if($old){IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_custom_field_values SET value=?,revision=? WHERE issuer_id=? AND field_source_kind=? AND field_id=? AND source_kind=? AND source_id=? AND revision=?',array_merge([$v,$revision],$args,[(int)$old['revision']]));}
            else{IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_custom_field_values (issuer_id,field_source_kind,field_id,source_kind,source_id,value,revision) VALUES (?,?,?,?,?,?,?)',array_merge($args,[$v,$revision]));}
            $expected[]=[$args,$v,$revision];
        }
        $verify();
        foreach($expected as[$args,$v,$revision]){$row=IssuerStorage::query($this->db,'SELECT value,revision FROM '.$this->p.'company_custom_field_values WHERE issuer_id=? AND field_source_kind=? AND field_id=? AND source_kind=? AND source_id=?',$args)->row_array();if(!$row||(string)$row['value']!==$v||(int)$row['revision']!==$revision)throw new RuntimeException('native_custom_field_write_unconfirmed');}
    }
}
