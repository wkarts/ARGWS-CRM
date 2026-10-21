<?php

declare(strict_types=1);
defined('BASEPATH') or exit('No direct script access allowed');
require_once APPPATH.'helpers/ui_language_helper.php';

/** Catalog-only translation for isolated company views. Never hydrates legacy hooks/models. */
final class CompanyUiTranslation
{
    public static function translator(?string $language=null):Closure
    {
        $language=$language??crm_ui_language();
        if(!in_array($language,['portuguese_br','english'],true))throw new InvalidArgumentException('company_ui_language_invalid');
        $load=static function(string $locale):array{
            $lang=[];require APPPATH.'language/'.$locale.'/'.$locale.'_lang.php';return $lang;
        };
        $catalog=array_replace($load('english'),$language==='english'?[]:$load($language));
        return static function(string $key,$parameters='')use($catalog):string{
            $line=$catalog[$key]??$key;
            try{return is_array($parameters)?vsprintf($line,$parameters):sprintf($line,$parameters);}
            catch(ValueError|ArgumentCountError $error){return $line;}
        };
    }
}
