<?php
require __DIR__.'/project_contact_files_setup.php';
require_once APPPATH.'services/CompanySettingsStorage.php';
$start=$GLOBALS['essentialChecks'];$settings=new CompanySettingsStorage($db,$p);
foreach([$a,$b,$primary]as$owner){$project=$portalParents['projects'][$owner];$savePf($owner,'1','1','native-delete-enable-'.$owner);$state=$projectFileRules->get($owner,7,$project);$revision=$state['project']['revision'];$file=$projectFileWrite->upload($sessions[$owner],$owner,$project,$revision,0,$key('native-delete-file-'.$owner),'Own.pdf',$pdf)['file_key'];
 essentialThrows(fn()=>$projectFileWrite->hideNative($sessions[$owner],$owner,$project,$revision,1,$file,$key('native-delete-absent-'.$owner)),'company_project_file_unavailable','native delete absent remains denied despite project flags');
 if($owner===$primary)$db->query("INSERT INTO {$p}options(name,value) VALUES('allow_contact_to_delete_files','1')");else$settings->mutate($owner,7,'update','allow_contact_to_delete_files','1');
 $result=$projectFileWrite->hideNative($sessions[$owner],$owner,$project,$revision,1,$file,$key('native-delete-ok-'.$owner));essentialCheck($result['file_key']===$file&&(int)$db->query('SELECT active FROM '.$p.'company_project_files WHERE file_key=?',[$file])->row()->active===0,'original Clients setting governs exact contact project removal');essentialCheck($db->query('SELECT content FROM '.$p.'company_project_files WHERE file_key=?',[$file])->row()->content===$pdf,'native delete preserves canonical bytes and origin');
 if($owner===$primary)$db->query("UPDATE {$p}options SET value='0' WHERE name='allow_contact_to_delete_files'");else$settings->mutate($owner,7,'update','allow_contact_to_delete_files','0');
 essentialThrows(fn()=>$projectFileWrite->hideNative($sessions[$owner],$owner,$project,$revision,1,$file,$key('native-delete-ok-'.$owner)),'company_project_file_unavailable','revoked native option blocks replay');
}
echo 'Native project file deletion policy: '.($GLOBALS['essentialChecks']-$start).' actual SQLite A/B/principal checks passed.'.PHP_EOL;
