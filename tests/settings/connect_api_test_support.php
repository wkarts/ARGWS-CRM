<?php
/** Reconstructed synthetic provider fixtures; production client/parser are unchanged. */
function connectTestIdentity(string$instance):array
{
    return[['id'=>'00000000-0000-4000-8000-'.substr(hash('sha256',$instance),0,12),'name'=>$instance,'connectionStatus'=>'open','ownerJid'=>'5511999990000@s.whatsapp.net']];
}
function connectTestGet(string$url):array
{
    if(str_contains($url,'/instance/fetchInstances?')){parse_str(parse_url($url,PHP_URL_QUERY),$q);return connectTestIdentity($q['instanceName']??'');}
    return['instance'=>['instanceName'=>rawurldecode(basename(parse_url($url,PHP_URL_PATH))),'state'=>'open']];
}
