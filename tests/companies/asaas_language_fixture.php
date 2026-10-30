<?php
/** Uses the shipped catalogs; no translation-key placeholders in view evidence. */
function asaasFixtureLanguage(string $locale='portuguese_br'):array
{
    if(!in_array($locale,['portuguese_br','english'],true))throw new InvalidArgumentException('unsupported_fixture_language');
    $lang=[];require dirname(__DIR__,2).'/application/language/'.$locale.'/'.$locale.'_lang.php';
    return $GLOBALS['asaasFixtureLanguage']=$lang;
}
if(!function_exists('_l')){function _l($key,$parameters='',$logErrors=true){$line=$GLOBALS['asaasFixtureLanguage'][$key]??$key;return is_array($parameters)?vsprintf($line,$parameters):sprintf($line,$parameters);}}
asaasFixtureLanguage();
