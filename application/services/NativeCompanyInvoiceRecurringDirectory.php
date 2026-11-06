<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyInvoiceRecurring.php';
require_once __DIR__.'/NativeCompanyDataTableWire.php';

/** Original recurring-table columns over genuine owned schedules. Schedule IDs never become invoice IDs. */
final class NativeCompanyInvoiceRecurringDirectory
{
    public const FIELDS=['number','total','year','customer','frequency','cycles_remaining','last_date','next_date'];
    public function __construct(private $db,private string $p)
    {if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}

    private function snapshot(int $issuer,int $actor):array
    {
        $reader=new CompanyInvoiceRecurring($this->db,$this->p);
        $documents=$reader->directory($issuer,$actor);$rows=[];$years=[];$agents=[];
        $acl=new IssuerResourceAccess($this->db,$this->p);
        foreach($documents as$document){
            $payload=$document['payload'];$config=$document['config'];
            $amounts=CompanyInvoiceLines::calculate($payload['items'],array_intersect_key($payload,array_flip(['discount_mode','discount_value','discount_type','adjustment','calculation_contract'])));
            $year=substr($payload['date'],0,4);$years[$year]=['year'=>$year];
            $agent=(int)($payload['native_fields']['sale_agent']??0);
            if($agent>0&&$acl->member($issuer,$agent)&&!isset($agents[$agent])){
                $staff=IssuerStorage::query($this->db,'SELECT firstname,lastname FROM '.$this->p.'staff WHERE staffid=?',[$agent])->result_array()[0]??null;
                if($staff)$agents[$agent]=['sale_agent'=>$agent,'full_name'=>trim($staff['firstname'].' '.$staff['lastname'])];
            }
            $last='';$lastInvoice=0;foreach($document['runs']as$run)if($run['canonical_invoice_date']!==null&&(int)$run['canonical_invoice_id']>$lastInvoice){$lastInvoice=(int)$run['canonical_invoice_id'];$last=$run['canonical_invoice_date'];}
            $units=['day'=>'dias','week'=>'semanas','month'=>'meses','year'=>'anos'];
            $rows[]=['id'=>(int)$document['id'],'number'=>(int)$document['id'],'total'=>$amounts['total'],'year'=>$year,
                'customer'=>(string)$document['context']['recipient']['name'],'currency'=>(string)$document['context']['currency']['name'],
                'frequency'=>$config['interval'].' '.$units[$config['unit']],
                'cycles_remaining'=>$config['cycles']===0?null:max(0,$config['cycles']-(int)$document['completed_cycles']),
                'last_date'=>$last,'next_date'=>in_array($document['state'],['completed','cancelled'],true)?'':$document['next_date'],
                'sale_agent'=>$agent,'state'=>$document['state'],'mode'=>$document['mode']['mode'],
                'principal_invoice_id'=>$document['mode']['principal_invoice_id']];
        }
        if(!$acl->can($issuer,$actor,'invoices','view'))throw new DomainException('company_invoice_recurring_denied');
        krsort($years,SORT_STRING);$agents=array_values($agents);usort($agents,static fn($a,$b)=>[$a['full_name'],$a['sale_agent']]<=>[$b['full_name'],$b['sale_agent']]);
        return ['rows'=>$rows,'invoices_years'=>array_values($years),'invoices_sale_agents'=>$agents];
    }
    public function catalogue($issuer,$actor):array
    {
        $snapshot=$this->snapshot(IssuerPolicy::id($issuer),IssuerPolicy::id($actor));unset($snapshot['rows']);return$snapshot;
    }
    public function table($issuer,$actor,array $input):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$snapshot=$this->snapshot($issuer,$actor);$years=[];$agents=[];
        $allowedYears=array_column($snapshot['invoices_years'],'year');$allowedAgents=array_column($snapshot['invoices_sale_agents'],'sale_agent');
        foreach($input as$key=>$value){
            if(!preg_match('/^(year|sale_agent)_([1-9][0-9]*)$/D',(string)$key,$match))continue;
            unset($input[$key]);if($value==='')continue;
            if(!is_string($value)&&!is_int($value)||((string)$value!==$match[2]))throw new DomainException('native_company_table_request_invalid');
            if($match[1]==='year'){
                if(!in_array((string)$value,array_map('strval',$allowedYears),true))throw new DomainException('native_company_table_request_invalid');$years[]=(string)$value;
            }else{
                if(!in_array((int)$value,$allowedAgents,true))throw new DomainException('native_company_table_request_invalid');$agents[]=(int)$value;
            }
        }
        if(isset($input['last_order_identifier'])&&$input['last_order_identifier']!=='recurring-invoices')throw new DomainException('native_company_table_request_invalid');
        unset($input['last_order_identifier']);$request=NativeCompanyDataTableWire::request($input,self::FIELDS);$rows=$snapshot['rows'];$total=count($rows);
        $search=mb_strtolower($request['search'],'UTF-8');
        $rows=array_values(array_filter($rows,static function($row)use($years,$agents,$search){
            if($years&&!in_array($row['year'],$years,true)||$agents&&!in_array($row['sale_agent'],$agents,true))return false;
            if($search==='')return true;
            $values=['Série #'.$row['id'],$row['total'],$row['year'],$row['customer'],$row['frequency'],$row['cycles_remaining']===null?'Sem limite':(string)$row['cycles_remaining'],$row['last_date'],$row['next_date']];
            foreach($values as$value)if(str_contains(mb_strtolower($value,'UTF-8'),$search))return true;return false;
        }));
        usort($rows,static function($a,$b)use($request){foreach($request['orders']as$order){$field=$order['field'];$left=$a[$field];$right=$b[$field];if($field==='total'){$left=CompanyInvoiceAmounts::minor($left);$right=CompanyInvoiceAmounts::minor($right);}elseif($field==='cycles_remaining'){$left=$left??PHP_INT_MAX;$right=$right??PHP_INT_MAX;}$result=$left<=>$right;if($result)return$order['direction']==='asc'?$result:-$result;}return$a['id']<=>$b['id'];});
        return ['draw'=>$request['draw'],'iTotalRecords'=>$total,'iTotalDisplayRecords'=>count($rows),'rows'=>array_slice($rows,$request['start'],$request['length']===-1?null:$request['length'])];
    }
}
