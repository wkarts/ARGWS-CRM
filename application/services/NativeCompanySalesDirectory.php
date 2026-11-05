<?php

declare(strict_types=1);
require_once __DIR__.'/CompanySales.php';
require_once __DIR__.'/NativeCompanyResourceId.php';
require_once __DIR__.'/NativeCompanyLegacySalesBoundary.php';

/** One directory for the original sales routes. Storage IDs are never rewritten. */
final class NativeCompanySalesDirectory
{
    public function __construct(private $db, private string $prefix)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) throw new InvalidArgumentException('Invalid prefix');
    }

    public static function type(string $kind): string
    {
        return match ($kind) {'estimates'=>'estimate', 'proposals'=>'proposal', default=>throw new DomainException('company_sales_invalid')};
    }

    public static function status(string $kind, string $state): int
    {
        return match ($state) {
            'accepted','converting','converted'=>$kind==='estimates'?4:3,
            'declined','cancelled'=>2+($kind==='estimates'?1:0),
            'open'=>1,
            default=>throw new DomainException('company_sales_invalid'),
        };
    }

    private function authorize(int $issuer, int $actor, string $kind): bool
    {
        self::type($kind);
        $acl=new IssuerResourceAccess($this->db,$this->prefix);
        if (!$acl->can($issuer,$actor,$kind,'view')) throw new DomainException('company_sales_denied');
        return $acl->primaryId()===$issuer;
    }

    private function rows(string $sql, array $args=[]): array
    {
        return IssuerStorage::query($this->db,$sql,$args)->result_array();
    }

    /** SQL projects only public search fields, before pagination; private notes are not searchable. */
    private function source(int $issuer, int $actor, string $kind): array
    {
        $primary=$this->authorize($issuer,$actor,$kind);
        $offset=$kind==='estimates'?34359738368:38654705664;
        $accepted=$kind==='estimates'?4:3;$declined=$kind==='estimates'?3:2;
        $sqlite=($this->db->pdo??null) instanceof PDO&&$this->db->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        $json=static fn($path)=>$sqlite?"JSON_EXTRACT(d.payload_json,'$.".$path."')":"CASE WHEN JSON_TYPE(JSON_EXTRACT(d.payload_json,'$.".$path."'))='NULL' THEN NULL ELSE JSON_UNQUOTE(JSON_EXTRACT(d.payload_json,'$.".$path."')) END";
        $date=$json('date');$due=$json('due_date');$displayStatus=$json('native_status');
        $reference=$json('native_reference');$subject=$json('native_proposal.subject');
        $agent=$kind==='estimates'?$json('native_fields.sale_agent'):$json('native_proposal.assigned');
        $project=$json('native_project.source_id');
        // MariaDB returns NULL when removing a nested path whose parent does not exist.
        $public="json_remove(d.payload_json,'$.native_custom_fields','$.native_item_fields')";
        $search="CASE WHEN json_valid(d.payload_json) THEN CASE WHEN json_extract(d.payload_json,'$.native_fields.adminnote') IS NULL THEN $public ELSE json_remove($public,'$.native_fields.adminnote') END ELSE '' END";
        $projection=NativeCompanySalesProjectionSchema::available($this->db,$this->prefix);
        if($projection){
            require_once __DIR__.'/NativeCompanySalesProjection.php';
            (new NativeCompanySalesProjection($this->db,$this->prefix))->assertFrame($issuer,$actor,$kind);
        }
        $subtotal=$projection?'p.subtotal':'NULL';$tax=$projection?'p.total_tax':'NULL';
        $signature=$kind==='estimates'&&$this->db->table_exists($this->prefix.'company_estimate_signatures')
            ?"EXISTS(SELECT 1 FROM {$this->prefix}company_estimate_signatures sig WHERE sig.issuer_id=d.issuer_id AND sig.document_id=d.id)":'0';
        $sent=$this->db->table_exists($this->prefix.'company_sales_deliveries')&&$this->db->table_exists($this->prefix.'mail_queue')
            ?"EXISTS(SELECT 1 FROM {$this->prefix}company_sales_deliveries sd JOIN {$this->prefix}mail_queue mq ON mq.id=sd.queue_id AND mq.issuer_id=sd.issuer_id WHERE sd.issuer_id=d.issuer_id AND sd.document_id=d.id AND mq.status='sent')":'0';
        $sql="SELECT d.id+$offset canonical_id,d.id source_id,'owned' origin,d.number,d.total,$date date,$due due_date,d.created_at datecreated,d.id pipeline_order,
            CASE WHEN d.state IN ('accepted','converting','converted') THEN $accepted WHEN d.state IN ('declined','cancelled') THEN $declined ELSE COALESCE($displayStatus,1) END status,
            d.client_id,CAST(d.number AS CHAR) number_text,$search search_text,d.context_json client_text,
            $subtotal subtotal,$tax total_tax,COALESCE($reference,'') reference_no,COALESCE($subject,'') subject,
            COALESCE($agent,0) agent,'customer' rel_type,$signature is_signed,$sent sent,
            CASE WHEN d.invoice_id>0 THEN 1 ELSE 0 END invoiced,d.currency,COALESCE($project,0) project_id
            FROM {$this->prefix}company_sales_documents d".
            ($projection?" JOIN {$this->prefix}company_sales_projection p ON p.id=d.id AND p.issuer_id=d.issuer_id AND p.payload_hash=d.input_hash":'').
            ' WHERE d.issuer_id=? AND d.kind=?';
        $args=[$issuer,$kind];
        if($primary&&$this->db->table_exists($this->prefix.$kind)){
            $boundary=NativeCompanyLegacySalesBoundary::sql($this->db,$this->prefix,$kind,'s');
            if($kind==='estimates'){
                $sql.=" UNION ALL SELECT s.id,s.id,'legacy',s.number,s.total,s.date,s.expirydate,s.datecreated,s.pipeline_order,s.status,s.clientid,
                    CAST(s.number AS CHAR),COALESCE(s.clientnote,''),COALESCE(c.company,''),s.subtotal,s.total_tax,
                    COALESCE(s.reference_no,''),'',COALESCE(s.sale_agent,0),'customer',CASE WHEN s.signature IS NULL THEN 0 ELSE 1 END,
                    s.sent,CASE WHEN s.invoiceid IS NULL THEN 0 ELSE 1 END,s.currency,COALESCE(s.project_id,0)
                    FROM {$this->prefix}estimates s LEFT JOIN {$this->prefix}clients c ON c.userid=s.clientid WHERE $boundary";
            }else{
                $sql.=" UNION ALL SELECT s.id,s.id,'legacy',s.id,s.total,s.date,s.open_till,s.datecreated,s.pipeline_order,s.status,
                    CASE WHEN HEX(s.rel_type)=HEX('customer') THEN s.rel_id ELSE 0 END,
                    CAST(s.id AS CHAR),COALESCE(s.subject,''),COALESCE(s.proposal_to,''),s.subtotal,s.total_tax,'',COALESCE(s.subject,''),
                    COALESCE(s.assigned,0),s.rel_type,CASE WHEN s.signature IS NULL THEN 0 ELSE 1 END,0,
                    CASE WHEN s.invoice_id IS NULL THEN 0 ELSE 1 END,s.currency,COALESCE(s.project_id,0)
                    FROM {$this->prefix}proposals s WHERE $boundary";
            }
        }
        return [$sql,$args,$primary];
    }

    public function catalogue(int $issuer,int $actor,string $kind):array
    {
        [$source,$params,$primary]=$this->source($issuer,$actor,$kind);
        $years=$this->rows("SELECT DISTINCT SUBSTR(date,1,4) year FROM ($source) sales ORDER BY year DESC",$params);
        $counts=$this->rows("SELECT status,COUNT(*) total FROM ($source) sales GROUP BY status",$params);
        $agents=$this->rows("SELECT DISTINCT agent FROM ($source) sales WHERE agent>0 ORDER BY agent",$params);$members=[];
        $acl=new IssuerResourceAccess($this->db,$this->prefix);
        foreach($agents as $row){$id=IssuerPolicy::id($row['agent']);if(!$acl->member($issuer,$id))continue;
            $staff=$this->rows('SELECT staffid,firstname,lastname FROM '.$this->prefix.'staff WHERE staffid=?',[$id])[0]??null;
            if($staff)$members[]=['sale_agent'=>$id,'name'=>trim($staff['firstname'].' '.$staff['lastname'])];
        }
        if($primary!==$this->authorize($issuer,$actor,$kind))throw new DomainException('company_sales_denied');
        return ['years'=>$years,'counts'=>$counts,'agents'=>$members];
    }

    public function customDefinitions(int $issuer,int $actor,string $kind):array
    {
        $this->authorize($issuer,$actor,$kind);
        if(!$this->db->table_exists($this->prefix.'company_custom_fields'))return [];
        require_once __DIR__.'/NativeCompanyCustomFields.php';
        return (new NativeCompanyCustomFields($this->db,$this->prefix))->definitions($issuer,$actor,self::type($kind),['active'=>1]);
    }

    /** Original financial summary, grouped in the selected currency instead of combining currencies. */
    public function totals(int $issuer,int $actor,array $input):array
    {
        [$source,$params,$primary]=$this->source($issuer,$actor,'estimates');
        $currencies=$this->rows("SELECT DISTINCT c.id,c.name,c.symbol,c.isdefault FROM ($source) sales JOIN {$this->prefix}currencies c ON c.id=sales.currency ORDER BY c.isdefault DESC,c.id",$params);
        if(!$currencies)$currencies=$this->rows('SELECT id,name,symbol,isdefault FROM '.$this->prefix.'currencies WHERE isdefault=1 ORDER BY id');
        if(!$currencies)throw new DomainException('company_sales_invalid');
        $currency=$input['currency']??'';$selected=null;
        if($currency!==''&&$currency!==null)$currency=IssuerPolicy::id($currency);
        foreach($currencies as $row)if($currency===''||$currency===null||(string)$row['id']===(string)$currency){$selected=$row;break;}
        if(!$selected)throw new DomainException('company_sales_invalid');
        $years=$input['years']??[];if($years===[])$years=[gmdate('Y')];
        if(!is_array($years)||!array_is_list($years)||count($years)>100)throw new DomainException('company_sales_invalid');
        $ranges=[];foreach($years as $year){if((!is_int($year)&&!is_string($year))||!preg_match('/^[1-9][0-9]{3}$/D',(string)$year))throw new DomainException('company_sales_invalid');$ranges[]='(date>=? AND date<=?)';array_push($params,$year.'-01-01',$year.'-12-31');}
        $where='('.implode(' OR ',$ranges).') AND currency=?';$params[]=$selected['id'];
        $client=$input['customer_id']??'';
        if($client!==''&&$client!==null){$client=IssuerPolicy::id($client);$where.=' AND client_id=?';$params[]=$client;}
        if(($input['project_id']??'')!==''){
            require_once __DIR__.'/NativeCompanyResourceReferences.php';
            $project=NativeCompanyResourceReferences::resolve($this->db,$this->prefix,$issuer,$actor,'project',$input['project_id']);
            if(!in_array($project['source_kind'],['project','company_project'],true))throw new DomainException('company_sales_denied');
            $where.=' AND project_id=? AND origin=?';array_push($params,$project['source_id'],$project['source_kind']==='project'?'legacy':'owned');
        }
        $rows=$this->rows("SELECT status,SUM(total) total FROM ($source) sales WHERE $where GROUP BY status",$params);$map=[];
        foreach($rows as $row)$map[(int)$row['status']]=(string)$row['total'];
        $totals=[];foreach(range(1,5) as $status)$totals[]=['status'=>$status,'total'=>$map[$status]??'0.00','currency_name'=>$selected['name']];
        if($primary!==$this->authorize($issuer,$actor,'estimates'))throw new DomainException('company_sales_denied');
        return ['totals'=>$totals,'currencies'=>$currencies,'_currency'=>$selected['id'],'estimates_years'=>$this->catalogue($issuer,$actor,'estimates')['years']];
    }

    private function customExpression(int $issuer,string $kind,array $definition):array
    {
        $type=self::type($kind);$source="CASE WHEN sales.origin='owned' THEN 'company_$type' ELSE '$type' END";
        $params=[$issuer,$definition['field_source_kind'],$definition['source_field_id']];
        $sql="(SELECT cv.value FROM {$this->prefix}company_custom_field_values cv WHERE cv.issuer_id=? AND cv.field_source_kind=? AND cv.field_id=? AND cv.source_kind=$source AND cv.source_id=sales.source_id)";
        if($definition['field_source_kind']==='custom_field'){
            $sql="COALESCE($sql,(SELECT lv.value FROM {$this->prefix}customfieldsvalues lv WHERE sales.origin='legacy' AND lv.fieldid=? AND lv.fieldto=? AND lv.relid=sales.source_id))";
            array_push($params,$definition['source_field_id'],$type);
        }
        return ['sql'=>$sql,'params'=>$params];
    }

    /** Returns canonical IDs and exact counts for both DataTables and HTML pipeline readers. */
    public function query(int $issuer,int $actor,string $kind,array $filter=[]): array
    {
        if (array_diff(array_keys($filter),['search','after','start','length','status','client_id','sort_by','sort','builder'])) throw new DomainException('company_sales_invalid');
        $search=$filter['search']??'';
        if (!is_string($search)||strlen($search)>191||preg_match('//u',$search)!==1||preg_match('/[\x00-\x1f\x7f]/',$search)) throw new DomainException('company_sales_invalid');
        $integer=static function($value,int $max):int {
            if ((!is_int($value)&&!is_string($value))||!preg_match('/^(0|[1-9][0-9]*)$/D',(string)$value)||(int)$value>$max) throw new DomainException('company_sales_invalid');
            return (int)$value;
        };
        $start=$integer($filter['start']??0,1000000);$limit=$integer($filter['length']??50,100);if($limit<1)throw new DomainException('company_sales_invalid');
        $after=$integer($filter['after']??0,9007199254740991);$status=$integer($filter['status']??0,$kind==='estimates'?5:6);$client=$integer($filter['client_id']??0,2147483647);
        $columns=['id'=>'canonical_id','date'=>'date','number'=>'number','total'=>'total','pipeline_order'=>'pipeline_order','datecreated'=>'datecreated','expirydate'=>'due_date','open_till'=>'due_date','subject'=>'subject','client'=>'client_text','status'=>'status','reference_no'=>'reference_no','tax'=>'total_tax','year'=>'date'];
        $column=$filter['sort_by']??'id';$direction=strtoupper((string)($filter['sort']??'ASC'));
        if (!is_string($column)||!isset($columns[$column])||!in_array($direction,['ASC','DESC'],true)) throw new DomainException('company_sales_invalid');
        [$source,$args,$primary]=$this->source($issuer,$actor,$kind);
        $where=[];$values=$args;
        if($after){$where[]='canonical_id>CAST(? AS SIGNED)';$values[]=$after;}
        if($status){$where[]='status=CAST(? AS SIGNED)';$values[]=$status;}
        if($client){$where[]='client_id=?';$values[]=$client;}
        if($search!==''){$needle='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';$where[]="(search_text LIKE ? ESCAPE '!' OR client_text LIKE ? ESCAPE '!' OR number_text LIKE ? ESCAPE '!')";array_push($values,$needle,$needle,$needle);}
        if(isset($filter['builder'])){
            require_once __DIR__.'/NativeCompanySalesRules.php';
            if(!is_array($filter['builder']))throw new DomainException('company_sales_invalid');
            $definitions=$this->customDefinitions($issuer,$actor,$kind);
            $store=new CompanySettingsStorage($this->db,$this->prefix);
            $format=$store->exists($issuer,'dateformat')?$store->get($issuer,'dateformat'):'Y-m-d|%Y-%m-%d';
            $compiled=NativeCompanySalesRules::compile($kind,$filter['builder'],gmdate('Y-m-d'),$format,$definitions,
                fn($definition)=>$this->customExpression($issuer,$kind,$definition));
            if($compiled['sql']!==''){$where[]=$compiled['sql'];$values=array_merge($values,$compiled['params']);}
        }
        $condition=$where?' WHERE '.implode(' AND ',$where):'';
        $total=(int)$this->rows("SELECT COUNT(*) n FROM ($source) sales",$args)[0]['n'];
        $count=(int)$this->rows("SELECT COUNT(*) n FROM ($source) sales$condition",$values)[0]['n'];
        $order=$columns[$column].' '.$direction.($columns[$column]==='canonical_id'?'':',canonical_id '.$direction);
        $rows=$this->rows("SELECT canonical_id,source_id,origin FROM ($source) sales$condition ORDER BY $order LIMIT ".($limit+1).' OFFSET '.$start,$values);
        $more=count($rows)>$limit;$rows=array_slice($rows,0,$limit);$items=[];
        foreach($rows as$row){$document=$this->document($issuer,$actor,$kind,(string)$row['canonical_id']);$items[]=$document;}
        if($primary!==$this->authorize($issuer,$actor,$kind))throw new DomainException('company_sales_denied');
        return ['items'=>$items,'total'=>$total,'filtered'=>$count,'next'=>$more?(string)end($rows)['canonical_id']:null];
    }

    public function document(int $issuer,int $actor,string $kind,$canonical):array
    {
        $primary=$this->authorize($issuer,$actor,$kind);$type=self::type($kind);$ref=NativeCompanyResourceId::decode($type,$canonical);
        if($ref['source_kind']===$type){
            if(!$primary)throw new DomainException('company_sales_denied');
            require_once __DIR__.'/NativeCompanyLegacySales.php';
            return (new NativeCompanyLegacySales($this->db,$this->prefix))->document($issuer,$actor,$kind,$ref['source_id']);
        }
        if($ref['source_kind']!=='company_'.$type)throw new DomainException('company_sales_denied');
        $row=(new CompanySales($this->db,$this->prefix))->document($issuer,$actor,$ref['source_id']);
        if($row['kind']!==$kind)throw new DomainException('company_sales_denied');
        $row['source_id']=$ref['source_id'];$row['id']=$ref['canonical_id'];$row['origin']='owned';$row['native_status']=$row['state']==='open'?(int)($row['payload']['native_status']??1):self::status($kind,$row['state']);
        $row['subject']=$row['payload']['native_proposal']['subject']??$row['formatted_number'];
        if(NativeCompanySalesProjectionSchema::available($this->db,$this->prefix)){
            require_once __DIR__.'/NativeCompanySalesProjection.php';
            (new NativeCompanySalesProjection($this->db,$this->prefix))->assertDocument($issuer,$row);
        }
        return $row;
    }
}
