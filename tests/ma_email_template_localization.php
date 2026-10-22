<?php
/** Real PDO fixtures/native migration and model methods. No SMTP, transport, credentials or production data. */
require __DIR__.'/issuers_core_support.php';
require dirname(__DIR__).'/modules/ma/services/MaEmailTemplateDefaults.php';
class App_Model { public $db; }
require dirname(__DIR__).'/modules/ma/models/Ma_model.php';
function get_staff_user_id(){ return 7; }
function get_option($key){ return $key === 'active_language' ? ($GLOBALS['ma_active_language'] ?? 'portuguese_br') : ''; }
require dirname(__DIR__).'/modules/ma/helpers/Ma_helper.php';

function maFixture(): object {
    $f = essentialFixture(); $db=$f->db; $p=$f->prefix;
    $db->query("CREATE TABLE {$p}ma_email_templates(id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, name TEXT NOT NULL, color TEXT NOT NULL, category INT NOT NULL DEFAULT 0, published INT NOT NULL DEFAULT 1, language TEXT, data_html LONGTEXT, data_design LONGTEXT, description TEXT, addedfrom INT, dateadded DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE {$p}ma_email_template_designs(id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, email_template_id INT NOT NULL, language VARCHAR(40), data_html LONGTEXT, data_design LONGTEXT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE {$p}ma_emails(id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, email_template INT, name TEXT, subject TEXT, description TEXT, published INT DEFAULT 1, addedfrom INT, dateadded DATETIME) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE {$p}ma_email_designs(id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, email_id INT NOT NULL, language VARCHAR(40), data_html LONGTEXT, data_design LONGTEXT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    foreach (['ma_campaigns','ma_email_logs','ma_marketing_messages'] as $table) $db->query("CREATE TABLE {$p}{$table}(id INT PRIMARY KEY, data LONGTEXT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("INSERT INTO {$p}ma_emails(id,email_template,name,subject,description,published,addedfrom,dateadded) VALUES(400,1,'Existing campaign','Assunto customizado','Original',1,7,'2026-10-21 00:00:00')");
    $db->query("INSERT INTO {$p}ma_email_designs(email_id,language,data_html,data_design) VALUES(400,'portuguese_br','\"Campanha {client_name} personalizada\"','\"{}\"')");
    foreach (['ma_campaigns','ma_email_logs','ma_marketing_messages'] as $table) $db->insert($p.$table,['id'=>1,'data'=>'{"email":400,"active":true,"future":"2030-01-01"}']);
    unset($db->data_cache['table_names']);
    $db->insert($p.'modules',['module_name'=>'ma','installed_version'=>'1.1.0','active'=>1]);
    $f->ci->app_modules = new class { public function get($name){ return ['headers'=>['version'=>'1.1.1']]; } };
    $f->model = (new ReflectionClass(Ma_model::class))->newInstanceWithoutConstructor();$f->model->db=$db;
    return $f;
}
function maRows(object $f,string $table):array { return $f->db->query('SELECT * FROM '.$f->prefix.$table.' ORDER BY id')->result_array(); }
function maSnapshot(object $f):array { $out=[];foreach(['ma_email_templates','ma_email_template_designs','ma_emails','ma_email_designs','ma_campaigns','ma_email_logs','ma_marketing_messages'] as $t)$out[$t]=maRows($f,$t);return $out; }
function maSeed(object $f,array $item,array $override=[],array $designOverride=[]):int {
    $row=array_replace(array_merge($item['source'],$item['origin_metadata']),$override);$f->db->insert($f->prefix.'ma_email_templates',$row);$id=$f->db->insert_id();
    $f->db->insert($f->prefix.'ma_email_template_designs',array_replace(['email_template_id'=>$id,'language'=>'english','data_html'=>$item['source']['data_html'],'data_design'=>$item['source']['data_design']],$designOverride));return $id;
}
function maAddDesign(object $f,int $id,array $content,string $language):void { $f->db->insert($f->prefix.'ma_email_template_designs',['email_template_id'=>$id,'language'=>$language,'data_html'=>$content['data_html'],'data_design'=>$content['data_design']]); }
function maDesign(object $f,int $id,string $language):array { return $f->db->query('SELECT * FROM '.$f->prefix.'ma_email_template_designs WHERE email_template_id=? AND language=?',[$id,$language])->row_array(); }
function maVersion(object $f):string { return (string)$f->db->where('module_name','ma')->get($f->prefix.'modules')->row()->installed_version; }

foreach(['portuguese_br'=>1,'english'=>0,'french'=>2,'unknown'=>0] as $language=>$key){$GLOBALS['ma_active_language']=$language;essentialCheck(ma_email_preview_key([['language'=>'english'],['language'=>'portuguese_br'],['language'=>'french']])===$key,'preview selects configured language '.$language);}
unset($GLOBALS['ma_active_language']);essentialCheck(ma_email_preview_key([])===null,'empty preview has no artificial active language');
$catalogue=MaEmailTemplateDefaults::catalogue();essentialCheck(count($catalogue)===7,'all seven MA library defaults catalogued');
$fresh=maFixture();$campaignBefore=maSnapshot($fresh);
$fresh->db->data_cache['table_names']=[$fresh->prefix.'modules']; // CI3's pre-DDL cache during install.php.
essentialCheck(MaEmailTemplateDefaults::install($fresh->db,$fresh->prefix)===21,'fresh install refreshes pre-DDL cache and writes seven parents and fourteen real language variants');
$parents=maRows($fresh,'ma_email_templates');essentialCheck(count($parents)===7,'fresh library has exactly seven choices, not fourteen duplicate templates');
foreach($parents as $i=>$parent){
 $item=$catalogue[$i];essentialCheck($parent['name']===$item['target']['name'],'fresh name in Portuguese '.$i);
 essentialCheck($parent['language']==='english' && $parent['data_html']===$item['source']['data_html'],'legacy language truthfully remains English '.$i);
 foreach(['source','target'] as $variant){$design=maDesign($fresh,(int)$parent['id'],$item[$variant]['language']);essentialCheck($design['data_html']===$item[$variant]['data_html'] && $design['data_design']===$item[$variant]['data_design'],'both editor/HTML language representations match '.$i.' '.$variant);}
}
$freshSnapshot=maSnapshot($fresh);essentialCheck(MaEmailTemplateDefaults::install($fresh->db,$fresh->prefix)===0 && MaEmailTemplateDefaults::upgrade($fresh->db,$fresh->prefix)===0 && maSnapshot($fresh)===$freshSnapshot,'seed and upgrade replay are exact no-ops');
foreach(['ma_emails','ma_email_designs','ma_campaigns','ma_email_logs','ma_marketing_messages'] as $t)essentialCheck(maRows($fresh,$t)===$campaignBefore[$t],'fresh install never changes existing '.$t);
// The actual model copies both languages for a future email; no transport is called.
$newEmail=$fresh->model->add_email(['email_template'=>$parents[0]['id'],'name'=>'Future draft','subject'=>'Assunto escolhido','description'=>'','published'=>0]);
essentialCheck((bool)$newEmail,'actual model creates a future draft');
foreach(['english','portuguese_br'] as $lang)essentialCheck($fresh->model->get_email_content_by_contact($newEmail,['lead'=>['default_language'=>$lang]])===$catalogue[0][$lang==='english'?'source':'target']['data_html'],'actual MA selection respects explicit '.$lang);
essentialCheck($fresh->model->get_email_content_by_contact($newEmail,['client'=>['default_language'=>'']])===$catalogue[0]['target']['data_html'],'new email uses configured PTBR when recipient has no preference');
essentialCheck($fresh->model->get_email_content_by_contact(400,['client'=>['default_language'=>'portuguese_br']])==='"Campanha {client_name} personalizada"','existing campaign snapshot is unchanged');

$upgrade=maFixture();$ids=[];
foreach($catalogue as $i=>$item)$ids[$i]=maSeed($upgrade,$item);
// A/B custom/default siblings and disabled state.
$upgrade->db->query('UPDATE '.$upgrade->prefix.'ma_email_template_designs SET data_html=? WHERE email_template_id=?',['"Custom English A"',$ids[1]]);
maAddDesign($upgrade,$ids[2],['data_html'=>'','data_design'=>''],'portuguese_br');
maAddDesign($upgrade,$ids[3],$catalogue[3]['source'],'portuguese_br');
$upgrade->db->query('UPDATE '.$upgrade->prefix.'ma_email_templates SET name=? WHERE id=?',['Título customizado',$ids[4]]);
$upgrade->db->query('UPDATE '.$upgrade->prefix.'ma_email_templates SET description=? WHERE id=?',['Descrição personalizada',$ids[5]]);
$upgrade->db->query('UPDATE '.$upgrade->prefix.'ma_email_templates SET addedfrom=9 WHERE id=?',[$ids[6]]);
$upgrade->db->query('UPDATE '.$upgrade->prefix.'ma_email_templates SET published=0,category=42,color=? WHERE id=?',['#123456',$ids[0]]);
maAddDesign($upgrade,$ids[0],['data_html'=>'"Texte français"','data_design'=>'"{}"'],'french');
$before=maSnapshot($upgrade);
essentialCheck((new App_module_migration('ma'))->version(111)==111 && maVersion($upgrade)==='1.1.1','native module migration reaches 111 with real helper');
$after=maSnapshot($upgrade);
foreach([1,2,4,5,6] as $i){essentialCheck($after['ma_email_templates'][$i]===$before['ma_email_templates'][$i],'custom/provenance parent preserved '.$i);essentialCheck(maDesign($upgrade,$ids[$i],'portuguese_br')===($i===2?array_values(array_filter($before['ma_email_template_designs'],fn($r)=>$r['email_template_id']==$ids[$i]&&$r['language']==='portuguese_br'))[0]:[]),'custom design or absence preserved '.$i);}
foreach([0,3] as $i){essentialCheck(maDesign($upgrade,$ids[$i],'portuguese_br')['data_html']===$catalogue[$i]['target']['data_html'],'recognized default localized '.$i);essentialCheck(maDesign($upgrade,$ids[$i],'english')['data_html']===$catalogue[$i]['source']['data_html'],'English remains intact '.$i);}
foreach(['published','category','color','language','addedfrom','dateadded','id','data_html','data_design'] as $field)essentialCheck($after['ma_email_templates'][0][$field]===$before['ma_email_templates'][0][$field],'state/legacy/ownership preserved '.$field);
essentialCheck(maDesign($upgrade,$ids[0],'french')['data_html']==='"Texte français"','third language preserved');
foreach(['ma_emails','ma_email_designs','ma_campaigns','ma_email_logs','ma_marketing_messages'] as $t)essentialCheck($after[$t]===$before[$t],'native migration preserves '.$t);
essentialCheck((new App_module_migration('ma'))->version(111)==111 && MaEmailTemplateDefaults::upgrade($upgrade->db,$upgrade->prefix)===0 && maSnapshot($upgrade)===$after,'native migration/helper replay has no delta');
// Missing defaults never get resurrected by upgrade, nor inside a non-empty custom install.
$missing=maFixture();maSeed($missing,$catalogue[0],['name'=>'Only my template']);$before=maSnapshot($missing);essentialCheck(MaEmailTemplateDefaults::install($missing->db,$missing->prefix)===0 && MaEmailTemplateDefaults::upgrade($missing->db,$missing->prefix)===0 && maSnapshot($missing)===$before,'occupied custom library is never replenished');
$empty=maFixture();essentialCheck(MaEmailTemplateDefaults::upgrade($empty->db,$empty->prefix)===0 && maRows($empty,'ma_email_templates')===[],'upgrade does not recreate intentionally absent defaults');
// Failure after the new design insert must roll it back, and leave native tracking at 110.
$failure=maFixture();maSeed($failure,$catalogue[0]);$before=maSnapshot($failure);$failure->db->failOn='UPDATE '.$failure->prefix.'ma_email_templates';
essentialThrows(fn()=>(new App_module_migration('ma'))->version(111),'issuer_storage_failed','second-write failure propagates to native runner');$failure->db->failOn=null;
essentialCheck(maSnapshot($failure)===$before && maVersion($failure)==='1.1.0','failed native migration rolls back insert and leaves old version');
essentialCheck((new App_module_migration('ma'))->version(111)==111,'native retry succeeds after transactional failure');
// A duplicate or deleted English variant cannot silently choose an arbitrary default.
$duplicate=maFixture();$id=maSeed($duplicate,$catalogue[0]);maAddDesign($duplicate,$id,$catalogue[0]['source'],'english');$before=maSnapshot($duplicate);
essentialThrows(fn()=>MaEmailTemplateDefaults::upgrade($duplicate->db,$duplicate->prefix),'ma_email_design_ambiguous','duplicate source language fails closed');essentialCheck(maSnapshot($duplicate)===$before,'ambiguous migration is non-mutating');
$deleted=maFixture();$id=maSeed($deleted,$catalogue[0]);$deleted->db->query('DELETE FROM '.$deleted->prefix.'ma_email_template_designs');$before=maSnapshot($deleted);essentialCheck(MaEmailTemplateDefaults::upgrade($deleted->db,$deleted->prefix)===0 && maSnapshot($deleted)===$before,'deleted English design is not bypassed');
$deleted->db->trans_begin();essentialThrows(fn()=>MaEmailTemplateDefaults::upgrade($deleted->db,$deleted->prefix),'issuer_outer_transaction_not_allowed','never commits caller transaction');essentialCheck($deleted->db->pdo->inTransaction(),'caller retains transaction ownership');$deleted->db->trans_rollback();

$locked=maFixture();$locked->db->query('CREATE TABLE '.$locked->prefix.'issuers_lock(id INT PRIMARY KEY) ENGINE=InnoDB');$locked->db->query('INSERT INTO '.$locked->prefix.'issuers_lock VALUES(1)');
essentialCheck(MaEmailTemplateDefaults::install($locked->db,$locked->prefix)===21 && MaEmailTemplateDefaults::upgrade($locked->db,$locked->prefix)===0,'company-foundation global transaction path seeds and replays');
$seedFailure=maFixture();$before=maSnapshot($seedFailure);$seedFailure->db->failOn='INSERT INTO '.$seedFailure->prefix.'ma_email_template_designs';
essentialThrows(fn()=>MaEmailTemplateDefaults::install($seedFailure->db,$seedFailure->prefix),'issuer_storage_failed','fresh design failure is propagated');$seedFailure->db->failOn=null;essentialCheck(maSnapshot($seedFailure)===$before,'fresh design failure rolls back parent insert');
$tracking=maFixture();maSeed($tracking,$catalogue[0]);$tracking->db->failVersion='1.1.1';essentialCheck((new App_module_migration('ma'))->version(111)===false && maVersion($tracking)==='1.1.0','native tracking failure is never reported complete');$tracking->db->failVersion=null;$after=maSnapshot($tracking);essentialCheck((new App_module_migration('ma'))->version(111)==111 && maSnapshot($tracking)===$after,'tracking retry records version without duplicating content');

echo 'MA library localization: '.$GLOBALS['essentialChecks'].' checks passed ('.$fresh->db->driver().'); no email sent.'.PHP_EOL;
// Only synthetic random-prefix fixtures are removed; no user table is accessed.
foreach($GLOBALS['essentialFixtures'] as $f)if($f->db->driver()==='mysql')foreach(['ma_email_template_designs','ma_email_templates','ma_email_designs','ma_emails','ma_campaigns','ma_email_logs','ma_marketing_messages'] as $t)$f->db->pdo->exec('DROP TABLE IF EXISTS '.$f->prefix.$t);
essentialCleanup();
