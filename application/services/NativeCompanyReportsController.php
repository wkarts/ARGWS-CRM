<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Sealed current position and bounded event journal. Exports commit audit before CSV bytes. */
final class NativeCompanyReportsController
{
    public static function handle($ci)
    {
        if(!CompanyRequestScope::sealedFor($ci)){show_error('Contexto de empresa indisponível.',503);return;}
        if(!in_array($ci->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
        $scope=CompanyRequestScope::context();$issuer=$scope->issuerId();$actor=$scope->staffId();$ci->output->set_header('Cache-Control: no-store');
        if($issuer===null){show_error('Selecione uma empresa autorizada antes de consultar ou exportar relatórios.',403);return;}
        $kind=$ci->input->get('report',false);
        if($kind===null||$kind==='position'){self::position($ci,$issuer,$actor);return;}
        if($kind==='ledger'){self::ledger($ci,$issuer,$actor);return;}
        show_error('Selecione um relatório disponível.',422);
    }

    private static function position($ci,int $issuer,int $actor):void
    {
        require_once APPPATH.'services/CompanyReports.php';$service=new CompanyReports($ci->db,db_prefix());
        try{if($ci->input->get('export',false)==='csv'){$csv=$service->csv($issuer,$actor);$ci->output->set_header('X-Content-Type-Options: nosniff');$ci->output->set_header('Content-Disposition: attachment; filename="posicao-financeira-empresa-'.(int)$issuer.'.csv"');$ci->output->set_content_type('text/csv','UTF-8')->set_output($csv);return;}$report=$service->financialPosition($issuer,$actor);$ci->load->view('admin/reports/company_financial',['issuer'=>$issuer,'report'=>$report,'error'=>null]);}
        catch(DomainException $e){if($e->getMessage()==='company_report_access_denied'){self::denied();return;}$ci->output->set_status_header(422);$ci->load->view('admin/reports/company_financial',['issuer'=>$issuer,'report'=>null,'error'=>'Os dados financeiros exigem revisão. Nenhum saldo ou arquivo foi disponibilizado nesta tentativa.']);}
        catch(Throwable $e){log_message('error','Company financial report failed; no financial data logged.');show_error('Não foi possível confirmar os dados do relatório. Recarregue antes de tentar novamente.',503);}
    }

    private static function denied():void
    {
        show_error('Este relatório exige acesso a relatórios, faturas, recebimentos, notas de crédito e despesas da mesma empresa.',403);
    }

    private static function filter($ci,string $name,?string $default,int $limit):?string
    {
        $value=$ci->input->get($name,false);if($value===null)return $default;
        if(!is_string($value)||strlen($value)>$limit)throw new DomainException('company_ledger_invalid_filter');return $value;
    }

    private static function ledger($ci,int $issuer,int $actor):void
    {
        require_once APPPATH.'services/CompanyLedgerReports.php';$service=new CompanyLedgerReports($ci->db,db_prefix());
        $filters=['from'=>gmdate('Y-m-01'),'to'=>gmdate('Y-m-d'),'through'=>gmdate('Y-m-d H:i:s'),'watermark'=>null,'after'=>'0'];$report=null;$error=null;
        try{
            foreach(['from'=>10,'to'=>10,'through'=>19,'watermark'=>19,'after'=>19] as $name=>$limit)$filters[$name]=self::filter($ci,$name,$filters[$name],$limit);
            // A datetime-local control may omit zero seconds. Its displayed clock is explicitly UTC.
            if(preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D',$filters['through']))$filters['through'].=':00';
            foreach(['watermark','after'] as $name)if($filters[$name]!==null&&!preg_match('/^(?:0|[1-9][0-9]{0,18})$/D',$filters[$name]))throw new DomainException('company_ledger_invalid_filter');
            if($filters['after']!=='0'&&$filters['watermark']===null)throw new DomainException('company_ledger_invalid_filter');
            $format=self::filter($ci,'export',null,3);if($format!==null&&$format!=='csv')throw new DomainException('company_ledger_invalid_filter');
            $args=[$issuer,$actor,$filters['from'],$filters['to'],$filters['through'],$filters['watermark'],$filters['after']];
            if($format==='csv'){
                $result=$service->csv(...$args);$ci->output->set_header('X-Content-Type-Options: nosniff');$ci->output->set_header('Content-Disposition: attachment; filename="eventos-financeiros-empresa-'.(int)$issuer.'-'.$filters['from'].'-'.$filters['to'].'-apos-'.$filters['after'].'.csv"');
                $ci->output->set_header('X-Company-Ledger-Watermark: '.$result['watermark']);$ci->output->set_header('X-Company-Ledger-Recorded-Through: '.$result['recorded_through_utc']);$ci->output->set_header('X-Company-Ledger-Next: '.($result['next']===null?'none':$result['next']));
                $ci->output->set_content_type('text/csv','UTF-8')->set_output($result['csv']);return;
            }
            $report=$service->report(...$args);$filters=['from'=>$report['from'],'to'=>$report['to'],'through'=>$report['recorded_through_utc'],'watermark'=>(string)$report['watermark'],'after'=>(string)$report['after']];
        }catch(DomainException $e){
            if($e->getMessage()==='company_ledger_access_denied'){self::denied();return;}
            $ci->output->set_status_header(422);$error=match($e->getMessage()){'company_ledger_invalid_filter'=>'Confira as datas, o corte UTC e o marcador da consulta. O intervalo deve ser de até 366 dias e o corte não pode estar no futuro.','company_ledger_period_limit'=>'O período excede 10.000 eventos. Reduza o intervalo antes de consultar ou exportar. Nenhum arquivo foi gerado.',default=>'Os eventos financeiros exigem revisão. Nenhum total ou arquivo foi disponibilizado nesta tentativa.'};
        }catch(Throwable $e){log_message('error','Company event journal failed; no financial data logged.');show_error('Não foi possível confirmar os eventos do relatório. Recarregue antes de tentar novamente.',503);return;}
        $ci->load->view('admin/reports/company_ledger',['issuer'=>$issuer,'ledger'=>$report,'filters'=>$filters,'error'=>$error]);
    }
}
