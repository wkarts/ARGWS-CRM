<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyContractTable.php';
require_once __DIR__.'/NativeCompanyContractIndex.php';

final class NativeCompanyContractTableFactory
{
    public function __construct(private $db,private string $p) {}
    public function make(int $issuer,int $actor,string $identifier='contracts',array $savedFilters=[]):NativeCompanyContractTable
    {
        $data=(new NativeCompanyContractIndex($this->db,$this->p))->presentation($issuer,$actor);
        return new NativeCompanyContractTable($this->db,$this->p,$issuer,$actor,$identifier,$data['options'],$savedFilters);
    }
}
