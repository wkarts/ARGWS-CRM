<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyHrSchema.php';
require_once __DIR__.'/CompanyApiTokens.php';
require_once __DIR__.'/CompanyHrStorage.php';
require_once __DIR__.'/CompanyHrStructure.php';
require_once __DIR__.'/CompanyHrEmployment.php';
require_once __DIR__.'/CompanyHrRecords.php';
require_once __DIR__.'/CompanyHrContracts.php';
require_once __DIR__.'/CompanyHrTraining.php';
require_once __DIR__.'/CompanyHrFiles.php';
require_once __DIR__.'/CompanyHrAccess.php';
require_once __DIR__.'/CompanyHrNotices.php';
require_once __DIR__.'/CompanyHrReports.php';

/** Global login identity stays in staff; every HR relationship belongs to one issuer. */
final class CompanyHr
{
    use CompanyHrStorage, CompanyHrStructure, CompanyHrEmployment, CompanyHrRecords,
        CompanyHrContracts, CompanyHrTraining, CompanyHrFiles, CompanyHrAccess,
        CompanyHrNotices, CompanyHrReports;
    private const OBJECTS=['unit'=>'company_hr_units','position'=>'company_hr_positions','workplace'=>'company_hr_workplaces','contract_type'=>'company_hr_contract_types','employee'=>'company_hr_employees','record'=>'company_hr_records','contract'=>'company_hr_contracts','course'=>'company_hr_courses','assignment'=>'company_hr_assignments','attempt'=>'company_hr_attempts','access'=>'company_hr_access','file'=>'company_hr_files','notice'=>'company_hr_notices','article'=>'company_hr_articles'];
    private const IMPORT_COLUMNS=['staff_id','employee_code','unit_id','position_id','workplace_id','manager_id','joined_on','state','left_on'];
    private IssuerResourceAccess $acl;
    public function __construct(private $db,private string $p)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');$this->acl=new IssuerResourceAccess($db,$p);
    }
    /** The API facade binds a freshly authenticated bearer, never a token ID. */
    public static function forApi($db,string $p,string $secret,string $scope):array
    {
        $s=new self($db,$p);$who=(new CompanyApiTokens($db,$p))->resolve($secret,$scope);$s->api=$who+['secret'=>$secret];return [$s,$who];
    }
}
