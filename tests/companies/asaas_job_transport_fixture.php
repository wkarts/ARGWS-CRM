<?php
/** Synthetic canonical GET responses shared by parent and independent worker tests. */
function asaasOriginDescriptionFixture($db,string $prefix,array $row):array
{
    $profile=(new \Asaas\CompanyPreferences($db,$prefix))->originSnapshot($row,['snapshot_json'=>$row['invoice_snapshot_json'],'account_row'=>$row['account_row'],'billing_type'=>$row['billing_type']]);
    return $profile===null?[]:['description'=>$profile['description']];
}

function asaasJobTransportFixture($db,string $prefix):callable
{
    if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$prefix))throw new InvalidArgumentException('synthetic_fixture_database_required');
    return static function($environment,$method,$path,$payload,$secret)use($db,$prefix):array{
        if($db->pdo->inTransaction())throw new LogicException('fixture HTTP under transaction');
        if($method!=='GET'||$payload!==null)throw new LogicException('fixture reconciliation only permits GET');
        $letter=str_contains($secret,'key-A')?'A':'B';
        if($path==='wallets/')return ['data'=>[['id'=>'wallet-'.$letter]],'hasMore'=>false];
        if(str_starts_with($path,'customers/'))return ['id'=>substr($path,10),'cpfCnpj'=>$letter==='A'?'11144477735':'12345678909','deleted'=>false];
        $remote=substr($path,9);
        $row=$db->query('SELECT o.*,m.customer_id,m.billing_type,m.account_row,m.snapshot_json AS invoice_snapshot_json FROM '.$prefix.'asaas_payment_origins o JOIN '.$prefix.'asaas_company_origin_meta m ON m.origin_id=o.id WHERE o.remote_id=? AND o.account_id=? AND o.environment=?',[$remote,'wallet-'.$letter,$environment])->row_array();
        if(!$row)throw new LogicException('synthetic origin required');
        $response=['id'=>$remote,'customer'=>$row['customer_id'],'billingType'=>$row['billing_type'],'externalReference'=>$row['invoice_hash'],'value'=>(string)$row['invoice_total'],'dueDate'=>$row['invoice_due_date'],'status'=>'RECEIVED','netValue'=>'104.53','paymentDate'=>'2026-10-19','creditDate'=>'2026-10-19','deleted'=>false];
        // Current155 origins bind the exact issued description, never today's mutable profile.
        return asaasOriginDescriptionFixture($db,$prefix,$row)+$response;
    };
}
