<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Announcements extends AdminController
{
    private ?NativeCompanyAuxiliaryUi $companyAuxiliary = null;
    public function __construct()
    {
        parent::__construct();
        if (CompanyRequestScope::nativeFor($this)) {
            require_once APPPATH.'services/NativeCompanyAuxiliaryUi.php';
            $context=CompanyRequestScope::context();
            $this->companyAuxiliary=new NativeCompanyAuxiliaryUi($this->db,db_prefix(),$context->issuerId(),$context->staffId(),$this);
            return;
        }
        $this->load->model('announcements_model');
    }

    /* List all announcements */
    public function index()
    {
        if($this->companyAuxiliary!==null){
            if($this->input->is_ajax_request()){
                $start=$this->input->post('start');$length=$this->input->post('length');$draw=$this->input->post('draw');$search=$this->input->post('search');
                foreach([$start,$length,$draw]as$value)if(!is_scalar($value)||!preg_match('/^[0-9]{1,6}$/D',(string)$value))throw new DomainException('company_auxiliary_invalid_input');
                $result=$this->companyAuxiliary->announcementTable((int)$start,(int)$length,is_array($search)?(string)($search['value']??''):'');
                $data=['draw'=>(int)$draw,'iTotalRecords'=>$result['total'],'iTotalDisplayRecords'=>$result['filtered'],'aaData'=>[]];
                foreach($result['rows']as$row){
                    $name='<a href="'.e(admin_url('announcements/view/'.$row['announcementid'])).'">'.e($row['name']).'</a><div class="row-options">';
                    if($this->companyAuxiliary->allowed('announcements','edit'))$name.='<a href="'.e(admin_url('announcements/announcement/'.$row['announcementid'])).'">'.e(_l('edit')).'</a> ';
                    if(!$row['is_dismissed']&&(int)$row['showtostaff']===1)$name.=$this->nativeAction('misc/dismiss_announcement/'.$row['announcementid'],_l('dismiss_announcement'));
                    if($this->companyAuxiliary->allowed('announcements','delete'))$name.=$this->nativeAction('announcements/delete/'.$row['announcementid'],_l('delete'),$row['revision']);
                    $data['aaData'][]=[$name.'</div>',e(_d($row['dateadded'])),'DT_RowClass'=>'has-row-options has-border-left'];
                }
                echo json_encode($data,JSON_THROW_ON_ERROR);return;
            }
            $this->load->view('admin/announcements/manage',['title'=>_l('announcements'),'native_can_create'=>$this->companyAuxiliary->allowed('announcements','create')]);return;
        }
        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data('announcements');
        }
        $data['title'] = _l('announcements');
        $this->load->view('admin/announcements/manage', $data);
    }

    /* Edit announcement or add new if passed id */
    public function announcement($id = '')
    {
        if($this->companyAuxiliary!==null){
            if(!$this->companyAuxiliary->allowed('announcements',$id===''?'create':'edit'))throw new DomainException('company_auxiliary_access_denied');
            if(strtoupper($this->input->method(true))==='POST'){
                CompanyRequestScope::assertNativeMutation($this);$input=[];
                foreach(['name','message','showtousers','showtostaff','showname']as$key){$value=$this->input->post($key,false);if($value!==null)$input[$key]=$value;}
                $saved=$this->companyAuxiliary->saveAnnouncement($input,$id,$this->input->post('revision'));
                redirect(CompanyRequestScope::adminUrl($this,'announcements/view/'.$saved),'location',303);return;
            }
            $data=['title'=>_l($id===''?'add_new':'edit',_l('announcement'))];
            if($id!=='')$data['announcement']=(object)$this->companyAuxiliary->announcement($id);
            $this->load->view('admin/announcements/announcement',$data);return;
        }
        if (!is_admin()) {
            access_denied('Announcement');
        }
        if ($this->input->post()) {
            $data            = $this->input->post();
            $data['message'] = html_purify($this->input->post('message', false));
            if ($id == '') {
                $id = $this->announcements_model->add($data);
                if ($id) {
                    set_alert('success', _l('added_successfully', _l('announcement')));
                    redirect(admin_url('announcements/view/' . $id));
                }
            } else {
                $success = $this->announcements_model->update($data, $id);
                if ($success) {
                    set_alert('success', _l('updated_successfully', _l('announcement')));
                }
                redirect(admin_url('announcements/view/' . $id));
            }
        }
        if ($id == '') {
            $title = _l('add_new', _l('announcement'));
        } else {
            $data['announcement'] = $this->announcements_model->get($id);
            $title                = _l('edit', _l('announcement'));
        }
        $data['title'] = $title;
        $this->load->view('admin/announcements/announcement', $data);
    }

    public function view($id)
    {
        if($this->companyAuxiliary!==null){
            $announcement=(object)$this->companyAuxiliary->announcement($id);
            $recent=array_values(array_filter($this->companyAuxiliary->announcements(5),static fn($row)=>(string)$row['announcementid']!==(string)$id));
            $this->load->view('admin/announcements/view',['announcement'=>$announcement,'recent_announcements'=>array_slice($recent,0,4),'title'=>$announcement->name,'native_can_edit'=>$this->companyAuxiliary->allowed('announcements','edit')]);return;
        }
        if (is_staff_member()) {
            $announcement = $this->announcements_model->get($id);
            if (!$announcement) {
                blank_page(_l('announcement_not_found'));
            }
            $data['announcement']         = $announcement;
            $data['recent_announcements'] = $this->announcements_model->get('', [
                'announcementid !=' => $id,
            ], 4);
            $data['title'] = $announcement->name;
            $this->load->view('admin/announcements/view', $data);
        }
    }

    /* Delete announcement from database */
    public function delete($id)
    {
        if($this->companyAuxiliary!==null){
            CompanyRequestScope::assertNativeMutation($this);
            $this->companyAuxiliary->deleteAnnouncement($id,$this->input->post('revision'));
            redirect(CompanyRequestScope::adminUrl($this,'announcements'),'location',303);return;
        }
        if (!$id) {
            redirect(admin_url('announcements'));
        }
        if (!is_admin()) {
            access_denied('Announcement');
        }

        $response = $this->announcements_model->delete($id);

        if ($response == true) {
            set_alert('success', _l('deleted', _l('announcement')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('announcement_lowercase')));
        }

        redirect(admin_url('announcements'));
    }
    private function nativeAction(string $path,string $label,$revision=null):string
    {
        $html='<form method="post" action="'.e(CompanyRequestScope::adminUrl($this,$path)).'" class="tw-inline">';
        foreach(['_company_context'=>CompanyRequestScope::token(),'_native_company_csrf'=>CompanyRequestScope::nativeCsrfToken($this),'revision'=>$revision]as$name=>$value){
            if($value!==null)$html.='<input type="hidden" name="'.e($name).'" value="'.e((string)$value).'">';
        }
        return $html.'<button type="submit" class="btn btn-link btn-xs">'.e($label).'</button></form>';
    }
}
