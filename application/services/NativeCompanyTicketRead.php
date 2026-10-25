<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTicketPreferences.php';
require_once __DIR__.'/NativeCompanyDepartments.php';
require_once __DIR__.'/NativeCompanyTicketReplySchema.php';
/** Ticket authority precedes subject/body/contact hydration, including generic relation consumers. */
final class NativeCompanyTicketRead
{
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public function tables():array{return array_values(array_unique(array_merge((new NativeCompanyTicketPreferences($this->db,$this->p))->tables(),array_keys(NativeCompanyDepartmentSchema::definitions($this->p)),array_keys(NativeCompanyTicketCatalogSchema::definitions($this->p)),[NativeCompanyTicketReplySchema::BODY,'company_tickets','company_ticket_replies','tickets','ticket_replies','issuers_clients','issuers_client_links','clients','contacts','projects','company_projects','departments','staff_departments'])));}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function access(int $issuer,int $actor):IssuerResourceAccess{$acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($issuer,$actor,'tickets','view'))throw new DomainException('native_ticket_denied');return$acl;}
    private function client(int $issuer,?int $client,IssuerResourceAccess $acl,bool $owned):void
    {
        if($client===null)return;
        $owner=$this->rows('SELECT owner_issuer_id FROM '.$this->p.'issuers_clients WHERE client_id=?',[$client])[0]??null;
        if(!$owned){if($acl->primaryId()!==$issuer||$owner!==null)throw new DomainException('native_ticket_denied');}
        elseif($owner!==null&&(int)$owner['owner_issuer_id']!==$issuer&&!$this->rows('SELECT client_id FROM '.$this->p.'issuers_client_links WHERE issuer_id=? AND client_id=?',[$issuer,$client]))throw new DomainException('native_ticket_denied');
        elseif($owner===null&&$acl->primaryId()!==$issuer)throw new DomainException('native_ticket_denied');
        if(!$this->rows('SELECT userid FROM '.$this->p.'clients WHERE userid=?',[$client]))throw new DomainException('native_ticket_denied');
    }
    private function authority(int $issuer,int $actor,$id,bool $referenceOnly=false):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$acl=$this->access($issuer,$actor);$lineage=$acl->primaryId();$d=NativeCompanyResourceId::decode('ticket',$id);$owned=$d['source_kind']==='company_ticket';
        if(!$owned&&$lineage!==$issuer)throw new DomainException('native_ticket_denied');
        $row=$owned?($this->rows('SELECT id,issuer_id,client_id,assignee_id FROM '.$this->p.'company_tickets WHERE issuer_id=? AND id=?',[$issuer,$d['source_id']])[0]??null):($this->rows('SELECT ticketid,userid,contactid,department FROM '.$this->p.'tickets WHERE ticketid=?',[$d['source_id']])[0]??null);
        if(!$row)throw new DomainException('native_ticket_denied');$client=$owned?($row['client_id']===null?null:(int)$row['client_id']):((int)$row['userid']>0?(int)$row['userid']:null);$this->client($issuer,$client,$acl,$owned);
        $metaTable=$owned?NativeCompanyDepartmentSchema::TICKETS:NativeCompanyDepartmentSchema::LEGACY_TICKETS;
        $meta=$this->rows('SELECT department_source_kind,department_source_id'.($owned?',contact_id,project_source_kind,project_source_id':'').' FROM '.$this->p.$metaTable.' WHERE issuer_id=? AND ticket_id=?',[$issuer,$d['source_id']])[0]??null;
        if($owned&&$meta){
            if($meta['contact_id']!==null){$contact=$this->rows('SELECT userid FROM '.$this->p.'contacts WHERE id=?',[(int)$meta['contact_id']])[0]??null;if(!$contact||$client===null||(int)$contact['userid']!==$client)throw new DomainException('native_ticket_denied');}
            if($meta['project_source_id']!==null){
                $kind=$meta['project_source_kind'];if(!in_array($kind,['project','company_project'],true)||$client===null||($kind==='project'&&$lineage!==$issuer))throw new DomainException('native_ticket_denied');
                $project=$this->rows('SELECT '.($kind==='company_project'?'client_id,issuer_id':'clientid').' FROM '.$this->p.($kind==='company_project'?'company_projects':'projects').' WHERE id=?',[(int)$meta['project_source_id']])[0]??null;
                if(!$project||($kind==='company_project'&&((int)$project['issuer_id']!==$issuer||(int)$project['client_id']!==$client))||($kind==='project'&&(int)$project['clientid']!==$client))throw new DomainException('native_ticket_denied');
            }elseif($meta['project_source_kind']!=='')throw new DomainException('native_ticket_denied');
        }
        $department=$meta?NativeCompanyResourceId::encode($meta['department_source_kind'],(int)$meta['department_source_id']):(!$owned&&(int)$row['department']>0?NativeCompanyResourceId::encode('ticket_department',(int)$row['department']):null);
        $policy=$referenceOnly?$this->referencePolicy($issuer):(new NativeCompanyTicketPreferences($this->db,$this->p))->readInOwnedTransaction($issuer,$actor);$staff=$this->rows('SELECT active,is_not_staff FROM '.$this->p.'staff WHERE staffid=?',[$actor])[0]??null;if(!$staff||(int)$staff['active']!==1||($policy['values']['access_tickets_to_none_staff_members']==='0'&&(int)$staff['is_not_staff']!==0))throw new DomainException('native_ticket_denied');$departments=new NativeCompanyDepartments($this->db,$this->p);
        if($department!==null)$departments->get($issuer,$actor,$department);
        if(!$acl->administrative($issuer,$actor)&&$policy['values']['staff_access_only_assigned_departments']==='1'&&($department===null||!in_array($department,array_column($departments->forStaff($issuer,$actor),'departmentid'),true)))throw new DomainException('native_ticket_department_denied');
        if(!$owned&&(int)$row['contactid']>0){$contact=$this->rows('SELECT userid FROM '.$this->p.'contacts WHERE id=?',[(int)$row['contactid']])[0]??null;if(!$contact||$client===null||(int)$contact['userid']!==$client)throw new DomainException('native_ticket_denied');}
        $this->access($issuer,$actor);if($lineage!==$acl->primaryId())throw new DomainException('native_ticket_denied');return compact('d','owned','row','client','metaTable','department','policy','lineage');
    }
    /** References need department authority, not the full presentation settings.
     * Read again on every authority check: no object/global/transaction cache can mask revocation.
     */
    private function referencePolicy(int $issuer):array
    {
        $store=new CompanySettingsStorage($this->db,$this->p);$values=[];
        foreach(['staff_access_only_assigned_departments','access_tickets_to_none_staff_members']as$key){
            $value=$store->exists($issuer,$key)?$store->get($issuer,$key):NativeCompanyTicketPreferences::defaultValue($key);
            if(!in_array($value,['0','1'],true))throw new DomainException('native_ticket_preferences_invalid');$values[$key]=$value;
        }
        return ['values'=>$values];
    }
    public function referenceInOwnedTransaction(int $issuer,int $actor,$id):array
    {
        $a=$this->authority($issuer,$actor,$id,true);$table=$a['owned']?'company_tickets':'tickets';$key=$a['owned']?'id':'ticketid';
        $row=$this->rows('SELECT subject FROM '.$this->p.$table.' WHERE '.$key.'=?'.($a['owned']?' AND issuer_id=?':''),$a['owned']?[$a['d']['source_id'],$issuer]:[$a['d']['source_id']])[0]??null;
        if(!$row||$a!==$this->authority($issuer,$actor,$id,true))throw new DomainException('native_ticket_denied');$canonical=NativeCompanyResourceId::encode($a['d']['source_kind'],$a['d']['source_id']);
        return ['source_kind'=>$a['d']['source_kind'],'source_id'=>$a['d']['source_id'],'feature'=>'tickets','name'=>$row['subject'],'url'=>'tickets/ticket/'.$canonical,'canonical_id'=>$canonical];
    }
    public function reference(int $issuer,int $actor,$id):array{return IssuerTransaction::run($this->db,$this->p,$this->tables(),fn()=>$this->referenceInOwnedTransaction($issuer,$actor,$id));}
    public function get(int $issuer,int $actor,$id):array{return IssuerTransaction::run($this->db,$this->p,$this->tables(),fn()=>$this->getInOwnedTransaction($issuer,$actor,$id));}
    public function getInOwnedTransaction(int $issuer,int $actor,$id):array
    {
        $a=$this->authority($issuer,$actor,$id);$owned=$a['owned'];$table=$owned?'company_tickets':'tickets';$key=$owned?'id':'ticketid';$source=$a['d']['source_id'];
        $row=$this->rows('SELECT * FROM '.$this->p.$table.' WHERE '.$key.'=?'.($owned?' AND issuer_id=?':''),$owned?[$source,$issuer]:[$source])[0]??null;
        if(!$row)throw new DomainException('native_ticket_denied');$metadata=$this->rows('SELECT * FROM '.$this->p.$a['metaTable'].' WHERE issuer_id=? AND ticket_id=?',[$issuer,$source])[0]??null;
        $refs=$this->rows('SELECT kind,source_kind,source_id FROM '.$this->p.($owned?NativeCompanyTicketCatalogSchema::REFERENCES:NativeCompanyTicketCatalogSchema::LEGACY_REFERENCES).' WHERE issuer_id=? AND ticket_id=? ORDER BY kind',[$issuer,$source]);$catalog=new NativeCompanyTicketCatalog($this->db,$this->p);$resolved=[];
        foreach($refs as$ref){$kind=$ref['kind'];if(!in_array($kind,['status','priority','service'],true)||isset($resolved[$kind]))throw new DomainException('native_ticket_reference_invalid');$resolved[$kind]=$catalog->get($issuer,$actor,$kind,NativeCompanyResourceId::encode($ref['source_kind'],(int)$ref['source_id']));}
        if(!$owned)foreach(['status','priority','service']as$kind)if(!isset($resolved[$kind])&&(int)$row[$kind]>0)$resolved[$kind]=$catalog->get($issuer,$actor,$kind,(string)$row[$kind]);
        if($owned&&($row['status']==='native'||$row['priority']==='native')&&(!$metadata||!isset($resolved['status'],$resolved['priority'])))throw new DomainException('native_ticket_reference_invalid');
        if($owned&&$metadata&&$metadata['contact_id']!==null){$contact=$this->rows('SELECT userid FROM '.$this->p.'contacts WHERE id=?',[(int)$metadata['contact_id']])[0]??null;if(!$contact||$a['client']===null||(int)$contact['userid']!==$a['client'])throw new DomainException('native_ticket_denied');}
        if($owned&&$a['client']===null&&$metadata&&($metadata['contact_id']!==null||$metadata['project_source_id']!==null))throw new DomainException('native_ticket_reference_invalid');
        $bodyProof=$owned?($this->rows('SELECT * FROM '.$this->p.NativeCompanyTicketReplySchema::BODY.' WHERE ticket_id=?',[$source])[0]??null):null;
        if($bodyProof&&((int)$bodyProof['issuer_id']!==$issuer||$bodyProof['body_format']!=='native_html'||(int)$bodyProof['revision']<1||!hash_equals((string)$bodyProof['content_hash'],hash('sha256',$row['body']))))throw new DomainException('native_ticket_body_provenance_invalid');
        $plain=$owned&&!$bodyProof&&$row['status']!=='native';
        $canonical=NativeCompanyResourceId::encode($a['d']['source_kind'],$source);$revisionMetadata=$metadata;if($revisionMetadata!==null)unset($revisionMetadata['admin_read'],$revisionMetadata['staff_replying'],$revisionMetadata['staff_replying_at']);$snapshot=[$row,$revisionMetadata,$refs,$bodyProof];
        $out=['ticketid'=>$canonical,'source_kind'=>$a['d']['source_kind'],'source_id'=>$source,'subject'=>$row['subject'],'message'=>$plain?$row['body']:NativeCompanyContentHtml::purify($owned?$row['body']:$row['message']),'message_format'=>$plain?'plain_text':'native_html',
            'userid'=>$a['client'],'contactid'=>$owned?($metadata['contact_id']??null):((int)$row['contactid']?:null),'department'=>$a['department'],'status'=>$resolved['status']['native_id']??null,'priority'=>$resolved['priority']['native_id']??null,'service'=>$resolved['service']['native_id']??null,
            'catalog'=>$resolved,'historical_status'=>$owned&&!isset($resolved['status'])?$row['status']:null,'historical_priority'=>$owned&&!isset($resolved['priority'])?$row['priority']:null,
            'ticket_email'=>$owned?($metadata['sender_email']??null):$row['email'],'submitter'=>$owned?($metadata['sender_name']??null):$row['name'],'sender_origin'=>$owned?($metadata['sender_origin']??'historical_customer'):'legacy_ticket',
            'assigned'=>(int)$row[$owned?'assignee_id':'assigned'],'date'=>$row[$owned?'created_at':'date'],'revision_token'=>hash('sha256',json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)),
            'policy'=>$a['policy'],'metadata'=>$metadata];
        if($a!==$this->authority($issuer,$actor,$id))throw new DomainException('native_ticket_denied');return$out;
    }
    /** Same canonical reply rows. Contact origin and new rich-text provenance are explicit, never inferred from actor zero. */
    public function replies(int $issuer,int $actor,$id):array
    {
        require_once __DIR__.'/NativeCompanyTicketReplySchema.php';NativeCompanyTicketReplySchema::assertValid($this->db,$this->p);
        $tables=array_merge($this->tables(),[NativeCompanyTicketReplySchema::TABLE,NativeCompanyTicketReplySchema::EDIT,'company_portal_support_replies']);
        return IssuerTransaction::run($this->db,$this->p,$tables,fn()=>$this->repliesInOwnedTransaction($issuer,$actor,$id));
    }
    public function repliesInOwnedTransaction(int $issuer,int $actor,$id):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,array_merge($this->tables(),[NativeCompanyTicketReplySchema::TABLE,NativeCompanyTicketReplySchema::EDIT,'company_portal_support_replies']));
            $before=$this->getInOwnedTransaction($issuer,$actor,$id);if($before['source_kind']!=='company_ticket')throw new DomainException('native_ticket_denied');$out=[];
            $rows=$this->rows('SELECT * FROM '.$this->p.'company_ticket_replies WHERE issuer_id=? AND ticket_id=? ORDER BY created_at,id',[$issuer,$before['source_id']]);
            foreach($rows as$row){
                $meta=$this->rows('SELECT * FROM '.$this->p.NativeCompanyTicketReplySchema::TABLE.' WHERE reply_id=?',[$row['id']])[0]??null;
                $contact=$this->rows('SELECT * FROM '.$this->p.'company_portal_support_replies WHERE reply_id=?',[$row['id']])[0]??null;
                $edit=$this->rows('SELECT * FROM '.$this->p.NativeCompanyTicketReplySchema::EDIT.' WHERE reply_id=?',[$row['id']])[0]??null;
                $staff=(int)$row['actor_id'];$format='plain_text';
                if($meta){if((int)$meta['issuer_id']!==$issuer||(int)$meta['ticket_id']!==$before['source_id']||(int)$meta['actor_id']!==$staff||$staff<1||$contact||$meta['body_format']!=='native_html'||(int)$meta['revision']<1)throw new DomainException('native_ticket_reply_provenance_invalid');$format='native_html';}
                if($contact){
                    if($staff!==0||(int)$contact['issuer_id']!==$issuer||(int)$contact['ticket_id']!==$before['source_id']||$before['userid']===null||(int)$contact['client_id']!==$before['userid'])throw new DomainException('native_ticket_reply_provenance_invalid');
                    $identity=$this->rows('SELECT id FROM '.$this->p.'contacts WHERE id=? AND userid=?',[$contact['contact_id'],$contact['client_id']])[0]??null;if(!$identity)throw new DomainException('native_ticket_reply_provenance_invalid');
                    $name=(string)$contact['author_name'];$contactId=(int)$contact['contact_id'];
                }else{
                    if($staff<1)throw new DomainException('native_ticket_reply_provenance_invalid');
                    $identity=$this->rows('SELECT firstname,lastname FROM '.$this->p.'staff WHERE staffid=?',[$staff])[0]??null;if(!$identity)throw new DomainException('native_ticket_reply_provenance_invalid');$name=trim($identity['firstname'].' '.$identity['lastname']);$contactId=null;
                }
                if($edit){if((int)$edit['issuer_id']!==$issuer||(int)$edit['ticket_id']!==$before['source_id']||(int)$edit['actor_id']<1||(int)$edit['revision']<1||!hash_equals((string)$edit['content_hash'],hash('sha256',$row['body'])))throw new DomainException('native_ticket_reply_provenance_invalid');$format='native_html';}
                $message=$format==='native_html'?NativeCompanyContentHtml::purify($row['body']):$row['body'];
                $out[]=['id'=>NativeCompanyResourceId::encode('company_ticket_reply',(int)$row['id']),'source_id'=>(int)$row['id'],'ticketid'=>$before['ticketid'],'admin'=>$staff?:null,'userid'=>$contact?$before['userid']:null,'contactid'=>$contactId,'submitter'=>$name,'message'=>$message,'message_format'=>$format,'date'=>$row['created_at'],'revision_token'=>hash('sha256',json_encode([$row,$meta,$contact,$edit],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))];
            }
            if($this->getInOwnedTransaction($issuer,$actor,$id)!==$before)throw new DomainException('native_ticket_changed');
            return $before['policy']['values']['ticket_replies_order']==='desc'?array_reverse($out):$out;
    }
    public function relationOptions(int $issuer,int $actor,string $search='', $selected=null):array
    {
        if(strlen($search)>200||preg_match('//u',$search)!==1||preg_match('/[\x00-\x1f\x7f]/',$search))throw new DomainException('native_ticket_input_invalid');
        return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($issuer,$actor,$search,$selected){
            $acl=$this->access($issuer,$actor);if($selected!==null&&$selected!==''){$r=$this->referenceInOwnedTransaction($issuer,$actor,$selected);return[['id'=>$r['canonical_id'],'name'=>$r['name'],'subtext'=>'']];}
            $lineage=$acl->primaryId();$sources=[['company_tickets','id','company_ticket','issuer_id=?',[$issuer]]];
            if($lineage===$issuer){
                require_once __DIR__.'/CustomerRelationBoundary.php';
                $sources[]=['tickets','ticketid','ticket',\app\services\CustomerRelationBoundary::sql($this->db,$this->p,'tickets','t'),[]];
            }
            // Predicate matching is performed by SQL but candidate rows contain IDs only.
            // Private subjects are hydrated exclusively by referenceInOwnedTransaction after authority.
            $needle='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';$out=[];
            foreach($sources as[$table,$key,$kind,$boundary,$params]){
                $last=0;
                do{
                    $args=array_merge($params,[$last]);$where='('.$boundary.') AND t.'.$key.'>?';
                    if($search!==''){$where.=" AND t.subject LIKE ? ESCAPE '!'";$args[]=$needle;}
                    $rows=$this->rows('SELECT t.'.$key.' AS candidate_id FROM '.$this->p.$table.' t WHERE '.$where.' ORDER BY t.'.$key.' LIMIT 100',$args);
                    foreach($rows as$row){
                        $last=(int)$row['candidate_id'];$id=NativeCompanyResourceId::encode($kind,$last);
                        try{$r=$this->referenceInOwnedTransaction($issuer,$actor,$id);}
                        catch(DomainException$e){if($e->getMessage()==='native_ticket_department_denied')continue;throw$e;}
                        if($search!==''&&mb_stripos($r['name'],$search,0,'UTF-8')===false)continue;
                        $out[]=['id'=>$r['canonical_id'],'name'=>$r['name'],'subtext'=>''];
                        if(count($out)===50)break 3;
                    }
                }while(count($rows)===100);
            }
            $this->access($issuer,$actor);if($acl->primaryId()!==$lineage)throw new DomainException('native_ticket_denied');return$out;
        });
    }
}
