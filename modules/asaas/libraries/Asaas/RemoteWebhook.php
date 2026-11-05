<?php

declare(strict_types=1);
namespace Asaas;

/** Explicit, account-bound API v3 operations. Never selects the first unrelated webhook. */
final class RemoteWebhook
{
    public const EVENTS = ['PAYMENT_CREATED','PAYMENT_UPDATED','PAYMENT_CONFIRMED','PAYMENT_RECEIVED',
        'PAYMENT_OVERDUE','PAYMENT_DELETED','PAYMENT_RESTORED','PAYMENT_REFUNDED',
        'PAYMENT_PARTIALLY_REFUNDED','PAYMENT_CHARGEBACK_REQUESTED','PAYMENT_CHARGEBACK_DISPUTE',
        'PAYMENT_AWAITING_CHARGEBACK_REVERSAL','PAYMENT_RECEIVED_IN_CASH_UNDONE'];

    public static function validate(string $action,array $input):array
    {
        if (!in_array($action,['inspect','create','update','delete','resume'],true)) throw new \DomainException('asaas_webhook_invalid_operation');
        if (in_array($action,['inspect','delete','resume'],true)) return [];
        $name=trim(is_string($input['name']??null)?$input['name']:'');
        $email=is_string($input['email']??null)?trim($input['email']):'';
        $events=$input['events']??null;
        if ($name===''||strlen($name)>100||preg_match('/[\x00-\x1f\x7f]/',$name)
            ||strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL)
            ||!is_array($events)||!array_is_list($events)||!$events||count($events)>count(self::EVENTS)
            ||array_filter($events,static fn($v)=>!is_string($v)||!in_array($v,self::EVENTS,true))
            ||!in_array($input['enabled']??null,['0','1'],true)) throw new \DomainException('asaas_webhook_invalid_configuration');
        return ['name'=>$name,'email'=>$email,'events'=>array_values(array_unique($events)),
            'enabled'=>$input['enabled']==='1','sendType'=>'SEQUENTIALLY','apiVersion'=>3];
    }

    public static function message(\Throwable $error):string
    {
        return match($error->getMessage()) {
            'asaas_webhook_already_exists'=>'Já existe um webhook para esta URL nesta conta. Consulte e use Atualizar.',
            'asaas_webhook_not_found'=>'Nenhum webhook desta URL foi encontrado nesta conta. Use Criar se necessário.',
            'asaas_webhook_duplicate_destination'=>'Há mais de um webhook para esta URL. Revise as duplicidades no Asaas antes de alterar.',
            'asaas_webhook_invalid_configuration'=>'Confira nome, e-mail, eventos, URL HTTPS e token local recuperável.',
            'asaas_webhook_confirmation_required'=>'Confirme a operação nesta conta e ambiente.',
            default=>'Operação remota não confirmada. Consulte o webhook antes de tentar novamente; o recebimento de pagamentos não foi alterado.',
        };
    }
    public function __construct(private $request,private string $url,private string $token)
    {
        $parts=parse_url($url);
        if(!is_array($parts)||!filter_var($url,FILTER_VALIDATE_URL)||($parts['scheme']??'')!=='https'
            ||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])
            ||preg_match('/[\x00-\x20\x7f\\\\]/',$url)
            ||strlen($token)<32||strlen($token)>255||preg_match('/[\x00-\x20\x7f]/',$token)) throw new \DomainException('asaas_webhook_invalid_configuration');
    }
    private function call(string $method,string $path,?array $payload=null):array
    {
        $result=($this->request)($method,$path,$payload);
        if(!is_array($result)||isset($result['errors'])||isset($result['error']))throw new \RuntimeException('asaas_webhook_remote_unconfirmed');
        return $result;
    }
    private function owned():?array
    {
        $found=[];
        for($offset=0;$offset<1000;$offset+=100){
            $page=$this->call('GET','webhooks?limit=100&offset='.$offset);
            if(!isset($page['data'])||!is_array($page['data'])||!array_is_list($page['data'])||!is_bool($page['hasMore']??null))throw new \RuntimeException('asaas_webhook_remote_unconfirmed');
            foreach($page['data'] as $row){
                if(!is_array($row))throw new \RuntimeException('asaas_webhook_remote_unconfirmed');
                if(($row['url']??null)===$this->url){
                    if(!is_string($row['id']??null)||!preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$row['id']))throw new \RuntimeException('asaas_webhook_remote_unconfirmed');
                    $found[$row['id']]=$row;
                }
            }
            if(!$page['hasMore']){
                if(count($found)>1)throw new \DomainException('asaas_webhook_duplicate_destination');
                return $found?array_values($found)[0]:null;
            }
            if(!$page['data'])throw new \RuntimeException('asaas_webhook_remote_unconfirmed');
        }
        throw new \RuntimeException('asaas_webhook_pagination_limit');
    }
    private static function publicRow(?array $row):?array
    {
        if($row===null)return null;
        $safe=[];
        foreach(['id','name','url','email','enabled','interrupted','sendType','apiVersion'] as $key){
            if(isset($row[$key])&&is_scalar($row[$key]))$safe[$key]=is_string($row[$key])?substr($row[$key],0,2048):$row[$key];
        }
        $safe['events']=array_values(array_filter($row['events']??[],static fn($e)=>is_string($e)&&preg_match('/^[A-Z_]{1,80}$/D',$e)));
        return $safe;
    }
    public function execute(string $action,array $input=[]):array
    {
        $payload=self::validate($action,$input);
        $existing=$this->owned();
        if($action==='inspect')return ['operation'=>'inspect','webhook'=>self::publicRow($existing)];
        if($action==='create'&&$existing!==null)throw new \DomainException('asaas_webhook_already_exists');
        if($action!=='create'&&$existing===null)throw new \DomainException('asaas_webhook_not_found');
        if($action==='delete'){
            $result=$this->call('DELETE','webhooks/'.$existing['id']);
            if(($result['deleted']??null)!==true)throw new \RuntimeException('asaas_webhook_remote_unconfirmed');
            return ['operation'=>'delete','webhook'=>null,'remote_id'=>$existing['id']];
        }
        if($action==='resume')$payload=['interrupted'=>false];
        else {
            $payload['url']=$this->url;$payload['authToken']=$this->token;
            // Updating credentials must not silently resume a deliberately interrupted queue.
            if($action==='create')$payload['interrupted']=false;
        }
        $result=$this->call($action==='create'?'POST':'PUT',$action==='create'?'webhooks':'webhooks/'.$existing['id'],$payload);
        if(!is_string($result['id']??null)||!preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$result['id'])
            ||($result['url']??null)!==$this->url||($existing!==null&&$result['id']!==$existing['id']))throw new \RuntimeException('asaas_webhook_remote_unconfirmed');
        return ['operation'=>$action,'webhook'=>self::publicRow($result)];
    }
}
