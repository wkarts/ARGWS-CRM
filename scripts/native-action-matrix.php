<?php
/** Read-only action/admission reconciliation. No application bootstrap or runtime approval. */
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/application/services/NativeCompanyRoutes.php';
$scope=['Projects','Tasks','Clients','Invoices','Expenses','Staff','Tickets','Contracts','Payments','Settings'];
$census=json_decode(file_get_contents($root.'/docs/companies/native-experience-action-census.json'),true,512,JSON_THROW_ON_ERROR);
$catalog=(new ReflectionClass(NativeCompanyRoutes::class))->getConstant('ACTIONS');
$reviewPath=$root.'/docs/companies/pr52-action-classification.json';
$review=json_decode(file_get_contents($reviewPath),true,512,JSON_THROW_ON_ERROR);$classifications=[];
foreach($review['actions']as$row){$key=strtolower($row['controller'].'::'.$row['method']);if(isset($classifications[$key])||!in_array($row['classification'],['necessary','internal','not_applicable'],true)||trim($row['reason'])===''||$row['runtime_equivalence_proven']!==false)throw new RuntimeException('Invalid action review: '.$key);$classifications[$key]=$row;}
if(count($classifications)!==139)throw new RuntimeException('Original 139-action review incomplete');
$modules=[];$total=0;$admitted=0;$seen=[];
foreach($census['controllers']as$controller){
    if(!in_array($controller['controller'],$scope,true))continue;
    $name=$controller['controller'];$methods=[];$allowed=$catalog[strtolower($name)]??[];
    foreach($controller['methods']as$method){
        $key=strtolower($name.'::'.$method['method']);
        if(isset($seen[$key]))throw new RuntimeException('Duplicate action: '.$key);
        $seen[$key]=true;$verbs=$allowed[$method['method']]??[];
        $methods[]=[
            'method'=>$method['method'],'line'=>$method['line'],
            'secondary_catalog_verbs'=>$verbs,
            'secondary_admission'=>$verbs?'catalog_admitted':'not_admitted',
            'runtime_parity'=>'not_inferred_from_catalog',
        ]+(isset($classifications[$key])?['review'=>$classifications[$key]]:[]);
        $total++;$admitted+=(int)(bool)$verbs;
    }
    foreach($allowed as$method=>$verbs){
        if(!isset($seen[strtolower($name.'::'.$method)]))throw new RuntimeException('Catalog action absent from census: '.$name.'::'.$method);
    }
    $modules[]=['controller'=>$name,'path'=>$controller['path'],'source_sha256'=>hash_file('sha256',$root.'/'.$controller['path']),'methods'=>$methods];
}
if(count($modules)!==count($scope))throw new RuntimeException('Incomplete requested module scope');
if(array_diff_key($classifications,$seen))throw new RuntimeException('Reviewed method absent from current census');
$result=[
    'format'=>1,
    'scope'=>'All public actions of the ten official modules requested for PR52; catalogue admission is not runtime equivalence.',
    'census_controllers'=>count($census['controllers']),
    'census_methods'=>array_sum(array_map(static fn($row)=>count($row['methods']),$census['controllers'])),
    'census_sha256'=>hash_file('sha256',$root.'/docs/companies/native-experience-action-census.json'),
    'route_catalog_sha256'=>hash_file('sha256',$root.'/application/services/NativeCompanyRoutes.php'),
    'context_policy_sha256'=>hash_file('sha256',$root.'/application/services/CompanyRequestScope.php'),
    'primary_policy'=>'CompanyRequestScope retains historical primary paths except its explicit shared/typed routes. No primary/secondary equivalence is inferred.',
    'summary'=>['modules'=>count($modules),'methods'=>$total,'catalog_admitted'=>$admitted,'not_admitted'=>$total-$admitted,'full_parity_approved'=>false],
    'runtime_evidence'=>'docs/companies/pr52-local-consolidation-2026-10-29.md',
    'original_139_review'=>['path'=>'docs/companies/pr52-action-classification.json','sha256'=>hash_file('sha256',$reviewPath),'classification_counts'=>$review['summary'],'local_closure_proven'=>false],
    'modules'=>$modules,
];
$json=json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
$target=$root.'/docs/companies/pr52-official-action-matrix.json';
if(in_array('--check',$argv,true)){
    if(!is_file($target)||file_get_contents($target)!==$json){fwrite(STDERR,"Action matrix is stale. Run php scripts/native-action-matrix.php.\n");exit(1);}
}else file_put_contents($target,$json);
echo $total.' actions; '.$admitted.' admitted by secondary catalogue; '.($total-$admitted)." not admitted; full parity remains unapproved.\n";
