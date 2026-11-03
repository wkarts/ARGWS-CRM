<?php
declare(strict_types=1);
/**
 * Synthetic pre130 rows, decoded by the shipped400 contract.
 * This is test preparation, not an execution of a retired importer. Production
 * ModuleStore must continue to reject imports until its mandatory archive exists.
 */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
function seedHistoricalBb400Return(BbPdoDatabase $db,string $p,array $connection,array $binding,int $actor,string $bytes):array
{
    if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p)
        ||!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:')
        ||$actor<1||$binding['layout_id']!==BancoBrasil\LayoutCatalog::CNAB400
        ||$db->table_exists($p.'bb_return_originals'))throw new RuntimeException('historical_bb_fixture_boundary_invalid');
    $agreement=BancoBrasil\ModuleStore::agreement($connection);
    $parsed=(new BancoBrasil\Cnab400ReturnParser())->parse($agreement,$bytes,BancoBrasil\LayoutCatalog::CNAB400);
    if(count($parsed['events'])!==1)throw new RuntimeException('historical_bb_fixture_one_parent_required');
    return BancoBrasil\Transaction::run($db,$p,['bb_connections','bb_layout_bindings','bb_return_files','bb_return_events','bb_return_file_events','bb_audit'],function()use($db,$p,$connection,$binding,$actor,$agreement,$parsed):array{
        $now=date('Y-m-d H:i:s');
        $provenance=[
            'declared_environment'=>$agreement->scope->environment,
            'environment_evidence'=>$parsed['environment_evidence']??'lot_marker_matches_declared_profile',
            'authenticity'=>'not_verified',
            'issuer_tax_id_evidence'=>$parsed['issuer_tax_id_evidence']??'matches_file',
            'date_window'=>$binding['date_window'],
            'trailer_raw_totals'=>$parsed['trailer_raw_totals']??null,
            'trailer_total_interpretation'=>$parsed['trailer_total_interpretation']??null,
        ];
        $file=['issuer_id'=>$agreement->scope->issuerId,'connection_id'=>$agreement->scope->connectionId,
            'scope_key'=>$agreement->scope->key(),'content_hash'=>$parsed['file_hash'],
            'layout_id'=>$binding['layout_id'],'layout_binding_id'=>(int)$binding['id'],
            'provenance_json'=>json_encode($provenance,JSON_THROW_ON_ERROR),'record_count'=>$parsed['records'],
            'event_count'=>count($parsed['events']),'process_state'=>'review_required','created_by'=>$actor,'created_at'=>$now];
        if(!$db->insert($p.'bb_return_files',$file))throw new RuntimeException('historical_bb_fixture_file_failed');
        $id=(int)$db->insert_id();if($id<1)throw new RuntimeException('historical_bb_fixture_file_failed');
        foreach($parsed['events']as$event){
            $lot=$event['lot'];$sequence=$event['sequence'];unset($event['file_hash'],$event['lot'],$event['sequence']);
            $json=json_encode($event,JSON_THROW_ON_ERROR);
            $record=['return_file_id'=>$id,'layout_binding_id'=>(int)$binding['id'],'issuer_id'=>$agreement->scope->issuerId,
                'connection_id'=>$agreement->scope->connectionId,'scope_key'=>$agreement->scope->key(),
                'event_key'=>$event['event_key'],'payload_hash'=>hash('sha256',$json),'remote_id'=>$event['our_number'],
                'control_reference'=>$event['control'],'occurrence'=>$event['occurrence'],'observation'=>$event['observation'],
                'observation_json'=>$json,'financial_state'=>'not_applied','created_at'=>$now];
            if(!$db->insert($p.'bb_return_events',$record))throw new RuntimeException('historical_bb_fixture_event_failed');
            $eventId=(int)$db->insert_id();
            if($eventId<1||!$db->insert($p.'bb_return_file_events',[
                'return_file_id'=>$id,'return_event_id'=>$eventId,'layout_binding_id'=>(int)$binding['id'],
                'issuer_id'=>$agreement->scope->issuerId,'connection_id'=>$agreement->scope->connectionId,
                'scope_key'=>$agreement->scope->key(),'lot_number'=>$lot,'record_sequence'=>$sequence,'created_at'=>$now,
            ]))throw new RuntimeException('historical_bb_fixture_provenance_failed');
            $stored=$db->where('id',$eventId)->get($p.'bb_return_events')->row_array();
            if(($stored['observation_json']??null)!==$json||($stored['payload_hash']??null)!==hash('sha256',$json)
                ||($stored['financial_state']??null)!=='not_applied')throw new RuntimeException('historical_bb_fixture_event_unconfirmed');
        }
        if(!$db->insert($p.'bb_audit',['issuer_id'=>$agreement->scope->issuerId,'connection_id'=>$agreement->scope->connectionId,
            'actor_id'=>$actor,'action'=>'return_imported_for_review','resource_type'=>'return_file','resource_id'=>$id,'created_at'=>$now]))throw new RuntimeException('historical_bb_fixture_audit_failed');
        $stored=$db->where('id',$id)->get($p.'bb_return_files')->row_array();
        foreach($file as$key=>$value)if((string)($stored[$key]??'')!==(string)$value)throw new RuntimeException('historical_bb_fixture_file_unconfirmed');
        return ['id'=>$id,'duplicate'=>false,'events'=>count($parsed['events']),'financial_effect'=>'not_applied'];
    });
}
