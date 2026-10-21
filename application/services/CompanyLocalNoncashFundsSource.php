<?php
declare(strict_types=1);
/** The writer accepts only the exact final RestoredLocalNoncashFunds adapter; this contract itself grants no exception. */
interface CompanyLocalNoncashFundsSource
{
    public function assertSchema($db,string$p):void;
    public function tables():array;
    public function operation():string;
    public function key():string;
    public function invoice():int;
    public function coreInput():array;
    public function claim($db,string$p,int$issuer,int$actor):array;
    public function prepared($db,string$p,int$issuer,int$actor,array$native):void;
    public function applied($db,string$p,int$issuer,int$actor):void;
    public function confirmed($db,string$p,int$issuer,int$actor):void;
}
