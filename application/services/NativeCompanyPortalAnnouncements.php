<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyPortal.php';
require_once __DIR__.'/NativeCompanyResourceId.php';
require_once __DIR__.'/NativeCompanyAuxiliarySchema.php';

/** Existing native announcements, authenticated contact lineage. No staff impersonation or ambient session. */
final class NativeCompanyPortalAnnouncements
{
    public function __construct(private $db,private string $p,private CompanyPortal $portal)
    {if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');$portal->assertStorageBinding($db,$p);}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function identity(array $binding,int $issuer,bool $lock=false):array
    {$this->portal->assertStorageBinding($this->db,$this->p);$who=$this->portal->invoiceIdentity($binding,$issuer,$lock);if(!$who)throw new DomainException('company_portal_announcement_denied');return$who;}
    private function principal(int $issuer):bool
    {$row=$this->rows('SELECT primary_issuer_id FROM '.$this->p.'issuers_installation_identity WHERE id=1')[0]??[];return isset($row['primary_issuer_id'])&&(int)$row['primary_issuer_id']===$issuer;}
    private static function project(array $row,bool $owned):array
    {$row['source_id']=(int)$row['announcementid'];$row['source_kind']=$owned?'company_announcement':'announcement';$row['announcementid']=NativeCompanyResourceId::encode($row['source_kind'],$row['source_id']);unset($row['issuer_id'],$row['actor_id']);return$row;}
    public function page(array $binding,int $issuer,int $offset=0,int $limit=100):array
    {
        if($offset<0||$offset>100000||$limit<1||$limit>100)throw new DomainException('company_portal_announcement_invalid');return array_slice($this->collect($binding,$issuer,$offset+$limit),$offset,$limit);
    }
    /** Complete native client-side table, with two bounded-authority queries rather than repeated prefix pages. */
    public function all(array$binding,int$issuer):array{return$this->collect($binding,$issuer,null);}
    private function collect(array$binding,int$issuer,?int$n):array
    {
        $who=$this->identity($binding,$issuer);
        $rows=$this->rows('SELECT a.*,CASE WHEN d.contact_id IS NULL THEN 0 ELSE 1 END is_dismissed FROM '.$this->p.'company_announcements a LEFT JOIN '.$this->p.'company_announcement_contact_dismissals d ON d.issuer_id=a.issuer_id AND d.announcementid=a.announcementid AND d.contact_id=? WHERE a.issuer_id=? AND a.showtousers=1 ORDER BY a.dateadded DESC,a.announcementid DESC'.($n===null?'':' LIMIT '.$n),[$who['contact_id'],$issuer]);
        $rows=array_map(static fn($r)=>self::project($r,true),$rows);
        if($this->principal($issuer))foreach($this->rows('SELECT a.*,CASE WHEN EXISTS(SELECT 1 FROM '.$this->p.'dismissed_announcements d WHERE d.announcementid=a.announcementid AND d.userid=? AND d.staff=0) THEN 1 ELSE 0 END is_dismissed FROM '.$this->p.'announcements a WHERE a.showtousers=1 ORDER BY a.dateadded DESC,a.announcementid DESC'.($n===null?'':' LIMIT '.$n),[$who['contact_id']])as$row)$rows[]=self::project($row,false);
        usort($rows,static fn($a,$b)=>strcmp($b['dateadded'],$a['dateadded'])?:strcmp($b['announcementid'],$a['announcementid']));$this->identity($binding,$issuer);return $rows;
    }
    public function detail(array$binding,int$issuer,$nativeId):array
    {
        $this->identity($binding,$issuer);$ref=NativeCompanyResourceId::decode('announcement',$nativeId);$owned=$ref['source_kind']==='company_announcement';if(!$owned&&!$this->principal($issuer))throw new DomainException('company_portal_announcement_denied');
        $rows=$owned?$this->rows('SELECT * FROM '.$this->p.'company_announcements WHERE issuer_id=? AND announcementid=? AND showtousers=1',[$issuer,$ref['source_id']]):$this->rows('SELECT * FROM '.$this->p.'announcements WHERE announcementid=? AND showtousers=1',[$ref['source_id']]);if(count($rows)!==1)throw new DomainException('company_portal_announcement_denied');$this->identity($binding,$issuer);return self::project($rows[0],$owned);
    }
    public function countUndismissed(array $binding,int $issuer):int
    {
        $who=$this->identity($binding,$issuer);$count=(int)$this->rows('SELECT COUNT(*) n FROM '.$this->p.'company_announcements a WHERE a.issuer_id=? AND a.showtousers=1 AND NOT EXISTS(SELECT 1 FROM '.$this->p.'company_announcement_contact_dismissals d WHERE d.issuer_id=a.issuer_id AND d.announcementid=a.announcementid AND d.contact_id=?)',[$issuer,$who['contact_id']])[0]['n'];
        if($this->principal($issuer))$count+=(int)$this->rows('SELECT COUNT(*) n FROM '.$this->p.'announcements a WHERE a.showtousers=1 AND NOT EXISTS(SELECT 1 FROM '.$this->p.'dismissed_announcements d WHERE d.announcementid=a.announcementid AND d.userid=? AND d.staff=0)',[$who['contact_id']])[0]['n'];$this->identity($binding,$issuer);return$count;
    }
    public function dismiss(array $binding,int $issuer,$nativeId):void
    {
        $ref=NativeCompanyResourceId::decode('announcement',$nativeId);$tables=['issuers_lock','issuers_registry','issuers_installation_identity','issuers_clients','contacts','clients','company_announcements','company_announcement_contact_dismissals','announcements','dismissed_announcements','issuers_audit'];
        IssuerTransaction::run($this->db,$this->p,$tables,function()use($binding,$issuer,$ref):void{
            $who=$this->identity($binding,$issuer,true);$id=$ref['source_id'];
            if($ref['source_kind']==='company_announcement'){
                if(!$this->rows('SELECT announcementid FROM '.$this->p.'company_announcements WHERE issuer_id=? AND announcementid=? AND showtousers=1 FOR UPDATE',[$issuer,$id]))throw new DomainException('company_portal_announcement_denied');
                $sql='SELECT contact_id FROM '.$this->p.'company_announcement_contact_dismissals WHERE issuer_id=? AND announcementid=? AND contact_id=?';$args=[$issuer,$id,$who['contact_id']];
                if($this->rows($sql,$args))return;
                IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_announcement_contact_dismissals(issuer_id,announcementid,contact_id,created_at) VALUES(?,?,?,?)',array_merge($args,[gmdate('Y-m-d H:i:s')]));
            }else{
                if(!$this->principal($issuer)||!$this->rows('SELECT announcementid FROM '.$this->p.'announcements WHERE announcementid=? AND showtousers=1 FOR UPDATE',[$id]))throw new DomainException('company_portal_announcement_denied');
                $sql='SELECT userid FROM '.$this->p.'dismissed_announcements WHERE announcementid=? AND userid=? AND staff=0';$args=[$id,$who['contact_id']];if($this->rows($sql,$args))return;
                IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'dismissed_announcements(announcementid,userid,staff) VALUES(?,?,0)',$args);
            }
            if(!$this->rows($sql,$args))throw new RuntimeException('issuer_announcement_dismissal_unconfirmed');
            $meta=json_encode(['source_kind'=>$ref['source_kind'],'source_id'=>$id,'contact_id'=>$who['contact_id'],'operation_nonce'=>bin2hex(random_bytes(16))],JSON_THROW_ON_ERROR);
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(issuer_id,actor_id,subject_id,event,metadata_json,created_at) VALUES(?,0,?,?,?,?)',[$issuer,$who['contact_id'],'native_portal_announcement_dismissed',$meta,gmdate('Y-m-d H:i:s')]);$audit=(int)$this->db->insert_id();
            if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND metadata_json=?',[$audit,$issuer,$meta]))throw new RuntimeException('issuer_announcement_dismissal_unconfirmed');$this->identity($binding,$issuer,true);
        });
    }
}
