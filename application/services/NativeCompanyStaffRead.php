<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyStaff.php';
/** DTO for existing staff forms, following membership authorization before profile hydration. */
final class NativeCompanyStaffRead
{
    private const FIELDS=['staffid','firstname','lastname','email','phonenumber','admin','active','role','is_not_staff','hourly_rate','facebook','linkedin','skype','default_language','email_signature','direction','profile_image','last_login','last_activity','last_password_change','datecreated','two_factor_auth_enabled'];
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public function member(int $i,int $a,int $id):array
    {
        $staff=new CompanyStaff($this->db,$this->p);$authorization=$staff->get($i,$a,$id);
        $select='SELECT '.implode(',',self::FIELDS).' FROM '.$this->p.'staff WHERE staffid=?';$row=IssuerStorage::query($this->db,$select,[$id])->row_array();if(!$row)throw new DomainException('issuer_staff_denied');
        $again=$staff->get($i,$a,$id);if(!hash_equals($authorization['revision'],$again['revision'])||$row!==IssuerStorage::query($this->db,$select,[$id])->row_array())throw new DomainException('issuer_staff_stale');
        return['member'=>(object)$row,'access'=>$authorization,'revision'=>hash('sha256',json_encode([$authorization['revision'],$row],JSON_THROW_ON_ERROR))];
    }
}
