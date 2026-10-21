<?php
/** Native models and real database; mail/hooks are local fixture recorders only. */
require __DIR__.'/companies/staff_native_atomic.php';
$identityChecks=$GLOBALS['staffChecks'];
$target=$id;
$GLOBALS['nativeActor']=17;
$before=staffSnapshot($db);$effects=$GLOBALS['nativeEffects'];
foreach ([21,22] as $existing) {
    $email=$db->query('SELECT email FROM '.$p.'staff WHERE staffid=?',[$existing])->row()->email;
    staffThrows(fn()=>$model->update_profile(['email'=>' '.strtoupper($email).' ','firstname'=>'Rejected'],$target),'issuer_staff_email_conflict','direct self-profile global duplicate '.$existing);
    staffCheck(staffSnapshot($db)===$before,'self-profile duplicate preserves identity and all company links');
    $edit=['email'=>$email,'firstname'=>'Rejected','lastname'=>'Synthetic','password'=>'','departments'=>[2],'custom_fields'=>['staff'=>[1=>'rejected custom']],'permissions'=>['tasks'=>['edit']]];
    staffThrows(fn()=>$model->update($edit,$target),'issuer_staff_email_conflict','direct native update global duplicate '.$existing);
    staffCheck(staffSnapshot($db)===$before,'native update duplicate preserves custom fields departments permissions and links');
}
staffCheck($effects===$GLOBALS['nativeEffects'],'duplicate writers produce no hooks or mail');
$email=$db->query('SELECT email FROM '.$p.'staff WHERE staffid=?',[$target])->row()->email;
$model->update_profile(['email'=>' '.strtoupper($email).' '],$target);
staffCheck(staffSnapshot($db)===$before,'self profile can retain same canonical identity');
$before=staffSnapshot($db);
$edit=['email'=>'renamed-native@example.invalid','firstname'=>'Native retained','lastname'=>'Synthetic','password'=>'','departments'=>[2],'custom_fields'=>['staff'=>[1=>'new custom']],'permissions'=>['tasks'=>['edit']]];
$db->failOn='UPDATE '.$p.'staff SET';
staffThrows(fn()=>$model->update($edit,$target),'issuer_storage_failed','late native update failure');$db->failOn=null;
staffCheck(staffSnapshot($db)===$before,'direct transaction rolls back earlier department and custom field mutations');
foreach (['before_update_staff_member','before_staff_update_profile'] as $tag) {
    $GLOBALS['nativeHooks']->filters[$tag]=[static function($value){$value['email']='staff21@example.invalid';return $value;}];
    staffThrows(fn()=>$tag==='before_update_staff_member'?$model->update($edit,$target):$model->update_profile(['email'=>'unused@example.invalid'],$target),'issuer_staff_extension_unreviewed','unreviewed identity-changing pre-hook fails before writes');
    $GLOBALS['nativeHooks']->filters=[];
    staffCheck(staffSnapshot($db)===$before,'pre-hook rejection leaves no mutation');
}
// A direct writer outside the application commits a duplicate AFTER availability SELECT.
// The unique constraint must still reject our update and roll back its earlier participants.
$other=new StaffTestDb(staffPdo(),$p);$racer=900001;$raced=false;
$db->observer=static function($sql,$params,$db)use($other,$p,$racer,&$raced){
    if(!$raced && str_starts_with($sql,'SELECT staffid FROM `'.$p.'staff` WHERE LOWER(TRIM(`email`))')) {
        $raced=true;$db->observer=null;
        $other->query('INSERT INTO '.$p.'staff(staffid,firstname,lastname,email,password,datecreated,admin,active,role) VALUES(?,?,?,?,?,NOW(),0,1,0)',[$racer,'Synthetic','Competing writer','renamed-native@example.invalid','synthetic-unused-hash']);
    }
};
staffThrows(fn()=>$model->update($edit,$target),'issuer_staff_email_conflict','constraint race maps to controlled conflict');$db->observer=null;
staffCheck($raced,'independent writer actually raced after availability check');
$other->query('DELETE FROM '.$p.'staff WHERE staffid=?',[$racer]);
staffCheck(staffSnapshot($db)===$before,'constraint race rolls back native departments custom fields and identity');
$effectsBefore=count($GLOBALS['nativeEffects']);
$model->update($edit,$target);
staffCheck($db->query('SELECT email FROM '.$p.'staff WHERE staffid=?',[$target])->row()->email==='renamed-native@example.invalid','direct native unique update succeeds');
staffCheck(array_unique(array_column(array_slice($GLOBALS['nativeEffects'],$effectsBefore),1))===[false],'direct successful writer effects happen after commit');
$after=staffSnapshot($db);
foreach(['issuers_installation_identity','issuers_staff','issuers_staff_defaults','issuers_resource_permissions'] as $table) {
    staffCheck($after[$table]===$before[$table],'email editing preserves '.$table);
}
// HR uses its real model entrypoints. Only peripheral date formatting is adapted.
require APPPATH.'../modules/hr_profile/models/Hr_profile_model.php';
if (!function_exists('to_sql_date')) { function to_sql_date($value){return $value;} }
$hr=new Hr_profile_model();$hr->db=$db;
$db->query('CREATE TABLE '.$p.'hr_profile_option (id INT PRIMARY KEY) ENGINE=InnoDB');
$db->query('CREATE TABLE '.$p.'options (id INT PRIMARY KEY) ENGINE=InnoDB');
try {
    $beforeHr=staffSnapshot($db);
    staffThrows(fn()=>$hr->add_staff(['email'=>' STAFF21@EXAMPLE.INVALID ']),'issuer_staff_email_conflict','HR add global email conflict before side effects');
    staffThrows(fn()=>$hr->update_staff(['email'=>'staff22@example.invalid'],$target),'issuer_staff_email_conflict','HR update global email conflict');
    staffThrows(fn()=>$hr->update_staff_profile(['id'=>$target,'email'=>'staff22@example.invalid','birthday'=>'2000-01-01','days_for_identity'=>'2000-01-01']),'issuer_staff_email_conflict','HR profile global email conflict');
    staffCheck(staffSnapshot($db)===$beforeHr,'all HR duplicate entrypoints preserve staff identities and links');
} finally {
    $db->query('DROP TABLE '.$p.'options');$db->query('DROP TABLE '.$p.'hr_profile_option');
}
echo 'Staff email real native writers: '.($GLOBALS['staffChecks']-$identityChecks).' checks passed.'.PHP_EOL;
