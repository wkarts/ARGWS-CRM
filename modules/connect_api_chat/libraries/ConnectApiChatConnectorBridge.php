<?php

defined('BASEPATH') or exit('No direct script access allowed');

class ConnectApiChatConnectorBridge
{
    private $CI;
    private $baseContext;
    private $contextOverride;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->library('connect_api_chat/ConnectApiChatLogger');
    }

    public function available()
    {
        if (function_exists('connect_api_chat_connector_available')) return (bool)connect_api_chat_connector_available();
        if (function_exists('is_module_active')) return (bool)is_module_active('connect_api_connector');
        return defined('CONNECT_API_CONNECTOR_MODULE');
    }

    public function context()
    {
        if ($this->contextOverride !== null) return $this->contextOverride;
        return $this->baseContext();
    }

    public function baseContext()
    {
        if ($this->baseContext !== null) return $this->baseContext;
        if (!$this->available()) {
            return $this->baseContext = [
                'base_url'=>'','instance_name'=>'','instance_token'=>'','instance_number'=>'',
                'verify_tls'=>true,'timeout'=>30,'default_country'=>'55','default_area'=>'','auto_normalize'=>true,'source'=>'connector',
            ];
        }
        $this->CI->load->library('connect_api_connector/ConnectApiConnectorConfig');
        $this->baseContext = $this->CI->connectapiconnectorconfig->all();
        $this->baseContext['source']='connector';
        return $this->baseContext;
    }

    public function useContext(array $context)
    {
        $base=$this->baseContext();
        $this->contextOverride=array_merge($base,$context);
        return $this;
    }

    public function clearContext()
    {
        $this->contextOverride=null;
        return $this;
    }

    public function availableContexts()
    {
        $result=[];
        $base=$this->baseContext();
        if(!empty($base['instance_name'])&&!empty($base['instance_token'])&&!empty($base['base_url'])){
            $base['source']='connector';
            $result[$base['instance_name']]=$base;
        }

        $managerActive=function_exists('is_module_active') ? (bool)is_module_active('connect_api_manager') : defined('CONNECT_API_MANAGER_MODULE');
        if($managerActive){
            try{
                $this->CI->load->model('connect_api_manager/connect_api_manager_model');
                $rows=$this->CI->connect_api_manager_model->get_connector_instances();
                foreach(is_array($rows)?$rows:[] as $row){
                    $name=trim((string)($row['instance_name']??''));
                    $token=(string)($row['instance_token']??'');
                    $url=rtrim((string)($row['api_url']??''),'/');
                    if($name===''||$token===''||$url==='')continue;
                    if(isset($result[$name]))continue;
                    $result[$name]=[
                        'base_url'=>$url,
                        'instance_name'=>$name,
                        'instance_token'=>$token,
                        'instance_number'=>preg_replace('/\D+/','',(string)($row['phone_number']??'')),
                        'verify_tls'=>get_option('connect_api_manager_verify_tls')!=='0',
                        'timeout'=>(int)(get_option('connect_api_manager_timeout')?:30),
                        'default_country'=>$base['default_country']??'55',
                        'default_area'=>$base['default_area']??'',
                        'auto_normalize'=>$base['auto_normalize']??true,
                        'source'=>'manager',
                        'integration'=>$row['integration']??null,
                    ];
                }
            }catch(Throwable $e){
                $this->CI->connectapichatlogger->log('WARNING','connector.instances','Falha ao consultar instâncias do Manage',['error'=>$e->getMessage()]);
            }
        }
        return array_values($result);
    }

    public function contextForInstance($instanceName)
    {
        $instanceName=trim((string)$instanceName);
        foreach($this->availableContexts() as $context){
            if((string)($context['instance_name']??'')===$instanceName)return $context;
        }
        return null;
    }

    public function configured()
    {
        $c=$this->context();
        return $this->available() && !empty($c['base_url']) && !empty($c['instance_name']) && !empty($c['instance_token']);
    }

    public function normalizeNumber($number)
    {
        if ($this->available()) {
            $this->CI->load->library('connect_api_connector/ConnectApiConnectorConfig');
            return $this->CI->connectapiconnectorconfig->normalizeNumber($number);
        }
        return preg_replace('/\D+/', '', (string)$number);
    }

    public function state(){return $this->json('GET','/instance/connectionState/'.rawurlencode($this->instance()));}
    public function info(){return $this->json('GET','/instance/fetchInstances?instanceName='.rawurlencode($this->instance()));}
    public function ownProfile(){return $this->json('POST','/chat/fetchProfile/'.rawurlencode($this->instance()),(object)[]);}

    public function instanceIdentity($force = false)
    {
        $instance=(string)($this->context()['instance_name']??'');
        $cacheKey='connect_api_chat_identity_'.substr(sha1($instance),0,16);
        if(!$force && $instance!==''){
            $cached=json_decode((string)get_option($cacheKey),true);
            if(is_array($cached)&&!empty($cached['_cached_at'])&&(time()-(int)$cached['_cached_at'])<300){
                unset($cached['_cached_at']);
                return $cached;
            }
        }
        $identity=['name'=>'WhatsApp','picture'=>null,'number'=>'','is_business'=>false,'description'=>null];
        try {
            $profile=$this->ownProfile();
            if(!empty($profile['name']))$identity['name']=(string)$profile['name'];
            if(!empty($profile['picture']))$identity['picture']=(string)$profile['picture'];
            if(!empty($profile['wuid']))$identity['number']=preg_replace('/\D+/','',preg_replace('/@.*$/','',(string)$profile['wuid']));
            if(array_key_exists('isBusiness',$profile))$identity['is_business']=(bool)$profile['isBusiness'];
            if(!empty($profile['description']))$identity['description']=(string)$profile['description'];
        } catch(Throwable $e) {
            $this->CI->connectapichatlogger->log('WARNING','connector.identity','Falha ao consultar perfil da instância',['error'=>$e->getMessage()]);
        }
        try {
            $rows=$this->normalizeList($this->info());
            if(!empty($rows[0])&&is_array($rows[0])){
                $row=$rows[0];
                if(($identity['name']==='WhatsApp'||$identity['name']==='')&&!empty($row['profileName']))$identity['name']=(string)$row['profileName'];
                if(empty($identity['picture'])&&!empty($row['profilePicUrl']))$identity['picture']=(string)$row['profilePicUrl'];
                if(empty($identity['number'])&&!empty($row['number']))$identity['number']=preg_replace('/\D+/','',(string)$row['number']);
                if(empty($identity['number'])&&!empty($row['ownerJid']))$identity['number']=preg_replace('/\D+/','',preg_replace('/@.*$/','',(string)$row['ownerJid']));
            }
        } catch(Throwable $e) {}
        if($identity['name']===''||$identity['name']==='WhatsApp')$identity['name']='WhatsApp';
        if($instance!==''){
            $toCache=$identity;$toCache['_cached_at']=time();
            if(get_option($cacheKey)==='')add_option($cacheKey,json_encode($toCache,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
            else update_option($cacheKey,json_encode($toCache,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        }
        return $identity;
    }
    public function findChats(){return $this->json('POST','/chat/findChats/'.rawurlencode($this->instance()),(object)[]);}
    public function findMessages($remoteJid,$page=1,$offset=50){return $this->json('POST','/chat/findMessages/'.rawurlencode($this->instance()),['where'=>['key'=>['remoteJid'=>(string)$remoteJid]],'page'=>max(1,(int)$page),'offset'=>max(1,min(200,(int)$offset))]);}
    public function findMessageById($messageId)
    {
        $messageId=trim((string)$messageId);
        if($messageId==='')return [];
        return $this->json('POST','/chat/findMessages/'.rawurlencode($this->instance()),[
            'where'=>['key'=>['id'=>$messageId]],
            'page'=>1,
            'offset'=>10,
        ]);
    }
    public function findContacts($where=[]){return $this->json('POST','/chat/findContacts/'.rawurlencode($this->instance()),$where?['where'=>$where]:(object)[]);}

    public function sendText($number,$text,$quoted=null)
    {
        $payload=['number'=>(string)$number,'text'=>(string)$text];
        if(is_array($quoted)&&!empty($quoted['key']['id']))$payload['quoted']=$quoted;
        return $this->json('POST','/message/sendText/'.rawurlencode($this->instance()),$payload);
    }

    public function sendMedia($number,$filePath,$fileName,$mime,$caption='',$quoted=null,$ptt=false)
    {
        if(!class_exists('CURLFile'))throw new RuntimeException('A extensão PHP cURL é obrigatória para envio de arquivos.');
        $endpoint=($ptt?'/message/sendWhatsAppAudio/':'/message/sendMedia/').rawurlencode($this->instance());
        $fields=['number'=>(string)$number,'file'=>new CURLFile($filePath,$mime?:'application/octet-stream',$fileName)];
        if($ptt){
            if(is_array($quoted)&&!empty($quoted['key']['id']))$fields['options']=json_encode(['quoted'=>$quoted],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }else{
            $mediaType='document';
            if(strpos($mime,'image/')===0)$mediaType='image';elseif(strpos($mime,'video/')===0)$mediaType='video';elseif(strpos($mime,'audio/')===0)$mediaType='audio';
            $fields['mediatype']=$mediaType;
            $fields['mimetype']=(string)$mime;
            $fields['fileName']=(string)$fileName;
            if($caption!=='')$fields['caption']=(string)$caption;
            if(is_array($quoted)&&!empty($quoted['key']['id']))$fields['options']=json_encode(['quoted'=>$quoted],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }
        return $this->multipart('POST',$endpoint,$fields);
    }

    public function sendContact($number,array $contact,$quoted=null)
    {
        $payload=['number'=>(string)$number,'contact'=>[$contact]];
        if(is_array($quoted)&&!empty($quoted['key']['id']))$payload['quoted']=$quoted;
        return $this->json('POST','/message/sendContact/'.rawurlencode($this->instance()),$payload);
    }

    public function mediaBase64(array $message,$convertToMp4=false)
    {
        return $this->json('POST','/chat/getBase64FromMediaMessage/'.rawurlencode($this->instance()),['message'=>$message,'convertToMp4'=>(bool)$convertToMp4]);
    }

    public function mediaBase64ByKey($messageId,$remoteJid,$fromMe=false,$remoteJidAlt=null,$convertToMp4=false)
    {
        $key=['id'=>(string)$messageId,'remoteJid'=>(string)$remoteJid,'fromMe'=>(bool)$fromMe];
        if($remoteJidAlt)$key['remoteJidAlt']=(string)$remoteJidAlt;
        return $this->mediaBase64(['key'=>$key],$convertToMp4);
    }

    public function downloadPresignedMedia($url)
    {
        $url=trim((string)$url);
        if($url===''||!filter_var($url,FILTER_VALIDATE_URL))throw new RuntimeException('URL de mídia inválida.');
        if(!function_exists('curl_init'))throw new RuntimeException('A extensão PHP cURL é obrigatória.');
        $c=$this->context();$ch=curl_init();$start=microtime(true);
        curl_setopt_array($ch,[CURLOPT_URL=>$url,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>3,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>max(30,(int)$c['timeout']),CURLOPT_SSL_VERIFYPEER=>(bool)$c['verify_tls'],CURLOPT_SSL_VERIFYHOST=>$c['verify_tls']?2:0]);
        $body=curl_exec($ch);$errno=curl_errno($ch);$error=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$ctype=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE);$duration=(int)round((microtime(true)-$start)*1000);curl_close($ch);
        $this->CI->connectapichatlogger->log(($status>=200&&$status<300&&!$errno)?'DEBUG':'WARNING','media.presigned','Download de mídia presignada',['host'=>parse_url($url,PHP_URL_HOST),'curl_error'=>$error],$status,$duration);
        if($errno||$status<200||$status>=300||$body===false||$body==='')throw new RuntimeException('Falha ao recuperar mídia presignada'.($error!==''?': '.$error:' (HTTP '.$status.')'));
        return ['binary'=>$body,'mimetype'=>preg_replace('/\s*;.*$/','',$ctype?:'application/octet-stream')];
    }

    public function deleteMessage($messageId,$remoteJid){return $this->json('DELETE','/chat/deleteMessageForEveryone/'.rawurlencode($this->instance()),['id'=>(string)$messageId,'remoteJid'=>(string)$remoteJid,'fromMe'=>true]);}

    public function setWebhook($url,$secret)
    {
        return $this->json('POST','/webhook/set/'.rawurlencode($this->instance()),['webhook'=>[
            'enabled'=>true,'url'=>(string)$url,'headers'=>['X-Connect-Chat-Secret'=>(string)$secret],'byEvents'=>false,'base64'=>false,
            'events'=>['MESSAGES_UPSERT','MESSAGES_UPDATE','MESSAGES_EDITED','MESSAGES_DELETE','SEND_MESSAGE','SEND_MESSAGE_UPDATE','CONTACTS_SET','CONTACTS_UPSERT','CONTACTS_UPDATE','CHATS_SET','CHATS_UPSERT','CHATS_UPDATE','CHATS_DELETE','CONNECTION_UPDATE'],
        ]]);
    }

    public function getWebhook(){return $this->json('GET','/webhook/find/'.rawurlencode($this->instance()));}

    public function normalizeList($response)
    {
        if(!is_array($response))return[];
        if($this->isList($response))return$response;
        foreach(['data','records','rows','chats','messages','contacts','response'] as $k){if(isset($response[$k])&&is_array($response[$k])){if($this->isList($response[$k]))return$response[$k];foreach(['records','rows','data'] as $nested){if(isset($response[$k][$nested])&&is_array($response[$k][$nested])&&$this->isList($response[$k][$nested]))return$response[$k][$nested];}}}
        return[];
    }

    private function instance()
    {
        $c=$this->context();
        if(!$this->configured())throw new RuntimeException(function_exists('_l')?_l('connect_api_chat_connector_not_configured'):'Conector não configurado.');
        return$c['instance_name'];
    }

    private function json($method,$path,$payload=null)
    {
        $c=$this->context();
        if(!$this->configured())throw new RuntimeException(function_exists('_l')?_l('connect_api_chat_connector_not_configured'):'Conector não configurado.');
        if(!function_exists('curl_init'))throw new RuntimeException('A extensão PHP cURL é obrigatória.');
        $url=rtrim($c['base_url'],'/').$path;
        $ch=curl_init();
        $opts=[CURLOPT_URL=>$url,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>5,CURLOPT_CONNECTTIMEOUT=>min(10,(int)$c['timeout']),CURLOPT_TIMEOUT=>(int)$c['timeout'],CURLOPT_CUSTOMREQUEST=>strtoupper($method),CURLOPT_HTTPHEADER=>['Accept: application/json','Content-Type: application/json','apikey: '.$c['instance_token']],CURLOPT_SSL_VERIFYPEER=>(bool)$c['verify_tls'],CURLOPT_SSL_VERIFYHOST=>$c['verify_tls']?2:0];
        if($payload!==null)$opts[CURLOPT_POSTFIELDS]=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        curl_setopt_array($ch,$opts);
        return $this->execute($ch,$method,$path,$payload);
    }

    private function multipart($method,$path,$fields)
    {
        $c=$this->context();
        if(!$this->configured())throw new RuntimeException(function_exists('_l')?_l('connect_api_chat_connector_not_configured'):'Conector não configurado.');
        if(!function_exists('curl_init'))throw new RuntimeException('A extensão PHP cURL é obrigatória.');
        $url=rtrim($c['base_url'],'/').$path;$ch=curl_init();
        curl_setopt_array($ch,[CURLOPT_URL=>$url,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>min(10,(int)$c['timeout']),CURLOPT_TIMEOUT=>max(60,(int)$c['timeout']),CURLOPT_CUSTOMREQUEST=>strtoupper($method),CURLOPT_POSTFIELDS=>$fields,CURLOPT_HTTPHEADER=>['Accept: application/json','apikey: '.$c['instance_token']],CURLOPT_SSL_VERIFYPEER=>(bool)$c['verify_tls'],CURLOPT_SSL_VERIFYHOST=>$c['verify_tls']?2:0]);
        $meta=$fields;if(isset($meta['file']))$meta['file']='[ARQUIVO]';
        return $this->execute($ch,$method,$path,$meta);
    }

    private function execute($ch,$method,$path,$requestContext=[])
    {
        $start=microtime(true);$body=curl_exec($ch);$errno=curl_errno($ch);$error=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$duration=(int)round((microtime(true)-$start)*1000);curl_close($ch);
        $decoded=json_decode((string)$body,true);$result=json_last_error()===JSON_ERROR_NONE?$decoded:['raw'=>(string)$body];
        if($errno){$this->CI->connectapichatlogger->log('ERROR','connector.http','Falha cURL',['method'=>$method,'path'=>$path,'request'=>$requestContext,'curl_error'=>$error],$status,$duration);throw new RuntimeException('Falha de comunicação com Connect|API: '.$error);}
        $this->CI->connectapichatlogger->log(($status>=200&&$status<300)?'DEBUG':'ERROR','connector.http',strtoupper($method).' '.$path,$this->compactHttpLogContext($path,$requestContext,$result),$status,$duration);
        if($status<200||$status>=300){$message=is_array($result)&&isset($result['message'])?$result['message']:('HTTP '.$status);if(is_array($message))$message=implode('; ',$message);throw new RuntimeException((string)$message);}
        return is_array($result)?$result:[];
    }

    private function compactHttpLogContext($path,$request,$response)
    {
        $context=['request'=>$request];
        if(strpos((string)$path,'/chat/findMessages/')===0){
            $records=$this->normalizeList($response);$types=[];$ids=[];
            foreach(array_slice($records,0,20) as$r){$type=(string)($r['messageType']??'unknown');$types[$type]=($types[$type]??0)+1;$id=(string)($r['key']['id']??$r['id']??'');if($id!==''&&count($ids)<8)$ids[]=$id;}
            $container=is_array($response['messages']??null)?$response['messages']:$response;
            $context['response_summary']=['records'=>count($records),'total'=>(int)($container['total']??count($records)),'types'=>$types,'sample_ids'=>$ids];
            return $context;
        }
        if(strpos((string)$path,'/chat/findChats/')===0){
            $rows=$this->normalizeList($response);$sample=[];
            foreach(array_slice($rows,0,10) as$r)$sample[]=['remoteJid'=>$r['remoteJid']??null,'updatedAt'=>$r['updatedAt']??null,'messageType'=>$r['lastMessage']['messageType']??null];
            $context['response_summary']=['records'=>count($rows),'sample'=>$sample];
            return $context;
        }
        if(strpos((string)$path,'/instance/fetchInstances')===0){
            $rows=$this->normalizeList($response);$sample=[];
            foreach(array_slice($rows,0,5) as$r)$sample[]=['name'=>$r['name']??null,'connectionStatus'=>$r['connectionStatus']??null,'ownerJid'=>$r['ownerJid']??null,'profileName'=>$r['profileName']??null,'counts'=>$r['_count']??null];
            $context['response_summary']=$sample;
            return $context;
        }
        $encoded=json_encode($response,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if($encoded!==false && strlen($encoded)>12000){
            $context['response_summary']=['keys'=>is_array($response)?array_slice(array_keys($response),0,20):[],'bytes'=>strlen($encoded)];
        }else{$context['response']=$response;}
        return $context;
    }

    private function isList($arr){$i=0;foreach(array_keys($arr)as$k){if($k!==$i++)return false;}return true;}
}
