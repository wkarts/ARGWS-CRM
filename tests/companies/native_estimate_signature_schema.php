<?php
require __DIR__.'/sales.php';
if(!class_exists('CI_Migration',false)){class CI_Migration{public $db;}}
require APPPATH.'migrations/477_version_477.php';
require_once APPPATH.'services/NativeCompanyEstimateSignatureSchema.php';
$migration=new Migration_Version_477();$migration->db=$db;$migration->up();$migration->up();
NativeCompanyEstimateSignatureSchema::assertValid($db,$p);
essentialCheck($db->table_exists($p.'company_estimate_signatures'),'migration477 signature schema is additive and idempotent');
essentialThrows(fn()=>$migration->down(),'native_company_schema_downgrade_requires_review','migration477 downgrade cannot delete signature evidence');
echo "Native estimate signature migration477: 2 checks.\n";
