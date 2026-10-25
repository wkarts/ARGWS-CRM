<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Spam_filters extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if(CompanyRequestScope::nativeFor($this)){$this->nativeSpam();return;}

        if (! is_admin()) {
            access_denied('Spam Filters');
        }

        $this->load->model('spam_filters_model');
    }

    private function nativeSpam():NativeCompanySpamController{require_once APPPATH.'services/NativeCompanySpamController.php';return new NativeCompanySpamController($this);}

    public function view($rel_type, $filter_type = '')
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeSpam()->view($rel_type,$filter_type);return;}
        if ($this->input->is_ajax_request()) {
            $aColumns = [
                'value',
            ];
            $sIndexColumn = 'id';
            $sTable       = db_prefix() . 'spam_filters';
            $result       = data_tables_init($aColumns, $sIndexColumn, $sTable, [], [
                'AND type ="' . $filter_type . '" AND rel_type="' . $rel_type . '"',
            ], [
                'id',
            ]);
            $output  = $result['output'];
            $rResult = $result['rResult'];

            foreach ($rResult as $aRow) {
                $output['aaData'][] = require APPPATH.'views/admin/spam_filters/table_row.php';
            }
            echo json_encode($output);

            exit();
        }

        $data['rel_type'] = $rel_type;
        $data['title']    = _l('spam_filters');
        $this->load->view('admin/spam_filters/list', $data);
    }

    public function filter($type)
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeSpam()->filter($type);return;}
        $message = '';
        $success = false;
        if ($this->input->post()) {
            if ($this->input->post('id')) {
                $success = $this->spam_filters_model->edit($this->input->post());
                if ($success == true) {
                    $message = _l('updated_successfully', _l('spam_filter'));
                }
            } else {
                $success = $this->spam_filters_model->add($this->input->post(), $type);
                if ($success == true) {
                    $message = _l('added_successfully', _l('spam_filter'));
                }
            }
        }
        echo json_encode([
            'success' => $success,
            'message' => $message,
        ]);
    }

    public function delete($id, $type)
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeSpam()->delete($id,$type);return;}
        $success = $this->spam_filters_model->delete($id, $type);
        if ($success) {
            set_alert('success', _l('deleted', _l('spam_filter')));
        }

        redirect(admin_url('spam_filters/view/' . $type));
    }
}
