<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyCredits.php';
require_once __DIR__.'/NativeCompanyCreditColumns.php';
require_once __DIR__.'/NativeCompanyCreditRules.php';
require_once __DIR__.'/CompanySettingsStorage.php';
require_once __DIR__.'/CompanySettingsCatalog.php';

/** Original table data from canonical credit snapshots and reconciled local movements. */
final class NativeCompanyCreditDirectory
{
    public const FIELDS=['number','date','company','status','project_name','reference_no','total','remaining_amount'];
    public function __construct(private $db, private string $p)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D',$p)) throw new InvalidArgumentException('Invalid prefix');
    }
    private function rows(string $sql, array $args=[]): array { return IssuerStorage::query($this->db,$sql,$args)->result_array(); }
    private function authorize(int $issuer, int $actor): void
    {
        if (!(new IssuerResourceAccess($this->db,$this->p))->can($issuer,$actor,'credit_notes','view')) throw new DomainException('company_credit_access_denied');
    }
    private function transaction(int $issuer, int $actor, callable $work)
    {
        $this->authorize($issuer,$actor); CompanyCreditSchema::assertValid($this->db,$this->p);
        $tables=array_values(array_unique(array_merge(['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','creditnotes','credits','creditnote_refunds','invoices','issuers_invoice_documents','company_credit_documents','company_credit_entries','currencies','company_currency_units','issuers_audit','issuers_clients','issuers_client_links','clients','options','customfields','customfieldsvalues'],(new CompanyInvoiceBalance($this->db,$this->p))->tables(),(new NativeCompanyCustomFields($this->db,$this->p))->tableNames())));
        return IssuerTransaction::run($this->db,$this->p,$tables,function() use($issuer,$actor,$work) {
            (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);
            $this->authorize($issuer,$actor); $result=$work(); $this->authorize($issuer,$actor); return $result;
        });
    }
    private function expressions(NativeCompanyCreditColumns $columns): array
    {
        $conn=$this->db->pdo??$this->db->conn_id??null;
        $sqlite=$conn instanceof PDO&&$conn->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        $name=$sqlite?"json_extract(d.recipient_json,'$.name')":"JSON_UNQUOTE(JSON_EXTRACT(d.recipient_json,'$.name'))";
        $remaining='(CASE WHEN c.status=3 THEN 0 ELSE c.total-(SELECT COALESCE(SUM(x.amount),0) FROM '.$this->p.'credits x WHERE x.credit_id=c.id)-(SELECT COALESCE(SUM(r.amount),0) FROM '.$this->p.'creditnote_refunds r WHERE r.credit_note_id=c.id) END)';
        $out=[];
        foreach (['number'=>'c.number','date'=>'c.date','company'=>$name,'status'=>'c.status','project_name'=>"''",'reference_no'=>"COALESCE(c.reference_no,'')",'total'=>'c.total','remaining_amount'=>$remaining,'year'=>'SUBSTR(c.date,1,4)'] as $key=>$sql) $out[$key]=['sql'=>$sql,'params'=>[]];
        foreach ($columns->definitions() as $definition) {
            $projection=$columns->expression($definition['id']);
            $out['custom_field_'.$definition['id']]=$projection;
            if (isset(NativeCompanyInvoiceCustomRules::TYPES[$definition['type']])) $out[$definition['slug']]=$projection;
        }
        return $out;
    }
    public function catalog(int $issuer, int $actor): array
    {
        return $this->transaction($issuer,$actor,function() use($issuer,$actor) {
            $years=$this->rows('SELECT DISTINCT SUBSTR(c.date,1,4) year FROM '.$this->p.'creditnotes c JOIN '.$this->p.'company_credit_documents d ON d.credit_id=c.id AND d.issuer_id=c.issuer_id WHERE c.issuer_id=? ORDER BY year DESC',[$issuer]);
            foreach ($years as $year) if (!preg_match('/^[1-9][0-9]{3}$/D',(string)$year['year'])) throw new DomainException('company_credit_balance_review');
            return ['options'=>['status'=>array_map(static fn($status)=>['value'=>(string)$status,'label'=>_l(['1'=>'credit_note_status_open','2'=>'credit_note_status_closed','3'=>'credit_note_status_void'][$status])],[1,2,3]),'year'=>array_map(static fn($row)=>['value'=>(string)$row['year'],'label'=>(string)$row['year']],$years)],'customDefinitions'=>(new NativeCompanyCreditColumns($this->db,$this->p,$issuer,$actor))->definitions()];
        });
    }
    public function listing(int $issuer,int $actor,array $request,array $builder=['match_type'=>'and','rules'=>[]],?int $client=null): array
    {
        if (!is_int($request['start']??null)||$request['start']<0||!is_int($request['length']??null)||($request['length']<1&&$request['length']!==-1)||!is_string($request['search']??null)||mb_strlen($request['search'])>191||!is_array($request['orders']??null)) throw new DomainException('native_credit_table_invalid');
        return $this->transaction($issuer,$actor,function() use($issuer,$actor,$request,$builder,$client) {
            $columns=new NativeCompanyCreditColumns($this->db,$this->p,$issuer,$actor); $definitions=$columns->definitions(); $expressions=$this->expressions($columns);
            $settings=new CompanySettingsStorage($this->db,$this->p);
            $format=$settings->exists($issuer,'dateformat')?$settings->get($issuer,'dateformat'):CompanySettingsCatalog::defaultValue('localization','dateformat');
            $filter=NativeCompanyCreditRules::compile($builder,$expressions,gmdate('Y-m-d'),$format,$definitions);
            $scope=' FROM '.$this->p.'creditnotes c JOIN '.$this->p.'company_credit_documents d ON d.credit_id=c.id AND d.issuer_id=c.issuer_id WHERE c.issuer_id=?'; $args=[$issuer];
            if ($client!==null) { $scope.=' AND c.clientid=?'; $args[]=IssuerPolicy::id($client); }
            $total=(int)$this->rows('SELECT COUNT(*) n'.$scope,$args)[0]['n'];
            if ($filter['sql']!=='') { $scope.=' AND '.$filter['sql']; $args=array_merge($args,$filter['params']); }
            $tableFields=self::FIELDS;
            foreach ($columns->definitions(true) as $definition) $tableFields[]='custom_field_'.$definition['id'];
            if ($request['search']!=='') {
                $search=[]; $needle='%'.strtr($request['search'],['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
                foreach ($tableFields as $field) { $search[]='CAST('.$expressions[$field]['sql']." AS CHAR) LIKE ? ESCAPE '!'"; $args=array_merge($args,$expressions[$field]['params'],[$needle]); }
                $scope.=' AND ('.implode(' OR ',$search).')';
            }
            $filtered=(int)$this->rows('SELECT COUNT(*) n'.$scope,$args)[0]['n']; $sort=[]; $sortArgs=[];
            foreach ($request['orders'] as $order) {
                $field=$order['field']??null; $direction=$order['direction']??null;
                if (!in_array($field,$tableFields,true)||!in_array($direction,['asc','desc'],true)) throw new DomainException('native_credit_table_invalid');
                $sort[]=$expressions[$field]['sql'].' '.strtoupper($direction); $sortArgs=array_merge($sortArgs,$expressions[$field]['params']);
            }
            $sort[]='c.id ASC';
            $limit=$request['length']===-1?'':' LIMIT '.$request['length'].' OFFSET '.$request['start'];
            $rows=$this->rows('SELECT c.id,c.reference_no'.$scope.' ORDER BY '.implode(',',$sort).$limit,array_merge($args,$sortArgs));
            $reader=new CompanyCredits($this->db,$this->p); $out=[];
            foreach ($rows as $row) {
                $document=$reader->inOwnedTransaction($issuer,$actor,(int)$row['id']);
                $out[]=['document'=>$document,'reference_no'=>(string)($row['reference_no']??''),'project_name'=>'','custom_values'=>$columns->values($document['id'])];
            }
            $columns->recheck();
            return ['rows'=>$out,'total'=>$total,'filtered'=>$filtered,'definitions'=>$columns->definitions(true)];
        });
    }
}
