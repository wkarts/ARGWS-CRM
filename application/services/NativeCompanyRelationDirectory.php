<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
/** Bounded native relation selectors; shared customer projection never grants contact access. */
final class NativeCompanyRelationDirectory
{
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function authority(int $i,int $a,string $type):array
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->member($i,$a))throw new DomainException('native_relation_denied');
        $feature=$type==='staff'?'staff':'customers';$full=$acl->can($i,$a,$feature,'view');
        $primary=$acl->primaryId()===$i;
        if($type==='staff'){if(!$full)throw new DomainException('native_relation_denied');return[$primary,true,[]];}
        $assigned=[];
        if(!$full){
            foreach($this->rows('SELECT x.client_id FROM '.$this->p.'company_customer_admins x JOIN '.$this->p.'issuers_clients o ON o.client_id=x.client_id AND o.owner_issuer_id=x.issuer_id WHERE x.issuer_id=? AND x.staff_id=? ORDER BY x.client_id',[$i,$a])as$r)$assigned[]=(int)$r['client_id'];
            if($primary)foreach($this->rows('SELECT x.customer_id FROM '.$this->p.'customer_admins x LEFT JOIN '.$this->p.'issuers_clients o ON o.client_id=x.customer_id WHERE x.staff_id=? AND o.client_id IS NULL ORDER BY x.customer_id',[$a])as$r)$assigned[]=(int)$r['customer_id'];
            $assigned=array_values(array_unique($assigned));sort($assigned,SORT_NUMERIC);if(count($assigned)>1000)throw new DomainException('native_relation_invalid');if(!$assigned)throw new DomainException('native_relation_denied');
        }
        return[$primary,$full,$assigned];
    }
    public function options(int $i,int $a,string $type,string $q='', $selected=null, $client=null):array
    {
        if(!in_array($type,['customer','contact','staff'],true)||strlen($q)>200||preg_match('//u',$q)!==1||preg_match('/[\x00-\x1f\x7f]/',$q))throw new DomainException('native_relation_invalid');
        $q=trim($q);$selected=$selected===null||$selected===''?null:IssuerPolicy::id($selected);$client=$client===null||$client===''?null:IssuerPolicy::id($client);
        if($client!==null&&$type!=='contact')throw new DomainException('native_relation_invalid');
        $authority=$this->authority($i,$a,$type);[$primary,$full,$assigned]=$authority;
        $args=[];$connection=$this->db->pdo??$this->db->conn_id??null;$sqlite=$connection instanceof PDO&&$connection->getAttribute(PDO::ATTR_DRIVER_NAME)==="sqlite";
        if($type==='staff'){
            // A master without an explicit membership row is still a current company member.
            $sql='SELECT s.staffid id,s.firstname,s.lastname FROM '.$this->p.'staff s WHERE s.active=1 AND (EXISTS (SELECT 1 FROM '.$this->p.'issuers_staff m WHERE m.staff_id=s.staffid AND m.issuer_id=? AND m.can_view=1) OR EXISTS (SELECT 1 FROM '.$this->p.'issuers_installation_identity z WHERE z.id=1 AND z.master_staff_id=s.staffid AND s.admin=1))';$args=[$i];$idColumn='s.staffid';$search=[$sqlite?"s.firstname || ' ' || s.lastname":"CONCAT(s.firstname,' ',s.lastname)"];
        }else{
            $sql=$type==='customer'?'SELECT c.userid id,c.company':'SELECT t.id,t.firstname,t.lastname,t.email,c.company';
            $sql.=' FROM '.$this->p.'clients c '.($type==='contact'?'JOIN '.$this->p.'contacts t ON t.userid=c.userid ':'').'LEFT JOIN '.$this->p.'issuers_clients o ON o.client_id=c.userid WHERE c.active=1'.($type==='contact'?' AND t.active=1':'');
            $sql.=' AND (o.owner_issuer_id=?';$args[]=$i;
            if($primary)$sql.=' OR o.client_id IS NULL';
            if($type==='customer'&&$full){$sql.=' OR EXISTS (SELECT 1 FROM '.$this->p.'issuers_client_links l WHERE l.client_id=c.userid AND l.issuer_id=?)';$args[]=$i;}
            $sql.=')';
            if(!$full){$sql.=' AND c.userid IN ('.implode(',',array_fill(0,count($assigned),'?')).')';$args=array_merge($args,$assigned);}
            if($client!==null){$sql.=' AND c.userid=?';$args[]=$client;}
            $idColumn=$type==='customer'?'c.userid':'t.id';$search=$type==='customer'?['c.company']:[$sqlite?"t.firstname || ' ' || t.lastname":"CONCAT(t.firstname,' ',t.lastname)",'t.email'];
        }
        if($selected!==null){$sql.=' AND '.$idColumn.'=?';$args[]=$selected;}
        elseif($q!==''){
            $like='%'.str_replace(['!','%','_'],['!!','!%','!_'],$q).'%';
            if($type==='customer'){
                $name=$sqlite?"directory_contact.firstname || ' ' || directory_contact.lastname":"CONCAT(directory_contact.firstname,' ',directory_contact.lastname)";
                // Shared display projection is not permission to search that customer's private contacts.
                $sql.=" AND (c.company LIKE ? ESCAPE '!' OR ((o.owner_issuer_id=?".($primary?' OR o.client_id IS NULL':'').") AND EXISTS (SELECT 1 FROM ".$this->p."contacts directory_contact WHERE directory_contact.userid=c.userid AND directory_contact.active=1 AND (".$name." LIKE ? ESCAPE '!' OR directory_contact.email LIKE ? ESCAPE '!'))))";
                array_push($args,$like,$i,$like,$like);
            }else{$sql.=' AND ('.implode(' OR ',array_map(fn($column)=>$column." LIKE ? ESCAPE '!'",$search)).')';foreach($search as$_)$args[]=$like;}
        }
        $rows=$this->rows($sql.' ORDER BY '.$idColumn.' ASC LIMIT 50',$args);
        if($selected!==null&&count($rows)!==1)throw new DomainException('native_relation_denied');
        if($rows!==$this->rows($sql.' ORDER BY '.$idColumn.' ASC LIMIT 50',$args)||$authority!==$this->authority($i,$a,$type))throw new DomainException('native_relation_denied');
        $out=[];foreach($rows as$r){$name=$type==='customer'?(string)$r['company']:trim((string)$r['firstname'].' '.(string)$r['lastname']);$out[]=['id'=>(string)$r['id'],'name'=>$name,'subtext'=>$type==='contact'?(string)$r['email']:''];}return$out;
    }
}
