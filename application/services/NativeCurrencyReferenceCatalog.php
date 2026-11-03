<?php

declare(strict_types=1);
/** One native reference catalogue, shared by App and sealed currency maintenance. No data hydration. */
final class NativeCurrencyReferenceCatalog
{
    public static function tables(string $prefix):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('Invalid prefix');
        $out=[];foreach(['invoices'=>'currency','expenses'=>'currency','proposals'=>'currency','estimates'=>'currency','clients'=>'default_currency','creditnotes'=>'currency','subscriptions'=>'currency']as$table=>$field)$out[]=['table'=>$prefix.$table,'field'=>$field];return$out;
    }
}
