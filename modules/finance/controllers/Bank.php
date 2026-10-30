<?php
defined("BASEPATH") or exit("No direct script access allowed");

class Bank extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model("bank_model");
    }

    // Lista todos os bancos
    public function index()
    {
        $this->load->model("currencies_model");
        $currency = $this->currencies_model->get_base_currency();

        // Definindo um valor padrão se a moeda base não for encontrada
        if (!$currency) {
            $currency = new stdClass();
            $currency->symbol = 'R$'; // Defina o símbolo padrão desejado
        }

        $data["currency"] = $currency;
        $data["banks"] = $this->bank_model->get_all_banks();
        $this->load->view("finance/banks_list", $data);
    }

    // Exibe o formulário para adicionar/editar banco
    public function manage($id = null)
    {
        if ($this->input->post()) {
            $data = $this->input->post();

            if ($id) {
                $this->bank_model->update_bank($id, $data);
                set_alert("success", "Banco atualizado com sucesso.");
            } else {
                $insert_id = $this->bank_model->add_bank($data);
                set_alert("success", "Banco adicionado com sucesso.");
            }
            redirect(admin_url("finance/bank"));
        }

        $data["bank"] = $id ? $this->bank_model->get_bank($id) : null;
        $this->load->view("finance/bank_form", $data);
    }

    // Deleta um banco
    public function delete($id)
    {
        $this->bank_model->delete_bank($id);
        set_alert("success", "Banco deletado com sucesso.");
        redirect(admin_url("finance/bank"));
    }
}
