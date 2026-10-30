<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Connect_api_chat extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model(CONNECT_API_CHAT_MODULE . '/connect_api_chat_model');
        $this->load->library(CONNECT_API_CHAT_MODULE . '/ConnectApiChatConnectorBridge');
        $this->load->library(CONNECT_API_CHAT_MODULE . '/ConnectApiChatNormalizer');
        $this->load->library(CONNECT_API_CHAT_MODULE . '/ConnectApiChatLogger');
    }

    public function index()
    {
        $this->requirePermission('view');
        $data=$this->chatViewData(false);
        $this->load->view('chat',$data);
    }

    public function app()
    {
        $this->requirePermission('view');
        if(get_option('connect_api_chat_pwa_enabled')==='0')redirect(admin_url('connect_api_chat'));
        $data=$this->chatViewData(true);
        $this->load->view('pwa',$data);
    }

    public function switch_instance()
    {
        $this->requirePermission('view');
        $this->requireAjax();
        $this->requirePost();
        try {
            $requested=trim((string)$this->input->post('instance_name'));
            $ctx=$this->resolveInstanceContext(get_staff_user_id(),$requested,true);
            $identity=$this->connectapichatconnectorbridge->instanceIdentity();
            connect_api_chat_json([
                'success'=>true,
                'instance_name'=>$ctx['instance_name'],
                'brand_name'=>trim((string)($identity['name']??''))?:'WhatsApp',
                'brand_picture'=>$identity['picture']??null,
                'csrf_hash'=>$this->security->get_csrf_hash(),
            ]);
        } catch(Throwable $e) {
            $this->connectapichatlogger->log('ERROR','controller.instance',$e->getMessage());
            connect_api_chat_json(['success'=>false,'message'=>$e->getMessage(),'csrf_hash'=>$this->security->get_csrf_hash()],400);
        }
    }

    public function manifest()
    {
        $this->requirePermission('view');
        try{$this->resolveInstanceContext(get_staff_user_id(),null,false);}catch(Throwable $e){}
        $identity=$this->connectapichatconnectorbridge->configured()?$this->connectapichatconnectorbridge->instanceIdentity():['name'=>'WhatsApp','picture'=>null];
        $appName=trim((string)($identity['name']??'')) ?: 'WhatsApp';
        $manifest=[
            'name'=>$appName . ' - Atendimento',
            'short_name'=>mb_substr($appName,0,24),
            'id'=>admin_url('connect_api_chat/app'),
            'start_url'=>admin_url('connect_api_chat/app'),
            'scope'=>admin_url('connect_api_chat/'),
            'display'=>'standalone',
            'background_color'=>'#f0f2f5',
            'theme_color'=>'#00a884',
            'description'=>'Atendimento WhatsApp colaborativo vinculado à instância ativa do Conector.',
            'icons'=>[
                ['src'=>base_url('modules/connect_api_chat/assets/pwa/icon-192.png'),'sizes'=>'192x192','type'=>'image/png','purpose'=>'any maskable'],
                ['src'=>base_url('modules/connect_api_chat/assets/pwa/icon-512.png'),'sizes'=>'512x512','type'=>'image/png','purpose'=>'any maskable'],
            ],
        ];
        $this->output->set_content_type('application/manifest+json','utf-8')->set_header('Cache-Control: no-cache')->set_output(json_encode($manifest,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    }

    public function service_worker()
    {
        $this->requirePermission('view');
        $js="const CACHE='connect-api-chat-v112';\nself.addEventListener('install',e=>self.skipWaiting());\nself.addEventListener('activate',e=>e.waitUntil(self.clients.claim()));\nself.addEventListener('fetch',e=>{if(e.request.method!=='GET'||e.request.mode!=='navigate')return;e.respondWith(fetch(e.request));});";
        $this->output->set_content_type('application/javascript','utf-8')->set_header('Cache-Control: no-cache, no-store')->set_header('Service-Worker-Allowed: '.parse_url(admin_url('connect_api_chat/'),PHP_URL_PATH))->set_output($js);
    }

    public function diagnostics()
    {
        $this->requirePermission('manage_settings');
        $checks=[];
        $checks[]=['name'=>'Módulo Conector','ok'=>$this->connectapichatconnectorbridge->available(),'detail'=>$this->connectapichatconnectorbridge->available()?'Disponível':'Não disponível'];
        $checks[]=['name'=>'Configuração do Conector','ok'=>$this->connectapichatconnectorbridge->configured(),'detail'=>$this->connectapichatconnectorbridge->configured()?'Configurado':'Incompleto'];
        if($this->connectapichatconnectorbridge->configured()){
            foreach(['state'=>'Estado da instância','instanceIdentity'=>'Perfil do WhatsApp','findChats'=>'Consulta de conversas','getWebhook'=>'Webhook configurado na API'] as $method=>$label){
                $started=microtime(true);try{$response=$this->connectapichatconnectorbridge->{$method}();$checks[]=['name'=>$label,'ok'=>true,'detail'=>'OK · '.(int)round((microtime(true)-$started)*1000).' ms','response'=>$response];}catch(Throwable$e){$checks[]=['name'=>$label,'ok'=>false,'detail'=>$e->getMessage()];$this->connectapichatlogger->log('ERROR','diagnostics.'.$method,$e->getMessage());}
            }
        }
        $checks[]=$this->webhookHealthCheck();
        $this->load->view('diagnostics',[
            'title'=>_l('connect_api_chat_diagnostics'),
            'debug_enabled'=>$this->connectapichatlogger->enabled(),
            'logs'=>$this->connectapichatlogger->recent(300),
            'checks'=>$checks,
            'webhook_url'=>site_url('connect_api_chat/webhook/receive'),
            'pwa_url'=>admin_url('connect_api_chat/app'),
        ]);
    }

    public function download_diagnostics()
    {
        $this->requirePermission('manage_settings');
        try {
            $contexts=[];
            foreach($this->connectapichatconnectorbridge->availableContexts() as $ctx){
                $contexts[]=[
                    'instance_name'=>(string)($ctx['instance_name']??''),
                    'base_url'=>(string)($ctx['base_url']??''),
                    'verify_tls'=>!empty($ctx['verify_tls']),
                    'timeout'=>(int)($ctx['timeout']??0),
                    'configured'=>!empty($ctx['instance_name'])&&!empty($ctx['base_url'])&&!empty($ctx['instance_token']),
                ];
            }
            $report=[
                'generated_at'=>date('c'),
                'module'=>['name'=>'Connect|API Chat','version'=>CONNECT_API_CHAT_VERSION],
                'runtime'=>[
                    'php_version'=>PHP_VERSION,
                    'codeigniter_version'=>defined('CI_VERSION')?CI_VERSION:null,
                    'base_url'=>site_url(),
                    'webhook_url'=>site_url('connect_api_chat/webhook/receive'),
                    'pwa_url'=>admin_url('connect_api_chat/app'),
                ],
                'settings'=>[
                    'debug_enabled'=>$this->connectapichatlogger->enabled(),
                    'all_staff'=>get_option('connect_api_chat_all_staff')==='1',
                    'poll_interval'=>(int)get_option('connect_api_chat_poll_interval'),
                    'realtime_poll_ms'=>(int)(get_option('connect_api_chat_realtime_poll_ms')?:1000),
                    'realtime_transport'=>'webhook_local_events',
                    'page_size'=>(int)get_option('connect_api_chat_page_size'),
                    'upload_limit_mb'=>(int)get_option('connect_api_chat_upload_limit_mb'),
                    'api_sync_interval'=>(int)get_option('connect_api_chat_api_sync_interval'),
                    'pwa_enabled'=>get_option('connect_api_chat_pwa_enabled')==='1',
                    'multi_instance_enabled'=>get_option('connect_api_chat_multi_instance_enabled')==='1',
                    'last_inbound_at'=>(string)get_option('connect_api_chat_last_inbound_at'),
                    'last_inbound_message_id'=>(string)get_option('connect_api_chat_last_inbound_message_id'),
                    'last_watchdog_at'=>(int)get_option('connect_api_chat_last_watchdog_at'),
                    'watchdog_seconds'=>(int)(get_option('connect_api_chat_watchdog_seconds')?:10),
                    'inbound_queue_enabled'=>get_option('connect_api_chat_inbound_queue_enabled')!=='0',
                    'media_storage_mode'=>(string)(get_option('connect_api_chat_media_storage_mode')?:'local_on_demand'),
                ],
                'connector_contexts'=>$contexts,
                'inbound_queue'=>!empty($contexts[0]['instance_name'])?$this->connect_api_chat_model->inboundQueueStats((string)$contexts[0]['instance_name']):['pending'=>0,'failed'=>0,'processed'=>0],
                'media_storage'=>[
                    'mode'=>(string)(get_option('connect_api_chat_media_storage_mode')?:'local_on_demand'),
                    'directory'=>'uploads/connect_api_chat/<instancia>',
                    'remote_presigned_fallback'=>false,
                ],
                'logs'=>$this->connectapichatlogger->recent(1000),
            ];
            $json=json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            if($json===false)throw new RuntimeException('Não foi possível serializar o diagnóstico.');
            $filename='connect-api-chat-diagnostico-'.date('Ymd-His').'.json';
            $this->output->set_content_type('application/json','utf-8');
            $this->output->set_header('Content-Disposition: attachment; filename="'.$filename.'"');
            $this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate');
            $this->output->set_header('Pragma: no-cache');
            $this->output->set_output($json);
        } catch(Throwable $e) {
            $this->connectapichatlogger->log('ERROR','diagnostics.download',$e->getMessage());
            show_error('Não foi possível gerar o diagnóstico: '.$e->getMessage(),500);
        }
    }

    public function clear_debug_logs()
    {
        $this->requirePermission('manage_settings');$this->requirePost();$this->connectapichatlogger->clear();set_alert('success',_l('connect_api_chat_debug_cleared'));redirect(admin_url('connect_api_chat/diagnostics'));
    }

    public function settings()
    {
        $this->requirePermission('manage_settings');
        if ($this->input->post()) {
            update_option('connect_api_chat_all_staff',$this->input->post('all_staff')?'1':'0');
            update_option('connect_api_chat_auto_claim_on_send',$this->input->post('auto_claim_on_send')?'1':'0');
            update_option('connect_api_chat_notify_unassigned',$this->input->post('notify_unassigned')?'1':'0');
            update_option('connect_api_chat_poll_interval',(string)max(2,min(15,(int)$this->input->post('poll_interval'))));
            update_option('connect_api_chat_page_size',(string)max(20,min(100,(int)$this->input->post('page_size'))));
            update_option('connect_api_chat_upload_limit_mb',(string)max(1,min(64,(int)$this->input->post('upload_limit_mb'))));
            update_option('connect_api_chat_api_sync_interval',(string)max(15,min(300,(int)($this->input->post('api_sync_interval')?:60))));
            update_option('connect_api_chat_debug_enabled',$this->input->post('debug_enabled')?'1':'0');
            update_option('connect_api_chat_debug_retention',(string)max(50,min(2000,(int)($this->input->post('debug_retention')?:300))));
            update_option('connect_api_chat_pwa_enabled',$this->input->post('pwa_enabled')?'1':'0');
            $forwardUrl=trim((string)$this->input->post('forward_webhook_url'));
            if($forwardUrl!==''&&!filter_var($forwardUrl,FILTER_VALIDATE_URL)){set_alert('danger',_l('connect_api_chat_forward_webhook_invalid'));redirect(admin_url('connect_api_chat/settings'));}
            update_option('connect_api_chat_forward_webhook_url',$forwardUrl);
            update_option('connect_api_chat_multi_instance_enabled',$this->input->post('multi_instance_enabled')?'1':'0');
            if($this->input->post('instance_access_present')){
                $allNames=[];foreach($this->connectapichatconnectorbridge->availableContexts() as $ic){if(!empty($ic['instance_name']))$allNames[]=(string)$ic['instance_name'];}
                $staffRows=$this->connect_api_chat_model->staffList();
                $postedAccess=$this->input->post('staff_instances');if(!is_array($postedAccess))$postedAccess=[];
                $postedDefault=$this->input->post('staff_default');if(!is_array($postedDefault))$postedDefault=[];
                foreach($staffRows as $staffRow){
                    $sid=(int)$staffRow['staffid'];$names=$postedAccess[$sid]??[];if(!is_array($names))$names=[];
                    $names=array_values(array_intersect(array_map('strval',$names),$allNames));
                    $default=(string)($postedDefault[$sid]??'');
                    $this->connect_api_chat_model->replaceStaffInstanceAccess($sid,$names,$default);
                }
            }
            set_alert('success',_l('settings_updated')); redirect(admin_url('connect_api_chat/settings'));
        }
        $ctx=$this->connectapichatconnectorbridge->context();
        $webhook=null; $webhookError=null; $state=null;
        if ($this->connectapichatconnectorbridge->configured()) {
            try { $webhook=$this->connectapichatconnectorbridge->getWebhook(); } catch(Throwable $e){$webhookError=$e->getMessage();}
            try { $state=$this->connectapichatconnectorbridge->state(); } catch(Throwable $e){}
        }
        $instanceContexts=[];
        foreach($this->connectapichatconnectorbridge->availableContexts() as $ic){
            if(empty($ic['instance_name']))continue;
            $instanceContexts[]=['instance_name'=>(string)$ic['instance_name'],'source'=>(string)($ic['source']??'connector'),'phone_number'=>(string)($ic['instance_number']??'')];
        }
        $this->load->view('settings',[
            'title'=>_l('connect_api_chat_settings'),'connector'=>$ctx,'connector_available'=>$this->connectapichatconnectorbridge->available(),'connector_configured'=>$this->connectapichatconnectorbridge->configured(),
            'webhook'=>$webhook,'webhook_error'=>$webhookError,'state'=>$state,'webhook_url'=>site_url('connect_api_chat/webhook/receive'),
            'debug_enabled'=>$this->connectapichatlogger->enabled(),'pwa_url'=>admin_url('connect_api_chat/app'),
            'instance_contexts'=>$instanceContexts,
            'staff_rows'=>$this->connect_api_chat_model->staffList(),
            'staff_instance_access'=>$this->connect_api_chat_model->allStaffInstanceAccess(),
        ]);
    }

    public function configure_webhook()
    {
        $this->requirePermission('manage_settings'); $this->requirePost();
        try {
            $contexts=$this->connectapichatconnectorbridge->availableContexts();
            if(empty($contexts)) throw new RuntimeException(_l('connect_api_chat_connector_not_configured'));
            $secret=(string)get_option('connect_api_chat_webhook_secret'); if ($secret==='') { $secret=bin2hex(random_bytes(24)); update_option('connect_api_chat_webhook_secret',$secret); }
            $configured=[];$errors=[];
            foreach($contexts as $ctx){
                try{
                    $this->connectapichatconnectorbridge->useContext($ctx)->setWebhook(site_url('connect_api_chat/webhook/receive'),$secret);
                    $configured[]=(string)$ctx['instance_name'];
                }catch(Throwable $inner){$errors[]=(string)$ctx['instance_name'].': '.$inner->getMessage();}
            }
            $this->connectapichatconnectorbridge->clearContext();
            update_option('connect_api_chat_webhook_configured_instance',implode(',',$configured));
            if(empty($configured))throw new RuntimeException(implode('; ',$errors));
            $message=_l('connect_api_chat_webhook_configured').' ('.count($configured).')';
            if(!empty($errors))$message.=' · '.count($errors).' falha(s)';
            set_alert(empty($errors)?'success':'warning',$message);
        } catch(Throwable $e){ $this->connectapichatlogger->log('ERROR','webhook.configure',$e->getMessage()); set_alert('danger',$e->getMessage()); }
        redirect(admin_url('connect_api_chat/settings'));
    }

    public function conversations()
    {
        $this->requirePermission('view'); $this->requireAjax();
        try {
            $ctx=$this->requireConnector(); $scope=(string)$this->input->get('scope'); $search=trim((string)$this->input->get('search'));
            // Reparo local de tickets live pendentes. Não consulta Connect|API e é barato.
            $this->connect_api_chat_model->repairLiveInboundTickets($ctx['instance_name'],5);
            if (!in_array($scope,['mine','unassigned','all','closed'],true)) $scope='mine';
            if ($scope==='all'&&!connect_api_chat_can('view_all')) $scope='mine';
            $rows=$this->connect_api_chat_model->listConversations($ctx['instance_name'],get_staff_user_id(),$scope,$search);
            $counts=$this->connect_api_chat_model->conversationCounts($ctx['instance_name'],get_staff_user_id());
            connect_api_chat_json(['success'=>true,'conversations'=>$rows,'counts'=>$counts,'csrf_hash'=>$this->security->get_csrf_hash()]);
        } catch(Throwable $e){$this->connectapichatlogger->log('ERROR','controller.error',$e->getMessage());connect_api_chat_json(['success'=>false,'message'=>$e->getMessage(),'csrf_hash'=>$this->security->get_csrf_hash()],400);}
    }

    public function messages()
    {
        $this->requirePermission('view'); $this->requireAjax();
        try {
            $ctx=$this->requireConnector(); $id=(int)$this->input->get('conversation_id'); $before=(int)$this->input->get('before_id'); $focus=(int)$this->input->get('focus_id');
            $c=$this->allowedConversation($ctx['instance_name'],$id,true);
            $rows=$focus ? $this->connect_api_chat_model->messagesAround($ctx['instance_name'],$id,$focus,24) : $this->connect_api_chat_model->messages($ctx['instance_name'],$id,(int)(get_option('connect_api_chat_page_size')?:30),$before ?: null,null);
            if (!$before && !$focus && empty($rows)) {
                try {
                    $this->syncConversationJids($ctx['instance_name'],$c,50);
                    $rows=$this->connect_api_chat_model->messages($ctx['instance_name'],$id,(int)(get_option('connect_api_chat_page_size')?:30),null,null);
                } catch(Throwable $syncError) { $this->connectapichatlogger->log('WARNING','controller.messages.sync',$syncError->getMessage()); }
            }
            $this->connect_api_chat_model->markRead($id,get_staff_user_id());
            $crm=[];
            try{$crm=$this->connect_api_chat_model->crmContext($c['phone_number']??'');}catch(Throwable $crmError){$this->connectapichatlogger->log('WARNING','crm.lookup',$crmError->getMessage(),['phone'=>$c['phone_number']??'']);}
            $rows=$this->decorateMediaRows($rows);
            connect_api_chat_json(['success'=>true,'conversation'=>$c,'messages'=>$rows,'crm'=>$crm,'assignment_history'=>$this->connect_api_chat_model->assignmentHistory($id),'ticket_history'=>$this->connect_api_chat_model->ticketHistory($ctx['instance_name'],$id),'csrf_hash'=>$this->security->get_csrf_hash()]);
        } catch(Throwable $e){$this->connectapichatlogger->log('ERROR','controller.error',$e->getMessage());connect_api_chat_json(['success'=>false,'message'=>$e->getMessage(),'csrf_hash'=>$this->security->get_csrf_hash()],400);}
    }

    public function realtime_events()
    {
        $this->requirePermission('view');
        $this->requireAjax();
        try {
            $ctx=$this->requireConnector();
            // Reprocessa primeiro a fila DURÁVEL deixada pelo webhook. É somente banco local
            // e roda antes do cursor realtime, portanto a mensagem aparece praticamente no
            // mesmo ciclo de 1s da PWA mesmo se a regra síncrona do webhook tiver falhado.
            $this->processPendingInboundQueue($ctx['instance_name'],20);
            // Garante que um inbound já persistido nunca fique preso em ticket encerrado.
            $this->connect_api_chat_model->repairLiveInboundTickets($ctx['instance_name'],5);
            $after=max(0,(int)$this->input->get('after'));
            if($after===0){
                connect_api_chat_json(['success'=>true,'events'=>[],'cursor'=>$this->connect_api_chat_model->realtimeCursor($ctx['instance_name']),'csrf_hash'=>$this->security->get_csrf_hash()]);
                return;
            }
            $rows=$this->connect_api_chat_model->realtimeEvents($ctx['instance_name'],$after,120);
            $cursor=$after;$visible=[];
            foreach($rows as$row){
                $cursor=max($cursor,(int)$row['id']);
                $cid=(int)($row['conversation_id']??0);
                if($cid){
                    try{$this->allowedConversation($ctx['instance_name'],$cid,true);}catch(Throwable$ignore){continue;}
                }
                $visible[]=$row;
            }
            connect_api_chat_json(['success'=>true,'events'=>$visible,'cursor'=>$cursor,'csrf_hash'=>$this->security->get_csrf_hash()]);
        } catch(Throwable $e){
            $this->connectapichatlogger->log('ERROR','controller.realtime',$e->getMessage());
            connect_api_chat_json(['success'=>false,'message'=>$e->getMessage(),'csrf_hash'=>$this->security->get_csrf_hash()],400);
        }
    }

    /**
     * Watchdog de segurança do recebimento.
     *
     * O webhook continua sendo a fonte realtime principal. Este endpoint só consulta
     * `findChats` quando não houve inbound recente e respeita throttle, evitando a
     * regressão de performance da 1.0.9. Recupera apenas lastMessage inbound recente.
     */
    public function inbound_watchdog()
    {
        $this->requirePermission('view');
        $this->requireAjax();
        try {
            $ctx=$this->requireConnector();
            $instance=(string)$ctx['instance_name'];
            $now=time();
            $watchdogSeconds=max(5,min(60,(int)(get_option('connect_api_chat_watchdog_seconds')?:10)));
            $lastRun=(int)get_option('connect_api_chat_last_watchdog_at');
            if($lastRun>0 && ($now-$lastRun)<$watchdogSeconds){
                connect_api_chat_json(['success'=>true,'checked'=>false,'recovered'=>0,'csrf_hash'=>$this->security->get_csrf_hash()]);
                return;
            }
            update_option('connect_api_chat_last_watchdog_at',(string)$now);

            $lastInbound=(string)get_option('connect_api_chat_last_inbound_at');
            $lastInboundTs=$lastInbound!=='' ? strtotime($lastInbound) : 0;
            if($lastInboundTs>0 && ($now-$lastInboundTs)<($watchdogSeconds+2)){
                connect_api_chat_json(['success'=>true,'checked'=>false,'recovered'=>0,'csrf_hash'=>$this->security->get_csrf_hash()]);
                return;
            }

            $response=$this->connectapichatconnectorbridge->findChats();
            $records=$this->connectapichatconnectorbridge->normalizeList($response);
            $recovered=0;
            foreach($records as$row){
                $last=$row['lastMessage']??$row['last_message']??null;
                if(!is_array($last))continue;
                $n=$this->connectapichatnormalizer->normalizeMessage($last);
                if(!$n||!empty($n['from_me']))continue;
                $ts=(int)($n['message_timestamp']??0);
                // Watchdog é somente para perda recente do webhook, nunca backfill histórico.
                if($ts<=0||$ts<($now-180))continue;
                $apiId=(string)($n['api_message_id']??'');
                if($apiId===''||$this->connect_api_chat_model->message($instance,$apiId))continue;
                $id=$this->connect_api_chat_model->saveMessage($instance,$n,null,true);
                if($id){
                    $recovered++;
                    update_option('connect_api_chat_last_inbound_at',date('Y-m-d H:i:s'));
                    update_option('connect_api_chat_last_inbound_message_id',$apiId);
                }
            }
            if($recovered){
                $this->connect_api_chat_model->repairLiveInboundTickets($instance,10);
                $this->connectapichatlogger->log('WARNING','inbound.watchdog','Mensagem(ns) inbound recuperada(s) pelo watchdog',['instance'=>$instance,'recovered'=>$recovered]);
            }
            connect_api_chat_json(['success'=>true,'checked'=>true,'recovered'=>$recovered,'csrf_hash'=>$this->security->get_csrf_hash()]);
        } catch(Throwable $e){
            $this->connectapichatlogger->log('ERROR','inbound.watchdog',$e->getMessage());
            connect_api_chat_json(['success'=>false,'message'=>$e->getMessage(),'csrf_hash'=>$this->security->get_csrf_hash()],400);
        }
    }

    public function poll()
    {
        $this->requirePermission('view'); $this->requireAjax();
        try {
            $ctx=$this->requireConnector(); $id=(int)$this->input->get('conversation_id'); $after=(int)$this->input->get('after_id');
            $rows=[];$conversation=null; if ($id) { $conversation=$this->allowedConversation($ctx['instance_name'],$id,true); $rows=$this->connect_api_chat_model->messages($ctx['instance_name'],$id,100,null,$after?:null); if($rows)$this->connect_api_chat_model->markRead($id,get_staff_user_id()); $conversation=$this->connect_api_chat_model->conversation($ctx['instance_name'],$id); }
            $rows=$this->decorateMediaRows($rows);
            connect_api_chat_json(['success'=>true,'messages'=>$rows,'conversation'=>$conversation,'csrf_hash'=>$this->security->get_csrf_hash()]);
        } catch(Throwable $e){$this->connectapichatlogger->log('ERROR','controller.error',$e->getMessage());connect_api_chat_json(['success'=>false,'message'=>$e->getMessage(),'csrf_hash'=>$this->security->get_csrf_hash()],400);}
    }

    public function search_messages()
    {
        $this->requirePermission('view'); $this->requireAjax();
        try {
            $ctx=$this->requireConnector(); $id=(int)$this->input->get('conversation_id'); $term=trim((string)$this->input->get('q'));
            $this->allowedConversation($ctx['instance_name'],$id,true);
            $rows=$term===''?[]:$this->connect_api_chat_model->searchMessages($ctx['instance_name'],$id,$term,150);
            connect_api_chat_json(['success'=>true,'results'=>$rows,'csrf_hash'=>$this->security->get_csrf_hash()]);
        } catch(Throwable $e){$this->connectapichatlogger->log('ERROR','controller.search',$e->getMessage());connect_api_chat_json(['success'=>false,'message'=>$e->getMessage(),'csrf_hash'=>$this->security->get_csrf_hash()],400);}
    }

    public function conversation_status()
    {
        $this->requirePermission('close_conversation'); $this->requireAjax(); $this->requirePost();
        try {
            $ctx=$this->requireConnector(); $id=(int)$this->input->post('conversation_id'); $action=(string)$this->input->post('action');
            $this->allowedConversation($ctx['instance_name'],$id,true);
            $status=$action==='close'?'closed':'open';
            if(!$this->connect_api_chat_model->setConversationStatus($ctx['instance_name'],$id,$status,get_staff_user_id()))throw new RuntimeException('Não foi possível atualizar o atendimento.');
            $ticket=$this->connect_api_chat_model->currentTicket($ctx['instance_name'],$id);
            $this->connectapichatlogger->log('INFO','ticket.status',$status==='closed'?'Atendimento encerrado':'Atendimento reaberto',[
                'conversation_id'=>$id,'ticket_id'=>(int)($ticket['id']??0),'ticket_number'=>(int)($ticket['ticket_number']??0),'staff_id'=>(int)get_staff_user_id()
            ]);
            connect_api_chat_json(['success'=>true,'status'=>$status,'ticket'=>$ticket,'csrf_hash'=>$this->security->get_csrf_hash()]);
        } catch(Throwable $e){$this->connectapichatlogger->log('ERROR','controller.status',$e->getMessage());connect_api_chat_json(['success'=>false,'message'=>$e->getMessage(),'csrf_hash'=>$this->security->get_csrf_hash()],400);}
    }

    public function send_text()
    {
        $this->requirePermission('send'); $this->requireAjax(); $this->requirePost();
        try {
            $ctx=$this->requireConnector(); $id=(int)$this->input->post('conversation_id'); $text=trim((string)$this->input->post('message')); $quoted=trim((string)$this->input->post('quoted_id'));
            if ($text==='') throw new RuntimeException(_l('connect_api_chat_message_required'));
            $c=$this->allowedConversation($ctx['instance_name'],$id,false); if(($c['status']??'open')==='closed')throw new RuntimeException('Este atendimento está encerrado. Reabra-o para enviar mensagens.'); $this->claimIfNeeded($ctx['instance_name'],$c);
            $number=$this->numberForConversation($c); $quotedPayload=$this->quotedPayload($ctx['instance_name'],$quoted); $api=$this->connectapichatconnectorbridge->sendText($number,$text,$quotedPayload);
            $apiId=$this->apiMessageId($api) ?: ('local_'.bin2hex(random_bytes(8))); $status=$api['status']??($api['data']['status']??'sent');
            $localId=$this->connect_api_chat_model->saveMessage($ctx['instance_name'],[
                'api_message_id'=>$apiId,'remote_jid'=>$c['remote_jid'],'remote_jid_alt'=>$c['remote_jid_alt']??null,'from_me'=>true,'sender_name'=>get_staff_full_name(get_staff_user_id()),'message_type'=>'text','text_content'=>$text,'message_timestamp'=>time(),'status'=>$status,'quoted_api_message_id'=>$quoted?:null,'raw_payload'=>json_encode($api)
            ],get_staff_user_id());
            $this->connect_api_chat_model->acknowledgeCurrentTicket($ctx['instance_name'],$id);
            connect_api_chat_json(['success'=>true,'message_id'=>$localId,'api_message_id'=>$apiId,'csrf_hash'=>$this->security->get_csrf_hash()]);
        } catch(Throwable $e){$this->connectapichatlogger->log('ERROR','controller.error',$e->getMessage());connect_api_chat_json(['success'=>false,'message'=>$e->getMessage(),'csrf_hash'=>$this->security->get_csrf_hash()],400);}
    }

    public function send_media()
    {
        $this->requirePermission('send'); $this->requireAjax(); $this->requirePost();
        try {
            $ctx=$this->requireConnector(); $id=(int)$this->input->post('conversation_id'); $caption=trim((string)$this->input->post('caption')); $quoted=trim((string)$this->input->post('quoted_id')); $ptt=$this->input->post('ptt')==='1';
            $c=$this->allowedConversation($ctx['instance_name'],$id,false); if(($c['status']??'open')==='closed')throw new RuntimeException('Este atendimento está encerrado. Reabra-o para enviar mensagens.'); $this->claimIfNeeded($ctx['instance_name'],$c);
            if (!isset($_FILES['file']) || $_FILES['file']['error']!==UPLOAD_ERR_OK) throw new RuntimeException(_l('connect_api_chat_file_required'));
            $max=(int)(get_option('connect_api_chat_upload_limit_mb')?:16)*1024*1024; if ((int)$_FILES['file']['size']>$max) throw new RuntimeException(_l('connect_api_chat_file_too_large'));
            $tmp=$_FILES['file']['tmp_name']; $name=basename($_FILES['file']['name']); $mime=mime_content_type($tmp)?:($_FILES['file']['type']?:'application/octet-stream'); $number=$this->numberForConversation($c);
            $quotedPayload=$this->quotedPayload($ctx['instance_name'],$quoted);
            $api=$this->connectapichatconnectorbridge->sendMedia($number,$tmp,$name,$mime,$caption,$quotedPayload,$ptt);
            $apiId=$this->apiMessageId($api) ?: ('local_'.bin2hex(random_bytes(8))); $type=$ptt?'audio':(strpos($mime,'image/')===0?'image':(strpos($mime,'video/')===0?'video':(strpos($mime,'audio/')===0?'audio':'document')));
            $localId=$this->connect_api_chat_model->saveMessage($ctx['instance_name'],[
                'api_message_id'=>$apiId,'remote_jid'=>$c['remote_jid'],'remote_jid_alt'=>$c['remote_jid_alt']??null,'from_me'=>true,'sender_name'=>get_staff_full_name(get_staff_user_id()),'message_type'=>$type,'caption'=>$caption?:null,'file_name'=>$name,'mimetype'=>$mime,'message_timestamp'=>time(),'status'=>$api['status']??'sent','quoted_api_message_id'=>$quoted?:null,'raw_payload'=>json_encode($api)
            ],get_staff_user_id());
            $stored=$this->persistMediaFile($ctx['instance_name'],$localId,$tmp,$name,$mime);
            if($stored)$this->connect_api_chat_model->setLocalMediaPath($ctx['instance_name'],$localId,$stored['relative'],$mime,$name);
            $this->connect_api_chat_model->acknowledgeCurrentTicket($ctx['instance_name'],$id);
            connect_api_chat_json(['success'=>true,'message_id'=>$localId,'media_url'=>admin_url('connect_api_chat/media/'.$localId),'csrf_hash'=>$this->security->get_csrf_hash()]);
        } catch(Throwable $e){$this->connectapichatlogger->log('ERROR','controller.error',$e->getMessage());connect_api_chat_json(['success'=>false,'message'=>$e->getMessage(),'csrf_hash'=>$this->security->get_csrf_hash()],400);}
    }

    public function send_contact()
    {
        $this->requirePermission('send'); $this->requireAjax(); $this->requirePost();
        try {
            $ctx=$this->requireConnector(); $id=(int)$this->input->post('conversation_id');
            $fullName=trim((string)$this->input->post('full_name')); $phone=$this->connectapichatconnectorbridge->normalizeNumber($this->input->post('phone'));
            $organization=trim((string)$this->input->post('organization')); $email=trim((string)$this->input->post('email')); $quoted=trim((string)$this->input->post('quoted_id'));
            if($fullName===''||strlen($phone)<10)throw new RuntimeException('Informe nome e telefone válidos para o contato.');
            if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('E-mail do contato inválido.');
            $c=$this->allowedConversation($ctx['instance_name'],$id,false); if(($c['status']??'open')==='closed')throw new RuntimeException('Este atendimento está encerrado. Reabra-o para enviar mensagens.'); $this->claimIfNeeded($ctx['instance_name'],$c);
            $number=$this->numberForConversation($c); $contact=['fullName'=>$fullName,'wuid'=>$phone,'phoneNumber'=>'+'.$phone];
            if($organization!=='')$contact['organization']=$organization; if($email!=='')$contact['email']=$email;
            $quotedPayload=$this->quotedPayload($ctx['instance_name'],$quoted); $api=$this->connectapichatconnectorbridge->sendContact($number,$contact,$quotedPayload);
            $apiId=$this->apiMessageId($api) ?: ('local_'.bin2hex(random_bytes(8)));
            $localId=$this->connect_api_chat_model->saveMessage($ctx['instance_name'],[
                'api_message_id'=>$apiId,'remote_jid'=>$c['remote_jid'],'remote_jid_alt'=>$c['remote_jid_alt']??null,'from_me'=>true,'sender_name'=>get_staff_full_name(get_staff_user_id()),'message_type'=>'contact','text_content'=>'Contato: '.$fullName.' · '.$phone,'message_timestamp'=>time(),'status'=>$api['status']??'sent','quoted_api_message_id'=>$quoted?:null,'raw_payload'=>json_encode($api)
            ],get_staff_user_id());
            $this->connect_api_chat_model->acknowledgeCurrentTicket($ctx['instance_name'],$id);
            connect_api_chat_json(['success'=>true,'message_id'=>$localId,'csrf_hash'=>$this->security->get_csrf_hash()]);
        } catch(Throwable $e){$this->connectapichatlogger->log('ERROR','controller.error',$e->getMessage());connect_api_chat_json(['success'=>false,'message'=>$e->getMessage(),'csrf_hash'=>$this->security->get_csrf_hash()],400);}
    }

    public function media($messageId=0)
    {
        $this->requirePermission('view');
        try {
            $ctx=$this->requireConnector();
            $message=$this->connect_api_chat_model->messageById($ctx['instance_name'],(int)$messageId);
            if(!$message)show_404();
            $this->allowedConversation($ctx['instance_name'],(int)$message['conversation_id'],true);
            $download=$this->input->get('download')==='1'||(($message['message_type']??'')==='document');

            // 1) Arquivo local: usado principalmente para mídias enviadas pela própria equipe.
            $path=$this->absoluteMediaPath((string)($message['local_media_path']??''));
            if($path && is_file($path) && filesize($path)>0){
                $mime=(string)($message['mimetype']??'application/octet-stream');
                if($mime===''||$mime==='application/octet-stream')$mime=mime_content_type($path)?:'application/octet-stream';
                $mime=preg_replace('/\s*;.*$/','',$mime);
                $this->connectapichatlogger->log('DEBUG','media.local','Mídia servida do cache local',['message_id'=>(int)$messageId,'bytes'=>filesize($path),'mimetype'=>$mime]);
                $this->streamMediaFile($path,$mime,(string)($message['file_name']??basename($path)),$download);
                return;
            }

            $apiMessageId=trim((string)($message['api_message_id']??''));
            if($apiMessageId===''||strpos($apiMessageId,'local_')===0)throw new RuntimeException('A mensagem não possui identificador remoto válido.');
            $this->connectapichatlogger->log('DEBUG','media.fetch','Iniciando recuperação de mídia',['message_id'=>(int)$messageId,'api_message_id'=>$apiMessageId,'message_type'=>$message['message_type']??'','from_me'=>!empty($message['from_me'])]);

            $fresh=null;$lastError=null;$isAudio=(($message['message_type']??'')==='audio');
            try{
                $freshResponse=$this->connectapichatconnectorbridge->findMessageById($apiMessageId);
                foreach($this->connectapichatconnectorbridge->normalizeList($freshResponse) as $row){
                    if(!is_array($row))continue;
                    $rid=(string)($row['key']['id']??$row['id']??'');
                    if($rid===$apiMessageId){$fresh=$row;break;}
                }
                if($fresh){
                    try{$this->connect_api_chat_model->refreshMessageRawPayload($ctx['instance_name'],(int)$messageId,$fresh);}catch(Throwable $ignore){}
                    $this->connectapichatlogger->log('DEBUG','media.lookup','Mensagem remota localizada',['message_id'=>(int)$messageId,'api_message_id'=>$apiMessageId]);
                }
            }catch(Throwable $e){$lastError=$e;$this->connectapichatlogger->log('WARNING','media.lookup',$e->getMessage(),['message_id'=>(int)$messageId,'api_message_id'=>$apiMessageId]);}

            // 2) Estratégia preferencial: WebMessageInfo COMPLETO recém obtido.
            // É exatamente o padrão robusto do Baileys: downloadMediaMessage recebe key+message,
            // preservando mediaKey/directPath/wrappers e permitindo reuploadRequest na API.
            $conversionOrder=$isAudio?[false,true]:[false];
            $media=null;
            if($fresh){
                foreach($conversionOrder as$convert){
                    try{
                        $response=$this->connectapichatconnectorbridge->mediaBase64($fresh,$convert);
                        if(isset($response['data'])&&is_array($response['data'])&&empty($response['base64']))$response=$response['data'];
                        if(is_array($response)&&!empty($response['base64'])){$media=$response;$this->connectapichatlogger->log('DEBUG','media.fetch','Mídia recuperada por WebMessageInfo completo',['message_id'=>(int)$messageId,'convert_to_mp4'=>$convert,'mimetype'=>$response['mimetype']??null]);break;}
                    }catch(Throwable$e){$lastError=$e;$this->connectapichatlogger->log('WARNING','media.fetch',$e->getMessage(),['message_id'=>(int)$messageId,'strategy'=>'fresh-full','convert_to_mp4'=>$convert]);}
                }
            }

            // 3) Fallback por KEY: útil quando findMessages não devolve o conteúdo completo.
            if(!$media){
                $jids=[];
                if($fresh&&is_array($fresh['key']??null))foreach([$fresh['key']['remoteJid']??'', $fresh['key']['remoteJidAlt']??''] as$jid){$jid=trim((string)$jid);if($jid!==''&&!in_array($jid,$jids,true))$jids[]=$jid;}
                foreach([$message['remote_jid']??'', $message['remote_jid_alt']??''] as$jid){$jid=trim((string)$jid);if($jid!==''&&!in_array($jid,$jids,true))$jids[]=$jid;}
                $fromCandidates=[];if($fresh&&array_key_exists('fromMe',$fresh['key']??[]))$fromCandidates[]=(bool)$fresh['key']['fromMe'];$fromCandidates[]=!empty($message['from_me']);$fromCandidates[]=true;$fromCandidates[]=false;$fromCandidates=array_values(array_unique($fromCandidates,SORT_REGULAR));
                foreach($jids as$jid)foreach($fromCandidates as$fromMe)foreach($conversionOrder as$convert){
                    try{$response=$this->connectapichatconnectorbridge->mediaBase64ByKey($apiMessageId,$jid,$fromMe,$message['remote_jid_alt']??null,$convert);if(isset($response['data'])&&is_array($response['data'])&&empty($response['base64']))$response=$response['data'];if(is_array($response)&&!empty($response['base64'])){$media=$response;$this->connectapichatlogger->log('DEBUG','media.fetch','Mídia recuperada por key',['message_id'=>(int)$messageId,'jid'=>$jid,'from_me'=>$fromMe,'convert_to_mp4'=>$convert]);break 3;}}catch(Throwable$e){$lastError=$e;}
                }
            }

            $binary=null;$mime='';$fileName=(string)($message['file_name']??('media-'.$messageId));
            if($media){
                $base64=(string)$media['base64'];$dataMime=null;
                if(strpos($base64,'data:')===0&&strpos($base64,',')!==false){$header=substr($base64,0,strpos($base64,','));if(preg_match('#^data:([^;]+)#',$header,$mm))$dataMime=$mm[1];$base64=substr($base64,strpos($base64,',')+1);}
                $binary=base64_decode(preg_replace('/\s+/','',$base64),true);
                $mime=trim((string)($dataMime?:($media['mimetype']??$message['mimetype']??'application/octet-stream')));
                $fileName=(string)($media['fileName']??$fileName);
            }

            // 4) Nunca usa mediaUrl interno do MinIO como fallback. O hostname pertence
            // à rede privada do Connect|API e não é resolvível pelo servidor do CRM.
            // A mídia só é aceita quando getBase64FromMediaMessage devolve o binário.
            if($binary===false||$binary===null||$binary==='')throw new RuntimeException($lastError?$lastError->getMessage():'Connect|API não retornou o conteúdo da mídia.');
            if($mime==='')$mime=(string)($message['mimetype']??'application/octet-stream');
            if($mime==='')$mime='application/octet-stream';

            // A mídia recebida NÃO é baixada automaticamente. Porém, quando o usuário
            // clica para carregar, esse ato é explícito: grava uma única cópia no storage
            // local do CRM e os próximos plays/previews não dependem mais da API/MinIO.
            $stored=$this->persistBinaryMedia($ctx['instance_name'],(int)$messageId,$binary,$fileName,$mime);
            if($stored){
                $this->connect_api_chat_model->setLocalMediaPath($ctx['instance_name'],(int)$messageId,$stored['relative'],$mime,$fileName);
                $this->streamMediaFile($stored['absolute'],preg_replace('/\s*;.*$/','',$mime),$fileName,$download);
                return;
            }
            // Último recurso somente para a requisição atual, sem expor qualquer URL externa.
            $this->streamBinaryMedia($binary,preg_replace('/\s*;.*$/','',$mime),$fileName,$download);
        } catch(Throwable $e) {
            $this->connectapichatlogger->log('ERROR','media.stream',$e->getMessage(),['message_id'=>(int)$messageId]);
            show_error('Não foi possível carregar a mídia: '.$e->getMessage(),404);
        }
    }

    public function new_chat()
    {
        $this->requirePermission('send'); $this->requireAjax(); $this->requirePost();
        try {
            $ctx=$this->requireConnector(); $phone=$this->connectapichatconnectorbridge->normalizeNumber($this->input->post('phone')); $name=trim((string)$this->input->post('name'));
            if (strlen($phone)<10) throw new RuntimeException(_l('connect_api_chat_invalid_phone'));
            $id=$this->connect_api_chat_model->createManualConversation($ctx['instance_name'],$phone,$name,get_staff_user_id());
            connect_api_chat_json(['success'=>true,'conversation_id'=>$id,'csrf_hash'=>$this->security->get_csrf_hash()]);
        } catch(Throwable $e){$this->connectapichatlogger->log('ERROR','controller.error',$e->getMessage());connect_api_chat_json(['success'=>false,'message'=>$e->getMessage(),'csrf_hash'=>$this->security->get_csrf_hash()],400);}
    }

    public function assign()
    {
        $this->requirePermission('assign'); $this->requireAjax(); $this->requirePost();
        try {
            $ctx=$this->requireConnector(); $id=(int)$this->input->post('conversation_id'); $target=(int)$this->input->post('staff_id'); $note=trim((string)$this->input->post('note'));
            $c=$this->allowedConversation($ctx['instance_name'],$id,true); if ($target && !$this->connect_api_chat_model->isActiveStaff($target)) throw new RuntimeException(_l('connect_api_chat_invalid_staff'));
            if($target && !$this->staffCanAccessInstance($target,$ctx['instance_name'])) throw new RuntimeException('O usuário selecionado não possui acesso a esta instância.');
            if (!$this->connect_api_chat_model->assign($ctx['instance_name'],$id,$target?:null,get_staff_user_id(),$note)) throw new RuntimeException(_l('connect_api_chat_assignment_failed'));
            if ($target && $target!==(int)get_staff_user_id()) add_notification(['description'=>'WhatsApp: atendimento transferido para você','touserid'=>$target,'link'=>'connect_api_chat?instance='.rawurlencode($ctx['instance_name']).'&conversation_id='.(int)$id,'additional_data'=>serialize([$c['title']])]);
            connect_api_chat_json(['success'=>true,'csrf_hash'=>$this->security->get_csrf_hash()]);
        } catch(Throwable $e){$this->connectapichatlogger->log('ERROR','controller.error',$e->getMessage());connect_api_chat_json(['success'=>false,'message'=>$e->getMessage(),'csrf_hash'=>$this->security->get_csrf_hash()],400);}
    }

    public function delete_message()
    {
        $this->requirePermission('delete_message'); $this->requireAjax(); $this->requirePost();
        try {
            $ctx=$this->requireConnector(); $id=(int)$this->input->post('conversation_id'); $apiId=trim((string)$this->input->post('api_message_id')); $c=$this->allowedConversation($ctx['instance_name'],$id,true);
            $message=$this->connect_api_chat_model->message($ctx['instance_name'],$apiId); $deleteJid=$message['remote_jid']??$c['remote_jid'];
            $this->connectapichatconnectorbridge->deleteMessage($apiId,$deleteJid); $this->connect_api_chat_model->markMessageDeleted($ctx['instance_name'],$apiId);
            connect_api_chat_json(['success'=>true,'csrf_hash'=>$this->security->get_csrf_hash()]);
        } catch(Throwable $e){$this->connectapichatlogger->log('ERROR','controller.error',$e->getMessage());connect_api_chat_json(['success'=>false,'message'=>$e->getMessage(),'csrf_hash'=>$this->security->get_csrf_hash()],400);}
    }

    public function sync()
    {
        $this->requirePermission('view'); $this->requireAjax(); $this->requirePost();
        try {
            $ctx=$this->requireConnector(); $chats=$this->connectapichatconnectorbridge->normalizeList($this->connectapichatconnectorbridge->findChats()); $count=0;
            foreach($chats as $row){ $n=$this->connectapichatnormalizer->normalizeChat($row); if(!$n)continue; $cid=$this->connect_api_chat_model->saveContact($ctx['instance_name'],$n); $this->connect_api_chat_model->upsertConversation($ctx['instance_name'],$n['remote_jid'],['remote_jid_alt'=>$n['remote_jid_alt']??null,'contact_id'=>$cid,'title'=>$n['display_name'],'is_group'=>$n['is_group']??false]); if(!empty($n['last_message'])){$this->connect_api_chat_model->saveMessage($ctx['instance_name'],$n['last_message'],null);} $count++; }
            $merged=$this->connect_api_chat_model->consolidateAliases($ctx['instance_name']);
            connect_api_chat_json(['success'=>true,'count'=>$count,'merged'=>$merged,'csrf_hash'=>$this->security->get_csrf_hash()]);
        } catch(Throwable $e){$this->connectapichatlogger->log('ERROR','controller.error',$e->getMessage());connect_api_chat_json(['success'=>false,'message'=>$e->getMessage(),'csrf_hash'=>$this->security->get_csrf_hash()],400);}
    }

    public function sync_conversation()
    {
        $this->requirePermission('view'); $this->requireAjax(); $this->requirePost();
        try {
            $ctx=$this->requireConnector(); $id=(int)$this->input->post('conversation_id'); $c=$this->allowedConversation($ctx['instance_name'],$id,true);
            $count=$this->syncConversationJids($ctx['instance_name'],$c,80);
            connect_api_chat_json(['success'=>true,'count'=>$count,'csrf_hash'=>$this->security->get_csrf_hash()]);
        } catch(Throwable $e){$this->connectapichatlogger->log('ERROR','controller.error',$e->getMessage());connect_api_chat_json(['success'=>false,'message'=>$e->getMessage(),'csrf_hash'=>$this->security->get_csrf_hash()],400);}
    }

    private function chatViewData($pwaMode=false)
    {
        $allowed=$this->allowedInstanceContextsForStaff(get_staff_user_id());
        $ctx=null;$identity=['name'=>'WhatsApp','picture'=>null,'number'=>''];
        try{
            $ctx=$this->resolveInstanceContext(get_staff_user_id(),trim((string)$this->input->get('instance')),false);
            if($ctx && !empty($ctx['instance_name']))$this->ensureLocalAliasCleanup((string)$ctx['instance_name']);
            if($this->connectapichatconnectorbridge->configured())$identity=$this->connectapichatconnectorbridge->instanceIdentity();
        }catch(Throwable $e){$ctx=$this->connectapichatconnectorbridge->baseContext();}
        return [
            'title'=>(trim((string)($identity['name']??'')) ?: 'WhatsApp') . ' - Atendimento',
            'connector_available'=>$this->connectapichatconnectorbridge->available(),
            'connector_configured'=>$ctx&&!empty($ctx['base_url'])&&!empty($ctx['instance_name'])&&!empty($ctx['instance_token']),
            'connector'=>$ctx?:$this->connectapichatconnectorbridge->baseContext(),
            'available_instances'=>$allowed,
            'active_instance_name'=>$ctx['instance_name']??'',
            'brand_name'=>trim((string)($identity['name']??'')) ?: 'WhatsApp',
            'brand_picture'=>$identity['picture']??null,
            'brand_number'=>$identity['number']??($ctx['instance_number']??''),
            'brand_is_business'=>!empty($identity['is_business']),
            'staff'=>$this->connect_api_chat_model->staffList(),
            'current_staff_id'=>(int)get_staff_user_id(),
            'current_staff_name'=>get_staff_full_name(get_staff_user_id()),
            'can_view_all'=>connect_api_chat_can('view_all'),
            'can_assign'=>connect_api_chat_can('assign'),
            'can_send'=>connect_api_chat_can('send'),
            'can_delete'=>connect_api_chat_can('delete_message'),
            'can_close'=>connect_api_chat_can('close_conversation'),
            'can_manage_settings'=>connect_api_chat_can('manage_settings'),
            'poll_interval'=>max(2,min(15,(int)(get_option('connect_api_chat_poll_interval')?:3))),
            'realtime_poll_ms'=>max(500,min(5000,(int)(get_option('connect_api_chat_realtime_poll_ms')?:1000))),
            'api_sync_interval'=>max(60,min(900,(int)(get_option('connect_api_chat_api_sync_interval')?:300))),
            'csrf_name'=>$this->security->get_csrf_token_name(),
            'csrf_hash'=>$this->security->get_csrf_hash(),
            'initial_conversation_id'=>(int)$this->input->get('conversation_id'),
            'pwa_mode'=>(bool)$pwaMode,
            'pwa_enabled'=>get_option('connect_api_chat_pwa_enabled')!=='0',
            'pwa_url'=>admin_url('connect_api_chat/app'),
            'manifest_url'=>admin_url('connect_api_chat/manifest'),
            'service_worker_url'=>admin_url('connect_api_chat/service_worker'),
            'diagnostics_url'=>admin_url('connect_api_chat/diagnostics'),
            'settings_url'=>admin_url('connect_api_chat/settings'),
            'debug_enabled'=>$this->connectapichatlogger->enabled(),
        ];
    }

    private function ensureLocalAliasCleanup($instance)
    {
        $instance=trim((string)$instance);if($instance==='')return;
        $key='connect_api_chat_alias_cleanup_110_'.substr(sha1($instance),0,16);
        if(get_option($key)==='1')return;
        try{
            $merged=$this->connect_api_chat_model->consolidateAliases($instance);
            if(get_option($key)==='')add_option($key,'1');else update_option($key,'1');
            if($merged)$this->connectapichatlogger->log('INFO','identity.cleanup','Aliases LID/JID consolidados na atualização 1.1.2',['instance'=>$instance,'merged'=>$merged]);
        }catch(Throwable $e){
            $this->connectapichatlogger->log('WARNING','identity.cleanup',$e->getMessage(),['instance'=>$instance]);
        }
    }

    private function webhookHealthCheck()
    {
        $url=site_url('connect_api_chat/webhook/receive');
        if(!function_exists('curl_init'))return['name'=>'Endpoint público do webhook','ok'=>false,'detail'=>'Extensão cURL indisponível'];
        $ch=curl_init();$started=microtime(true);curl_setopt_array($ch,[CURLOPT_URL=>$url,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>10,CURLOPT_HTTPHEADER=>['Accept: application/json'],CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);$body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);$duration=(int)round((microtime(true)-$started)*1000);curl_close($ch);$decoded=json_decode((string)$body,true);$ok=$status===200&&is_array($decoded)&&($decoded['webhook']??'')==='ready';$detail=$ok?('OK · '.$duration.' ms'):('HTTP '.$status.($error!==''?' · '.$error:''));return['name'=>'Endpoint público do webhook','ok'=>$ok,'detail'=>$detail,'response'=>$decoded?:['raw'=>mb_substr((string)$body,0,500)]];
    }

    private function staffCanAccessInstance($staffId,$instanceName)
    {
        $staffId=(int)$staffId;$instanceName=(string)$instanceName;
        $names=$this->connect_api_chat_model->staffInstanceNames($staffId);
        if(!empty($names))return in_array($instanceName,$names,true);
        $base=(string)($this->connectapichatconnectorbridge->baseContext()['instance_name']??'');
        return $base!==''&&$base===$instanceName;
    }

    private function allowedInstanceContextsForStaff($staffId)
    {
        $all=$this->connectapichatconnectorbridge->availableContexts();
        if(empty($all))return[];
        if(get_option('connect_api_chat_multi_instance_enabled')==='0')return[$this->connectapichatconnectorbridge->baseContext()];
        if(is_admin()||connect_api_chat_can('manage_settings'))return$all;
        $names=$this->connect_api_chat_model->staffInstanceNames((int)$staffId);
        if(empty($names)){
            $base=$this->connectapichatconnectorbridge->baseContext();
            return !empty($base['instance_name'])?[$base]:[];
        }
        $result=[];foreach($all as $context){if(in_array((string)($context['instance_name']??''),$names,true))$result[]=$context;}
        return$result;
    }

    private function resolveInstanceContext($staffId,$requested=null,$persist=false)
    {
        $allowed=$this->allowedInstanceContextsForStaff((int)$staffId);
        if(empty($allowed))throw new RuntimeException(_l('connect_api_chat_connector_not_configured'));
        $byName=[];foreach($allowed as $context){$name=(string)($context['instance_name']??'');if($name!=='')$byName[$name]=$context;}
        $selected=trim((string)$requested);
        if($selected===''&&isset($this->session))$selected=trim((string)$this->session->userdata('connect_api_chat_active_instance'));
        if($selected===''||!isset($byName[$selected])){
            $preferred=$this->connect_api_chat_model->staffDefaultInstance((int)$staffId);
            if($preferred!==''&&isset($byName[$preferred]))$selected=$preferred;
        }
        if($selected===''||!isset($byName[$selected])){
            $baseName=(string)($this->connectapichatconnectorbridge->baseContext()['instance_name']??'');
            $selected=isset($byName[$baseName])?$baseName:(string)array_key_first($byName);
        }
        if(!isset($byName[$selected]))throw new RuntimeException('Instância não autorizada para este usuário.');
        if(isset($this->session)&&($persist||$this->session->userdata('connect_api_chat_active_instance')!==$selected))$this->session->set_userdata('connect_api_chat_active_instance',$selected);
        $this->connectapichatconnectorbridge->useContext($byName[$selected]);
        return$byName[$selected];
    }

    private function requireConnector()
    {
        $ctx=$this->resolveInstanceContext(get_staff_user_id(),null,false);
        if(!$this->connectapichatconnectorbridge->configured())throw new RuntimeException(_l('connect_api_chat_connector_not_configured'));
        return$ctx;
    }
    private function allowedConversation($instance,$id,$allowOther=false){ $c=$this->connect_api_chat_model->conversation($instance,$id); if(!$c)throw new RuntimeException(_l('connect_api_chat_conversation_not_found')); $assigned=(int)($c['assigned_staff_id']??0); if($assigned && $assigned!==(int)get_staff_user_id()&&!connect_api_chat_can('view_all'))throw new RuntimeException(_l('connect_api_chat_assigned_to_other')); return $c; }
    private function claimIfNeeded($instance,$c){ if(empty($c['assigned_staff_id'])&&get_option('connect_api_chat_auto_claim_on_send')!=='0')$this->connect_api_chat_model->assign($instance,$c['id'],get_staff_user_id(),get_staff_user_id(),'Autoatribuição ao enviar'); }
    private function numberForConversation($c)
    {
        $jid=(string)($c['remote_jid']??'');
        if(strpos($jid,'@g.us')!==false)return $jid;
        $phone=preg_replace('/\D+/','',(string)($c['phone_number']??''));
        if($phone!=='')return $phone;
        $alt=(string)($c['remote_jid_alt']??'');
        if(strpos($alt,'@s.whatsapp.net')!==false){$n=preg_replace('/\D+/','',preg_replace('/@.*$/','',$alt));if($n!=='')return$n;}
        if(strpos($jid,'@s.whatsapp.net')!==false){$n=preg_replace('/\D+/','',preg_replace('/@.*$/','',$jid));if($n!=='')return$n;}
        if(strpos($jid,'@lid')!==false)return $jid;
        throw new RuntimeException(_l('connect_api_chat_invalid_phone'));
    }

    /**
     * Reprocessa payloads inbound já armazenados localmente pelo webhook.
     * Não consulta o Connect|API. A fila é a proteção contra falhas intermediárias
     * de ticket/notificação e elimina a espera de ~40s do watchdog remoto.
     */
    private function processPendingInboundQueue($instance,$limit=20)
    {
        $processed=0;
        foreach($this->connect_api_chat_model->pendingInboundPayloads((string)$instance,$limit) as$q){
            $qid=(int)($q['id']??0);
            try{
                $payload=json_decode((string)($q['payload']??''),true);
                if(!is_array($payload))throw new RuntimeException('Payload inbound local inválido.');
                $event=$this->connectapichatnormalizer->eventName($payload['event']??($q['event_name']??''));
                if(!in_array($event,['MESSAGES_UPSERT','MESSAGES_SET'],true)){
                    $this->connect_api_chat_model->markInboundPayloadProcessed($qid);continue;
                }
                $ok=0;
                foreach($this->inboundMessageRecords($payload['data']??null) as$record){
                    $n=$this->connectapichatnormalizer->normalizeMessage($record);
                    if(!$n)continue;
                    $id=$this->connect_api_chat_model->saveMessage((string)$instance,$n,null,true);
                    if(!$id)continue;
                    $ok++;
                    if(empty($n['from_me'])){
                        update_option('connect_api_chat_last_inbound_at',date('Y-m-d H:i:s'));
                        update_option('connect_api_chat_last_inbound_message_id',(string)($n['api_message_id']??''));
                    }
                }
                if($ok===0)throw new RuntimeException('Fila inbound não produziu mensagem válida.');
                $this->connect_api_chat_model->repairLiveInboundTickets((string)$instance,10);
                $this->connect_api_chat_model->markInboundPayloadProcessed($qid);
                $processed+=$ok;
            }catch(Throwable$e){
                $this->connect_api_chat_model->markInboundPayloadFailed($qid,$e->getMessage());
                $this->connectapichatlogger->log('WARNING','inbound.queue',$e->getMessage(),['queue_id'=>$qid,'instance'=>$instance]);
            }
        }
        return$processed;
    }

    private function inboundMessageRecords($data)
    {
        if(!is_array($data))return[];
        foreach(['messages','records','rows','data'] as$k){
            if(isset($data[$k])&&is_array($data[$k])&&!isset($data['key'])){
                $candidate=$data[$k];$keys=array_keys($candidate);$isList=$candidate===[]||$keys===range(0,count($candidate)-1);
                if($isList)return$candidate;
            }
        }
        $keys=array_keys($data);$isList=$data===[]||$keys===range(0,count($data)-1);
        return$isList?$data:[$data];
    }

    private function syncConversationJids($instance,$conversation,$limit=80)
    {
        $jids=[];
        foreach([$conversation['remote_jid']??'', $conversation['remote_jid_alt']??''] as $jid){$jid=trim((string)$jid);if($jid!==''&&!in_array($jid,$jids,true))$jids[]=$jid;}
        $count=0;
        foreach($jids as $jid){
            try{
                $records=$this->connectapichatconnectorbridge->normalizeList($this->connectapichatconnectorbridge->findMessages($jid,1,$limit));
                foreach($records as $r){$n=$this->connectapichatnormalizer->normalizeMessage($r);if($n){$this->connect_api_chat_model->saveMessage($instance,$n,null);$count++;}}
            }catch(Throwable $e){$this->connectapichatlogger->log('WARNING','controller.sync.jid',$e->getMessage(),['jid'=>$jid]);}
        }
        return$count;
    }

    private function decorateMediaRows($rows)
    {
        foreach(is_array($rows)?$rows:[] as &$row){
            $type=(string)($row['message_type']??'');
            if(in_array($type,['image','video','audio','document','sticker'],true) && !empty($row['id'])){
                // Nunca exponha a URL criptografada mmg.whatsapp.net ao navegador.
                // Toda mídia passa pelo proxy autenticado do Chat.
                $row['media_url']=admin_url('connect_api_chat/media/'.(int)$row['id']);
                $row['media_download_url']=admin_url('connect_api_chat/media/'.(int)$row['id'].'?download=1');
                $row['media_cached']=!empty($row['local_media_path'])?1:0;
                $row['media_lazy']=empty($row['local_media_path'])?1:0;
            }
            // raw_payload pode conter mediaKey/directPath e é grande; nunca deve ir para o browser.
            unset($row['raw_payload']);
        }
        return is_array($rows)?$rows:[];
    }

    private function quotedPayload($instance,$apiMessageId)
    {
        $apiMessageId=trim((string)$apiMessageId); if($apiMessageId==='')return null;
        $row=$this->connect_api_chat_model->message($instance,$apiMessageId); if(!$row)return ['key'=>['id'=>$apiMessageId],'message'=>['conversation'=>'Mensagem respondida']];
        $raw=json_decode((string)($row['raw_payload']??''),true); if(isset($raw['data'])&&is_array($raw['data'])&&isset($raw['data']['key']))$raw=$raw['data'];
        $key=is_array($raw)&&isset($raw['key'])&&is_array($raw['key'])?$raw['key']:[]; $message=is_array($raw)&&isset($raw['message'])&&is_array($raw['message'])?$raw['message']:[];
        $key['id']=$apiMessageId; if(empty($key['remoteJid']))$key['remoteJid']=$row['remote_jid']??null; if(!array_key_exists('fromMe',$key))$key['fromMe']=!empty($row['from_me']);
        if(empty($message)){
            $text=(string)($row['text_content']??$row['caption']??'Mensagem respondida'); $message=['conversation'=>$text];
        }
        return ['key'=>$key,'message'=>$message];
    }

    private function mediaDirectory($instance)
    {
        $safe=preg_replace('/[^A-Za-z0-9._-]+/','-',(string)$instance); $dir=rtrim(FCPATH,'/\\').DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'connect_api_chat'.DIRECTORY_SEPARATOR.$safe;
        if(!is_dir($dir)&&!@mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('Não foi possível criar o diretório de mídia do Chat.');
        return $dir;
    }

    private function extensionForMedia($fileName,$mime)
    {
        $ext=strtolower(pathinfo((string)$fileName,PATHINFO_EXTENSION)); if($ext!==''&&preg_match('/^[a-z0-9]{1,8}$/',$ext))return $ext;
        $map=['audio/ogg'=>'ogg','audio/webm'=>'webm','audio/mpeg'=>'mp3','audio/mp4'=>'m4a','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','video/mp4'=>'mp4','application/pdf'=>'pdf'];
        return $map[strtolower((string)$mime)]??'bin';
    }

    private function persistMediaFile($instance,$messageId,$source,$fileName,$mime)
    {
        if(!is_file($source))return null; $dir=$this->mediaDirectory($instance); $ext=$this->extensionForMedia($fileName,$mime); $target=$dir.DIRECTORY_SEPARATOR.'msg-'.(int)$messageId.'-'.$this->safeRandom(5).'.'.$ext;
        if(!@copy($source,$target))return null; $relative=$this->relativeMediaPath($target); return ['absolute'=>$target,'relative'=>$relative];
    }

    private function persistBinaryMedia($instance,$messageId,$binary,$fileName,$mime)
    {
        $dir=$this->mediaDirectory($instance); $ext=$this->extensionForMedia($fileName,$mime); $target=$dir.DIRECTORY_SEPARATOR.'msg-'.(int)$messageId.'-'.$this->safeRandom(5).'.'.$ext;
        if(@file_put_contents($target,$binary,LOCK_EX)===false)return null; return ['absolute'=>$target,'relative'=>$this->relativeMediaPath($target)];
    }

    private function safeRandom($bytes=5){try{return bin2hex(random_bytes($bytes));}catch(Throwable $e){return substr(sha1(uniqid('',true)),0,$bytes*2);}}
    private function relativeMediaPath($absolute){$base=rtrim(FCPATH,'/\\').DIRECTORY_SEPARATOR;return str_replace(DIRECTORY_SEPARATOR,'/',substr($absolute,strlen($base)));}
    private function absoluteMediaPath($relative){$relative=ltrim(str_replace(['../','..\\'],'',(string)$relative),'/\\');if($relative==='')return null;$path=rtrim(FCPATH,'/\\').DIRECTORY_SEPARATOR.str_replace(['/', '\\'],DIRECTORY_SEPARATOR,$relative);$root=realpath(rtrim(FCPATH,'/\\').DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'connect_api_chat');$real=realpath($path);if(!$root||!$real||strpos($real,$root)!==0)return null;return$real;}

    private function streamBinaryMedia($binary,$mime,$fileName,$download=false)
    {
        $binary=(string)$binary;$mime=$mime?:'application/octet-stream';$fileName=$fileName?:'media';
        while(ob_get_level()>0)@ob_end_clean();
        header('Content-Type: '.$mime);
        header('Content-Length: '.strlen($binary));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=300, no-transform');
        header('Accept-Ranges: none');
        $disp=$download?'attachment':'inline';
        header('Content-Disposition: '.$disp.'; filename="'.str_replace('"','',basename($fileName)).'"');
        echo $binary;
        exit;
    }

    private function streamMediaFile($path,$mime,$fileName,$download=false)
    {
        while(ob_get_level()>0)@ob_end_clean(); $size=filesize($path); $start=0; $end=$size-1; $status=200;
        if(isset($_SERVER['HTTP_RANGE'])&&preg_match('/bytes=(\d*)-(\d*)/',$_SERVER['HTTP_RANGE'],$m)){
            if($m[1]!=='')$start=max(0,(int)$m[1]); if($m[2]!=='')$end=min($end,(int)$m[2]); if($start<=$end)$status=206;
        }
        http_response_code($status); header('Content-Type: '.$mime); header('Accept-Ranges: bytes'); header('Cache-Control: private, max-age=86400'); header('X-Content-Type-Options: nosniff');
        $disp=$download?'attachment':'inline'; header('Content-Disposition: '.$disp.'; filename="'.str_replace('"','',basename($fileName)).'"');
        $length=$end-$start+1; header('Content-Length: '.$length); if($status===206)header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);
        $fp=fopen($path,'rb'); fseek($fp,$start); $remaining=$length; while($remaining>0&&!feof($fp)){ $chunk=fread($fp,min(8192,$remaining)); if($chunk===false)break; echo$chunk; $remaining-=strlen($chunk); flush(); } fclose($fp); exit;
    }

    private function apiMessageId($api){return $api['key']['id']??$api['data']['key']['id']??$api['id']??$api['data']['id']??null;}
    private function requirePost(){if(strtoupper((string)$this->input->method(true))!=='POST')show_error('Método não permitido',405);}
    private function requireAjax(){if(!$this->input->is_ajax_request())show_404();}
    private function requirePermission($cap){if(!connect_api_chat_can($cap))access_denied(_l('connect_api_chat_title'));}
}
