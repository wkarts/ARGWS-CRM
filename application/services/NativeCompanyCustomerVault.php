<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyCustomerAccess.php';
require_once __DIR__.'/NativeCompanyCustomerAudit.php';
/** Existing native vault rows; encrypted payload carries exact tenant/customer/entry identity. */
final class NativeCompanyCustomerVault
{
    public function __construct(private$db,private string$p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public function tables():array{return['issuers_lock','issuers_registry','issuers_staff','issuers_installation_identity','issuers_resource_permissions','issuers_clients','issuers_audit','staff','clients','vault'];}
    private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function access(int$i,int$a,int$c,string$cap='view'):void{if(!(new CompanyCustomerAccess($this->db,$this->p))->can($i,$a,$c,$cap))throw new DomainException('company_client_access_denied');}
    private function visible(int$i,int$a,array$r):bool{return (int)$r['visibility']===1||(int)$r['creator']===$a||((int)$r['visibility']===2&&(new IssuerResourceAccess($this->db,$this->p))->administrative($i,$a));}
    private function raw(int$i,int$a,int$id,bool$write=false):array
    {
        $r=$this->rows('SELECT v.* FROM '.$this->p.'vault v JOIN '.$this->p.'issuers_clients o ON o.client_id=v.customer_id WHERE v.id=? AND o.owner_issuer_id=?',[$id,$i])[0]??null;if(!$r)throw new DomainException('company_client_access_denied');
        $this->access($i,$a,(int)$r['customer_id'],$write?'edit':'view');if(!$this->visible($i,$a,$r)||($write&&(int)$r['creator']!==$a&&!(new IssuerResourceAccess($this->db,$this->p))->administrative($i,$a)))throw new DomainException('company_client_access_denied');return$r;
    }
    private static function revision(array$r):string{ksort($r);return hash('sha256',json_encode($r,JSON_THROW_ON_ERROR));}
    private function dto(int$i,int$a,array$r):array{$r['revision']=self::revision($r);unset($r['password']);$r['can_manage']=(int)$r['creator']===$a||(new IssuerResourceAccess($this->db,$this->p))->administrative($i,$a);return$r;}
    public function get(int$i,int$a,int$id):array{return$this->dto($i,$a,$this->raw($i,$a,$id));}
    public function page(int$i,int$a,int$c,bool$projectOnly=false):array
    {
        $this->access($i,$a,$c);$out=[];$rows=$this->rows('SELECT * FROM '.$this->p.'vault WHERE customer_id=? ORDER BY id LIMIT 1001',[$c]);if(count($rows)>1000)throw new DomainException('company_client_invalid_page');foreach($rows as$r){if($this->visible($i,$a,$r)&&(!$projectOnly||(int)$r['share_in_projects']===1))$out[]=$this->dto($i,$a,$r);}if(count($out)>1000)throw new DomainException('company_client_invalid_page');$this->access($i,$a,$c);return$out;
    }
    public function listForProject(int$i,int$a,int$c):array{return$this->page($i,$a,$c,true);}
    private static function input(array$v):array
    {
        if(array_diff(array_keys($v),['server_address','port','username','password','description','visibility','share_in_projects']))throw new DomainException('native_vault_invalid');
        foreach(['server_address'=>191,'username'=>191,'password'=>4096,'description'=>65535]as$k=>$max){$x=$v[$k]??'';if(!is_string($x)||strlen($x)>$max||preg_match('//u',$x)!==1||preg_match($k==='description'?'/[\x00-\x08\x0b\x0c\x0e-\x1f]/':'/[\x00-\x1f]/',$x))throw new DomainException('native_vault_invalid');$v[$k]=$x;}
        if(trim($v['server_address'])==='')throw new DomainException('native_vault_invalid');$port=$v['port']??'';if($port!==''&&(!is_scalar($port)||!preg_match('/^[1-9][0-9]{0,4}$/D',(string)$port)||(int)$port>65535))throw new DomainException('native_vault_invalid');$v['port']=$port===''?null:(int)$port;
        $visibility=$v['visibility']??'1';if(!in_array($visibility,[1,2,3,'1','2','3'],true))throw new DomainException('native_vault_invalid');$v['visibility']=(int)$visibility;$share=$v['share_in_projects']??'0';if(!in_array($share,[0,1,'0','1','on'],true))throw new DomainException('native_vault_invalid');$v['share_in_projects']=in_array($share,[1,'1','on'],true)?1:0;return$v;
    }
    private function staffName(int$a):string{$r=$this->rows('SELECT firstname,lastname FROM '.$this->p.'staff WHERE staffid=? AND active=1',[$a])[0]??null;if(!$r)throw new DomainException('company_client_access_denied');$n=trim($r['firstname'].' '.$r['lastname']);if(strlen($n)>100)throw new DomainException('native_vault_invalid');return$n;}
    public function save(int$i,int$a,int$c,array$input,callable$encrypt,?int$id=null,?string$revision=null):int
    {
        $input=self::input($input);return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($i,$a,$c,$input,$encrypt,$id,$revision):int{
            $this->access($i,$a,$c,'edit');$at=gmdate('Y-m-d H:i:s');$name=$this->staffName($a);$old=null;
            if($id!==null){$old=$this->raw($i,$a,$id,true);if((int)$old['customer_id']!==$c)throw new DomainException('company_client_access_denied');if($revision===null||!hash_equals(self::revision($old),$revision))throw new DomainException('company_client_stale');}
            else{if($input['password']==='')throw new DomainException('native_vault_invalid');$marker='pending:'.bin2hex(random_bytes(32));IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'vault(customer_id,server_address,username,password,creator,creator_name,visibility,share_in_projects,date_created) VALUES(?,?,?,?,?,?,1,0,?)',[$c,$input['server_address'],$input['username'],$marker,$a,$name,$at]);$id=(int)$this->db->insert_id();$r=$this->raw($i,$a,$id,true);if($r['password']!==$marker)throw new RuntimeException('issuer_vault_write_unconfirmed');}
            $password=$input['password'];unset($input['password']);$data=$input;
            if($password!==''){$payload=json_encode(['version'=>1,'issuer'=>$i,'client'=>$c,'entry'=>$id,'password'=>$password],JSON_THROW_ON_ERROR);$cipher=$encrypt($payload);if(!is_string($cipher)||$cipher===''||$cipher===$payload||strlen($cipher)>32768)throw new DomainException('native_vault_crypto_failed');$data['password']='native-v1:'.$cipher;}
            if($old){$data['last_updated']=$at;$data['last_updated_from']=$name;}
            $keys=array_keys($data);IssuerStorage::query($this->db,'UPDATE '.$this->p.'vault SET '.implode(',',array_map(fn($k)=>$k.'=?',$keys)).' WHERE id=? AND customer_id=?',[...array_values($data),$id,$c]);$r=$this->raw($i,$a,$id,true);foreach($data as$k=>$v)if($v===null?$r[$k]!==null:(string)$r[$k]!==(string)$v)throw new RuntimeException('issuer_vault_write_unconfirmed');NativeCompanyCustomerAudit::record($this->db,$this->p,$i,$a,$id,$old?'native_vault_updated':'native_vault_created');return$id;
        });
    }
    public function delete(int$i,int$a,int$id,string$revision):void{IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($i,$a,$id,$revision):void{$r=$this->raw($i,$a,$id,true);if(!hash_equals(self::revision($r),$revision))throw new DomainException('company_client_stale');IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'vault WHERE id=? AND customer_id=?',[$id,$r['customer_id']]);if($this->rows('SELECT id FROM '.$this->p.'vault WHERE id=?',[$id]))throw new RuntimeException('issuer_vault_write_unconfirmed');NativeCompanyCustomerAudit::record($this->db,$this->p,$i,$a,$id,'native_vault_deleted');});}
    public function reveal(int$i,int$a,int$id,string$revision,string$password,callable$verify,callable$decrypt):string
    {
        if($password===''||strlen($password)>4096)throw new DomainException('native_vault_reauthentication_required');return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($i,$a,$id,$revision,$password,$verify,$decrypt):string{
            $r=$this->raw($i,$a,$id);if(!hash_equals(self::revision($r),$revision))throw new DomainException('company_client_stale');$staff=$this->rows('SELECT password FROM '.$this->p.'staff WHERE staffid=? AND active=1',[$a])[0]??null;if(!$staff||!$verify($password,$staff['password']))throw new DomainException('native_vault_reauthentication_required');
            if(!str_starts_with($r['password'],'native-v1:'))throw new DomainException('native_vault_unbound_ciphertext');$plain=$decrypt(substr($r['password'],10));if(!is_string($plain))throw new DomainException('native_vault_crypto_failed');try{$v=json_decode($plain,true,8,JSON_THROW_ON_ERROR);}catch(Throwable$e){throw new DomainException('native_vault_crypto_failed');}if(!is_array($v)||array_keys($v)!==['version','issuer','client','entry','password']||$v['version']!==1||$v['issuer']!==$i||$v['client']!==(int)$r['customer_id']||$v['entry']!==$id||!is_string($v['password']))throw new DomainException('native_vault_crypto_failed');
            NativeCompanyCustomerAudit::record($this->db,$this->p,$i,$a,$id,'native_vault_revealed');$this->raw($i,$a,$id);return$v['password'];
        });
    }
}
