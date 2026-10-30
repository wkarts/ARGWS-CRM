<?php

defined('BASEPATH') or exit('No direct script access allowed');

class ConnectApiConnectorClient
{
    private $baseUrl;
    private $instanceName;
    private $instanceToken;
    private $timeout;
    private $verifyTls;

    public function __construct($config = [])
    {
        $this->configure($config);
    }

    public function configure($config)
    {
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
        $this->instanceName = (string) ($config['instance_name'] ?? '');
        $this->instanceToken = (string) ($config['instance_token'] ?? '');
        $this->timeout = max(5, min(120, (int) ($config['timeout'] ?? 30)));
        $this->verifyTls = !isset($config['verify_tls']) || (bool) $config['verify_tls'];
        return $this;
    }

    public function state()
    {
        return $this->request('GET', '/instance/connectionState/' . rawurlencode($this->instanceName));
    }

    public function info()
    {
        return $this->request('GET', '/instance/fetchInstances?instanceName=' . rawurlencode($this->instanceName));
    }

    public function connectQr()
    {
        return $this->request('GET', '/instance/connect/' . rawurlencode($this->instanceName));
    }

    public function connectPairing($number)
    {
        $number = preg_replace('/\D+/', '', (string) $number);
        return $this->request('GET', '/instance/connect/' . rawurlencode($this->instanceName) . '?number=' . rawurlencode($number));
    }

    public function restart()
    {
        return $this->request('POST', '/instance/restart/' . rawurlencode($this->instanceName));
    }

    public function sendText($number, $text)
    {
        return $this->request('POST', '/message/sendText/' . rawurlencode($this->instanceName), [
            'number' => (string) $number,
            'text' => (string) $text,
        ]);
    }

    public function request($method, $path, $payload = null)
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('A extensão PHP cURL é obrigatória para comunicação com Connect|API.');
        }

        if ($this->baseUrl === '' || $this->instanceName === '' || $this->instanceToken === '') {
            throw new RuntimeException('URL, nome da instância e token da instância são obrigatórios.');
        }

        $ch = curl_init();
        $options = [
            CURLOPT_URL => $this->baseUrl . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'apikey: ' . $this->instanceToken,
            ],
            CURLOPT_SSL_VERIFYPEER => $this->verifyTls,
            CURLOPT_SSL_VERIFYHOST => $this->verifyTls ? 2 : 0,
        ];
        if ($payload !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            throw new RuntimeException('Falha de comunicação com Connect|API: ' . $error);
        }
        $decoded = json_decode((string) $body, true);
        $response = json_last_error() === JSON_ERROR_NONE ? $decoded : ['raw' => (string) $body];
        if ($status < 200 || $status >= 300) {
            $message = is_array($response) && isset($response['message']) ? $response['message'] : ('HTTP ' . $status);
            if (is_array($message)) $message = implode('; ', $message);
            throw new RuntimeException((string) $message);
        }
        return is_array($response) ? $response : [];
    }
}
