<?php

defined('BASEPATH') or exit('No direct script access allowed');
/** Explicit CLI job. HTTP calls cannot discover or process schedule origins. */
class Recurring_worker extends CI_Controller
{
    public function index()
    {
        if(PHP_SAPI!=='cli'||!$this->input->is_cli_request()||!class_exists('CompanyRecurringWorkerScope',false)||!CompanyRecurringWorkerScope::active()){show_404();return;}$this->load->database();require_once APPPATH.'services/CompanyRecurring.php';$result=(new CompanyRecurring($this->db,db_prefix()))->processPending(date('Y-m-d'));echo 'Recurrences completed: '.$result['completed'].'; blocked: '.$result['blocked'].PHP_EOL;require_once APPPATH.'services/CompanyExpenseRecurring.php';$decrypt=function($value){$this->load->library('encryption');return $this->encryption->decrypt($value);};$expenses=(new CompanyExpenseRecurring($this->db,db_prefix()))->processPending(gmdate('Y-m-d'),25,$decrypt);echo 'Expense recurrences completed: '.$expenses['completed'].'; blocked: '.$expenses['blocked'].PHP_EOL;require_once APPPATH.'services/CompanyInvoiceRecurring.php';$nativeInvoices=(new CompanyInvoiceRecurring($this->db,db_prefix()))->processPending(gmdate('Y-m-d'),25,$decrypt);echo 'Native invoice schedules completed: '.$nativeInvoices['completed'].'; blocked: '.$nativeInvoices['blocked'].PHP_EOL;
        require_once APPPATH.'services/CompanyTaskRecurring.php';$tasks=(new CompanyTaskRecurring($this->db,db_prefix()))->processPending(gmdate('Y-m-d H:i:s'),25);echo 'Task schedules examined: '.$tasks['examined'].'; completed: '.$tasks['completed'].'; waiting: '.$tasks['waiting'].'; blocked: '.$tasks['blocked'].PHP_EOL;
        require_once APPPATH.'services/CompanyLeads.php';$leads=(new CompanyLeads($this->db,db_prefix()))->processAllNotices(100);echo 'Lead internal notices scanned: '.$leads['scanned'].'; delivered: '.$leads['delivered'].'; suppressed: '.$leads['suppressed'].'; unchanged: '.$leads['unchanged'].'; failed and pending: '.$leads['failed'].PHP_EOL;
        require_once APPPATH.'services/CompanyHr.php';$hr=(new CompanyHr($this->db,db_prefix()))->processNotices(25);echo 'HR internal notices examined: '.$hr['examined'].'; published: '.$hr['published'].'; waiting: '.$hr['waiting'].'; blocked: '.$hr['blocked'].PHP_EOL;
    }
}
