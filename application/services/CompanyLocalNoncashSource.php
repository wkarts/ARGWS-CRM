<?php
declare(strict_types=1);
/** Only the final bank adapter is accepted by the canonical writer; implementing this interface grants no authority. */
interface CompanyLocalNoncashSource
{
    public function originId():int;
    public function input():array;
    public function tables():array;
    public function assertSchema($db,string$prefix):void;
    public function capture($db,string$prefix,int$issuer,int$actor):array;
    public function confirmCurrent($db,string$prefix,int$issuer,int$actor,array$captured,bool$new):void;
}
