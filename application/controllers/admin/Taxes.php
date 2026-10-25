<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Taxes extends AdminController
{
    private ?NativeCompanyCatalogUi $companyCatalog=null;
    public function __construct()
    {
        parent::__construct();
        if(CompanyRequestScope::nativeFor($this)){
            require_once APPPATH.'services/NativeCompanyCatalogUi.php';$context=CompanyRequestScope::context();
            $this->companyCatalog=new NativeCompanyCatalogUi($this->db,db_prefix(),$context->issuerId(),$context->staffId(),$this);
            if(!$this->companyCatalog->allowed('taxes','view'))throw new DomainException('company_catalog_denied');return;
        }
        $this->load->model('taxes_model');
        if (!is_admin()) {
            access_denied('Taxes');
        }
    }

    /* List all taxes */
    public function index()
    {
        if($this->companyCatalog!==null){
            if($this->input->is_ajax_request()){
                $draw=$this->input->post('draw');if(!is_scalar($draw)||!preg_match('/^[0-9]{1,6}$/D',(string)$draw))throw new DomainException('company_catalog_invalid');
                $rows=$this->companyCatalog->taxes();$data=['draw'=>(int)$draw,'iTotalRecords'=>count($rows),'iTotalDisplayRecords'=>count($rows),'aaData'=>[]];
                foreach($rows as$row){
                    $actions='';if($this->companyCatalog->allowed('taxes','edit'))$actions.='<button type="button" class="btn btn-default btn-icon" data-toggle="modal" data-target="#tax_modal" data-id="'.(int)$row['id'].'" data-revision="'.e((string)$row['revision']).'"><i class="fa-regular fa-pen-to-square"></i><span class="sr-only">'.e(_l('edit')).'</span></button>';
                    if($this->companyCatalog->allowed('taxes','delete'))$actions.='<form method="post" action="'.e(admin_url('taxes/delete/'.$row['id'])).'" class="tw-inline"><input type="hidden" name="_company_context" value="'.e(CompanyRequestScope::token()).'"><input type="hidden" name="_native_company_csrf" value="'.e(CompanyRequestScope::nativeCsrfToken($this)).'"><input type="hidden" name="revision" value="'.e((string)$row['revision']).'"><button class="btn btn-danger btn-icon" type="submit"><i class="fa-regular fa-trash-can"></i><span class="sr-only">'.e(_l('delete')).'</span></button></form>';
                    $data['aaData'][]=[(int)$row['id'],e($row['name']),e($row['taxrate']),$actions];
                }
                echo json_encode($data,JSON_THROW_ON_ERROR);return;
            }
            $this->load->view('admin/taxes/manage',['title'=>_l('taxes'),'native_can_create'=>$this->companyCatalog->allowed('taxes','create')]);return;
        }
        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data('taxes');
        }
        $data['title'] = _l('taxes');
        $this->load->view('admin/taxes/manage', $data);
    }

    /* Add or edit tax / ajax */
    public function manage()
    {
        if($this->companyCatalog!==null){
            CompanyRequestScope::assertNativeMutation($this);
            $this->companyCatalog->saveTax(['name'=>$this->input->post('name',false),'taxrate'=>$this->input->post('taxrate',false)],$this->input->post('taxid'),$this->input->post('revision'));
            echo json_encode(['success'=>true,'message'=>_l('updated_successfully',_l('tax'))],JSON_THROW_ON_ERROR);return;
        }
        if ($this->input->post()) {
            $data = $this->input->post();
            if ($data['taxid'] == '') {
                $success = $this->taxes_model->add($data);
                $message = '';
                if ($success == true) {
                    $message = _l('added_successfully', _l('tax'));
                }
                echo json_encode([
                    'success' => $success,
                    'message' => $message,
                ]);
            } else {
                $success = $this->taxes_model->edit($data);
                $message = '';
                if (is_array($success) && isset($success['tax_is_using_expenses'])) {
                    $success = false;
                    $message = _l('tax_is_used_in_expenses_warning');
                } elseif ($success == true) {
                    $message = _l('updated_successfully', _l('tax'));
                }
                echo json_encode([
                    'success' => $success,
                    'message' => $message,
                ]);
            }
        }
    }

    /* Delete tax from database */
    public function delete($id)
    {
        if($this->companyCatalog!==null){
            CompanyRequestScope::assertNativeMutation($this);$this->companyCatalog->delete('tax',$id,$this->input->post('revision'));
            redirect(CompanyRequestScope::adminUrl($this,'taxes'),'location',303);return;
        }
        if (!$id) {
            redirect(admin_url('taxes'));
        }
        $response = $this->taxes_model->delete($id);
        if (is_array($response) && isset($response['referenced'])) {
            set_alert('warning', _l('is_referenced', _l('tax_lowercase')));
        } elseif ($response == true) {
            set_alert('success', _l('deleted', _l('tax')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('tax_lowercase')));
        }
        redirect(admin_url('taxes'));
    }

    public function tax_name_exists()
    {
        if($this->companyCatalog!==null){
            $name=$this->input->post('name',false);$id=$this->input->post('taxid');$available=true;
            if(!is_string($name)||!is_scalar($id))throw new DomainException('company_catalog_invalid');
            foreach($this->companyCatalog->taxes()as$row)if($row['name']===$name&&(string)$row['id']!==(string)$id)$available=false;
            echo json_encode($available);return;
        }
        if ($this->input->post()) {
            $tax_id = $this->input->post('taxid');
            if ($tax_id != '') {
                $this->db->where('id', $tax_id);
                $_current_tax = $this->db->get(db_prefix().'taxes')->row();
                if ($_current_tax->name == $this->input->post('name')) {
                    echo json_encode(true);
                    die();
                }
            }
            $this->db->where('name', $this->input->post('name'));
            $total_rows = $this->db->count_all_results(db_prefix().'taxes');
            if ($total_rows > 0) {
                echo json_encode(false);
            } else {
                echo json_encode(true);
            }
            die();
        }
    }
}
