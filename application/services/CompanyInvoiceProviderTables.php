<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
/** Fresh fixed provider-origin metadata. Framework table_names cache is never authority. */
final class CompanyInvoiceProviderTables
{
    public static function current($db,string $prefix):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('Invalid table prefix');
        $allowed=['bb_origins','asaas_payment_origins','efi_company_origins'];$names=array_map(static fn($s)=>$prefix.$s,$allowed);
        $connection=$db->pdo??$db->conn_id??null;
        $sqlite=$connection instanceof PDO&&$connection->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        $sql=$sqlite?"SELECT name AS table_name FROM sqlite_master WHERE type='table' AND name IN (?,?,?)":"SELECT TABLE_NAME AS table_name FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (?,?,?)";
        $rows=IssuerStorage::query($db,$sql,$names)->result_array();$found=[];
        foreach($rows as$row){$index=array_search($row['table_name']??null,$names,true);if($index===false||isset($found[$index]))throw new RuntimeException('issuer_invoice_provider_metadata_invalid');$found[$index]=$allowed[$index];}
        ksort($found);return array_values($found);
    }
}
