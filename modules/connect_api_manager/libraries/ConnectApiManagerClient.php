<?php

defined('BASEPATH') or exit('No direct script access allowed');

class ConnectApiManagerClient
{
    private $baseUrl;
    private $apiKey;
    private $timeout;
    private $verifyTls;
    private $lastStatus = 0;
    private $lastError = '';

    public function __construct($config = [])
    {
        $this->configure($config);
    }

    public function configure($config)
    {
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
        $this->apiKey = (string) ($config['api_key'] ?? '');
        $this->timeout = max(5, min(120, (int) ($config['timeout'] ?? 30)));
        $this->verifyTls = !isset($config['verify_tls']) || (bool) $config['verify_tls'];
        return $this;
    }

    public function getLastStatus()
    {
        return $this->lastStatus;
    }

    public function getLastError()
    {
        return $this->lastError;
    }

    public function fetchInstances()
    {
        return $this->request('GET', '/instance/fetchInstances');
    }

    public function fetchInstance($instanceName)
    {
        return $this->request('GET', '/instance/fetchInstances?instanceName=' . rawurlencode((string) $instanceName));
    }

    public function connectionState($instanceName)
    {
        return $this->request('GET', '/instance/connectionState/' . rawurlencode($instanceName));
    }

    public function createInstance($payload)
    {
        return $this->request('POST', '/instance/create', $payload);
    }

    public function connect($instanceName, $number = null)
    {
        $path = '/instance/connect/' . rawurlencode($instanceName);
        if ($number !== null && $number !== '') {
            $path .= '?number=' . rawurlencode(preg_replace('/\D+/', '', $number));
        }
        return $this->request('GET', $path);
    }

    public function restart($instanceName)
    {
        return $this->request('POST', '/instance/restart/' . rawurlencode($instanceName));
    }

    public function logout($instanceName)
    {
        return $this->request('DELETE', '/instance/logout/' . rawurlencode($instanceName));
    }

    public function delete($instanceName)
    {
        return $this->request('DELETE', '/instance/delete/' . rawurlencode($instanceName));
    }

    public function sendText($instanceName, $number, $text)
    {
        return $this->request('POST', '/message/sendText/' . rawurlencode($instanceName), [
            'number' => preg_replace('/\D+/', '', (string) $number),
            'text'   => (string) $text,
        ]);
    }

    public function request($method, $path, $payload = null)
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('A extensão PHP cURL é obrigatória para comunicação com Connect|API.');
        }

        $this->lastStatus = 0;
        $this->lastError = '';

        if ($this->baseUrl === '' || $this->apiKey === '') {
            throw new RuntimeException('URL da API e token administrativo são obrigatórios.');
        }

        $ch = curl_init();
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'apikey: ' . $this->apiKey,
        ];

        $options = [
            CURLOPT_URL            => $this->baseUrl . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_HTTPHEADER     => $headers,
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

        $this->lastStatus = $status;
        if ($errno) {
            $this->lastError = $error;
            throw new RuntimeException('Falha de comunicação com Connect|API: ' . $error);
        }

        $decoded = json_decode((string) $body, true);
        $response = json_last_error() === JSON_ERROR_NONE ? $decoded : ['raw' => (string) $body];

        if ($status < 200 || $status >= 300) {
            $message = $this->extractMessage($response, 'HTTP ' . $status);
            $this->lastError = $message;
            throw new RuntimeException($message);
        }

        return is_array($response) ? $response : [];
    }

    private function extractMessage($response, $fallback)
    {
        if (is_array($response)) {
            foreach (['message', 'error', 'response'] as $key) {
                if (isset($response[$key]) && is_string($response[$key]) && $response[$key] !== '') {
                    return $response[$key];
                }
            }
            if (isset($response['message']) && is_array($response['message'])) {
                return implode('; ', $response['message']);
            }
        }
        return $fallback;
    }
}
