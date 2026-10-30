<?php
/** Real native item model and CI/App model, synthetic query transport, real SQLite. */
require __DIR__.'/primary_catalog_models.php';
require_once APPPATH.'services/utilities/Arr.php';
require_once APPPATH.'services/NativeCompanyCatalogCurrencySchema.php';
function handle_custom_fields_post($id,$fields,$items=false){if($fields!==[])throw new RuntimeException('Native model must not borrow edit permission for creation');return false;}
function do_action_deprecated($tag,$args,$version,$replacement){hooks()->do_action($tag,...$args);}
foreach(['long_description TEXT','rate DECIMAL(15,2)','unit TEXT']as$column)$db->query('ALTER TABLE '.$p.'items ADD '.$column);
$db->query("CREATE TABLE {$p}customfields(id INTEGER PRIMARY KEY,fieldto TEXT,field_order INT)");
$db->query("CREATE TABLE {$p}customfieldsvalues(id INTEGER PRIMARY KEY,fieldid INT,fieldto TEXT,relid INT,value TEXT)");
$db->query("UPDATE {$p}items SET rate=1,unit='',long_description=''");
$db->query("CREATE TABLE {$p}currencies(id INTEGER PRIMARY KEY,name TEXT,symbol TEXT,isdefault INT,decimal_separator TEXT,thousand_separator TEXT,placement TEXT)");
$db->query("INSERT INTO {$p}currencies VALUES(1,'BRL','R$',1,',','.','before')");
NativeCompanyCatalogCurrencySchema::ensure($db,$p,2);
$ci->load=new class($ci){public function __construct(private $ci){}public function model($name):void{if($name!=='invoice_items_model')throw new RuntimeException('Unexpected model');$this->ci->$name=new Invoice_items_model();}public function dbforge():void{$this->ci->dbforge=new class{public function add_column(...$args):void{throw new RuntimeException('DDL inside writer forbidden');}};}};
$release=CompanySettingsRuntime::enterRequest($db,$p,$principal,7);
$c=new NativeCompanyCatalogUi($db,$p,$principal,7,$ci);
$definition=NativeCompanyCustomFields::definition(['fieldto'=>'items','name'=>'Options','type'=>'checkbox','options'=>'Alpha,Beta']);
$db->query("INSERT INTO {$p}company_custom_fields(issuer_id,fieldto,slug,definition_json,revision) VALUES(?,'items','items_options',?,1)",[$principal,json_encode($definition,JSON_THROW_ON_ERROR)]);$fid=NativeCompanyResourceId::encode('company_custom_field',$db->insert_id());
$input=['description'=>'Real native item','rate'=>'12.30','unit'=>'un','custom_fields'=>['items_pr'=>[$fid=>['Alpha','Beta']]]];
$id=$c->saveItem($input);$row=$c->item($id);
essentialCheck($row['source_kind']==='item'&&$row['description']==='Real native item'&&(string)$row['rate']==='12.3','actual native add with explicit principal lineage');
$f=new NativeCompanyCustomFields($db,$p);essentialCheck($f->values($principal,7,'items_pr',$id)===[$fid=>'Alpha, Beta'],'fresh native source attests typed fields');
$copy=$c->copyItem($id,$row['revision']);essentialCheck($f->values($principal,7,'items_pr',$copy)===[$fid=>'Alpha, Beta'],'native copy preserves typed custom values');
$c->saveItem($input+['long_description'=>'Edited'],$id,$row['revision']);$updated=$c->item($id);essentialCheck($updated['long_description']==='Edited','actual native edit preserves identity');
settingThrows(fn()=>$c->saveItem($input,$id,$row['revision']),'company_catalog_stale','stale item data rejected');
$db->query("UPDATE {$p}company_custom_field_values SET value='Beta' WHERE issuer_id=? AND source_kind='item' AND source_id=?",[$principal,$id]);
settingThrows(fn()=>$c->saveItem($input,$id,$updated['revision']),'company_catalog_stale','custom-only concurrent write invalidates item CAS');
$count=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'items')->row()->n;
$db->query("CREATE TRIGGER ignore_native_item BEFORE INSERT ON {$p}items BEGIN SELECT RAISE(IGNORE); END");
settingThrows(fn()=>$c->saveItem($input),'issuer_catalog_write_unconfirmed','ignored source insert cannot reuse old insert ID');
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'items')->row()->n===$count,'ignored insert leaves no partial source');$db->query('DROP TRIGGER ignore_native_item');
$second=$c->saveItem(['description'=>'Second native item','rate'=>'3']);$historical=$c->item($copy);$secondRow=$c->item($second);
settingThrows(fn()=>$c->deleteItems([$copy=>$historical['revision'],$second=>str_repeat('0',64)]),'company_catalog_stale','bulk failure rolls back previous native model deletion');
essentialCheck($c->item($copy)['id']===$copy&&$c->item($second)['id']===$second,'both native sources remain after stale second item');
$c->deleteItems([$copy=>$historical['revision'],$second=>$secondRow['revision']]);
essentialCheck(!$db->query('SELECT id FROM '.$p.'items WHERE id=?',[$copy])->row_array()&&!$db->query('SELECT id FROM '.$p.'items WHERE id=?',[$second])->row_array(),'native bulk deletes both exact sources atomically');
essentialCheck(!$db->query("SELECT value FROM {$p}company_custom_field_values WHERE issuer_id=? AND source_kind='item' AND source_id=?",[$principal,$copy])->row_array(),'native deletion cleans owned custom override only for deleted lineage');
$model=new Invoice_items_model();
essentialCheck($model->get($id)->itemid===$id&&count($model->get())>=2,'actual native model reader dispatches existing single/list contract through explicit catalogue');
$grouped=$model->get_grouped();$flat=[];foreach($grouped as$rows)foreach($rows as$row)$flat[]=$row['id'];essentialCheck(in_array($id,$flat,true),'actual native grouped reader retains principal source');
essentialCheck($model->get_for_company($principal,7,$id)->itemid===$id,'non-UI reader accepts explicit authenticated issuer and actor');
settingThrows(fn()=>$model->get_for_company(41,7,$id),'company_catalog_denied','explicit non-UI reader never falls through to principal raw item');
CompanyRequestScope::$sealed=false;settingThrows(fn()=>$model->get($id),'company_catalog_denied','model reader requires sealed native context');CompanyRequestScope::$sealed=true;
$release();echo "Native historical catalogue actual model creation/edit/copy/CAS and ignored insert: passed.\n";
