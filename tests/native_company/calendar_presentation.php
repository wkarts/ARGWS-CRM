<?php
declare(strict_types=1);
require __DIR__.'/knowledge_read.php';require_once __DIR__.'/../../application/services/NativeCompanyCalendarPresentation.php';
$db->query("ALTER TABLE tblissuers_registry ADD settings_json TEXT NOT NULL DEFAULT '{}'");$db->query('CREATE TABLE tbloptions (name TEXT,value TEXT)');$db->query("INSERT INTO tbloptions VALUES('calendar_first_day','5')");
$r=NativeCompanyCalendarPresentation::read($db,'tbl',2,17);kc($r===['calendar_first_day'=>'1','source'=>'owned_default','revision'=>0],'named absence uses real owned default');
$values=CompanyCalendarPreferences::defaults();$values['calendar_first_day']='3';$doc=json_encode(['company_calendar'=>['format'=>1,'revision'=>7,'values'=>$values]],JSON_THROW_ON_ERROR);$db->query('UPDATE tblissuers_registry SET settings_json=? WHERE id=2',[$doc]);$db->queries=[];
$r=NativeCompanyCalendarPresentation::read($db,'tbl',2,17);kc($r===['calendar_first_day'=>'3','source'=>'owned_override','revision'=>7],'actual calendar node read not fabricated options');kc(!preg_grep('/FROM tbloptions/',$db->queries),'named never reads principal calendar setting');
$r=NativeCompanyCalendarPresentation::read($db,'tbl',1,17);kc($r['calendar_first_day']==='5'&&$r['source']==='principal_legacy','proven primary option retained');
$db->query('UPDATE tblissuers_registry SET settings_json=? WHERE id=1',[$doc]);$r=NativeCompanyCalendarPresentation::read($db,'tbl',1,17);kc($r['calendar_first_day']==='3'&&$r['source']==='owned_override','explicit owned node has precedence on primary too');
$deny(fn()=>NativeCompanyCalendarPresentation::read($db,'tbl',3,17));$db->query('UPDATE tblissuers_staff SET can_view=0 WHERE issuer_id=2 AND staff_id=17');$deny(fn()=>NativeCompanyCalendarPresentation::read($db,'tbl',2,17));
echo "Calendar presentation actual SQLite: owned node/default, explicit primary lineage, no calendar configure grant, A/B and revoked member passed.\n";
