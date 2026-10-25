<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';
/** Storage primitive only: caller must authorize its exact source in this owned transaction. */
final class NativeCompanyFileRows
{
    public static function insertInOwned($db,string$p,array$fields):int
    {
        IssuerPolicy::id($fields['staffid']??null);
        return self::insert($db,$p,$fields);
    }
    /** Exact portal writer proves contact identity and source inside its owned create callback. */
    public static function insertContactInOwned($db,string$p,array$fields,object$writer):int
    {
        if(get_class($writer)!=='NativePortalCustomerFiles'||($fields['staffid']??null)!==0||($fields['rel_type']??null)!=='customer')throw new DomainException('native_file_row_invalid');
        IssuerPolicy::id($fields['contact_id']??null);$writer->assertFileAllocationInOwned($db,$p,$fields);
        $id=self::insert($db,$p,$fields);$writer->assertFileAllocationInOwned($db,$p,$fields);return$id;
    }
    private static function insert($db,string$p,array$fields):int
    {
        IssuerTransaction::assertOwned($db,$p,['issuers_lock','files']);
        $keys=['rel_id','rel_type','file_name','filetype','visible_to_customer','external','external_link','thumbnail_link','staffid','contact_id','task_comment_id','dateadded'];if(array_diff(array_keys($fields),$keys)||array_diff($keys,array_keys($fields))||!in_array($fields['rel_type'],['customer','invoice'],true))throw new DomainException('native_file_row_invalid');
        IssuerPolicy::id($fields['rel_id']);$fields['attachment_key']=bin2hex(random_bytes(16));
        IssuerStorage::query($db,'INSERT INTO '.$p.'files (`'.implode('`,`',array_keys($fields)).'`) VALUES('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));$id=(int)$db->insert_id();
        if($id<1||(method_exists($db,'affected_rows')&&(int)$db->affected_rows()!==1))throw new RuntimeException('issuer_file_write_unconfirmed');
        $rows=IssuerStorage::query($db,'SELECT * FROM '.$p.'files WHERE id=? AND attachment_key=?',[$id,$fields['attachment_key']])->result_array();if(count($rows)!==1)throw new RuntimeException('issuer_file_write_unconfirmed');foreach($fields as$k=>$v)if(!array_key_exists($k,$rows[0])||(string)$rows[0][$k]!==(string)$v)throw new RuntimeException('issuer_file_write_unconfirmed');return$id;
    }
}
