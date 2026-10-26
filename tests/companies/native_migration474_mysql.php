<?php
/** Actual MySQL DDL/replay. Does not replace native installer/tracking/browser suites. */
declare(strict_types=1);
$dsn=(string)getenv('ISSUERS_TEST_DSN');
if(!preg_match('/^mysql:.*(?:^|;)dbname=(issuer_[a-zA-Z0-9_]*module_audit)(?:;|$)/D',$dsn))throw new RuntimeException('Dedicated disposable issuer_*module_audit MySQL required');
require __DIR__.'/../issuers_core_support.php';
// The database is disposable; cleanup owns ONLY this random fixture prefix.
register_shutdown_function(static function()use(&$db,&$p){
    if(!isset($db,$p))return;
    if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))return;
    $db->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    try{foreach($db->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)as$table)if(str_starts_with($table,$p)&&preg_match('/^[A-Za-z0-9_]+$/D',$table))$db->pdo->exec('DROP TABLE `'.$table.'`');}
    finally{$db->pdo->exec('SET FOREIGN_KEY_CHECKS=1');}
});
// Full historical invoice/draft schemas and real canonical synthetic documents, not minimal parent stubs.
require __DIR__.'/invoice_drafts.php';
require APPPATH.'migrations/474_version_474.php';
if($db->driver()!=='mysql')throw new RuntimeException('Real MySQL required');
require_once APPPATH.'services/CompanyProjectsSchema.php';CompanyProjectsSchema::ensure($db,$p);
require_once APPPATH.'services/CompanyStandaloneTasksSchema.php';CompanyStandaloneTasksSchema::ensure($db,$p);
$db->query("CREATE TABLE {$p}items(id INT UNSIGNED NOT NULL PRIMARY KEY,description VARCHAR(191),rate DECIMAL(15,4)) ENGINE=InnoDB");
$db->query("INSERT INTO {$p}items VALUES(1,'Synthetic preserved item',12.3456)");
$db->query("INSERT INTO {$p}currencies(id,symbol,name,decimal_separator,thousand_separator,placement,isdefault) VALUES(2,'$','USD','.',',','before',0)");
// Actual native baseline DDL and original indexes for every missing historical parent.
// Existing invoice fixture tables/data are left untouched; no seed INSERT is executed.
require_once __DIR__.'/../support/NativeInstalledSchemaFixture.php';
$native=file_get_contents(dirname(__DIR__,2).'/install/database.sql');
$nativeCreated=NativeInstalledSchemaFixture::apply($db,$p,$native);
if(!in_array('customers_groups',$nativeCreated,true))throw new RuntimeException('Missing native group prerequisite');
require_once APPPATH.'services/CompanyContractsSchema.php';CompanyContractsSchema::ensure($db,$p);
require_once APPPATH.'services/CompanyOperationFilesSchema.php';CompanyOperationFilesSchema::ensure($db,$p);
// Migration 424 is part of the installed baseline; 474 upgrades its expense
// scalar and adds owned-form metadata without adopting or rewriting expenses.
require_once APPPATH.'services/CompanyExpenseSchema.php';CompanyExpenseSchema::ensure($db,$p);
require_once APPPATH.'services/CompanyInvoiceAttachmentSchema.php';CompanyInvoiceAttachmentSchema::ensure($db,$p);
$db->query("INSERT INTO {$p}company_expense_categories(id,issuer_id,name,active,revision,created_at) VALUES(1,?,'Preserved expense category',1,1,'2026-10-25 00:00:00')",[$a]);
$db->query("INSERT INTO {$p}company_expenses(id,issuer_id,number,category_id,client_id,client_name,project_id,project_name,currency,expense_date,description,note,amount_cents,tax_cents,tax_rate,total_cents,state,revision,created_by,created_at,updated_at) VALUES(900,?,1,1,0,'Historical client',0,'Historical project','USD','2026-10-25','Historical expense','Preserve this row',1000,100,10,1100,'draft',1,7,'2026-10-25 00:00:00','2026-10-25 00:00:00')",[$a]);
$snapshot=static function()use($db,$p){$out=[];foreach(['options','invoices','clients','modules','company_expenses','company_expense_categories','company_invoice_drafts','company_invoice_draft_events','issuers_audit']as$table)$out[$table]=$db->query('SELECT * FROM '.$p.$table.' ORDER BY 1')->result_array();return$out;};
// Reproduce the actual MySQL implicit-constraint length failure in this disposable schema.
$probe=$p.'company_announcement_contact_dismissals';
if($db->table_exists($probe))throw new RuntimeException('Unexpected probe table already exists');
$lengthError=null;
try{$db->pdo->exec('CREATE TABLE `'.$probe.'` (issuer_id INT UNSIGNED NOT NULL, FOREIGN KEY(issuer_id) REFERENCES `'.$p.'issuers_registry`(id)) ENGINE=InnoDB');}
catch(PDOException $error){$lengthError=$error->errorInfo[1]??null;}
if($lengthError!==1059)throw new RuntimeException('Expected synthetic MySQL identifier-length error1059');
echo "Actual MySQL implicit foreign-key identifier limit reproduced (1059); explicit bounded names required.\n";
$db->query('INSERT INTO '.$p.'estimate_request_forms(id,form_key,type,name,status) VALUES(1,?,?,?,1)',['synthetic-native-form-key','estimate','Preserved native form']);
$nativeFormBefore=$db->query('SELECT id,form_key,type,name,status FROM '.$p.'estimate_request_forms ORDER BY id')->result_array();
$before=$snapshot();$migration=new Migration_Version_474();$migration->db=$db;
try{$migration->up();$migration->up();}catch(Throwable$error){if($db->lastQueryFailure!==null)fwrite(STDERR,'Native474 MySQL statement failure (values omitted): '.json_encode($db->lastQueryFailure,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n");throw$error;}
NativeCompanyProjectDeadlineSchema::assertValid($db,$p);
NativeCompanyTaskFormSchema::assertValid($db,$p);
NativeCompanyExpenseFormSchema::assertValid($db,$p);
$preservedExpense=$db->query('SELECT id,issuer_id,number,tax_rate,note FROM '.$p.'company_expenses WHERE id=900')->row_array();essentialCheck($preservedExpense&&(int)$preservedExpense['id']===900&&(int)$preservedExpense['issuer_id']===$a&&(int)$preservedExpense['number']===1&&(int)$preservedExpense['tax_rate']===10&&$preservedExpense['note']==='Preserve this row','474 expense form schema retains historical issuer, tax scalar and note');
NativeCompanyTaskChecklistSchema::assertValid($db,$p);
NativeCompanySpamSchema::assertValid($db,$p);
NativeCompanyStandaloneFollowersSchema::assertValid($db,$p);
NativeCompanyTicketLifecycleSchema::assertValid($db,$p);
NativeCompanyStaffInviteSchema::assertValid($db,$p);
NativeCompanyStaffRateSchema::assertValid($db,$p);
NativeCompanyTaskTimerDetailsSchema::assertValid($db,$p);
NativeCompanyTicketMergeSchema::assertValid($db,$p);
NativeCompanyPrivacyMailSchema::assertValid($db,$p);
NativeCompanyEstimateRequestSchema::assertValid($db,$p);
essentialCheck($db->query('SELECT id,form_key,type,name,status FROM '.$p.'estimate_request_forms ORDER BY id')->result_array()===$nativeFormBefore&&$db->query('SELECT issuer_id FROM '.$p.'estimate_request_forms WHERE id=1')->row_array()['issuer_id']===null,'native estimate form ownership addition preserves historical IDs and fields without adoption');
NativeCompanyTicketReplySchema::assertValid($db,$p);
NativeCompanyTicketPublicSchema::assertValid($db,$p);
$deadline=$db->query('SHOW FULL COLUMNS FROM '.$p.'company_projects')->result_array();
$deadline=array_column($deadline,null,'Field')['due_date'];
essentialCheck(strtolower($deadline['Type'])==='date'&&$deadline['Null']==='YES','474 preserves native optional project deadline as actual SQL NULL');
require_once APPPATH.'services/NativeCompanyInvoiceDraftProjection.php';
NativeCompanyInvoiceDraftProjectionSchema::assertValid($db,$p);
$projection=new NativeCompanyInvoiceDraftProjection($db,$p);
$frame=$projection->frame($a,7);essentialCheck(count($frame)>0,'474 backfills existing actual private drafts before reporting success');
$projectionRows=$db->query('SELECT * FROM '.$p.'company_invoice_draft_projection ORDER BY id')->result_array();
$projection->backfill();essentialCheck($db->query('SELECT * FROM '.$p.'company_invoice_draft_projection ORDER BY id')->result_array()===$projectionRows,'standalone real MySQL backfill replay is byte-preserving');
essentialCheck($snapshot()===$before,'migration does not rewrite company/client/invoice/options/module rows');
$row=$db->query('SELECT * FROM '.$p.'items WHERE id=1')->row_array();
essentialCheck((string)$row['rate']==='12.3456'&&$row['rate_currency_1']===null&&$row['rate_currency_2']===null,'native rates preserved and installed currency columns empty');
$metadata=$db->query('SHOW FULL COLUMNS FROM '.$p.'items')->result_array();$rates=array_column($metadata,null,'Field');
essentialCheck(strtolower($rates['rate_currency_2']['Type'])==='decimal(15,4)','installed precision preserved, no hook/default substituted');
// Interrupted additive DDL resumes without deleting or replacing existing rows.
$db->query('DROP TABLE '.$p.'company_custom_field_values');unset($db->data_cache['table_names']);$migration->up();
essentialCheck($db->table_exists($p.'company_custom_field_values'),'missing-only replay restores an interrupted empty child table');
$db->query('ALTER TABLE '.$p.'company_custom_fields MODIFY revision VARCHAR(12) NOT NULL');
$failed=false;try{$migration->up();}catch(Throwable $e){$failed=true;}
essentialCheck($failed,'ambiguous existing schema is rejected, not silently repaired');
$db->query('ALTER TABLE '.$p.'company_custom_fields MODIFY revision INT UNSIGNED NOT NULL');$migration->up();
essentialCheck($snapshot()===$before,'failed/recovered migration preserves all original data');
try{$migration->down();throw new LogicException('Destructive downgrade accepted');}catch(RuntimeException $e){essentialCheck($e->getMessage()==='native_company_schema_downgrade_requires_review','downgrade does not delete company data');}
echo "Native474 real MySQL additive DDL/replay/metadata/preservation passed; installer tracking and browser remain separate required gates.\n";
