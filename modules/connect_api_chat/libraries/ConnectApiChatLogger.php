<?php

defined('BASEPATH') or exit('No direct script access allowed');

class ConnectApiChatLogger
{
    private $CI;
    private $table;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->table = db_prefix() . 'connect_api_chat_debug_logs';
    }

    public function enabled()
    {
        return get_option('connect_api_chat_debug_enabled') === '1';
    }

    public function log($level, $source, $message, $context = [], $httpStatus = null, $durationMs = null)
    {
        $level = strtoupper((string)$level);
        if (!in_array($level, ['DEBUG','INFO','WARNING','ERROR'], true)) $level = 'INFO';

        if ($level === 'ERROR') {
            log_message('error', '[Connect|API Chat][' . $source . '] ' . $message);
        }

        if (!$this->enabled()) return;
        if (!$this->CI->db->table_exists($this->table)) return;

        $context = $this->sanitize($context);
        $encoded = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded !== false && strlen($encoded) > 16000) {
            $encoded = substr($encoded, 0, 16000) . '... [truncado]';
        }

        $this->CI->db->insert($this->table, [
            'level' => $level,
            'source' => mb_substr((string)$source, 0, 120),
            'message' => mb_substr((string)$message, 0, 1000),
            'http_status' => $httpStatus !== null ? (int)$httpStatus : null,
            'duration_ms' => $durationMs !== null ? (int)$durationMs : null,
            'context_json' => $encoded ?: null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->trim();
    }

    public function recent($limit = 200)
    {
        if (!$this->CI->db->table_exists($this->table)) return [];
        $this->CI->db->order_by('id', 'DESC');
        $this->CI->db->limit(max(1, min(1000, (int)$limit)));
        return $this->CI->db->get($this->table)->result_array();
    }

    public function clear()
    {
        if ($this->CI->db->table_exists($this->table)) {
            $this->CI->db->empty_table($this->table);
        }
    }

    private function trim()
    {
        $max = max(50, min(2000, (int)(get_option('connect_api_chat_debug_retention') ?: 300)));
        $count = (int)$this->CI->db->count_all($this->table);
        if ($count <= $max) return;
        $remove = $count - $max;
        $ids = $this->CI->db->select('id')->from($this->table)->order_by('id','ASC')->limit($remove)->get()->result_array();
        if (!$ids) return;
        $this->CI->db->where_in('id', array_column($ids, 'id'))->delete($this->table);
    }

    private function sanitize($value, $key = '')
    {
        $sensitive = ['apikey','api_key','token','secret','authorization','password','instance_token','admin_token','x-connect-chat-secret'];
        $keyLower = strtolower((string)$key);
        foreach ($sensitive as $needle) {
            if (strpos($keyLower, $needle) !== false) return '[REDACTED]';
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) $out[$k] = $this->sanitize($v, (string)$k);
            return $out;
        }
        if (is_object($value)) return $this->sanitize((array)$value, $key);
        if (is_string($value)) {
            if (strpos($keyLower, 'base64') !== false || strpos($value, 'data:') === 0) return '[BASE64 OMITIDO]';
            return strlen($value) > 3000 ? substr($value, 0, 3000) . '... [truncado]' : $value;
        }
        return $value;
    }
}
