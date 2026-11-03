<?php
/** Actual service/PDO; only an intervening second writer is injected after the lookup. */
require __DIR__.'/native_contact_assignments.php';
$fresh=$access->assignments($a,7,101);$access->assign($a,7,101,[51],$fresh['revision']);
final class NativeEmailProbeDb {
 public ?Closure $afterLookup=null;
 public function __construct(private EssentialTestDb $inner){}
 public function __get($key){return $this->inner->$key;}
 public function __call($method,$args){return $this->inner->$method(...$args);}
 public function query($sql,$params=[]){$result=$this->inner->query($sql,$params);if($this->afterLookup!==null&&str_contains($sql,'SELECT c.id FROM ')&&str_contains($sql,'LOWER(c.email)')){$callback=$this->afterLookup;$this->afterLookup=null;$callback();}return $result;}
}
$probeDb=new NativeEmailProbeDb($db);$probe=new CompanyNativeContacts($probeDb,$p);$start=$GLOBALS['essentialChecks'];
essentialCheck($probe->emailAvailable($a,51,101,'unoccupied@example.invalid',$created),'assigned editor can probe own client address');
essentialCheck(!$probe->emailAvailable($a,51,101,'changed-native@example.invalid',$created),'duplicate email in same issuer rejected');
essentialCheck($probe->emailAvailable($a,51,101,'native@example.invalid',$created),'other issuer duplicate does not leak or deny local availability');
$probeDb->afterLookup=function()use($db,$p,$a){$db->query("DELETE FROM {$p}company_customer_admins WHERE issuer_id=? AND client_id=101 AND staff_id=51",[$a]);};
essentialThrows(fn()=>$probe->emailAvailable($a,51,101,'unoccupied@example.invalid',$created),'company_client_access_denied','grant revoked after SELECT denies availability projection');
$fresh=$access->assignments($a,7,101);$access->assign($a,7,101,[51],$fresh['revision']);
$probeDb->afterLookup=function()use($db,$p,$created){$db->query("UPDATE {$p}contacts SET userid=202 WHERE id=?",[$created]);};
essentialThrows(fn()=>$probe->emailAvailable($a,51,101,'unoccupied@example.invalid',$created),'company_client_access_denied','contact reassigned after SELECT cannot return availability');
$db->query("UPDATE {$p}contacts SET userid=101 WHERE id=?",[$created]);
essentialCheck($probe->emailAvailable($a,51,101,'unoccupied@example.invalid',$created),'unchanged authorized binding remains usable');
echo 'Native contact email probe: '.($GLOBALS['essentialChecks']-$start).' checks passed.'.PHP_EOL;
