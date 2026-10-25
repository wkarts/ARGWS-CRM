<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** Native catalogue identity. Banking credentials remain issuer/profile-scoped. */
class Banco_brasil_gateway extends App_gateway
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('banco_brasil');
        $this->setName('Banco do Brasil');
        $this->setSettings([
            ['name'=>'currencies','label'=>'settings_paymentmethod_currencies','default_value'=>'BRL'],
            ['name'=>'description','label'=>'settings_paymentmethod_description','type'=>'textarea',
             'default_value'=>'Cobrança Banco do Brasil vinculada à empresa da fatura. Conexões, ambiente e emissão são administrados no perfil autorizado da empresa. A consulta do instrumento ocorre somente no portal autorizado da empresa, nunca no checkout global legado.'],
        ]);
        // Listing a newly distributed gateway never opts existing invoices into it.
        foreach ($this->settings as &$setting) {
            if ($setting['name']==='default_selected') { $setting['default_value']=0; }
        }
        unset($setting);
    }

    /**
     * Keep the native catalogue/settings entry, never offer this identity in the
     * legacy checkout. Persisted switches are preserved, not rewritten. Company
     * portal publication has its own issuer/profile-scoped authorization.
     */
    public function initMode($modes)
    {
        $modes = parent::initMode($modes);
        foreach ($modes as &$mode) {
            if (($mode['id'] ?? null) === $this->getId()) {
                $mode['active'] = '0';
                $mode['selected_by_default'] = '0';
            }
        }
        unset($mode);
        return $modes;
    }

    /** Defense in depth: never dereference or hydrate the supplied invoice. */
    public function process_payment($data)
    {
        set_alert('warning', 'Consulte o boleto Banco do Brasil pelo portal autorizado da empresa emissora. O checkout global não disponibiliza este instrumento.');
        return false;
    }
}
