<?php

/** Revision used by HTML links so a committed branding projection is visible
 * without asking the public PWA endpoint to bootstrap the application. */
function native_pwa_revision():string
{
    static $revision;
    if(is_string($revision))return$revision;
    $file=(defined('FCPATH')?FCPATH:dirname(__DIR__,2)).'assets/pwa/runtime/identity.json';
    if(is_file($file)){$doc=json_decode((string)@file_get_contents($file),true);if(is_array($doc)&&is_string($doc['revision']??null)&&preg_match('/^[a-f0-9]{64}$/D',$doc['revision']))return$revision=$doc['revision'];}
    $parts=[];foreach(['assets/images/argws/favicon-16.png','assets/images/argws/favicon-32.png','assets/pwa/icon-192.png','assets/pwa/icon-512.png','assets/pwa/icon-maskable-512.png','assets/pwa/apple-touch-icon-180.png']as$path){$absolute=(defined('FCPATH')?FCPATH:dirname(__DIR__,2)).$path;$parts[]=$path.(is_file($absolute)?':'.hash_file('sha256',$absolute):'');}return$revision=hash('sha256',implode('|',$parts));
}
