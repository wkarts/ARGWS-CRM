<?php
declare(strict_types=1);
/** Optional local CNAB instructions on the existing invoice and recurring forms. */
final class NativeCompanyInvoiceBankChoices
{
    public static function listing($db,string $prefix,int $issuer,int $actor):array
    {
        require_once APPPATH.'../modules/banco_brasil/libraries/BancoBrasil/InvoiceIssuance.php';
        try{return (new BancoBrasil\InvoiceIssuance($db,$prefix))->choices($issuer,$actor);}
        catch(DomainException $denied){return [];}
    }
}
