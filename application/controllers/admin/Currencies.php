<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Currencies extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->assertInstallationOwner();
        $this->load->model('currencies_model');
        if (!is_admin()) {
            access_denied('Currencies');
        }
    }

    private function assertInstallationOwner():void
    {
        require_once APPPATH.'services/issuers/IssuerResourceAccess.php';
        $actor=IssuerPolicy::id(get_staff_user_id());
        if(!(new IssuerResourceAccess($this->db,db_prefix()))->isMaster($actor))throw new DomainException('company_currency_owner_required');
    }
    private function assertMutation():void
    {
        $this->assertInstallationOwner();
        // Central admits these finite native routes for principal and named contexts alike.
        CompanyRequestScope::assertNativeMutation($this);
    }
    private function prepareCurrencyColumns():void
    {
        $this->assertInstallationOwner();require_once APPPATH.'services/NativeCompanyCatalogCurrencySchema.php';
        NativeCompanyCatalogCurrencySchema::ensure($this->db,db_prefix(),NativeCompanyCatalogCurrencySchema::installedPrecision($this->db,db_prefix()));
    }

    /* List all currencies */
    public function index()
    {
        if ($this->input->is_ajax_request()) {
            if(CompanyRequestScope::nativeFor($this)){
                $draw=$this->input->post('draw');if(!is_scalar($draw)||!preg_match('/^[0-9]{1,6}$/D',(string)$draw))throw new DomainException('company_catalog_invalid');
                $rows=IssuerStorage::query($this->db,'SELECT id,name,symbol,isdefault,placement,thousand_separator,decimal_separator FROM '.db_prefix().'currencies ORDER BY name,id')->result_array();
                $total=count($rows);$search=$this->input->post('search');$query=is_array($search)?($search['value']??''):'';if(!is_string($query)||strlen($query)>191||preg_match('//u',$query)!==1)throw new DomainException('company_catalog_invalid');
                if($query!=='')$rows=array_values(array_filter($rows,static fn($r)=>mb_stripos($r['name'],$query)!==false||mb_stripos($r['symbol'],$query)!==false));$filtered=count($rows);
                $start=$this->input->post('start')??'0';$length=$this->input->post('length')??'25';if(!is_scalar($start)||!preg_match('/^[0-9]{1,6}$/D',(string)$start)||!is_scalar($length)||!preg_match('/^(?:-1|[1-9][0-9]{0,3})$/D',(string)$length))throw new DomainException('company_catalog_invalid');
                $order=$this->input->post('order');if($order!==null){if(!is_array($order)||count($order)!==1||!in_array((string)($order[0]['column']??''),['0','1'],true)||!in_array($order[0]['dir']??null,['asc','desc'],true))throw new DomainException('company_catalog_invalid');$field=$order[0]['column']==0?'name':'symbol';$direction=$order[0]['dir']==='desc'?-1:1;usort($rows,static fn($a,$b)=>$direction*(strcmp($a[$field],$b[$field])?:((int)$a['id']<=>(int)$b['id'])));}
                $rows=array_slice($rows,(int)$start,(int)$length===-1?null:(int)$length);
                $this->assertInstallationOwner();$this->load->view('admin/tables/currencies',['native_currency_rows'=>$rows,'native_currency_draw'=>(int)$draw,'native_currency_total'=>$total,'native_currency_filtered'=>$filtered]);return;
            }
            $this->app->get_table_data('currencies');
        }
        $data['title'] = _l('currencies');
        $this->load->view('admin/currencies/manage', $data);
    }

    /* Update currency or add new / ajax */
    public function manage()
    {
        $this->assertMutation();
        if ($this->input->post()) {
            $data = $this->input->post();
            if(array_diff(array_keys($data),['currencyid','name','symbol','placement','decimal_separator','thousand_separator']))throw new DomainException('company_catalog_invalid');
            if(!isset($data['currencyid'],$data['name'],$data['symbol'],$data['placement'],$data['decimal_separator'],$data['thousand_separator'])||!is_string($data['name'])||!preg_match('/^[A-Za-z]{3}$/D',$data['name'])||!is_string($data['symbol'])||mb_strlen($data['symbol'])>10||preg_match('/[\x00-\x1f\x7f]/',$data['symbol'])||!in_array($data['placement'],['before','after'],true)||!in_array($data['decimal_separator'],['.',','],true)||!in_array($data['thousand_separator'],['.',',',"'",'',' '],true)||$data['decimal_separator']===$data['thousand_separator'])throw new DomainException('company_catalog_invalid');
            if ($data['currencyid'] == '') {
                $success = $this->currencies_model->add($data);
                $message = '';
                if ($success == true) {
                    $this->prepareCurrencyColumns();
                    $message = _l('added_successfully', _l('currency'));
                }
                echo json_encode([
                    'success' => $success,
                    'message' => $message,
                ]);
            } else {
                try{$success = $this->currencies_model->edit($data);}
                catch(DomainException $error){
                    if(!in_array($error->getMessage(),['company_currency_catalog_locked','company_currency_snapshot_required','company_currency_catalog_changed'],true))throw $error;
                    echo json_encode(['success'=>false,'message'=>'O código desta moeda está vinculado ao histórico de empresas e não pode ser redefinido. Restaure o código congelado ou crie outra moeda com um novo ID.']);
                    return;
                }
                $message = '';
                if ($success == true) {
                    $message = _l('updated_successfully', _l('currency'));
                }
                echo json_encode([
                    'success' => $success,
                    'message' => $message,
                ]);
            }
        }
    }

    /* Make currency your base currency */
    public function make_base_currency($id)
    {
        $this->assertMutation();
        if (!$id) {
            redirect(admin_url('currencies'));
        }
        $response = $this->currencies_model->make_base_currency($id);
        if (is_array($response) && isset($response['has_transactions_currency'])) {
            set_alert('danger', _l('has_transactions_currency_base_change'));
        } elseif ($response == true) {
            set_alert('success', _l('base_currency_set'));
        }
        redirect(admin_url('currencies'));
    }

    /* Delete currency from database */
    public function delete($id)
    {
        $this->assertMutation();
        if (!$id) {
            redirect(admin_url('currencies'));
        }
        $response = $this->currencies_model->delete($id);
        if (is_array($response) && isset($response['referenced'])) {
            set_alert('warning', _l('is_referenced', _l('currency_lowercase')));
        } elseif (is_array($response) && isset($response['is_default'])) {
            set_alert('warning', _l('cant_delete_base_currency'));
        } elseif ($response == true) {
            set_alert('success', _l('deleted', _l('currency')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('currency_lowercase')));
        }
        redirect(admin_url('currencies'));
    }

    /* Get symbol by currency id passed */
    public function get_currency_symbol($id)
    {
        if ($this->input->is_ajax_request()) {
            echo json_encode([
                'symbol' => $this->currencies_model->get_currency_symbol($id),
            ]);
        }
    }
}
