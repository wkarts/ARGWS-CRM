<?php
/** Real form/controller/SQL; loader hooks reproduce revocation after the complete real view renders. */
require __DIR__.'/native_expense_original_http.php';
if(getenv('CI')&&$db->driver()!=='mysql')throw new RuntimeException('Original expense render CI requires MySQL');
$renderStart=$GLOBALS['essentialChecks'];
class NativeExpenseRevocationLoader extends NativeExpenseTestLoader
{
    public $afterRender=null;
    public function view($path,$data=[],$return=false){
        $html=parent::view($path,$data,$return);
        if($path==='admin/expenses/expense'&&$this->afterRender!==null){$callback=$this->afterRender;$this->afterRender=null;$callback();}
        return$html;
    }
}
$loader=new NativeExpenseRevocationLoader();$GLOBALS['CI']->load=$loader;
$runRender=function(?callable $after=null)use($reset,$controller,$httpExpense,$loader,$out){
    $reset();$loader->afterRender=$after;$level=ob_get_level();ob_start();
    $controller->expense($httpExpense);$leaked=ob_get_clean();
    essentialCheck($leaked===''&&ob_get_level()===$level,'No bytes or nested output buffer survive a guarded original form render');
    return[$out->status,$out->body];
};
[$status,$body]=$runRender();essentialCheck($status===200&&str_contains($body,'id="expense-form"')&&str_contains($body,'Original custom field'),'Stable original GET publishes the complete real form after verification');
[$status,$body]=$runRender(static function()use($b){CompanyRequestScope::$issuer=$b;});
essentialCheck($status===403&&!str_contains($body,'Original custom field')&&!str_contains($body,'expense-form'),'Company switch after rendering discards the old company form');CompanyRequestScope::$issuer=$a;
[$status,$body]=$runRender(static function(){CompanyRequestScope::$actor=12;});
essentialCheck($status===403&&!str_contains($body,'expense-form'),'Actor change after rendering cannot publish the previous operator form');CompanyRequestScope::$actor=7;
[$status,$body]=$runRender(static function()use($db,$p){$db->query('UPDATE '.$p.'staff SET active=0 WHERE staffid=7');});
essentialCheck($status===403&&!str_contains($body,'Original custom field'),'Actual SQL access revocation after rendering is rechecked before output');$db->query('UPDATE '.$p.'staff SET active=1 WHERE staffid=7');
[$status,$body]=$runRender(static function(){CompanyRequestScope::$sealed=false;});
essentialCheck($status===403&&!str_contains($body,'expense-form'),'Lost sealed request context discards the rendered form');CompanyRequestScope::$sealed=true;
$oldValue=$fields->values($a,7,'expenses',$httpExpense)[$field['id']];
[$status,$body]=$runRender(static function()use($db,$p,$a,$httpSource){$db->query('UPDATE '.$p.'company_custom_field_values SET value=? WHERE issuer_id=? AND source_kind=? AND source_id=?',['Changed after render',$a,'company_expense',$httpSource]);});
essentialCheck($status===409&&!str_contains($body,'Original custom field'),'A custom-field projection changed after rendering makes the whole form stale');
$db->query('UPDATE '.$p.'company_custom_field_values SET value=? WHERE issuer_id=? AND source_kind=? AND source_id=?',[$oldValue,$a,'company_expense',$httpSource]);
[$status,$body]=$runRender(static function(){ob_start();echo 'PRIVATE-PARTIAL-FORM';throw new DomainException('company_expense_denied');});
essentialCheck($status===403&&!str_contains($body,'PRIVATE-PARTIAL-FORM'),'An exception after partial nested output leaves only the failure response');
[$status,$body]=$runRender();essentialCheck($status===200&&str_contains($body,'Original custom field'),'A later authorized render succeeds with clean buffers and restored state');
echo 'Original expense guarded render: '.($GLOBALS['essentialChecks']-$renderStart).' added actual SQL/controller/view checks passed ('.$db->driver().').'.PHP_EOL;
