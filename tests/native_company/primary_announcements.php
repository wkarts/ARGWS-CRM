<?php
/** Production Announcements_model + owned boundary; synthetic CI transport, real PDO storage. */
require __DIR__.'/primary_auxiliary.php';
require APPPATH.'vendor/bainternet/php-hooks/php-hooks.php';$GLOBALS['essentialHooks']=new Hooks();
$db->query("CREATE TABLE {$p}announcements(announcementid {$auto},name varchar(191),message TEXT,showtousers INT,showtostaff INT,showname INT,userid varchar(100),dateadded DATETIME)");
$db->query("CREATE TABLE {$p}dismissed_announcements(announcementid INT,userid INT,staff INT)");
if(!$db->table_exists($p.'activity_log'))$db->query("CREATE TABLE {$p}activity_log(id {$auto},description TEXT,date DATETIME,staffid varchar(100))");
$db->query("INSERT INTO {$p}announcements(announcementid,name,message,showtousers,showtostaff,showname,userid,dateadded) VALUES(1,'Historical','<p>Kept</p>',0,1,1,'Synthetic','2020-01-01 00:00:00')");
final class NativeLegacyQueryBuilder
{
    public PDO $pdo;private array $where=[];
    public function __construct(private $inner){$this->pdo=$inner->pdo;}
    public function __call($method,$args){return$this->inner->$method(...$args);}
    public function affected_rows():int{return(int)$this->pdo->query($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'SELECT changes()':'SELECT ROW_COUNT()')->fetchColumn();}
    public function where($key,$value):self{$this->where[$key]=$value;return$this;}
    public function update($table,$values){$where=$this->where;$this->where=[];return$this->inner->query('UPDATE '.$table.' SET '.implode(',',array_map(static fn($k)=>$k.'=?',array_keys($values))).' WHERE '.implode(' AND ',array_map(static fn($k)=>$k.'=?',array_keys($where))),array_merge(array_values($values),array_values($where)))!==false;}
    public function delete($table){$where=$this->where;$this->where=[];return$this->inner->query('DELETE FROM '.$table.' WHERE '.implode(' AND ',array_map(static fn($k)=>$k.'=?',array_keys($where))),array_values($where))!==false;}
}
$db=new NativeLegacyQueryBuilder($db);
final class CompanyRequestScope
{
    public static string $method='POST';public static bool $csrf=true;public static function assertNativeMutation($ci):void{if(!self::$sealed||self::$method!=='POST'||!self::$csrf)throw new DomainException('native_company_csrf_invalid');}public static function token():string{return 'synthetic-context';}public static function nativeCsrfToken($ci):string{return 'synthetic-csrf';}public static bool $sealed=true;public static int $issuer;public static function nativeFor($ci):bool{return true;}public static function sealedFor($ci):bool{return self::$sealed;}
    public static function context():object{return new class{public function issuerId():int{return CompanyRequestScope::$issuer;}public function staffId():int{return 7;}};}
}
CompanyRequestScope::$issuer=$principal;
function get_staff_user_id(){return'7';}
function log_activity($description,$actor=null){$db=$GLOBALS['CI']->db;$p=db_prefix();$db->query("INSERT INTO {$p}activity_log(description,date,staffid) VALUES(?,?,?)",[$description,date('Y-m-d H:i:s'),'Synthetic']);}
require_once BASEPATH.'core/Model.php';
require_once APPPATH.'core/App_Model.php';
require APPPATH.'models/Announcements_model.php';
$ci=new stdClass();$ci->db=$db;$GLOBALS['CI']=$ci;$ci->load=new class($ci){public function __construct(private $ci){}public function model($name):void{if($name!=='announcements_model')throw new RuntimeException('Unexpected model');$this->ci->announcements_model=new Announcements_model();}};
$releaseNativeSettings=CompanySettingsRuntime::enterRequest($db,$p,$principal,7);
$ui=new NativeCompanyAuxiliaryUi($db,$p,$principal,7,$ci);$new=$ui->saveAnnouncement(['name'=>'Owned','message'=>'<p>New</p>','showtostaff'=>1]);
essentialCheck($ui->undismissedAnnouncementsCount()===2&&count($ui->announcements())===2,'principal unions native historical and owned announcement streams');
$old=$ui->announcement('1');$ui->dismissAnnouncement('1');$ui->dismissAnnouncement('1');essentialCheck($ui->undismissedAnnouncementsCount()===1,'legacy dismissal is idempotent and does not dismiss owned announcement');
$ui->saveAnnouncement(['name'=>'Edited via actual model','message'=>'<p>OK</p><script>bad()</script>','showtostaff'=>1],'1',$old['revision']);
$after=$ui->announcement('1');essentialCheck($after['name']==='Edited via actual model'&&!str_contains($after['message'],'script'),'actual native model receives sanitized allowlisted fields under principal CAS');
settingThrows(fn()=>$ui->saveAnnouncement(['name'=>'Stale','message'=>'x'],'1',$old['revision']),'company_auxiliary_stale','stale principal announcement rejects before native model');
hooks()->add_action('before_delete_announcement',static function(){throw new RuntimeException('Unreviewed extension executed');});
settingThrows(fn()=>$ui->deleteAnnouncement('1',$after['revision']),'native_legacy_hook_review_required','unreviewed hook cannot run arbitrary cross-company side effects');
hooks()->remove_all_filters('before_delete_announcement');CompanyRequestScope::$sealed=false;
settingThrows(fn()=>$ui->deleteAnnouncement('1',$after['revision']),'company_auxiliary_access_denied','unsealed controller cannot invoke global native model');CompanyRequestScope::$sealed=true;
$ui->deleteAnnouncement('1',$after['revision']);essentialCheck(count($ui->announcements())===1&&$ui->announcement($new)['name']==='Owned','native deletion clears historical dismissal only and preserves owned lineage');
$releaseNativeSettings();
echo "Primary announcements actual CI/App model/CAS/union/hook fence: passed.\n";
