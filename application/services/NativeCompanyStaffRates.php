<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyStaff.php';
/** Native hourly_rate belongs to company membership; shared login identity is not a wage catalogue. */
final class NativeCompanyStaffRates
{
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public function tables():array{return array_merge(IssuerStaffAccess::TABLES,['company_staff_rates']);}
    public static function normalize($value):?string
    {
        if($value===null||$value==='')return null;if(!is_string($value)||!preg_match('/^(?:0|[1-9][0-9]{0,12})(?:\.[0-9]{1,2})?$/D',$value))throw new DomainException('native_staff_rate_invalid');[$whole,$fraction]=array_pad(explode('.',$value),2,'');return$whole.'.'.str_pad($fraction,2,'0');
    }
    private function authority(int $i,int $a,int $staff,bool $own):IssuerResourceAccess
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->member($i,$a)||($own&&!$acl->member($i,$staff))||($own?($a!==$staff||!$acl->can($i,$a,'tasks','view')):!$acl->can($i,$a,'staff','view')))throw new DomainException('native_staff_rate_denied');if(!$own)(new CompanyStaff($this->db,$this->p))->assertAdministrativeTarget($i,$a,$staff);return$acl;
    }
    private function read(int $i,int $a,int $staff,bool $own):array
    {
        $acl=$this->authority($i,$a,$staff,$own);$primary=$acl->primaryId();
        if($primary===$i){$row=IssuerStorage::query($this->db,'SELECT hourly_rate FROM '.$this->p.'staff WHERE staffid=?',[$staff])->row_array();if(!$row)throw new DomainException('native_staff_rate_denied');$rate=$row['hourly_rate']===null?null:self::normalize((string)$row['hourly_rate']);$revision=hash('sha256',json_encode([$i,$staff,$row],JSON_THROW_ON_ERROR));$source='staff';}
        else{$row=IssuerStorage::query($this->db,'SELECT hourly_rate,revision FROM '.$this->p.'company_staff_rates WHERE issuer_id=? AND staff_id=?',[$i,$staff])->row_array();$rate=$row&&$row['hourly_rate']!==null?self::normalize((string)$row['hourly_rate']):null;$revision=$row?(int)$row['revision']:0;if($row&&$revision<1)throw new RuntimeException('native_staff_rate_invalid');$source=$row?'company_staff_rate':'unconfirmed';}
        $this->authority($i,$a,$staff,$own);$again=$primary===$i?IssuerStorage::query($this->db,'SELECT hourly_rate FROM '.$this->p.'staff WHERE staffid=?',[$staff])->row_array():IssuerStorage::query($this->db,'SELECT hourly_rate,revision FROM '.$this->p.'company_staff_rates WHERE issuer_id=? AND staff_id=?',[$i,$staff])->row_array();if($again!==$row)throw new DomainException('native_staff_rate_stale');if($primary!==$acl->primaryId())throw new DomainException('native_staff_rate_denied');return['hourly_rate'=>$rate,'revision'=>$revision,'source_kind'=>$source,'source_id'=>$staff];
    }
    public function forStaff(int $i,int $a,int $staff):array{return$this->read($i,$a,$staff,false);}
    public function forOwnTimer(int $i,int $a):array{return$this->read($i,$a,$a,true);}
    public function forOwnTimerInOwned(int $i,int $a):array{IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());return$this->forOwnTimer($i,$a);}
    public function saveInOwned(int $i,int $a,int $staff,$value,int $revision,?CompanyStaff $creatingWriter=null):void
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$acl=$this->authority($i,$a,$staff,false);if($acl->primaryId()===$i)throw new DomainException('native_staff_rate_primary_required');
        if($creatingWriter!==null)$creatingWriter->assertCreatedInOwned($this->db,$this->p,$i,$a,'staff',$staff);
        $cap=$creatingWriter===null?'edit':'create';if(!$acl->can($i,$a,'staff',$cap))throw new DomainException('native_staff_rate_denied');
        $value=self::normalize($value);$old=$this->forStaff($i,$a,$staff);if($old['revision']!==$revision||$revision<0||$revision>=2147483647)throw new DomainException('native_staff_rate_stale');if($old['source_kind']!=='unconfirmed'&&$old['hourly_rate']===$value)return;
        $now=gmdate('Y-m-d H:i:s');if($revision===0)IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_staff_rates(issuer_id,staff_id,hourly_rate,revision,actor_id,updated_at) VALUES(?,?,?,1,?,?)',[$i,$staff,$value,$a,$now]);else IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_staff_rates SET hourly_rate=?,revision=revision+1,actor_id=?,updated_at=? WHERE issuer_id=? AND staff_id=? AND revision=?',[$value,$a,$now,$i,$staff,$revision]);
        $after=$this->forStaff($i,$a,$staff);if($after['hourly_rate']!==$value||$after['revision']!==$revision+1)throw new RuntimeException('native_staff_rate_unconfirmed');
        $nonce=bin2hex(random_bytes(32));$metadata=json_encode(['nonce'=>$nonce,'revision'=>$revision+1],JSON_THROW_ON_ERROR);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$a,'native_staff_rate_saved',$i,$staff,$now,$metadata]);$id=(int)$this->db->insert_id();$r=IssuerStorage::query($this->db,'SELECT metadata_json FROM '.$this->p.'issuers_audit WHERE id=? AND event=? AND issuer_id=? AND subject_id=?',[$id,'native_staff_rate_saved',$i,$staff])->row_array();if(($r['metadata_json']??null)!==$metadata)throw new RuntimeException('native_staff_rate_unconfirmed');if(!$acl->can($i,$a,'staff',$cap)||$after!==$this->forStaff($i,$a,$staff))throw new DomainException('native_staff_rate_denied');
    }
}
