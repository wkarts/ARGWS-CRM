<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Sealed current-position report. Export authorization commits before CSV bytes. */
class Company_reports extends AdminController
{
    public function index()
    {
        if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto de empresa indisponível.',503);return;}
        if(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
        require_once APPPATH.'services/CompanyReports.php';$scope=CompanyRequestScope::context();$issuer=$scope->issuerId();$actor=$scope->staffId();$service=new CompanyReports($this->db,db_prefix());$this->output->set_header('Cache-Control: no-store');
        try{if($this->input->get('export',false)==='csv'){$csv=$service->csv($issuer,$actor);$this->output->set_header('X-Content-Type-Options: nosniff');$this->output->set_header('Content-Disposition: attachment; filename="posicao-financeira-empresa-'.(int)$issuer.'.csv"');$this->output->set_content_type('text/csv','UTF-8')->set_output($csv);return;}$report=$service->financialPosition($issuer,$actor);$this->load->view('admin/companies/reports',['issuer'=>$issuer,'report'=>$report,'error'=>null]);}
        catch(DomainException $e){if($e->getMessage()==='company_report_access_denied'){show_error('Este relatório exige acesso a relatórios, faturas, recebimentos, notas de crédito e despesas da mesma empresa.',403);return;}$this->output->set_status_header(422);$this->load->view('admin/companies/reports',['issuer'=>$issuer,'report'=>null,'error'=>'Os dados financeiros exigem revisão. Nenhum saldo ou arquivo foi disponibilizado nesta tentativa.']);}
        catch(Throwable $e){log_message('error','Company financial report failed; no financial data logged.');show_error('Não foi possível confirmar os dados do relatório. Recarregue antes de tentar novamente.',503);}
    }
}
