<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyCreditTable.php';

final class NativeCompanyCreditTableFactory
{
    public function __construct(private $db,private string $p) {}
    public function make(int $issuer,int $actor,string $identifier='credit_notes',array $savedFilters=[]): NativeCompanyCreditTable
    {
        if ($identifier!=='credit_notes') throw new DomainException('native_credit_table_invalid');
        $data=(new NativeCompanyCreditDirectory($this->db,$this->p))->catalog($issuer,$actor);
        return new NativeCompanyCreditTable($this->db,$this->p,$issuer,$actor,$data['options'],$savedFilters,$data['customDefinitions']);
    }
}
