<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyPortal.php';
/** Identity adapter for native public routes. The HTTP bootstrap owns route admission and CSRF. */
final class NativePortalIdentity
{
    public const SESSION_KEY='native_company_portal_identity';
    private CompanyPortal $portal;
    public function __construct($db,string $prefix,string $secret){$this->portal=new CompanyPortal($db,$prefix,$secret);}
    /** Explicit issuer is mandatory. Never infer tenant from email or the selected staff session. */
    public function authenticate(int $issuer,string $email,string $password,string $ip,callable $verify):?array
    {
        if(!$this->portal->allowAttempt($issuer,$ip,'login'))return null;
        return $this->portal->login($issuer,$email,$password,$verify);
    }
    public function current($session,int $issuer):?array
    {
        $bound=$session->userdata(self::SESSION_KEY);
        if(!is_array($bound)||$session->userdata('client_logged_in')!==true)return null;
        if((string)$session->userdata('client_user_id')!==(string)($bound['client_id']??'')||(string)$session->userdata('contact_user_id')!==(string)($bound['contact_id']??''))return null;
        $identity=$this->portal->invoiceIdentity($bound,$issuer);
        return $identity?['issuer_id'=>$issuer,'identity'=>$identity,'binding'=>$bound]:null;
    }
    /** Called only after authentication and immediately before the server session is rotated. */
    public function establish($session,int $issuer,array $binding):void
    {
        if(!$this->portal->invoiceIdentity($binding,$issuer))throw new DomainException('native_portal_identity_invalid');
        $session->sess_regenerate(true);
        $session->set_userdata([self::SESSION_KEY=>$binding,'client_logged_in'=>true,'client_user_id'=>$binding['client_id'],'contact_user_id'=>$binding['contact_id']]);
        if(!$this->current($session,$issuer)){self::clear($session);throw new DomainException('native_portal_identity_invalid');}
    }
    public static function clear($session):void
    {
        // Never terminate an independent authenticated staff session.
        $session->unset_userdata([self::SESSION_KEY,'client_logged_in','client_user_id','contact_user_id','company_portal_identity']);
        $session->sess_regenerate(true);
    }
    public function __debugInfo():array{return[];}
    public function __serialize():array{throw new LogicException('native_portal_runtime_only');}
}
