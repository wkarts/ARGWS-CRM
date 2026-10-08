<?php
defined("BASEPATH") or exit("No direct script access allowed");

class Finance extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model("finance_model");
        $this->load->model("currencies_model");
        $this->load->model("category_model"); // Modelo de categorias
        $this->load->model("banco_model"); // Modelo de bancos
    }

    public function dashboard()
{
    // Obtém o ano atual
    $current_year = date('Y');

    // Obtém o símbolo da moeda base
    $currency = $this->currencies_model->get_base_currency();
    $currency_symbol = $currency ? $currency->symbol : '$';

    // Calcula o total de receitas e despesas do ano atual
    $total_revenue = $this->finance_model->calculate_total_revenue($current_year);
    $total_expense = $this->finance_model->calculate_total_expense($current_year);
    $total_cash = $total_revenue - $total_expense;

    // Obtém todas as transações da tabela de conciliações do ano atual
    $transactions = $this->finance_model->get_all_conciliacoes($current_year);
    if (!$transactions) {
        $transactions = [];
    }

    // Obtenha as categorias e bancos para o modal de inclusão manual
    $categorias = $this->category_model->get_all_categories();
    $bancos = $this->banco_model->get_all_banks();

    // Dados para a visualização
    $data["total_revenue"] = $total_revenue;
    $data["total_expense"] = $total_expense;
    $data["total_cash"] = $total_cash;
    $data["currency_symbol"] = $currency_symbol;
    $data["transactions"] = $transactions;
    $data["categorias"] = $categorias;
    $data["bancos"] = $bancos;

    $this->load->view("finance/dashboard", $data);
}

    // public function dashboard()
    // {
    //     // Obtém o símbolo da moeda base
    //     $currency = $this->currencies_model->get_base_currency();
    //     $currency_symbol = $currency ? $currency->symbol : '$';
    
    //     // Calcula o total de receitas e despesas usando o modelo Finance_model
    //     $total_revenue = $this->finance_model->calculate_total_revenue();
    //     $total_expense = $this->finance_model->calculate_total_expense();
    //     $total_cash = $total_revenue - $total_expense;
    
    //     // Obtém todas as transações da tabela de conciliações
    //     $transactions = $this->finance_model->get_all_conciliacoes();
    //     // Verifique se $transactions foi preenchido. Caso contrário, defina-o como um array vazio.
    //     if (!$transactions) {
    //         $transactions = [];
    //     }
    
    //     // Obtenha as categorias e bancos para o modal de inclusão manual
    //     $categorias = $this->category_model->get_all_categories();
    //     $bancos = $this->banco_model->get_all_banks();
    
    //     // Dados para a visualização
    //     $data["total_revenue"] = $total_revenue;
    //     $data["total_expense"] = $total_expense;
    //     $data["total_cash"] = $total_cash;
    //     $data["currency_symbol"] = $currency_symbol;
    //     $data["transactions"] = $transactions; // Envia as transações para a view
    //     $data["categorias"] = $categorias; // Envia as categorias para a view
    //     $data["bancos"] = $bancos; // Envia os bancos para a view
    
    //     $this->load->view("finance/dashboard", $data);
    // }

    // public function dashboard()
    // {
    //     // Obtém o símbolo da moeda base
    //     $currency = $this->currencies_model->get_base_currency();
    //     $currency_symbol = $currency ? $currency->symbol : '$';
    
    //     // Calcula o total de receitas e despesas usando a tabela conciliações
    //     $total_revenue = $this->finance_model->calculate_total_revenue();
    //     $total_expense = $this->finance_model->calculate_total_expense();
    //     $total_cash = $total_revenue - $total_expense;
    
    //     // Obtém todas as transações da tabela conciliações
    //     $transactions = $this->finance_model->get_all_conciliacoes();
    
    //     // Ordena as transações pela data
    //     usort($transactions, function ($a, $b) {
    //         $dateA = isset($a["data"]) && !empty($a["data"]) ? strtotime($a["data"]) : 0;
    //         $dateB = isset($b["data"]) && !empty($b["data"]) ? strtotime($b["data"]) : 0;
    //         return $dateA - $dateB;
    //     });
    
    //     // Obtenha as categorias e bancos para o modal de inclusão manual
    //     $categorias = $this->category_model->get_all_categories();
    //     $bancos = $this->banco_model->get_all_banks();
    
    //     // Dados para a visualização
    //     $data["total_revenue"] = $total_revenue;
    //     $data["total_expense"] = $total_expense;
    //     $data["total_cash"] = $total_cash;
    //     $data["currency_symbol"] = $currency_symbol;
    //     $data["transactions"] = $transactions; // Adiciona $transactions para a view
    //     $data["categorias"] = $categorias; // Envia as categorias para a view
    //     $data["bancos"] = $bancos; // Envia os bancos para a view
    
    //     $this->load->view("finance/dashboard", $data);
    // }

    public function get_projection()
    {
        $this->load->model("finance_model");
    
        // Obter o total de receita e despesa por mês da tabela de conciliações
        $projection = $this->finance_model->calculate_monthly_projection();
    
        echo json_encode($projection);
    }

    public function adicionar_manual()
    {
        if ($this->input->is_ajax_request()) {
            // Gera ID aleatório para evitar conflitos
            $random_id = rand(100000, 999999); // Gera um número aleatório entre 100000 e 999999
            $ofx_id = 'MANUAL-' . uniqid(); // Prefixo para identificar transações manuais
    
            // Preparação dos dados para inserção manual
            $data = [
                "id" => $random_id,
                "ofx_id" => $ofx_id,
                "data" => $this->input->post("data"),
                "descricao" => $this->input->post("descricao"),
                "valor" => $this->input->post("tipo") === "saida" ? -abs($this->input->post("valor")) : abs($this->input->post("valor")),
                "tipo" => $this->input->post("tipo"),
                "categoria_id" => $this->input->post("categoria"),
                "banco_id" => $this->input->post("banco_destino") ?? null,
                "banco_origem_id" => $this->input->post("banco"),
                "conciliado" => 1, // Sempre conciliado ao adicionar manualmente
                "tipo_conciliacao" => "Manual"
            ];
    
            // Log para verificação dos dados de inserção
            log_activity('Dados para conciliação manual: ' . json_encode($data));
    
            // Inserir no banco
            $this->load->model("finance_model");
            if ($this->finance_model->add_conciliacao($data)) {
                echo json_encode(["status" => "success"]);
            } else {
                log_activity("Erro ao salvar conciliação no banco de dados.");
                echo json_encode([
                    "status" => "error",
                    "message" => "Erro ao salvar a conciliação no banco de dados."
                ]);
            }
        }
    }


    public function get_invoice_forecast_data()
{
    // Carrega o model de finanças
    $this->load->model('finance_model');
    
    // Obtém o forecast dos próximos 30 dias
    $forecast_data = $this->finance_model->get_invoice_forecast_30_days();
    
    // Formata os dados para o gráfico
    $data = [];
    $current_date = new DateTime();
    for ($i = 0; $i < 30; $i++) {
        $date = $current_date->format('Y-m-d');
        $data[$date] = 0; // Inicializa com zero
        $current_date->modify('+1 day');
    }

    foreach ($forecast_data as $row) {
        $data[$row['duedate']] = (float) $row['daily_total'];
    }

    echo json_encode($data);
}
    

}
