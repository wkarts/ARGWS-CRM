<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyInvoices.php';
require_once __DIR__.'/NativeCompanyResourceReferences.php';
require_once __DIR__.'/NativeCompanyInvoiceRules.php';
require_once __DIR__.'/CompanySettingsCatalog.php';

/** Read adapter for the native invoice table. Returns data, never HTML or SQL from clients. */
final class NativeCompanyInvoiceRead
{
    private CompanyInvoices $invoices;
    public function __construct(private $db, private string $prefix)
    {
        $this->invoices = new CompanyInvoices($db, $prefix);
    }

    private function authorize(int $issuer, int $actor): void
    {
        if (!(new IssuerResourceAccess($this->db, $this->prefix))->can($issuer, $actor, 'invoices', 'view')) {
            throw new DomainException('company_invoice_access_denied');
        }
    }

    private static function integer($value, int $minimum, int $maximum): int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^(?:0|[1-9][0-9]*)$/D', (string)$value)
            || strlen((string)$value)>strlen((string)$maximum)
            || (strlen((string)$value)===strlen((string)$maximum)&&strcmp((string)$value,(string)$maximum)>0)
            || (int)$value < $minimum || (int)$value > $maximum) {
            throw new DomainException('native_company_invoice_query_invalid');
        }
        return (int)$value;
    }

    /** Canonical query contract. The HTTP parser must reject unsupported native filters, not drop them. */
    public static function query(array $input): array
    {
        if (array_diff(array_keys($input), ['draw','start','length','search','order','direction','status','year','client_id','filters','orders'])) {
            throw new DomainException('native_company_invoice_query_invalid');
        }
        $query = [
            'draw'=>self::integer($input['draw'] ?? 0, 0, PHP_INT_MAX),
            'start'=>self::integer($input['start'] ?? 0, 0, PHP_INT_MAX),
            'length'=>in_array($input['length']??null,[-1,'-1'],true)?-1:self::integer($input['length'] ?? 25, 1, PHP_INT_MAX),
            'search'=>$input['search'] ?? '', 'order'=>$input['order'] ?? 'number',
            'direction'=>$input['direction'] ?? 'desc', 'status'=>null, 'year'=>null, 'client_id'=>null,
            'filters'=>$input['filters']??['match_type'=>'and','rules'=>[]], 'orders'=>$input['orders']??null,
        ];
        if (!is_array($query['filters']) || !is_string($query['search']) || strlen($query['search']) > 191
            || preg_match('//u', $query['search']) !== 1 || preg_match('/[\x00-\x1f\x7f]/', $query['search'])
            || (!in_array($query['order'], ['number','total','total_tax','year','date','customer','due_date','status','project','tags'], true)&&(!is_string($query['order'])||!preg_match('/^cf_[1-9][0-9]{0,15}$/D',$query['order'])))
            || !in_array($query['direction'], ['asc','desc'], true)) {
            throw new DomainException('native_company_invoice_query_invalid');
        }
        if($query['orders']===null)$query['orders']=[['field'=>$query['order'],'direction'=>$query['direction']]];
        if(!is_array($query['orders'])||!array_is_list($query['orders'])||!$query['orders']||count($query['orders'])>100)throw new DomainException('native_company_invoice_query_invalid');
        $seen=[];foreach($query['orders']as$order){
            if(!is_array($order)||count($order)!==2||!is_string($order['field']??null)||(!in_array($order['field'],['number','total','total_tax','year','date','customer','due_date','status','project','tags'],true)&&!preg_match('/^cf_[1-9][0-9]{0,15}$/D',$order['field']))
                ||!in_array($order['direction']??null,['asc','desc'],true)||isset($seen[$order['field']]))throw new DomainException('native_company_invoice_query_invalid');
            $seen[$order['field']]=true;
        }
        foreach (['status'=>[1,6], 'year'=>[1000,9999], 'client_id'=>[1,999999999]] as $key=>$range) {
            if (array_key_exists($key, $input) && $input[$key] !== null) { $query[$key] = self::integer($input[$key], ...$range); }
        }
        return $query;
    }

    private function rows(string $sql, array $params): array
    {
        return IssuerStorage::query($this->db, $sql, $params)->result_array();
    }

    private function ruleDependencies(int $issuer,int $actor,array $compiled):array
    {
        $frame=[];
        if(in_array('sale_agents',$compiled['dependencies'],true)){
            foreach($compiled['builder']['rules']as$rule)if($rule['id']==='sale_agent'&&$rule['value']!==null&&$rule['value']!=='0'){
                if(!(new IssuerResourceAccess($this->db,$this->prefix))->member($issuer,(int)$rule['value']))throw new DomainException('native_company_invoice_filter_reference_denied');
            }
        }
        if(in_array('recurrence',$compiled['dependencies'],true)){
            require_once __DIR__.'/CompanyInvoiceRecurring.php';
            require_once __DIR__.'/CompanyInvoiceRecurringModes.php';
            CompanyInvoiceRecurringSchema::assertValid($this->db,$this->prefix);
            CompanyInvoiceRecurringModesSchema::assertValid($this->db,$this->prefix);
            $frame=$this->rows('SELECT * FROM '.$this->prefix.'company_invoice_schedule_modes WHERE issuer_id=? ORDER BY schedule_id',[$issuer]);
            if(count($frame)>10000)throw new DomainException('native_company_invoice_filter_limit');
            $service=new CompanyInvoiceRecurring($this->db,$this->prefix);
            foreach($frame as$mode){
                // Existing reader checks schedule/hash/audit/mode/principal provenance, without running a worker.
                $document=$service->document($issuer,$actor,$mode['schedule_id']);
                if((int)$document['issuer_id']!==$issuer||($document['mode']['principal_invoice_id']??null)!==($mode['principal_invoice_id']===null?null:(int)$mode['principal_invoice_id']))throw new DomainException('native_company_invoice_read_changed');
            }
        }
        return $frame;
    }

    public function page($issuer, $actor, array $input = [], array $scope = []): array
    {
        $issuer = IssuerPolicy::id($issuer); $actor = IssuerPolicy::id($actor);
        $query = self::query($input); $this->authorize($issuer, $actor);
        $related=$this->relatedScope($issuer,$actor,$scope);
        return $this->tablePage($issuer,$actor,$query,$scope,$related);
    }

    /** Original relation dropdown wire; IDs remain canonical and labels use native presentation. */
    public function options($issuer,$actor,string $search='', $selected=null):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$this->authorize($issuer,$actor);
        $query=self::query(['search'=>$search,'length'=>50,'order'=>'number','direction'=>'desc']);
        $legacySelection=null;$legacyCustomerReference=null;
        require_once __DIR__.'/NativeCompanyInvoiceIdentity.php';require_once __DIR__.'/SalesNumberPresentation.php';
        if($selected!==null&&$selected!==''){
            $source=NativeCompanyResourceId::decode('invoice',$selected);
            if($source['source_kind']==='company_invoice_draft'){
                require_once __DIR__.'/CompanyInvoiceDrafts.php';$draft=(new CompanyInvoiceDrafts($this->db,$this->prefix))->document($issuer,$actor,$source['source_id']);
                if($draft['state']!=='open')throw new DomainException('company_invoice_draft_unavailable');
                $documents=[['invoice_id'=>$source['canonical_id'],'lineage'=>'private_draft','draft'=>$draft]];
            }else{
                $identity=(new NativeCompanyInvoiceIdentity($this->db,$this->prefix))->resolve($issuer,$actor,$source['source_id']);
                if($identity['lineage']==='named_company')$documents=[$this->invoices->document($issuer,$actor,$source['source_id'])->data()+['lineage'=>'named_company']];
                else{
                    $record=$this->rows('SELECT id,clientid,number,prefix,number_format,date,status,deleted_customer_name FROM '.$this->prefix.'invoices WHERE id=? AND issuer_id IS NULL',[$source['source_id']])[0]??null;
                    if(!$record)throw new DomainException('native_company_invoice_read_changed');$label=(string)($record['deleted_customer_name']??'');
                    if($label===''){try{$client=NativeCompanyResourceReferences::resolve($this->db,$this->prefix,$issuer,$actor,'client',$record['clientid']);$label=$client['name'];$legacyCustomerReference=$client;}catch(DomainException$e){if($e->getMessage()!=='native_resource_reference_denied')throw$e;}}
                    $documents=[['invoice_id'=>$source['canonical_id'],'lineage'=>'native_legacy_null','native_record'=>$record,'customer_label'=>$label]];
                    $legacySelection=[$source['source_id'],$identity,$record];
                }
            }
        }else{$page=$this->page($issuer,$actor,['search'=>$query['search'],'length'=>50,'order'=>'number','direction'=>'desc']);$documents=$page['documents'];}
        $options=[];foreach($documents as$document){
            if($document['lineage']==='private_draft'){$name='DRAFT';$label=$document['draft']['context']['recipient']['name'];}
            elseif($document['lineage']==='named_company'){$name=$document['number'];$label=$document['recipient']['name'];}
            else{$r=$document['native_record'];$settings=new CompanySettingsStorage($this->db,$this->prefix);$padding=$settings->get($issuer,'number_padding_prefixes');if(!preg_match('/^(?:0|[1-9][0-9]?)$/D',$padding))throw new DomainException('native_company_invoice_padding_invalid');$name=(int)$r['status']===6?$r['prefix'].'DRAFT':SalesNumberPresentation::format($r['number'],$r['number_format'],$r['prefix'],$r['date'],(int)$padding);$label=$document['customer_label'];}
            $options[]=['id'=>$document['invoice_id'],'name'=>(string)$name,'subtext'=>(string)$label];
        }
        if($legacySelection){[$id,$identity,$before]=$legacySelection;$after=$this->rows('SELECT id,clientid,number,prefix,number_format,date,status,deleted_customer_name FROM '.$this->prefix.'invoices WHERE id=? AND issuer_id IS NULL',[$id])[0]??null;if($after!==$before||(new NativeCompanyInvoiceIdentity($this->db,$this->prefix))->resolve($issuer,$actor,$id)!==$identity)throw new DomainException('native_company_invoice_read_changed');}
        if($legacyCustomerReference!==null&&NativeCompanyResourceReferences::resolve($this->db,$this->prefix,$issuer,$actor,'client',$legacyCustomerReference['canonical_id'])!==$legacyCustomerReference)throw new DomainException('native_company_invoice_read_changed');
        $this->authorize($issuer,$actor);return$options;
    }

    /** Both contexts use the same query builder; only proven primary adds the disjoint NULL branch. */
    private function tablePage(int $issuer,int $actor,array $query,array $scope,array $related):array
    {
        $settings=new CompanySettingsStorage($this->db,$this->prefix);
        $format=$settings->exists($issuer,'dateformat')?$settings->get($issuer,'dateformat'):CompanySettingsCatalog::defaultValue('localization','dateformat');
        $recipient=($this->db->dbdriver??'')==='sqlite'||($this->db->dbdriver??'')==='sqlite3'
            ?"json_extract(d.recipient_identity_json,'$.name')":"JSON_UNQUOTE(JSON_EXTRACT(d.recipient_identity_json,'$.name'))";
        // A historical invoice does not authorize hydration of a now-private customer's directory row.
        $legacyRecipient="CASE WHEN i.deleted_customer_name<>'' THEN i.deleted_customer_name ELSE COALESCE(c.company,'') END";
        $legacyJoin=' LEFT JOIN '.$this->prefix.'clients c ON c.userid=i.clientid AND (NOT EXISTS (SELECT 1 FROM '.$this->prefix.'issuers_clients co WHERE co.client_id=c.userid AND co.owner_issuer_id<>?) OR EXISTS (SELECT 1 FROM '.$this->prefix.'issuers_client_links cl WHERE cl.client_id=c.userid AND cl.issuer_id=?))';
        $customColumns=null;
        foreach($query['orders']as$order)if(str_starts_with($order['field'],'cf_')){require_once __DIR__.'/NativeCompanyInvoiceCustomColumns.php';$customColumns=new NativeCompanyInvoiceCustomColumns($this->db,$this->prefix,$issuer,$actor);break;}
        foreach($query['filters']['rules']??[]as$rule)if(is_array($rule)&&is_string($rule['id']??null)&&!isset(NativeCompanyInvoiceRules::TYPES[$rule['id']])){require_once __DIR__.'/NativeCompanyInvoiceCustomColumns.php';$customColumns??=new NativeCompanyInvoiceCustomColumns($this->db,$this->prefix,$issuer,$actor);break;}
        if($query['search']!==''){require_once __DIR__.'/NativeCompanyInvoiceCustomColumns.php';$customColumns??=new NativeCompanyInvoiceCustomColumns($this->db,$this->prefix,$issuer,$actor);}
        $primary=(new IssuerResourceAccess($this->db,$this->prefix))->primaryId();
        $draftProjection=null;$draftFrame=[];
        if($this->db->table_exists($this->prefix.'company_invoice_drafts')){
            require_once __DIR__.'/NativeCompanyInvoiceDraftProjection.php';
            $draftProjection=new NativeCompanyInvoiceDraftProjection($this->db,$this->prefix);$draftFrame=$draftProjection->frame($issuer,$actor);
        }
        $branches=[];$allParams=[];$total=0;$namedRules=null;$dependencyFrame=[];
        foreach(array_merge($primary===$issuer?['named','legacy']:['named'],$draftProjection?['draft']:[])as$lineage){
            $rules=NativeCompanyInvoiceRules::compile($query['filters'],$this->prefix,$lineage,gmdate('Y-m-d'),$format,$customColumns);
            if($lineage==='named'){$namedRules=$rules;$dependencyFrame=$this->ruleDependencies($issuer,$actor,$rules);}
            $name=$lineage==='draft'?'i.customer':($lineage==='named'?$recipient:$legacyRecipient);
            $number=$lineage==='draft'?'NULL':($lineage==='named'?'d.formatted_number':'i.formatted_number');
            $from=' FROM '.$this->prefix.'invoices i'.($lineage==='named'?' INNER JOIN '.$this->prefix.'issuers_invoice_documents d ON d.invoice_id=i.id AND d.issuer_id=i.issuer_id':$legacyJoin)
                .' WHERE '.($lineage==='named'?'i.issuer_id=?':'i.issuer_id IS NULL').($related[$lineage]??'');
            $params=$lineage==='named'?array_merge([$issuer],$related['params']):array_merge([$issuer,$issuer],$related['legacy_params']);
            if($lineage==='draft'){
                $offset=(int)NativeCompanyResourceId::encode('company_invoice_draft',1)-1;
                // Use the canonical ID in all cross-resource predicates: no collision with physical invoice IDs.
                $from=' FROM (SELECT dp.canonical_id AS id,dp.source_id,dp.issuer_id,dp.number,dp.sent,dp.sale_agent,dp.status,dp.recurring,dp.project_id,dp.clientid,dp.currency,dp.date,dp.duedate,dp.customer,dp.subtotal,dp.total,dp.total_tax FROM (SELECT p.*,p.id AS source_id,p.id+'.$offset.' AS canonical_id,NULL AS number,CAST(0 AS SIGNED) AS sent,CAST(6 AS SIGNED) AS status,CAST(0 AS SIGNED) AS recurring FROM '.$this->prefix.NativeCompanyInvoiceDraftProjectionSchema::TABLE.' p WHERE p.state=\'open\') dp) i WHERE i.issuer_id=?';
                $params=[$issuer];
                if($related['reference']){$ref=$related['reference'];if($ref['feature']==='customers'){$from.=' AND i.clientid=?';$params[]=$ref['source_id'];}elseif($ref['source_kind']==='company_project'){$from.=' AND i.project_id=?';$params[]=$ref['source_id'];}else{$from.=' AND 1=0';}}
            }

            $total+=(int)$this->rows('SELECT COUNT(*) n'.$from,$params)[0]['n'];
            $where=$rules['sql']===''?'':' AND '.$rules['sql'];$params=array_merge($params,$rules['params']);
            foreach(['status'=>'status','client_id'=>'clientid']as$key=>$column)if($query[$key]!==null){$where.=' AND i.'.$column.'=?';$params[]=$query[$key];}
            if($query['year']!==null){$where.=' AND i.date>=? AND i.date<=?';$params[]=$query['year'].'-01-01';$params[]=$query['year'].'-12-31';}
            if($query['search']!==''){
                $needle='%'.strtr($query['search'],['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$search=[];
                foreach([$number,$name,'i.number','i.total','i.total_tax','i.date','i.duedate','i.status','i.id','i.clientid']as$column){$search[]=$column." LIKE ? ESCAPE '!'";$params[]=$needle;}
                $extraSearch=['project','tags'];foreach($customColumns->definitions(true)as$definition)$extraSearch[]='cf_'.$definition['id'];
                foreach($extraSearch as$field){$projection=$this->extraColumn($issuer,$actor,$lineage,$field,$customColumns);$search[]=$projection['sql']." LIKE ? ESCAPE '!'";$params=array_merge($params,$projection['params'],[$needle]);}
                $where.=' AND ('.implode(' OR ',$search).')';
            }
            $extra='';$projectionParams=[];
            foreach($query['orders']as$order){$field=$order['field'];
                if(in_array($field,['project','tags'],true)||str_starts_with($field,'cf_')){
                    $projection=$this->extraColumn($issuer,$actor,$lineage,$field,$customColumns);
                    $extra.=','.$projection['sql'].' AS '.$field;$projectionParams=array_merge($projectionParams,$projection['params']);
                }
            }
            $branches[]="SELECT '".$lineage."' AS native_lineage,i.id,i.issuer_id,".$number.' AS number,i.total,i.total_tax,SUBSTR(i.date,1,4) AS year,i.date,'.$name.' AS customer,i.duedate AS due_date,i.status'.$extra.$from.$where;
            $allParams=array_merge($allParams,$projectionParams,$params);
        }
        $union='('.implode(' UNION ALL ',$branches).') native_union';
        $filtered=(int)$this->rows('SELECT COUNT(*) n FROM '.$union,$allParams)[0]['n'];
        $sql='SELECT * FROM '.$union.' ORDER BY '.implode(',',array_map(static fn($order)=>$order['field'].' '.strtoupper($order['direction']),$query['orders'])).',id '.strtoupper($query['orders'][0]['direction']).($query['length']===-1?'':' LIMIT '.$query['length'].' OFFSET '.$query['start']);
        $frame=$this->rows($sql,$allParams);$documents=[];$legacyFrames=[];
        foreach($frame as$row){
            if($row['native_lineage']==='draft'){
                require_once __DIR__.'/CompanyInvoiceDrafts.php';$reference=NativeCompanyResourceId::decode('invoice',$row['id']);
                $doc=(new CompanyInvoiceDrafts($this->db,$this->prefix))->document($issuer,$actor,$reference['source_id']);
                if($doc['state']!=='open')throw new DomainException('native_company_invoice_read_changed');
                $doc=['invoice_id'=>$reference['canonical_id'],'issuer_id'=>$issuer,'lineage'=>'private_draft','draft'=>$doc];
            }elseif($row['issuer_id']!==null){
                $doc=$this->invoices->document($issuer,$actor,$row['id'])->data();$doc['lineage']='named_company';
            }else{
                // Not a company document/receipt proof. The native row remains immutable and explicitly NULL.
                $raw=$this->rows('SELECT id,issuer_id,clientid,number,prefix,number_format,formatted_number,date,duedate,currency,subtotal,total,total_tax,status,sent,hash,recurring,project_id,deleted_customer_name FROM '.$this->prefix.'invoices WHERE id=? AND issuer_id IS NULL',[$row['id']]);
                if(count($raw)!==1)throw new DomainException('native_company_invoice_read_changed');
                $legacyFrames[(int)$row['id']]=$raw;
                $doc=['invoice_id'=>(int)$row['id'],'issuer_id'=>null,'lineage'=>'native_legacy_null','native_record'=>$raw[0],'customer_label'=>$row['customer']];
            }
            $documents[]=$doc;
        }
        $this->authorize($issuer,$actor);
        if($customColumns)$customColumns->recheck();
        if($this->relatedScope($issuer,$actor,$scope)!==$related||(new IssuerResourceAccess($this->db,$this->prefix))->primaryId()!==$primary||$this->rows($sql,$allParams)!==$frame
            ||$this->ruleDependencies($issuer,$actor,$namedRules)!==$dependencyFrame)throw new DomainException('native_company_invoice_read_changed');
        if($draftProjection&&$draftProjection->frame($issuer,$actor)!==$draftFrame)throw new DomainException('native_company_invoice_read_changed');
        foreach($legacyFrames as$id=>$before){$after=$this->rows('SELECT id,issuer_id,clientid,number,prefix,number_format,formatted_number,date,duedate,currency,subtotal,total,total_tax,status,sent,hash,recurring,project_id,deleted_customer_name FROM '.$this->prefix.'invoices WHERE id=? AND issuer_id IS NULL',[$id]);if($after!==$before)throw new DomainException('native_company_invoice_read_changed');}
        return ['draw'=>$query['draw'],'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'documents'=>$documents];
    }

    private function extraColumn(int $issuer,int $actor,string $lineage,string $field,$customColumns):array
    {
        if(str_starts_with($field,'cf_')){
            if(!$customColumns)throw new DomainException('native_custom_field_denied');
            $id=substr($field,3);$allowed=false;foreach($customColumns->definitions(true)as$d)if((string)$d['id']===$id)$allowed=true;
            if(!$allowed)throw new DomainException('native_custom_field_denied');
            $projection=$customColumns->expression($id,$lineage==='draft'?'company_invoice_draft':'invoice');$type=$projection['definition']['type'];
            if($type==='number')$projection['sql']='CAST('.$projection['sql'].' AS DECIMAL(65,30))';
            return $projection;
        }
        if($field==='project'){
            if(!(new IssuerResourceAccess($this->db,$this->prefix))->can($issuer,$actor,'projects','view'))return ['sql'=>'NULL','params'=>[]];
            if($lineage==='draft')return ['sql'=>'(SELECT cp.name FROM '.$this->prefix.'company_projects cp WHERE cp.id=i.project_id AND cp.issuer_id=i.issuer_id AND cp.client_id=i.clientid)','params'=>[]];
            if($lineage==='named'){
                CompanyInvoiceProjectSchema::assertValid($this->db,$this->prefix);
                return ['sql'=>"(SELECT cp.name FROM ".$this->prefix.CompanyInvoiceProjectSchema::TABLE." cr JOIN ".$this->prefix."company_projects cp ON cp.id=cr.source_id AND cp.issuer_id=cr.issuer_id AND cp.client_id=cr.client_id WHERE cr.issuer_id=i.issuer_id AND cr.invoice_id=i.id AND cr.client_id=i.clientid AND cr.source_kind='company_project')",'params'=>[]];
            }
            require_once __DIR__.'/ClientIssuerScope.php';require_once __DIR__.'/CustomerRelationBoundary.php';
            $boundary=\app\services\CustomerRelationBoundary::sql($this->db,$this->prefix,'projects','lp');
            return ['sql'=>'(SELECT lp.name FROM '.$this->prefix.'projects lp WHERE lp.id=i.project_id AND ('.$boundary.'))','params'=>[]];
        }
        require_once __DIR__.'/NativeCompanyWorkTagsSchema.php';NativeCompanyWorkTagsSchema::assertValid($this->db,$this->prefix);
        if($lineage==='draft'){$source=' FROM '.$this->prefix."company_work_tag_links tl JOIN ".$this->prefix."company_work_tags tt ON tt.id=tl.tag_id AND tt.issuer_id=tl.issuer_id WHERE tl.issuer_id=i.issuer_id AND tl.source_kind='company_invoice_draft' AND tl.source_id=i.source_id";$label='tt.label';$order='tl.position,tt.id';}
        elseif($lineage==='named'){$source=' FROM '.$this->prefix."company_work_tag_links tl JOIN ".$this->prefix."company_work_tags tt ON tt.id=tl.tag_id AND tt.issuer_id=tl.issuer_id WHERE tl.issuer_id=i.issuer_id AND tl.source_kind='invoice' AND tl.source_id=i.id";$label='tt.label';$order='tl.position,tt.id';}
        else{$source=' FROM '.$this->prefix."taggables tl JOIN ".$this->prefix."tags tt ON tt.id=tl.tag_id WHERE tl.rel_type='invoice' AND tl.rel_id=i.id";$label='tt.name';$order='tl.tag_order,tt.id';}
        $sqlite=in_array($this->db->dbdriver??'',['sqlite','sqlite3'],true);
        return ['sql'=>$sqlite?"(SELECT GROUP_CONCAT(tag_name,',') FROM (SELECT ".$label.' AS tag_name'.$source.' ORDER BY '.$order.'))':"(SELECT GROUP_CONCAT(".$label.' ORDER BY '.$order." SEPARATOR ',')".$source.')','params'=>[]];
    }

    private function relatedScope(int $issuer,int $actor,array $scope):array
    {
        if(array_diff(array_keys($scope),['customer_id','project_id'])||count($scope)>1)throw new DomainException('native_company_invoice_query_invalid');
        $result=['named'=>'','legacy'=>'','params'=>[],'legacy_params'=>[],'reference'=>null];
        if(!$scope)return $result;
        $key=array_key_first($scope);$type=$key==='customer_id'?'client':'project';
        $reference=NativeCompanyResourceReferences::resolve($this->db,$this->prefix,$issuer,$actor,$type,$scope[$key]);
        $result['reference']=$reference;
        if($type==='client'){
            $result['named']=$result['legacy']=' AND i.clientid=?';
            $result['params']=$result['legacy_params']=[$reference['source_id']];
        }elseif($reference['source_kind']==='company_project'){
            CompanyInvoiceProjectSchema::assertValid($this->db,$this->prefix);
            $result['named']=' AND EXISTS (SELECT 1 FROM '.$this->prefix.CompanyInvoiceProjectSchema::TABLE.' r WHERE r.issuer_id=i.issuer_id AND r.invoice_id=i.id AND r.client_id=i.clientid AND r.source_kind=? AND r.source_id=?)';
            $result['params']=['company_project',$reference['source_id']];
            $result['legacy']=' AND 1=0'; // Separate source namespace: never match a legacy raw project ID.
        }elseif($reference['source_kind']==='project'){
            $result['named']=' AND 1=0'; // Native legacy project association to named invoices is not implemented yet.
            $result['legacy']=' AND i.project_id=?';$result['legacy_params']=[$reference['source_id']];
        }else throw new DomainException('native_company_invoice_query_invalid');
        return $result;
    }

    /** Native totals semantics, with owned effective receipts instead of unqualified SUM(payment.amount). */
    public function summary($issuer, $actor, $currency = null, array $years = [], array $scope = []): array
    {
        $issuer = IssuerPolicy::id($issuer); $actor = IssuerPolicy::id($actor);
        $this->authorize($issuer, $actor);
        $related=$this->relatedScope($issuer,$actor,$scope);
        if (count($years) > 100) { throw new DomainException('native_company_invoice_query_invalid'); }
        $years = array_values(array_unique(array_map(static fn($year)=>self::integer($year, 1000, 9999), $years)));
        if (!$years) { $years = [(int)date('Y')]; }
        $catalog = $this->rows('SELECT id,name,symbol,decimal_separator,thousand_separator,placement,isdefault FROM '.$this->prefix.'currencies ORDER BY id', []);
        $byCurrency = []; $default = null;
        foreach ($catalog as $unit) {
            $id = IssuerPolicy::id($unit['id']); $unit['id'] = $id;
            if (!preg_match('/^[A-Z]{3}$/D', $unit['name'])) { throw new DomainException('company_currency_catalog_changed'); }
            $byCurrency[$id] = $unit;
            if ((string)$unit['isdefault'] === '1') {
                if ($default !== null) { throw new DomainException('company_currency_catalog_changed'); }
                $default = $id;
            }
        }
        $currency = $currency === null ? $default : IssuerPolicy::id($currency);
        if ($currency === null || !isset($byCurrency[$currency])) { throw new DomainException('company_currency_catalog_changed'); }
        $sql = 'SELECT i.id,i.currency,i.date,i.duedate,i.status,i.sent,i.total FROM '.$this->prefix.'invoices i'
            .' INNER JOIN '.$this->prefix.'issuers_invoice_documents d ON d.invoice_id=i.id AND d.issuer_id=i.issuer_id'
            .' WHERE i.issuer_id=?'.$related['named'].' ORDER BY i.id';
        $params=array_merge([$issuer],$related['params']);
        $rows = $this->rows($sql, $params);
        $totals = ['due'=>0, 'paid'=>0, 'overdue'=>0]; $counts = array_fill(1, 6, 0);
        $primary = (new IssuerResourceAccess($this->db, $this->prefix))->primaryId();
        $legacyRows = []; $legacyTotals = ['due'=>0, 'paid'=>0, 'overdue'=>0];
        $notSent = 0; $availableYears = []; $usedCurrencies = [];
        foreach ($rows as $row) {
            $document = $this->invoices->document($issuer, $actor, $row['id'])->data();
            $unitId = IssuerPolicy::id($row['currency']);
            if (!isset($byCurrency[$unitId]) || $byCurrency[$unitId]['name'] !== $document['currency']) {
                throw new DomainException('company_currency_catalog_changed');
            }
            if ((string)$row['status'] !== (string)$document['status'] || $row['date'] !== $document['date']) {
                throw new DomainException('native_company_invoice_read_changed');
            }
            if (!in_array((string)$row['sent'], ['0','1'], true)) { throw new DomainException('native_company_invoice_read_changed'); }
            $status = $document['status']; $year = (int)substr($document['date'], 0, 4);
            ++$counts[$status]; $availableYears[$year] = ['year'=>$year]; $usedCurrencies[$unitId] = $byCurrency[$unitId];
            if ((string)$row['sent'] === '0' && !in_array($status, [2,5], true)) { ++$notSent; }
            if ($unitId !== $currency || !in_array($year, $years, true) || in_array($status, [5,6], true)) { continue; }
            $add = ['paid'=>$document['position']['received']];
            if ($status !== 2) { $add['due'] = $document['position']['receivable']; }
            // Native overdue badge uses total of status4, not remaining balance or an invented clock rule.
            if ($status === 4) { $add['overdue'] = $document['totals']['total']; }
            foreach ($add as $field=>$amount) {
                $minor = CompanyInvoiceAmounts::minor($amount);
                if ($minor > PHP_INT_MAX - $totals[$field]) { throw new DomainException('native_company_invoice_total_overflow'); }
                $totals[$field] += $minor;
            }
        }
        $draftProjection=null;$draftFrame=[];
        if($this->db->table_exists($this->prefix.'company_invoice_drafts')){
            require_once __DIR__.'/NativeCompanyInvoiceDraftProjection.php';$draftProjection=new NativeCompanyInvoiceDraftProjection($this->db,$this->prefix);$draftFrame=$draftProjection->frame($issuer,$actor);
            foreach($draftFrame as$draft){
                if($related['reference']){$ref=$related['reference'];if($ref['feature']==='customers'){if($draft['clientid']!==$ref['source_id'])continue;}elseif($ref['source_kind']==='company_project'){if($draft['project_id']!==$ref['source_id'])continue;}else continue;}
                $unitId=$draft['currency'];if(!isset($byCurrency[$unitId]))throw new DomainException('company_currency_catalog_changed');
                ++$counts[6];++$notSent;$year=(int)substr($draft['date'],0,4);$availableYears[$year]=['year'=>$year];$usedCurrencies[$unitId]=$byCurrency[$unitId];
            }
        }
        $namedTotals = $totals;
        // Legacy NULL is a separate persisted lineage, accessible only through the proven installation issuer.
        // Its recorded sums are NOT provider-verified company receipt evidence.
        $legacySql = 'SELECT i.id,i.currency,i.date,i.status,i.sent,i.total,'
            .'(SELECT COALESCE(SUM(p.amount),0) FROM '.$this->prefix.'invoicepaymentrecords p WHERE p.invoiceid=i.id) paid,'
            .'(SELECT COALESCE(SUM(c.amount),0) FROM '.$this->prefix.'credits c WHERE c.invoice_id=i.id) credited'
            .' FROM '.$this->prefix.'invoices i WHERE i.issuer_id IS NULL'.$related['legacy'].' ORDER BY i.id';
        if ($issuer === $primary) {
            $legacyRows = $this->rows($legacySql, $related['legacy_params']);
            foreach ($legacyRows as $row) {
                $unitId = IssuerPolicy::id($row['currency']);
                $status = self::integer($row['status'], 1, 6);
                if (!isset($byCurrency[$unitId]) || !is_string($row['date'])
                    || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $row['date'])
                    || !in_array((string)$row['sent'], ['0','1'], true)) {
                    throw new DomainException('native_company_invoice_legacy_review');
                }
                $year = (int)substr($row['date'], 0, 4);
                ++$counts[$status]; $availableYears[$year] = ['year'=>$year]; $usedCurrencies[$unitId] = $byCurrency[$unitId];
                if ((string)$row['sent'] === '0' && !in_array($status, [2,5], true)) { ++$notSent; }
                if ($unitId !== $currency || !in_array($year, $years, true) || in_array($status, [5,6], true)) { continue; }
                $total = CompanyInvoiceAmounts::minor((string)$row['total']);
                $paid = CompanyInvoiceAmounts::minor((string)$row['paid']);
                $credit = CompanyInvoiceAmounts::minor((string)$row['credited']);
                $amounts = ['paid'=>$paid];
                if ($status !== 2) { $amounts['due'] = $total - $paid - $credit; }
                if ($status === 4) { $amounts['overdue'] = $total; }
                foreach ($amounts as $field=>$minor) {
                    if (($minor > 0 && $legacyTotals[$field] > PHP_INT_MAX - $minor)
                        || ($minor < 0 && $legacyTotals[$field] < PHP_INT_MIN - $minor)) {
                        throw new DomainException('native_company_invoice_total_overflow');
                    }
                    $legacyTotals[$field] += $minor;
                }
            }
            foreach ($legacyTotals as $field=>$minor) {
                if (($minor > 0 && $totals[$field] > PHP_INT_MAX - $minor)
                    || ($minor < 0 && $totals[$field] < PHP_INT_MIN - $minor)) {
                    throw new DomainException('native_company_invoice_total_overflow');
                }
                $totals[$field] += $minor;
            }
            if ($this->rows($legacySql, $related['legacy_params']) !== $legacyRows) { throw new DomainException('native_company_invoice_read_changed'); }
        }
        if ($this->rows($sql, $params) !== $rows) { throw new DomainException('native_company_invoice_read_changed'); }
        if ((new IssuerResourceAccess($this->db, $this->prefix))->primaryId() !== $primary) { throw new DomainException('native_company_invoice_read_changed'); }
        $this->authorize($issuer, $actor);
        if($this->relatedScope($issuer,$actor,$scope)!==$related)throw new DomainException('native_company_invoice_read_changed');
        if($draftProjection&&$draftProjection->frame($issuer,$actor)!==$draftFrame)throw new DomainException('native_company_invoice_read_changed');
        krsort($availableYears); $usedCurrencies[$currency] = $byCurrency[$currency]; ksort($usedCurrencies);
        $denominator = array_sum($counts) - $counts[5]; $percent = [];
        foreach (array_merge(range(1, 6), ['not_sent']) as $status) {
            $count = $status === 'not_sent' ? $notSent : ($status === 5 ? 0 : $counts[$status]);
            $percent[$status] = ['total_by_status'=>$count, 'total'=>$denominator,
                'percent'=>$denominator > 0 ? number_format(($count * 100) / $denominator, 2) : 0];
        }
        return [
            'total_result'=>array_map([CompanyInvoiceAmounts::class, 'decimal'], $totals)
                + ['currency'=>(object)$byCurrency[$currency], 'currencyid'=>$currency],
            'invoices_years'=>array_values($availableYears),
            'invoices_total_currencies'=>array_values($usedCurrencies),
            'counts'=>$counts, 'not_sent'=>$notSent, 'percent_by_status'=>$percent,
            'lineages'=>[
                'named'=>['basis'=>'canonical_verified_position', 'totals'=>array_map([CompanyInvoiceAmounts::class, 'decimal'], $namedTotals)],
                'legacy'=>['basis'=>'native_legacy_recorded', 'included'=>$issuer === $primary,
                    'totals'=>array_map([CompanyInvoiceAmounts::class, 'decimal'], $legacyTotals)],
            ],
        ];
    }
}
