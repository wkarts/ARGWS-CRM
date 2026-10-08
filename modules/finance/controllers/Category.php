<?php
defined("BASEPATH") or exit("No direct script access allowed");

class Category extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model("category_model");
    }

    // Listar todas as categorias
    public function index()
    {
        $data["categories"] = $this->category_model->get_all_categories();
        $this->load->view("finance/categories_list", $data);
    }

    // Formulário de criação/edição de categoria
    public function manage($id = null)
    {
        if ($this->input->post()) {
            $data = $this->input->post();
            if ($id) {
                $this->category_model->update_category($id, $data);
                set_alert("success", "Categoria atualizada com sucesso.");
            } else {
                $this->category_model->add_category($data);
                set_alert("success", "Categoria adicionada com sucesso.");
            }
            redirect(admin_url("finance/category"));
        }
    
        $data["category"] = $id ? $this->category_model->get_category($id) : null;
        $data["categories"] = $this->category_model->get_all_categories(); // Carrega todas as categorias
    
        $this->load->view("finance/category_form", $data);
    }

    // Excluir categoria
    public function delete($id)
    {
        $this->category_model->delete_category($id);
        set_alert("success", "Categoria excluída com sucesso.");
        redirect(admin_url("finance/category"));
    }
}
