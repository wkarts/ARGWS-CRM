<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTicketCatalogSchema.php';
require_once __DIR__.'/NativeCompanyTicketReplySchema.php';
require_once __DIR__.'/NativeCompanyContentHtml.php';
/** Content/state projection after the portal has proved contact, customer and publication. */
final class NativeCompanyTicketPortalProjection
{
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public function tables():array{
        return array_values(array_filter(['company_ticket_catalog','company_ticket_catalog_references','company_ticket_native_metadata','company_ticket_reply_native_metadata','company_ticket_reply_edit_metadata','company_ticket_body_native_metadata','company_ticket_merge_replies','company_ticket_merges','tickets_status'],fn($t)=>$this->db->table_exists($this->p.$t)));
    }
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function proof(string $table,string $column,int $id):?array{
        if(!$this->db->table_exists($this->p.$table))return null;
        $rows=$this->rows('SELECT * FROM '.$this->p.$table.' WHERE '.$column.'=?',[$id]);
        if(count($rows)>1)throw new DomainException('company_portal_operation_unavailable');return $rows[0]??null;
    }
    private static function text(string $html):string{
        $safe=NativeCompanyContentHtml::purify($html);
        $safe=preg_replace('~<(?:br\s*/?|/p|/div|/li|/h[1-6])>~i',"\n",$safe);
        return html_entity_decode(strip_tags($safe),ENT_QUOTES|ENT_HTML5,'UTF-8');
    }
    public function ticket(int $issuer,array $row):array{
        $id=IssuerPolicy::id($row['id']);
        if(isset($row['issuer_id'])&&(int)$row['issuer_id']!==$issuer)throw new DomainException('company_portal_operation_unavailable');
        $native=$row['status']==='native';$metadata=$this->proof('company_ticket_native_metadata','ticket_id',$id);
        if($metadata&&((int)$metadata['issuer_id']!==$issuer||(int)$metadata['revision']<1))throw new DomainException('company_portal_operation_unavailable');
        if($native&&!$metadata)throw new DomainException('company_portal_operation_unavailable');
        $refs=$this->db->table_exists($this->p.'company_ticket_catalog_references')?$this->rows('SELECT * FROM '.$this->p.'company_ticket_catalog_references WHERE ticket_id=? AND kind=?',[$id,'status']):[];
        if(count($refs)>1||($native&&!$refs))throw new DomainException('company_portal_operation_unavailable');
        if($refs){
            $ref=$refs[0];if((int)$ref['issuer_id']!==$issuer||(int)$ref['revision']<1)throw new DomainException('company_portal_operation_unavailable');
            NativeCompanyTicketCatalogSchema::assertValid($this->db,$this->p);
            if($ref['source_kind']==='company_ticket_status'){
                $status=$this->rows('SELECT code,name,revision FROM '.$this->p.'company_ticket_catalog WHERE issuer_id=? AND id=? AND kind=?',[$issuer,$ref['source_id'],'status'])[0]??null;
                if(!$status||(int)$status['revision']<1)throw new DomainException('company_portal_operation_unavailable');
                $code=$status['code'];$row['status_name']=$status['name'];
            }elseif($ref['source_kind']==='ticket_status'&&(new IssuerResourceAccess($this->db,$this->p))->primaryId()===$issuer){
                $status=$this->rows('SELECT ticketstatusid,name FROM '.$this->p.'tickets_status WHERE ticketstatusid=?',[$ref['source_id']])[0]??null;
                if(!$status)throw new DomainException('company_portal_operation_unavailable');
                // The original ticket lifecycle uses status 5 as its terminal state.
                $code=(int)$status['ticketstatusid']===5?'closed':'open';$row['status_name']=$status['name'];
            }else throw new DomainException('company_portal_operation_unavailable');
            $row['status']=in_array($code,['closed','resolved'],true)?$code:'open';
        }elseif(!in_array($row['status'],['open','resolved','closed'],true))throw new DomainException('company_portal_operation_unavailable');
        if(array_key_exists('body',$row)){
            $body=$this->proof(NativeCompanyTicketReplySchema::BODY,'ticket_id',$id);
            if($body&&((int)$body['issuer_id']!==$issuer||$body['body_format']!=='native_html'||(int)$body['revision']<1||!hash_equals($body['content_hash'],hash('sha256',$row['body']))))throw new DomainException('company_portal_operation_unavailable');
            if($native||$body)$row['body']=self::text($row['body']);
        }
        return $row;
    }
    public function reply(int $issuer,int $ticket,array $row):array{
        $id=IssuerPolicy::id($row['id']);$native=$this->proof(NativeCompanyTicketReplySchema::TABLE,'reply_id',$id);$edit=$this->proof(NativeCompanyTicketReplySchema::EDIT,'reply_id',$id);
        $merged=$this->proof('company_ticket_merge_replies','reply_id',$id);$rich=false;
        if($native){
            if((int)$native['issuer_id']!==$issuer||(int)$native['ticket_id']!==$ticket||(int)$native['actor_id']!==(int)$row['actor_id']||(int)$native['actor_id']<1||(int)$native['revision']<1||$native['body_format']!=='native_html'||$merged||($row['contact_author']??null)!==null)throw new DomainException('company_portal_operation_unavailable');
            $rich=true;
        }
        if($merged){
            $link=$this->proof('company_ticket_merges','child_id',(int)$merged['child_id']);
            if(!$link||(int)$link['issuer_id']!==$issuer||(int)$link['primary_id']!==$ticket||(int)$merged['issuer_id']!==$issuer||(int)$merged['primary_id']!==$ticket||(int)($merged['author_staff_id']??0)!==(int)$row['actor_id']||!in_array($merged['body_format'],['plain_text','native_html'],true)||(!$edit&&!hash_equals($merged['content_hash'],hash('sha256',$row['body']))))throw new DomainException('company_portal_operation_unavailable');
            $rich=$merged['body_format']==='native_html';
        }
        if($edit){if((int)$edit['issuer_id']!==$issuer||(int)$edit['ticket_id']!==$ticket||(int)$edit['actor_id']<1||(int)$edit['revision']<1||!hash_equals($edit['content_hash'],hash('sha256',$row['body'])))throw new DomainException('company_portal_operation_unavailable');$rich=true;}
        if($rich)$row['body']=self::text($row['body']);return $row;
    }
}
