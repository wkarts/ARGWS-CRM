<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyExpenseTable.php';
require_once __DIR__.'/NativeCompanyExpenseIndex.php';
final class NativeCompanyExpenseTableFactory
{
    public function __construct(private $db,private string $p){}
    public function make(int $issuer,int $actor,string $identifier='company_expenses',array $savedFilters=[]):NativeCompanyExpenseTable
    {
        $options=(new NativeCompanyExpenseIndex($this->db,$this->p))->presentation($issuer,$actor)['options'];return new NativeCompanyExpenseTable($this->db,$this->p,$issuer,$actor,$identifier,$options,$savedFilters);
    }
}
