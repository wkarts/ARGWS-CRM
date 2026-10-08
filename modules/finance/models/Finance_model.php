<?php
defined("BASEPATH") or exit("No direct script access allowed");

class Finance_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }


    public function get_transacoes_para_conciliacao()
    {
        // Faturas (entradas)
        $this->db->select('tblinvoices.id, tblinvoices.date, tblinvoices.total as valor, CONCAT("Fatura - ", tblinvoices.id, " - ", tblclients.company) as descricao, "entrada" as tipo');
        $this->db->from('tblinvoices');
        $this->db->join('tblclients', 'tblclients.userid = tblinvoices.clientid', 'left');
        $this->db->where_in('tblinvoices.status', [1, 2]); // Status 1 e 2 para conciliar
        $faturas = $this->db->get()->result_array();

        // Despesas (saídas)
        $this->db->select('tblexpenses.id, tblexpenses.date, tblexpenses.amount as valor, CONCAT(tblexpenses.expense_name, " - ", tblexpenses.note) as descricao, "saida" as tipo');
        $this->db->from('tblexpenses');
        $despesas = $this->db->get()->result_array();

        // Combinar e ordenar por data
        $transacoes = array_merge($faturas, $despesas);
        usort($transacoes, function ($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        return $transacoes;
    }

    public function get_categorias_por_tipo($tipo)
    {
        $this->db->select("id, name");
        $this->db->from("tblcategories");
        $this->db->where("type", $tipo);
        $this->db->where("father_category IS NOT NULL", null, false);
        return $this->db->get()->result_array();
    }

    public function get_bancos()
    {
        $this->db->select("id, name");
        $this->db->from("tblbanks");
        return $this->db->get()->result_array();
    }

    public function verificar_conciliacao($data, $valor, $descricao)
    {
        $this->db->where('data', $data);
        $this->db->where('valor', $valor);
        $this->db->where('descricao', $descricao);
        $query = $this->db->get('tblconciliacoes');

        return $query->num_rows() > 0;
    }

    public function get_all_conciliacoes()
    {
        $this->db->select("tblconciliacoes.*, tblbanks.name as banco_nome");
        $this->db->from("tblconciliacoes");
        $this->db->join("tblbanks", "tblbanks.id = tblconciliacoes.banco_origem_id", "left");
        $this->db->order_by("tblconciliacoes.data", "ASC");
    
        return $this->db->get()->result_array();
    }


    public function get_faturas_previsao()
{
    $this->db->select('date, total as valor');
    $this->db->from('tblinvoices');
    $this->db->where('status', 1); // Status 1 significa fatura a receber
    return $this->db->get()->result_array();
}

public function get_despesas()
{
    $this->db->select('date, amount as valor');
    $this->db->from('tblexpenses');
    return $this->db->get()->result_array();
}




    // Calcula o total de receita (faturas pagas)
    public function calculate_total_revenue()
    {
        $this->db->select_sum("total");
        $this->db->from("tblinvoices");
        $this->db->where("status", 2); // Assumindo que status '2' representa faturas pagas
        $query = $this->db->get();
        $result = $query->row();
        return $result ? $result->total : 0;
    }

    // Adiciona uma transação à tabela de conciliações
    public function add_conciliacao($data)
    {
        return $this->db->insert("tblconciliacoes", $data);
    }

    // Calcula o total de despesa
    public function calculate_total_expense()
    {
        $this->db->select_sum("amount");
        $this->db->from("tblexpenses");
        $query = $this->db->get();
        $result = $query->row();
        return $result ? $result->amount : 0;
    }

    //---------------

    public function calculate_total_revenue_from_conciliacoes()
    {
        $this->db->select_sum("valor");
        $this->db->from("tblconciliacoes");
        $this->db->where("tipo", "entrada");
        $query = $this->db->get();
        $result = $query->row();
        return $result ? $result->valor : 0;
    }
    
    public function calculate_total_expense_from_conciliacoes()
    {
        $this->db->select_sum("valor");
        $this->db->from("tblconciliacoes");
        $this->db->where("tipo", "saida");
        $query = $this->db->get();
        $result = $query->row();
        return $result ? $result->valor : 0;
    }


    public function calculate_monthly_projection()
{
    // Inicializa a projeção mensal
    $projection = array_fill(1, 12, ['faturamento' => 0, 'despesa' => 0]);

    // Obter entradas (faturamento)
    $this->db->select("MONTH(data) as mes, SUM(valor) as total");
    $this->db->from("tblconciliacoes");
    $this->db->where("tipo", "entrada");
    $this->db->group_by("mes");
    $entradas = $this->db->get()->result_array();

    foreach ($entradas as $entrada) {
        $projection[(int)$entrada["mes"]]['faturamento'] = (float)$entrada["total"];
    }

    // Obter saídas (despesas)
    $this->db->select("MONTH(data) as mes, SUM(valor) as total");
    $this->db->from("tblconciliacoes");
    $this->db->where("tipo", "saida");
    $this->db->group_by("mes");
    $saidas = $this->db->get()->result_array();

    foreach ($saidas as $saida) {
        $projection[(int)$saida["mes"]]['despesa'] = (float)$saida["total"];
    }

    return $projection;
}


//     public function get_all_conciliacoes()
// {
//     // Seleciona as colunas da tabela `tblconciliacoes` e faz a junção com `tblbanks` para obter o nome do banco
//     $this->db->select("tblconciliacoes.*, tblbanks.name as banco_nome");
//     $this->db->from("tblconciliacoes");
//     $this->db->join("tblbanks", "tblbanks.id = tblconciliacoes.banco_origem_id", "left"); // Junção com tabela de bancos
//     $this->db->order_by("tblconciliacoes.data", "ASC");

//     return $this->db->get()->result_array();
// }


    // Obtenha todas as faturas pagas como entradas financeiras
    public function get_all_invoices_as_entries()
    {
        $this->db->select("tblinvoices.id, tblinvoices.date AS date, tblinvoices.clientid, tblinvoices.total AS valor, tblclients.company AS company_name");
        $this->db->from("tblinvoices");
        $this->db->join("tblclients", "tblclients.userid = tblinvoices.clientid", "left");
        $this->db->where("tblinvoices.status", 2); // Status '2' significa fatura paga
        $query = $this->db->get();

        $entries = [];
        foreach ($query->result() as $invoice) {
            $descricao = "Fatura - Cliente ID: " . $invoice->clientid;
            if ($invoice->company_name) {
                $descricao .= " - " . $invoice->company_name;
            }

            $entries[] = [
                "date" => $invoice->date,
                "descricao" => $descricao,
                "valor" => $invoice->valor,
                "tipo" => "entrada",
            ];
        }
        return $entries;
    }


    public function calculate_10_day_projection()
{
    $projection = [];
    $today = date("Y-m-d");

    // Inicializa os valores dos próximos 10 dias com saldo 0 para receita e despesa
    for ($i = 0; $i < 10; $i++) {
        $date = date("Y-m-d", strtotime($today . " +$i days"));
        $projection[$date] = [
            'receita' => 0,
            'despesa' => 0
        ];
    }

    

    // Obter entradas (receita) e saídas (despesas) dos próximos 10 dias
    $this->db->select("data, tipo, SUM(valor) as total");
    $this->db->from("tblconciliacoes");
    $this->db->where("data >=", $today);
    $this->db->where("data <", date("Y-m-d", strtotime($today . " +10 days")));
    $this->db->group_by(["data", "tipo"]);
    $result = $this->db->get()->result_array();

    // Preenche a projeção com os valores de receita e despesa
    foreach ($result as $row) {
        $date = $row["data"];
        if ($row["tipo"] == "entrada") {
            $projection[$date]['receita'] = (float)$row["total"];
        } elseif ($row["tipo"] == "saida") {
            $projection[$date]['despesa'] = (float)$row["total"];
        }
    }

    return $projection;
}


public function get_invoice_forecast_30_days()
{
    $current_date = date('Y-m-d');
    $end_date = date('Y-m-d', strtotime('+30 days'));

    // Busca o total de faturas pendentes nos próximos 30 dias
    $this->db->select('duedate, SUM(total) as daily_total');
    $this->db->from('tblinvoices');
    $this->db->where('status', 1); // Status 1 para faturas a receber
    $this->db->where('duedate >=', $current_date);
    $this->db->where('duedate <=', $end_date);
    $this->db->group_by('duedate');
    $this->db->order_by('duedate', 'ASC');

    $query = $this->db->get();
    return $query->result_array();
}

    // Calcula a projeção mensal de faturamento e despesas
    // public function calculate_monthly_projection()
    // {
    //     $projection = [];

    //     for ($month = 1; $month <= 12; $month++) {
    //         $projection[$month] = [
    //             'faturamento' => 0,
    //             'despesa' => 0
    //         ];
    //     }

    //     // Obter faturas recorrentes
    //     $faturas_recorrentes = $this->db->select("valor, recurrency_type, date")
    //         ->from("tblinvoices")
    //         ->where("recurring", 1)
    //         ->get()
    //         ->result_array();
    //     foreach ($faturas_recorrentes as $fatura) {
    //         $valor = $fatura['valor'];
    //         $recurrency_type = $fatura['recurrency_type'];
    //         $start_month = (int)date("m", strtotime($fatura['date']));

    //         for ($month = $start_month; $month <= 12; $month += ($recurrency_type == 'monthly' ? 1 : ($recurrency_type == 'quarterly' ? 3 : 12))) {
    //             $projection[$month]['faturamento'] += $valor;
    //         }
    //     }

    //     // Obter despesas recorrentes
    //     $despesas_recorrentes = $this->db->select("amount, recurrency_type, date")
    //         ->from("tblexpenses")
    //         ->where("recurring", 1)
    //         ->get()
    //         ->result_array();
    //     foreach ($despesas_recorrentes as $despesa) {
    //         $valor = $despesa['amount'];
    //         $recurrency_type = $despesa['recurrency_type'];
    //         $start_month = (int)date("m", strtotime($despesa['date']));

    //         for ($month = $start_month; $month <= 12; $month += ($recurrency_type == 'monthly' ? 1 : ($recurrency_type == 'quarterly' ? 3 : 12))) {
    //             $projection[$month]['despesa'] += $valor;
    //         }
    //     }

    //     return $projection;
    // }
}
