<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Connect_api_chat_model extends App_Model
{
    private $contacts;
    private $conversations;
    private $messages;
    private $reads;
    private $assignments;
    private $events;
    private $staffInstances;
    private $tickets;
    private $realtimeEvents;
    private $inboundQueue;

    public function __construct()
    {
        parent::__construct();
        $p = db_prefix();
        $this->contacts = $p . 'connect_api_chat_contacts';
        $this->conversations = $p . 'connect_api_chat_conversations';
        $this->messages = $p . 'connect_api_chat_messages';
        $this->reads = $p . 'connect_api_chat_reads';
        $this->assignments = $p . 'connect_api_chat_assignments';
        $this->events = $p . 'connect_api_chat_webhook_events';
        $this->staffInstances = $p . 'connect_api_chat_staff_instances';
        $this->tickets = $p . 'connect_api_chat_tickets';
        $this->realtimeEvents = $p . 'connect_api_chat_realtime_events';
        $this->inboundQueue = $p . 'connect_api_chat_inbound_queue';
    }

    public function saveContact($instance, $data)
    {
        $jid = trim((string)($data['remote_jid'] ?? ''));
        if ($jid === '') return null;

        $alt = trim((string)($data['remote_jid_alt'] ?? ''));
        $isGroup = !empty($data['is_group']) || strpos($jid, '@g.us') !== false;
        $phone = preg_replace('/\D+/', '', (string)($data['phone_number'] ?? ''));
        if ($phone === '') $phone = $this->phoneFromJids($jid, $alt);
        $identity = $this->identityKey($jid, $alt, $phone, $isGroup);

        $row = $this->findContactRow($instance, $jid, $alt, $identity);
        $payload = [
            'phone_number' => $phone ?: null,
            'display_name' => ($data['display_name'] ?? null) ?: ($row['display_name'] ?? null),
            'profile_pic_url' => ($data['profile_pic_url'] ?? null) ?: ($row['profile_pic_url'] ?? null),
            'is_group' => $isGroup ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($this->db->field_exists('identity_key', $this->contacts)) $payload['identity_key'] = $identity;
        if ($this->db->field_exists('remote_jid_alt', $this->contacts)) {
            $payload['remote_jid_alt'] = $this->mergeAltJid($row['remote_jid'] ?? $jid, $row['remote_jid_alt'] ?? '', $jid, $alt);
        }

        if ($row) {
            $this->db->where('id', $row['id'])->update($this->contacts, $payload);
            return (int)$row['id'];
        }

        $payload['instance_name'] = $instance;
        $payload['remote_jid'] = $jid;
        $payload['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->contacts, $payload);
        return (int)$this->db->insert_id();
    }

    public function upsertConversation($instance, $jid, $details = [])
    {
        $jid = trim((string)$jid);
        $alt = trim((string)($details['remote_jid_alt'] ?? ''));
        $isGroup = !empty($details['is_group']) || strpos($jid, '@g.us') !== false;
        $phone = preg_replace('/\D+/', '', (string)($details['phone_number'] ?? ''));
        if ($phone === '') $phone = $this->phoneFromJids($jid, $alt);
        $identity = $this->identityKey($jid, $alt, $phone, $isGroup);

        $row = $this->findConversationRow($instance, $jid, $alt, $identity);
        $contactId = $details['contact_id'] ?? null;
        $payload = [
            'contact_id' => $contactId ?: ($row['contact_id'] ?? null),
            'title' => ($details['title'] ?? null) ?: ($row['title'] ?? null) ?: ($phone ?: $jid),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        foreach (['assigned_staff_id', 'status', 'last_message_preview', 'last_message_from_me', 'created_by_staff_id'] as $k) {
            if (array_key_exists($k, $details)) $payload[$k] = $details[$k];
        }
        if (array_key_exists('last_message_at', $details)) $payload['last_message_at'] = $details['last_message_at'];
        if ($this->db->field_exists('identity_key', $this->conversations)) $payload['identity_key'] = $identity;
        if ($this->db->field_exists('remote_jid_alt', $this->conversations)) {
            $payload['remote_jid_alt'] = $this->mergeAltJid($row['remote_jid'] ?? $jid, $row['remote_jid_alt'] ?? '', $jid, $alt);
        }

        if ($row) {
            $this->db->where('id', $row['id'])->update($this->conversations, $payload);
            return (int)$row['id'];
        }

        $payload['instance_name'] = $instance;
        $payload['remote_jid'] = $jid;
        $payload['status'] = $payload['status'] ?? 'open';
        $payload['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->conversations, $payload);
        return (int)$this->db->insert_id();
    }

    public function conversationByRemoteJid($instance, $jid)
    {
        $jid = trim((string)$jid);
        if ($jid === '') return null;
        return $this->db->where(['instance_name'=>(string)$instance,'remote_jid'=>$jid])->limit(1)->get($this->conversations)->row_array();
    }

    public function currentTicket($instance, $conversationId)
    {
        if (!$this->db->table_exists($this->tickets)) return null;
        $c = $this->db->select('current_ticket_id')->where(['id'=>(int)$conversationId,'instance_name'=>(string)$instance])->get($this->conversations)->row_array();
        if (!$c || empty($c['current_ticket_id'])) return null;
        return $this->db->where(['id'=>(int)$c['current_ticket_id'],'conversation_id'=>(int)$conversationId,'instance_name'=>(string)$instance])->get($this->tickets)->row_array();
    }

    public function ticket($instance, $ticketId)
    {
        if (!$this->db->table_exists($this->tickets)) return null;
        return $this->db->where(['id'=>(int)$ticketId,'instance_name'=>(string)$instance])->get($this->tickets)->row_array();
    }

    public function createTicket($instance, $conversationId, $via = 'inbound', $staffId = null, $timestamp = null, $isNew = true, $sourceApiMessageId = null)
    {
        if (!$this->db->table_exists($this->tickets)) return null;
        $c = $this->conversation($instance, $conversationId);
        if (!$c) return null;
        $number = max((int)($c['ticket_counter'] ?? 0), (int)$this->db->select_max('ticket_number','n')->where('conversation_id',(int)$conversationId)->get($this->tickets)->row()->n) + 1;
        $openedAt = date('Y-m-d H:i:s', $timestamp ? (int)$timestamp : time());
        $this->db->trans_start();
        $ticketPayload = [
            'instance_name'=>(string)$instance,
            'conversation_id'=>(int)$conversationId,
            'ticket_number'=>$number,
            'status'=>'open',
            'opened_via'=>(string)$via,
            'opened_by_staff_id'=>$staffId ? (int)$staffId : null,
            'opened_at'=>$openedAt,
            'closed_at'=>null,
            'closed_by_staff_id'=>null,
            'is_new'=>$isNew ? 1 : 0,
            'created_at'=>$openedAt,
            'updated_at'=>date('Y-m-d H:i:s'),
        ];
        if ($this->db->field_exists('source_api_message_id',$this->tickets)) $ticketPayload['source_api_message_id']=$sourceApiMessageId ? (string)$sourceApiMessageId : null;
        $this->db->insert($this->tickets, $ticketPayload);
        $ticketId=(int)$this->db->insert_id();
        $this->db->where(['id'=>(int)$conversationId,'instance_name'=>(string)$instance])->update($this->conversations,[
            'current_ticket_id'=>$ticketId,
            'ticket_counter'=>$number,
            'new_ticket_flag'=>$isNew ? 1 : 0,
            'status'=>'open',
            'closed_at'=>null,
            'closed_by_staff_id'=>null,
            'assigned_staff_id'=>$via==='inbound' ? null : ($staffId ? (int)$staffId : ($c['assigned_staff_id'] ?: null)),
            'updated_at'=>date('Y-m-d H:i:s'),
        ]);
        if ($this->db->field_exists('ticket_id',$this->assignments)) {
            $this->db->insert($this->assignments,[
                'conversation_id'=>(int)$conversationId,
                'ticket_id'=>$ticketId,
                'from_staff_id'=>$c['assigned_staff_id'] ? (int)$c['assigned_staff_id'] : null,
                'to_staff_id'=>$via==='inbound' ? null : ($staffId ? (int)$staffId : null),
                'action'=>'new_ticket',
                'note'=>'Novo atendimento #'.$number.' iniciado via '.$via.'.',
                'created_by_staff_id'=>$staffId ? (int)$staffId : null,
                'created_at'=>date('Y-m-d H:i:s'),
            ]);
        }
        $this->db->trans_complete();
        if (!$this->db->trans_status()) return null;
        $ticket=$this->ticket($instance,$ticketId);
        $this->emitRealtimeEvent($instance,'ticket.new',$conversationId,null,$ticketId);
        return $ticket;
    }

    private function ticketForNewMessage($instance, $conversationId, $fromMe, $timestamp, $staffId = null, $liveEvent = false, $apiMessageId = null)
    {
        if (!$this->db->table_exists($this->tickets)) return null;
        $timestamp = (int)$timestamp ?: time();
        $at = date('Y-m-d H:i:s', $timestamp);
        $apiMessageId=trim((string)$apiMessageId);

        // Idempotência por mensagem: um retry tardio do webhook nunca cria outro ticket.
        if ($liveEvent && !$fromMe && $apiMessageId!=='' && $this->db->field_exists('source_api_message_id',$this->tickets)) {
            $existingSource=$this->db->where([
                'instance_name'=>(string)$instance,
                'conversation_id'=>(int)$conversationId,
                'source_api_message_id'=>$apiMessageId,
            ])->limit(1)->get($this->tickets)->row_array();
            if ($existingSource) return $existingSource;
        }

        $current = $this->currentTicket($instance, $conversationId);

        // Caminho autoritativo do webhook: uma mensagem inbound NOVA em atendimento fechado
        // cria um NOVO ticket, independentemente do timestamp ou de a mensagem já ter sido
        // pré-sincronizada no banco local. É o mesmo princípio do FindOrCreateTicket do projeto
        // Baileys de referência: fechado não absorve nova demanda.
        if ($liveEvent && !$fromMe) {
            if (!$current || (string)($current['status'] ?? 'open') === 'closed') {
                return $this->createTicket($instance, $conversationId, 'inbound', null, $timestamp, true, $apiMessageId ?: null);
            }
            return $current;
        }

        if ($current) {
            $status = (string)($current['status'] ?? 'open');
            $openedTs = !empty($current['opened_at']) ? strtotime((string)$current['opened_at']) : 0;
            $closedTs = !empty($current['closed_at']) ? strtotime((string)$current['closed_at']) : 0;
            if (!$fromMe && $status === 'closed' && ($closedTs === 0 || $timestamp >= $closedTs)) {
                return $this->createTicket($instance, $conversationId, 'inbound', null, $timestamp, true, $apiMessageId ?: null);
            }
            if ($status === 'open' && ($openedTs === 0 || $timestamp >= $openedTs)) return $current;
        }

        // Backfill/sincronização: mensagens históricas permanecem no ticket temporal correto.
        $this->db->where(['instance_name'=>(string)$instance,'conversation_id'=>(int)$conversationId]);
        $this->db->where('opened_at <=', $at);
        $this->db->group_start()->where('closed_at IS NULL', null, false)->or_where('closed_at >=', $at)->group_end();
        $historical = $this->db->order_by('ticket_number','DESC')->limit(1)->get($this->tickets)->row_array();
        if ($historical) return $historical;
        if ($current) return $current;
        return $this->createTicket($instance, $conversationId, $fromMe ? 'outbound' : 'inbound', $staffId, $timestamp, !$fromMe, (!$fromMe?$apiMessageId:null));
    }

    public function acknowledgeCurrentTicket($instance,$conversationId)
    {
        $ticket=$this->currentTicket($instance,$conversationId);
        if($ticket && !empty($ticket['is_new'])) $this->db->where('id',(int)$ticket['id'])->update($this->tickets,['is_new'=>0,'updated_at'=>date('Y-m-d H:i:s')]);
        $this->db->where(['id'=>(int)$conversationId,'instance_name'=>(string)$instance])->update($this->conversations,['new_ticket_flag'=>0,'updated_at'=>date('Y-m-d H:i:s')]);
        return true;
    }

    public function ticketHistory($instance,$conversationId)
    {
        if (!$this->db->table_exists($this->tickets)) return [];
        return $this->db->where(['instance_name'=>(string)$instance,'conversation_id'=>(int)$conversationId])->order_by('ticket_number','DESC')->get($this->tickets)->result_array();
    }

    /**
     * Fila durável de entrada. O webhook grava o payload antes de executar qualquer
     * regra de negócio. Se o processamento imediato falhar, a PWA/CRM reprocessa
     * localmente em até ~1s sem consultar novamente o Connect|API.
     */
    public function enqueueInboundPayload($instance, $eventName, $rawPayload)
    {
        if (!$this->db->table_exists($this->inboundQueue)) return 0;
        $instance = (string)$instance;
        $eventName = (string)$eventName;
        $rawPayload = (string)$rawPayload;
        $key = hash('sha256', $instance . '|' . $eventName . '|' . $rawPayload);
        $row = $this->db->where('event_key', $key)->limit(1)->get($this->inboundQueue)->row_array();
        if ($row) {
            if ((string)($row['status'] ?? '') !== 'processed') {
                $this->db->where('id', (int)$row['id'])->update($this->inboundQueue, [
                    'status' => 'pending',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
            return (int)$row['id'];
        }
        $now = date('Y-m-d H:i:s');
        $this->db->insert($this->inboundQueue, [
            'event_key' => $key,
            'instance_name' => $instance,
            'event_name' => $eventName,
            'payload' => $rawPayload,
            'status' => 'pending',
            'attempts' => 0,
            'last_error' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'processed_at' => null,
        ]);
        return (int)$this->db->insert_id();
    }

    public function pendingInboundPayloads($instance, $limit = 20)
    {
        if (!$this->db->table_exists($this->inboundQueue)) return [];
        return $this->db
            ->where('instance_name', (string)$instance)
            ->where_in('status', ['pending','failed'])
            ->order_by('id', 'ASC')
            ->limit(max(1, min(50, (int)$limit)))
            ->get($this->inboundQueue)
            ->result_array();
    }

    public function inboundQueueStats($instance)
    {
        if (!$this->db->table_exists($this->inboundQueue)) return ['pending'=>0,'failed'=>0,'processed'=>0];
        $rows=$this->db->select('status,COUNT(*) AS qty',false)->where('instance_name',(string)$instance)->group_by('status')->get($this->inboundQueue)->result_array();
        $out=['pending'=>0,'failed'=>0,'processed'=>0];
        foreach($rows as$r){$k=(string)($r['status']??'');if(array_key_exists($k,$out))$out[$k]=(int)($r['qty']??0);}
        return$out;
    }

    public function markInboundPayloadProcessed($queueId)
    {
        if (!$this->db->table_exists($this->inboundQueue) || (int)$queueId <= 0) return false;
        $now = date('Y-m-d H:i:s');
        $this->db->where('id', (int)$queueId)->update($this->inboundQueue, [
            'status' => 'processed',
            'processed_at' => $now,
            'last_error' => null,
            'updated_at' => $now,
        ]);
        return $this->db->affected_rows() >= 0;
    }

    public function markInboundPayloadFailed($queueId, $error)
    {
        if (!$this->db->table_exists($this->inboundQueue) || (int)$queueId <= 0) return false;
        $row = $this->db->select('attempts')->where('id', (int)$queueId)->get($this->inboundQueue)->row_array();
        $this->db->where('id', (int)$queueId)->update($this->inboundQueue, [
            'status' => 'failed',
            'attempts' => (int)($row['attempts'] ?? 0) + 1,
            'last_error' => mb_substr((string)$error, 0, 1000),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->db->affected_rows() >= 0;
    }

    public function emitRealtimeEvent($instance, $eventType, $conversationId = null, $messageId = null, $ticketId = null)
    {
        if (!$this->db->table_exists($this->realtimeEvents)) return 0;
        $this->db->insert($this->realtimeEvents, [
            'instance_name'=>(string)$instance,
            'conversation_id'=>$conversationId ? (int)$conversationId : null,
            'ticket_id'=>$ticketId ? (int)$ticketId : null,
            'message_id'=>$messageId ? (int)$messageId : null,
            'event_type'=>mb_substr((string)$eventType,0,64),
            'created_at'=>date('Y-m-d H:i:s'),
        ]);
        $id=(int)$this->db->insert_id();
        // Retenção barata: não faz COUNT(*) a cada evento.
        if ($id > 5000 && ($id % 250) === 0) {
            $this->db->where('id <', $id - 5000)->delete($this->realtimeEvents);
        }
        return $id;
    }

    public function realtimeCursor($instance)
    {
        if (!$this->db->table_exists($this->realtimeEvents)) return 0;
        $row=$this->db->select_max('id','max_id')->where('instance_name',(string)$instance)->get($this->realtimeEvents)->row_array();
        return (int)($row['max_id']??0);
    }

    public function realtimeEvents($instance, $afterId, $limit = 100)
    {
        if (!$this->db->table_exists($this->realtimeEvents)) return [];
        return $this->db->select('id,conversation_id,ticket_id,message_id,event_type,created_at')
            ->where('instance_name',(string)$instance)
            ->where('id >',(int)$afterId)
            ->order_by('id','ASC')
            ->limit(max(1,min(250,(int)$limit)))
            ->get($this->realtimeEvents)->result_array();
    }

    public function saveMessage($instance, $message, $staffId = null, $liveEvent = false)
    {
        $jid = (string)($message['remote_jid'] ?? '');
        if ($jid === '') return null;
        $alt = trim((string)($message['remote_jid_alt'] ?? ''));
        $isGroup = strpos($jid, '@g.us') !== false;
        $phone = $this->phoneFromJids($jid, $alt);
        $contactName = empty($message['from_me']) && !$isGroup ? ($message['sender_name'] ?? null) : null;

        // 1. Identidade/conversa primeiro. Nenhuma regra de ticket pode impedir o
        // recebimento/persistência da mensagem.
        $contactId = $this->saveContact($instance, [
            'remote_jid' => $jid,
            'remote_jid_alt' => $alt,
            'phone_number' => $phone,
            'display_name' => $contactName,
            'profile_pic_url' => $message['profile_pic_url'] ?? null,
            'is_group' => $isGroup,
        ]);
        $conversationId = $this->upsertConversation($instance, $jid, [
            'remote_jid_alt' => $alt,
            'phone_number' => $phone,
            'is_group' => $isGroup,
            'contact_id' => $contactId,
            'title' => $contactName ?: null,
        ]);
        if (!$conversationId) return null;

        $apiId = trim((string)($message['api_message_id'] ?? ''));
        if ($apiId === '') return null;

        $existing = $this->db
            ->where(['instance_name' => $instance, 'api_message_id' => $apiId])
            ->limit(1)
            ->get($this->messages)
            ->row_array();

        // 2. Para evento live inbound, NÃO cria ticket antes de salvar a mensagem.
        // Esse desacoplamento é deliberado: uma inconsistência de atendimento jamais
        // pode transformar o Chat em "envia mas não recebe".
        $ticketId = $existing['ticket_id'] ?? null;
        if (!$ticketId && $this->db->table_exists($this->tickets) && !$liveEvent) {
            $ticket = $this->ticketForNewMessage(
                $instance,
                $conversationId,
                !empty($message['from_me']),
                (int)($message['message_timestamp'] ?? time()),
                $staffId,
                false,
                $apiId
            );
            $ticketId = $ticket['id'] ?? null;
        } elseif (!$ticketId && $this->db->table_exists($this->tickets) && $liveEvent) {
            // Se já existe ticket aberto, podemos associá-lo sem criar nada.
            $current = $this->currentTicket($instance, $conversationId);
            if ($current && (string)($current['status'] ?? '') === 'open') {
                $ticketId = (int)$current['id'];
            }
        }

        $now = date('Y-m-d H:i:s');
        $payload = [
            'conversation_id' => $conversationId,
            'remote_jid' => $jid,
            'participant_jid' => $message['participant_jid'] ?? null,
            'from_me' => !empty($message['from_me']) ? 1 : 0,
            'sender_staff_id' => $staffId ?: ($existing['sender_staff_id'] ?? null),
            'sender_name' => $message['sender_name'] ?? ($existing['sender_name'] ?? null),
            'message_type' => $message['message_type'] ?? 'text',
            'text_content' => $message['text_content'] ?? null,
            'media_url' => $message['media_url'] ?? null,
            'caption' => $message['caption'] ?? null,
            'file_name' => $message['file_name'] ?? null,
            'mimetype' => $message['mimetype'] ?? null,
            'message_timestamp' => (int)($message['message_timestamp'] ?? time()),
            'status' => $message['status'] ?? null,
            'quoted_api_message_id' => $message['quoted_api_message_id'] ?? null,
            'raw_payload' => $message['raw_payload'] ?? null,
            'updated_at' => $now,
        ];
        if ($this->db->field_exists('ticket_id', $this->messages)) $payload['ticket_id'] = $ticketId ?: null;
        if ($this->db->field_exists('remote_jid_alt', $this->messages)) $payload['remote_jid_alt'] = $alt ?: null;
        if ($this->db->field_exists('thumbnail_base64', $this->messages)) $payload['thumbnail_base64'] = $message['thumbnail_base64'] ?? ($existing['thumbnail_base64'] ?? null);
        if ($this->db->field_exists('media_duration', $this->messages)) $payload['media_duration'] = $message['media_duration'] ?? ($existing['media_duration'] ?? null);
        if ($this->db->field_exists('media_waveform', $this->messages)) $payload['media_waveform'] = $message['media_waveform'] ?? ($existing['media_waveform'] ?? null);
        if ($this->db->field_exists('media_width', $this->messages)) $payload['media_width'] = $message['media_width'] ?? ($existing['media_width'] ?? null);
        if ($this->db->field_exists('media_height', $this->messages)) $payload['media_height'] = $message['media_height'] ?? ($existing['media_height'] ?? null);
        if ($this->db->field_exists('local_media_path', $this->messages)) $payload['local_media_path'] = $message['local_media_path'] ?? ($existing['local_media_path'] ?? null);
        if ($this->db->field_exists('live_received_at', $this->messages)) {
            if ($liveEvent && empty($message['from_me'])) {
                $payload['live_received_at'] = $existing['live_received_at'] ?? $now;
            } elseif ($existing && array_key_exists('live_received_at', $existing)) {
                $payload['live_received_at'] = $existing['live_received_at'];
            }
        }

        if ($existing) {
            $this->db->where('id', (int)$existing['id'])->update($this->messages, $payload);
            $id = (int)$existing['id'];
        } else {
            $payload['instance_name'] = $instance;
            $payload['api_message_id'] = $apiId;
            $payload['created_at'] = $now;
            $this->db->insert($this->messages, $payload);
            $id = (int)$this->db->insert_id();
        }
        if (!$id) return null;

        $preview = $this->preview($payload);
        $incomingLastAt = date('Y-m-d H:i:s', (int)$payload['message_timestamp']);
        $conversationRow = $this->db->select('last_message_at')->where('id', $conversationId)->get($this->conversations)->row_array();
        $currentLastAt = (string)($conversationRow['last_message_at'] ?? '');
        if ($currentLastAt === '' || strtotime($incomingLastAt) >= strtotime($currentLastAt)) {
            $this->db->where('id', $conversationId)->update($this->conversations, [
                'last_message_at' => $incomingLastAt,
                'last_message_preview' => $preview,
                'last_message_from_me' => $payload['from_me'],
                'updated_at' => $now,
            ]);
        } else {
            // Backfill histórico nunca pode mover a conversa para trás nem substituir
            // o preview da mensagem mais recente.
            $this->db->where('id', $conversationId)->update($this->conversations, ['updated_at' => $now]);
        }

        // 3. Só depois da persistência garantida trata ticket live.
        if ($liveEvent && empty($message['from_me']) && $this->db->table_exists($this->tickets)) {
            try {
                $ticket = $this->ensureLiveInboundTicket(
                    $instance,
                    $conversationId,
                    $id,
                    $apiId,
                    (int)$payload['message_timestamp']
                );
                if ($ticket) {
                    $ticketId = (int)$ticket['id'];
                    if ($this->db->field_exists('ticket_id', $this->messages)) {
                        $this->db->where('id', $id)->update($this->messages, ['ticket_id' => $ticketId]);
                    }
                }
            } catch (Throwable $ticketError) {
                // Nunca desfaz a mensagem já recebida. O reconciliador local tentará
                // novamente no ciclo realtime/conversations.
                log_message('error', '[Connect|API Chat][ticket.inbound] ' . $ticketError->getMessage());
            }
        }

        $this->emitRealtimeEvent($instance, $existing ? 'message.update' : 'message.new', $conversationId, $id, $ticketId);
        return $id;
    }

    /**
     * Garante o ticket de uma mensagem inbound recebida AO VIVO.
     *
     * A conversa é bloqueada durante a decisão para evitar dois tickets simultâneos
     * quando o Connect|API retransmite o mesmo evento.
     */
    public function ensureLiveInboundTicket($instance, $conversationId, $messageId, $apiMessageId, $timestamp = null)
    {
        if (!$this->db->table_exists($this->tickets)) return null;

        $instance = (string)$instance;
        $conversationId = (int)$conversationId;
        $messageId = (int)$messageId;
        $apiMessageId = trim((string)$apiMessageId);
        $timestamp = (int)$timestamp ?: time();
        if ($conversationId <= 0 || $messageId <= 0) return null;

        $created = false;
        $ticketId = 0;
        $now = date('Y-m-d H:i:s');

        $this->db->trans_begin();
        try {
            $c = $this->db->query(
                'SELECT * FROM `' . $this->conversations . '` WHERE `id`=? AND `instance_name`=? LIMIT 1 FOR UPDATE',
                [$conversationId, $instance]
            )->row_array();
            if (!$c) throw new RuntimeException('Conversa inbound não encontrada.');

            // Retry idempotente: se esta mensagem já originou um ticket, reutiliza.
            $sourceTicket = null;
            if ($apiMessageId !== '' && $this->db->field_exists('source_api_message_id', $this->tickets)) {
                $sourceTicket = $this->db
                    ->where([
                        'instance_name' => $instance,
                        'conversation_id' => $conversationId,
                        'source_api_message_id' => $apiMessageId,
                    ])
                    ->order_by('id', 'DESC')
                    ->limit(1)
                    ->get($this->tickets)
                    ->row_array();
            }

            if ($sourceTicket) {
                $ticketId = (int)$sourceTicket['id'];
                if ((string)($sourceTicket['status'] ?? '') === 'open') {
                    $this->db->where(['id'=>$conversationId,'instance_name'=>$instance])->update($this->conversations,[
                        'current_ticket_id'=>$ticketId,
                        'status'=>'open',
                        'assigned_staff_id'=>null,
                        'new_ticket_flag'=>!empty($sourceTicket['is_new']) ? 1 : 0,
                        'closed_at'=>null,
                        'closed_by_staff_id'=>null,
                        'updated_at'=>$now,
                    ]);
                }
            } else {
                $current = null;
                if (!empty($c['current_ticket_id'])) {
                    $current = $this->db
                        ->where([
                            'id'=>(int)$c['current_ticket_id'],
                            'instance_name'=>$instance,
                            'conversation_id'=>$conversationId,
                        ])
                        ->limit(1)
                        ->get($this->tickets)
                        ->row_array();
                }

                if ($current && (string)($current['status'] ?? '') === 'open') {
                    $ticketId = (int)$current['id'];
                } else {
                    $maxRow = $this->db->select_max('ticket_number','n')
                        ->where('conversation_id',$conversationId)
                        ->get($this->tickets)
                        ->row_array();
                    $number = max((int)($c['ticket_counter'] ?? 0), (int)($maxRow['n'] ?? 0)) + 1;
                    $openedAt = date('Y-m-d H:i:s', $timestamp);

                    $ticketPayload = [
                        'instance_name'=>$instance,
                        'conversation_id'=>$conversationId,
                        'ticket_number'=>$number,
                        'status'=>'open',
                        'opened_via'=>'inbound',
                        'opened_by_staff_id'=>null,
                        'opened_at'=>$openedAt,
                        'closed_at'=>null,
                        'closed_by_staff_id'=>null,
                        'is_new'=>1,
                        'created_at'=>$now,
                        'updated_at'=>$now,
                    ];
                    if ($this->db->field_exists('source_api_message_id',$this->tickets)) {
                        $ticketPayload['source_api_message_id'] = $apiMessageId !== '' ? $apiMessageId : null;
                    }
                    $this->db->insert($this->tickets,$ticketPayload);
                    $ticketId=(int)$this->db->insert_id();
                    if (!$ticketId) throw new RuntimeException('Não foi possível criar o novo atendimento inbound.');

                    $this->db->where(['id'=>$conversationId,'instance_name'=>$instance])->update($this->conversations,[
                        'current_ticket_id'=>$ticketId,
                        'ticket_counter'=>$number,
                        'new_ticket_flag'=>1,
                        'status'=>'open',
                        'closed_at'=>null,
                        'closed_by_staff_id'=>null,
                        'assigned_staff_id'=>null,
                        'updated_at'=>$now,
                    ]);

                    if ($this->db->table_exists($this->assignments) && $this->db->field_exists('ticket_id',$this->assignments)) {
                        $this->db->insert($this->assignments,[
                            'conversation_id'=>$conversationId,
                            'ticket_id'=>$ticketId,
                            'from_staff_id'=>$c['assigned_staff_id'] ? (int)$c['assigned_staff_id'] : null,
                            'to_staff_id'=>null,
                            'action'=>'new_ticket',
                            'note'=>'Novo atendimento #'.$number.' iniciado por mensagem recebida.',
                            'created_by_staff_id'=>null,
                            'created_at'=>$now,
                        ]);
                    }
                    $created = true;
                }
            }

            if ($ticketId && $this->db->field_exists('ticket_id',$this->messages)) {
                $update = ['ticket_id'=>$ticketId,'updated_at'=>$now];
                if ($this->db->field_exists('live_received_at',$this->messages)) $update['live_received_at']=$now;
                $this->db->where(['id'=>$messageId,'instance_name'=>$instance])->update($this->messages,$update);
            }

            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Transação do atendimento inbound falhou.');
            }
            $this->db->trans_commit();
        } catch (Throwable $e) {
            if ($this->db->trans_status() !== false || method_exists($this->db,'trans_rollback')) {
                $this->db->trans_rollback();
            }
            throw $e;
        }

        $ticket = $ticketId ? $this->ticket($instance,$ticketId) : null;
        if ($created && $ticket) {
            $this->emitRealtimeEvent($instance,'ticket.new',$conversationId,$messageId,$ticketId);
        }
        return $ticket;
    }

    /**
     * Reconciliador LOCAL e barato. Não consulta o Connect|API.
     * Repara mensagens que o webhook já persistiu mas cujo ticket não concluiu.
     */
    public function repairLiveInboundTickets($instance, $limit = 10)
    {
        if (!$this->db->table_exists($this->tickets) || !$this->db->field_exists('live_received_at',$this->messages)) return 0;
        $limit=max(1,min(50,(int)$limit));
        $sql='SELECT m.id,m.conversation_id,m.api_message_id,m.message_timestamp,m.ticket_id,m.live_received_at,'
            .'c.current_ticket_id,c.status AS conversation_status,c.closed_at AS conversation_closed_at,'
            .'tk.status AS current_ticket_status,tk.closed_at AS current_ticket_closed_at '
            .'FROM `'.$this->messages.'` m '
            .'JOIN `'.$this->conversations.'` c ON c.id=m.conversation_id AND c.instance_name=m.instance_name '
            .'LEFT JOIN `'.$this->tickets.'` tk ON tk.id=c.current_ticket_id '
            .'WHERE m.instance_name=? AND m.from_me=0 AND m.live_received_at IS NOT NULL '
            .'AND (c.current_ticket_id IS NULL OR tk.id IS NULL '
            .'OR (tk.status="closed" AND m.live_received_at > COALESCE(tk.closed_at,c.closed_at,"1970-01-01 00:00:00")) '
            .'OR (tk.status="open" AND (m.ticket_id IS NULL OR m.ticket_id<>tk.id))) '
            .'ORDER BY m.id DESC LIMIT '.$limit;
        $rows=$this->db->query($sql,[(string)$instance])->result_array();
        $fixed=0;
        foreach($rows as$row){
            try{
                $ticket=$this->ensureLiveInboundTicket(
                    (string)$instance,
                    (int)$row['conversation_id'],
                    (int)$row['id'],
                    (string)$row['api_message_id'],
                    (int)$row['message_timestamp']
                );
                if($ticket)$fixed++;
            }catch(Throwable$e){
                log_message('error','[Connect|API Chat][ticket.repair] '.$e->getMessage());
            }
        }
        return$fixed;
    }

    public function conversationCounts($instance, $staffId)
    {
        $instance=(string)$instance;$staffId=(int)$staffId;
        $sql='SELECT '
            .'SUM(CASE WHEN status <> ? AND assigned_staff_id = ? THEN 1 ELSE 0 END) AS mine, '
            .'SUM(CASE WHEN status <> ? AND assigned_staff_id IS NULL THEN 1 ELSE 0 END) AS unassigned, '
            .'SUM(CASE WHEN status <> ? THEN 1 ELSE 0 END) AS all_open, '
            .'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS closed_all, '
            .'SUM(CASE WHEN status = ? AND assigned_staff_id = ? THEN 1 ELSE 0 END) AS closed_mine '
            .'FROM '.$this->conversations.' WHERE instance_name = ?';
        $row=$this->db->query($sql,['closed',$staffId,'closed','closed','closed','closed',$staffId,$instance])->row_array() ?: [];
        return [
            'mine'=>(int)($row['mine']??0),
            'unassigned'=>(int)($row['unassigned']??0),
            'all'=>(int)($row['all_open']??0),
            'closed'=>(int)(connect_api_chat_can('view_all') ? ($row['closed_all']??0) : ($row['closed_mine']??0)),
        ];
    }

    public function listConversations($instance, $staffId, $scope = 'mine', $search = '', $limit = 80)
    {
        $extra = '';
        if ($this->db->field_exists('remote_jid_alt', $this->conversations)) $extra .= ', c.remote_jid_alt';
        if ($this->db->field_exists('identity_key', $this->conversations)) $extra .= ', c.identity_key';
        $this->db->select('c.*, ct.phone_number, ct.display_name, ct.profile_pic_url, s.firstname as assigned_firstname, s.lastname as assigned_lastname, r.last_read_message_id' . $extra);
        if ($this->db->table_exists($this->tickets)) $this->db->select('tk.ticket_number as current_ticket_number, tk.status as current_ticket_status, tk.opened_at as current_ticket_opened_at, tk.is_new as current_ticket_is_new');
        $this->db->select('(SELECT COUNT(*) FROM ' . $this->messages . ' m WHERE m.conversation_id=c.id AND m.from_me=0 AND m.is_deleted=0 AND m.id > COALESCE(r.last_read_message_id,0)) AS unread_count', false);
        $this->db->from($this->conversations . ' c');
        $this->db->join($this->contacts . ' ct', 'ct.id=c.contact_id', 'left');
        $this->db->join(db_prefix() . 'staff s', 's.staffid=c.assigned_staff_id', 'left');
        $this->db->join($this->reads . ' r', 'r.conversation_id=c.id AND r.staff_id=' . (int)$staffId, 'left');
        if ($this->db->table_exists($this->tickets)) $this->db->join($this->tickets . ' tk', 'tk.id=c.current_ticket_id', 'left');
        $this->db->where('c.instance_name', $instance);
        if ($scope === 'closed') {
            $this->db->where('c.status', 'closed');
            if (!connect_api_chat_can('view_all')) $this->db->where('c.assigned_staff_id', (int)$staffId);
        } else {
            $this->db->where('c.status !=', 'closed');
            if ($scope === 'mine') $this->db->where('c.assigned_staff_id', (int)$staffId);
            elseif ($scope === 'unassigned') $this->db->where('c.assigned_staff_id IS NULL', null, false);
        }
        if ($search !== '') {
            $this->db->group_start();
            $this->db->like('c.title', $search);
            $this->db->or_like('ct.display_name', $search);
            $this->db->or_like('ct.phone_number', $search);
            $this->db->group_end();
        }
        $this->db->order_by('c.last_message_at IS NULL', 'ASC', false);
        $this->db->order_by('c.last_message_at', 'DESC');
        $this->db->order_by('c.id', 'DESC');
        $this->db->limit(max(1, min(200, (int)$limit)));
        $rows = $this->db->get()->result_array();
        foreach ($rows as &$r) {
            $r['assigned_name'] = trim(($r['assigned_firstname'] ?? '') . ' ' . ($r['assigned_lastname'] ?? ''));
            $r['unread_count'] = (int)$r['unread_count'];
            if (empty($r['display_name']) || $this->looksLikeJid($r['display_name'])) {
                $r['display_name'] = $this->displayAddress($r['phone_number'] ?? '', $r['remote_jid'] ?? '');
            }
        }
        return $rows;
    }

    public function conversation($instance, $id)
    {
        $this->db->select('c.*, ct.phone_number, ct.display_name, ct.profile_pic_url, s.firstname as assigned_firstname, s.lastname as assigned_lastname');
        if ($this->db->table_exists($this->tickets)) $this->db->select('tk.ticket_number as current_ticket_number, tk.status as current_ticket_status, tk.opened_at as current_ticket_opened_at, tk.closed_at as current_ticket_closed_at, tk.is_new as current_ticket_is_new');
        $this->db->from($this->conversations . ' c');
        $this->db->join($this->contacts . ' ct', 'ct.id=c.contact_id', 'left');
        $this->db->join(db_prefix() . 'staff s', 's.staffid=c.assigned_staff_id', 'left');
        if ($this->db->table_exists($this->tickets)) $this->db->join($this->tickets . ' tk', 'tk.id=c.current_ticket_id', 'left');
        $this->db->where(['c.instance_name' => $instance, 'c.id' => (int)$id]);
        $r = $this->db->get()->row_array();
        if ($r) {
            $r['assigned_name'] = trim(($r['assigned_firstname'] ?? '') . ' ' . ($r['assigned_lastname'] ?? ''));
            if (empty($r['display_name']) || $this->looksLikeJid($r['display_name'])) {
                $r['display_name'] = $this->displayAddress($r['phone_number'] ?? '', $r['remote_jid'] ?? '');
            }
        }
        return $r;
    }

    public function messages($instance, $conversationId, $limit = 50, $beforeId = null, $afterId = null)
    {
        $this->db->select('m.*, s.firstname as staff_firstname, s.lastname as staff_lastname');
        if ($this->db->table_exists($this->tickets) && $this->db->field_exists('ticket_id',$this->messages)) $this->db->select('tk.ticket_number, tk.status as ticket_status, tk.opened_at as ticket_opened_at, tk.closed_at as ticket_closed_at');
        $this->db->from($this->messages . ' m');
        $this->db->join(db_prefix() . 'staff s', 's.staffid=m.sender_staff_id', 'left');
        if ($this->db->table_exists($this->tickets) && $this->db->field_exists('ticket_id',$this->messages)) $this->db->join($this->tickets . ' tk', 'tk.id=m.ticket_id', 'left');
        $this->db->where(['m.instance_name' => $instance, 'm.conversation_id' => (int)$conversationId]);
        if ($beforeId) $this->db->where('m.id <', (int)$beforeId);
        if ($afterId) $this->db->where('m.id >', (int)$afterId);
        if ($afterId) {
            // Poll incremental continua baseado no id local, mas entrega as linhas
            // em ordem cronológica para não quebrar a linha do tempo visual.
            $this->db->order_by('m.message_timestamp', 'ASC');
            $this->db->order_by('m.id', 'ASC');
        } else {
            // Busca a janela mais recente por timestamp real do WhatsApp, não por id
            // de inserção local (sincronizações tardias podem ter ids maiores).
            $this->db->order_by('m.message_timestamp', 'DESC');
            $this->db->order_by('m.id', 'DESC');
        }
        $this->db->limit(max(1, min(200, (int)$limit)));
        $rows = $this->db->get()->result_array();
        if (!$afterId) $rows = array_reverse($rows);
        foreach ($rows as &$m) $m['staff_name'] = trim(($m['staff_firstname'] ?? '') . ' ' . ($m['staff_lastname'] ?? ''));
        return $rows;
    }

    public function messageById($instance, $id)
    {
        return $this->db->where(['instance_name' => $instance, 'id' => (int)$id])->get($this->messages)->row_array();
    }

    public function refreshMessageRawPayload($instance,$messageId,array $record)
    {
        $payload=['raw_payload'=>json_encode($record,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'updated_at'=>date('Y-m-d H:i:s')];
        $this->db->where('instance_name',(string)$instance)->where('id',(int)$messageId)->update($this->messages,$payload);
        return $this->db->affected_rows()>=0;
    }

    public function setLocalMediaPath($instance, $id, $path, $mime = null, $fileName = null)
    {
        $payload = ['local_media_path' => (string)$path, 'updated_at' => date('Y-m-d H:i:s')];
        if ($mime !== null && $mime !== '') $payload['mimetype'] = (string)$mime;
        if ($fileName !== null && $fileName !== '') $payload['file_name'] = (string)$fileName;
        $this->db->where(['instance_name' => $instance, 'id' => (int)$id])->update($this->messages, $payload);
        return $this->db->affected_rows() >= 0;
    }

    public function message($instance, $apiMessageId)
    {
        return $this->db->where(['instance_name' => $instance, 'api_message_id' => (string)$apiMessageId])->get($this->messages)->row_array();
    }

    public function conversationForMessage($instance, $messageId)
    {
        $this->db->select('c.*')->from($this->messages . ' m')->join($this->conversations . ' c', 'c.id=m.conversation_id', 'inner');
        $this->db->where(['m.instance_name' => $instance, 'm.id' => (int)$messageId]);
        return $this->db->get()->row_array();
    }

    public function markRead($conversationId, $staffId)
    {
        $this->db->select_max('id', 'max_id')->where('conversation_id', (int)$conversationId);
        $max = $this->db->get($this->messages)->row_array();
        $maxId = (int)($max['max_id'] ?? 0);
        $this->db->where(['conversation_id' => (int)$conversationId, 'staff_id' => (int)$staffId]);
        $row = $this->db->get($this->reads)->row_array();
        $data = ['last_read_message_id' => $maxId ?: null, 'last_read_at' => date('Y-m-d H:i:s')];
        if ($row) $this->db->where('id', $row['id'])->update($this->reads, $data);
        else {
            $data['conversation_id'] = (int)$conversationId;
            $data['staff_id'] = (int)$staffId;
            $this->db->insert($this->reads, $data);
        }
    }

    public function assign($instance, $conversationId, $toStaffId, $actorStaffId, $note = '')
    {
        $c = $this->conversation($instance, $conversationId);
        if (!$c) return false;
        $from = $c['assigned_staff_id'] ? (int)$c['assigned_staff_id'] : null;
        $to = $toStaffId ? (int)$toStaffId : null;
        $this->db->where('id', (int)$conversationId)->update($this->conversations, ['assigned_staff_id' => $to, 'new_ticket_flag' => $to ? 0 : (int)($c['new_ticket_flag']??0), 'updated_at' => date('Y-m-d H:i:s')]);
        $ticket=$this->currentTicket($instance,$conversationId);
        if($ticket && $to){$this->db->where('id',(int)$ticket['id'])->update($this->tickets,['is_new'=>0,'updated_at'=>date('Y-m-d H:i:s')]);}
        $action = $from === null && $to ? 'claimed' : ($to === null ? 'unassigned' : ($from === $to ? 'assigned' : 'transferred'));
        $assignment = [
            'conversation_id' => (int)$conversationId,
            'from_staff_id' => $from,
            'to_staff_id' => $to,
            'action' => $action,
            'note' => $note ?: null,
            'created_by_staff_id' => (int)$actorStaffId,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        if($ticket && $this->db->field_exists('ticket_id',$this->assignments))$assignment['ticket_id']=(int)$ticket['id'];
        $this->db->insert($this->assignments,$assignment);
        $this->emitRealtimeEvent($instance,'assignment.changed',$conversationId,null,$ticket['id']??null);
        return true;
    }

    public function assignmentHistory($conversationId, $limit = 20)
    {
        $this->db->select('a.*, f.firstname as from_firstname, f.lastname as from_lastname, t.firstname as to_firstname, t.lastname as to_lastname, u.firstname as actor_firstname, u.lastname as actor_lastname');
        if($this->db->table_exists($this->tickets)&&$this->db->field_exists('ticket_id',$this->assignments))$this->db->select('tk.ticket_number');
        $this->db->from($this->assignments . ' a');
        $this->db->join(db_prefix() . 'staff f', 'f.staffid=a.from_staff_id', 'left');
        $this->db->join(db_prefix() . 'staff t', 't.staffid=a.to_staff_id', 'left');
        $this->db->join(db_prefix() . 'staff u', 'u.staffid=a.created_by_staff_id', 'left');
        if($this->db->table_exists($this->tickets)&&$this->db->field_exists('ticket_id',$this->assignments))$this->db->join($this->tickets . ' tk','tk.id=a.ticket_id','left');
        $this->db->where('a.conversation_id', (int)$conversationId);
        $this->db->order_by('a.id', 'DESC');
        $this->db->limit(max(1, min(100, (int)$limit)));
        $rows = $this->db->get()->result_array();
        foreach ($rows as &$r) {
            $r['from_name'] = trim(($r['from_firstname'] ?? '') . ' ' . ($r['from_lastname'] ?? ''));
            $r['to_name'] = trim(($r['to_firstname'] ?? '') . ' ' . ($r['to_lastname'] ?? ''));
            $r['actor_name'] = trim(($r['actor_firstname'] ?? '') . ' ' . ($r['actor_lastname'] ?? ''));
        }
        return $rows;
    }

    public function searchMessages($instance, $conversationId, $term, $limit = 100)
    {
        $term = trim((string)$term);
        if ($term === '') return [];
        $this->db->select('id,api_message_id,message_type,text_content,caption,file_name,sender_name,from_me,message_timestamp');
        $this->db->from($this->messages);
        $this->db->where(['instance_name' => $instance, 'conversation_id' => (int)$conversationId, 'is_deleted' => 0]);
        $this->db->group_start();
        $this->db->like('text_content', $term);
        $this->db->or_like('caption', $term);
        $this->db->or_like('file_name', $term);
        $this->db->or_like('sender_name', $term);
        $this->db->group_end();
        $this->db->order_by('message_timestamp', 'ASC');
        $this->db->order_by('id', 'ASC');
        $this->db->limit(max(1, min(200, (int)$limit)));
        $rows = $this->db->get()->result_array();
        foreach ($rows as &$row) {
            $snippet = trim((string)($row['text_content'] ?: ($row['caption'] ?: $row['file_name'])));
            if ($snippet === '') $snippet = '[' . ($row['message_type'] ?: 'mensagem') . ']';
            $row['snippet'] = mb_substr(preg_replace('/\s+/u', ' ', $snippet), 0, 180);
        }
        return $rows;
    }

    public function messagesAround($instance, $conversationId, $messageId, $radius = 24)
    {
        $radius=max(5,min(60,(int)$radius));
        $ticketJoin=$this->db->table_exists($this->tickets)&&$this->db->field_exists('ticket_id',$this->messages);
        $select='m.*, s.firstname as staff_firstname, s.lastname as staff_lastname';
        if($ticketJoin)$select.=', tk.ticket_number, tk.status as ticket_status, tk.opened_at as ticket_opened_at, tk.closed_at as ticket_closed_at';
        $this->db->select($select)->from($this->messages.' m')->join(db_prefix().'staff s','s.staffid=m.sender_staff_id','left');
        if($ticketJoin)$this->db->join($this->tickets.' tk','tk.id=m.ticket_id','left');
        $before=$this->db->where(['m.instance_name'=>$instance,'m.conversation_id'=>(int)$conversationId])->where('m.id <=',(int)$messageId)->order_by('m.message_timestamp','DESC')->order_by('m.id','DESC')->limit($radius+1)->get()->result_array();
        $before=array_reverse($before);
        $this->db->select($select)->from($this->messages.' m')->join(db_prefix().'staff s','s.staffid=m.sender_staff_id','left');
        if($ticketJoin)$this->db->join($this->tickets.' tk','tk.id=m.ticket_id','left');
        $after=$this->db->where(['m.instance_name'=>$instance,'m.conversation_id'=>(int)$conversationId])->where('m.id >',(int)$messageId)->order_by('m.message_timestamp','ASC')->order_by('m.id','ASC')->limit($radius)->get()->result_array();
        $rows=array_merge($before,$after);
        foreach($rows as&$m)$m['staff_name']=trim(($m['staff_firstname']??'').' '.($m['staff_lastname']??''));
        return$rows;
    }

    public function setConversationStatus($instance, $conversationId, $status, $actorStaffId)
    {
        $status = $status === 'closed' ? 'closed' : 'open';
        $c = $this->conversation($instance, $conversationId);
        if (!$c) return false;
        $ticket=$this->currentTicket($instance,$conversationId);
        if(!$ticket) $ticket=$this->createTicket($instance,$conversationId,'manual',$actorStaffId,time(),false);
        if(!$ticket) return false;

        $now=date('Y-m-d H:i:s');
        $ticketPayload=['status'=>$status,'updated_at'=>$now,'is_new'=>0];
        if($status==='closed'){
            $ticketPayload['closed_at']=($ticket['status']??'')==='closed' && !empty($ticket['closed_at']) ? $ticket['closed_at'] : $now;
            $ticketPayload['closed_by_staff_id']=($ticket['status']??'')==='closed' && !empty($ticket['closed_by_staff_id']) ? $ticket['closed_by_staff_id'] : (int)$actorStaffId;
        }else{
            // Reabrir é SEMPRE manual e mantém o mesmo número do atendimento.
            $ticketPayload['closed_at']=null;
            $ticketPayload['closed_by_staff_id']=null;
        }

        $this->db->trans_start();
        $this->db->where('id',(int)$ticket['id'])->update($this->tickets,$ticketPayload);
        $convPayload=[
            'status'=>$status,
            'current_ticket_id'=>(int)$ticket['id'],
            'new_ticket_flag'=>0,
            'updated_at'=>$now,
        ];
        if($status==='closed'){
            $convPayload['closed_at']=$ticketPayload['closed_at'];
            $convPayload['closed_by_staff_id']=$ticketPayload['closed_by_staff_id'];
        }else{
            $convPayload['closed_at']=null;
            $convPayload['closed_by_staff_id']=null;
        }
        $this->db->where(['id'=>(int)$conversationId,'instance_name'=>(string)$instance])->update($this->conversations,$convPayload);

        if ((string)($ticket['status']??'') !== $status) {
            $assignment=[
                'conversation_id'=>(int)$conversationId,
                'from_staff_id'=>$c['assigned_staff_id']?(int)$c['assigned_staff_id']:null,
                'to_staff_id'=>$c['assigned_staff_id']?(int)$c['assigned_staff_id']:null,
                'action'=>$status==='closed'?'closed':'reopened',
                'note'=>$status==='closed'?'Atendimento #'.(int)$ticket['ticket_number'].' encerrado.':'Atendimento #'.(int)$ticket['ticket_number'].' reaberto manualmente.',
                'created_by_staff_id'=>(int)$actorStaffId,
                'created_at'=>$now,
            ];
            if($this->db->field_exists('ticket_id',$this->assignments))$assignment['ticket_id']=(int)$ticket['id'];
            $this->db->insert($this->assignments,$assignment);
        }
        $this->db->trans_complete();
        if (!$this->db->trans_status()) return false;
        $this->emitRealtimeEvent($instance,$status==='closed'?'ticket.closed':'ticket.reopened',$conversationId,null,(int)$ticket['id']);
        return true;
    }

    public function staffList()
    {
        $this->db->select('staffid,firstname,lastname,profile_image')->where('active', 1)->order_by('firstname', 'ASC')->order_by('lastname', 'ASC');
        return $this->db->get(db_prefix() . 'staff')->result_array();
    }

    public function createManualConversation($instance, $phone, $name, $staffId)
    {
        $jid = $phone . '@s.whatsapp.net';
        $contactId = $this->saveContact($instance, ['remote_jid' => $jid, 'phone_number' => $phone, 'display_name' => $name ?: $phone, 'is_group' => false]);
        $conversationId=$this->upsertConversation($instance, $jid, ['contact_id' => $contactId, 'phone_number' => $phone, 'title' => $name ?: $phone, 'assigned_staff_id' => $staffId, 'created_by_staff_id' => $staffId]);
        if($this->db->table_exists($this->tickets)){
            $ticket=$this->currentTicket($instance,$conversationId);
            if(!$ticket||($ticket['status']??'open')==='closed')$this->createTicket($instance,$conversationId,'manual',$staffId,time(),true);
            else $this->assign($instance,$conversationId,$staffId,$staffId,'Atendimento iniciado manualmente.');
        }
        return $conversationId;
    }

    public function updateMessageStatus($instance, $apiId, $status)
    {
        $row=$this->message($instance,$apiId);
        $this->db->where(['instance_name' => $instance, 'api_message_id' => $apiId])->update($this->messages, ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
        if($row)$this->emitRealtimeEvent($instance,'message.status',(int)$row['conversation_id'],(int)$row['id'],$row['ticket_id']??null);
    }

    public function markMessageDeleted($instance, $apiId)
    {
        $row=$this->message($instance,$apiId);
        $this->db->where(['instance_name' => $instance, 'api_message_id' => $apiId])->update($this->messages, ['is_deleted' => 1, 'text_content' => null, 'caption' => null, 'updated_at' => date('Y-m-d H:i:s')]);
        if($row)$this->emitRealtimeEvent($instance,'message.deleted',(int)$row['conversation_id'],(int)$row['id'],$row['ticket_id']??null);
    }

    public function registerEvent($instance, $eventName, $raw)
    {
        $key = hash('sha256', $instance . '|' . $eventName . '|' . $raw);
        $row=$this->db->where('event_key',$key)->limit(1)->get($this->events)->row_array();
        if($row){
            if($this->db->field_exists('attempts',$this->events))$this->db->set('attempts','attempts+1',false)->where('id',(int)$row['id'])->update($this->events);
            return false;
        }
        $payload=['event_key'=>$key,'instance_name'=>$instance,'event_name'=>$eventName,'created_at'=>date('Y-m-d H:i:s')];
        if($this->db->field_exists('attempts',$this->events))$payload['attempts']=1;
        $this->db->insert($this->events,$payload);
        return true;
    }

    public function markEventProcessed($instance,$eventName,$raw)
    {
        if(!$this->db->field_exists('processed_at',$this->events))return;
        $key=hash('sha256',$instance.'|'.$eventName.'|'.$raw);
        $payload=['processed_at'=>date('Y-m-d H:i:s')];
        if($this->db->field_exists('last_error',$this->events))$payload['last_error']=null;
        $this->db->where('event_key',$key)->update($this->events,$payload);
    }

    public function markEventFailed($instance,$eventName,$raw,$error)
    {
        if(!$this->db->field_exists('last_error',$this->events))return;
        $key=hash('sha256',$instance.'|'.$eventName.'|'.$raw);
        $this->db->where('event_key',$key)->update($this->events,['last_error'=>mb_substr((string)$error,0,1000)]);
    }

    public function crmContext($phone)
    {
        $digits = preg_replace('/\D+/', '', (string)$phone);
        if ($digits === '') return [];

        $candidates = array_values(array_unique(array_filter([
            $digits,
            strlen($digits) > 11 ? substr($digits, -11) : null,
            strlen($digits) > 10 ? substr($digits, -10) : null,
        ])));
        $result = [];
        $normalizedExpression = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(%s,'+',''),'-',''),' ',''),'(',''),')','')";

        $contacts = db_prefix() . 'contacts';
        $clients = db_prefix() . 'clients';
        if ($this->db->table_exists($contacts)) {
            $this->db->select('ct.id,ct.firstname,ct.lastname,ct.userid,cl.company')->from($contacts . ' ct')->join($clients . ' cl', 'cl.userid=ct.userid', 'left');
            $this->appendPhoneWhere(sprintf($normalizedExpression, 'ct.phonenumber'), $candidates);
            $this->db->limit(1);
            $c = $this->db->get()->row_array();
            if ($c) $result['contact'] = ['id' => $c['id'], 'name' => trim($c['firstname'] . ' ' . $c['lastname']), 'company' => $c['company'], 'url' => admin_url('clients/client/' . $c['userid'] . '?contactid=' . $c['id'])];
        }

        $leads = db_prefix() . 'leads';
        if ($this->db->table_exists($leads)) {
            $this->db->select('id,name,status')->from($leads);
            $this->appendPhoneWhere(sprintf($normalizedExpression, 'phonenumber'), $candidates);
            $this->db->limit(1);
            $l = $this->db->get()->row_array();
            if ($l) $result['lead'] = ['id' => $l['id'], 'name' => $l['name'], 'url' => admin_url('leads/index/' . $l['id'])];
        }
        return $result;
    }


    public function staffInstanceAccess($staffId)
    {
        if (!$this->db->table_exists($this->staffInstances)) return [];
        return $this->db->where('staff_id',(int)$staffId)->order_by('is_default','DESC')->order_by('instance_name','ASC')->get($this->staffInstances)->result_array();
    }

    public function staffInstanceNames($staffId)
    {
        return array_values(array_filter(array_map(function($row){return (string)($row['instance_name']??'');},$this->staffInstanceAccess($staffId))));
    }

    public function staffDefaultInstance($staffId)
    {
        if (!$this->db->table_exists($this->staffInstances)) return '';
        $row=$this->db->where('staff_id',(int)$staffId)->where('is_default',1)->limit(1)->get($this->staffInstances)->row_array();
        if($row)return (string)$row['instance_name'];
        $row=$this->db->where('staff_id',(int)$staffId)->order_by('instance_name','ASC')->limit(1)->get($this->staffInstances)->row_array();
        return $row?(string)$row['instance_name']:'';
    }

    public function replaceStaffInstanceAccess($staffId,array $instanceNames,$defaultInstance='')
    {
        if (!$this->db->table_exists($this->staffInstances)) return false;
        $staffId=(int)$staffId;
        $clean=[];
        foreach($instanceNames as $name){$name=trim((string)$name);if($name!==''&&!in_array($name,$clean,true))$clean[]=$name;}
        if($defaultInstance!==''&&!in_array($defaultInstance,$clean,true))$defaultInstance='';
        if($defaultInstance===''&&!empty($clean))$defaultInstance=$clean[0];
        $this->db->trans_start();
        $this->db->where('staff_id',$staffId)->delete($this->staffInstances);
        foreach($clean as $name){
            $this->db->insert($this->staffInstances,[
                'staff_id'=>$staffId,'instance_name'=>$name,'is_default'=>$name===$defaultInstance?1:0,
                'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s'),
            ]);
        }
        $this->db->trans_complete();
        return $this->db->trans_status();
    }

    public function allStaffInstanceAccess()
    {
        $result=[];
        if (!$this->db->table_exists($this->staffInstances)) return $result;
        foreach($this->db->order_by('staff_id','ASC')->order_by('instance_name','ASC')->get($this->staffInstances)->result_array() as $row){
            $sid=(int)$row['staff_id'];if(!isset($result[$sid]))$result[$sid]=[];$result[$sid][]=$row;
        }
        return $result;
    }

    public function consolidateAliases($instance)
    {
        if (!$this->db->field_exists('remote_jid_alt', $this->conversations)) return 0;
        $rows=$this->db->where('instance_name',$instance)->where('remote_jid_alt IS NOT NULL',null,false)->where("remote_jid_alt != ''",null,false)->get($this->conversations)->result_array();
        $merged=0;
        foreach($rows as $keeper){
            $alt=(string)$keeper['remote_jid_alt'];
            $duplicate=$this->db->where('instance_name',$instance)->where('remote_jid',$alt)->where('id !=',(int)$keeper['id'])->limit(1)->get($this->conversations)->row_array();
            if(!$duplicate)continue;
            $this->mergeConversationRows($keeper,$duplicate);
            $merged++;
        }
        return$merged;
    }

    private function mergeConversationRows($keeper,$duplicate)
    {
        $keeperId=(int)$keeper['id'];$duplicateId=(int)$duplicate['id'];
        if(!$keeperId||!$duplicateId||$keeperId===$duplicateId)return;

        if($this->db->table_exists($this->tickets)){
            $maxRow=$this->db->select_max('ticket_number','n')->where('conversation_id',$keeperId)->get($this->tickets)->row_array();$next=(int)($maxRow['n']??0);
            $dupTickets=$this->db->where('conversation_id',$duplicateId)->order_by('ticket_number','ASC')->get($this->tickets)->result_array();
            foreach($dupTickets as$tk){$next++;$this->db->where('id',(int)$tk['id'])->update($this->tickets,['conversation_id'=>$keeperId,'ticket_number'=>$next,'updated_at'=>date('Y-m-d H:i:s')]);}
            $latest=$this->db->where('conversation_id',$keeperId)->order_by('ticket_number','DESC')->limit(1)->get($this->tickets)->row_array();
            $ticketUpdate=['ticket_counter'=>max($next,(int)($keeper['ticket_counter']??0))];
            if($latest){$ticketUpdate['current_ticket_id']=(int)$latest['id'];$ticketUpdate['status']=(string)$latest['status'];$ticketUpdate['new_ticket_flag']=!empty($latest['is_new'])?1:0;$ticketUpdate['closed_at']=($latest['status']==='closed')?($latest['closed_at']??null):null;$ticketUpdate['closed_by_staff_id']=($latest['status']==='closed')?($latest['closed_by_staff_id']??null):null;}
            $this->db->where('id',$keeperId)->update($this->conversations,$ticketUpdate);
        }
        $this->db->where('conversation_id',$duplicateId)->update($this->messages,['conversation_id'=>$keeperId]);
        $this->db->where('conversation_id',$duplicateId)->update($this->assignments,['conversation_id'=>$keeperId]);

        $readRows=$this->db->where('conversation_id',$duplicateId)->get($this->reads)->result_array();
        foreach($readRows as$r){
            $existing=$this->db->where(['conversation_id'=>$keeperId,'staff_id'=>(int)$r['staff_id']])->get($this->reads)->row_array();
            if($existing){
                $max=max((int)($existing['last_read_message_id']??0),(int)($r['last_read_message_id']??0));
                $this->db->where('id',$existing['id'])->update($this->reads,['last_read_message_id'=>$max?:null,'last_read_at'=>max((string)($existing['last_read_at']??''),(string)($r['last_read_at']??''))?:null]);
                $this->db->where('id',$r['id'])->delete($this->reads);
            }else{$this->db->where('id',$r['id'])->update($this->reads,['conversation_id'=>$keeperId]);}
        }

        $updates=[];
        if(empty($keeper['assigned_staff_id'])&&!empty($duplicate['assigned_staff_id']))$updates['assigned_staff_id']=$duplicate['assigned_staff_id'];
        $keeperTime=(string)($keeper['last_message_at']??'');$dupTime=(string)($duplicate['last_message_at']??'');
        if($dupTime!==''&&($keeperTime===''||$dupTime>$keeperTime)){
            $updates['last_message_at']=$duplicate['last_message_at'];
            $updates['last_message_preview']=$duplicate['last_message_preview'];
            $updates['last_message_from_me']=$duplicate['last_message_from_me'];
        }
        if(empty($keeper['contact_id'])&&!empty($duplicate['contact_id']))$updates['contact_id']=$duplicate['contact_id'];
        if($updates){$updates['updated_at']=date('Y-m-d H:i:s');$this->db->where('id',$keeperId)->update($this->conversations,$updates);}
        $this->db->where('id',$duplicateId)->delete($this->conversations);
    }

    public function isActiveStaff($id)
    {
        return (bool)$this->db->where(['staffid' => (int)$id, 'active' => 1])->count_all_results(db_prefix() . 'staff');
    }

    private function appendPhoneWhere($expression, array $candidates)
    {
        if (!$candidates) {
            $this->db->where('1=0', null, false);
            return;
        }
        $parts = [];
        foreach ($candidates as $candidate) {
            $needle = '%' . $this->db->escape_like_str($candidate) . '%';
            $parts[] = $expression . ' LIKE ' . $this->db->escape($needle) . " ESCAPE '!'";
        }
        $this->db->where('(' . implode(' OR ', $parts) . ')', null, false);
    }

    private function findContactRow($instance, $jid, $alt, $identity)
    {
        if ($this->db->field_exists('identity_key', $this->contacts) && $identity !== '') {
            $row=$this->db->where(['instance_name'=>$instance,'identity_key'=>$identity])->limit(1)->get($this->contacts)->row_array();
            if($row)return$row;
        }
        $this->db->where('instance_name', $instance);
        $this->db->group_start()->where('remote_jid', $jid);
        if ($alt !== '' && $this->db->field_exists('remote_jid_alt', $this->contacts)) $this->db->or_where('remote_jid_alt', $alt);
        if ($this->db->field_exists('remote_jid_alt', $this->contacts)) $this->db->or_where('remote_jid_alt', $jid);
        $this->db->group_end()->limit(1);
        return $this->db->get($this->contacts)->row_array();
    }

    private function findConversationRow($instance, $jid, $alt, $identity)
    {
        if ($this->db->field_exists('identity_key', $this->conversations) && $identity !== '') {
            $row=$this->db->where(['instance_name'=>$instance,'identity_key'=>$identity])->limit(1)->get($this->conversations)->row_array();
            if($row)return$row;
        }
        $this->db->where('instance_name', $instance);
        $this->db->group_start()->where('remote_jid', $jid);
        if ($alt !== '' && $this->db->field_exists('remote_jid_alt', $this->conversations)) $this->db->or_where('remote_jid_alt', $alt);
        if ($this->db->field_exists('remote_jid_alt', $this->conversations)) $this->db->or_where('remote_jid_alt', $jid);
        $this->db->group_end()->limit(1);
        return $this->db->get($this->conversations)->row_array();
    }

    private function mergeAltJid($primary, $existingAlt, $incomingJid, $incomingAlt)
    {
        foreach ([$incomingAlt, $incomingJid, $existingAlt] as $candidate) {
            $candidate = trim((string)$candidate);
            if ($candidate !== '' && $candidate !== $primary) return $candidate;
        }
        return null;
    }

    private function identityKey($jid, $alt, $phone, $isGroup)
    {
        if ($isGroup) return 'group:' . strtolower($jid);
        if ($phone !== '') return 'phone:' . $phone;
        $standard = $this->standardJid([$jid, $alt]);
        if ($standard !== '') return 'jid:' . strtolower($standard);
        return 'jid:' . strtolower($jid);
    }

    private function standardJid(array $jids)
    {
        foreach ($jids as $jid) if (strpos((string)$jid, '@s.whatsapp.net') !== false) return (string)$jid;
        return '';
    }

    private function phoneFromJids($jid, $alt = '')
    {
        if (strpos($jid, '@g.us') !== false) return null;
        foreach ([$alt, $jid] as $candidate) {
            $candidate = (string)$candidate;
            if (strpos($candidate, '@s.whatsapp.net') !== false) return preg_replace('/\D+/', '', preg_replace('/@.*$/', '', $candidate));
        }
        if (strpos($jid, '@lid') !== false) return null;
        return preg_replace('/\D+/', '', preg_replace('/@.*$/', '', $jid));
    }

    private function displayAddress($phone, $jid)
    {
        $digits = preg_replace('/\D+/', '', (string)$phone);
        if ($digits !== '') return $digits;
        if (strpos((string)$jid, '@g.us') !== false) return preg_replace('/@g\.us$/', '', (string)$jid);
        if (strpos((string)$jid, '@lid') !== false) return 'Contato WhatsApp';
        return preg_replace('/@.*$/', '', (string)$jid);
    }

    private function looksLikeJid($value)
    {
        return strpos((string)$value, '@') !== false;
    }

    private function preview($p)
    {
        if (!empty($p['text_content'])) return mb_substr($p['text_content'], 0, 180);
        if (!empty($p['caption'])) return mb_substr($p['caption'], 0, 180);
        $map = ['image' => '[Imagem]', 'video' => '[Vídeo]', 'audio' => '[Áudio]', 'document' => '[Documento]', 'sticker' => '[Figurinha]', 'location' => '[Localização]', 'contact' => '[Contato]'];
        return $map[$p['message_type'] ?? ''] ?? '[Mensagem]';
    }
}
