#!/usr/bin/env php
<?php
/** Read-only lexical inventory. No application/module code is loaded or executed. */
declare(strict_types=1);
$root=dirname(__DIR__);
$check=in_array('--check',$argv,true);
$full=in_array('--full',$argv,true);
$target=$root.'/docs/companies/operational-surfaces.json';
foreach($argv as $index=>$arg)if($arg==='--output'){
    $output=$argv[$index+1]??'';if($output===''||str_starts_with($output,'--'))throw new InvalidArgumentException('Output path required');
    $target=$output[0]==='/'?$output:$root.'/'.$output;
}
if($full&&$target===$root.'/docs/companies/operational-surfaces.json')throw new InvalidArgumentException('Full inventory requires a separate --output path');
$skip=['vendor','node_modules','third_party','vendor-prefixed'];
$private=['application/config/app-config.php','application/config/my_hooks.php','application/config/my_autoload.php'];
$optionCalls=['get_option','add_option','update_option','delete_option','option_exists'];
$hookCalls=['add_action','add_filter','register_activation_hook','register_deactivation_hook','register_uninstall_hook','register_cron_task'];
$infrastructure=['cron_has_run_from_cli','last_cron_run','last_updated_date','update_info_message'];
$files=[];$modules=[];$options=[];$dynamic=[];$controllers=[];$hooks=[];$jobs=[];$reports=[];$tables=[];$databaseCalls=[];
foreach(glob($root.'/modules/*',GLOB_ONLYDIR) as $directory){
    $name=basename($directory);$entry=$directory.'/'.$name.'.php';$version=null;
    if(is_file($entry)&&preg_match('/^Version:\s*([^\r\n]+)/m',file_get_contents($entry),$m))$version=trim($m[1]);
    $modules[$name]=['name'=>$name,'entry'=>is_file($entry)?'modules/'.$name.'/'.$name.'.php':null,'version'=>$version,'deployment_enabled'=>'not_observed','state'=>'not_verified','operational_ready'=>false,'admin_controllers'=>[],'public_controllers'=>[],'job_candidates'=>[],'report_candidates'=>[],'option_keys'=>[],'table_candidates'=>[]];
}
ksort($modules,SORT_STRING);
function surfaceText($token):string{return is_array($token)?$token[1]:$token;}
function surfaceNext(array $tokens,int $index):int
{
    $count=count($tokens);while($index<$count&&is_array($tokens[$index])&&in_array($tokens[$index][0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true))$index++;
    return $index;
}
function surfaceLiteral($token):?string
{
    if(!is_array($token)||$token[0]!==T_CONSTANT_ENCAPSED_STRING)return null;
    $value=substr($token[1],1,-1);
    return $token[1][0]==="'"?str_replace(["\\'","\\\\"],["'","\\"],$value):stripcslashes($value);
}
function surfaceArgument(array $tokens,int $index):array
{
    $next=surfaceNext($tokens,$index+1);
    if(($tokens[$next]??null)!=='(')return [false,null,''];
    $arg=surfaceNext($tokens,$next+1);$literal=surfaceLiteral($tokens[$arg]??null);
    $end=surfaceNext($tokens,$arg+1);
    if($literal!==null&&in_array($tokens[$end]??null,[',',')'],true))return [true,$literal,''];
    $text='';$depth=0;
    for($j=$arg;$j<count($tokens)&&strlen($text)<160;$j++){
        $part=surfaceText($tokens[$j]);
        if($depth===0&&in_array($part,[',',')'],true))break;
        if($part==='(')$depth++;if($part===')')$depth--;
        $text.=$part;
    }
    return [true,null,substr(preg_replace('/\s+/',' ',trim($text)),0,160)];
}
function surfaceMethod(array $tokens,int $index):?array
{
    $next=surfaceNext($tokens,$index+1);if(($tokens[$next]??null)==='&')$next=surfaceNext($tokens,$next+1);
    if(!is_array($tokens[$next]??null)||$tokens[$next][0]!==T_STRING)return null;
    $visibility='public';
    for($i=$index-1;$i>=0;$i--){
        $part=$tokens[$i];if(!is_array($part)&&in_array($part,['{','}',';'],true))break;
        if(is_array($part)&&$part[0]===T_PRIVATE){$visibility='private';break;}
        if(is_array($part)&&$part[0]===T_PROTECTED){$visibility='protected';break;}
    }
    return ['method'=>$tokens[$next][1],'line'=>$tokens[$index][2],'visibility'=>$visibility];
}
foreach(['application','modules'] as $folder){
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$folder,FilesystemIterator::SKIP_DOTS));
    foreach($iterator as $file){
        if(!$file->isFile()||$file->isLink()||strtolower($file->getExtension())!=='php')continue;
        $path=str_replace(DIRECTORY_SEPARATOR,'/',substr($file->getPathname(),strlen($root)+1));
        if(array_intersect(explode('/',$path),$skip)||str_contains($path,'/language/')||in_array($path,$private,true))continue;
        $files[$path]=hash_file('sha256',$file->getPathname());
    }
}
ksort($files,SORT_STRING);
foreach($files as $path=>$hash){
    $source=file_get_contents($root.'/'.$path);$tokens=token_get_all($source);
    $module=str_starts_with($path,'modules/')?explode('/',$path)[1]:null;
    $isController=str_contains($path,'/controllers/');
    $parent=null;$methods=[];$calls=[];
    // Prefix expressions locate table candidates without executing SQL or copying
    // arbitrary query values, private configuration or credentials into the report.
    $tablePatterns=[
        '/(?:db_prefix\s*\(\s*\)|\$this->(?:p|prefix)|\$(?:p|prefix))\s*\.\s*[\'"]([a-z][a-z0-9_]*)(?:[\'"\s`])/i',
        '/\{\$(?:this->(?:p|prefix)|p|prefix)\}([a-z][a-z0-9_]*)/i',
    ];
    $seenTables=[];
    foreach($tablePatterns as $pattern){
        preg_match_all($pattern,$source,$matches,PREG_OFFSET_CAPTURE);
        foreach($matches[1] as [$name,$offset]){
            $line=substr_count(substr($source,0,$offset),"\n")+1;$signature=$name.':'.$line;
            if(isset($seenTables[$signature]))continue;$seenTables[$signature]=true;
            $tables[$name][]=['path'=>$path,'line'=>$line,'source_sha256'=>$hash,'module'=>$module,'state'=>'requires_query_and_relationship_review'];
            if($module!==null)$modules[$module]['table_candidates'][$name]=true;
        }
    }
    preg_match_all('/\$this->db->\s*(query|from|join|get|get_where|insert|insert_batch|update|update_batch|delete|where|or_where|count_all_results)\s*\(/i',$source,$matches,PREG_OFFSET_CAPTURE);
    foreach($matches[1] as [$call,$offset])$databaseCalls[]=['path'=>$path,'line'=>substr_count(substr($source,0,$offset),"\n")+1,'source_sha256'=>$hash,'module'=>$module,'call'=>strtolower($call),'state'=>'requires_company_predicate_review'];
    for($i=0;$i<count($tokens);$i++){
        $token=$tokens[$i];if(!is_array($token))continue;
        if($isController&&$token[0]===T_EXTENDS){$n=surfaceNext($tokens,$i+1);$parent=surfaceText($tokens[$n]??'');}
        if($isController&&$token[0]===T_FUNCTION){$m=surfaceMethod($tokens,$i);if($m&&$m['visibility']==='public')$methods[]=$m;}
        if($token[0]!==T_STRING)continue;
        $call=strtolower($token[1]);if(!in_array($call,array_merge($optionCalls,$hookCalls),true))continue;
        [$called,$literal,$expression]=surfaceArgument($tokens,$i);if(!$called)continue;
        $reference=['path'=>$path,'line'=>$token[2],'source_sha256'=>$hash,'call'=>$call];
        if(in_array($call,$optionCalls,true)){
            if($literal!==null&&preg_match('/^[A-Za-z0-9_][A-Za-z0-9_.:-]{0,190}$/D',$literal)){
                $options[$literal][]=$reference;
                if($module!==null)$modules[$module]['option_keys'][$literal]=true;
            }else $dynamic[]=$reference+['expression'=>$expression,'state'=>'requires_call_site_review'];
        }elseif($literal!==null)$hooks[]=$reference+['hook'=>$literal,'module'=>$module,'state'=>'not_verified'];
        else $hooks[]=$reference+['hook'=>null,'expression'=>$expression,'module'=>$module,'state'=>'not_verified'];
    }
    if($isController){
        $surface=str_contains($path,'application/controllers/admin/')||$parent==='AdminController'?'admin':'public_or_api_or_job';
        $controllers[]=['path'=>$path,'source_sha256'=>$hash,'module'=>$module,'base_class'=>$parent,'surface'=>$surface,'method_candidates'=>$methods,'http_verbs'=>'requires_dispatch_review','authentication'=>'requires_entry_point_review','company_origin'=>'requires_persisted_origin_review','state'=>'not_verified','operational_ready'=>false];
        if($module!==null)$modules[$module][$surface==='admin'?'admin_controllers':'public_controllers'][]=$path;
    }
    if(preg_match('/cron|queue|worker|webhook|callback/i',$path)||preg_match('/function\s+(?:cron|process_queue|process_jobs|run_cron|cron_job)\b/i',$source)){
        $jobs[]=['path'=>$path,'source_sha256'=>$hash,'module'=>$module,'state'=>'not_verified'];
        if($module!==null)$modules[$module]['job_candidates'][]=$path;
    }
    if(preg_match('/report|export|pdf|download/i',$path)){
        $reports[]=['path'=>$path,'source_sha256'=>$hash,'module'=>$module,'state'=>'not_verified'];
        if($module!==null)$modules[$module]['report_candidates'][]=$path;
    }
}
ksort($options,SORT_STRING);$optionRows=[];
foreach($options as $key=>$references)$optionRows[]=['name'=>$key,'classification'=>in_array($key,$infrastructure,true)?'installation_infrastructure_explicit':'company_private_by_storage_policy','consumer_validation'=>'not_verified','references'=>$references];
foreach($modules as &$module){foreach(['option_keys','table_candidates'] as $key){$module[$key]=array_keys($module[$key]);sort($module[$key],SORT_STRING);}}unset($module);
ksort($tables,SORT_STRING);$tableRows=[];foreach($tables as $name=>$references)$tableRows[]=['name'=>$name,'state'=>'not_verified','references'=>$references];
$manifest=hash('sha256',json_encode($files,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
$result=[
    'format'=>1,'operational_ready'=>false,'rollout'=>'blocked',
    'scope'=>'Shipped module folders and first-party PHP source candidates. No module is loaded. Installed/enabled modules in a deployment are a separate observation. Private runtime configuration, dependency code and translations are excluded.',
    'limitations'=>['Lexical references are candidates, not a runtime call graph or a confirmed bug.','Public controller methods are not a proven route/verb contract.','Storage policy is not proof that each consumer uses the company adapter.','Admin route denial does not make a feature operational.','Job/report candidates need positive and negative proofs through their actual entry points.'],
    'source_manifest_sha256'=>$manifest,
    'counts'=>['modules'=>count($modules),'source_files'=>count($files),'literal_option_keys'=>count($options),'dynamic_option_references'=>count($dynamic),'controllers'=>count($controllers),'hook_candidates'=>count($hooks),'job_file_candidates'=>count($jobs),'report_file_candidates'=>count($reports),'table_name_candidates'=>count($tables),'database_call_candidates'=>count($databaseCalls)],
    'infrastructure_options'=>$infrastructure,'modules'=>array_values($modules),'options'=>$optionRows,'dynamic_options'=>$dynamic,'controllers'=>$controllers,'hooks'=>$hooks,'jobs'=>$jobs,'reports'=>$reports,'tables'=>$tableRows,'database_calls'=>$databaseCalls,
];
if(!$full){
    $result['full_inventory_command']='php scripts/company-surface-inventory.php --full --output /tmp/company-operational-surfaces-full.json';
    $result['options']=array_map(static fn($row)=>['name'=>$row['name'],'classification'=>$row['classification'],'consumer_validation'=>$row['consumer_validation'],'reference_count'=>count($row['references'])],$result['options']);
    $result['tables']=array_map(static fn($row)=>['name'=>$row['name'],'state'=>$row['state'],'reference_count'=>count($row['references'])],$result['tables']);
    unset($result['database_calls'],$result['hooks'],$result['jobs'],$result['reports']);
}
$encoded=json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
if($check){
    if(!is_file($target)||file_get_contents($target)!==$encoded){fwrite(STDERR,"Operational surface inventory is stale. Run php scripts/company-surface-inventory.php.\n");fwrite(STDERR,"Expected operational source_manifest_sha256: ".$manifest."\n");exit(1);}
}else file_put_contents($target,$encoded);
echo json_encode($result['counts'],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
echo $check?"Inventory matches; operational readiness remains blocked.\n":"Inventory written; operational readiness remains blocked.\n";
