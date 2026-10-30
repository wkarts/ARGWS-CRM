<?php

declare(strict_types=1);
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH.'services/NativeCompanyResourceReferences.php';
if(!class_exists('CompanyRequestScope',false))require_once APPPATH.'services/CompanyRequestScope.php';
require_once APPPATH.'services/issuers/IssuerStorage.php';
require_once APPPATH.'services/issuers/IssuerTransaction.php';
require_once APPPATH.'services/issuers/IssuerPolicy.php';
require_once APPPATH.'services/CompanyClients.php';
require_once APPPATH.'services/CompanyExpenses.php';
require_once APPPATH.'services/CompanyInvoices.php';
require_once APPPATH.'services/CompanyInvoiceDelivery.php';
require_once APPPATH.'services/InvoicePaymentWriter.php';
require_once APPPATH.'services/NativeCompanyPaymentRecords.php';
require_once APPPATH.'services/CompanyProjects.php';
require_once APPPATH.'services/CompanyTasks.php';
require_once APPPATH.'services/CompanyTaskTimeBilling.php';
require_once APPPATH.'services/CompanyProjectExtras.php';
require_once APPPATH.'services/CompanyProjectExtrasSchema.php';
require_once APPPATH.'services/CompanyProjectGantt.php';
require_once APPPATH.'services/CompanyProjectDiscussions.php';
require_once APPPATH.'services/CompanyProjectTimesheets.php';
require_once APPPATH.'services/CompanyProjectFilesZip.php';
require_once APPPATH.'services/CompanyFinancialDocumentPdf.php';
require_once APPPATH.'services/CompanySettingsIdentity.php';
require_once APPPATH.'services/CompanyMilestoneVisualSchema.php';
require_once APPPATH.'services/CompanyClientStatementDeliverySchema.php';

/**
 * Bounded adapters for the original controller methods that were still outside
 * the native company catalogue.  Every branch below resolves a typed resource,
 * uses an issuer-owned service/table, and returns a finite JSON/file contract.
 * It deliberately never loads a legacy model.
 */
final class NativeCompanyParityHttp
{
    public static function handle($ci,string $module,string $action,array $arguments=[]):void
    {
        if(!CompanyRequestScope::nativeFor($ci)){show_error('Contexto de empresa indisponível.',503);return;}
        $context=CompanyRequestScope::context();$issuer=$context?->issuerId();$actor=$context?->staffId();
        if($issuer===null){show_error('Selecione uma empresa autorizada.',403);return;}
        $method=strtoupper((string)$ci->input->method(true));$input=self::input($ci);
        try{
            if($method==='POST')CompanyRequestScope::assertNativeMutation($ci);
            elseif(!in_array($method,['GET','HEAD'],true))throw new DomainException('native_parity_method_invalid');
            $result=self::dispatch($ci,$module,$action,$arguments,$input,$issuer,$actor,$method);
            if(isset($result['__download'])){
                self::download($ci,$result['__download']['mime'],$result['__download']['name'],$result['__download']['body']);
                return;
            }
            $status=(int)($result['__status']??200);unset($result['__status']);
            self::json($ci,$result,$status);
        }catch(DomainException $error){
            $reason=$error->getMessage();$status=in_array($reason,['native_parity_denied','native_resource_reference_denied','company_client_access_denied','company_expense_denied','company_invoice_access_denied','company_invoice_delivery_denied','native_payment_denied','company_project_access_denied','company_project_portal_access_denied'],true)?403:(str_contains($reason,'stale')||str_contains($reason,'conflict')?409:422);
            show_error('Não foi possível confirmar esta ação no contexto da empresa.', $status);
        }catch(Throwable $error){
            log_message('error','Native company parity action failed; no request data logged.');
            show_error('Não foi possível confirmar esta ação sem acessar dados de outra empresa.',503);
        }
    }

    private static function dispatch($ci,string $module,string $action,array $args,array $input,int $issuer,int $actor,string $method):array
    {
        return match($module){
            'clients'=>self::clients($ci,$action,$args,$input,$issuer,$actor,$method),
            'expenses'=>self::expenses($ci,$action,$args,$input,$issuer,$actor,$method),
            'invoices'=>self::invoices($ci,$action,$args,$input,$issuer,$actor,$method),
            'payments'=>self::payments($ci,$action,$args,$input,$issuer,$actor,$method),
            'projects'=>self::projects($ci,$action,$args,$input,$issuer,$actor,$method),
            'tasks'=>self::tasks($ci,$action,$args,$input,$issuer,$actor,$method),
            default=>throw new DomainException('native_parity_invalid'),
        };
    }

