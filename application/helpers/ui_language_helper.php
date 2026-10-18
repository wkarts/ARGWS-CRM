<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** PT-BR is the only selectable language; English remains a translation fallback. */
function crm_ui_language()
{
    return 'portuguese_br';
}

function crm_load_ui_language($reset = false)
{
    $CI = &get_instance();
    if ($reset) {
        $CI->lang->is_loaded = [];
        $CI->lang->language = [];
    }

    // Read arrays without loader-cache side effects: autoload may have already
    // loaded PT-BR before English. Fallback must never overwrite translations.
    $fallback = $CI->lang->load('english_lang', 'english', true);
    $language = crm_ui_language();
    $translated = $CI->lang->load($language . '_lang', $language, true);
    $CI->lang->language = array_replace($fallback, $CI->lang->language, $translated);
    load_custom_lang_file($language);
    $CI->lang->set_last_loaded_language($language);
    $GLOBALS['language'] = $language;
    $GLOBALS['locale'] = get_locale_key($language);

    return $language;
}
