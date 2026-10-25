<?php
/** Real SQLite services and existing nested views; framework envelope/helpers explicitly doubled. */
require __DIR__.'/native_invoice_table_controller.php';
require_once APPPATH.'services/NativeCompanyInvoiceManage.php';
require_once APPPATH.'helpers/datatables_helper.php';
function init_head(){echo '<!-- synthetic framework head -->';}
function init_tail(){echo '<!-- synthetic framework tail -->';}
function staff_can($cap,$feature){return (new IssuerResourceAccess($GLOBALS['CI']->db,db_prefix()))->can(CompanyRequestScope::$issuer,7,$feature,$cap);}
function get_invoice_status_label($status){return 'synthetic-status-'.(int)$status;}
function form_hidden($name,$value=''){return '<input type="hidden" name="'.e($name).'" value="'.e($value).'">';}
function form_open_multipart($url,$attrs){return '<form action="'.e($url).'" method="post" enctype="multipart/form-data"><input type="hidden" name="synthetic_framework_csrf" value="fixture-only">';}
function form_close(){return '</form>';}
$ci->agent=new class{public function browser(){return 'Synthetic';}};
$ci->load=new class{
 public $load,$input;private array $data=[];public array $views=[];
 public function __construct(){$this->load=$this;$this->input=$GLOBALS['CI']->input;}
 public function model($name){throw new RuntimeException('global model invoked');}
 public function view($path,$data=[]){$this->views[]=$path;$this->data=array_merge($this->data,$data);extract($this->data);include APPPATH.'views/'.$path.'.php';}
};
CompanyRequestScope::$issuer=$a;$ci->input->verb='GET';$ci->input->gets=[];$ci->output->status=200;
$factory=new NativeCompanyInvoiceTableFactory($db,$p);
$filters=new NativeCompanyFilters($db,$p,$a,7,$factory->make($a,7));
$filter=$filters->save(['name'=>'Synthetic persisted A filter','is_shared'=>0,'builder'=>['match_type'=>'and','rules'=>[['id'=>'status','type'=>'MultiSelectRule','operator'=>'in','value'=>['2'],'has_dynamic_value'=>false]]]],null,null,true);
$manage=new NativeCompanyInvoiceManage($db,$p);$data=$manage->data($a,7,$branded);
essentialCheck(str_contains($data['invoices_table']->filtersJs(),'Synthetic persisted A filter'),'manage uses actual persisted filters, not empty substitute');
$before=$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array();
$controller=new Invoices();ob_start();$controller->index($branded);$html=ob_get_clean();
essentialCheck($ci->output->status===200&&in_array('admin/invoices/manage',$ci->load->views,true),'same Invoices::index renders actual native manage view');
foreach(['id="vueApp"','id="invoices_total"','class="dt-table-loading table table-invoices"','<app-filters','Synthetic persisted A filter','/admin/invoices/invoice','id="sales_attach_file"','init_invoice();']as$marker)essentialCheck(str_contains($html,$marker),'same native manage contains '.$marker);
essentialCheck(!str_contains($html,'company_invoices'),'manage does not replace native route surface');
$other=$manage->data($b,7);essentialCheck(!str_contains($other['invoices_table']->filtersJs(),'Synthetic persisted A filter'),'B never receives A saved filter');
essentialThrows(fn()=>$manage->data($b,7,$branded),'company_invoice_access_denied','B cannot preselect A invoice');
$ci->input->verb='POST';$ci->load->views=[];$controller->list_invoices();essentialCheck($ci->output->status===422&&$ci->load->views===[],'wrong list verb cannot invoke global or native view');
$ci->input->verb='GET';$ci->input->gets=['status'=>['1']];$controller->list_invoices();essentialCheck($ci->output->status===422,'malformed status fails before rendering');
essentialCheck($db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array()===$before,'manage leaves financial rows byte-identical');
echo 'Native manage actual nested views: '.$GLOBALS['essentialChecks']." cumulative PASS; envelope is a double, not HTTP/browser proof.\n";
