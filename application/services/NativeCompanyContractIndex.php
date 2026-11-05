<?php
declare(strict_types=1);

require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/NativeCompanyContractRules.php';
require_once __DIR__.'/NativeCompanyContractTypes.php';
require_once __DIR__.'/NativeCompanyCustomFields.php';
require_once __DIR__.'/NativeCompanyCurrencyReference.php';
require_once __DIR__.'/NativeCompanyDataTableWire.php';
require_once __DIR__.'/CustomerRelationBoundary.php';
require_once __DIR__.'/ClientIssuerScope.php';
require_once __DIR__.'/CompanySettingsCatalog.php';

/** Read-only projection for the original contract list, summaries and filters. */
final class NativeCompanyContractIndex
{
    public const COLUMNS=['id','subject','company','type_name','contract_value','datestart','dateend','project_name','signed'];

    public function __construct(private $db, private string $p)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D',$p)) throw new InvalidArgumentException('Invalid prefix');
    }

    private function rows(string $sql,array $args=[]):array { return IssuerStorage::query($this->db,$sql,$args)->result_array(); }

    private function authority(int $issuer,int $actor):array
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if (!$acl->can($issuer,$actor,'contracts','view')) throw new DomainException('native_contract_denied');
        return ['primary'=>$acl->primaryId()===$issuer,'projects'=>$acl->can($issuer,$actor,'projects','view'),'customers'=>$acl->can($issuer,$actor,'customers','view')];
    }

    private function source(int $issuer,array $authority,array $definitions=[]):array
    {
        $p=$this->p; $pdo=$this->db->pdo??$this->db->conn_id??null;
        $sqlite=$pdo instanceof PDO&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        // A JSON null is absence; the valid literal string "null" is still content.
        $json=static fn($column,$path)=>$sqlite?"json_extract($column,'$.".$path."')":"(CASE WHEN JSON_TYPE(JSON_EXTRACT($column,'$.".$path."'))='NULL' THEN NULL ELSE JSON_UNQUOTE(JSON_EXTRACT($column,'$.".$path."')) END)";
        $value=$json('c.snapshot_json','contract_value'); $currency=$json('c.snapshot_json','currency');
        $type=$json('c.snapshot_json','contract_type.id'); $typeName=$json('c.snapshot_json','contract_type.name');
        $customer=$json('c.snapshot_json','client.name');
        $marked=$json('a.metadata_json','marked');
        $signed="COALESCE((SELECT CASE WHEN CAST($marked AS CHAR) IN ('true','1') THEN 1 ELSE 0 END FROM {$p}issuers_audit a WHERE a.issuer_id=c.issuer_id AND a.subject_id=c.id AND a.event='native_contract_signature_changed' ORDER BY a.id DESC LIMIT 1),0)";
        $sql="SELECT c.id+21474836480 id,c.id source_id,c.issuer_id,'company_contract' source_kind,c.client_id,c.name subject,COALESCE($customer,u.company,'') company,CASE WHEN u.userid IS NULL THEN 0 ELSE 1 END client_visible,c.start_date datestart,c.due_date dateend,SUBSTR(c.start_date,1,4) year,c.created_at dateadded,CASE c.status WHEN 'deleted' THEN 1 ELSE 0 END trash,0 signed,$signed marked_as_signed,$type contract_type,$typeName type_name,CAST($value AS DECIMAL(15,2)) contract_value,$currency currency,NULL project_id,'' project_name,c.status,c.revision FROM {$p}company_contracts c LEFT JOIN {$p}issuers_clients o ON o.client_id=c.client_id LEFT JOIN {$p}clients u ON u.userid=c.client_id AND (o.owner_issuer_id=c.issuer_id OR EXISTS(SELECT 1 FROM {$p}issuers_client_links l WHERE l.client_id=c.client_id AND l.issuer_id=c.issuer_id)) WHERE c.issuer_id=?";
        if ($authority['primary']) {
            $project=$authority['projects']?'pr.name':"''";
            $projectJoin=$authority['projects']?" LEFT JOIN {$p}projects pr ON pr.id=c.project_id AND ".\app\services\CustomerRelationBoundary::sql($this->db,$p,'projects','pr'):'';
            $sql.=" UNION ALL SELECT c.id,c.id,$issuer,'contract',c.client,c.subject,u.company,1,c.datestart,c.dateend,SUBSTR(c.datestart,1,4),c.dateadded,c.trash,CASE WHEN c.signature IS NULL OR c.signature='' THEN 0 ELSE 1 END,c.marked_as_signed,c.contract_type,t.name,c.contract_value,(SELECT name FROM {$p}currencies WHERE isdefault=1),c.project_id,$project,'legacy',NULL FROM {$p}contracts c LEFT JOIN {$p}clients u ON u.userid=c.client LEFT JOIN {$p}contracts_types t ON t.id=c.contract_type$projectJoin WHERE ".\app\services\CustomerRelationBoundary::sql($this->db,$p,'contracts','c');
        }
        $custom='';
        foreach ($definitions as $definition) {
            $id=NativeCompanyResourceId::decode('custom_field',$definition['id'])['canonical_id']; $raw=IssuerPolicy::id($definition['source_field_id']); $kind=$definition['field_source_kind'];
            if (!in_array($kind,['custom_field','company_custom_field'],true)) throw new DomainException('native_contract_columns_invalid');
            $expr="(SELECT v.value FROM {$p}company_custom_field_values v WHERE v.issuer_id=q.issuer_id AND v.field_source_kind='$kind' AND v.field_id=$raw AND v.source_kind=q.source_kind AND v.source_id=q.source_id)";
            if ($kind==='custom_field') $expr="CASE WHEN q.source_kind='contract' THEN COALESCE($expr,(SELECT v.value FROM {$p}customfieldsvalues v WHERE v.fieldto='contracts' AND v.fieldid=$raw AND v.relid=q.source_id)) ELSE $expr END";
            $custom.=",$expr cf_$id";
        }
        return ['sql'=>"SELECT q.*$custom FROM ($sql) q",'params'=>[$issuer]];
    }

    public function presentation(int $issuer,int $actor,?string $today=null):array
    {
        $authority=$this->authority($issuer,$actor); $source=$this->source($issuer,$authority); $today=$today??gmdate('Y-m-d');
        NativeCompanyInvoiceRules::dateValue($today,'Y-m-d|%Y-%m-%d');
        $day=new DateTimeImmutable($today,new DateTimeZone('UTC')); $weekBefore=$day->modify('-7 days')->format('Y-m-d'); $weekAfter=$day->modify('+7 days')->format('Y-m-d');
        $summarySql='SELECT COUNT(CASE WHEN (q.dateend>? OR q.dateend IS NULL) AND q.trash=0 THEN 1 END) count_active,COUNT(CASE WHEN q.dateend<? AND q.trash=0 THEN 1 END) count_expired,COUNT(CASE WHEN q.dateend BETWEEN ? AND ? AND q.trash=0 THEN 1 END) count_expiring,COUNT(CASE WHEN q.dateadded BETWEEN ? AND ? AND q.trash=0 THEN 1 END) count_recently_created,COUNT(CASE WHEN q.trash=1 THEN 1 END) count_trash FROM ('.$source['sql'].') q';
        $summaryArgs=[$today,$today,$weekBefore,$weekAfter,$weekBefore,$weekAfter,...$source['params']];
        $summary=$this->rows($summarySql,$summaryArgs)[0];
        $groupSql='SELECT q.contract_type,q.type_name,q.currency,COUNT(*) total,SUM(q.contract_value) amount FROM ('.$source['sql'].') q WHERE q.trash=0 GROUP BY q.contract_type,q.type_name,q.currency ORDER BY q.contract_type,q.type_name,q.currency';
        $groups=$this->rows($groupSql,$source['params']);
        $yearSql='SELECT DISTINCT q.year FROM ('.$source['sql'].') q ORDER BY q.year DESC'; $years=$this->rows($yearSql,$source['params']);
        $types=(new NativeCompanyContractTypes($this->db,$this->p))->listing($issuer,$actor,false); $catalog=[];
        foreach ($types as $type) $catalog[$type['id']]=['value'=>$type['id'],'label'=>$type['name']];
        if ($authority['primary']) foreach ($this->rows('SELECT id,name FROM '.$this->p.'contracts_types ORDER BY id') as $type) $catalog[(string)$type['id']]=['value'=>(string)$type['id'],'label'=>$type['name']];
        foreach ($groups as $group) if ($group['contract_type']!==null && !isset($catalog[(string)$group['contract_type']])) $catalog[(string)$group['contract_type']]=['value'=>(string)$group['contract_type'],'label'=>$group['type_name']];
        $base=(new NativeCompanyCurrencyReference($this->db,$this->p,$issuer,$actor))->baseCurrency();
        $counts=[]; $amounts=[];
        foreach ($catalog as $id=>$type) { $counts[$id]=0; $amounts[$id]=null; }
        foreach ($groups as $group) {
            $id=(string)$group['contract_type']; if (!isset($catalog[$id])) continue;
            $counts[$id]+=(int)$group['total'];
            // The reference chart is explicitly in base currency. Never sum another currency into it.
            if ($group['currency']===$base['name'] && $group['amount']!==null) $amounts[$id]=($amounts[$id]??0)+(float)$group['amount'];
        }
        $labels=array_column(array_values($catalog),'label');
        $chart=['labels'=>$labels,'datasets'=>[['label'=>_l('contract_summary_by_type'),'backgroundColor'=>'rgba(3,169,244,0.2)','borderColor'=>'#03a9f4','borderWidth'=>1,'data'=>array_values($counts)]]];
        $values=['labels'=>$labels,'datasets'=>[['label'=>_l('contract_summary_by_type_value'),'backgroundColor'=>'rgba(37,155,35,0.2)','borderColor'=>'#84c529','tension'=>false,'borderWidth'=>1,'data'=>array_values($amounts)]]];
        if ($this->authority($issuer,$actor)!==$authority || $this->rows($summarySql,$summaryArgs)[0]!==$summary || $this->rows($groupSql,$source['params'])!==$groups || $this->rows($yearSql,$source['params'])!==$years || (new NativeCompanyContractTypes($this->db,$this->p))->listing($issuer,$actor,false)!==$types) throw new DomainException('native_contract_snapshot_changed');
        return array_map('intval',$summary)+['native_contract_index'=>true,'native_contract_primary'=>$authority['primary'],'base_currency'=>(object)$base,'chart_types'=>json_encode($chart,JSON_THROW_ON_ERROR|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_QUOT),'chart_types_values'=>json_encode($values,JSON_THROW_ON_ERROR|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_QUOT),'options'=>['contract_type'=>array_values($catalog),'year'=>array_map(static fn($row)=>['value'=>$row['year'],'label'=>$row['year']],$years)]];
    }

    public function page(int $issuer,int $actor,array $input,$client='',?string $today=null):array
    {
        $authority=$this->authority($issuer,$actor); $custom=new NativeCompanyCustomFields($this->db,$this->p);
        $definitions=$custom->definitions($issuer,$actor,'contracts',['active'=>1,'show_on_table'=>1]);
        $fields=self::COLUMNS; $types=array_fill(0,count($fields),''); foreach ($definitions as $definition) { $fields[]='cf_'.$definition['id']; $types[]=$definition['type']; }
        $lastOrder=$input['last_order_identifier']??null;
        if (($lastOrder!==null&&$lastOrder!=='contracts') || ($input['custom_view']??'')!=='') throw new DomainException('native_contract_filter_invalid');
        $builder=$input['filters']??['match_type'=>'and','rules'=>[]];
        if (is_array($builder)&&array_keys($builder)===['match_type']&&in_array($builder['match_type'],['and','or'],true)) $builder['rules']=[];
        if (!is_array($builder)) throw new DomainException('native_contract_filter_invalid');
        unset($input['last_order_identifier'],$input['custom_view'],$input['filters']);
        $wire=NativeCompanyDataTableWire::request($input,$fields,$types);
        $store=new CompanySettingsStorage($this->db,$this->p);
        $format=$store->exists($issuer,'dateformat')?$store->get($issuer,'dateformat'):CompanySettingsCatalog::defaultValue('localization','dateformat');
        $rules=NativeCompanyContractRules::compile($builder,$today??gmdate('Y-m-d'),$format);
        $source=$this->source($issuer,$authority,$definitions); $args=$source['params']; $where=[];
        if ($client!==''&&$client!==null) { $client=IssuerPolicy::id($client); NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,'client',$client,'view'); $where[]='q.client_id=?'; $args[]=$client; }
        $base=' FROM ('.$source['sql'].') q'; $totalSql='SELECT COUNT(*) n'.$base.($where?' WHERE '.implode(' AND ',$where):''); $totalArgs=$args;
        if ($rules['sql']!=='') { $where[]=$rules['sql']; $args=array_merge($args,$rules['params']); }
        if ($wire['search']!=='') { $pattern='%'.str_replace(['!','%','_'],['!!','!%','!_'],$wire['search']).'%'; $parts=[]; foreach ($fields as $field) { $parts[]='q.'.$field." LIKE ? ESCAPE '!'"; $args[]=$pattern; } $where[]='('.implode(' OR ',$parts).')'; }
        $filteredSql='SELECT COUNT(*) n'.$base.($where?' WHERE '.implode(' AND ',$where):'');
        $total=(int)$this->rows($totalSql,$totalArgs)[0]['n']; $filtered=(int)$this->rows($filteredSql,$args)[0]['n'];
        $limit=$wire['length']===-1?$filtered:$wire['length']; if ($limit>10000) throw new DomainException('native_contract_export_limit');
        $order=implode(',',array_map(static fn($row)=>'q.'.$row['field'].' '.strtoupper($row['direction']),$wire['orders']));
        $sql='SELECT q.*'.$base.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY '.$order.',q.id ASC LIMIT '.$limit.' OFFSET '.$wire['start'];
        $rows=$this->rows($sql,$args); $projected=[];
        foreach ($rows as $row) {
            $row['id']=(string)$row['id']; NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,'contract',$row['id'],'view');
            $row['custom_values']=$definitions?$custom->values($issuer,$actor,'contracts',$row['id']):[]; $projected[]=$row;
        }
        if ($this->authority($issuer,$actor)!==$authority || $this->rows($sql,$args)!==$rows || (int)$this->rows($totalSql,$totalArgs)[0]['n']!==$total || (int)$this->rows($filteredSql,$args)[0]['n']!==$filtered || $custom->definitions($issuer,$actor,'contracts',['active'=>1,'show_on_table'=>1])!==$definitions) throw new DomainException('native_contract_snapshot_changed');
        return ['draw'=>$wire['draw'],'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'rows'=>$projected,'definitions'=>$definitions,'can_view_customers'=>$authority['customers']];
    }
}
