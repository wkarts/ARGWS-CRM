<?php
namespace app\services;
defined('BASEPATH') or exit('No direct script access allowed');

/** Ownership checks for native records that reference a customer. Not a company authorization grant. */
final class CustomerRelationBoundary
{
    private const TABLES=['contracts'=>['id','client',false],'projects'=>['id','clientid',false],'expenses'=>['id','clientid',true],'tickets'=>['ticketid','userid',true]];
    public static function sql($db,string $prefix,string $table,string $alias): string
    {
        if(!isset(self::TABLES[$table]))throw new \InvalidArgumentException('customer_relation_unknown');
        $sql=ClientIssuerScope::legacySql($db,$prefix,$alias,self::TABLES[$table][1]);
        if($table==='tickets'){
            $contact=ClientIssuerScope::legacySql($db,$prefix,'ticket_contact_boundary');
            $sql.=' AND NOT EXISTS (SELECT 1 FROM '.$prefix.'contacts AS ticket_contact_boundary WHERE ticket_contact_boundary.id='.$alias.'.contactid AND NOT ('.$contact.'))';
        }
        if($table==='expenses' && $db->field_exists('invoiceid',$prefix.'expenses') && $db->table_exists($prefix.'invoices')){
            $invoice=InvoiceIssuerScope::legacySql($db,$prefix.'invoices','expense_invoice_boundary');
            $sql.=' AND NOT EXISTS (SELECT 1 FROM '.$prefix.'invoices AS expense_invoice_boundary WHERE expense_invoice_boundary.id='.$alias.'.invoiceid AND NOT ('.$invoice.'))';
        }
        return '('.$sql.')';
    }
    public static function permitsWrite($db,string $prefix,string $table,array $data): bool
    {
        if(!isset(self::TABLES[$table]))return false;
        [$id,$field,$optional]=self::TABLES[$table];
        if(array_key_exists($field,$data)){
            if(!($optional && in_array($data[$field],[null,'',0,'0'],true)) && !ClientIssuerScope::allowsLegacyWrite($db,$prefix,$data,$field,true))return false;
        }
        if($table==='expenses' && !empty($data['invoiceid']) && !InvoiceIssuerScope::isLegacyInvoice($db,$prefix.'invoices',$data['invoiceid']))return false;
        if($table==='tickets' && !empty($data['contactid']) && !ClientIssuerScope::isLegacyContact($db,$prefix,$data['contactid']))return false;
        return true;
    }
    public static function permitsRecord($db,string $prefix,string $table,$id,array $changes=[]): bool
    {
        if(!isset(self::TABLES[$table]) || (!is_int($id)&&!is_string($id)) || !preg_match('/^[1-9][0-9]*$/D',(string)$id))return false;
        $sql=self::sql($db,$prefix,$table,$prefix.$table);
        $result=$db->query('SELECT * FROM '.$prefix.$table.' WHERE '.self::TABLES[$table][0].'=? AND '.$sql,[$id]);
        if($result===false||!($row=$result->row()))return false;
        return !$changes || self::permitsWrite($db,$prefix,$table,array_replace((array)$row,$changes));
    }
}
