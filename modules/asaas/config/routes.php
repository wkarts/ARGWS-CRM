<?php


$route['asaas/checkout'] = "checkout/index";

$route['asaas/gateways/callback'] = "gateways/callback/index";
$route['asaas/health'] = "health";
$route['asaas/gateways/callback/integration/(:any)'] = 'gateways/callback/integration/$1';