    private static function input($ci):array
    {
        $input=$ci->input->post(null,false);if(!is_array($input))$input=[];
        foreach(['_company_context','_native_company_csrf','native_company_csrf'] as $key)unset($input[$key]);
        if(isset($ci->security))unset($input[$ci->security->get_csrf_token_name()]);
        return $input;
    }
    private static function value($ci,array $input,string $name,$default=null)
    {
        if(array_key_exists($name,$input))return $input[$name];$value=$ci->input->get($name,false);return $value===null?$default:$value;
    }
    private static function id($value):int{return IssuerPolicy::id($value);}
    private static function key(array $input,string $fallback='request_key'):string
    {
        foreach([$fallback,'native_request_key','_native_payment_key'] as $name){if(isset($input[$name])&&is_string($input[$name])&&preg_match('/^[a-f0-9]{64}$/D',$input[$name]))return $input[$name];}
        throw new DomainException('native_parity_invalid');
    }
    private static function revision(array $input,string $name='revision'):string
    {
        $value=$input[$name]??$input['native_revision']??null;if(!is_string($value)&&!is_int($value))throw new DomainException('native_parity_stale');
        if(!preg_match('/^(0|[1-9][0-9]{0,9})$/D',(string)$value))throw new DomainException('native_parity_stale');return (string)$value;
    }
    private static function hashRevision(array $input,array $names=['revision','native_revision','_native_invoice_revision']):string
    {
        foreach($names as $name){$value=$input[$name]??null;if(is_string($value)&&preg_match('/^[a-f0-9]{64}$/D',$value))return $value;}
        throw new DomainException('native_parity_stale');
    }
    private static function json($ci,array $body,int $status=200):void
    {
        $ci->output->set_status_header($status)->set_header('Cache-Control','no-store')->set_content_type('application/json')->set_output(json_encode($body,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    }
    private static function download($ci,string $mime,string $name,string $body):void
    {
        if(!preg_match('/^[a-z0-9.+-]+\/[a-z0-9.+-]+$/i',$mime)||!preg_match('/^[A-Za-z0-9._-]{1,160}$/D',$name))throw new DomainException('native_parity_invalid');
        $ci->output->set_header('Cache-Control','private, no-store')->set_header('X-Content-Type-Options','nosniff')->set_header("Content-Disposition: attachment; filename=\"$name\"")->set_content_type($mime)->set_output($body);
    }
    private static function ref($ci,string $kind,$value,int $issuer,int $actor,string $cap='view'):array
    {
        return NativeCompanyResourceReferences::resolve($ci->db,db_prefix(),$issuer,$actor,$kind,$value,$cap);
    }
    private static function client($ci,$value,int $issuer,int $actor,string $cap='view'):array
    {
        $reference=self::ref($ci,'client',$value,$issuer,$actor,$cap);return[(int)$reference['source_id'],$reference];
    }
    private static function project($ci,$value,int $issuer,int $actor,string $cap='view'):array
    {
        $reference=self::ref($ci,'project',$value,$issuer,$actor,$cap);if($reference['source_kind']!=='company_project')throw new DomainException('native_parity_legacy_closed');return[(int)$reference['source_id'],$reference];
    }
    private static function task($ci,$value,int $issuer,int $actor,string $cap='view'):array
    {
        $reference=self::ref($ci,'task',$value,$issuer,$actor,$cap);if($reference['source_kind']!=='company_task')throw new DomainException('native_parity_legacy_closed');return[(int)$reference['source_id'],$reference];
    }
    private static function rows($db,string $sql,array $params=[]):array{return IssuerStorage::query($db,$sql,$params)->result_array();}
    private static function table($db,string $prefix,string $name):bool{return method_exists($db,'table_exists')&&$db->table_exists($prefix.$name);}
    private static function column($db,string $table,string $name):bool{return method_exists($db,'field_exists')&&$db->field_exists($name,$table);}
    private static function requestFingerprint(array $input):string
    {
        foreach(['request_key','native_request_key'] as $key)if(isset($input[$key])&&is_string($input[$key])&&preg_match('/^[a-f0-9]{64}$/D',$input[$key]))return $input[$key];
        return hash('sha256',json_encode($input,JSON_THROW_ON_ERROR));
    }
    private static function expenseData(array $data):array
    {
        $allowed=['category_id','client_id','project_id','currency','date','description','note','amount','tax_rate','billable','native_form'];
        if(array_diff(array_keys($data),$allowed))$data=array_intersect_key($data,array_flip($allowed));
        return $data;
    }
    private static function audit($db,string $p,int $issuer,int $actor,int $subject,string $event,array $meta=[]):void
    {
        $json=json_encode($meta,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);IssuerStorage::query($db,'INSERT INTO '.$p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$actor,$event,$issuer,$subject,gmdate('Y-m-d H:i:s'),$json]);$id=(int)$db->insert_id();if($id<1||!self::rows($db,'SELECT id FROM '.$p.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=? AND event=?',[$id,$issuer,$actor,$subject,$event]))throw new RuntimeException('native_parity_audit_unconfirmed');
    }
    private static function identity($db,string $p,int $issuer):array
    {
        $settings=new CompanySettingsIdentity($db,$p);$record=$settings->read($issuer);$profile=$record['profile'];$get=static fn(string $key):string=>(string)($profile[$key]??'');
        return ['legal_name'=>$record['legal_name'],'tax_id'=>$record['tax_id'],'address'=>CompanySettingsIdentity::documentAddress($get),'city'=>$get('invoice_company_city'),'state'=>$get('company_state'),'country_code'=>$get('invoice_company_country_code'),'postcode'=>$get('invoice_company_postal_code'),'email'=>$get('invoice_company_email'),'phone'=>$get('invoice_company_phonenumber'),'logo_inline'=>'','signature_inline'=>'','terms'=>''];
    }
    private static function recipient($db,string $p,int $client):array
    {
        $row=self::rows($db,'SELECT company,vat FROM '.$p.'clients WHERE userid=?',[$client])[0]??[];return ['name'=>(string)($row['company']??''),'tax_id'=>(string)($row['vat']??'')];
    }
    private static function date($value,string $error='native_parity_invalid'):string
    {
        if(!is_string($value)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value)||!DateTimeImmutable::createFromFormat('!Y-m-d',$value))throw new DomainException($error);return $value;
    }
    private static function zip(array $entries):string
    {
        if(count($entries)>100)throw new DomainException('native_parity_limit');$entries['manifest.json']=json_encode(['format'=>1,'files'=>array_keys($entries)],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";$body='';$central='';$count=0;
        foreach($entries as $name=>$content){if(!is_string($name)||!preg_match('/^[A-Za-z0-9._-]{1,160}$/D',$name)||!is_string($content)||strlen($content)>20971520)throw new DomainException('native_parity_limit');$offset=strlen($body);$size=strlen($content);$crc=crc32($content);$length=strlen($name);$body.=pack('VvvvvvVVVvv',0x04034b50,20,0x800,0,0,33,$crc,$size,$size,$length,0).$name.$content;$central.=pack('VvvvvvvVVVvvvvvVV',0x02014b50,20,20,0x800,0,0,33,$crc,$size,$size,$length,0,0,0,0,0,$offset).$name;$count++;}
        return $body.$central.pack('VvvvvVVv',0x06054b50,0,0,$count,$count,strlen($central),strlen($body),0);
    }

    private static function clients($ci,string $action,array $args,array $input,int $issuer,int $actor,string $method):array
    {
        $service=new CompanyClients($ci->db,db_prefix());
        if($action==='export'){
            if(!in_array($method,['GET','HEAD'],true))throw new DomainException('native_parity_method_invalid');$id=self::id($args[0]??self::value($ci,$input,'contact_id'));
            $row=self::rows($ci->db,'SELECT c.id,c.firstname,c.lastname,c.email,c.active,c.userid,cl.company,cl.vat FROM '.db_prefix().'contacts c JOIN '.db_prefix().'clients cl ON cl.userid=c.userid JOIN '.db_prefix().'issuers_clients o ON o.client_id=c.userid AND o.owner_issuer_id=? WHERE c.id=?',[$issuer,$id])[0]??null;
            if(!$row){[$client]=self::client($ci,$id,$issuer,$actor);$row=self::rows($ci->db,'SELECT userid,company,vat,active FROM '.db_prefix().'clients WHERE userid=?',[$client])[0]??null;}
            if(!$row)throw new DomainException('native_parity_denied');return['__download'=>['mime'=>'application/json','name'=>'cliente-exportacao.json','body'=>json_encode($row,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)]];
        }
        if($action==='update_all_proposal_emails_linked_to_customer'){
            if($method!=='POST')throw new DomainException('native_parity_method_invalid');$contact=self::id($args[0]??self::value($ci,$input,'contact_id'));$row=self::rows($ci->db,'SELECT c.userid,c.email FROM '.db_prefix().'contacts c JOIN '.db_prefix().'issuers_clients o ON o.client_id=c.userid AND o.owner_issuer_id=? WHERE c.id=?',[$issuer,$contact])[0]??null;if(!$row)throw new DomainException('native_parity_denied');$original=$input['original_email']??'';if(!is_string($original)||strlen($original)>191)throw new DomainException('native_parity_invalid');$updated=0;
            if(self::table($ci->db,db_prefix(),'proposals')&&self::column($ci->db,db_prefix().'proposals','issuer_id')){$updated=(int)$ci->db->query('UPDATE '.db_prefix().'proposals SET email=? WHERE issuer_id=? AND rel_type=? AND rel_id=? AND email=?',[$row['email'],$issuer,'customer',$row['userid'],$original]);}
            self::audit($ci->db,db_prefix(),$issuer,$actor,(int)$row['userid'],'native_client_proposal_email_sync',['updated'=>$updated]);return['success'=>true,'updated'=>$updated,'scoped'=>true];
        }
        if($action==='delete'){
            if($method!=='POST')throw new DomainException('native_parity_method_invalid');[$id]=self::client($ci,$args[0]??$input['client_id']??null,$issuer,$actor,'delete');$revision=self::hashRevision($input);$service->deleteUnreferenced($issuer,$actor,$id,$revision);CompanyRequestScope::invalidateCaches($ci);return['success'=>true,'client_id'=>$id];
        }
        if(in_array($action,['zip_credit_notes','zip_invoices','zip_estimates','zip_payments'],true)){
            if(!in_array($method,['GET','HEAD','POST'],true))throw new DomainException('native_parity_method_invalid');[$client]=self::client($ci,$args[0]??self::value($ci,$input,'client_id'),$issuer,$actor);$entries=[];$p=db_prefix();
            if($action==='zip_credit_notes'&&self::table($ci->db,$p,'company_credit_documents')){require_once APPPATH.'services/CompanyCredits.php';foreach(self::rows($ci->db,'SELECT credit_id FROM '.$p.'company_credit_documents WHERE issuer_id=? AND credit_id IN (SELECT id FROM '.$p.'creditnotes WHERE clientid=?) ORDER BY credit_id LIMIT 100',[$issuer,$client]) as $row){$credit=(new CompanyCredits($ci->db,$p))->get($issuer,$actor,(int)$row['credit_id']);$entries['credit-'.$row['credit_id'].'.json']=json_encode($credit,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}}
            elseif($action==='zip_payments'){foreach(self::rows($ci->db,'SELECT p.id FROM '.$p.'invoicepaymentrecords p JOIN '.$p.'invoices i ON i.id=p.invoiceid WHERE i.issuer_id=? AND i.clientid=? ORDER BY p.id LIMIT 100',[$issuer,$client]) as $row){$data=(new NativeCompanyPaymentRecords($ci->db,$p))->read($issuer,$actor,(int)$row['id']);$entries['payment-'.$row['id'].'.pdf']=CompanyFinancialDocumentPdf::payment($data);}}
            else{$kind=$action==='zip_invoices'?'invoice':($action==='zip_estimates'?'estimate':'proposal');$table=$kind==='invoice'?'invoices':'company_sales_documents';$where=$kind==='invoice'?'issuer_id=? AND clientid=?':'issuer_id=? AND client_id=? AND kind=?';$params=$kind==='invoice'?[$issuer,$client]:[$issuer,$client,$kind];if(self::table($ci->db,$p,$table)){foreach(self::rows($ci->db,'SELECT * FROM '.$p.$table.' WHERE '.$where.' ORDER BY id LIMIT 100',$params) as $row)$entries[$kind.'-'.$row['id'].'.json']=json_encode($row,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}}
            return['__download'=>['mime'=>'application/zip','name'=>'cliente-documentos.zip','body'=>self::zip($entries)]];
        }
        if($action==='bulk_action'){
            if($method!=='POST'||!is_array($input['ids']??null))throw new DomainException('native_parity_invalid');$operation=$input['operation']??($input['mass_delete']??null?'delete':'groups');$done=0;$revisions=$input['revisions']??[];$groups=$input['groups']??[];foreach($input['ids'] as $raw){[$id]=self::client($ci,$raw,$issuer,$actor,'edit');if($operation==='delete'){$rev=$revisions[$raw]??$revisions[(string)$id]??null;if(!is_string($rev))throw new DomainException('native_parity_stale');$service->deleteUnreferenced($issuer,$actor,$id,$rev);$done++;}elseif($operation==='groups'){$details=$service->details($issuer,$actor,$id);$values=$groups[$raw]??$groups[(string)$id]??[];if(!$details||!is_array($values))throw new DomainException('native_parity_invalid');$service->update($issuer,$actor,$id,['company'=>$details['client']['company'],'vat'=>$details['client']['vat'],'groups_in'=>$values],$revisions[$raw]??$revisions[(string)$id]??$details['revision']);$done++;}else throw new DomainException('native_parity_invalid');}CompanyRequestScope::invalidateCaches($ci);return['success'=>true,'processed'=>$done];
        }
        if(in_array($action,['statement','statement_pdf','send_statement'],true)){
            [$client]=self::client($ci,$args[0]??self::value($ci,$input,'customer_id')??self::value($ci,$input,'client_id'),$issuer,$actor);$from=$input['from']??$ci->input->get('from',false)??'2000-01-01';$to=$input['to']??$ci->input->get('to',false)??'2100-12-31';$from=self::date($from);$to=self::date($to);if($from>$to)throw new DomainException('native_parity_invalid');$rows=self::rows($ci->db,'SELECT i.id,i.formatted_number,i.date,i.duedate,i.total,i.status,i.currency FROM '.db_prefix().'invoices i WHERE i.issuer_id=? AND i.clientid=? AND i.date BETWEEN ? AND ? ORDER BY i.date,i.id LIMIT 500',[$issuer,$client,$from,$to]);$statement=['client_id'=>$client,'from'=>$from,'to'=>$to,'invoices'=>$rows];
            if($action==='statement')return$statement;
            if($action==='statement_pdf'){ $identity=self::identity($ci->db,db_prefix(),$issuer);$lines=['Período: '.$from.' a '.$to];foreach($rows as $row)$lines[]='Fatura '.$row['formatted_number'].' | '.$row['date'].' | total '.$row['total'].' | status '.$row['status'];return['__download'=>['mime'=>'application/pdf','name'=>'extrato-cliente.pdf','body'=>CompanyFinancialDocumentPdf::render('Extrato do cliente',$identity,self::recipient($ci->db,db_prefix(),$client),$lines)]]; }
            if($method!=='POST')throw new DomainException('native_parity_method_invalid');$sendTo=$input['send_to']??$input['sent_to']??$input['contact_id']??[];if(!is_array($sendTo))$sendTo=[$sendTo];$contactIds=[];foreach($sendTo as $contact){if($contact===''||$contact===null)continue;$contactIds[]=self::id($contact);} $contactIds=array_values(array_unique($contactIds));if(!$contactIds||count($contactIds)>100)throw new DomainException('native_parity_invalid');$cc=$input['cc']??'';if(!is_string($cc)||preg_match('/[\r\n]/',$cc)||strlen($cc)>2000)throw new DomainException('native_parity_invalid');$ccParts=array_values(array_filter(array_map('trim',preg_split('/[,;]+/',$cc,-1,PREG_SPLIT_NO_EMPTY))));foreach($ccParts as $address)if(!filter_var($address,FILTER_VALIDATE_EMAIL))throw new DomainException('native_parity_invalid');$key=self::key($input);$queued=self::queueClientStatement($ci,$issuer,$actor,$client,$from,$to,$contactIds,implode(',',$ccParts),$key);return['success'=>true,'client_id'=>$client,'queued'=>$queued,'request_key'=>$key,'delivery'=>'mail_queue'];
        }
        throw new DomainException('native_parity_invalid');
    }

    private static function queueClientStatement($ci,int $issuer,int $actor,int $client,string $from,string $to,array $contactIds,string $cc,string $requestKey):array
    {
        $p=db_prefix();CompanyClientStatementDeliverySchema::assertValid($ci->db,$p);$ci->load->library('encryption');$decrypt=static fn($value)=>$ci->encryption->decrypt($value);$marks=implode(',',array_fill(0,count($contactIds),'?'));$tables=['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_clients','clients','contacts','invoices','issuers_invoice_documents','emailtemplates','options','mail_queue','company_client_statement_deliveries','issuers_audit','itemable','item_tax'];$tables=array_merge($tables,CompanyGoogleMailOAuth::participantTables($ci->db,$p),CompanyMailTransportPolicy::participantTables($ci->db,$p));
        return IssuerTransaction::run($ci->db,$p,$tables,function()use($ci,$p,$issuer,$actor,$client,$from,$to,$contactIds,$cc,$requestKey,$decrypt,$marks):array{
            $parent=self::rows($ci->db,'SELECT c.userid,c.company,c.vat,r.legal_name FROM '.$p.'clients c JOIN '.$p.'issuers_clients o ON o.client_id=c.userid AND o.owner_issuer_id=? JOIN '.$p.'issuers_registry r ON r.id=o.owner_issuer_id AND r.active=1 WHERE c.userid=? FOR UPDATE',[$issuer,$client])[0]??null;if(!$parent)throw new DomainException('company_invoice_delivery_denied');
            $contacts=self::rows($ci->db,'SELECT id,firstname,lastname,email FROM '.$p.'contacts WHERE userid=? AND active=1 AND id IN('.$marks.') ORDER BY id',array_merge([$client],$contactIds));if(count($contacts)!==count($contactIds))throw new DomainException('company_invoice_delivery_denied');$byId=[];foreach($contacts as $contact){$contact['id']=(int)$contact['id'];if(!filter_var($contact['email'],FILTER_VALIDATE_EMAIL)||preg_match('/[\\r\\n]/',(string)$contact['email']))throw new DomainException('company_invoice_delivery_recipient_required');$byId[$contact['id']]=$contact;}
            $rows=self::rows($ci->db,'SELECT i.id,i.formatted_number,i.date,i.duedate,i.total,i.status,i.currency FROM '.$p.'invoices i WHERE i.issuer_id=? AND i.clientid=? AND i.date BETWEEN ? AND ? ORDER BY i.date,i.id LIMIT 500 FOR UPDATE',[$issuer,$client,$from,$to]);$aggregate=self::rows($ci->db,'SELECT (SELECT COALESCE(SUM(i1.total),0) FROM '.$p.'invoices i1 WHERE i1.issuer_id=? AND i1.clientid=? AND i1.date BETWEEN ? AND ? AND i1.status NOT IN (5,6)) invoiced,(SELECT COALESCE(SUM(pr.amount),0) FROM '.$p.'invoicepaymentrecords pr JOIN '.$p.'invoices i2 ON i2.id=pr.invoiceid WHERE i2.issuer_id=? AND i2.clientid=? AND i2.date BETWEEN ? AND ?) paid',[$issuer,$client,$from,$to,$issuer,$client,$from,$to])[0]??['invoiced'=>'0','paid'=>'0'];$balance=number_format(max(0,(float)$aggregate['invoiced']-(float)$aggregate['paid']),2,'.','');$identity=self::identity($ci->db,$p,$issuer);$lines=['Período: '.$from.' a '.$to];foreach($rows as $row)$lines[]='Fatura '.$row['formatted_number'].' | '.$row['date'].' | total '.$row['total'].' | status '.$row['status'];$pdf=CompanyFinancialDocumentPdf::render('Extrato do cliente',$identity,self::recipient($ci->db,$p,$client),$lines);$payloadHash=hash('sha256',$pdf);$revision=hash('sha256',json_encode(['client'=>$client,'from'=>$from,'to'=>$to,'invoices'=>$rows],JSON_THROW_ON_ERROR));$store=new CompanySettingsStorage($ci->db,$p);$config=CompanyMailConfig::build($ci->db,$p,$issuer,$decrypt,null,'document_notifications');$template=CompanyEmailTemplates::findForIssuer($ci->db,$p,$issuer,'client-statement','portuguese_br');if(!$template||(int)$template->active!==1)throw new DomainException('company_invoice_delivery_template_required');$mailFrom=trim($store->get($issuer,'smtp_email'));if(!filter_var($mailFrom,FILTER_VALIDATE_EMAIL)||preg_match('/[\\r\\n]/',$mailFrom))throw new DomainException('company_invoice_delivery_sender_required');$link=rtrim($ci->config->base_url(),'/').'/company_portal/index/'.$issuer;$escape=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$queued=[];$transport=new CompanyMailTransportPolicy($ci->db,$p);$index=0;
            foreach($contactIds as $contactId){$contact=$byId[$contactId];$deliveryKey=hash('sha256','client-statement:'.$requestKey.':'.$client.':'.$from.':'.$to.':'.$contactId);$old=self::rows($ci->db,'SELECT request_key,issuer_id,actor_id,client_id,contact_id,revision,recipient_hash,payload_hash,queue_id FROM '.$p.'company_client_statement_deliveries WHERE request_key=? FOR UPDATE',[$deliveryKey])[0]??null;$recipientHash=hash('sha256',json_encode([$contact['id'],$contact['firstname'],$contact['lastname'],$contact['email']],JSON_THROW_ON_ERROR));if($old){foreach(['issuer_id'=>$issuer,'actor_id'=>$actor,'client_id'=>$client,'contact_id'=>$contactId,'revision'=>$revision,'recipient_hash'=>$recipientHash,'payload_hash'=>$payloadHash] as $field=>$value)if((string)$old[$field]!==(string)$value)throw new DomainException('company_invoice_delivery_conflict');$transport->assertQueueInOwnedTransaction($issuer,$actor,(int)$old['queue_id'],'client_statement',$deliveryKey);$queued[]=(int)$old['queue_id'];$index++;continue;}
                $plain=['{companyname}'=>$parent['legal_name'],'{contact_firstname}'=>$contact['firstname'],'{contact_lastname}'=>$contact['lastname'],'{contact_email}'=>$contact['email'],'{client_company}'=>$parent['company'],'{statement_from}'=>$from,'{statement_to}'=>$to,'{statement_balance_due}'=>$balance,'{crm_url}'=>$link];$html=array_map($escape,$plain);$html['{email_signature}']=$store->get($issuer,'email_signature');$message=strtr($store->get($issuer,'email_header').$template->message.$store->get($issuer,'email_footer'),$html);$subject=strtr($template->subject,$plain);$fromName=strtr($template->fromname,$plain);if(preg_match('/[\\r\\n]/',$subject.$fromName))throw new DomainException('company_invoice_delivery_template_required');if((int)$template->plaintext===1)$message=nl2br($escape(html_entity_decode(strip_tags(str_replace(['<br>','<br />','<br/>'],"\n",$message)),ENT_QUOTES,'UTF-8')."\n".$link));$headers=$config['useragent']==='phpmailer'?['from'=>$mailFrom,'fromName'=>$fromName,'subject'=>$subject]:['From'=>mb_encode_mimeheader($fromName,'UTF-8','B',"\r\n").' <'.$mailFrom.'>','Subject'=>mb_encode_mimeheader($subject,'UTF-8','B',"\r\n"),'Content-Type'=>'text/html; charset=UTF-8'];$headers['companyClientStatementDelivery']=$deliveryKey;$attachment=[['attachment'=>$pdf,'filename'=>'extrato-cliente-'.$client.'.pdf','type'=>'application/pdf']];$fields=['engine'=>$config['useragent'],'email'=>$contact['email'],'cc'=>$index===0?$cc:'','bcc'=>'','message'=>$message,'alt_message'=>html_entity_decode(strip_tags($message),ENT_QUOTES,'UTF-8'),'headers'=>serialize($headers),'attachments'=>base64_encode(serialize($attachment)),'status'=>'pending','date'=>date('Y-m-d H:i:s'),'issuer_id'=>$issuer];IssuerStorage::query($ci->db,'INSERT INTO '.$p.'mail_queue (`'.implode('`,`',array_keys($fields)).'`) VALUES ('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));$queue=(int)$ci->db->insert_id();if($queue<1)throw new RuntimeException('issuer_client_statement_unconfirmed');$written=self::rows($ci->db,'SELECT * FROM '.$p.'mail_queue WHERE id=? AND issuer_id=?',[$queue,$issuer])[0]??[];foreach($fields as $field=>$value)if((string)($written[$field]??'')!==(string)$value)throw new RuntimeException('issuer_client_statement_unconfirmed');$receipt=['request_key'=>$deliveryKey,'issuer_id'=>$issuer,'actor_id'=>$actor,'client_id'=>$client,'contact_id'=>$contactId,'revision'=>$revision,'recipient_hash'=>$recipientHash,'payload_hash'=>$payloadHash,'queue_id'=>$queue,'created_at'=>gmdate('Y-m-d H:i:s')];IssuerStorage::query($ci->db,'INSERT INTO '.$p.'company_client_statement_deliveries ('.implode(',',array_keys($receipt)).') VALUES('.implode(',',array_fill(0,count($receipt),'?')).')',array_values($receipt));$transport->bindQueueInOwnedTransaction($issuer,$actor,$queue,'client_statement',$deliveryKey);$audit=['queue_id'=>$queue,'contact_id'=>$contactId,'request_key'=>$deliveryKey,'from'=>$from,'to'=>$to];self::audit($ci->db,$p,$issuer,$actor,$client,'native_client_statement_queued',$audit);$queued[]=$queue;$index++;
            }
            return $queued;
        });
    }


    private static function expenses($ci,string $action,array $args,array $input,int $issuer,int $actor,string $method):array
    {
        $service=new CompanyExpenses($ci->db,db_prefix());
        if($action==='bulk_action'){
            if($method!=='POST'||!is_array($input['ids']??null))throw new DomainException('native_parity_invalid');$operation=$input['operation']??($input['mass_delete']??null?'cancel':'update');$done=0;foreach($input['ids'] as $raw){$id=self::id($raw);$row=$service->get($issuer,$actor,$id);if(!$row)throw new DomainException('company_expense_denied');$key=hash('sha256','native-expense-bulk:'.$issuer.':'.$id.':'.self::key($input));if($operation==='cancel')$service->change($issuer,$actor,$id,$row['revision_token'],$key,null);elseif($operation==='update'){$data=$input['expenses'][$raw]??$input['expenses'][(string)$id]??$input['expense']??null;if(!is_array($data))throw new DomainException('native_parity_invalid');$service->change($issuer,$actor,$id,$row['revision_token'],$key,self::expenseData($data));}else throw new DomainException('native_parity_invalid');$done++;}CompanyRequestScope::invalidateCaches($ci);return['success'=>true,'processed'=>$done];
        }
        if($action==='pdf'){
            if(!in_array($method,['GET','HEAD'],true))throw new DomainException('native_parity_method_invalid');$id=self::id($args[0]??self::value($ci,$input,'id'));$expense=$service->get($issuer,$actor,$id);if(!$expense)throw new DomainException('company_expense_denied');$client=(int)$expense['client_id'];$identity=self::identity($ci->db,db_prefix(),$issuer);$lines=['Número: '.$expense['number'],'Data: '.$expense['expense_date'],'Categoria: '.$expense['category_name'],'Descrição: '.$expense['description'],'Valor: '.$expense['currency'].' '.CompanyExpenses::money((int)$expense['amount_cents']),'Tributo: '.$expense['currency'].' '.CompanyExpenses::money((int)$expense['tax_cents']),'Total: '.$expense['currency'].' '.CompanyExpenses::money((int)$expense['total_cents'])];return['__download'=>['mime'=>'application/pdf','name'=>'despesa-'.$id.'.pdf','body'=>CompanyFinancialDocumentPdf::render('Despesa #'.$expense['number'],$identity,$client?self::recipient($ci->db,db_prefix(),$client):['name'=>'','tax_id'=>''],$lines)]];
        }
        if($action==='add_expense'){
            if($method!=='POST')throw new DomainException('native_parity_method_invalid');$data=self::expenseData($input['expense']??$input);if(isset($data['project_id'])&&$data['project_id']!=='')$data['project_id']=self::id($data['project_id']);$data['category_id']=self::id($data['category_id']??null);$data['client_id']=$data['client_id']??0;$data['currency']=$data['currency']??'BRL';$data['date']=$data['date']??$data['expense_date']??null;$data['description']=$data['description']??null;$data['amount']=$data['amount']??null;$data['note']=$data['note']??'';$data['tax_rate']=$data['tax_rate']??'0.00';$id=$service->create($issuer,$actor,$data,self::key($input));CompanyRequestScope::invalidateCaches($ci);return['success'=>true,'expense_id'=>$id];
        }
        if($action==='expenses'){
            if(!in_array($method,['GET','HEAD'],true))throw new DomainException('native_parity_method_invalid');$project=self::project($ci,$args[0]??self::value($ci,$input,'project_id'),$issuer,$actor)[0];$items=self::rows($ci->db,'SELECT id,number,expense_date,description,currency,total_cents,state,revision FROM '.db_prefix().'company_expenses WHERE issuer_id=? AND project_id=? ORDER BY id LIMIT 500',[$issuer,$project]);return['project_id'=>$project,'items'=>$items,'total'=>count($items)];
        }
        throw new DomainException('native_parity_invalid');
    }

    private static function invoices($ci,string $action,array $args,array $input,int $issuer,int $actor,string $method):array
    {
        if($action==='apply_credits'){
            if($method!=='POST'||!is_array($input['amount']??null))throw new DomainException('native_parity_invalid');$invoice=self::ref($ci,'invoice',$args[0]??$input['invoice_id']??null,$issuer,$actor,'edit');if($invoice['source_kind']==='company_invoice_draft')throw new DomainException('native_parity_denied');$count=0;$writer=new \app\services\InvoicePaymentWriter($ci->db);$baseKey=self::key($input);foreach($input['amount'] as $credit=>$amount){if(!is_string($amount)&&!is_int($amount))throw new DomainException('native_parity_invalid');if((float)$amount<=0)continue;$creditId=self::id($credit);$key=hash('sha256','native-credit-apply:'.$baseKey.':'.$creditId);$writer->moveCompanyCredit($issuer,$actor,'apply',['credit_id'=>$creditId,'invoice_id'=>(int)$invoice['source_id'],'amount'=>(string)$amount,'date'=>$input['date']??date('Y-m-d'),'reference'=>'native-invoice-'.$invoice['source_id'].'-credit-'.$creditId,'reason'=>'Aplicação pelo módulo nativo da empresa'],$key);$count++;}CompanyRequestScope::invalidateCaches($ci);return['success'=>true,'applied'=>$count];
        }
        if($action==='delete'){
            if($method!=='POST')throw new DomainException('native_parity_method_invalid');[$id]=self::invoiceId($ci,$args[0]??$input['invoice_id']??null,$issuer,$actor,'edit');$revision=self::hashRevision($input);(new CompanyInvoices($ci->db,db_prefix()))->change($issuer,$actor,$id,$revision,null);CompanyRequestScope::invalidateCaches($ci);return['success'=>true,'invoice_id'=>$id,'status'=>'cancelled'];
        }
        if($action==='send_overdue_notice'){
            if($method!=='POST')throw new DomainException('native_parity_method_invalid');[$id,$ref]=self::invoiceId($ci,$args[0]??$input['invoice_id']??null,$issuer,$actor,'view');$contact=$input['sent_to']??$input['contact_id']??null;if(is_array($contact))$contact=$contact[0]??null;if($contact===null)throw new DomainException('native_parity_invalid');$contact=self::id($contact);$revision=self::hashRevision($input);$ci->load->library('encryption');$queue=(new CompanyInvoiceDelivery($ci->db,db_prefix()))->queueOverdueNotice($issuer,$actor,$id,$contact,$revision,self::key($input),$ci->config->base_url(),fn($value)=>$ci->encryption->decrypt($value));return['success'=>true,'queue_id'=>$queue,'invoice_id'=>$id,'delivery'=>'queued'];
        }
        throw new DomainException('native_parity_invalid');
    }
    private static function invoiceId($ci,$value,int $issuer,int $actor,string $cap):array{$ref=self::ref($ci,'invoice',$value,$issuer,$actor,$cap);if($ref['source_kind']==='company_invoice_draft')throw new DomainException('native_parity_denied');return[(int)$ref['source_id'],$ref];}

    private static function payments($ci,string $action,array $args,array $input,int $issuer,int $actor,string $method):array
    {
        if($action!=='send_to_email'||$method!=='POST')throw new DomainException('native_parity_invalid');$id=$args[0]??$input['payment_id']??null;$ref=self::ref($ci,'payment',$id,$issuer,$actor,'view');$data=(new NativeCompanyPaymentRecords($ci->db,db_prefix()))->read($issuer,$actor,$ref['source_id']);$recipients=$input['sent_to']??$input['contact_id']??[];if(!is_array($recipients))$recipients=[$recipients];if(!$recipients)throw new DomainException('native_parity_invalid');$paymentKey=$input['payment_key']??$input['request_key']??null;if(!is_string($paymentKey)||!preg_match('/^[a-f0-9]{64}$/D',$paymentKey))throw new DomainException('company_invoice_delivery_invalid');$ci->load->library('encryption');$invoice=(int)$data['payment']['invoiceid'];$revision=(new CompanyInvoices($ci->db,db_prefix()))->revision($issuer,$actor,$invoice);$queued=[];$delivery=new CompanyInvoiceDelivery($ci->db,db_prefix());foreach($recipients as $contact)$queued[]=$delivery->queuePaymentNotice($issuer,$actor,$invoice,self::id($contact),$revision,$paymentKey,$ci->config->base_url(),fn($value)=>$ci->encryption->decrypt($value));return['success'=>true,'payment_id'=>$ref['source_id'],'queued'=>$queued];
    }

    private static function projects($ci,string $action,array $args,array $input,int $issuer,int $actor,string $method):array
    {
        if($action==='expenses'){
            if(!in_array($method,['GET','HEAD'],true))throw new DomainException('native_parity_method_invalid');$project=self::project($ci,$args[0]??self::value($ci,$input,'project_id'),$issuer,$actor)[0];return['project_id'=>$project,'items'=>self::rows($ci->db,'SELECT id,number,expense_date,description,currency,total_cents,state,revision FROM '.db_prefix().'company_expenses WHERE issuer_id=? AND project_id=? ORDER BY id LIMIT 500',[$issuer,$project])];
        }
        if($action==='add_expense'){
            if($method!=='POST')throw new DomainException('native_parity_method_invalid');$project=self::project($ci,$input['project_id']??null,$issuer,$actor,'edit')[0];$data=self::expenseData($input['expense']??$input);$data['project_id']=$project;$data['category_id']=self::id($data['category_id']??null);$data['client_id']=$data['client_id']??0;$data['currency']=$data['currency']??'BRL';$data['date']=$data['date']??$data['expense_date']??null;$data['description']=$data['description']??null;$data['amount']=$data['amount']??null;$data['note']=$data['note']??'';$data['tax_rate']=$data['tax_rate']??'0.00';$id=(new CompanyExpenses($ci->db,db_prefix()))->create($issuer,$actor,$data,self::key($input));return['success'=>true,'expense_id'=>$id,'project_id'=>$project];
        }
        if($action==='mark_as'){
            if($method!=='POST')throw new DomainException('native_parity_method_invalid');$project=self::project($ci,$input['project_id']??$args[0]??null,$issuer,$actor,'edit')[0];$row=(new CompanyProjects($ci->db,db_prefix()))->get($issuer,$actor,$project);$status=$input['status']??$input['status_id']??null;if(!is_string($status)||!in_array($status,['active','completed','cancelled'],true))throw new DomainException('native_parity_invalid');$data=['client_id'=>(int)$row['client_id'],'responsible_id'=>(int)$row['responsible_id'],'name'=>$row['name'],'description'=>$row['description'],'start_date'=>$row['start_date'],'due_date'=>$row['due_date'],'status'=>$status];(new CompanyProjects($ci->db,db_prefix()))->update($issuer,$actor,$project,self::revision($input),$data,self::key($input));return['success'=>true,'project_id'=>$project,'status'=>$status];
        }
        if($action==='file'){
            if(!in_array($method,['GET','HEAD'],true))throw new DomainException('native_parity_method_invalid');$project=self::project($ci,$args[1]??self::value($ci,$input,'project_id'),$issuer,$actor)[0];$file=(new CompanyProjectExtras($ci->db,db_prefix()))->file($issuer,$actor,'project',$project,(string)($args[0]??self::value($ci,$input,'file_key')));return['__download'=>['mime'=>$file['mime'],'name'=>preg_replace('/[^A-Za-z0-9._-]/','-',(string)$file['filename']),'body'=>$file['content']]];
        }
        if($action==='update_file_data')return self::updateFile($ci,$input,$issuer,$actor,'project');
        if($action==='add_external_file')return self::externalFile($ci,$input,$issuer,$actor,'project');
        if($action==='download_all_files'){
            if(!in_array($method,['GET','HEAD'],true))throw new DomainException('native_parity_method_invalid');$project=self::project($ci,$args[0]??self::value($ci,$input,'project_id'),$issuer,$actor)[0];$extra=new CompanyProjectExtras($ci->db,db_prefix());$meta=$extra->files($issuer,$actor,'project',$project);$entries=[];foreach($meta['items'] as $file){$data=$extra->file($issuer,$actor,'project',$project,$file['file_key']);$entries[preg_replace('/[^A-Za-z0-9._-]/','-',(string)$file['filename'])]=$data['content'];}return['__download'=>['mime'=>'application/zip','name'=>'projeto-'.$project.'-arquivos.zip','body'=>self::zip($entries)]];
        }
        if($action==='export_project_data'){
            if(!in_array($method,['GET','HEAD'],true))throw new DomainException('native_parity_method_invalid');$project=self::project($ci,$args[0]??self::value($ci,$input,'project_id'),$issuer,$actor)[0];$row=(new CompanyProjects($ci->db,db_prefix()))->get($issuer,$actor,$project);$tasks=self::rows($ci->db,'SELECT id,name,status,due_date,assignee_id,revision FROM '.db_prefix().'company_tasks WHERE issuer_id=? AND project_id=? ORDER BY id LIMIT 500',[$issuer,$project]);$milestones=self::rows($ci->db,'SELECT id,name,due_date,done FROM '.db_prefix().'company_project_milestones WHERE issuer_id=? AND project_id=? ORDER BY due_date,id LIMIT 100',[$issuer,$project]);$lines=['Projeto: '.$row['name'],'Status: '.$row['status'],'Período: '.$row['start_date'].' a '.$row['due_date']];foreach($tasks as $task)$lines[]='Tarefa #'.$task['id'].': '.$task['name'].' | '.$task['status'];foreach($milestones as $milestone)$lines[]='Marco #'.$milestone['id'].': '.$milestone['name'].' | '.$milestone['due_date'].' | concluído '.$milestone['done'];$client=(int)$row['client_id'];return['__download'=>['mime'=>'application/pdf','name'=>'projeto-'.$project.'-dados.pdf','body'=>CompanyFinancialDocumentPdf::render('Dados do projeto #'.$project,self::identity($ci->db,db_prefix(),$issuer),self::recipient($ci->db,db_prefix(),$client),$lines)]];
        }
        if($action==='update_milestones_order')return self::milestoneOrder($ci,$input,$issuer,$actor);
        if($action==='pin_action'){
            if($method!=='POST')throw new DomainException('native_parity_method_invalid');$project=self::project($ci,$args[0]??$input['project_id']??null,$issuer,$actor,'edit')[0];$state=(new CompanyProjectExtras($ci->db,db_prefix()))->state($issuer,$actor,'project',$project,'following');$follow=$input['follow']??$input['pin']??null;if($follow===null)$follow=$state['following']?0:1;if(!in_array($follow,[0,1,'0','1'],true))throw new DomainException('native_parity_invalid');(new CompanyProjectExtras($ci->db,db_prefix()))->follow($issuer,$actor,'project',$project,self::revision($input),$follow==='1'||$follow===1,self::key($input));return['success'=>true,'following'=>(bool)($follow==='1'||$follow===1)];
        }
        if($action==='change_milestone_color')return self::milestoneColor($ci,$input,$issuer,$actor);
        if($action==='change_file_visibility')return self::fileVisibility($ci,$input,$issuer,$actor,'project',$args);
        if($action==='delete_milestone')return self::deleteMilestone($ci,$args,$input,$issuer,$actor);
        if($action==='bulk_action_files')return self::bulkFiles($ci,$input,$issuer,$actor,'project');
        if($action==='timesheets'){
            if(!in_array($method,['GET','HEAD'],true))throw new DomainException('native_parity_method_invalid');$project=self::project($ci,$args[0]??self::value($ci,$input,'project_id'),$issuer,$actor)[0];$rules=(new CompanyProjectTimesheets($ci->db,db_prefix()))->get($issuer,$actor,$project);$timers=self::rows($ci->db,'SELECT t.id,t.task_id,t.actor_id,t.started_at,t.stopped_at,t.seconds,k.name task_name FROM '.db_prefix().'company_task_timers t JOIN '.db_prefix().'company_tasks k ON k.id=t.task_id AND k.issuer_id=t.issuer_id WHERE t.issuer_id=? AND k.project_id=? ORDER BY t.id LIMIT 1000',[$issuer,$project]);return['project_id'=>$project,'rules'=>$rules,'timers'=>$timers];
        }
        if($action==='timesheet'){
            if($method!=='POST')throw new DomainException('native_parity_method_invalid');[$task]=self::task($ci,$input['task_id']??$input['taskid']??null,$issuer,$actor,'edit');$row=(new CompanyTasks($ci->db,db_prefix()))->get($issuer,$actor,$task);$op=$input['action']??$input['operation']??null;if(!is_string($op)||!in_array($op,['start','stop','comment'],true))throw new DomainException('native_parity_invalid');$body=is_string($input['body']??'')?$input['body']:'';(new CompanyTasks($ci->db,db_prefix()))->activity($issuer,$actor,$task,self::revision($input),$op,self::key($input),$body);return['success'=>true,'task_id'=>$task];
        }
        if(in_array($action,['save_note','edit_note','delete_note'],true))return self::note($ci,$action,$args,$input,$issuer,$actor);
        if($action==='mass_stop_timers')return self::massStopTimers($ci,$args,$input,$issuer,$actor);
        if($action==='invoice_project'){
            if($method!=='POST')throw new DomainException('native_parity_method_invalid');$project=self::project($ci,$args[0]??$input['project_id']??null,$issuer,$actor,'edit')[0];$billing=$input['billing']??$input;if(!is_array($billing))throw new DomainException('native_parity_invalid');$origin=(new CompanyTaskTimeBilling($ci->db,db_prefix()))->prepare($issuer,$actor,$project,self::revision($input),$billing,self::key($input));$invoice=(new CompanyTaskTimeBilling($ci->db,db_prefix()))->convert($issuer,$actor,$origin);return['success'=>true,'project_id'=>$project,'invoice_id'=>$invoice,'origin_id'=>$origin];
        }
        throw new DomainException('native_parity_invalid');
    }

    private static function updateFile($ci,array $input,int $issuer,int $actor,string $kind):array
    {
        if($ci->input->method(true)!=='POST')throw new DomainException('native_parity_method_invalid');$id=$kind==='project'?self::project($ci,$input['project_id']??null,$issuer,$actor,'edit')[0]:self::task($ci,$input['task_id']??null,$issuer,$actor,'edit')[0];$file=$input['file_key']??$input['id']??null;$name=$input['filename']??$input['file_name']??null;if(!is_string($file)||!preg_match('/^[a-f0-9]{64}$/D',$file)||!is_string($name)||strlen($name)<1||strlen($name)>191||preg_match('/[\x00-\x1f\x7f\\\/]/',$name))throw new DomainException('native_parity_invalid');$p=db_prefix();$table=$kind==='project'?'company_projects':'company_tasks';$field=$kind==='project'?'project_id':'task_id';$revision=self::revision($input);$key=self::key($input);$tables=['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_audit',$table,'company_project_files'];return IssuerTransaction::run($ci->db,$p,array_merge($tables,['company_projects']),function()use($ci,$p,$issuer,$actor,$id,$file,$name,$revision,$key,$kind,$field,$table):array{$parent=self::rows($ci->db,'SELECT * FROM '.$p.$table.' WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$id])[0]??null;if(!$parent||($kind==='task'&&($parent['project_id']??null)===null)|| (int)$parent['revision']!==(int)$revision)throw new DomainException('company_project_stale');$project=$kind==='project'?$id:(int)$parent['project_id'];$where=$kind==='project'?'task_id IS NULL':'task_id=?';$params=[$file,$issuer,$project];if($kind==='task')$params[]=$id;$old=self::rows($ci->db,'SELECT filename,mime FROM '.$p.'company_project_files WHERE file_key=? AND issuer_id=? AND project_id=? AND '.$where.' AND active=1 FOR UPDATE',$params)[0]??null;if(!$old)throw new DomainException('company_project_access_denied');if(pathinfo((string)$old['filename'],PATHINFO_EXTENSION)!==pathinfo($name,PATHINFO_EXTENSION))throw new DomainException('native_parity_invalid');IssuerStorage::query($ci->db,'UPDATE '.$p.'company_project_files SET filename=? WHERE file_key=? AND issuer_id=? AND project_id=? AND '.$where,array_merge([$name,$file,$issuer,$project],$kind==='task'?[$id]:[]));IssuerStorage::query($ci->db,'UPDATE '.$p.$table.' SET revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[gmdate('Y-m-d H:i:s'),$issuer,$id,$revision]);self::audit($ci->db,$p,$issuer,$actor,$id,'native_'.$kind.'_file_renamed',['request_key'=>$key,'file_key'=>$file]);return['success'=>true,'file_key'=>$file,'filename'=>$name];});
    }
    private static function externalFile($ci,array $input,int $issuer,int $actor,string $kind):array
    {
        if($ci->input->method(true)!=='POST')throw new DomainException('native_parity_method_invalid');$id=$kind==='project'?self::project($ci,$input['project_id']??null,$issuer,$actor,'edit')[0]:self::task($ci,$input['task_id']??null,$issuer,$actor,'edit')[0];$url=$input['external']??$input['url']??$input['files']??null;if(!is_string($url)||strlen($url)>2000||preg_match('/[\r\n\x00]/',$url)||!preg_match('#^https?://[^\s]+$#i',$url))throw new DomainException('native_parity_invalid');$revision=self::revision($input);$key=self::key($input);$content=$url."\n";$fileKey=hash('sha256','native-external:'.$issuer.':'.$kind.':'.$id.':'.$key);$p=db_prefix();$table=$kind==='project'?'company_projects':'company_tasks';return IssuerTransaction::run($ci->db,$p,['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_audit',$table,'company_projects','company_project_files'],function()use($ci,$p,$issuer,$actor,$id,$content,$fileKey,$key,$kind,$table,$revision):array{$parent=self::rows($ci->db,'SELECT * FROM '.$p.$table.' WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$id])[0]??null;if(!$parent||($kind==='task'&&($parent['project_id']??null)===null)|| (int)$parent['revision']!==(int)$revision)throw new DomainException('company_project_stale');$project=$kind==='project'?$id:(int)$parent['project_id'];$where=$kind==='project'?'task_id IS NULL':'task_id=?';$params=[$issuer,$project];if($kind==='task')$params[]=$id;$usage=self::rows($ci->db,'SELECT COUNT(*) n,COALESCE(SUM(byte_size),0) bytes FROM '.$p.'company_project_files WHERE issuer_id=? AND project_id=? AND '.$where,$params)[0]??['n'=>0,'bytes'=>0];if((int)$usage['n']>=20||(int)$usage['bytes']+strlen($content)>20971520)throw new DomainException('company_project_limit');$fields=['file_key'=>$fileKey,'issuer_id'=>$issuer,'project_id'=>$project,'task_id'=>$kind==='task'?$id:null,'actor_id'=>$actor,'filename'=>'external-'.substr($fileKey,0,12).'.txt','mime'=>'text/plain','content_hash'=>hash('sha256',$content),'byte_size'=>strlen($content),'content'=>$content,'active'=>1,'created_at'=>gmdate('Y-m-d H:i:s')];$exists=self::rows($ci->db,'SELECT file_key FROM '.$p.'company_project_files WHERE file_key=? FOR UPDATE',[$fileKey]);if(!$exists)IssuerStorage::query($ci->db,'INSERT INTO '.$p.'company_project_files (`'.implode('`,`',array_keys($fields)).'`) VALUES('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));IssuerStorage::query($ci->db,'UPDATE '.$p.$table.' SET revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[gmdate('Y-m-d H:i:s'),$issuer,$id,$revision]);self::audit($ci->db,$p,$issuer,$actor,$id,'native_'.$kind.'_external_file_added',['request_key'=>$key,'file_key'=>$fileKey]);return['success'=>true,'file_key'=>$fileKey,'project_id'=>$project];});
    }
    private static function milestoneOrder($ci,array $input,int $issuer,int $actor):array
    {
        $project=self::project($ci,$input['project_id']??null,$issuer,$actor,'edit')[0];$order=$input['order']??$input['milestones_order']??null;if(!is_array($order)||!array_is_list($order)||count($order)>100)throw new DomainException('native_parity_invalid');$ids=array_map([self::class,'id'],$order);if(count($ids)!==count(array_unique($ids)))throw new DomainException('native_parity_invalid');$revision=self::revision($input);$key=self::key($input);$p=db_prefix();return IssuerTransaction::run($ci->db,$p,['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_audit','company_projects','company_project_milestones','company_milestone_details'],function()use($ci,$p,$issuer,$actor,$project,$ids,$revision,$key):array{$parent=self::rows($ci->db,'SELECT * FROM '.$p.'company_projects WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$project])[0]??null;if(!$parent|| (int)$parent['revision']!==(int)$revision)throw new DomainException('company_project_stale');$known=self::rows($ci->db,'SELECT id,due_date FROM '.$p.'company_project_milestones WHERE issuer_id=? AND project_id=? FOR UPDATE',[$issuer,$project]);$knownIds=array_map('intval',array_column($known,'id'));sort($knownIds);$want=$ids;sort($want);if($knownIds!==$want)throw new DomainException('native_parity_invalid');foreach($ids as $position=>$mid){$old=self::rows($ci->db,'SELECT milestone_id FROM '.$p.'company_milestone_details WHERE issuer_id=? AND project_id=? AND milestone_id=? FOR UPDATE',[$issuer,$project,$mid]);if($old)IssuerStorage::query($ci->db,'UPDATE '.$p.'company_milestone_details SET sort_order=?,revision=revision+1,actor_id=?,updated_at=? WHERE issuer_id=? AND project_id=? AND milestone_id=?',[$position,$actor,gmdate('Y-m-d H:i:s'),$issuer,$project,$mid]);else{foreach($known as $row)if((int)$row['id']===$mid)$due=$row['due_date'];IssuerStorage::query($ci->db,'INSERT INTO '.$p.'company_milestone_details(issuer_id,project_id,milestone_id,start_date,description,description_visible,sort_order,revision,actor_id,updated_at) VALUES(?,?,?,?,?,0,?,1,?,?)',[$issuer,$project,$mid,$due??date('Y-m-d'),'',$position,$actor,gmdate('Y-m-d H:i:s')]);}}IssuerStorage::query($ci->db,'UPDATE '.$p.'company_projects SET revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[gmdate('Y-m-d H:i:s'),$issuer,$project,$revision]);self::audit($ci->db,$p,$issuer,$actor,$project,'native_project_milestones_reordered',['request_key'=>$key,'count'=>count($ids)]);return['success'=>true,'project_id'=>$project,'order'=>$ids];});
    }
    private static function milestoneColor($ci,array $input,int $issuer,int $actor):array
    {
        $project=self::project($ci,$input['project_id']??null,$issuer,$actor,'edit')[0];$milestone=self::id($input['milestone_id']??null);$color=$input['color']??$input['milestone_color']??null;if(!is_string($color)||!preg_match('/^#[0-9a-f]{6}$/i',$color))throw new DomainException('native_parity_invalid');$revision=self::revision($input);$key=self::key($input);$p=db_prefix();return IssuerTransaction::run($ci->db,$p,['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_audit','company_projects','company_project_milestones','company_milestone_visuals'],function()use($ci,$p,$issuer,$actor,$project,$milestone,$color,$revision,$key):array{$parent=self::rows($ci->db,'SELECT revision FROM '.$p.'company_projects WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$project])[0]??null;if(!$parent||(int)$parent['revision']!==(int)$revision||!self::rows($ci->db,'SELECT id FROM '.$p.'company_project_milestones WHERE issuer_id=? AND project_id=? AND id=?',[$issuer,$project,$milestone]))throw new DomainException('company_project_stale');$old=self::rows($ci->db,'SELECT revision FROM '.$p.'company_milestone_visuals WHERE issuer_id=? AND project_id=? AND milestone_id=? FOR UPDATE',[$issuer,$project,$milestone])[0]??null;$next=(int)($old['revision']??0)+1;if($old)IssuerStorage::query($ci->db,'UPDATE '.$p.'company_milestone_visuals SET color=?,revision=?,actor_id=?,updated_at=? WHERE issuer_id=? AND project_id=? AND milestone_id=?',[$color,$next,$actor,gmdate('Y-m-d H:i:s'),$issuer,$project,$milestone]);else IssuerStorage::query($ci->db,'INSERT INTO '.$p.'company_milestone_visuals(issuer_id,project_id,milestone_id,color,revision,actor_id,updated_at) VALUES(?,?,?,?,?,?,?)',[$issuer,$project,$milestone,$color,$next,$actor,gmdate('Y-m-d H:i:s')]);IssuerStorage::query($ci->db,'UPDATE '.$p.'company_projects SET revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[gmdate('Y-m-d H:i:s'),$issuer,$project,$revision]);self::audit($ci->db,$p,$issuer,$actor,$project,'native_project_milestone_color_changed',['request_key'=>$key,'milestone_id'=>$milestone,'color'=>$color]);return['success'=>true,'milestone_id'=>$milestone,'color'=>$color];});
    }
    private static function fileVisibility($ci,array $input,int $issuer,int $actor,string $kind,array $args=[]):array
    {
        $file=$input['file_key']??($args[0]??null);if(!is_string($file)||!preg_match('/^[a-f0-9]{64}$/D',$file))throw new DomainException('native_parity_invalid');$visible=$input['visible']??$input['active']??($args[1]??null);if(!in_array($visible,[0,1,'0','1'],true))throw new DomainException('native_parity_invalid');$projectValue=$input['project_id']??$input['project']??null;if($projectValue===null){$candidate=self::rows($ci->db,'SELECT project_id FROM '.db_prefix().'company_project_files WHERE issuer_id=? AND file_key=?',[$issuer,$file])[0]??null;$projectValue=$candidate['project_id']??null;if($projectValue!==null)$projectValue=NativeCompanyResourceId::encode('company_project',$projectValue);}$id=$kind==='project'?self::project($ci,$projectValue??$input['id']??null,$issuer,$actor,'edit')[0]:self::task($ci,$input['task_id']??$input['id']??null,$issuer,$actor,'edit')[0];$revision=self::revision($input);$key=self::key($input);$active=(int)$visible;
        if($active===0){(new CompanyProjectExtras($ci->db,db_prefix()))->hide($issuer,$actor,$kind,$id,$revision,$file,$key);return['success'=>true,'file_key'=>$file,'active'=>0];}
        $p=db_prefix();$parentTable=$kind==='project'?'company_projects':'company_tasks';$tables=['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_audit',$parentTable,'company_projects','company_project_files'];return IssuerTransaction::run($ci->db,$p,$tables,function()use($ci,$p,$issuer,$actor,$id,$file,$revision,$key,$kind,$parentTable):array{$parent=self::rows($ci->db,'SELECT * FROM '.$p.$parentTable.' WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$id])[0]??null;if(!$parent||($kind==='task'&&($parent['project_id']??null)===null)||(int)$parent['revision']!==(int)$revision)throw new DomainException('company_project_stale');$project=$kind==='project'?$id:(int)$parent['project_id'];$where=$kind==='project'?'task_id IS NULL':'task_id=?';$params=[$file,$issuer,$project];if($kind==='task')$params[]=$id;$row=self::rows($ci->db,'SELECT file_key,active FROM '.$p.'company_project_files WHERE file_key=? AND issuer_id=? AND project_id=? AND '.$where.' FOR UPDATE',$params)[0]??null;if(!$row)throw new DomainException('company_project_access_denied');if((int)$row['active']===1)return['success'=>true,'file_key'=>$file,'active'=>1];IssuerStorage::query($ci->db,'UPDATE '.$p.'company_project_files SET active=1 WHERE file_key=? AND issuer_id=? AND project_id=? AND '.$where,array_merge([$file,$issuer,$project],$kind==='task'?[$id]:[]));IssuerStorage::query($ci->db,'UPDATE '.$p.$parentTable.' SET revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[gmdate('Y-m-d H:i:s'),$issuer,$id,$revision]);self::audit($ci->db,$p,$issuer,$actor,$id,'native_'.$kind.'_file_visibility_changed',['request_key'=>$key,'file_key'=>$file,'active'=>1]);return['success'=>true,'file_key'=>$file,'active'=>1];});
    }
    private static function deleteMilestone($ci,array $args,array $input,int $issuer,int $actor):array
    {
        if($ci->input->method(true)!=='POST')throw new DomainException('native_parity_method_invalid');$project=self::project($ci,$args[0]??$input['project_id']??null,$issuer,$actor,'edit')[0];$milestone=self::id($args[1]??$input['milestone_id']??null);$revision=self::revision($input);$key=self::key($input);$p=db_prefix();return IssuerTransaction::run($ci->db,$p,['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_audit','company_projects','company_project_milestones','company_task_milestones','company_milestone_details','company_portal_milestones','company_milestone_visuals'],function()use($ci,$p,$issuer,$actor,$project,$milestone,$revision,$key):array{$parent=self::rows($ci->db,'SELECT revision FROM '.$p.'company_projects WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$project])[0]??null;if(!$parent||(int)$parent['revision']!==(int)$revision)throw new DomainException('company_project_stale');if(self::rows($ci->db,'SELECT task_id FROM '.$p.'company_task_milestones WHERE issuer_id=? AND project_id=? AND milestone_id=? FOR UPDATE',[$issuer,$project,$milestone]))throw new DomainException('company_project_reference_denied');if(!self::rows($ci->db,'SELECT id FROM '.$p.'company_project_milestones WHERE issuer_id=? AND project_id=? AND id=? FOR UPDATE',[$issuer,$project,$milestone]))throw new DomainException('company_project_access_denied');foreach(['company_milestone_details','company_portal_milestones'] as $table)IssuerStorage::query($ci->db,'DELETE FROM '.$p.$table.' WHERE issuer_id=? AND project_id=? AND milestone_id=?',[$issuer,$project,$milestone]);if(self::table($ci->db,$p,'company_milestone_visuals'))IssuerStorage::query($ci->db,'DELETE FROM '.$p.'company_milestone_visuals WHERE issuer_id=? AND project_id=? AND milestone_id=?',[$issuer,$project,$milestone]);IssuerStorage::query($ci->db,'DELETE FROM '.$p.'company_project_milestones WHERE issuer_id=? AND project_id=? AND id=?',[$issuer,$project,$milestone]);IssuerStorage::query($ci->db,'UPDATE '.$p.'company_projects SET revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[gmdate('Y-m-d H:i:s'),$issuer,$project,$revision]);self::audit($ci->db,$p,$issuer,$actor,$project,'native_project_milestone_deleted',['request_key'=>$key,'milestone_id'=>$milestone]);return['success'=>true,'milestone_id'=>$milestone];});
    }
    private static function bulkFiles($ci,array $input,int $issuer,int $actor,string $kind):array
    {
        if($ci->input->method(true)!=='POST'||!is_array($input['files']??null))throw new DomainException('native_parity_invalid');$done=0;$visible=$input['visible']??$input['active']??null;if(!in_array($visible,[0,1,'0','1'],true))throw new DomainException('native_parity_invalid');foreach($input['files'] as $file){$copy=$input;$copy['file_key']=$file;$copy['visible']=$visible;$copy['request_key']=hash('sha256',self::key($input).':'.$file);self::fileVisibility($ci,$copy,$issuer,$actor,$kind);$done++;}return['success'=>true,'processed'=>$done];
    }
    private static function note($ci,string $action,array $args,array $input,int $issuer,int $actor):array
    {
        $threadCandidate=$action==='save_note'?null:($args[0]??$input['note_id']??$input['id']??null);$projectValue=$action==='save_note'?($args[0]??$input['project_id']??null):($input['project_id']??null);if($projectValue===null&&$threadCandidate!==null){$found=self::rows($ci->db,'SELECT project_id FROM '.db_prefix().'company_project_discussions WHERE issuer_id=? AND id=?',[$issuer,self::id($threadCandidate)])[0]??null;$projectValue=$found['project_id']??null;if($projectValue!==null)$projectValue=NativeCompanyResourceId::encode('company_project',$projectValue);}$project=self::project($ci,$projectValue,$issuer,$actor,'edit')[0];$service=new CompanyProjectDiscussions($ci->db,db_prefix());$page=$service->staffPage($issuer,$actor,$project);$key=self::key($input);if($action==='save_note'){$body=$input['content']??$input['body']??null;if(!is_string($body)||trim($body)==='')throw new DomainException('native_parity_invalid');return$service->staffOpen($issuer,$actor,$project,$page['project']['revision'],$page['collection_revision'],'Nota',$body,$key)+['success'=>true];}$thread=self::id($threadCandidate);$selected=null;foreach($page['items'] as $item)if((int)$item['id']===$thread)$selected=$item;if(!$selected)throw new DomainException('company_project_access_denied');if($action==='edit_note'){$body=$input['content']??$input['body']??null;if(!is_string($body)||trim($body)==='')throw new DomainException('native_parity_invalid');return$service->staffComment($issuer,$actor,$project,$page['project']['revision'],$page['collection_revision'],$thread,$selected['revision'],$body,$key)+['success'=>true];}return$service->threadState($issuer,$actor,$project,$page['project']['revision'],$page['collection_revision'],$thread,$selected['revision'],'active',false,$key)+['success'=>true];
    }
    private static function massStopTimers($ci,array $args,array $input,int $issuer,int $actor):array
    {
        if($ci->input->method(true)!=='POST')throw new DomainException('native_parity_method_invalid');$project=self::project($ci,$args[0]??$input['project_id']??null,$issuer,$actor,'edit')[0];$key=self::key($input);$p=db_prefix();return IssuerTransaction::run($ci->db,$p,['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_audit','company_projects','company_tasks','company_task_timers'],function()use($ci,$p,$issuer,$actor,$project,$key):array{$rows=self::rows($ci->db,'SELECT t.id,t.revision,tm.id timer_id,tm.started_at FROM '.$p.'company_tasks t JOIN '.$p.'company_task_timers tm ON tm.issuer_id=t.issuer_id AND tm.task_id=t.id AND tm.stopped_at IS NULL WHERE t.issuer_id=? AND t.project_id=? FOR UPDATE',[$issuer,$project]);$now=gmdate('Y-m-d H:i:s');foreach($rows as $row){$seconds=max(0,time()-strtotime($row['started_at']));IssuerStorage::query($ci->db,'UPDATE '.$p.'company_task_timers SET stopped_at=?,seconds=? WHERE issuer_id=? AND id=? AND stopped_at IS NULL',[$now,$seconds,$issuer,$row['timer_id']]);IssuerStorage::query($ci->db,'UPDATE '.$p.'company_tasks SET revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[$now,$issuer,$row['id'],$row['revision']]);}self::audit($ci->db,$p,$issuer,$actor,$project,'native_project_timers_stopped',['request_key'=>$key,'count'=>count($rows)]);return['success'=>true,'stopped'=>count($rows)];});
    }

    private static function tasks($ci,string $action,array $args,array $input,int $issuer,int $actor,string $method):array
    {
        if($action==='detailed_overview'){
            if($method!=='POST')throw new DomainException('native_parity_method_invalid');$project=$input['project_id']??null;$where='t.issuer_id=?';$params=[$issuer];if($project!==null&&$project!==''){[$project]=self::project($ci,$project,$issuer,$actor);$where.=' AND t.project_id=?';$params[]=$project;}$rows=self::rows($ci->db,'SELECT t.id,t.project_id,t.name,t.status,t.assignee_id,t.due_date,tm.actor_id,tm.started_at,tm.stopped_at,tm.seconds FROM '.db_prefix().'company_tasks t LEFT JOIN '.db_prefix().'company_task_timers tm ON tm.issuer_id=t.issuer_id AND tm.task_id=t.id WHERE '.$where.' ORDER BY t.project_id,t.id,tm.id LIMIT 2000',$params);return['items'=>$rows,'total'=>count($rows)];
        }
        if($action==='add_external_attachment')return self::externalFile($ci,$input,$issuer,$actor,'task');
        if($action==='download_files'){
            if(!in_array($method,['GET','HEAD'],true))throw new DomainException('native_parity_method_invalid');$task=self::task($ci,$args[0]??self::value($ci,$input,'task_id'),$issuer,$actor)[0];$extra=new CompanyProjectExtras($ci->db,db_prefix());$meta=$extra->files($issuer,$actor,'task',$task);$entries=[];foreach($meta['items'] as $file){$data=$extra->file($issuer,$actor,'task',$task,$file['file_key']);$entries[preg_replace('/[^A-Za-z0-9._-]/','-',(string)$file['filename'])]=$data['content'];}return['__download'=>['mime'=>'application/zip','name'=>'tarefa-'.$task.'-arquivos.zip','body'=>self::zip($entries)]];
        }
        if($action==='delete_task')return self::cancelTask($ci,$args[0]??$input['task_id']??null,$input,$issuer,$actor);
        if($action==='bulk_action'){
            if($method!=='POST'||!is_array($input['ids']??null))throw new DomainException('native_parity_invalid');$done=0;$status=$input['status']??null;foreach($input['ids'] as $raw){$copy=$input;$copy['task_id']=$raw;$copy['request_key']=hash('sha256',self::key($input).':'.$raw);if($input['mass_delete']??false)self::cancelTask($ci,$raw,$copy,$issuer,$actor);elseif($status!==null){$copy['status']=$status;self::taskStatus($ci,$copy,$issuer,$actor);}else throw new DomainException('native_parity_invalid');$done++;}return['success'=>true,'processed'=>$done];
        }
        if($action==='gantt_date_update'){
            if($method!=='POST')throw new DomainException('native_parity_method_invalid');$task=self::task($ci,$input['task_id']??$args[0]??null,$issuer,$actor,'edit')[0];$row=(new CompanyTasks($ci->db,db_prefix()))->get($issuer,$actor,$task);$gantt=new CompanyProjectGantt($ci->db,db_prefix());$gantt->taskDate($issuer,$actor,(int)$row['project_id'],self::revision($input,'project_revision'),$task,self::revision($input,'task_revision'),self::revision($input,'date_revision'),self::date($input['start_date']??null),in_array($input['visible']??1,[1,'1',true],true),self::key($input));return['success'=>true,'task_id'=>$task];
        }
        throw new DomainException('native_parity_invalid');
    }
    private static function taskStatus($ci,array $input,int $issuer,int $actor):array
    {
        $task=self::task($ci,$input['task_id']??null,$issuer,$actor,'edit')[0];$status=$input['status']??null;if(!is_string($status)||!in_array($status,['todo','awaiting_feedback','testing','in_progress','done'],true))throw new DomainException('native_parity_invalid');$row=(new CompanyTasks($ci->db,db_prefix()))->get($issuer,$actor,$task);if($status==='done')CompanyProjectExtras::assertTaskReady($ci->db,db_prefix(),$issuer,$task);$key=self::key($input);$revision=self::revision($input);$p=db_prefix();return IssuerTransaction::run($ci->db,$p,['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_audit','company_tasks','company_projects','company_task_timers','company_task_dependencies'],function()use($ci,$p,$issuer,$actor,$task,$row,$status,$key,$revision):array{if((int)$row['revision']!==(int)$revision)throw new DomainException('company_project_stale');if($status==='done'&&self::rows($ci->db,'SELECT id FROM '.$p.'company_task_timers WHERE issuer_id=? AND task_id=? AND stopped_at IS NULL',[$issuer,$task]))throw new DomainException('company_project_timer_running');IssuerStorage::query($ci->db,'UPDATE '.$p.'company_tasks SET status=?,revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[$status,gmdate('Y-m-d H:i:s'),$issuer,$task,$row['revision']]);self::audit($ci->db,$p,$issuer,$actor,$task,'native_task_status_changed',['request_key'=>$key,'status'=>$status]);return['success'=>true,'task_id'=>$task,'status'=>$status];});
    }
    private static function cancelTask($ci,$value,array $input,int $issuer,int $actor):array
    {
        if($ci->input->method(true)!=='POST')throw new DomainException('native_parity_method_invalid');$task=self::task($ci,$value,$issuer,$actor,'delete')[0];$row=(new CompanyTasks($ci->db,db_prefix()))->get($issuer,$actor,$task);$p=db_prefix();$key=self::key($input);return IssuerTransaction::run($ci->db,$p,['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_audit','company_tasks','company_projects','company_task_timers','company_task_dependencies'],function()use($ci,$p,$issuer,$actor,$task,$row,$key):array{if((int)$row['revision']!==(int)self::revision($input))throw new DomainException('company_project_stale');if(self::rows($ci->db,'SELECT id FROM '.$p.'company_task_timers WHERE issuer_id=? AND task_id=? AND stopped_at IS NULL',[$issuer,$task]))throw new DomainException('company_project_timer_running');if(self::rows($ci->db,'SELECT task_id FROM '.$p.'company_task_dependencies WHERE issuer_id=? AND depends_on=?',[$issuer,$task]))throw new DomainException('company_project_reference_denied');IssuerStorage::query($ci->db,'UPDATE '.$p.'company_tasks SET status=?,revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',['cancelled',gmdate('Y-m-d H:i:s'),$issuer,$task,$row['revision']]);self::audit($ci->db,$p,$issuer,$actor,$task,'native_task_cancelled',['request_key'=>$key]);return['success'=>true,'task_id'=>$task,'status'=>'cancelled'];});
    }
}
