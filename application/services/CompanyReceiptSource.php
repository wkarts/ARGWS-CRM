<?php

declare(strict_types=1);
/** Trusted provider adapter, called only inside the core-owned receipt transaction. No network I/O. */
interface CompanyReceiptSource
{
    public function assertSchema($db,string $prefix):void;
    public function tables():array;
    public function claim($db,string $prefix,int $issuer,int $actor):array;
    public function applied($db,string $prefix,int $issuer,int $actor,int $payment):void;
}
