<?php
require __DIR__.'/../issuers_core_support.php';
require_once APPPATH.'services/NativeCompanyProjectMetadataSchema.php';
register_shutdown_function(static function():void{foreach($GLOBALS['essentialFixtures']??[]as$f)foreach(['company_project_native_metadata','company_projects']as$t)$f->db->query('DROP TABLE IF EXISTS '.$f->prefix.$t);essentialCleanup();});
$f=essentialFixture('1.0.2',1);$db=$f->db;$p=$f->prefix;
$db->query("CREATE TABLE {$p}company_projects(id INT NOT NULL PRIMARY KEY,issuer_id INT NOT NULL,status VARCHAR(16) NOT NULL,UNIQUE(issuer_id,id))");
$db->query("INSERT INTO {$p}company_projects VALUES(1,1,'active')");
NativeCompanyProjectMetadataSchema::ensure($db,$p);NativeCompanyProjectMetadataSchema::ensure($db,$p);
essentialCheck($db->query("SELECT * FROM {$p}company_project_native_metadata")->result_array()===[],'Schema does not adopt historical active projects');
$db->query("INSERT INTO {$p}company_project_native_metadata VALUES(1,1,1,2,1,1,'100.01','0.00',NULL,1,0,NULL)");
$before=$db->query("SELECT * FROM {$p}company_project_native_metadata")->result_array();NativeCompanyProjectMetadataSchema::ensure($db,$p);
essentialCheck($db->query("SELECT * FROM {$p}company_project_native_metadata")->result_array()===$before,'Replay preserves explicitly configured state');
try{$db->query("INSERT INTO {$p}company_project_native_metadata VALUES(2,1,1,2,1,1,'100.01','0.00',NULL,1,0,NULL)");throw new RuntimeException('Cross-issuer reference accepted');}catch(PDOException$e){essentialCheck(true,'Composite project ownership FK');}
$db->query("ALTER TABLE {$p}company_project_native_metadata ADD extra INT NULL");
essentialThrows(fn()=>NativeCompanyProjectMetadataSchema::assertValid($db,$p),'native_project_metadata_schema_invalid','Unexpected schema fails closed');
echo "Native project metadata schema: {$GLOBALS['essentialChecks']} checks passed.\n";
