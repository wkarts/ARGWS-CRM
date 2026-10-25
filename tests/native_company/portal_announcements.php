<?php
require __DIR__.'/auxiliary_contract.php';
require APPPATH.'services/NativeCompanyPortalAnnouncements.php';
$db->query("UPDATE {$p}issuers_staff SET can_view=1 WHERE issuer_id=41 AND staff_id=12");
$db->query("ALTER TABLE {$p}clients ADD active INT NOT NULL DEFAULT 1");
$db->query("CREATE TABLE {$p}contacts(id INT PRIMARY KEY,userid INT,firstname TEXT,lastname TEXT,email TEXT,password TEXT,active INT)");
$db->query("CREATE TABLE {$p}company_portal_tokens(token_hash TEXT PRIMARY KEY)");
$db->query("CREATE TABLE {$p}announcements(announcementid INT PRIMARY KEY,name TEXT,message TEXT,showtousers INT,showtostaff INT,showname INT,userid TEXT,dateadded DATETIME)");
$db->query("CREATE TABLE {$p}dismissed_announcements(announcementid INT,userid INT,staff INT)");
$password=password_hash('Synthetic portal test',PASSWORD_DEFAULT);
$principal=(new IssuerResourceAccess($db,$p))->primaryId();
foreach([[41,90001,901],[82,90002,902],[$principal,90003,903]]as[$owner,$client,$contact]){
    $db->query("INSERT INTO {$p}clients(userid,company,active) VALUES(?,'Synthetic',1)",[$client]);
    if($owner!==$principal)$db->query("INSERT INTO {$p}issuers_clients(client_id,owner_issuer_id,created_by,created_at) VALUES(?,?,7,'2026-01-01 00:00:00')",[$client,$owner]);
    $db->query("INSERT INTO {$p}contacts VALUES(?,?,'Synthetic','Contact','synthetic@example.invalid',?,1)",[$contact,$client,$password]);
}
$portal=new CompanyPortal($db,$p,str_repeat('synthetic-secret-',4));$reader=new NativeCompanyPortalAnnouncements($db,$p,$portal);
$aSession=$portal->login(41,'synthetic@example.invalid','Synthetic portal test','password_verify');$bSession=$portal->login(82,'synthetic@example.invalid','Synthetic portal test','password_verify');$principalSession=$portal->login($principal,'synthetic@example.invalid','Synthetic portal test','password_verify');
$a=new NativeCompanyAuxiliary($db,$p,41,12);$b=new NativeCompanyAuxiliary($db,$p,82,7);
$id=$a->saveAnnouncement(['name'=>'A portal','message'=>'<p>Only A</p>','showtousers'=>1]);$private=$a->saveAnnouncement(['name'=>'Staff only','message'=>'Private','showtostaff'=>1]);$other=$b->saveAnnouncement(['name'=>'B portal','message'=>'Only B','showtousers'=>1]);
$nativeId=NativeCompanyResourceId::encode('company_announcement',$id);
essentialCheck(count($reader->page($aSession,41))===1&&$reader->countUndismissed($aSession,41)===1,'portal sees only own showtousers announcements');
settingThrows(fn()=>$reader->page($aSession,82),'company_portal_announcement_denied','session issuer mismatch denied');
settingThrows(fn()=>$reader->dismiss($aSession,41,NativeCompanyResourceId::encode('company_announcement',$other)),'company_portal_announcement_denied','foreign announcement denied');
settingThrows(fn()=>$reader->dismiss($aSession,41,NativeCompanyResourceId::encode('company_announcement',$private)),'company_portal_announcement_denied','staff-only announcement cannot be dismissed by contact');
$reader->dismiss($aSession,41,$nativeId);$reader->dismiss($aSession,41,$nativeId);
essentialCheck($reader->countUndismissed($aSession,41)===0&&$reader->countUndismissed($bSession,82)===1,'dismissal isolated and idempotent, staff counters unrelated');
$db->query("INSERT INTO {$p}announcements VALUES(1,'Historical portal','<p>History</p>',1,0,1,'Synthetic','2020-01-01 00:00:00')");
essentialCheck(count($reader->page($principalSession,$principal))===1,'principal authenticated contact retains historical native announcement');
settingThrows(fn()=>$reader->dismiss($aSession,41,'1'),'company_portal_announcement_denied','raw legacy announcement not guessed from named issuer');
$reader->dismiss($principalSession,$principal,'1');essentialCheck($reader->countUndismissed($principalSession,$principal)===0,'historical dismissal uses contact staff-zero lineage');
$retry=$a->saveAnnouncement(['name'=>'Rollback','message'=>'Transactional','showtousers'=>1]);
$db->failOn='INSERT INTO '.$p.'issuers_audit';settingThrows(fn()=>$reader->dismiss($aSession,41,NativeCompanyResourceId::encode('company_announcement',$retry)),'issuer_storage_failed','portal dismissal audit failure rolls back receipt');$db->failOn=null;
essentialCheck($reader->countUndismissed($aSession,41)===1,'failed dismissal remains visibly unread');
$a->deleteAnnouncement($id,1);essentialCheck(!$db->query("SELECT contact_id FROM {$p}company_announcement_contact_dismissals WHERE issuer_id=41 AND announcementid=?",[$id])->row_array(),'native author deletion cleans contact dismissal without cascade or orphan');
$db->query("UPDATE {$p}contacts SET active=0 WHERE id=901");settingThrows(fn()=>$reader->page($aSession,41),'company_portal_announcement_denied','contact revocation invalidates cached portal binding');
echo "Portal native announcements HMAC identity/A-B/private/legacy/dismissal: passed.\n";
