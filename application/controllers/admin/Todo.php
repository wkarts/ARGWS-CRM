<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Todo extends AdminController
{
    private ?NativeCompanyAuxiliaryUi $companyAuxiliary = null;
    public function __construct()
    {
        parent::__construct();
        if (CompanyRequestScope::nativeFor($this)) {
            require_once APPPATH . 'services/NativeCompanyAuxiliaryUi.php';
            $context = CompanyRequestScope::context();
            $this->companyAuxiliary = new NativeCompanyAuxiliaryUi($this->db, db_prefix(), $context->issuerId(), $context->staffId());
            return;
        }
        $this->load->model('todo_model');
    }

    /* Get all staff todo items */
    public function index()
    {
        if ($this->companyAuxiliary !== null) {
            if ($this->input->is_ajax_request()) {
                $finished = $this->input->post('finished'); $page = $this->input->post('todo_page');
                if (!in_array($finished, ['0','1',0,1], true) || !preg_match('/^[0-9]{1,4}$/D', (string)$page)) {
                    throw new DomainException('company_auxiliary_invalid_input');
                }
                $rows = $this->companyAuxiliary->todos((bool)$finished, 10, (int)$page * 10);
                foreach ($rows as &$row) {
                    $row['description'] = nl2br(htmlspecialchars($row['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                    $row['dateadded'] = htmlspecialchars($row['dateadded'], ENT_QUOTES, 'UTF-8');
                }
                echo json_encode($rows, JSON_THROW_ON_ERROR); return;
            }
            require_once APPPATH . 'services/NativeCompanyAdminPresentation.php';
            NativeCompanyAdminPresentation::prepare($this);
            $counts = $this->companyAuxiliary->todoCounts();
            $this->load->view('admin/todos/all', ['bodyclass'=>'main-todo-page', 'title'=>_l('my_todos'),
                'total_pages_finished'=>ceil($counts[1]/10), 'total_pages_unfinished'=>ceil($counts[0]/10)]);
            return;
        }
        if ($this->input->is_ajax_request()) {
            echo json_encode($this->todo_model->get_todo_items($this->input->post('finished'), $this->input->post('todo_page')));
            exit;
        }
        $data['bodyclass']            = 'main-todo-page';
        $data['total_pages_finished'] = ceil(total_rows(db_prefix().'todos', [
            'finished' => 1,
            'staffid'  => get_staff_user_id(),
        ]) / $this->todo_model->getTodosLimit());
        $data['total_pages_unfinished'] = ceil(total_rows(db_prefix().'todos', [
            'finished' => 0,
            'staffid'  => get_staff_user_id(),
        ]) / $this->todo_model->getTodosLimit());
        $data['title'] = _l('my_todos');
        $this->load->view('admin/todos/all', $data);
    }

    /* Add new todo item */
    public function todo()
    {
        if ($this->companyAuxiliary !== null) {
            CompanyRequestScope::assertNativeMutation($this);
            $this->companyAuxiliary->saveTodo(['description'=>$this->input->post('description', false)], $this->input->post('todoid'), $this->input->post('revision'));
            redirect(CompanyRequestScope::adminUrl($this, 'todo'), 'location', 303); return;
        }
        if ($this->input->post()) {
            $data = $this->input->post();
            if ($data['todoid'] == '') {
                unset($data['todoid']);
                $id = $this->todo_model->add($data);
                if ($id) {
                    set_alert('success', _l('added_successfully', _l('todo')));
                }
            } else {
                $id = $data['todoid'];
                unset($data['todoid']);
                $success = $this->todo_model->update($id, $data);
                if ($success) {
                    set_alert('success', _l('updated_successfully', _l('todo')));
                }
            }

            redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
        }
    }

    public function get_by_id($id)
    {
        if ($this->companyAuxiliary !== null) {
            echo json_encode($this->companyAuxiliary->todo($id), JSON_THROW_ON_ERROR); return;
        }
        $todo              = $this->todo_model->get($id);
        $todo->description = clear_textarea_breaks($todo->description);
        echo json_encode($todo);
    }

    /* Change todo status */
    public function change_todo_status($id, $status)
    {
        if ($this->companyAuxiliary !== null) {
            CompanyRequestScope::assertNativeMutation($this);
            $row = $this->companyAuxiliary->todo($id);
            $this->companyAuxiliary->orderTodos([[$id, $row['item_order'], $status, $this->input->post('revision')]]);
            redirect(CompanyRequestScope::adminUrl($this, 'todo'), 'location', 303); return;
        }
        $success = $this->todo_model->change_todo_status($id, $status);
        if ($success) {
            set_alert('success', _l('todo_status_changed'));
        }
        redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
    }

    /* Update todo order / ajax */
    public function update_todo_items_order()
    {
        if ($this->companyAuxiliary !== null) {
            CompanyRequestScope::assertNativeMutation($this);
            $rows = $this->input->post('data');
            if (!is_array($rows)) { throw new DomainException('company_auxiliary_invalid_input'); }
            echo json_encode(['success'=>true, 'revisions'=>$this->companyAuxiliary->orderTodos($rows)], JSON_THROW_ON_ERROR); return;
        }
        if ($this->input->post()) {
            $this->todo_model->update_todo_items_order($this->input->post());
        }
    }

    /* Delete todo item from databse */
    public function delete_todo_item($id)
    {
        if ($this->companyAuxiliary !== null) {
            CompanyRequestScope::assertNativeMutation($this);
            echo json_encode(['success'=>$this->companyAuxiliary->deleteTodo($id, $this->input->post('revision'))], JSON_THROW_ON_ERROR); return;
        }
        if ($this->input->is_ajax_request()) {
            echo json_encode([
                'success' => $this->todo_model->delete_todo_item($id),
            ]);
        }
        die();
    }
}
