<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerPolicy.php';
final class NativeCompanyItemOrderEndpoints
{
    public static function orderedIds($pairs):array
    {
        if(!is_array($pairs)||!array_is_list($pairs)||!$pairs||count($pairs)>1000)throw new DomainException('native_invoice_order_invalid');
        $ids=[];foreach($pairs as$offset=>$pair){
            if(!is_array($pair)||array_keys($pair)!==[0,1]||(!is_string($pair[1])&&!is_int($pair[1]))||(string)$pair[1]!==strval($offset+1))throw new DomainException('native_invoice_order_invalid');
            $id=IssuerPolicy::id($pair[0]);if(in_array($id,$ids,true))throw new DomainException('native_invoice_order_invalid');$ids[]=$id;
        }return$ids;
    }
    public static function save($ci,string $type):array
    {
        CompanyRequestScope::assertNativeMutation($ci);if($type!=='invoice')throw new DomainException('native_invoice_order_invalid');
        $ids=self::orderedIds($ci->input->post('data'));$invoice=IssuerPolicy::id($ci->input->post('invoice_id'));$revision=$ci->input->post('revision');
        if(!is_string($revision)||!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new DomainException('native_invoice_order_invalid');
        $c=CompanyRequestScope::context();require_once __DIR__.'/CompanyInvoices.php';
        $next=(new CompanyInvoices($ci->db,db_prefix()))->reorderItems($c->issuerId(),$c->staffId(),$invoice,$revision,$ids);
        if(!is_string($next)||!preg_match('/^[a-f0-9]{64}$/D',$next))throw new RuntimeException('native_invoice_order_receipt_invalid');return['revision'=>$next];
    }
}
