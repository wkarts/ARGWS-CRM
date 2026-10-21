<?php
/** Include from a disposable native migration fixture AFTER core428 has created all parent tables. */
require_once APPPATH.'services/CompanyBankReversalSchema.php';
function assertBankReversalSchemaContract($db,string $p):void
{
    $connection=$db->pdo??$db->conn_id??null;
    if(!$connection instanceof PDO||$connection->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'||$connection->inTransaction())throw new RuntimeException('native reversal schema test requires idle disposable MySQL');
    if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))throw new RuntimeException('disposable native prefix required');
    CompanyBankReversalSchema::assertValid($db,$p);essentialCheck(true,'native428 reversal schema exact fields/engine/collations/unique keys/foreign keys confirmed');$table=$p.'company_bank_receipt_reversals';
    essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$table)->row()->n===0,'native additive schema does not adopt existing named or NULL-owner payments');
    // DDL commits implicitly: each fault is restored explicitly in finally, never hidden behind rollback.
    $db->query('ALTER TABLE '.$table.' DROP INDEX bank_reversal_external_reference');
    try{essentialThrows(fn()=>CompanyBankReversalSchema::assertValid($db,$p),'issuer_bank_reversal_schema_invalid','missing independent scoped reference uniqueness is rejected');}
    finally{$db->query('ALTER TABLE '.$table.' ADD UNIQUE KEY bank_reversal_external_reference(external_reference_key)');}
    $db->query('ALTER TABLE '.$table.' MODIFY payload_json longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL');
    try{essentialThrows(fn()=>CompanyBankReversalSchema::assertValid($db,$p),'issuer_bank_reversal_schema_invalid','case-folding proof JSON collation is rejected');}
    finally{$db->query('ALTER TABLE '.$table.' MODIFY payload_json longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL');}
    $db->query('ALTER TABLE '.$table.' MODIFY cash_principal_reversed decimal(15,2) NULL');
    try{essentialThrows(fn()=>CompanyBankReversalSchema::assertValid($db,$p),'issuer_bank_reversal_schema_invalid','nullable reversal cash projection is rejected');}
    finally{$db->query('ALTER TABLE '.$table.' MODIFY cash_principal_reversed decimal(15,2) NOT NULL');}
    $fk=$db->query('SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_NAME=?',[$table,'payment_id',$p.'invoicepaymentrecords'])->row_array();$constraint=$fk['CONSTRAINT_NAME']??'';if(!preg_match('/^[A-Za-z0-9_]+$/D',$constraint))throw new RuntimeException('native reversal FK unavailable');$db->query('ALTER TABLE '.$table.' DROP FOREIGN KEY `'.$constraint.'`');
    try{essentialThrows(fn()=>CompanyBankReversalSchema::assertValid($db,$p),'issuer_bank_reversal_schema_invalid','missing native original payment FK is rejected');}
    finally{$db->query('ALTER TABLE '.$table.' ADD CONSTRAINT `'.$constraint.'` FOREIGN KEY(payment_id) REFERENCES '.$p.'invoicepaymentrecords(id)');}
    CompanyBankReversalSchema::assertValid($db,$p);essentialCheck(true,'all native reversal schema faults restored before fixture continues or drops tables');
}
