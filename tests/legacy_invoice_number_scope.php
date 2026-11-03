<?php
// Exercise the actual legacy controller POST, stopping at its redirect boundary.
define('BASEPATH', __DIR__);
define('APPPATH', dirname(__DIR__).'/application/');
require APPPATH.'services/InvoiceIssuerScope.php';
class AdminController { public $db; public $input; public $invoices_model; }
class CompanyRequestScope { static function nativeFor($ci) { return false; } }
class Invoices_model { const STATUS_DRAFT=6; public $writes=0; function add($data) { ++$this->writes; return 99; } function update($data,$id) { ++$this->writes; return true; } }
class RedirectBoundary extends RuntimeException {}
function redirect($target) { throw new RedirectBoundary($target); }
function admin_url($path) { return '/admin/'.$path; }
function staff_cant(...$args) { return false; }
function hooks() { return new class { function apply_filters($key,$value) { return $value; } }; }
function set_alert(...$args) {}
function _l($key,...$args) { return $key; }
function to_sql_date($date) { return $date; }
function db_prefix() { return 'tbl'; }
function total_rows($table,$where) { return $GLOBALS['controller']->db->where($where)->count_all_results($table); }
class NumberDatabase {
    public $rows=[],$filters=[],$schema=true;
    function field_exists($field,$table) { return $this->schema; }
    function where($field,$value=null,$escape=null) { if(is_array($field))$this->filters+=$field;else $this->filters[$field]=$value; return $this; }
    function count_all_results($table) {
        $count=0;
        foreach($this->rows as $row) {
            if(isset($this->filters['tblinvoices.issuer_id IS NULL']) || array_key_exists('tblinvoices.issuer_id IS NULL',$this->filters)) { if($row['issuer_id']!==null)continue; }
            if($row['number']!=$this->filters['number'] || substr($row['date'],0,4)!=$this->filters['YEAR(date)'] || $row['status']==$this->filters['status !='])continue;
            if(isset($this->filters['id !=']) && $row['id']==$this->filters['id !='])continue;
            ++$count;
        }
        $this->filters=[];return $count;
    }
}
require APPPATH.'controllers/admin/Invoices.php';
$checks=0;
foreach([false,true] as $edit)foreach(['named','legacy','draft','self','other-year','pre-migration'] as $case) {
    $controller=(new ReflectionClass(Invoices::class))->newInstanceWithoutConstructor();$GLOBALS['controller']=$controller;
    $controller->db=new NumberDatabase();$controller->db->schema=$case!=='pre-migration';
    $row=['id'=>$case==='self'?99:55,'issuer_id'=>$case==='named'?7:null,'number'=>'1','date'=>$case==='other-year'?'2025-01-01':'2026-01-01','status'=>$case==='draft'?6:1];
    $controller->db->rows=[$row];
    $controller->input=new class { function post() { return ['number'=>'000001','date'=>'2026-01-01']; } };
    $controller->invoices_model=new Invoices_model();
    $blocked=in_array($case,['legacy','pre-migration'],true)||($case==='self'&&!$edit);
    try { $controller->invoice($edit?'99':'');throw new RuntimeException('missing redirect'); }
    catch(RedirectBoundary $result) {
        $expected=$blocked?'/admin/invoices/invoice'.($edit?'/99':''):'/admin/invoices/list_invoices/99';
        if($result->getMessage()!==$expected || $controller->invoices_model->writes!==($blocked?0:1))throw new RuntimeException("Unexpected $case edit=$edit: ".$result->getMessage());
        ++$checks;
    }
}
echo "Legacy invoice create/edit namespace, duplicates, drafts, own ID, year and pre-migration: $checks PASS\n";
