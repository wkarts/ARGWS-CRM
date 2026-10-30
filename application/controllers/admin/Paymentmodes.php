<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Paymentmodes extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if (CompanyRequestScope::nativeFor($this)) return;
        $this->load->model('payment_modes_model');
        if (!is_admin()) {
            access_denied('Payment Modes');
        }
    }

    /* List all peyment modes*/
    public function index()
    {
        if (CompanyRequestScope::nativeFor($this)) {
            return $this->nativeModeAction(function($service,$issuer,$actor){
                if($this->input->is_ajax_request()){
                    CompanyRequestScope::assertNativeMutation($this);
                    require_once APPPATH.'services/NativeCompanyPaymentModeTable.php';
                    return (new NativeCompanyPaymentModeTable($this->db,db_prefix()))->response($issuer,$actor,$this->nativeModeInput());
                }
                if(!in_array(strtoupper($this->input->method(true)),['GET','HEAD'],true))throw new DomainException('native_payment_mode_invalid');
                $service->listing($issuer,$actor);
                $this->load->view('admin/paymentmodes/manage',['title'=>_l('payment_modes'),'native_payment_modes'=>true]);
                return null;
            });
        }
        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data('payment_modes');
        }
        $data['title'] = _l('payment_modes');
        $this->load->view('admin/paymentmodes/manage', $data);
    }

    /* Add or update payment mode / ajax */
    public function manage()
    {
        if (CompanyRequestScope::nativeFor($this)) return $this->nativeModeAction(function($service,$issuer,$actor){
            CompanyRequestScope::assertNativeMutation($this);$input=$this->nativeModeInput();$id=$input['paymentmodeid']??'';$revision=$input['revision']??null;unset($input['paymentmodeid'],$input['revision']);if($id===''&&$revision==='')$revision=null;
            foreach(['active','show_on_pdf','selected_by_default','invoices_only','expenses_only']as$key)if(isset($input[$key])&&$input[$key]==='on')$input[$key]=1;
            $dto=$service->save($issuer,$actor,$input,$id===''?null:$id,$revision);
            return ['success'=>true,'message'=>_l($id===''?'added_successfully':'updated_successfully',_l('payment_mode')),'id'=>$dto['id'],'revision'=>$dto['revision']];
        });
        if ($this->input->post()) {
            $data = $this->input->post();
            if ($data['paymentmodeid'] == '') {
                $message = '';
                $success = $this->payment_modes_model->add($data);
                if ($success) {
                    $message = _l('added_successfully', _l('payment_mode'));
                }
                echo json_encode([
                    'success' => $success,
                    'message' => $message,
                ]);
            } else {
                $message = '';
                $success = $this->payment_modes_model->edit($data);
                if ($success) {
                    $message = _l('updated_successfully', _l('payment_mode'));
                }
                echo json_encode([
                    'success' => $success,
                    'message' => $message,
                ]);
            }
        }
    }

    /* Delete payment mode */
    public function delete($id)
    {
        if (CompanyRequestScope::nativeFor($this)) return $this->nativeModeAction(function($service,$issuer,$actor)use($id){
            CompanyRequestScope::assertNativeMutation($this);$input=$this->nativeModeInput();if(array_keys($input)!==['revision']||!is_string($input['revision']))throw new DomainException('native_payment_mode_invalid');
            $service->delete($issuer,$actor,$id,$input['revision']);return ['success'=>true];
        });
        if (!$id) {
            redirect(admin_url('paymentmodes'));
        }
        $response = $this->payment_modes_model->delete($id);
        if (is_array($response) && isset($response['referenced'])) {
            set_alert('warning', _l('is_referenced', _l('payment_mode_lowercase')));
        } elseif ($response == true) {
            set_alert('success', _l('deleted', _l('payment_mode')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('payment_mode_lowercase')));
        }
        redirect(admin_url('paymentmodes'));
    }

    // Since version 1.0.1
    // Change payment mode active or inactive
    public function change_payment_mode_status($id, $status)
    {
        if (CompanyRequestScope::nativeFor($this)) return $this->nativeModeToggle($id,$status,'active');
        if ($this->input->is_ajax_request()) {
            $this->payment_modes_model->change_payment_mode_status($id, $status);
        }
    }

    // Since version 1.0.1
    // Change to show this mode to client or not
    public function change_payment_mode_show_to_client_status($id, $status)
    {
        if (CompanyRequestScope::nativeFor($this)) return $this->nativeModeToggle($id,$status,'show_on_pdf');
        if ($this->input->is_ajax_request()) {
            $this->payment_modes_model->change_payment_mode_show_to_client_status($id, $status);
        }
    }
    private function nativeModeInput():array
    {
        $input=$this->input->post();if(!is_array($input))throw new DomainException('native_payment_mode_invalid');
        unset($input['_company_context'],$input['_native_company_csrf']);
        if(isset($this->security))unset($input[$this->security->get_csrf_token_name()]);
        return$input;
    }
    private function nativeModeAction(callable $action)
    {
        $this->output->set_header('Cache-Control: no-store');
        try{require_once APPPATH.'services/NativeCompanyPaymentModes.php';$c=CompanyRequestScope::context();$result=$action(new NativeCompanyPaymentModes($this->db,db_prefix()),$c->issuerId(),$c->staffId());if($result!==null)$this->output->set_content_type('application/json')->set_output(json_encode($result,JSON_THROW_ON_ERROR));}
        catch(DomainException $e){$code=$e->getMessage();show_error('Modo de pagamento indisponível ou solicitação inválida nesta empresa.',str_contains($code,'stale')?409:(str_contains($code,'invalid')||str_contains($code,'referenced')?422:403));}
        catch(Throwable $e){show_error('Não foi possível confirmar o modo de pagamento.',503);}
    }
    private function nativeModeToggle($id,$status,string$field)
    {
        return$this->nativeModeAction(function($service,$issuer,$actor)use($id,$status,$field){
            CompanyRequestScope::assertNativeMutation($this);$input=$this->nativeModeInput();if(array_keys($input)!==['revision']||!is_string($input['revision'])||!in_array($status,[0,1,'0','1'],true))throw new DomainException('native_payment_mode_invalid');
            $row=$service->get($issuer,$actor,$id);$data=array_intersect_key($row,array_flip(['name','description','active','show_on_pdf','selected_by_default','invoices_only','expenses_only']));$data[$field]=(int)$status;
            $dto=$service->save($issuer,$actor,$data,$id,$input['revision']);return ['success'=>true,'revision'=>$dto['revision']];
        });
    }

}
