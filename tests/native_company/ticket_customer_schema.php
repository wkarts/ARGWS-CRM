<?php
/** Metadata/row transport double only; actual MySQL migration is a separate script. */
declare(strict_types=1);
require __DIR__.'/ticket_catalog_schema.php';require_once __DIR__.'/../../application/services/NativeCompanyTicketCustomerSchema.php';
final class TicketCustomerSchemaDb
{
    public array $metadata,$rows,$writes=[];public bool $mutate=false,$skipAlter=false,$db_debug=true,$save_queries=true;
    public function __construct(){
        $method=new ReflectionMethod(TicketCatalogSchemaDb::class,'metadata');$this->metadata=$method->invoke(null,CompanyContractsSchema::definitions('tbl')['company_tickets']);
        $this->metadata['indexes'][]=['INDEX_NAME'=>'client_id','COLUMN_NAME'=>'client_id','NON_UNIQUE'=>1,'SUB_PART'=>null];
        $this->metadata['indexes'][]=['INDEX_NAME'=>'issuer_id','COLUMN_NAME'=>'issuer_id','NON_UNIQUE'=>1,'SUB_PART'=>null];
        $this->rows=[['id'=>1,'issuer_id'=>2,'client_id'=>8,'subject'=>'Synthetic A'],['id'=>2,'issuer_id'=>3,'client_id'=>9,'subject'=>'Synthetic B']];
    }
    public function query($sql,$params=[]){
        if(str_starts_with($sql,'ALTER TABLE')){$this->writes[]=$sql;if(!$this->skipAlter)foreach($this->metadata['columns']as&$column)if($column['COLUMN_NAME']==='client_id')$column['IS_NULLABLE']='YES';if($this->mutate)$this->rows[0]['client_id']=9;return new TicketCatalogSchemaRows([]);}
        if(str_starts_with($sql,'SELECT * FROM `tblcompany_tickets`'))return new TicketCatalogSchemaRows(array_values(array_filter($this->rows,fn($r)=>$r['id']>$params[0])));
        if(str_contains($sql,'AND COLUMN_NAME=?'))return new TicketCatalogSchemaRows(array_values(array_filter($this->metadata['columns'],fn($r)=>$r['COLUMN_NAME']===$params[1])));
        foreach(['COLUMNS'=>'columns','STATISTICS'=>'indexes','KEY_COLUMN_USAGE'=>'foreign','REFERENTIAL_CONSTRAINTS'=>'rules','TABLES'=>'engine']as$n=>$k)if(str_contains($sql,'information_schema.'.$n))return new TicketCatalogSchemaRows($this->metadata[$k]);
        throw new LogicException('unexpected query');
    }
}
$db=new TicketCustomerSchemaDb();$before=$db->rows;$receipt=NativeCompanyTicketCustomerSchema::ensure($db,'tbl');ticketCatalogSchemaCheck($receipt['changed']&&$receipt['rows']===2&&$db->rows===$before&&count($db->writes)===1,'one exact nullable ALTER preserving rows');
$again=NativeCompanyTicketCustomerSchema::ensure($db,'tbl');ticketCatalogSchemaCheck(!$again['changed']&&$again['sha256']===$receipt['sha256']&&count($db->writes)===1,'replay does not ALTER or change original rows');
$withoutImplicitIssuerIndex=new TicketCustomerSchemaDb();$withoutImplicitIssuerIndex->metadata['indexes']=array_values(array_filter($withoutImplicitIssuerIndex->metadata['indexes'],static fn($row)=>$row['INDEX_NAME']!=='issuer_id'));
$accepted=NativeCompanyTicketCustomerSchema::ensure($withoutImplicitIssuerIndex,'tbl');ticketCatalogSchemaCheck($accepted['changed']&&count($withoutImplicitIssuerIndex->writes)===1,'composite ticket_owner index may satisfy issuer foreign key without a redundant secondary index');
foreach([
 static function($d){$d->metadata['columns'][]=$d->metadata['columns'][0]+['unexpected'=>true];$d->metadata['columns'][count($d->metadata['columns'])-1]['COLUMN_NAME']='unexpected';},
 static function($d){$d->metadata['columns'][2]['COLUMN_TYPE']='int unsigned';},
 static function($d){$d->metadata['columns'][2]['COLUMN_DEFAULT']='0';},
 static function($d){$d->metadata['columns'][3]['IS_NULLABLE']='YES';},
 static function($d){$d->metadata['indexes'][count($d->metadata['indexes'])-1]['NON_UNIQUE']=0;},
 static function($d){$d->metadata['indexes'][count($d->metadata['indexes'])-2]['NON_UNIQUE']=0;},
 static function($d){$d->metadata['indexes'][]=['INDEX_NAME'=>'unexpected','COLUMN_NAME'=>'subject','NON_UNIQUE'=>1,'SUB_PART'=>null];},
 static function($d){$d->metadata['foreign'][1]['REFERENCED_TABLE_NAME']='other_clients';},
 static function($d){$d->metadata['rules'][1]['DELETE_RULE']='CASCADE';},
 static function($d){$d->metadata['engine'][0]['ENGINE']='MyISAM';},
 ]as$mutation){$d=new TicketCustomerSchemaDb();$mutation($d);try{NativeCompanyTicketCustomerSchema::ensure($d,'tbl');throw new LogicException('unexpected metadata accepted');}catch(RuntimeException$e){ticketCatalogSchemaCheck(str_starts_with($e->getMessage(),'issuer_native_ticket_customer_schema_invalid')&&!$d->writes,'invalid shape rejected before ALTER');}}
foreach(['mutate','skipAlter']as$mode){$d=new TicketCustomerSchemaDb();$d->$mode=true;try{NativeCompanyTicketCustomerSchema::ensure($d,'tbl');throw new LogicException('unconfirmed ALTER accepted');}catch(RuntimeException$e){ticketCatalogSchemaCheck(in_array($e->getMessage(),['issuer_native_ticket_customer_rows_changed','issuer_native_ticket_customer_schema_invalid'],true),'row/schema postcondition rejects unconfirmed change');}}
ticketCatalogSchemaCheck(in_array('`client_id` int NOT NULL',CompanyContractsSchema::definitions('tbl')['company_tickets'],true),'historical definitions unchanged');
echo "Ticket email nullable migration contract: exact shape, row fingerprint, replay, ten pre-DDL negatives and two postconditions passed with metadata double. Real MySQL remains mandatory.\n";
