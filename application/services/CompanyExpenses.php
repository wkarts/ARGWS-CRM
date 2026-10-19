<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';
require_once __DIR__.'/CompanyExpenseSchema.php';

/** Company-owned expense recording, not a payment order or cash/bank settlement. */
final class CompanyExpenses
{
    private IssuerResourceAccess $acl;
    public function __construct(private $db,private string $p)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');$this->acl=new IssuerResourceAccess($db,$p);
    }
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function authorize(int $issuer,int $actor,string $cap):void
    {
        if(!$this->acl->can($issuer,$actor,'expenses','view')||!$this->acl->can($issuer,$actor,'expenses',$cap))throw new DomainException('company_expense_denied');
    }
    private function transaction(int $issuer,int $actor,string $cap,callable $work)
    {
        CompanyExpenseSchema::assertValid($this->db,$this->p);
        return IssuerTransaction::run($this->db,$this->p,['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_clients','issuers_client_links','clients','company_projects','company_expenses','company_expense_categories','company_expense_requests','company_expense_files','issuers_audit'],function()use($issuer,$actor,$cap,$work){$this->acl->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,$cap);$result=$work();$this->authorize($issuer,$actor,$cap);return $result;});
    }
    public static function money(int $cents):string{return intdiv($cents,100).'.'.str_pad((string)($cents%100),2,'0',STR_PAD_LEFT);}
    private static function text($value,int $max,bool $required=true):string
    {
        if(!is_string($value)||strlen($value)>$max||preg_match('//u',$value)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$value)||($required&&trim($value)===''))throw new DomainException('company_expense_invalid');return trim($value);
    }
    private static function decimal($value,int $max):int
    {
        if(!is_string($value)||!preg_match('/^(0|[1-9][0-9]{0,9})(?:\.([0-9]{1,2}))?$/D',$value,$m))throw new DomainException('company_expense_invalid');$cents=(int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');if($cents>$max)throw new DomainException('company_expense_invalid');return $cents;
    }
    private function normalize(array $input):array
    {
        if(array_diff(array_keys($input),['category_id','client_id','project_id','currency','date','description','note','amount','tax_rate']))throw new DomainException('company_expense_invalid');
        $date=$input['date']??null;$parsed=is_string($date)?DateTimeImmutable::createFromFormat('!Y-m-d',$date):false;
        if(!$parsed||$parsed->format('Y-m-d')!==$date||$date<'2000-01-01'||$date>'2100-12-31')throw new DomainException('company_expense_invalid');
        $currency=$input['currency']??null;if(!is_string($currency)||!in_array($currency,['BRL','USD','EUR'],true))throw new DomainException('company_expense_invalid');
        $amount=self::decimal($input['amount']??null,99999999999);if($amount<1)throw new DomainException('company_expense_invalid');
        $taxRate=self::decimal($input['tax_rate']??'0.00',10000);$tax=intdiv($amount*$taxRate+5000,10000);
        $client=$input['client_id']??0;$client=in_array($client,['',0,'0',null],true)?0:IssuerPolicy::id($client);
        $project=$input['project_id']??0;$project=in_array($project,['',0,'0',null],true)?0:IssuerPolicy::id($project);
        return ['project_id'=>$project,'category_id'=>IssuerPolicy::id($input['category_id']??null),'client_id'=>$client,'currency'=>$currency,'expense_date'=>$date,'description'=>self::text($input['description']??null,191),'note'=>self::text($input['note']??'',4000,false),'amount_cents'=>$amount,'tax_cents'=>$tax,'tax_rate'=>$taxRate,'total_cents'=>$amount+$tax];
    }
    private static function revision(array $row):string{return hash('sha256',json_encode($row,JSON_THROW_ON_ERROR));}
    private function receipt(int $issuer,int $actor,string $operation,string $key,array $input,?callable $confirm=null):?int
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_expense_invalid');
        $row=$this->rows('SELECT * FROM '.$this->p.'company_expense_requests WHERE request_key=? FOR UPDATE',[$key])[0]??null;if(!$row)return null;
        if((int)$row['issuer_id']!==$issuer||(int)$row['actor_id']!==$actor||$row['operation']!==$operation||!hash_equals($row['input_hash'],hash('sha256',json_encode($input,JSON_THROW_ON_ERROR))))throw new DomainException('company_expense_conflict');
        $id=(int)$row['result_id'];if($confirm)$confirm($id);return $id;
    }
    private function writeReceipt(int $issuer,int $actor,string $operation,string $key,array $input,int $id):void
    {
        $hash=hash('sha256',json_encode($input,JSON_THROW_ON_ERROR));IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_expense_requests(request_key,issuer_id,actor_id,operation,input_hash,result_id,created_at) VALUES(?,?,?,?,?,?,?)',[$key,$issuer,$actor,$operation,$hash,$id,date('Y-m-d H:i:s')]);
        if(!$this->rows('SELECT request_key FROM '.$this->p.'company_expense_requests WHERE request_key=? AND issuer_id=? AND actor_id=? AND operation=? AND input_hash=? AND result_id=?',[$key,$issuer,$actor,$operation,$hash,$id]))throw new RuntimeException('issuer_expense_write_unconfirmed');
    }
    private function audit(int $issuer,int $actor,int $id,string $event,array $extra=[]):void
    {
        $json=json_encode($extra,JSON_THROW_ON_ERROR);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$actor,$event,$issuer,$id,date('Y-m-d H:i:s'),$json]);$audit=(int)$this->db->insert_id();
        if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND subject_id=? AND event=? AND metadata_json=?',[$audit,$issuer,$id,$event,$json]))throw new RuntimeException('issuer_expense_write_unconfirmed');
    }
    private function reference(int $issuer,int $actor,array &$input):void
    {
        if(!$this->rows('SELECT id FROM '.$this->p.'company_expense_categories WHERE id=? AND issuer_id=? AND active=1 FOR UPDATE',[$input['category_id'],$issuer]))throw new DomainException('company_expense_reference_denied');
        $input['project_name']='';if($input['project_id']!==0){
            if(!$this->acl->can($issuer,$actor,'projects','view'))throw new DomainException('company_expense_reference_denied');
            $project=$this->rows('SELECT name,client_id FROM '.$this->p.'company_projects WHERE id=? AND issuer_id=? AND status=? FOR UPDATE',[$input['project_id'],$issuer,'active'])[0]??null;
            if(!$project||(int)$project['client_id']!==$input['client_id'])throw new DomainException('company_expense_reference_denied');$input['project_name']=self::text($project['name'],191);
        }
        $input['client_name']='';if($input['client_id']!==0){
            if(!$this->acl->can($issuer,$actor,'customers','view'))throw new DomainException('company_expense_reference_denied');
            $client=$this->rows('SELECT c.company FROM '.$this->p.'clients c JOIN '.$this->p.'issuers_clients o ON o.client_id=c.userid WHERE c.userid=? AND c.active=1 AND (o.owner_issuer_id=? OR EXISTS(SELECT 1 FROM '.$this->p.'issuers_client_links l WHERE l.client_id=c.userid AND l.issuer_id=?)) FOR UPDATE',[$input['client_id'],$issuer,$issuer])[0]??null;
            if(!$client)throw new DomainException('company_expense_reference_denied');$input['client_name']=self::text($client['company'],191);
        }
    }
    private function locked(int $issuer,int $id):array
    {
        $row=$this->rows('SELECT * FROM '.$this->p.'company_expenses WHERE id=? AND issuer_id=? FOR UPDATE',[$id,$issuer])[0]??null;if(!$row)throw new DomainException('company_expense_denied');return $row;
    }
    private function confirm(int $issuer,int $id,array $expected):void
    {
        $row=$this->locked($issuer,$id);foreach($expected as $field=>$value)if((string)$row[$field]!== (string)$value)throw new RuntimeException('issuer_expense_write_unconfirmed');
    }
    public function create($issuer,$actor,array $input,string $key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$data=$this->normalize($input);
        return $this->transaction($issuer,$actor,'create',function()use($issuer,$actor,$data,$key):int{
            $retry=$this->receipt($issuer,$actor,'create',$key,$data,fn(int $id)=>$this->locked($issuer,$id));if($retry!==null)return $retry;
            $fields=$data;$this->reference($issuer,$actor,$fields);$max=$this->rows('SELECT COALESCE(MAX(number),0) n FROM '.$this->p.'company_expenses WHERE issuer_id=?',[$issuer])[0];$number=(int)$max['n']+1;if($number>2147483647)throw new DomainException('company_expense_invalid');
            $fields+=['issuer_id'=>$issuer,'number'=>$number,'state'=>'recorded','revision'=>1,'created_by'=>$actor,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')];
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_expenses (`'.implode('`,`',array_keys($fields)).'`) VALUES ('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));$id=(int)$this->db->insert_id();if($id<1)throw new RuntimeException('issuer_expense_write_unconfirmed');$this->confirm($issuer,$id,$fields);
            $this->audit($issuer,$actor,$id,'company_expense_created',['number'=>$number,'total_cents'=>$fields['total_cents'],'currency'=>$fields['currency']]);$this->writeReceipt($issuer,$actor,'create',$key,$data,$id);return $id;
        });
    }
    public function change($issuer,$actor,$id,string $revision,string $key,?array $input=null):void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$data=$input===null?null:$this->normalize($input);$op=$data===null?'cancel':'update';$payload=[$id,$revision,$data];
        $this->transaction($issuer,$actor,$op==='cancel'?'delete':'edit',function()use($issuer,$actor,$id,$revision,$key,$data,$op,$payload):void{
            $old=$this->locked($issuer,$id);if($this->receipt($issuer,$actor,$op,$key,$payload)!==null)return;
            if(!preg_match('/^[a-f0-9]{64}$/D',$revision)||!hash_equals(self::revision($old),$revision))throw new DomainException('company_expense_stale');if($old['state']!=='recorded')throw new DomainException('company_expense_immutable');
            $fields=$data??['state'=>'cancelled'];if($data!==null){if($data['currency']!==$old['currency'])throw new DomainException('company_expense_immutable');$this->reference($issuer,$actor,$fields);}
            $fields+=['revision'=>(int)$old['revision']+1,'updated_at'=>date('Y-m-d H:i:s')];$set=[];foreach($fields as $field=>$value)$set[]='`'.$field.'`=?';IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_expenses SET '.implode(',',$set).' WHERE id=? AND issuer_id=?',array_merge(array_values($fields),[$id,$issuer]));$this->confirm($issuer,$id,$fields);
            $this->audit($issuer,$actor,$id,'company_expense_'.$op,['previous_revision'=>$old['revision'],'revision'=>$fields['revision']]);$this->writeReceipt($issuer,$actor,$op,$key,$payload,$id);
        });
    }
    public function get($issuer,$actor,$id):?array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$this->authorize($issuer,$actor,'view');$row=$this->rows('SELECT e.*,c.name category_name FROM '.$this->p.'company_expenses e JOIN '.$this->p.'company_expense_categories c ON c.id=e.category_id AND c.issuer_id=e.issuer_id WHERE e.id=? AND e.issuer_id=?',[$id,$issuer])[0]??null;
        $this->authorize($issuer,$actor,'view');if(!$row)return null;$category=$row['category_name'];unset($row['category_name']);$row['revision_token']=self::revision($row);$row['category_name']=$category;return $row;
    }
    public function page($issuer,$actor,$after=0,int $limit=50,string $search=''):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$after=$after===0?0:IssuerPolicy::id($after);$search=self::text($search,191,false);if($limit<1||$limit>100)throw new DomainException('company_expense_invalid');$this->authorize($issuer,$actor,'view');$like='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';
        $rows=$this->rows('SELECT e.id,e.number,e.expense_date,e.description,e.currency,e.total_cents,e.state,c.name category_name FROM '.$this->p.'company_expenses e JOIN '.$this->p.'company_expense_categories c ON c.id=e.category_id AND c.issuer_id=e.issuer_id WHERE e.issuer_id=? AND e.id>? AND (e.description LIKE ? ESCAPE \'!\' OR c.name LIKE ? ESCAPE \'!\') ORDER BY e.id LIMIT '.($limit+1),[$issuer,$after,$like,$like]);$this->authorize($issuer,$actor,'view');$more=count($rows)>$limit;if($more)array_pop($rows);return ['items'=>$rows,'next'=>$more?(int)$rows[count($rows)-1]['id']:null];
    }
    public function projectOptions($issuer,$actor):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$this->authorize($issuer,$actor,'view');if(!$this->acl->can($issuer,$actor,'projects','view'))return [];
        $rows=$this->rows('SELECT id,name FROM '.$this->p.'company_projects WHERE issuer_id=? AND status=? ORDER BY id LIMIT 100',[$issuer,'active']);$this->authorize($issuer,$actor,'view');if(!$this->acl->can($issuer,$actor,'projects','view'))throw new DomainException('company_expense_reference_denied');return $rows;
    }
    public function categories($issuer,$actor):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$this->authorize($issuer,$actor,'view');$rows=$this->rows('SELECT * FROM '.$this->p.'company_expense_categories WHERE issuer_id=? ORDER BY id LIMIT 500',[$issuer]);foreach($rows as &$row)$row['revision_token']=self::revision($row);unset($row);$this->authorize($issuer,$actor,'view');return $rows;
    }
    public function saveCategory($issuer,$actor,string $name,string $key,$id=null,?string $revision=null,bool $active=true):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$name=self::text($name,191);$id=$id===null?null:IssuerPolicy::id($id);$payload=[$name,$id,$revision,$active];
        return $this->transaction($issuer,$actor,$id===null?'create':'edit',function()use($issuer,$actor,$name,$key,$id,$revision,$active,$payload):int{
            $retry=$this->receipt($issuer,$actor,'category',$key,$payload);if($retry!==null)return $retry;
            if($this->rows('SELECT id FROM '.$this->p.'company_expense_categories WHERE issuer_id=? AND name=? AND id<>?',[$issuer,$name,$id??0]))throw new DomainException('company_expense_category_conflict');
            if($id===null){$count=(int)$this->rows('SELECT COUNT(*) n FROM '.$this->p.'company_expense_categories WHERE issuer_id=?',[$issuer])[0]['n'];if($count>=500)throw new DomainException('company_expense_invalid');IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_expense_categories(issuer_id,name,active,revision,created_at) VALUES(?,?,?,1,?)',[$issuer,$name,(int)$active,date('Y-m-d H:i:s')]);$id=(int)$this->db->insert_id();}
            else{$old=$this->rows('SELECT * FROM '.$this->p.'company_expense_categories WHERE id=? AND issuer_id=? FOR UPDATE',[$id,$issuer])[0]??null;if(!$old)throw new DomainException('company_expense_denied');if($revision===null||!hash_equals(self::revision($old),$revision))throw new DomainException('company_expense_stale');IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_expense_categories SET name=?,active=?,revision=revision+1 WHERE id=? AND issuer_id=?',[$name,(int)$active,$id,$issuer]);}
            if(!$this->rows('SELECT id FROM '.$this->p.'company_expense_categories WHERE id=? AND issuer_id=? AND name=? AND active=?',[$id,$issuer,$name,(int)$active]))throw new RuntimeException('issuer_expense_write_unconfirmed');$this->audit($issuer,$actor,$id,'company_expense_category_saved');$this->writeReceipt($issuer,$actor,'category',$key,$payload,$id);return $id;
        });
    }
}
