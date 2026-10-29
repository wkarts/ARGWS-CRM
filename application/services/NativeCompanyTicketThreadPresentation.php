<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyFormatting.php';require_once __DIR__.'/NativeCompanyTicketRead.php';require_once __DIR__.'/NativeCompanyTicketFiles.php';require_once __DIR__.'/NativeCompanySpamFilters.php';
/** History projection for the original two thread partials. Every name is attached to its proven source. */
final class NativeCompanyTicketThreadPresentation
{
    public function __construct(private$db,private string$p){}
    public function read(int$i,int$a,$id):array{
        $reader=new NativeCompanyTicketRead($this->db,$this->p);$before=$reader->get($i,$a,$id);if($before['source_kind']!=='company_ticket')throw new DomainException('native_ticket_metadata_required');
        $historical=$before['metadata']===null;
        // Earlier owned tickets have a proven tenant and plain-text body, but no
        // native author/department sidecar. Read their history without inventing
        // that provenance or granting the newer mutation capabilities.
        if($historical&&($before['message_format']!=='plain_text'||$before['sender_origin']!=='historical_customer'||!in_array($before['historical_status'],['open','resolved','closed'],true)))throw new DomainException('native_ticket_metadata_required');
        $store=new CompanySettingsStorage($this->db,$this->p);$formats=[];foreach(['dateformat','time_format']as$key)$formats[$key]=$store->exists($i,$key)?$store->get($i,$key):CompanySettingsCatalog::defaultValue('localization',$key);CompanySettingsFormats::validate($formats);$formatter=new NativeCompanyFormatting(CompanySettingsFormats::dates()[$formats['dateformat']],$formats['time_format']);
        $acl=new IssuerResourceAccess($this->db,$this->p);$edit=!$historical&&$acl->can($i,$a,'tickets','edit');$admin=$acl->administrative($i,$a);$policy=$before['policy']['values'];$m=$before['metadata'];
        $historyAuthorship=null;
        if($historical&&$this->db->table_exists($this->p.'company_portal_support_origins')){
            require_once __DIR__.'/CompanySupportAuthorship.php';
            $historyAuthorship=(new CompanySupportAuthorship($this->db,$this->p))->get($i,$a,$before['source_id']);
        }
        $author=null;
        if(!$historical){$author=IssuerStorage::query($this->db,'SELECT firstname,lastname FROM '.$this->p.'staff WHERE staffid=?',[(int)$m['opened_by']])->row_array();if(!$author)throw new DomainException('native_ticket_author_unavailable');}
        $files=(new NativeCompanyTicketFiles($this->db,$this->p))->catalog($i,$a,$id);$attachments=[];$replyFiles=[];foreach($files as$f){$attachment=['id'=>$f['file_key'],'file_name'=>$f['filename'],'filetype'=>$f['mime'],'revision'=>$f['revision'],'url'=>'download/file/ticket/'.$f['file_key'].'?ticket_id='.$before['ticketid'],'delete_url'=>'tickets/delete_attachment/'.$f['file_key'].'?ticket_id='.$before['ticketid']];if($f['reply_id']===null)$attachments[]=$attachment;else$replyFiles[$f['reply_id']][]=$attachment;}
        $ticket=(object)($before+['user_id'=>$before['userid'],'admin'=>$historical?null:(int)$m['opened_by'],'opened_by'=>$historical?'Autor não registrado no histórico':trim($author['firstname'].' '.$author['lastname']),'attachments'=>$attachments,'date_label'=>$formatter->datetime($before['date'])]);
        if($historical){
            $ticket->submitter=$historyAuthorship['origin']['author_name']??'Autor não registrado no histórico';
            $ticket->contactid=$historyAuthorship['origin']['contact_id']??null;
        }
        $replies=$reader->replies($i,$a,$id);foreach($replies as&$r){$r['date_label']=$formatter->datetime($r['date']);$r['reply_email']='';$r['attachments']=$replyFiles[$r['id']]??[]; // Merge-file receipt supplies the explicit association; never guess by filename/date.
        }unset($r);
        $out=['native_support_authorship'=>$historyAuthorship,'ticket'=>$ticket,'ticket_replies'=>$replies,'sender_blocked'=>$historical?false:(new NativeCompanySpamFilters($this->db,$this->p))->blockedSenderForTicket($i,$a,$id),'native_ticket_presentation'=>['actor'=>$a,'historical'=>$historical,'can_reply'=>$edit,'can_mark_read'=>!$historical,'can_edit_message'=>$edit&&($admin||$policy['allow_non_admin_members_to_edit_ticket_messages']==='1'),'can_delete_reply'=>$edit&&($admin||$policy['allow_non_admin_members_to_delete_tickets_and_replies']==='1'),'can_delete_attachment'=>$edit&&($admin||$policy['allow_non_admin_staff_to_delete_ticket_attachments']==='1'),'can_create_task'=>!$historical&&$acl->can($i,$a,'tasks','create')]];
        if($historyAuthorship!==null&&(new CompanySupportAuthorship($this->db,$this->p))->get($i,$a,$before['source_id'])!==$historyAuthorship)throw new DomainException('native_ticket_changed');
        if($reader->get($i,$a,$id)!==$before||!$acl->can($i,$a,'tickets','view'))throw new DomainException('native_ticket_changed');return$out;
    }
    /** Native rich text was purified by the proven reader; historical text remains escaped, never promoted to HTML. */
    public static function message(string$body,string$format):string{
        if($format==='plain_text')return nl2br(htmlspecialchars($body,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'));
        if($format==='native_html')return NativeCompanyContentHtml::purify($body);
        throw new DomainException('native_ticket_body_provenance_invalid');
    }
}
