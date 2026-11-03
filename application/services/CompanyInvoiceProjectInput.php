<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerPolicy.php';
/** Explicit native project lineage; absence preserves old hashes, null explicitly detaches. */
final class CompanyInvoiceProjectInput
{
    public static function normalize($input):?array
    {
        if($input===null)return null;
        if(!is_array($input)||count($input)!==3||array_diff(array_keys($input),['source_kind','source_id','source_revision'])
            ||($input['source_kind']??null)!=='company_project')throw new DomainException('company_invoice_project_invalid');
        return ['source_kind'=>'company_project','source_id'=>IssuerPolicy::id($input['source_id']??null),
            'source_revision'=>IssuerPolicy::id($input['source_revision']??null)];
    }
}
