<?php
/** Literal text reaches the scoped writer unchanged; output escaping stays in the original views. */
require __DIR__.'/native_clients_controller.php';
function redirect($url,$method='location',$code=302){$GLOBALS['native_client_redirect']=[$url,$code];}
$controller->input->post=array_replace($payload,['company'=>'Literal customer <script>','request_key'=>hash('sha256','literal-customer-controller'),'_native_company_csrf'=>'synthetic-native-csrf','csrf_token'=>'framework-token']);
$controller->client();
$created=$db->query('SELECT userid FROM '.$p.'clients WHERE company=?',['Literal customer <script>'])->row_array();
essentialCheck($created!==null,'actual controller preserves the literal customer identity');
essentialCheck(in_array([null,false],$controller->input->reads,true),'validated native writer receives unmodified plain text despite global XSS filtering');
essentialCheck($GLOBALS['native_client_redirect']===[admin_url('clients/client/'.$created['userid']),303],'create keeps the original profile redirect');
essentialCheck($adapterB->details((int)$created['userid'])===null,'new literal identity remains private to its owner');
$controller->client();
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'clients WHERE company=?',['Literal customer <script>'])->row()->n===1,'complete original payload remains idempotent');
echo "Native client literal identity, isolated persistence and durable replay passed.\n";

require_once APPPATH.'helpers/fields_helper.php';
$profileSource=file_get_contents(APPPATH.'views/admin/clients/groups/profile.php');
$modalStart=strpos($profileSource,'id="customer_admins_assign"');
if($modalStart===false)throw new RuntimeException('Original customer admin modal missing');
$selectorStart=strpos($profileSource,'$selected = [];',$modalStart);
if($selectorStart===false)throw new RuntimeException('Original customer admin selector missing');
$selectorEnd=strpos($profileSource,'?>',$selectorStart);
if($selectorEnd===false)throw new RuntimeException('Original customer admin renderer incomplete');
$selectorCode=substr($profileSource,$selectorStart,$selectorEnd-$selectorStart);
$staff=[['staffid'=>7,'firstname'=>'Exclusive <script>literal</script>','lastname'=>'A & B']];$rawStaff=$staff;
$adminSelector=(static function(array &$staff,string $code){$customer_admins=[['staff_id'=>7]];ob_start();try{eval($code);return ob_get_clean();}catch(Throwable $error){ob_end_clean();throw$error;}})($staff,$selectorCode);
essentialCheck(!str_contains($adminSelector,'<script>')&&str_contains($adminSelector,'&lt;script&gt;literal&lt;/script&gt;'),'actual customer-admin selector renders staff names as literal text');
essentialCheck(str_contains($adminSelector,'value="7" selected'),'customer-admin selector retains the same selected staff identity');
essentialCheck($staff===$rawStaff,'customer-admin label escaping preserves the raw staff projection');
echo "Customer profile admin labels: three additional real-renderer checks passed.\n";
