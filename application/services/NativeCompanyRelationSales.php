<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyResourceReferences.php';
require_once __DIR__.'/CompanyCredits.php';
require_once __DIR__.'/CompanySales.php';
require_once __DIR__.'/SalesNumberPresentation.php';
require_once __DIR__.'/NativeCompanyLegacySalesBoundary.php';
/** Original sales relation labels, preserving explicit source lineage and canonical IDs. */
final class NativeCompanyRelationSales
{
    private const MAP=['credit_note'=>['creditnotes','credit_notes','credit_note'],'estimate'=>['estimates','estimates','company_estimate'],'proposal'=>['proposals','proposals','company_proposal']];
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string $sql,array $a=[]):array{return IssuerStorage::query($this->db,$sql,$a)->result_array();}
    private function frame(int $i,int $a,string $type):array{$acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($i,$a,self::MAP[$type][1],'view'))throw new DomainException('native_relation_denied');return['primary'=>$acl->primaryId()===$i];}
    private function legacy(int $i,int $a,string $type,string $q,?array $ref):array
    {
        require_once __DIR__.'/ClientIssuerScope.php';[$table]=self::MAP[$type];
        $options=$this->rows('SELECT name,value FROM '.$this->p.'options WHERE name IN (?,?) ORDER BY name',['number_padding_prefixes','proposal_number_prefix']);$values=array_column($options,'value','name');
        $padding=$values['number_padding_prefixes']??null;if(!is_string($padding)||!preg_match('/^(0|[1-9][0-9]?)$/D',$padding)||(int)$padding>20)throw new DomainException('native_relation_number_invalid');
        $fields=$type==='proposal'?'x.id,x.subject':'x.id,x.number,x.prefix,x.number_format,x.date';
        $boundary=NativeCompanyLegacySalesBoundary::sql($this->db,$this->p,$table,'x');
        $sql='SELECT '.$fields.' FROM '.$this->p.$table.' x WHERE '.$boundary;$args=[];
        if(IssuerStorage::fieldExists($this->db,'issuer_id',$this->p.$table))$sql.=' AND x.issuer_id IS NULL';
        if($ref!==null){$sql.=' AND x.id=?';$args[]=$ref['source_id'];}
        elseif($q!==''){
            $needle='%'.str_replace(['!','%','_'],['!!','!%','!_'],$q).'%';
            if($type==='proposal'){$prefix=$values['proposal_number_prefix']??null;if(!is_string($prefix))throw new DomainException('native_relation_number_invalid');$numeric=str_starts_with($q,$prefix)?substr($q,strlen($prefix)):$q;$numeric=ltrim($numeric,'0');$sql.=" AND (x.id=? OR x.subject LIKE ? ESCAPE '!' OR x.content LIKE ? ESCAPE '!' OR x.proposal_to LIKE ? ESCAPE '!')";$args=[ctype_digit($numeric)?$numeric:'0',$needle,$needle,$needle];}
            else{$numeric=ctype_digit($q)?ltrim($q,'0'):'0';$sql.=" AND (x.number=? OR x.formatted_number LIKE ? ESCAPE '!' OR x.clientnote LIKE ? ESCAPE '!')";$args=[$numeric===''?'0':$numeric,$needle,$needle];}
        }
        $sql.=' ORDER BY x.id LIMIT 50';$rows=$this->rows($sql,$args);if($ref!==null&&count($rows)!==1)throw new DomainException('native_relation_denied');
        $out=[];foreach($rows as$r){
            if($type==='proposal'){if(!isset($values['proposal_number_prefix']))throw new DomainException('native_relation_number_invalid');$name=$values['proposal_number_prefix'].str_pad((string)$r['id'],(int)$padding,'0',STR_PAD_LEFT);}
            else{if(!in_array((string)$r['number_format'],['1','2','3','4'],true)||!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$r['date']))throw new DomainException('native_relation_number_invalid');$name=SalesNumberPresentation::format($r['number'],$r['number_format'],$r['prefix'],$r['date'],(int)$padding);}
            $out[]=['id'=>(string)$r['id'],'name'=>$name,'subtext'=>''];
        }
        if($rows!==$this->rows($sql,$args)||$options!==$this->rows('SELECT name,value FROM '.$this->p.'options WHERE name IN (?,?) ORDER BY name',['number_padding_prefixes','proposal_number_prefix']))throw new DomainException('native_relation_changed');return$out;
    }
    public function options(int $i,int $a,string $type,string $q='', $selected=null):array
    {
        if(!isset(self::MAP[$type])||strlen($q)>191||preg_match('//u',$q)!==1||preg_match('/[\x00-\x1f\x7f]/',$q))throw new DomainException('native_relation_invalid');$q=trim($q);$frame=$this->frame($i,$a,$type);[$table,$feature,$ownedSource]=self::MAP[$type];
        $ref=$selected===null||$selected===''?null:NativeCompanyResourceId::decode($type,$selected);$out=[];
        if($type==='credit_note'){
            $reader=new CompanyCredits($this->db,$this->p);
            if($ref!==null){$owner=$this->rows('SELECT issuer_id FROM '.$this->p.'creditnotes WHERE id=?',[$ref['source_id']])[0]??null;if(!$owner)throw new DomainException('native_relation_denied');
                if($owner['issuer_id']!==null){if((int)$owner['issuer_id']!==$i)throw new DomainException('native_relation_denied');$doc=$reader->get($i,$a,$ref['source_id']);$out=[['id'=>(string)$doc['id'],'name'=>$doc['number'],'subtext'=>'']];}
                elseif($frame['primary'])$out=$this->legacy($i,$a,$type,$q,$ref);else throw new DomainException('native_relation_denied');
            }else{$out=$reader->relationOptions($i,$a,$q);if($frame['primary'])$out=array_merge($out,$this->legacy($i,$a,$type,$q,null));}
        }else{
            $reader=new CompanySales($this->db,$this->p);
            if($ref===null){foreach($reader->relationOptions($i,$a,$feature,$q)as$r){$r['id']=NativeCompanyResourceId::encode($ownedSource,(int)$r['id']);$out[]=$r;}if($frame['primary'])$out=array_merge($out,$this->legacy($i,$a,$type,$q,null));}
            elseif($ref['source_kind']===$ownedSource){$doc=$reader->document($i,$a,$ref['source_id']);if($doc['kind']!==$feature)throw new DomainException('native_relation_denied');$out=[['id'=>$ref['canonical_id'],'name'=>$doc['formatted_number'],'subtext'=>'']];}
            elseif($ref['source_kind']===$type&&$frame['primary'])$out=$this->legacy($i,$a,$type,$q,$ref);else throw new DomainException('native_relation_denied');
        }
        if($frame!==$this->frame($i,$a,$type))throw new DomainException('native_relation_denied');usort($out,static fn($x,$y)=>[mb_strtolower($x['name']),$x['id']]<=>[mb_strtolower($y['name']),$y['id']]);return array_slice($out,0,50);
    }
}
