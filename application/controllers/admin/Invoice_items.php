<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Invoice_items extends AdminController
{
    private ?NativeCompanyCatalogUi $companyCatalog=null;
    private $not_importable_fields = ['id'];

    public function __construct()
    {
        parent::__construct();
        if(CompanyRequestScope::nativeFor($this)){
            require_once APPPATH.'services/NativeCompanyCatalogUi.php';$context=CompanyRequestScope::context();
            try{$this->companyCatalog=new NativeCompanyCatalogUi($this->db,db_prefix(),$context->issuerId(),$context->staffId(),$this);return;}catch(Throwable $error){error_log('Native item catalogue construction unconfirmed: '.get_class($error).'@'.basename($error->getFile()).':'.$error->getLine().'; no messages or request values logged.');throw $error;}
        }
        $this->load->model('invoice_items_model');
    }

    /* List all available items */
    public function index()
    {
        if($this->companyCatalog!==null){
            $this->output->set_header('Cache-Control: no-store');$level=ob_get_level();try{if(!$this->companyCatalog->allowed('items','view'))throw new DomainException('company_catalog_denied');
            $html=$this->load->view('admin/invoice_items/manage',['taxes'=>$this->companyCatalog->taxes(),'items_groups'=>$this->companyCatalog->groups(),'currencies'=>$this->companyCatalog->currencies(),'base_currency'=>(object)$this->companyCatalog->baseCurrency(),'title'=>_l('invoice_items')],true);if(!$this->companyCatalog->allowed('items','view'))throw new DomainException('company_catalog_denied');$this->output->set_output($html);return;
            }catch(Throwable $error){while(ob_get_level()>$level)ob_end_clean();error_log('Native item catalogue response unconfirmed: '.get_class($error).'@'.basename($error->getFile()).':'.$error->getLine().'; no messages or request values logged.');throw $error;}
        }
        if (staff_cant('view', 'items')) {
            access_denied('Invoice Items');
        }

        $this->load->model('taxes_model');
        $data['taxes']        = $this->taxes_model->get();
        $data['items_groups'] = $this->invoice_items_model->get_groups();

        $this->load->model('currencies_model');
        $data['currencies'] = $this->currencies_model->get();

        $data['base_currency'] = $this->currencies_model->get_base_currency();

        $data['title'] = _l('invoice_items');
        $this->load->view('admin/invoice_items/manage', $data);
    }

    public function table()
    {
        if($this->companyCatalog!==null){
            $start=$this->input->post('start');$length=$this->input->post('length');$draw=$this->input->post('draw');$search=$this->input->post('search');
            foreach([$start,$length,$draw]as$value)if(!is_scalar($value)||!preg_match('/^[0-9]{1,6}$/D',(string)$value))throw new DomainException('company_catalog_invalid');
            $result=$this->companyCatalog->itemTable((int)$start,(int)$length,is_array($search)?(string)($search['value']??''):'');$output=['draw'=>(int)$draw,'iTotalRecords'=>$result['total'],'iTotalDisplayRecords'=>$result['filtered'],'aaData'=>[]];
            foreach($result['rows']as$row){
                $description='<a href="#" data-toggle="modal" data-target="#sales_item_modal" data-id="'.(int)$row['id'].'" class="tw-font-medium">'.e($row['description']).'</a><div class="row-options">';
                if($this->companyCatalog->allowed('items','delete'))$description.=$this->nativeAction('invoice_items/delete/'.$row['id'],_l('delete'),$row['revision']);
                if($this->companyCatalog->allowed('items','create'))$description.=$this->nativeAction('invoice_items/copy/'.$row['id'],_l('copy'),$row['revision']);
                $cells=['<div class="checkbox"><input type="checkbox" value="'.(int)$row['id'].'" data-revision="'.e((string)$row['revision']).'"><label></label></div>',$description.'</div>',e($row['long_description']),e($row['rate']),e($row['taxrate']??''),e($row['taxrate_2']??''),e($row['unit']),e($row['group_name']??'')];
                foreach(get_custom_fields('items')as$field)$cells[]=e(get_custom_field_value($row['id'],$field['id'],'items_pr'));
                $cells['DT_RowClass']='has-row-options';$output['aaData'][]=$cells;
            }
            echo json_encode($output,JSON_THROW_ON_ERROR);return;
        }
        if (staff_cant('view', 'items')) {
            ajax_access_denied();
        }
        $this->app->get_table_data('invoice_items');
    }

    /* Edit or update items / ajax request /*/
    public function manage()
    {
        if($this->companyCatalog!==null){
            CompanyRequestScope::assertNativeMutation($this);$input=$this->input->post();$id=$input['itemid']??null;$revision=$input['revision']??null;unset($input['itemid'],$input['revision']);
            $saved=$this->companyCatalog->saveItem($input,$id,$revision);
            echo json_encode(['success'=>true,'message'=>_l('updated_successfully',_l('sales_item')),'item'=>$this->companyCatalog->item($saved)],JSON_THROW_ON_ERROR);return;
        }
        if (staff_can('view',  'items')) {
            if ($this->input->post()) {
                $data = $this->input->post();
                if ($data['itemid'] == '') {
                    if (staff_cant('create', 'items')) {
                        header('HTTP/1.0 400 Bad error');
                        echo _l('access_denied');
                        die;
                    }
                    $id      = $this->invoice_items_model->add($data);
                    $success = false;
                    $message = '';
                    if ($id) {
                        $success = true;
                        $message = _l('added_successfully', _l('sales_item'));
                    }
                    echo json_encode([
                        'success' => $success,
                        'message' => $message,
                        'item'    => $this->invoice_items_model->get($id),
                    ]);
                } else {
                    if (staff_cant('edit', 'items')) {
                        header('HTTP/1.0 400 Bad error');
                        echo _l('access_denied');
                        die;
                    }
                    $success = $this->invoice_items_model->edit($data);
                    $message = '';
                    if ($success) {
                        $message = _l('updated_successfully', _l('sales_item'));
                    }
                    echo json_encode([
                        'success' => $success,
                        'message' => $message,
                    ]);
                }
            }
        }
    }

    public function import()
    {
        if($this->companyCatalog!==null){
            if(!$this->companyCatalog->allowed('items','create'))throw new DomainException('company_catalog_denied');
            $this->load->library('import/import_items',[],'import');$fields=['description','long_description','rate','tax','tax2','unit','group_id'];
            foreach($this->companyCatalog->currencies()as$currency)if((int)$currency['id']!==(int)$this->companyCatalog->baseCurrency()['id'])$fields[]='rate_currency_'.$currency['id'];
            $this->import->setDatabaseFields($fields)->setCustomFields(get_custom_fields('items'));
            if(strtoupper($this->input->method(true))==='POST'){
                CompanyRequestScope::assertNativeMutation($this);
                if($this->input->post('download_sample')==='true'){$this->import->downloadSample();return;}
                if(!isset($_FILES['file_csv'])||($_FILES['file_csv']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||($_FILES['file_csv']['size']??0)>4*1024*1024||!is_uploaded_file($_FILES['file_csv']['tmp_name']))throw new DomainException('company_catalog_invalid_upload');
                $this->import->setSimulation($this->input->post('simulate'))->setTemporaryFileLocation($_FILES['file_csv']['tmp_name'])->setFilename($_FILES['file_csv']['name'])->perform();
            }
            $this->load->view('admin/invoice_items/import',['title'=>_l('import')]);return;
        }
        if (staff_cant('create', 'items')) {
            access_denied('Items Import');
        }

        $this->load->library('import/import_items', [], 'import');

        $this->import->setDatabaseFields($this->db->list_fields(db_prefix() . 'items'))
            ->setCustomFields(get_custom_fields('items'));

        if ($this->input->post('download_sample') === 'true') {
            $this->import->downloadSample();
        }

        if (
            $this->input->post()
            && isset($_FILES['file_csv']['name']) && $_FILES['file_csv']['name'] != ''
        ) {
            $this->import->setSimulation($this->input->post('simulate'))
                ->setTemporaryFileLocation($_FILES['file_csv']['tmp_name'])
                ->setFilename($_FILES['file_csv']['name'])
                ->perform();

            $data['total_rows_post'] = $this->import->totalRows();

            if (!$this->import->isSimulation()) {
                set_alert('success', _l('import_total_imported', $this->import->totalImported()));
            }
        }

        $data['title'] = _l('import');
        $this->load->view('admin/invoice_items/import', $data);
    }

    public function add_group()
    {
        if($this->companyCatalog!==null){
            CompanyRequestScope::assertNativeMutation($this);$id=$this->companyCatalog->saveGroup(['name'=>$this->input->post('name',false)]);echo json_encode(['success'=>true,'id'=>$id],JSON_THROW_ON_ERROR);return;
        }
        if ($this->input->post() && staff_can('create',  'items')) {
            $this->invoice_items_model->add_group($this->input->post());
            set_alert('success', _l('added_successfully', _l('item_group')));
        }
    }

    public function update_group($id)
    {
        if($this->companyCatalog!==null){
            CompanyRequestScope::assertNativeMutation($this);$this->companyCatalog->saveGroup(['name'=>$this->input->post('name',false)],$id,$this->input->post('revision'));echo json_encode(['success'=>true],JSON_THROW_ON_ERROR);return;
        }
        if ($this->input->post() && staff_can('edit',  'items')) {
            $this->invoice_items_model->edit_group($this->input->post(), $id);
            set_alert('success', _l('updated_successfully', _l('item_group')));
        }
    }

    public function delete_group($id)
    {
        if($this->companyCatalog!==null){
            CompanyRequestScope::assertNativeMutation($this);$this->companyCatalog->delete('group',$id,$this->input->post('revision'));redirect(CompanyRequestScope::adminUrl($this,'invoice_items'),'location',303);return;
        }
        if (staff_can('delete',  'items')) {
            if ($this->invoice_items_model->delete_group($id)) {
                set_alert('success', _l('deleted', _l('item_group')));
            }
        }
        redirect(admin_url('invoice_items?groups_modal=true'));
    }

    /* Delete item*/
    public function delete($id)
    {
        if($this->companyCatalog!==null){
            CompanyRequestScope::assertNativeMutation($this);$this->companyCatalog->delete('item',$id,$this->input->post('revision'));redirect(CompanyRequestScope::adminUrl($this,'invoice_items'),'location',303);return;
        }
        if (staff_cant('delete', 'items')) {
            access_denied('Invoice Items');
        }

        if (!$id) {
            redirect(admin_url('invoice_items'));
        }

        $response = $this->invoice_items_model->delete($id);
        if (is_array($response) && isset($response['referenced'])) {
            set_alert('warning', _l('is_referenced', _l('invoice_item_lowercase')));
        } elseif ($response == true) {
            set_alert('success', _l('deleted', _l('invoice_item')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('invoice_item_lowercase')));
        }
        redirect(admin_url('invoice_items'));
    }

    public function bulk_action()
    {
        if($this->companyCatalog!==null){
            CompanyRequestScope::assertNativeMutation($this);$ids=$this->input->post('ids');$revisions=$this->input->post('revisions');
            if(!is_array($ids)||!is_array($revisions)||!in_array($this->input->post('mass_delete'),['true','1',1,true],true))throw new DomainException('company_catalog_invalid');
            $selected=[];foreach($ids as$id){if(!is_string($id)||!preg_match('/^[1-9][0-9]{0,14}$/D',$id))throw new DomainException('company_catalog_invalid');if(!array_key_exists($id,$revisions)||isset($selected[$id]))throw new DomainException('company_catalog_invalid');$selected[$id]=$revisions[$id];}
            if(count($selected)!==count($revisions))throw new DomainException('company_catalog_invalid');$this->companyCatalog->deleteItems($selected);echo json_encode(['success'=>true]);return;
        }
        hooks()->do_action('before_do_bulk_action_for_items');
        $total_deleted = 0;
        if ($this->input->post()) {
            $ids                   = $this->input->post('ids');
            $has_permission_delete = staff_can('delete',  'items');
            if (is_array($ids)) {
                foreach ($ids as $id) {
                    if ($this->input->post('mass_delete')) {
                        if ($has_permission_delete) {
                            if ($this->invoice_items_model->delete($id)) {
                                $total_deleted++;
                            }
                        }
                    }
                }
            }
        }

        if ($this->input->post('mass_delete')) {
            set_alert('success', _l('total_items_deleted', $total_deleted));
        }
    }

    private function nativeInvoiceCatalogLookup(bool $post): ?NativeCompanyInvoiceCatalogLookup
    {
        $read=$post?'post':'get';$mode=$this->input->$read('native_editor');if($mode===null)return null;
        if($mode!=='invoice')throw new DomainException('company_catalog_invalid');
        if($post)CompanyRequestScope::assertNativeMutation($this);
        $id=$this->input->$read('native_invoice_id');$revision=$this->input->$read('native_invoice_revision');
        if(!is_string($id)||!is_string($revision))throw new DomainException('company_invoice_stale');
        require_once APPPATH.'services/NativeCompanyInvoiceCatalogLookup.php';$ctx=CompanyRequestScope::context();
        return new NativeCompanyInvoiceCatalogLookup($this->db,db_prefix(),$ctx->issuerId(),$ctx->staffId(),$id,$revision);
    }

    public function search()
    {
        if($this->companyCatalog!==null){
            $q=$this->input->post('q');if(!is_string($q))throw new DomainException('company_catalog_invalid');
            $lookup=$this->nativeInvoiceCatalogLookup(true);echo json_encode($lookup?$lookup->search($q):$this->companyCatalog->search($q),JSON_THROW_ON_ERROR);return;
        }
        if ($this->input->post() && $this->input->is_ajax_request()) {
            echo json_encode($this->invoice_items_model->search($this->input->post('q')));
        }
    }

    /* Get item by id / ajax */
    public function get_item_by_id($id)
    {
        if($this->companyCatalog!==null){
            $lookup=$this->nativeInvoiceCatalogLookup(false);$item=(object)($lookup?$lookup->item($id):$this->companyCatalog->item($id));$item->long_description=nl2br(htmlspecialchars($item->long_description,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'));
            $item->custom_fields_html=render_custom_fields('items',$id,[],['items_pr'=>true]+($lookup?['native_invoice_catalog'=>$lookup]:[]));$item->custom_fields=[];
            if($lookup){$projection=$lookup->fields($id);foreach($projection['definitions']as$field){$field['value']=$projection['values'][$field['id']]??'';$item->custom_fields[]=$field;}$lookup->assertCurrent();echo json_encode($item,JSON_THROW_ON_ERROR);return;}
            foreach(get_custom_fields('items')as$field){$field['value']=get_custom_field_value($id,$field['id'],'items_pr');$item->custom_fields[]=$field;}
            echo json_encode($item,JSON_THROW_ON_ERROR);return;
        }
        if ($this->input->is_ajax_request()) {
            $item                     = $this->invoice_items_model->get($id);
            $item->long_description   = nl2br($item->long_description);
            $item->custom_fields_html = render_custom_fields('items', $id, [], ['items_pr' => true]);
            $item->custom_fields      = [];

            $cf = get_custom_fields('items');

            foreach ($cf as $custom_field) {
                $val = get_custom_field_value($id, $custom_field['id'], 'items_pr');
                if ($custom_field['type'] == 'textarea') {
                    $val = clear_textarea_breaks($val);
                }
                $custom_field['value'] = $val;
                $item->custom_fields[] = $custom_field;
            }

            echo json_encode($item);
        }
    }

    /* Copy Item */
    public function copy($id)
    {
        if($this->companyCatalog!==null){
            CompanyRequestScope::assertNativeMutation($this);$saved=$this->companyCatalog->copyItem($id,$this->input->post('revision'));
            redirect(CompanyRequestScope::adminUrl($this,'invoice_items?id='.$saved),'location',303);return;
        }
        if (staff_cant('create', 'items')) {
            access_denied('Create Item');
        }

        $data = (array) $this->invoice_items_model->get($id);

        $id = $this->invoice_items_model->copy($data);

        if ($id) {
            set_alert('success', _l('item_copy_success'));
            return redirect(admin_url('invoice_items?id=' . $id));
        }

        set_alert('warning', _l('item_copy_fail'));
        return redirect(admin_url('invoice_items'));
    }
    private function nativeAction(string $path,string $label,$revision):string
    {
        return '<form method="post" action="'.e(admin_url($path)).'" class="tw-inline"><input type="hidden" name="_company_context" value="'.e(CompanyRequestScope::token()).'"><input type="hidden" name="_native_company_csrf" value="'.e(CompanyRequestScope::nativeCsrfToken($this)).'"><input type="hidden" name="revision" value="'.e((string)$revision).'"><button type="submit" class="btn btn-link btn-xs">'.e($label).'</button></form>';
    }
}
