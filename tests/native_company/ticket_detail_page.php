<?php
/** Actual Tickets/ticket GET, original single.php and real issuer-owned SQLite projections. */
declare(strict_types=1);
require __DIR__.'/ticket_thread_render.php';

// The current original page also renders publication controls. Install their
// declared SQLite participants; information_schema remains explicitly doubled.
require_once APPPATH.'services/CompanyOperationalPortalAdmin.php';
$portalDefinitions=array_merge(CompanyContractsSchema::definitions('tbl'),CompanyProjectsSchema::definitions('tbl'),CompanyProjectExtrasSchema::definitions('tbl'),CompanyOperationFilesSchema::definitions('tbl'),CompanyPortalOperationsSchema::definitions('tbl'));
foreach($portalDefinitions as $table=>$parts)if(!$db->table_exists('tbl'.$table))ticketSqliteDdl($table,$parts,$db);
if(!$db->table_exists('tblcontact_permissions'))$db->query('CREATE TABLE tblcontact_permissions(id INTEGER PRIMARY KEY,contact_id INTEGER,permission_id INTEGER)');
$wrapped=new TicketCreateDb($db,array_merge($portalDefinitions,NativeCompanyTicketReplySchema::definitions('tbl'),NativeCompanySpamSchema::definitions('tbl')));$ci->db=$wrapped;
$db->query('UPDATE tblissuers_installation_identity SET master_staff_id=17');
$db->query('UPDATE tblstaff SET admin=1,is_not_staff=0 WHERE staffid=17');
$db->query('UPDATE tblissuers_staff SET can_view=1 WHERE issuer_id=2 AND staff_id=17');
$ci->session=new class{private array $data=[];function userdata($key){return $this->data[$key]??null;}function set_userdata($key,$value){$this->data[$key]=$value;}};
$ci->router=new class{function fetch_method(){return'ticket';}};
$ci->input->verb='GET';$ci->input->data=[];$ci->input->query=[];
$ci->load=new class{
    public string$html='';public array$data=[];
    function model($name){throw new RuntimeException('global model hydration forbidden');}
    function view($path,$data=[]){
        kc($path==='admin/tickets/single','original ticket detail template selected');
        $this->data=$data;extract($data);ob_start();try{require APPPATH.'views/'.$path.'.php';$this->html=ob_get_clean();}catch(Throwable$e){ob_end_clean();throw$e;}
    }
};
$GLOBALS['ci']=$ci;$page=new Tickets();$page->ticket($id);$html=$ci->load->html;$data=$ci->load->data;
kc(isset($data['native_ticket_page'])&&$data['ticket']->ticketid===$id,'actual route composes issuer-owned detail DTO');
kc(str_contains($html,'id="ticket-reply"')&&str_contains($html,'name="_company_context"')&&str_contains($html,'name="_native_company_csrf"'),'original detail carries both context-bound mutation proofs');
kc(str_contains($html,'Typed <strong>response</strong>')&&!str_contains($html,'global model hydration'),'actual owned reply renders with proven rich-text provenance');
kc(str_contains($html,'download/file/ticket/'.$key.'?ticket_id='.$id),'private attachment uses the scoped original download route');
$ci->input->verb='PUT';$deny(fn()=>$page->ticket($id));
$db->query('UPDATE tblissuers_staff SET can_view=0 WHERE issuer_id=2 AND staff_id=17');$deny(fn()=>$page->ticket($id));
echo "Original Ticket detail page: scoped GET, original view, reply form, thread/file provenance, method and revoked-ACL boundaries passed.\n";
