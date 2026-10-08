<?php

defined('BASEPATH') or exit('No direct script access allowed');

class ConnectApiChatNormalizer
{
    public function normalizeMessage($record)
    {
        if (!is_array($record)) return null;
        if (isset($record['data']) && is_array($record['data']) && !isset($record['key'])) $record = $record['data'];

        $key = $this->decode($record['key'] ?? []);
        $rawMessage = $this->decode($record['message'] ?? []);
        $message = $this->unwrapMessage($rawMessage);
        $context = $this->decode($record['contextInfo'] ?? []);
        if (!is_array($key)) $key=[];
        if (!is_array($rawMessage)) $rawMessage=[];
        if (!is_array($message)) $message=[];
        if (!is_array($context)) $context=[];

        $remoteJid = $this->normalizeJid($key['remoteJid'] ?? $record['remoteJid'] ?? $record['remote_jid'] ?? null);
        $remoteJidAlt = $this->normalizeJid($key['remoteJidAlt'] ?? $key['senderPn'] ?? $record['remoteJidAlt'] ?? $record['remote_jid_alt'] ?? $record['senderPn'] ?? null);
        $addressingMode = $key['addressingMode'] ?? $record['addressingMode'] ?? null;
        $apiId = $key['id'] ?? $record['api_message_id'] ?? $record['messageId'] ?? $record['id'] ?? null;
        if (!$remoteJid || !$apiId) return null;

        $fromMeRaw = $key['fromMe'] ?? $record['fromMe'] ?? $record['from_me'] ?? false;
        $fromMe = is_string($fromMeRaw) ? in_array(strtolower($fromMeRaw), ['1','true','yes','on'], true) : (bool)$fromMeRaw;
        $participant = $this->normalizeJid($key['participant'] ?? $record['participant'] ?? null);
        $type = (string)($record['messageType'] ?? $record['type'] ?? $this->detectType($message));
        // Connect|API/Baileys pode informar o wrapper como messageType (ephemeral/viewOnce),
        // enquanto o conteúdo real já foi desempacotado acima. Nesses casos o tipo visual
        // precisa vir do conteúdo interno, como extractMessageContent() faz no Baileys.
        if (in_array(strtolower($type), ['ephemeralmessage','viewoncemessage','viewoncemessagev2','documentwithcaptionmessage','editedmessage'], true)) {
            $type = $this->detectType($message);
        }
        $timestamp = $this->timestamp($record['messageTimestamp'] ?? $record['timestamp'] ?? time());
        $text = $this->text($message, $record);
        $caption = $this->caption($message, $record);
        $mediaUrl = null; // 1.1.2: URLs mmg/MinIO nunca são persistidas como fonte do navegador; a mídia é resolvida por messageId e armazenada localmente sob demanda.
        $fileName = $record['fileName'] ?? $record['file_name'] ?? $this->deep($message, 'fileName');
        $mime = $record['mimetype'] ?? $this->deep($message, 'mimetype');
        $quoted = $record['quoted_api_message_id'] ?? $context['stanzaId'] ?? $this->deep($rawMessage, 'stanzaId');
        $thumb = $this->normalizeBinaryBase64($this->deep($message, 'jpegThumbnail'));
        $waveform = $this->normalizeBinaryBase64($this->deep($message, 'waveform'));
        $duration = (int)($this->deep($message, 'seconds') ?? 0);
        $width = (int)($this->deep($message, 'width') ?? 0);
        $height = (int)($this->deep($message, 'height') ?? 0);

        return [
            'api_message_id'=>(string)$apiId,
            'remote_jid'=>(string)$remoteJid,
            'remote_jid_alt'=>$remoteJidAlt ? (string)$remoteJidAlt : null,
            'addressing_mode'=>$addressingMode ? (string)$addressingMode : null,
            'participant_jid'=>$participant ? (string)$participant : null,
            'from_me'=>$fromMe,
            'sender_name'=>(string)($record['pushName'] ?? $record['senderName'] ?? ''),
            'message_type'=>$this->friendlyType($type, $message),
            'text_content'=>$text,
            'media_url'=>$mediaUrl ? (string)$mediaUrl : null,
            'caption'=>$caption,
            'file_name'=>$fileName ? (string)$fileName : null,
            'mimetype'=>$mime ? (string)$mime : null,
            'thumbnail_base64'=>$thumb ?: null,
            'media_duration'=>$duration > 0 ? $duration : null,
            'media_waveform'=>$waveform ?: null,
            'media_width'=>$width > 0 ? $width : null,
            'media_height'=>$height > 0 ? $height : null,
            'message_timestamp'=>$timestamp,
            'status'=>(string)($record['status'] ?? 'received'),
            'quoted_api_message_id'=>$quoted ? (string)$quoted : null,
            'raw_payload'=>json_encode($record, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        ];
    }

    public function normalizeChat($record)
    {
        if (!is_array($record)) return null;
        $jid = $this->normalizeJid($record['remoteJid'] ?? $record['remote_jid'] ?? $record['id'] ?? null);
        if (!$jid || strpos((string)$jid, '@') === false) return null;
        $lastMessage = null;
        if (!empty($record['lastMessage']) && is_array($record['lastMessage'])) $lastMessage = $this->normalizeMessage($record['lastMessage']);
        $alt = $this->normalizeJid($record['remoteJidAlt'] ?? $record['remote_jid_alt'] ?? ($lastMessage['remote_jid_alt'] ?? null));
        $display = $record['name'] ?? $record['pushName'] ?? $record['subject'] ?? null;
        if ((!is_string($display) || trim($display)==='') && $lastMessage && empty($lastMessage['from_me'])) $display = $lastMessage['sender_name'] ?? null;
        if (!is_string($display) || trim($display)==='' || $display==='Você') $display = $this->displayFromJids((string)$jid, (string)$alt);
        return [
            'remote_jid'=>(string)$jid,
            'remote_jid_alt'=>$alt ? (string)$alt : null,
            'display_name'=>(string)$display,
            'profile_pic_url'=>$record['profilePicUrl'] ?? $record['profile_pic_url'] ?? null,
            'is_group'=>strpos((string)$jid, '@g.us') !== false,
            'last_message'=>$lastMessage,
        ];
    }

    public function normalizeContact($record)
    {
        if (!is_array($record)) return null;
        $jid = $this->normalizeJid($record['remoteJid'] ?? $record['remote_jid'] ?? $record['id'] ?? null);
        if (!$jid || strpos((string)$jid, '@') === false) return null;
        $alt = $this->normalizeJid($record['remoteJidAlt'] ?? $record['remote_jid_alt'] ?? $record['senderPn'] ?? null);
        return [
            'remote_jid'=>(string)$jid,
            'remote_jid_alt'=>$alt ? (string)$alt : null,
            'display_name'=>(string)($record['pushName'] ?? $record['name'] ?? $this->displayFromJids((string)$jid,(string)$alt)),
            'profile_pic_url'=>$record['profilePicUrl'] ?? $record['profile_pic_url'] ?? null,
            'is_group'=>strpos((string)$jid, '@g.us') !== false,
        ];
    }

    public function eventName($event)
    {
        return strtoupper(str_replace(['.','-'], '_', (string)$event));
    }

    private function decode($value)
    {
        if (is_array($value)) return $value;
        if (is_string($value)) { $d=json_decode($value,true); if (json_last_error()===JSON_ERROR_NONE) return $d; }
        return [];
    }

    private function normalizeJid($jid)
    {
        $jid=trim((string)$jid); if($jid==='')return null;
        // Baileys pode incluir device id antes de @lid/@s.whatsapp.net, ex. 113...:95@lid.
        $jid=preg_replace('/:\\d+(?=@(?:lid|s\\.whatsapp\\.net)$)/i','',$jid);
        return $jid;
    }

    private function unwrapMessage($message)
    {
        if(!is_array($message))return[];
        // Mesmo princípio usado pelo projeto Baileys de referência: retirar wrappers até chegar ao conteúdo real.
        for($i=0;$i<8;$i++){
            $next=null;
            foreach(['ephemeralMessage','viewOnceMessage','viewOnceMessageV2','documentWithCaptionMessage','editedMessage'] as $wrapper){
                if(isset($message[$wrapper]['message'])&&is_array($message[$wrapper]['message'])){$next=$message[$wrapper]['message'];break;}
            }
            if($next===null)break;
            $message=$next;
        }
        return$message;
    }

    private function timestamp($value)
    {
        if (is_array($value)) $value = $value['low'] ?? $value['seconds'] ?? time();
        $v=(int)$value; if ($v > 20000000000) $v=(int)floor($v/1000); return $v ?: time();
    }

    private function detectType($m)
    {
        foreach (array_keys($m) as $k) if (substr($k,-7)==='Message') return $k;
        return 'text';
    }

    private function friendlyType($type, $m)
    {
        $s=strtolower((string)$type);
        if (strpos($s,'image')!==false) return 'image';
        if (strpos($s,'video')!==false || strpos($s,'ptv')!==false) return 'video';
        if (strpos($s,'audio')!==false) return 'audio';
        if (strpos($s,'document')!==false) return 'document';
        if (strpos($s,'sticker')!==false) return 'sticker';
        if (strpos($s,'location')!==false) return 'location';
        if (strpos($s,'contact')!==false) return 'contact';
        if (strpos($s,'reaction')!==false) return 'reaction';
        if (strpos($s,'poll')!==false) return 'poll';
        if (strpos($s,'event')!==false) return 'event';
        return 'text';
    }

    private function text($m, $r)
    {
        $poll=null;if(isset($m['pollCreationMessageV3']['name'])){$opts=[];foreach(($m['pollCreationMessageV3']['options']??[]) as$o){if(!empty($o['optionName']))$opts[]=$o['optionName'];}$poll='Enquete: '.$m['pollCreationMessageV3']['name'].($opts?' · '.implode(' / ',$opts):'');}
        $event=null;if(isset($m['eventMessage']['name']))$event='Evento: '.$m['eventMessage']['name'];
        $contact=null;if(isset($m['contactMessage']))$contact=$m['contactMessage']['displayName']??$m['contactMessage']['vcard']??'Contato';
        foreach ([
            $m['conversation'] ?? null,
            $m['extendedTextMessage']['text'] ?? null,
            $m['interactiveMessage']['body']['text'] ?? null,
            $m['templateMessage']['hydratedTemplate']['hydratedContentText'] ?? null,
            $m['templateMessage']['hydratedFourRowTemplate']['hydratedContentText'] ?? null,
            $m['templateMessage']['interactiveMessageTemplate']['body']['text'] ?? null,
            $m['buttonsResponseMessage']['selectedDisplayText'] ?? null,
            $m['listResponseMessage']['title'] ?? null,
            $m['templateButtonReplyMessage']['selectedDisplayText'] ?? $m['templateButtonReplyMessage']['selectedId'] ?? null,
            $m['interactiveResponseMessage']['body']['text'] ?? null,
            $m['reactionMessage']['text'] ?? null,
            $m['protocolMessage']['editedMessage']['conversation'] ?? null,
            $contact,$poll,$event,
            $r['text'] ?? null,
            $r['text_content'] ?? null,
        ] as $v) if (is_string($v) && trim($v)!=='') return $v;
        return null;
    }

    private function caption($m,$r)
    {
        foreach ([
            $m['imageMessage']['caption'] ?? null,
            $m['videoMessage']['caption'] ?? null,
            $m['ptvMessage']['caption'] ?? null,
            $m['documentMessage']['caption'] ?? null,
            $m['interactiveMessage']['header']['imageMessage']['caption'] ?? null,
            $m['interactiveMessage']['header']['videoMessage']['caption'] ?? null,
            $r['caption'] ?? null,
        ] as $v) if (is_string($v) && trim($v)!=='') return $v;
        return null;
    }

    private function normalizeBinaryBase64($value)
    {
        if(is_string($value)){if(strpos($value,'data:')===0&&strpos($value,',')!==false)$value=substr($value,strpos($value,',')+1);return preg_replace('/\\s+/','',$value);}
        if(is_array($value)){
            $bin='';foreach($value as$v){if(is_numeric($v))$bin.=chr(((int)$v)&255);}return$bin!==''?base64_encode($bin):null;
        }
        return null;
    }

    private function deep($arr,$key)
    {
        if (!is_array($arr)) return null;
        foreach ($arr as $k=>$v) { if ($k===$key) return $v; if (is_array($v)) { $f=$this->deep($v,$key); if ($f!==null) return $f; } }
        return null;
    }

    private function displayFromJids($jid, $alt='')
    {
        foreach ([$alt,$jid] as $candidate) if (strpos((string)$candidate,'@s.whatsapp.net') !== false) return preg_replace('/@.*$/','',(string)$candidate);
        if (strpos((string)$jid,'@g.us') !== false) return preg_replace('/@g\\.us$/','',(string)$jid);
        if (strpos((string)$jid,'@lid') !== false) return 'Contato WhatsApp';
        return preg_replace('/@.*$/','',(string)$jid);
    }
}
