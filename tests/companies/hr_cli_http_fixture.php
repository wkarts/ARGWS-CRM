<?php
/** Finite preparation/readback for the actual 456 CLI; never calls a queue processor. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!in_array(getenv('ISSUERS_BROWSER_DB_NAME'),['argws_upgrade_ci','argws_fresh_ci','argws_crm_ci','argws_hr_cli_ci','argws_legacy_ci'],true))exit(2);
define('FCPATH',dirname(__DIR__,2).'/');define('BASEPATH',FCPATH.'system/');define('APPPATH',FCPATH.'application/');require APPPATH.'config/app-config.php';
if(APP_DB_NAME!==getenv('ISSUERS_BROWSER_DB_NAME')||!preg_match('/^(127\.0\.0\.1|argws-crm-provision-db-[0-9]+)(:[0-9]+)?$/D',APP_DB_HOSTNAME))exit(2);
require dirname(__DIR__).'/support/BbPdoDatabase.php';require APPPATH.'services/CompanyHrApi.php';require APPPATH.'services/issuers/IssuerRegistry.php';function db_prefix(){return 'tbl';}
$pdo=new PDO('mysql:host='.str_replace(':',';port=',APP_DB_HOSTNAME).';dbname='.APP_DB_NAME.';charset=utf8mb4',APP_DB_USERNAME,APP_DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$db=new BbPdoDatabase($pdo);
$identity=$db->query('SELECT master_staff_id,primary_issuer_id,source FROM tblissuers_installation_identity WHERE id=1')->row_array();$version=(int)$db->query('SELECT version FROM tblmigrations')->row()->version;$expected=getenv('ISSUERS_HR_EXPECTED_VERSION')?:'456';
if(!preg_match('/^[0-9]{3,4}$/D',$expected)||(int)$expected<456||$version!==(int)$expected||(int)$identity['master_staff_id']!==17||!in_array($identity['source'],['installer','legacy_designation'],true))throw new RuntimeException('Native master17 and explicit exact RH migration required');
CompanyHrSchema::assertValid($db,'tbl');CompanyApiSchema::assertValid($db,'tbl');$s=new CompanyHr($db,'tbl');$tokens=new CompanyApiTokens($db,'tbl');$mode=$argv[1]??'';$run=$argv[2]??'';
if(!preg_match('/^[a-f0-9]{12}$/D',$run))throw new RuntimeException('Finite fresh CLI fixture identifier required');
$value=null;$courseTitle='HRCLI course '.$run;
if($mode==='prepare'){
    if($db->query('SELECT id FROM tblcompany_hr_courses WHERE title=?',[$courseTitle])->result_array())throw new RuntimeException('Fresh CLI fixture identifier required; existing work is preserved');
    $registry=new IssuerRegistry($db,'tbl');$owners=[];$targets=[];$subjects=[];$optOut=[];$expires=[];$beforeIdentity=$identity;
    foreach(['a'=>['HR CLI A','11222333000181'],'b'=>['HR CLI B','52998224725']] as $label=>[$name,$tax]){$found=$db->query('SELECT * FROM tblissuers_registry WHERE tax_id=?',[$tax])->result_array();if(count($found)>1)throw new RuntimeException('Ambiguous test company');$owners[$label]=$found?(int)$found[0]['id']:$registry->saveIssuer(17,['legal_name'=>$name,'tax_id'=>$tax]);$row=$db->query('SELECT * FROM tblissuers_registry WHERE id=?',[$owners[$label]])->row_array();if(!$row||(int)$row['active']!==1||$row['identity_status']!=='complete'||$owners[$label]===(int)$identity['primary_issuer_id'])throw new RuntimeException('Complete separate test company required');}
    $owners['principal']=(int)$identity['primary_issuer_id'];
    foreach($owners as $label=>$i){
        $key=static fn(string $part)=>hash('sha256','hr-cli|'.$run.'|'.$label.'|'.$part);$email='hr-cli-'.$label.'-'.$run.'@example.invalid';
        if($db->query('SELECT staffid FROM tblstaff WHERE email=?',[$email])->result_array())throw new RuntimeException('Fresh synthetic actor required');
        $db->query('INSERT INTO tblstaff(email,firstname,lastname,password,datecreated,active,admin) VALUES(?,?,?,?,?,1,0)',[$email,'HR CLI',ucfirst($label),password_hash('synthetic-hr-cli-only',PASSWORD_BCRYPT),gmdate('Y-m-d H:i:s')]);$actor=(int)$db->insert_id();
        $db->query('INSERT INTO tblissuers_staff(issuer_id,staff_id,can_view) VALUES(?,?,1)',[$i,$actor]);
        foreach([['company_api','manage'],['hr','view'],['hr_training','assign']] as [$feature,$cap])$db->query('INSERT INTO tblissuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,?,?,?)',[$i,$actor,$feature,$cap]);
        $unit=$s->saveStructure($i,17,'unit',null,null,['name'=>'HRCLI unit '.$run],$key('unit'));$position=$s->saveStructure($i,17,'position',null,null,['name'=>'HRCLI position '.$run,'unit_id'=>$unit],$key('position'));$workplace=$s->saveStructure($i,17,'workplace',null,null,['name'=>'HRCLI workplace '.$run],$key('workplace'));
        $employee=$s->saveEmployee($i,17,null,null,['staff_id'=>$actor,'employee_code'=>'HRCLI-'.$label.'-'.$run,'unit_id'=>$unit,'position_id'=>$position,'workplace_id'=>$workplace,'manager_id'=>null,'joined_on'=>'2026-01-01','left_on'=>null,'state'=>'active'],$key('employee'));
        $course=$s->saveCourse($i,17,null,null,['title'=>$courseTitle,'description'=>'Synthetic native CLI proof','questions'=>[['question'=>'Which company owns this assignment?','choices'=>['The captured company','An arbitrary other company'],'correct'=>0]],'pass_percent'=>100,'max_attempts'=>1,'active'=>1],$key('course'));
        $due=gmdate('Y-m-d\TH:i',time()+86400);$subjects[$label]=['issuer_id'=>$i,'actor_id'=>$actor,'employee_id'=>$employee,'course_id'=>$course];$issued=[];
        foreach(['active','revoked','expired','rotated','replacement','gui','optout'] as $case){
            $origin=null;
            if(in_array($case,['gui','optout'],true)){$consumer=$s;}
            else{
                if($case==='replacement'){$credential=$issued['replacement'];}
                else{$credential=$tokens->issue($i,$actor,'HRCLI-'.$run.'-'.$case,['hr:training:assign'],1,$key('token-'.$case));}
                [$consumer,$who]=CompanyHr::forApi($db,'tbl',$credential['secret'],'hr:training:assign');if($who['issuer_id']!==$i||$who['actor_id']!==$actor)throw new RuntimeException('Native bearer origin mismatch');$origin=$credential['token_id'];
            }
            $assignment=$consumer->assignCourse($i,$actor,$employee,$course,$due,'UTC',$case!=='optout',$key('assignment-'.$case));
            $row=$db->query('SELECT * FROM tblcompany_hr_assignments WHERE id=?',[$assignment])->row_array();$contract=json_decode($row['contract_json'],true,64,JSON_THROW_ON_ERROR);$expectedOrigin=$origin===null?null:['token_id'=>$origin,'scope'=>'hr:training:assign'];
            if(($contract['api_origin']??null)!==$expectedOrigin||(int)$row['actor_id']!==$actor||(int)$row['issuer_id']!==$i)throw new RuntimeException('Persisted native origin missing');
            if($case==='optout'){$notices=$db->query('SELECT id FROM tblcompany_hr_notices WHERE assignment_id=?',[$assignment])->result_array();if($notices!==[])throw new RuntimeException('Opt-out created a notice');$optOut[]=$assignment;continue;}
            $notices=$db->query('SELECT id,event,state,scheduled_at FROM tblcompany_hr_notices WHERE assignment_id=? ORDER BY id',[$assignment])->result_array();if(count($notices)!==2||array_column($notices,'event')!==['assigned','due'])throw new RuntimeException('Real assignment did not create exact two opt-in notices');
            foreach($notices as $n){if($n['state']!=='pending')throw new RuntimeException('Preparation may not process a queue');$targets[]=['id'=>(int)$n['id'],'issuer_id'=>$i,'employee_id'=>$employee,'actor_id'=>$actor,'assignment_id'=>$assignment,'company'=>$label,'case'=>$case,'event'=>$n['event'],'expected'=>in_array($case,['revoked','expired','rotated'],true)?'blocked':($n['event']==='assigned'?'published':'waiting'),'origin_token_id'=>$origin];}
            if($case==='revoked')$tokens->revoke($i,$actor,$credential['token_id']);
            elseif($case==='rotated'){$issued['replacement']=$tokens->issue($i,$actor,'HRCLI-'.$run.'-replacement',['hr:training:assign'],1,$key('token-replacement'),$credential['token_id']);$resolved=$tokens->resolve($issued['replacement']['secret'],'hr:training:assign');if($resolved['actor_id']!==$actor||$resolved['issuer_id']!==$i||$resolved['token_id']===$origin)throw new RuntimeException('Valid independent replacement credential required');}
            elseif($case==='expired'){
                // The public issuer supports whole days. Only this synthetic credential is shortened;
                // the job is created while valid, then the test waits for the actual UTC deadline.
                $deadline=gmdate('Y-m-d H:i:s',time()+3);$db->query('UPDATE tblcompany_api_tokens SET expires_at=? WHERE token_id=? AND issuer_id=? AND actor_id=?',[$deadline,$origin,$i,$actor]);$expires[]=$deadline;
            }
        }
    }
    if($db->query('SELECT master_staff_id,primary_issuer_id,source FROM tblissuers_installation_identity WHERE id=1')->row_array()!==$beforeIdentity)throw new RuntimeException('Native identity changed');
    $value=['native_version'=>$version,'run'=>$run,'owners'=>$owners,'subjects'=>$subjects,'targets'=>$targets,'optout_assignments'=>$optOut,'expiry_deadlines'=>$expires,'expiry_mode'=>'Only synthetic token deadlines shortened after real issue and real assignment; worker uses actual UTC, with no injected clock or processor call.'];
}elseif($mode==='state'){
    $maximum=(int)($db->query('SELECT MAX(id) n FROM tblcompany_hr_notices')->row()->n??0);if($maximum>10000)throw new RuntimeException('Finite disposable queue bound exceeded');$cursor=$db->query('SELECT * FROM tblcompany_hr_cursor ORDER BY id')->result_array();if(count($cursor)!==1||(int)$cursor[0]['id']!==1)throw new RuntimeException('Exact native cursor required');$last=(int)$cursor[0]['last_id'];$upper=(int)$cursor[0]['upper_id'];if($last===$upper){$last=0;$upper=$maximum;}
    $value=['native_version'=>$version,'now_utc'=>gmdate('Y-m-d H:i:s'),'maximum_id'=>$maximum,'cursor'=>$cursor,'selected_ids'=>array_map('intval',array_column($db->query('SELECT id FROM tblcompany_hr_notices WHERE id>? AND id<=? ORDER BY id LIMIT 25',[$last,$upper])->result_array(),'id')),'notices'=>$db->query('SELECT id,issuer_id,employee_id,assignment_id,actor_id,event,scheduled_at,state,read_at,created_at FROM tblcompany_hr_notices ORDER BY id')->result_array(),'audit'=>[],'preserved'=>[],'other_cursors'=>[]];
    foreach($db->query('SELECT * FROM tblissuers_audit ORDER BY id')->result_array() as $r)$value['audit'][]=['id'=>(int)$r['id'],'issuer_id'=>(int)$r['issuer_id'],'actor_id'=>(int)$r['actor_id'],'event'=>$r['event'],'subject_id'=>(int)$r['subject_id'],'metadata_json'=>$r['metadata_json'],'sha256'=>hash('sha256',json_encode($r,JSON_THROW_ON_ERROR))];
    $technical=['tblcompany_task_schedule_cursor','tblcompany_lead_notice_cursor','tblcompany_hr_cursor'];$mutable=array_merge($technical,['tblcompany_hr_notices','tblissuers_audit']);
    foreach($db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME')->result_array() as $t){$table=$t['TABLE_NAME'];if(!preg_match('/^tbl[A-Za-z0-9_]+$/D',$table))throw new RuntimeException('Unexpected native table name');if(in_array($table,$technical,true)&&$table!=='tblcompany_hr_cursor')$value['other_cursors'][$table]=$db->query('SELECT * FROM '.$table.' ORDER BY id')->result_array();if(in_array($table,$mutable,true))continue;$rows=$db->query('SELECT * FROM '.$table)->result_array();foreach($rows as &$row)ksort($row);unset($row);usort($rows,static fn($x,$y)=>serialize($x)<=>serialize($y));$value['preserved'][$table]=['count'=>count($rows),'sha256'=>hash('sha256',serialize($rows))];}
    $value['source_hashes']=[];foreach(['application/controllers/Company_recurring_worker.php','application/services/CompanyRecurring.php','application/services/CompanyExpenseRecurring.php','application/services/CompanyInvoiceRecurring.php','application/services/CompanyTaskRecurring.php','application/services/CompanyLeads.php','application/services/CompanyHr.php','application/services/CompanyHrNotices.php','application/services/CompanyHrTraining.php','application/services/CompanyHrSchema.php','application/services/CompanyApiTokens.php'] as $path)$value['source_hashes'][$path]=hash_file('sha256',FCPATH.$path);
}else throw new RuntimeException('Unknown finite HR CLI fixture operation');
echo json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE).PHP_EOL;
