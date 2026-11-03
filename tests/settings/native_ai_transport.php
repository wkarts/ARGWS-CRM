<?php
declare(strict_types=1);
/** Actual cURL/SDK over loopback; no provider DNS, request or credentials. */
if (($argv[1]??'')==='--serve') {
    $server=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
    if(!$server)throw new RuntimeException($error);
    echo stream_socket_get_name($server,false).PHP_EOL;flush();
    while($socket=@stream_socket_accept($server,15)){
        stream_set_timeout($socket,5);$line=fgets($socket);$path=explode(' ',$line?:'')[1]??'';$headers=[];
        while(($line=fgets($socket))!==false&&trim($line)!==''){$pair=explode(':',$line,2);$headers[strtolower($pair[0])]=trim($pair[1]??'');}
        $left=(int)($headers['content-length']??0);while($left>0){$data=fread($socket,min($left,8192));if($data===''||$data===false)break;$left-=strlen($data);}
        if(isset($headers['authorization']))exit(3);
        $text=in_array($path,['/gzip-large','/chunked-large'],true)?str_repeat('X',100000):($headers['accept-encoding']??'absent');
        $payload=['id'=>'chatcmpl-local','object'=>'chat.completion','created'=>1700000000,'model'=>'gpt-4o-mini','choices'=>[['index'=>0,'message'=>['role'=>'assistant','content'=>$text],'finish_reason'=>'stop']],'usage'=>['prompt_tokens'=>10,'completion_tokens'=>5,'total_tokens'=>15]];
        $body=json_encode($payload,JSON_THROW_ON_ERROR);$encoding=match($path){'/gzip','/gzip-large'=>'gzip','/br'=>'br','/stacked'=>'identity, gzip','/identity'=>'identity',default=>''};
        if(str_contains($encoding,'gzip'))$body=gzencode($body);
        $head="HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nConnection: close\r\n";
        if($encoding!=='')$head.='Content-Encoding: '.$encoding."\r\n";
        if($path==='/chunked-large')$head.="Transfer-Encoding: chunked\r\n";
        elseif($path!=='/gzip-large')$head.='Content-Length: '.($path==='/declared-large'?'100000':strlen($body))."\r\n";
        @fwrite($socket,$head."\r\n");
        if($path==='/chunked-large'){foreach(str_split($body,8192)as$chunk)@fwrite($socket,dechex(strlen($chunk))."\r\n".$chunk."\r\n");@fwrite($socket,"0\r\n\r\n");}
        else @fwrite($socket,$body);
        fclose($socket);
    }
    fclose($server);exit;
}
require dirname(__DIR__,2).'/application/vendor/autoload.php';
require dirname(__DIR__,2).'/application/services/CompanyAiClient.php';
$process=proc_open([PHP_BINARY,__FILE__,'--serve'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
if(!is_resource($process))throw new RuntimeException('local HTTP fixture unavailable');
$checks=0;$evidence=[];
try{
    stream_set_timeout($pipes[1],5);$address=trim(fgets($pipes[1])?:'');if(!preg_match('/^127\.0\.0\.1:[0-9]+$/D',$address))throw new RuntimeException('local HTTP fixture did not start');
    foreach(['/plain'=>null,'/identity'=>null,'/gzip'=>'company_ai_transport_unconfirmed','/gzip-large'=>'company_ai_transport_unconfirmed','/br'=>'company_ai_transport_unconfirmed','/stacked'=>'company_ai_transport_unconfirmed','/declared-large'=>'company_ai_transport_unconfirmed','/chunked-large'=>'company_ai_response_limit']as$path=>$expected){
        $wireMax=0;$options=CompanyAiClient::transportOptions();$progress=$options['progress'];$options['progress']=static function($total,$now,...$rest)use($progress,&$wireMax){$wireMax=max($wireMax,$now);return$progress($total,$now,...$rest);};
        $curl=new GuzzleHttp\Handler\CurlHandler();$handler=static function($request,$options)use($curl,$address,$path){if((string)$request->getUri()!=='https://api.openai.com/v1/chat/completions')throw new LogicException('unexpected provider endpoint');return$curl($request->withUri(new GuzzleHttp\Psr7\Uri('http://'.$address.$path))->withoutHeader('Authorization'),$options);};
        $options['handler']=GuzzleHttp\HandlerStack::create($handler);$options['proxy']='';$client=new CompanyAiClient(null,new GuzzleHttp\Client($options));$error=null;$answer=null;
        try{$answer=$client->complete('gpt-4o-mini','synthetic-loopback-key',['message'=>'Synthetic local source'],'summary');}catch(Throwable$failure){for($e=$failure;$e;$e=$e->getPrevious())if(str_contains($e->getMessage(),'company_ai_')){$error=$e->getMessage();break;}$error??=get_class($failure).': '.$failure->getMessage();}
        if($expected===null?($error!==null||$answer!=='identity'):!str_contains($error??'',$expected))throw new RuntimeException('unexpected local transport outcome '.$path.': '.($error??(string)$answer));
        $checks++;$evidence[$path]=['guard'=>$error===null?'accepted_identity':$expected,'wire_download_max'=>$wireMax];
    }
    $options=CompanyAiClient::transportOptions();if($options['decode_content']!==false||$options['headers']['Accept-Encoding']!=='identity')throw new RuntimeException('identity-only contract lost');$checks++;
    echo 'Company AI real cURL identity/compression/preparse/chunked bounds: '.$checks.' checks. Loopback only.'.PHP_EOL;
    echo json_encode($evidence,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL;
}finally{proc_terminate($process);foreach($pipes as$pipe)fclose($pipe);proc_close($process);}
