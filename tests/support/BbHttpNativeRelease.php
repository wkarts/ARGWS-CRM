<?php
/** Read-only shipped module release contract for disposable HTTP tests. */
function bbHttpNativeRelease(): array
{
    $root=dirname(__DIR__,2);$module=$root.'/modules/banco_brasil/';
    require_once $module.'libraries/BancoBrasil/ModuleRelease.php';
    $header=file_get_contents($module.'banco_brasil.php');
    if(preg_match_all('/^Version:\s*([1-9]\.[0-9]\.[0-9])\s*$/m',$header,$v)!==1||preg_match_all('/^Requires at least:\s*([1-9]\.[0-9]\.[0-9])\s*$/m',$header,$core)!==1)throw new RuntimeException('trusted_bb_release_header_required');
    $version=\BancoBrasil\ModuleRelease::VERSION;$level=\BancoBrasil\ModuleRelease::MIGRATION;
    if($v[1][0]!==$version||$core[1][0]!==\BancoBrasil\ModuleRelease::MINIMUM_CORE||!is_int($level)||$level<120||str_replace('.','',$version)!==(string)$level||version_compare(trim(file_get_contents($root.'/VERSION')),$core[1][0],'<'))throw new RuntimeException('bb_shipped_release_metadata_mismatch');
    $levels=[];foreach(glob($module.'migrations/*_version_*.php')as$path){if(!preg_match('/^(\d{3})_version_\1\.php$/',basename($path),$m))throw new RuntimeException('bb_native_migration_name_invalid');$n=(int)$m[1];if(!preg_match('/class\s+Migration_Version_'.$n.'\s+extends\s+App_module_migration\b/',file_get_contents($path)))throw new RuntimeException('bb_native_migration_class_invalid');$levels[]=$n;}
    if(!$levels||max($levels)!==$level)throw new RuntimeException('bb_release_latest_native_mismatch');
    foreach(range(101,$level)as$n)if(!in_array($n,$levels,true))throw new RuntimeException('bb_native_migration_gap');
    return ['version'=>$version,'migration'=>$level,'minimum_core'=>$core[1][0],'levels'=>$levels];
}
