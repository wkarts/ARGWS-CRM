<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyCustomFields.php';
require_once __DIR__.'/CompanyInvoiceProjectInput.php';
require_once __DIR__.'/CompanyProjects.php';

/** Optional presentation data for newly issued credits. Financial line/ledger contracts stay unchanged. */
final class CompanyCreditMetadata
{
    public const TEXT = ['reference_no'=>100, 'adminnote'=>20000, 'terms'=>10000,
        'billing_street'=>200, 'billing_city'=>100, 'billing_state'=>100, 'billing_zip'=>100,
        'shipping_street'=>200, 'shipping_city'=>100, 'shipping_state'=>100, 'shipping_zip'=>100];
    private const INTEGER = ['billing_country'=>[0,2147483647], 'shipping_country'=>[0,2147483647],
        'include_shipping'=>[0,1], 'show_shipping_on_credit_note'=>[0,1], 'show_quantity_as'=>[1,3]];

    private static function text($value, int $max): string
    {
        if (!is_string($value)||strlen($value)>$max||preg_match('//u',$value)!==1
            ||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$value)) throw new DomainException('company_credit_metadata_invalid');
        return $value;
    }
    public static function hash(array $value): string { return hash('sha256',json_encode($value,JSON_THROW_ON_ERROR)); }
    private static function frame($frame, bool $lines, int $count): array
    {
        if (!is_array($frame)||array_diff(array_keys($frame),['definitions_hash','values'])
            ||!is_string($frame['definitions_hash']??null)||!preg_match('/^[a-f0-9]{64}$/D',$frame['definitions_hash'])
            ||!is_array($frame['values']??null)) throw new DomainException('company_credit_metadata_invalid');
        $sets=$lines?$frame['values']:[$frame['values']];
        if ($lines&&(!array_is_list($sets)||count($sets)!==$count)) throw new DomainException('company_credit_metadata_invalid');
        foreach ($sets as &$values) {
            if (!is_array($values)||count($values)>256) throw new DomainException('company_credit_metadata_invalid');
            foreach ($values as $id=>$value) {
                NativeCompanyResourceId::decode('custom_field',$id);
                if (is_array($value)) {
                    if (!array_is_list($value)||count($value)>256) throw new DomainException('company_credit_metadata_invalid');
                    foreach ($value as $part) self::text($part,65535);
                } else self::text($value,65535);
            }
            ksort($values,SORT_STRING);
        }
        unset($values);
        $result=['definitions_hash'=>$frame['definitions_hash'],'values'=>$lines?$sets:$sets[0]];
        if (strlen(json_encode($result,JSON_THROW_ON_ERROR))>262144) throw new DomainException('company_credit_metadata_invalid');
        return $result;
    }
    public static function normalize($input, int $count): array
    {
        $allowed=array_merge(['version','project','items','custom_fields','item_fields'],array_keys(self::TEXT),array_keys(self::INTEGER));
        if (!is_array($input)||array_diff(array_keys($input),$allowed)||!in_array($input['version']??null,[1,'1'],true)||$count<1||$count>100) throw new DomainException('company_credit_metadata_invalid');
        $out=['version'=>1];
        foreach (self::TEXT as $field=>$max) {
            // An omitted terms override keeps the issuer's existing document default.
            if ($field==='terms'&&!array_key_exists($field,$input)) continue;
            $out[$field]=self::text($input[$field]??'',$max);
        }
        foreach (self::INTEGER as $field=>[$min,$max]) {
            $value=$input[$field]??($field==='show_quantity_as'?1:0);
            if ((!is_int($value)&&!is_string($value))||!preg_match('/^(?:0|[1-9][0-9]{0,9})$/D',(string)$value)||(int)$value<$min||(int)$value>$max) throw new DomainException('company_credit_metadata_invalid');
            $out[$field]=(int)$value;
        }
        $out['project']=CompanyInvoiceProjectInput::normalize($input['project']??null);
        $items=$input['items']??array_fill(0,$count,[]);
        if (!is_array($items)||!array_is_list($items)||count($items)!==$count) throw new DomainException('company_credit_metadata_invalid');
        $out['items']=[];
        foreach ($items as $item) {
            if (!is_array($item)||array_diff(array_keys($item),['long_description','unit'])) throw new DomainException('company_credit_metadata_invalid');
            $out['items'][]=['long_description'=>self::text($item['long_description']??'',10000),'unit'=>self::text($item['unit']??'',40)];
        }
        foreach (['custom_fields'=>false,'item_fields'=>true] as $field=>$lines) if (array_key_exists($field,$input)) $out[$field]=self::frame($input[$field],$lines,$count);
        if (strlen(json_encode($out,JSON_THROW_ON_ERROR))>1048576) throw new DomainException('company_credit_metadata_invalid');
        return $out;
    }
    public static function tables(array $input): array
    {
        $tables=['itemable','item_tax','countries','customfields','customfieldsvalues','company_custom_fields','company_custom_field_values','issuers_audit'];
        return array_values(array_unique(array_merge($tables,$input['project']===null?[]:CompanyProjects::ownedReferenceTables())));
    }
    /** Only readers that render new presentation fields need these optional storage participants. */
    public static function readTables($db,string $p): array
    {
        $tables=['itemable','item_tax','issuers_audit'];
        foreach (['customfields','customfieldsvalues','company_custom_fields','company_custom_field_values'] as $table) if ($db->table_exists($p.$table)) $tables[]=$table;
        return $tables;
    }
    private static function capturedFields(array $definitions,array $values): array
    {
        $out=[];
        foreach ($definitions as $definition) {
            $id=(string)$definition['id']; if (!array_key_exists($id,$values)) continue;
            $out[]=['id'=>$id,'field_source_kind'=>$definition['field_source_kind'],'source_field_id'=>(int)$definition['source_field_id'],
                'name'=>$definition['name'],'type'=>$definition['type'],'only_admin'=>(int)$definition['only_admin'],
                'show_on_pdf'=>(int)$definition['show_on_pdf'],'show_on_client_portal'=>(int)$definition['show_on_client_portal'],'value'=>$values[$id]];
        }
        return $out;
    }
    public static function capture($db,string $p,int $issuer,int $actor,int $client,array $input): array
    {
        IssuerTransaction::assertOwned($db,$p,self::tables($input));
        $acl=new IssuerResourceAccess($db,$p);
        if (!$acl->can($issuer,$actor,'credit_notes','view')||!$acl->can($issuer,$actor,'credit_notes','create')) throw new DomainException('company_credit_access_denied');
        $project=null;
        if ($input['project']!==null) {
            $row=(new CompanyProjects($db,$p))->getInOwnedTransaction($issuer,$actor,$input['project']['source_id']);
            if ((int)$row['client_id']!==$client) throw new DomainException('company_credit_project_denied');
            if ((int)$row['revision']!==$input['project']['source_revision']) throw new DomainException('company_credit_project_stale');
            $project=$input['project']+['name'=>self::text($row['name'],191)];
        }
        $countries=[];
        foreach (['billing_country','shipping_country'] as $field) {
            $countries[$field]=''; if ($input[$field]===0) continue;
            $rows=IssuerStorage::query($db,'SELECT short_name FROM '.$p.'countries WHERE country_id=?',[$input[$field]])->result_array();
            if (count($rows)!==1) throw new DomainException('company_credit_country_denied');
            $countries[$field]=self::text($rows[0]['short_name'],150);
        }
        $fields=new NativeCompanyCustomFields($db,$p);
        $headers=$fields->definitions($issuer,$actor,'credit_note',['active'=>1],'create');
        $lines=$fields->creditItemDefinitions($issuer,$actor,'create',['active'=>1]);
        foreach (['custom_fields'=>$headers,'item_fields'=>$lines] as $key=>$definitions) if (isset($input[$key])&&!hash_equals($input[$key]['definitions_hash'],self::hash($definitions))) throw new DomainException('company_credit_fields_stale');
        $headerValues=$fields->validateValues($issuer,$actor,'credit_note',$input['custom_fields']['values']??[],'create');
        $itemValues=[];
        foreach ($input['items'] as $index=>$_) $itemValues[]=$fields->validateCreditItemValues($issuer,$actor,$input['item_fields']['values'][$index]??[]);
        if ($fields->definitions($issuer,$actor,'credit_note',['active'=>1],'create')!==$headers||$fields->creditItemDefinitions($issuer,$actor,'create',['active'=>1])!==$lines) throw new DomainException('company_credit_fields_stale');
        return ['version'=>1,'project'=>$project,'countries'=>$countries,'custom_fields'=>self::capturedFields($headers,$headerValues),
            'item_fields'=>array_map(static fn($values)=>self::capturedFields($lines,$values),$itemValues)];
    }
    public static function columns(array $input): array
    {
        return array_intersect_key($input,array_flip(array_merge(array_keys(self::TEXT),array_keys(self::INTEGER))))+['project_id'=>0];
    }
    public static function persist($db,string $p,int $issuer,int $actor,int $credit,array $lineIds,array $data,\app\services\InvoicePaymentWriter $writer): void
    {
        $writer->assertNativeCreditCreationInOwned($db,$p,$issuer,$actor,$credit);
        $fields=new NativeCompanyCustomFields($db,$p);$input=$data['native_metadata'];
        $fields->saveCreatedCreditInOwnedTransaction($issuer,$actor,$credit,null,$input['custom_fields']['values']??[],$writer);
        foreach ($lineIds as $index=>$line) $fields->saveCreatedCreditInOwnedTransaction($issuer,$actor,$credit,$line,$input['item_fields']['values'][$index]??[],$writer);
        $json=json_encode(['input_hash'=>self::hash(array_diff_key($data,['native_snapshot'=>true])),'snapshot_hash'=>self::hash($data['native_snapshot'])],JSON_THROW_ON_ERROR);
        IssuerStorage::query($db,'INSERT INTO '.$p.'issuers_audit(issuer_id,actor_id,subject_id,event,metadata_json,created_at) VALUES(?,?,?,?,?,?)',[$issuer,$actor,$credit,'company_credit_metadata_captured',$json,gmdate('Y-m-d H:i:s')]);
        $audit=(int)$db->insert_id();
        $row=IssuerStorage::query($db,'SELECT issuer_id,actor_id,subject_id,event,metadata_json FROM '.$p.'issuers_audit WHERE id=?',[$audit])->row_array();
        if ($audit<1||!$row||(int)$row['issuer_id']!==$issuer||(int)$row['actor_id']!==$actor||(int)$row['subject_id']!==$credit||$row['event']!=='company_credit_metadata_captured'||$row['metadata_json']!==$json) throw new RuntimeException('issuer_credit_write_unconfirmed');
        $writer->assertNativeCreditCreationInOwned($db,$p,$issuer,$actor,$credit);
    }
    /** All receipts retain their original hash; derived snapshots have a separate immutable audit binding. */
    public static function validate($db,string $p,int $issuer,array $row,array $data): ?array
    {
        $payload=array_diff_key($data,['native_snapshot'=>true]);
        if (!is_string($row['input_hash']??null)||!hash_equals($row['input_hash'],self::hash($payload))) throw new DomainException('company_credit_balance_review');
        if (!array_key_exists('native_metadata',$data)) {
            if (array_key_exists('native_snapshot',$data)) throw new DomainException('company_credit_balance_review');
            return null;
        }
        try {
            $input=self::normalize($data['native_metadata'],count($data['items']));
            if ($input!==$data['native_metadata']) throw new DomainException();
            $snapshot=$data['native_snapshot']??null;
            if (!is_array($snapshot)||array_keys($snapshot)!==['version','project','countries','custom_fields','item_fields']||$snapshot['version']!==1
                ||!is_array($snapshot['item_fields'])||count($snapshot['item_fields'])!==count($input['items'])) throw new DomainException();
            if ($input['project']===null?$snapshot['project']!==null:(!is_array($snapshot['project'])||array_intersect_key($snapshot['project'],$input['project'])!==$input['project'])) throw new DomainException();
            foreach (self::columns($input) as $field=>$value) if (is_int($value)?(int)($row[$field]??-1)!==$value:(string)($row[$field]??'')!==$value) throw new DomainException();
            $audit=IssuerStorage::query($db,'SELECT actor_id,metadata_json FROM '.$p.'issuers_audit WHERE issuer_id=? AND subject_id=? AND event=?',[$issuer,$row['id'],'company_credit_metadata_captured'])->result_array();
            $expected=json_encode(['input_hash'=>$row['input_hash'],'snapshot_hash'=>self::hash($snapshot)],JSON_THROW_ON_ERROR);
            if (count($audit)!==1||(int)$audit[0]['actor_id']!==(int)$row['snapshot_actor']||$audit[0]['metadata_json']!==$expected) throw new DomainException();
        } catch (Throwable $error) { throw new DomainException('company_credit_balance_review'); }
        return ['input'=>$input,'snapshot'=>$snapshot];
    }
    public static function assertProjections($db,string $p,int $issuer,int $credit,array $items,array $metadata): void
    {
        IssuerTransaction::assertOwned($db,$p,self::readTables($db,$p));
        $rows=IssuerStorage::query($db,'SELECT id,description,long_description,qty,rate,unit FROM '.$p.'itemable WHERE rel_id=? AND rel_type=? ORDER BY item_order,id',[$credit,'credit_note'])->result_array();
        if (count($rows)!==count($items)) throw new DomainException('company_credit_balance_review');
        foreach ($rows as $index=>$row) {
            $line=$metadata['input']['items'][$index];
            if ($row['description']!==$items[$index]['description']||$row['long_description']!==$line['long_description']||$row['unit']!==$line['unit']
                ||CompanyInvoiceAmounts::minor($row['qty'])!==CompanyInvoiceAmounts::minor($items[$index]['qty'])||CompanyInvoiceAmounts::minor($row['rate'])!==CompanyInvoiceAmounts::minor($items[$index]['rate'])) throw new DomainException('company_credit_balance_review');
        }
        $sets=[['credit_note',$credit,$metadata['snapshot']['custom_fields']]];
        foreach ($rows as $index=>$row) $sets[]=['credit_note_item',(int)$row['id'],$metadata['snapshot']['item_fields'][$index]];
        foreach ($sets as [$kind,$id,$values]) {
            $actual=IssuerStorage::query($db,'SELECT field_source_kind,field_id,value FROM '.$p.'company_custom_field_values WHERE issuer_id=? AND source_kind=? AND source_id=? ORDER BY field_source_kind,field_id',[$issuer,$kind,$id])->result_array();
            $expected=[];foreach ($values as $field) $expected[]=['field_source_kind'=>$field['field_source_kind'],'field_id'=>$field['source_field_id'],'value'=>$field['value']];
            usort($expected,static fn($a,$b)=>[$a['field_source_kind'],$a['field_id']]<=>[$b['field_source_kind'],$b['field_id']]);
            foreach ($actual as &$value) $value['field_id']=(int)$value['field_id']; unset($value);
            if ($actual!==$expected) throw new DomainException('company_credit_balance_review');
        }
    }
    /** Confirm the stored receipt and native header, not merely the values passed to INSERT. */
    public static function confirmCreated($db,string $p,int $issuer,int $credit,array $expected):void
    {
        IssuerTransaction::assertOwned($db,$p,array_merge(['creditnotes','company_credit_documents'],self::readTables($db,$p)));
        $row=IssuerStorage::query($db,'SELECT c.*,d.actor_id snapshot_actor,d.input_hash,d.items_json FROM '.$p.'creditnotes c JOIN '.$p.'company_credit_documents d ON d.credit_id=c.id AND d.issuer_id=c.issuer_id WHERE c.id=? AND c.issuer_id=?',[$credit,$issuer])->row_array();
        if(!$row)throw new RuntimeException('issuer_credit_write_unconfirmed');
        $data=json_decode($row['items_json'],true,32,JSON_THROW_ON_ERROR);
        if($data!==$expected)throw new RuntimeException('issuer_credit_write_unconfirmed');
        $metadata=self::validate($db,$p,$issuer,$row,$data);
        if($metadata===null)throw new RuntimeException('issuer_credit_write_unconfirmed');
        self::assertProjections($db,$p,$issuer,$credit,$data['items'],$metadata);
    }
    /** Administrative notes and private custom fields never enter the public DTO or customer PDF. */
    public static function present($db,string $p,int $issuer,int $actor,array $metadata,string $audience): array
    {
        if (!in_array($audience,['admin','pdf'],true)) throw new DomainException('company_credit_metadata_invalid');
        $input=$metadata['input'];$snapshot=$metadata['snapshot'];
        $out=array_diff_key($input,array_flip(['version','project','custom_fields','item_fields']));
        if ($audience==='pdf') unset($out['adminnote']);
        $out['project']=$audience==='admin'&&(new IssuerResourceAccess($db,$p))->can($issuer,$actor,'projects','view')?$snapshot['project']:null;
        $out['countries']=$snapshot['countries'];
        $fields=new NativeCompanyCustomFields($db,$p);
        $definitions=$fields->definitions($issuer,$actor,'credit_note');
        $lineDefinitions=$fields->creditItemDefinitions($issuer,$actor,'view');
        $administrative=(new IssuerResourceAccess($db,$p))->administrative($issuer,$actor);
        $filter=static function(array $stored,array $current) use($audience,$administrative): array {
            $allowed=[];foreach ($current as $definition) $allowed[(string)$definition['id']]=$definition;
            return array_values(array_filter($stored,static function($field) use($allowed,$audience,$administrative) {
                $current=$allowed[$field['id']]??null;
                return $current!==null&&((int)$field['only_admin']===0||$administrative)
                    &&($audience==='admin'||((int)$field['only_admin']===0&&(int)$current['only_admin']===0&&(int)$field['show_on_pdf']===1&&(int)$current['show_on_pdf']===1));
            }));
        };
        $out['custom_fields']=$filter($snapshot['custom_fields'],$definitions);
        $out['item_fields']=array_map(static fn($values)=>$filter($values,$lineDefinitions),$snapshot['item_fields']);
        return $out;
    }
    /** Render a captured link as one fresh safe anchor; every other value remains literal text. */
    public static function fieldHtml(array $field):string
    {
        $escape=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$value=(string)$field['value'];
        if($field['type']!=='link'||$value==='')return nl2br($escape($value));
        $url=$value;$label=$value;
        if(str_starts_with(ltrim($value),'<')){
            $prior=libxml_use_internal_errors(true);
            try{$doc=new DOMDocument();$ok=$doc->loadHTML('<?xml encoding="UTF-8">'.$value,LIBXML_NONET|LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);$anchors=$doc->getElementsByTagName('a');
                if(!$ok||$anchors->length!==1)return$escape($value);$anchor=$anchors->item(0);
                if($anchor->getElementsByTagName('*')->length!==0||trim($doc->textContent)!==trim($anchor->textContent))return$escape($value);
                foreach($doc->childNodes as$node)if($node instanceof DOMElement&&$node!==$anchor)return$escape($value);
                $url=$anchor->getAttribute('href');$label=$anchor->textContent;
            }finally{libxml_clear_errors();libxml_use_internal_errors($prior);}
        }
        if(!preg_match('~^(?:https?://|mailto:)~i',$url)||preg_match('/[\x00-\x20\x7f]/',$url))return$escape($value);
        return '<a href="'.$escape($url).'" target="_blank" rel="noopener noreferrer">'.$escape($label).'</a>';
    }
}
