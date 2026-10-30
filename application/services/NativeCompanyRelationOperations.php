<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyResourceReferences.php';
require_once __DIR__.'/CompanyContracts.php';
require_once __DIR__.'/CompanyExpenses.php';
require_once __DIR__.'/CompanyLeads.php';
/** Read-only relation DTOs using existing owned readers and proved historical native rows. */
final class NativeCompanyRelationOperations
{
    private const TYPES=['contract'=>['contracts','company_contract',CompanyContracts::class],'expense'=>['expenses','company_expense',CompanyExpenses::class],'lead'=>['leads','company_lead',CompanyLeads::class]];
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string $sql,array $a=[]):array{return IssuerStorage::query($this->db,$sql,$a)->result_array();}
    private function frame(int $i,int $a,string $type):array{$acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($i,$a,self::TYPES[$type][0],'view'))throw new DomainException('native_relation_denied');return['primary'=>$acl->primaryId()===$i];}
    private function label(string $type,array $r,bool $legacy):string
    {
        if($type==='contract')return(string)$r[$legacy?'subject':'name'];
        if($type==='lead')return(string)$r['name'].(($r['email']??'')!==''?' - '.$r['email']:'');
        $name=(string)$r['category_name'];$description=(string)$r[$legacy?'expense_name':'description'];return$name.($description!==''?' ('.$description.')':'');
    }
    private function owned(int $i,int $a,string $type,string $q,?array $selected):array
    {
        [, $source,$class]=self::TYPES[$type];$service=new $class($this->db,$this->p);
        if($selected!==null){if($selected['source_kind']!==$source)throw new DomainException('native_relation_denied');$r=$service->get($i,$a,$selected['source_id']);if(!$r)throw new DomainException('native_relation_denied');$rows=[$r];}
        else{$rows=match($type){'contract'=>$service->page($i,$a,0,$q)['items'],'expense'=>$service->page($i,$a,0,50,$q)['items'],'lead'=>$service->page($i,$a,0,50,$q,'active')['items']};}
        $out=[];foreach($rows as$r)$out[]=['id'=>NativeCompanyResourceId::encode($source,(int)$r['id']),'name'=>$this->label($type,$r,false),'subtext'=>''];return$out;
    }
    private function legacy(int $i,int $a,string $type,string $q,?array $selected):array
    {
        require_once __DIR__.'/CustomerRelationBoundary.php';require_once __DIR__.'/ClientIssuerScope.php';
        [$table]=self::TYPES[$type];$args=[];
        if($type==='expense'){$sql='SELECT x.id,x.expense_name,c.name category_name FROM '.$this->p.'expenses x JOIN '.$this->p.'expenses_categories c ON c.id=x.category';$fields=['x.expense_name','c.name'];}
        elseif($type==='contract'){$sql='SELECT x.id,x.subject FROM '.$this->p.'contracts x';$fields=['x.subject'];}
        else{$sql='SELECT x.id,x.name,x.email FROM '.$this->p.'leads x';$fields=['x.name','x.email','x.company'];}
        $boundary=$type==='lead'?\app\services\ClientIssuerScope::legacySql($this->db,$this->p,'x','client_id'):\app\services\CustomerRelationBoundary::sql($this->db,$this->p,$table,'x');
        $sql.=' WHERE '.$boundary.($type==='lead'?' AND x.junk=0':'');
        if(IssuerStorage::fieldExists($this->db,'issuer_id',$this->p.$table))$sql.=' AND x.issuer_id IS NULL';
        if($selected!==null){if($selected['source_kind']!==$type)throw new DomainException('native_relation_denied');$sql.=' AND x.id=?';$args[]=$selected['source_id'];}
        elseif($q!==''){$needle='%'.str_replace(['!','%','_'],['!!','!%','!_'],$q).'%';$sql.=' AND ('.implode(' OR ',array_map(fn($f)=>$f." LIKE ? ESCAPE '!'",$fields)).')';foreach($fields as$_)$args[]=$needle;}
        $sql.=' ORDER BY x.id LIMIT 50';$rows=$this->rows($sql,$args);if($selected!==null&&count($rows)!==1)throw new DomainException('native_relation_denied');
        if($rows!==$this->rows($sql,$args))throw new DomainException('native_relation_changed');
        $out=[];foreach($rows as$r)$out[]=['id'=>(string)$r['id'],'name'=>$this->label($type,$r,true),'subtext'=>''];return$out;
    }
    public function options(int $i,int $a,string $type,string $q='', $selected=null):array
    {
        if(!isset(self::TYPES[$type])||strlen($q)>191||preg_match('//u',$q)!==1||preg_match('/[\x00-\x1f\x7f]/',$q))throw new DomainException('native_relation_invalid');$q=trim($q);$frame=$this->frame($i,$a,$type);
        $ref=$selected===null||$selected===''?null:NativeCompanyResourceId::decode($type,$selected);$out=[];
        if($ref===null||$ref['source_kind']===self::TYPES[$type][1])$out=$this->owned($i,$a,$type,$q,$ref);
        if($ref===null||$ref['source_kind']===$type){if($frame['primary'])$out=array_merge($out,$this->legacy($i,$a,$type,$q,$ref));elseif($ref!==null)throw new DomainException('native_relation_denied');}
        if($frame!==$this->frame($i,$a,$type))throw new DomainException('native_relation_denied');
        usort($out,static fn($x,$y)=>[mb_strtolower($x['name']),$x['id']]<=>[mb_strtolower($y['name']),$y['id']]);return array_slice($out,0,50);
    }
}
