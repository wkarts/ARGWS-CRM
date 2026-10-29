<?php
/** Pure projection of the exact original settings registry, without bootstrap. */
function _l($key){return$key;}
require dirname(__DIR__).'/application/services/NativeSettingsSections.php';
$expected=json_decode(file_get_contents(__DIR__.'/fixtures/native-settings-sections.json'),true,512,JSON_THROW_ON_ERROR);
$actual=NativeSettingsSections::collect();if($actual!==$expected){fwrite(STDERR,json_encode(['actual'=>$actual,'expected'=>$expected],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);throw new RuntimeException('Native settings section/view/label/order changed');}
$views=[];foreach($actual as$section)foreach($section['children']as$group){if(isset($views[$group['id']]))throw new RuntimeException('Duplicate native group');$views[$group['id']]=$group['view'];if(!is_file(dirname(__DIR__).'/application/views/'.$group['view'].'.php'))throw new RuntimeException('Native group view missing');}
$source=file_get_contents(dirname(__DIR__).'/application/helpers/settings_helper.php');if(!str_contains($source,'NativeSettingsSections::register($CI->app)'))throw new RuntimeException('Principal registry stopped sharing source');
echo 'Native settings: '.count($views).' original groups share identical registry and existing view paths.'.PHP_EOL;
