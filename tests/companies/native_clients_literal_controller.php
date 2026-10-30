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
