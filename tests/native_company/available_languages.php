<?php
define('BASEPATH',__DIR__);define('APPPATH',dirname(__DIR__,2).'/application/');require APPPATH.'services/NativeAvailableLanguages.php';require APPPATH.'libraries/App.php';$app=(new ReflectionClass(App::class))->newInstanceWithoutConstructor();
if(NativeAvailableLanguages::all()!==['portuguese_br']||$app->get_available_languages()!==NativeAvailableLanguages::all()||$app->get_all_languages()!==NativeAvailableLanguages::all())throw new RuntimeException('Native language catalogue changed');echo "Native language list: three exact legacy/shared comparisons PASS; no bootstrap.\n";
