<?php

declare(strict_types=1);
/** In-process proof adapter. Authority still requires an exact final source allowlist in the canonical writer. */
interface CompanyReceiptReversalSource
{
    public function assertSchema($db,string $prefix):void;
    public function tables():array;
    public function claim($db,string $prefix,int $issuer,int $actor):array;
    public function applied($db,string $prefix,int $issuer,int $actor,string $reversalKey):void;
}
