<?php
define("BASEPATH", __DIR__."/../system/");
require_once __DIR__.'/../application/services/NativeCompanyWorkStatusCatalog.php';
require_once __DIR__.'/../application/services/utilities/Js.php';
require_once __DIR__.'/../application/services/utilities/StrClickable.php';
require_once __DIR__.'/../application/services/utilities/Str.php';
function staff_cant(){if(!empty($GLOBALS['legacyStatsTest']))return false;throw new RuntimeException('Global authorization queried');}
function total_rows(){if(!empty($GLOBALS['legacyStatsTest']))return 3;throw new RuntimeException('Global rows queried');}
function db_prefix(){if(!empty($GLOBALS['legacyStatsTest']))return 'tbl';throw new RuntimeException('Global prefix queried');}
function e($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
$table=new class{public function findRule($id){if($id!=='status')throw new RuntimeException('Unexpected rule');return new class implements JsonSerializable{private array$value=[];public function setValue(array$v){$this->value=$v;return$this;}public function jsonSerialize():array{return['id'=>'status','value'=>$this->value];}};}};
$statuses=NativeCompanyWorkStatusCatalog::owned('project',static fn($key)=>'<'.$key.'>');
$native_project_summary=['statuses'=>$statuses,'status_counts'=>array_combine(array_column($statuses,'id'),[2,3,1,0,4,1])];
ob_start();require __DIR__.'/../application/views/admin/projects/stats.php';$html=ob_get_clean();
foreach($statuses as$status)if(!str_contains($html,'&lt;'.substr($status['name'],1,-1).'&gt;')||!str_contains($html,$status['id']))throw new RuntimeException('Native labels or canonical filter IDs lost');
if(substr_count($html,'@click.prevent=')!==6||!str_contains($html,'_filters _hidden_inputs'))throw new RuntimeException('Native filter DOM changed');
$native_project_summary['status_counts'][$statuses[0]['id']]=-1;
ob_start();try{require __DIR__.'/../application/views/admin/projects/stats.php';throw new RuntimeException('Invalid summary accepted');}catch(DomainException$e){if($e->getMessage()!=='native_project_summary_invalid')throw$e;}finally{ob_end_clean();}
echo "Native project stats real partial: same DOM, canonical filters, escaped labels, no global reads and invalid DTO rejected.\n";

// The principal legacy branch must keep the exact existing partial output.
$GLOBALS['legacyStatsTest']=true;unset($native_project_summary);
$statuses=WorkStatusPresentation::projects(static fn($key)=>$key);
ob_start();require __DIR__.'/fixtures/native-project-stats-original.php';$before=ob_get_clean();
ob_start();require __DIR__.'/../application/views/admin/projects/stats.php';$after=ob_get_clean();
if($before!==$after)throw new RuntimeException('Legacy project stats markup changed');
echo "Legacy project stats output remains byte-identical to abbf6c7 native partial.\n";
