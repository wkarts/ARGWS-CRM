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
        $accepted=$kind==='estimates'?4:3; $declined=$kind==='estimates'?3:2;
        $sqlite=($this->db->pdo??null) instanceof PDO&&$this->db->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        $json=static fn($path)=>$sqlite?"JSON_EXTRACT(payload_json,'$.".$path."')":"JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.".$path."'))";
        $date=$json('date');$due=$json('due_date');
        $sql="SELECT id+$offset canonical_id,id source_id,'owned' origin,number,total,$date date,$due due_date,created_at datecreated,id pipeline_order,
            CASE WHEN state IN ('accepted','converting','converted') THEN $accepted WHEN state IN ('declined','cancelled') THEN $declined ELSE 1 END status,
            client_id,CAST(number AS CHAR) number_text,
            CASE WHEN json_valid(payload_json) THEN json_remove(payload_json,'$.native_custom_fields','$.native_fields.adminnote') ELSE '' END search_text,
            context_json client_text FROM {$this->prefix}company_sales_documents WHERE issuer_id=? AND kind=?";
        $args=[$issuer,$kind];
        if ($primary && $this->db->table_exists($this->prefix.$kind)) {
            $boundary=NativeCompanyLegacySalesBoundary::sql($this->db,$this->prefix,$kind,'s');
            if ($kind==='estimates') {
                $sql.=" UNION ALL SELECT s.id,s.id,'legacy',s.number,s.total,s.date,s.expirydate,s.datecreated,s.pipeline_order,s.status,s.clientid,
                    CAST(s.number AS CHAR),COALESCE(s.clientnote,''),COALESCE(c.company,'')
                    FROM {$this->prefix}estimates s LEFT JOIN {$this->prefix}clients c ON c.userid=s.clientid WHERE $boundary";
            } else {
                $sql.=" UNION ALL SELECT s.id,s.id,'legacy',s.id,s.total,s.date,s.open_till,s.datecreated,s.pipeline_order,s.status,
                    CASE WHEN HEX(s.rel_type)=HEX('customer') THEN s.rel_id ELSE 0 END,
                    CAST(s.id AS CHAR),COALESCE(s.subject,''),COALESCE(s.proposal_to,'')
                    FROM {$this->prefix}proposals s WHERE $boundary";
            }
        }
        return [$sql,$args,$primary];
    }

    /** Returns canonical IDs and exact counts for both DataTables and HTML pipeline readers. */
    public function query(int $issuer,int $actor,string $kind,array $filter=[]): array
    {
        if (array_diff(array_keys($filter),['search','after','start','length','status','client_id','sort_by','sort'])) throw new DomainException('company_sales_invalid');
        $search=$filter['search']??'';
        if (!is_string($search)||strlen($search)>191||preg_match('//u',$search)!==1||preg_match('/[\x00-\x1f\x7f]/',$search)) throw new DomainException('company_sales_invalid');
        $integer=static function($value,int $max):int {
            if ((!is_int($value)&&!is_string($value))||!preg_match('/^(0|[1-9][0-9]*)$/D',(string)$value)||(int)$value>$max) throw new DomainException('company_sales_invalid');
            return (int)$value;
        };
        $start=$integer($filter['start']??0,1000000);$limit=$integer($filter['length']??50,100);if($limit<1)throw new DomainException('company_sales_invalid');
        $after=$integer($filter['after']??0,9007199254740991);$status=$integer($filter['status']??0,$kind==='estimates'?5:6);$client=$integer($filter['client_id']??0,2147483647);
        $columns=['id'=>'canonical_id','date'=>'date','number'=>'number','total'=>'total','pipeline_order'=>'pipeline_order','datecreated'=>'datecreated','expirydate'=>'due_date','open_till'=>'due_date'];
        $column=$filter['sort_by']??'id';$direction=strtoupper((string)($filter['sort']??'ASC'));
        if (!is_string($column)||!isset($columns[$column])||!in_array($direction,['ASC','DESC'],true)) throw new DomainException('company_sales_invalid');
        [$source,$args,$primary]=$this->source($issuer,$actor,$kind);
        $where=[];$values=$args;
        if($after){$where[]='canonical_id>CAST(? AS SIGNED)';$values[]=$after;}
        if($status){$where[]='status=CAST(? AS SIGNED)';$values[]=$status;}
        if($client){$where[]='client_id=?';$values[]=$client;}
        if($search!==''){$needle='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';$where[]="(search_text LIKE ? ESCAPE '!' OR client_text LIKE ? ESCAPE '!' OR number_text LIKE ? ESCAPE '!')";array_push($values,$needle,$needle,$needle);}
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
        $row['source_id']=$ref['source_id'];$row['id']=$ref['canonical_id'];$row['origin']='owned';$row['native_status']=self::status($kind,$row['state']);
        return $row;
    }
}
