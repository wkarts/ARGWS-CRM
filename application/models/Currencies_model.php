<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Currencies_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    private function currencyReferenceTables():array
    {
        $ci=get_instance();
        if(CompanyRequestScope::nativeFor($ci)){
            if(hooks()->has_filter('tables_with_currency')!==false)throw new DomainException('native_legacy_hook_review_required');
            require_once APPPATH.'services/NativeCurrencyReferenceCatalog.php';return NativeCurrencyReferenceCatalog::tables(db_prefix());
        }
        return$this->app->get_tables_with_currency();
    }

    /**
     * @param  integer ID (optional)
     * @return mixed
     * Get currency object based on passed id if not passed id return array of all currencies
     */
    public function get($id = false)
    {
        if(CompanyRequestScope::nativeFor(get_instance())){
            if($id!==false){$id=IssuerPolicy::id($id);return IssuerStorage::query($this->db,'SELECT * FROM '.db_prefix().'currencies WHERE id=?',[$id])->row();}
            return IssuerStorage::query($this->db,'SELECT * FROM '.db_prefix().'currencies ORDER BY id')->result_array();
        }
        if (is_numeric($id)) {
            $this->db->where('id', $id);

            $currency = $this->db->get(db_prefix() . 'currencies')->row();
            $this->app_object_cache->set('currency-' . $currency->name, $currency);

            return $currency;
        }

        $currencies = $this->app_object_cache->get('currencies-data');

        if (!$currencies && !is_array($currencies)) {
            $currencies = $this->db->get(db_prefix() . 'currencies')->result_array();
            $this->app_object_cache->add('currencies-data', $currencies);
        }

        return $currencies;
    }

    /**
     * Get currency by name/iso code
     * @since  2.3.2
     * @param  string $name currency name/iso code
     * @return object
     */
    public function get_by_name($name)
    {
        $currency = $this->app_object_cache->get('currency-' . $name);
        if (!$currency && !is_object($currency)) {
            $this->db->where('name', $name);
            $currency = $this->db->get(db_prefix() . 'currencies')->row();

            $this->app_object_cache->add('currency-' . $name, $currency);
        }

        return $currency;
    }

    /**
     * @param array $_POST data
     * @return boolean
     */
    public function add($data)
    {
        if(CompanyRequestScope::nativeFor(get_instance())){
            require_once APPPATH.'services/issuers/IssuerTransaction.php';require_once APPPATH.'services/issuers/IssuerResourceAccess.php';
            return IssuerTransaction::run($this->db,db_prefix(),['issuers_lock','issuers_registry','issuers_installation_identity','staff','currencies','activity_log'],function()use($data){
                $actor=IssuerPolicy::id(get_staff_user_id());$access=new IssuerResourceAccess($this->db,db_prefix());if(!$access->isMaster($actor))throw new DomainException('company_currency_owner_required');
                $result=$this->insertCurrency($data);if(!$access->isMaster($actor))throw new DomainException('company_currency_owner_required');return$result;
            });
        }
        return$this->insertCurrency($data);
    }
    private function insertCurrency($data)
    {
        unset($data['currencyid']);
        $data['name'] = strtoupper($data['name']);
        $this->db->insert(db_prefix() . 'currencies', $data);
        if(CompanyRequestScope::nativeFor(get_instance())){
            $connection=$this->db->conn_id??$this->db->pdo??null;$sqlite=$connection instanceof PDO&&$connection->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
            $changed=IssuerStorage::query($this->db,$sqlite?'SELECT changes() n':'SELECT ROW_COUNT() n')->row_array();if((string)($changed['n']??'')!=='1')throw new RuntimeException('issuer_currency_write_unconfirmed');
        }
        $insert_id = $this->db->insert_id();
        if ($insert_id) {
            log_activity('New Currency Added [ID: ' . $data['name'] . ']');

            return true;
        }

        return false;
    }

    /**
     * @param  array $_POST data
     * @return boolean
     * Update currency values
     */
    public function edit($data)
    {
        $currencyid = $data['currencyid'];
        unset($data['currencyid']);
        $data['name'] = strtoupper($data['name']);
        require_once APPPATH.'services/CompanyCurrencyUnits.php';
        $changed=(new CompanyCurrencyUnits($this->db,db_prefix()))->editCatalog($currencyid,$data['name'],function()use($currencyid,$data):bool{
            if(CompanyRequestScope::nativeFor(get_instance())&&!(new IssuerResourceAccess($this->db,db_prefix()))->isMaster(get_staff_user_id()))throw new DomainException('company_currency_owner_required');
            $this->db->where('id', $currencyid);
            $this->db->update(db_prefix() . 'currencies', $data);
            return $this->db->affected_rows() > 0;
        });
        if($changed){log_activity('Currency Updated [' . $data['name'] . ']');}
        return $changed;
    }

    /**
     * @param  integer ID
     * @return mixed
     * Delete currency from database, if used return array with key referenced
     */
    public function delete($id)
    {
        foreach ($this->currencyReferenceTables() as $tt) {
            if (is_reference_in_table($tt['field'], $tt['table'], $id)) {
                return [
                    'referenced' => true,
                ];
            }
        }

        $currency = $this->get($id);
        if ($currency->isdefault == 1) {
            return [
                'is_default' => true,
            ];
        }

        require_once APPPATH.'services/CompanyCurrencyUnits.php';
        try{
            $deleted=(new CompanyCurrencyUnits($this->db,db_prefix()))->deleteCatalog($id,function()use($id):bool{
                if(CompanyRequestScope::nativeFor(get_instance())&&!(new IssuerResourceAccess($this->db,db_prefix()))->isMaster(get_staff_user_id()))throw new DomainException('company_currency_owner_required');
                $id=IssuerPolicy::id($id);$column='rate_currency_'.$id;
                if(IssuerStorage::fieldExists($this->db,$column,db_prefix().'items')&&IssuerStorage::query($this->db,'SELECT id FROM '.db_prefix().'items WHERE '.$column.' IS NOT NULL LIMIT 1')->row_array())throw new DomainException('company_currency_catalog_locked');
                $this->db->where('id', $id);
                $this->db->delete(db_prefix() . 'currencies');
                return $this->db->affected_rows() > 0;
            });
        }catch(DomainException $error){
            if($error->getMessage()!=='company_currency_catalog_locked')throw $error;
            return ['referenced'=>true];
        }
        if ($deleted) {
            // Keep empty historical currency columns; deleting a catalogue row never drops storage.
            log_activity('Currency Deleted [' . $id . ']');

            return true;
        }

        return false;
    }

    /**
     * @param  integer ID
     * @return boolean
     * Make currency your base currency for better using reports if found invoices with more then 1 currency
     */
    public function make_base_currency($id)
    {
        if(CompanyRequestScope::nativeFor(get_instance())){
            require_once APPPATH.'services/issuers/IssuerTransaction.php';require_once APPPATH.'services/issuers/IssuerResourceAccess.php';
            $id=IssuerPolicy::id($id);$actor=IssuerPolicy::id(get_staff_user_id());$refs=$this->currencyReferenceTables();$tables=['issuers_lock','issuers_registry','issuers_installation_identity','staff','currencies','issuers_audit'];foreach($refs as$ref)$tables[]=substr($ref['table'],strlen(db_prefix()));
            return IssuerTransaction::run($this->db,db_prefix(),array_values(array_unique($tables)),function()use($id,$actor){
                $access=new IssuerResourceAccess($this->db,db_prefix());if(!$access->isMaster($actor))throw new DomainException('company_currency_owner_required');
                $defaults=IssuerStorage::query($this->db,'SELECT id FROM '.db_prefix().'currencies WHERE isdefault=1 FOR UPDATE')->result_array();if(count($defaults)!==1)throw new DomainException('company_catalog_invalid_currency');
                $result=$this->setBaseCurrency($id);
                if($result===true){
                    $rows=IssuerStorage::query($this->db,'SELECT id FROM '.db_prefix().'currencies WHERE isdefault=1')->result_array();if(count($rows)!==1||(int)$rows[0]['id']!==$id)throw new RuntimeException('issuer_currency_write_unconfirmed');
                    $meta=json_encode(['currency_id'=>$id,'operation_nonce'=>bin2hex(random_bytes(16))],JSON_THROW_ON_ERROR);$issuer=$access->primaryId();if(!$issuer)throw new DomainException('company_currency_owner_required');
                    IssuerStorage::query($this->db,'INSERT INTO '.db_prefix().'issuers_audit(issuer_id,actor_id,subject_id,event,metadata_json,created_at) VALUES(?,?,?,?,?,?)',[$issuer,$actor,$id,'native_base_currency_changed',$meta,gmdate('Y-m-d H:i:s')]);$audit=$this->db->insert_id();if(!IssuerStorage::query($this->db,'SELECT id FROM '.db_prefix().'issuers_audit WHERE id=? AND metadata_json=?',[$audit,$meta])->row_array())throw new RuntimeException('issuer_currency_write_unconfirmed');
                }
                if(!$access->isMaster($actor))throw new DomainException('company_currency_owner_required');return$result;
            });
        }
        return$this->setBaseCurrency($id);
    }
    private function setBaseCurrency($id)
    {
        $base = $this->get_base_currency();
        foreach ($this->currencyReferenceTables() as $tt) {
            if (is_reference_in_table($tt['field'], $tt['table'], $base->id)) {
                return [
                    'has_transactions_currency' => true,
                ];
            }
        }

        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'currencies', [
            'isdefault' => 1,
        ]);
        if ($this->db->affected_rows() > 0) {
            $this->db->where('id !=', $id);
            $this->db->update(db_prefix() . 'currencies', [
                'isdefault' => 0,
            ]);

            return true;
        }

        return false;
    }

    /**
     * @return object
     * Get base currency
     */
    public function get_base_currency()
    {
        $this->db->where('isdefault', 1);

        return $this->db->get(db_prefix() . 'currencies')->row();
    }

    /**
     * @param  integer ID
     * @return string
     * Get the symbol from the currency
     */
    public function get_currency_symbol($id)
    {
        if (!is_numeric($id)) {
            $id = $this->get_base_currency()->id;
        }

        $currencies = $this->app_object_cache->get('currencies-data');
        if ($currencies) {
            foreach ($currencies as $currency) {
                if ($id == $currency['id']) {
                    return $currency['symbol'];
                }
            }
        }

        $this->db->select('symbol');
        $this->db->from(db_prefix() . 'currencies');
        $this->db->where('id', $id);

        return $this->db->get()->row()->symbol;
    }
}
