<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Webhook extends CI_Controller
{
    private $bootstrapped = false;

    public function __construct()
    {
        parent::__construct();
    }

    public function receive()
    {
        $raw='';$instance='';$event='';$registered=false;$queueId=0;
        try {
            $method = strtoupper((string)$this->input->method(true));
            if ($method === 'GET') {
                $this->respond(['status'=>'ok','module'=>'Chat','version'=>defined('CONNECT_API_CHAT_VERSION')?CONNECT_API_CHAT_VERSION:'1.1.2','webhook'=>'ready','method'=>'POST'],200);return;
            }
            if ($method === 'OPTIONS') {$this->respond(['status'=>'ok'],204);return;}
            if ($method !== 'POST') {$this->respond(['status'=>'error','message'=>'Método não permitido'],405);return;}

            $expected=(string)get_option('connect_api_chat_webhook_secret');
            $provided=(string)$this->input->get_request_header('X-Connect-Chat-Secret',true);
            if($expected===''||$provided===''||!hash_equals($expected,$provided)){
                $this->logSafe('WARNING','webhook.auth','Webhook não autorizado',['has_expected'=>$expected!=='','has_header'=>$provided!=='']);
                $this->respond(['status'=>'error','message'=>'Webhook não autorizado'],401);return;
            }

            $this->bootstrapChat();
            $raw=(string)file_get_contents('php://input');
            $payload=json_decode($raw,true);
            if(!is_array($payload)){$this->connectapichatlogger->log('WARNING','webhook.receive','JSON inválido',['raw'=>mb_substr($raw,0,1000)]);$this->respond(['status'=>'error','message'=>'JSON inválido'],400);return;}

            $instance=trim((string)($payload['instance']??''));
            $event=$this->connectapichatnormalizer->eventName($payload['event']??'');
            $data=$payload['data']??null;
            $dataPreview=$data;
            if(is_array($dataPreview)&&isset($dataPreview['key']))$dataPreview=['key'=>$dataPreview['key'],'messageType'=>$dataPreview['messageType']??null,'pushName'=>$dataPreview['pushName']??null,'status'=>$dataPreview['status']??null];
            elseif(is_array($dataPreview)&&isset($dataPreview['keyId']))$dataPreview=['keyId'=>$dataPreview['keyId'],'messageId'=>$dataPreview['messageId']??null,'remoteJid'=>$dataPreview['remoteJid']??null,'fromMe'=>$dataPreview['fromMe']??null,'status'=>$dataPreview['status']??null];
            elseif(is_array($dataPreview))$dataPreview=['records'=>count($dataPreview)];
            $this->connectapichatlogger->log('DEBUG','webhook.receive','Evento recebido',['event'=>$payload['event']??null,'instance'=>$instance,'data'=>$dataPreview]);

            if($instance===''){$this->respond(['status'=>'ignored','message'=>'Instância não informada'],202);return;}
            $ctx=$this->connectapichatconnectorbridge->contextForInstance($instance);
            if(!$ctx){$this->connectapichatlogger->log('WARNING','webhook.instance','Instância não reconhecida',['instance'=>$instance]);$this->respond(['status'=>'ignored','message'=>'Instância não reconhecida pelo Chat'],202);return;}
            $this->connectapichatconnectorbridge->useContext($ctx);

            // 1.1.2: persistência durável ANTES de qualquer regra de negócio.
            // Se o processamento abaixo falhar, a PWA/CRM consome a fila local em ~1s.
            // Nenhuma mensagem inbound depende de uma segunda consulta ao Connect|API.
            if(in_array($event,['MESSAGES_UPSERT','MESSAGES_SET'],true)){
                $queueId=$this->connect_api_chat_model->enqueueInboundPayload($instance,$event,$raw);
            }

            // Importante: o registro é auditoria/idempotência de notificação, NÃO um bloqueio de
            // processamento. saveMessage/update são idempotentes por api_message_id. Assim um
            // retry corrige uma tentativa anterior parcialmente processada em vez de ser descartado.
            $registered=$this->connect_api_chat_model->registerEvent($instance,$event,$raw);
            if(!$registered)$this->connectapichatlogger->log('DEBUG','webhook.retry','Evento repetido reprocessado para garantir consistência',['event'=>$event,'instance'=>$instance]);

            if(in_array($event,['MESSAGES_UPSERT','MESSAGES_SET','SEND_MESSAGE'],true)){
                $messageOk=0;$messageFailed=0;
                foreach($this->messageRecords($data) as $record){
                    try{
                        $n=$this->connectapichatnormalizer->normalizeMessage($record);
                        if(!$n){
                            $messageFailed++;
                            $this->connectapichatlogger->log('WARNING','webhook.normalize','Mensagem ignorada: payload não reconhecido',['event'=>$event,'keys'=>is_array($record)?array_slice(array_keys($record),0,20):[]]);
                            continue;
                        }

                        // saveMessage agora garante a persistência ANTES das regras de ticket.
                        // Se a criação do atendimento falhar, a mensagem continua armazenada.
                        $id=$this->connect_api_chat_model->saveMessage($instance,$n,null,true);
                        if(!$id){
                            $messageFailed++;
                            $this->connectapichatlogger->log('WARNING','webhook.message','Mensagem não persistida',[
                                'api_message_id'=>$n['api_message_id']??null,'from_me'=>!empty($n['from_me']),
                                'remote_jid'=>$n['remote_jid']??null,'remote_jid_alt'=>$n['remote_jid_alt']??null,'type'=>$n['message_type']??null,
                            ]);
                            continue;
                        }
                        $messageOk++;
                        $this->connectapichatlogger->log('DEBUG','webhook.message','Mensagem persistida',[
                            'api_message_id'=>$n['api_message_id']??null,'local_message_id'=>(int)$id,'from_me'=>!empty($n['from_me']),
                            'remote_jid'=>$n['remote_jid']??null,'remote_jid_alt'=>$n['remote_jid_alt']??null,'type'=>$n['message_type']??null,
                        ]);

                        if(empty($n['from_me'])){
                            // Heartbeat operacional independente do modo debug.
                            update_option('connect_api_chat_last_inbound_at',date('Y-m-d H:i:s'));
                            update_option('connect_api_chat_last_inbound_message_id',(string)($n['api_message_id']??''));

                            // Reconciliador local é idempotente e não consulta a API.
                            $this->connect_api_chat_model->repairLiveInboundTickets($instance,5);

                            $c=$this->connect_api_chat_model->conversationForMessage($instance,$id);
                            $ticket=$c?$this->connect_api_chat_model->currentTicket($instance,(int)$c['id']):null;
                            $this->connectapichatlogger->log('INFO','ticket.inbound','Mensagem inbound vinculada ao atendimento',[
                                'api_message_id'=>$n['api_message_id']??null,'message_id'=>(int)$id,'conversation_id'=>(int)($c['id']??0),
                                'ticket_id'=>(int)($ticket['id']??0),'ticket_number'=>(int)($ticket['ticket_number']??0),'ticket_status'=>$ticket['status']??null,
                                'new_ticket'=>(int)($ticket['is_new']??0),'event_first_delivery'=>$registered?1:0,
                            ]);
                            if($registered)$this->notifyIncoming($instance,$n,$id);
                        }
                    }catch(Throwable$recordError){
                        $messageFailed++;
                        $this->connectapichatlogger->log('ERROR','webhook.message.exception',$recordError->getMessage(),[
                            'event'=>$event,'instance'=>$instance,'trace'=>$recordError->getTraceAsString()
                        ]);
                    }
                }
                if($messageOk===0 && $messageFailed>0){
                    throw new RuntimeException('Nenhuma mensagem do webhook pôde ser persistida.');
                }
                if($queueId>0)$this->connect_api_chat_model->markInboundPayloadProcessed($queueId);
            }elseif(in_array($event,['MESSAGES_UPDATE','SEND_MESSAGE_UPDATE','MESSAGES_EDITED'],true)){
                foreach($this->messageRecords($data) as $r){
                    $key=$r['key']??[];if(is_string($key))$key=json_decode($key,true)?:[];
                    $id=$key['id']??$r['keyId']??$r['messageId']??null;
                    $status=$r['status']??($r['update']['status']??null);
                    if($id&&$status)$this->connect_api_chat_model->updateMessageStatus($instance,$id,$status);
                    // Alguns MESSAGES_EDITED chegam com a mensagem inteira: reaproveite o normalizador.
                    if($event==='MESSAGES_EDITED'&&isset($r['message'])){$n=$this->connectapichatnormalizer->normalizeMessage($r);if($n)$this->connect_api_chat_model->saveMessage($instance,$n,null,true);}
                }
            }elseif($event==='MESSAGES_DELETE'){
                foreach($this->messageRecords($data) as$r){$id=$r['id']??($r['key']['id']??$r['keyId']??null);if($id)$this->connect_api_chat_model->markMessageDeleted($instance,$id);}
            }elseif(in_array($event,['CONTACTS_SET','CONTACTS_UPSERT','CONTACTS_UPDATE'],true)){
                foreach($this->messageRecords($data) as$r){$n=$this->connectapichatnormalizer->normalizeContact($r);if($n)$this->connect_api_chat_model->saveContact($instance,$n);}
            }elseif(in_array($event,['CHATS_SET','CHATS_UPSERT','CHATS_UPDATE'],true)){
                foreach($this->messageRecords($data) as$r){
                    $n=$this->connectapichatnormalizer->normalizeChat($r);if(!$n)continue;
                    $chatJid=trim((string)($n['remote_jid']??''));$chatAlt=trim((string)($n['remote_jid_alt']??''));
                    if(strpos($chatJid,'@lid')!==false&&$chatAlt===''&&!$this->connect_api_chat_model->conversationByRemoteJid($instance,$chatJid))continue;
                    $cid=$this->connect_api_chat_model->saveContact($instance,$n);
                    $this->connect_api_chat_model->upsertConversation($instance,$n['remote_jid'],['remote_jid_alt'=>$n['remote_jid_alt']??null,'contact_id'=>$cid,'title'=>$n['display_name'],'is_group'=>$n['is_group']??false]);
                    if(!empty($n['last_message'])){
                        // CHATS_* é metadado/snapshot de conversa, não uma entrega autoritativa de
                        // mensagem nova. Persistir como backfill evita abrir ticket novo por replay.
                        $this->connect_api_chat_model->saveMessage($instance,$n['last_message'],null,false);
                    }
                }
            }

            $this->connect_api_chat_model->markEventProcessed($instance,$event,$raw);
            $this->respond(['status'=>'success','event'=>$event,'retry'=>$registered?0:1],200);
            if((string)get_option('connect_api_chat_forward_webhook_url')!=='')register_shutdown_function(function()use($raw){try{$this->forwardWebhook($raw);}catch(Throwable$ignored){}});
        }catch(Throwable$e){
            try{if($this->bootstrapped&&$instance!==''&&$event!==''&&$raw!=='')$this->connect_api_chat_model->markEventFailed($instance,$event,$raw,$e->getMessage());}catch(Throwable$ignored){}
            try{if($this->bootstrapped&&$queueId>0)$this->connect_api_chat_model->markInboundPayloadFailed($queueId,$e->getMessage());}catch(Throwable$ignored){}
            $this->logSafe('ERROR','webhook.exception',$e->getMessage(),['event'=>$event,'instance'=>$instance,'queue_id'=>$queueId,'trace'=>$e->getTraceAsString()]);
            // Se o payload já foi gravado na fila local durável, ele está aceito.
            // Evita tempestade de retries e permite reprocessamento em ~1s pela PWA.
            if($queueId>0){$this->respond(['status'=>'accepted','queued'=>true,'message'=>'Evento armazenado para reprocessamento local'],202);return;}
            $this->respond(['status'=>'error','message'=>'Falha ao processar webhook'],500);
        }
    }

    private function messageRecords($data)
    {
        if(!is_array($data))return[];
        // Evolution/Connect pode entregar um registro, uma lista ou envelopes records/messages/data.
        foreach(['messages','records','rows','data'] as$k){
            if(isset($data[$k])&&is_array($data[$k])&&!isset($data['key'])){
                $candidate=$data[$k];$keys=array_keys($candidate);$isList=$candidate===[]||$keys===range(0,count($candidate)-1);
                if($isList)return$candidate;
            }
        }
        $keys=array_keys($data);$isList=$data===[]||$keys===range(0,count($data)-1);
        return$isList?$data:[$data];
    }

    private function bootstrapChat()
    {
        if ($this->bootstrapped) return;
        $this->load->model('connect_api_chat/connect_api_chat_model');
        $this->load->library('connect_api_chat/ConnectApiChatConnectorBridge');
        $this->load->library('connect_api_chat/ConnectApiChatNormalizer');
        $this->load->library('connect_api_chat/ConnectApiChatLogger');
        $this->bootstrapped = true;
    }

    private function logSafe($level, $source, $message, $context = [])
    {
        try {
            if (!$this->bootstrapped) $this->bootstrapChat();
            $this->connectapichatlogger->log($level, $source, $message, $context);
        } catch (Throwable $ignored) {
            log_message('error', '[Chat][' . $source . '] ' . $message);
        }
    }

    private function respond($payload, $status = 200)
    {
        $this->output->set_status_header($status);
        $this->output->set_content_type('application/json', 'utf-8');
        $this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        $this->output->set_header('Pragma: no-cache');
        if ($status === 204) {
            $this->output->set_output('');
            return;
        }
        $this->output->set_output(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function listify($data)
    {
        if (!is_array($data)) return [];
        $keys = array_keys($data);
        $isList = $data === [] || $keys === range(0, count($data) - 1);
        return $isList ? $data : [$data];
    }

    private function notifyIncoming($instance, $message, $messageId)
    {
        $jid = $message['remote_jid'];
        $c = $this->connect_api_chat_model->conversationForMessage($instance, $messageId);
        if (!$c) return;

        $targets = [];
        if (!empty($c['assigned_staff_id'])) {
            $targets[] = (int)$c['assigned_staff_id'];
        } elseif (get_option('connect_api_chat_notify_unassigned') !== '0') {
            $this->db->select('staffid')->where('active', 1);
            foreach ($this->db->get(db_prefix() . 'staff')->result_array() as $s) {
                $sid=(int)$s['staffid'];
                if($this->staffCanUseInstance($sid,$instance))$targets[]=$sid;
            }
        }

        $title = $c['title'] ?: $jid;
        $preview = $message['text_content'] ?: ($message['caption'] ?: 'Nova mensagem');
        $ticket=$this->connect_api_chat_model->currentTicket($instance,(int)$c['id']);
        $isNewTicket=$ticket && !empty($ticket['is_new']);
        $description=$isNewTicket ? ('WhatsApp: novo atendimento #'.(int)$ticket['ticket_number'].' de '.$title) : ('WhatsApp: nova mensagem de '.$title);
        $notified=[];
        foreach (array_unique($targets) as $staffId) {
            if(add_notification([
                'description' => $description,
                'touserid' => $staffId,
                'link' => 'connect_api_chat?instance=' . rawurlencode($instance) . '&conversation_id=' . (int)$c['id'],
                'additional_data' => serialize([mb_substr($preview, 0, 120)]),
            ]))$notified[]=(int)$staffId;
        }
        if($notified && function_exists('pusher_trigger_notification'))pusher_trigger_notification($notified);
    }

    private function staffCanUseInstance($staffId,$instance)
    {
        $names=$this->connect_api_chat_model->staffInstanceNames((int)$staffId);
        if(!empty($names))return in_array((string)$instance,$names,true);
        $base=(string)($this->connectapichatconnectorbridge->baseContext()['instance_name']??'');
        return $base!==''&&$base===(string)$instance;
    }

    private function forwardWebhook($raw)
    {
        $url = trim((string)get_option('connect_api_chat_forward_webhook_url'));
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL) || !function_exists('curl_init')) return;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $raw,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Connect-Chat-Forwarded: 1'],
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $start = microtime(true);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $duration = (int)round((microtime(true) - $start) * 1000);
        curl_close($ch);
        if ($body === false || $status < 200 || $status >= 300) {
            $this->connectapichatlogger->log('WARNING', 'webhook.forward', 'Falha ao encaminhar webhook secundário', ['url' => $url, 'error' => $error, 'response' => $body], $status, $duration);
        } else {
            $this->connectapichatlogger->log('DEBUG', 'webhook.forward', 'Webhook secundário encaminhado', ['url' => $url], $status, $duration);
        }
    }
}
