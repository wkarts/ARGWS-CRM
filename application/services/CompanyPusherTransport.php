<?php

declare(strict_types=1);
require_once dirname(__DIR__).'/vendor/autoload.php';

/** The installed pinned SDK signs both auth and events. No arbitrary host or logger is accepted. */
final class CompanyPusherTransport
{
    private GuzzleHttp\ClientInterface $http;
    public function __construct(?GuzzleHttp\ClientInterface $http=null)
    {
        $this->http=$http??new GuzzleHttp\Client(['verify'=>true,'allow_redirects'=>false,'timeout'=>10,'connect_timeout'=>5]);
    }
    private function sdk(array $profile,string $secret):Pusher\Pusher
    {
        return new Pusher\Pusher($profile['app_key'],$secret,$profile['app_id'],['cluster'=>$profile['cluster'],'useTLS'=>true,'timeout'=>10],$this->http);
    }
    public function authorize(array $profile,string $secret,string $channel,string $socket):array
    {
        try{return json_decode($this->sdk($profile,$secret)->authorizeChannel($channel,$socket),true,8,JSON_THROW_ON_ERROR);}
        catch(Throwable $e){throw new RuntimeException('company_pusher_unavailable');}
    }
    public function pulse(array $profile,string $secret,string $channel):void
    {
        try{$result=$this->sdk($profile,$secret)->trigger($channel,'company-task-pulse',(object)[]);if(get_object_vars($result)!==[])throw new RuntimeException();}
        catch(Throwable $e){throw new RuntimeException('company_pusher_unavailable');}
    }
}
