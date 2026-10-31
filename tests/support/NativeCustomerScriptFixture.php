<?php
/** Synthetic boundaries only; renders the complete production client_js.php. */
declare(strict_types=1);
error_reporting(E_ALL);
set_error_handler(static function(int $severity,string $message,string $file,int $line):never{throw new ErrorException($message,0,$severity,$file,$line);});
define('BASEPATH',__DIR__.'/');
$native_customers=true;
$native_contact_consent_enabled=true;
$company_clients_csrf='synthetic-csrf';
$native_map_revision=str_repeat('a',64);
function _l(string $key,...$args):string{return $key;}
function e($value):string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function base_url(string $path=''):string{return 'https://crm.example.invalid/'.$path;}
function get_option(...$args):never{throw new LogicException('Global options must not hydrate the company view');}
function hooks():never{throw new LogicException('Global hooks must not hydrate the company view');}
require dirname(__DIR__,2).'/application/views/admin/clients/client_js.php';
