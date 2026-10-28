<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Filters extends AdminController
{
    private function nativeStore($identifier,$view):array
    {
        if(!is_string($identifier)||!is_string($view)||!((in_array($identifier,['invoices','project_invoices'],true)&&$view==='invoices')||($identifier==='projects'&&$view==='projects')||($identifier==='tickets'&&$view==='tickets')||($identifier==='related_tasks'&&$view==='tasks_relations')||($identifier==='tasks'&&$view==='tasks')))throw new DomainException('native_filter_invalid');
        $this->output->set_header('Cache-Control: no-store')->set_content_type('application/json');
        require_once APPPATH.'services/NativeCompanyFilters.php';
        $ctx=CompanyRequestScope::context();
        if($identifier==='tickets'){
            require_once APPPATH.'services/NativeCompanyTicketTableFactory.php';
            $table=(new NativeCompanyTicketTableFactory($this->db,db_prefix()))->make($ctx->issuerId(),$ctx->staffId(),$identifier);
        }elseif(in_array($identifier,['related_tasks','tasks'],true)){
            require_once APPPATH.'services/NativeCompanyRelatedTaskTableFactory.php';
            $table=(new NativeCompanyRelatedTaskTableFactory($this->db,db_prefix()))->make($ctx->issuerId(),$ctx->staffId(),$identifier);
        }elseif($identifier==='projects'){
            require_once APPPATH.'services/NativeCompanyProjectTableFactory.php';
            $table=(new NativeCompanyProjectTableFactory($this->db,db_prefix()))->make($ctx->issuerId(),$ctx->staffId(),$identifier);
        }else{
            require_once APPPATH.'services/NativeCompanyInvoiceTableFactory.php';
            $table=(new NativeCompanyInvoiceTableFactory($this->db,db_prefix()))->make($ctx->issuerId(),$ctx->staffId(),$identifier);
        }
        return [new NativeCompanyFilters($this->db,db_prefix(),$ctx->issuerId(),$ctx->staffId(),$table),$table];
    }
    private static function nativeBoolean($value):bool
    {
        if(!in_array($value,[true,false,'true','false',1,0,'1','0'],true))throw new DomainException('native_filter_invalid');
        return in_array($value,[true,'true',1,'1'],true);
    }
    private function nativeFailure(Throwable $error):void
    {
        $reason=$error instanceof DomainException?$error->getMessage():'';
        show_error('Não foi possível concluir o filtro desta empresa.',$reason==='native_filter_stale'?409:(str_contains($reason,'denied')||str_contains($reason,'csrf')?403:($error instanceof DomainException?422:503)));
    }
    private function nativeSave($id=null):void
    {
        try{
            CompanyRequestScope::assertNativeMutation($this);$input=$this->input->post(null,false);
            if(!is_array($input))throw new DomainException('native_filter_invalid');
            unset($input['_company_context'],$input['_native_company_csrf'],$input[$this->security->get_csrf_token_name()]);
            if(array_diff(array_keys($input),['name','identifier','view','is_shared','is_default','rules','revision']))throw new DomainException('native_filter_invalid');
            [$store,$table]=$this->nativeStore($input['identifier']??null,$input['view']??null);
            $revision=$input['revision']??null;if($revision!==null&&!is_string($revision))throw new DomainException('native_filter_invalid');
            $filter=$store->save(['name'=>$input['name']??null,'is_shared'=>(int)self::nativeBoolean($input['is_shared']??null),'builder'=>$input['rules']??null],$id,$revision,self::nativeBoolean($input['is_default']??null));
            foreach($filter['builder']['rules']as&$rule)$rule=$table->findRule($rule['id'])->setOperator($rule['operator'])->setValue($rule['value'])->dynamic($rule['has_dynamic_value']);unset($rule);
            echo json_encode($filter,JSON_THROW_ON_ERROR);
        }catch(Throwable $error){$this->nativeFailure($error);}
    }

    public function create()
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeSave();return;}
        $this->load->model('filters_model');

        $filter = $this->filters_model->create([
            'name' => $this->input->post('name'),
            'identifier' => $this->input->post('identifier'),
            'builder' => $this->unformatDateRules($this->input->post('rules')),
            'is_shared' => filter_var($this->input->post('is_shared'), FILTER_VALIDATE_BOOL),
            'is_default' => filter_var($this->input->post('is_default'), FILTER_VALIDATE_BOOL),
            'view' => $this->input->post('view'),
            'staff_id' => get_staff_user_id(),
        ]);

        $filter['builder']['rules'] = $this->mapRulesIntoInstances($filter['builder']['rules'], $filter['identifier']);

        echo json_encode($filter);
    }

    public function update($id)
    {
        if(CompanyRequestScope::nativeFor($this)){$this->nativeSave($id);return;}
        $this->load->model('filters_model');
        $staffId = get_staff_user_id();
        $view = $this->input->post('view');

        $filter = $this->db->where('id', $id)->get('filters')->row_array();

        if (!is_admin() && $filter['staff_id'] != $staffId) {
            ajax_access_denied();
        }

        $filter = $this->filters_model->update($id, [
            'name' => $this->input->post('name'),
            'is_shared' => filter_var($this->input->post('is_shared'), FILTER_VALIDATE_BOOL),
            'is_default' => filter_var($this->input->post('is_default'), FILTER_VALIDATE_BOOL),
            'view' => $view,
            'builder' => $this->unformatDateRules($this->input->post('rules')),
        ], $staffId);

        $filter['builder']['rules'] = $this->mapRulesIntoInstances($filter['builder']['rules'], $filter['identifier']);

        echo json_encode($filter);
    }

    public function delete($id)
    {
        if(CompanyRequestScope::nativeFor($this)){
            try{CompanyRequestScope::assertNativeMutation($this);[$store]=$this->nativeStore($this->input->post('identifier'),$this->input->post('view'));$revision=$this->input->post('revision');if(!is_string($revision))throw new DomainException('native_filter_invalid');$store->delete($id,$revision);echo json_encode(['success'=>true]);}catch(Throwable$error){$this->nativeFailure($error);}return;
        }
        $this->load->model('filters_model');
        $filter = $this->db->where('id', $id)->get('filters')->row_array();

        if (!is_admin() && $filter['staff_id'] != get_staff_user_id()) {
            ajax_access_denied();
        }

        $this->filters_model->delete($id);
    }

    public function mark_as_default($id, $identifier, $view)
    {
        if(CompanyRequestScope::nativeFor($this)){try{CompanyRequestScope::assertNativeMutation($this);[$store]=$this->nativeStore($identifier,$view);$store->default($id);echo json_encode(['success'=>true]);}catch(Throwable$error){$this->nativeFailure($error);}return;}
        $this->load->model('filters_model');
        $this->filters_model->mark_as_default($id, $identifier, $view, get_staff_user_id());
    }

    public function unmark_as_default($identifier, $view)
    {
        if(CompanyRequestScope::nativeFor($this)){try{CompanyRequestScope::assertNativeMutation($this);[$store]=$this->nativeStore($identifier,$view);$store->default(null);echo json_encode(['success'=>true]);}catch(Throwable$error){$this->nativeFailure($error);}return;}
        $this->load->model('filters_model');
        $this->filters_model->delete_default($identifier, $view, get_staff_user_id());
    }

    public function validate_dynamic_date()
    {
        if(CompanyRequestScope::nativeFor($this)){try{CompanyRequestScope::assertNativeMutation($this);$value=$this->input->post('value');if(!is_string($value)||strlen($value)>191)throw new DomainException('native_filter_invalid');$parsed=strtotime($value);echo $parsed===false?'fails':date('Y-m-d',$parsed);}catch(Throwable$error){$this->nativeFailure($error);}return;}
        $value = $this->input->post('value');
        $parsed = strtotime($value);
        if ($parsed === false) {
            echo 'fails';
        } else {
            echo date('Y-m-d', $parsed);
        }
    }

    protected function unformatDateRules($builder)
    {
        foreach ($builder['rules'] as $key => $rule) {
            if ($rule['type'] == 'DateRule' && !$rule['has_dynamic_value']) {
                if (is_array($rule['value'])) {
                    $builder['rules'][$key]['value'] = [to_sql_date($rule['value'][0]), to_sql_date($rule['value'][1])];
                } else {
                    $builder['rules'][$key]['value'] = to_sql_date($rule['value']);
                }
            }
        }

        return $builder;
    }

    protected function mapRulesIntoInstances($rules, $identifier)
    {
        $table = App_table::find($identifier);

        foreach ($rules as $key => $rule) {
            $ruleInstance = clone $table->findRule($rule['id']);

            $rules[$key] = $ruleInstance
                ->setOperator($rule['operator'])
                ->setValue($rule['value'])
                ->dynamic($rule['has_dynamic_value']);
        }

        return $rules;
    }
}
