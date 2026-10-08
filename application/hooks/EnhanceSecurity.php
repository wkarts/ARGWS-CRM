<?php

defined('BASEPATH') or exit('No direct script access allowed');

class EnhanceSecurity
{
    protected function retrieveBadData($filename)
    {
        $cache = $this->getCachedResults($filename);

        // Keep the optional deny-list check fully local. Fetching remote lists
        // on application requests disclosed the server IP and added a periodic
        // third-party call unrelated to a customer's explicit integration.
        return is_array($cache)
            ? array_values(array_filter(array_map('trim', $cache), static function ($value) {
                return $value !== '';
            }))
            : [];
    }

    protected function getBadReferrers()
    {
        return $this->retrieveBadData('bad-referrers');
    }

    protected function getBadIps()
    {
        return $this->retrieveBadData('bad-ip-addresses');
    }

    protected function getBadUserAgents()
    {
        return $this->retrieveBadData('bad-user-agents');
    }

    protected function getRealIpAddr()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            //to check ip is pass from proxy
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }

    protected function getCachedResults($filename)
    {
        $path = $this->cachePath($filename);

        if (!file_exists($path)) {
            return false;
        }

        $cache = include($path);

        return is_array($cache) ? $cache : false;
    }

    protected function cachePath($filename)
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . $filename . '.php';
    }

    public function protect()
    {
        if (! defined('APP_ENHANCE_SECURITY') || (defined('APP_ENHANCE_SECURITY') && !APP_ENHANCE_SECURITY)) {
            return;
        }

        if (in_array($_SERVER['HTTP_USER_AGENT'], $this->getBadUserAgents())) {
            $this->forbidden();
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? null;

        if ($referer && in_array($referer, $this->getBadReferrers())) {
            $this->forbidden();
        }

        if (in_array($this->getRealIpAddr(), $this->getBadIps())) {
            $this->forbidden();
        }
    }

    protected static function forbidden()
    {
        $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
        header($protocol . ' 403 Forbidden');
        exit();
    }
}
