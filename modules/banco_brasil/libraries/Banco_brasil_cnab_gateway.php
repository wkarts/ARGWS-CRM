<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__.'/Banco_brasil_gateway.php';
class Banco_brasil_cnab_gateway extends Banco_brasil_gateway
{
    public function __construct(){parent::__construct();$this->setId('banco_brasil_cnab');$this->setName('Banco do Brasil — CNAB');}
}
