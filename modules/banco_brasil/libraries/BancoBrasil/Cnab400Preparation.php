<?php

declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/ModuleRelease.php';
require_once __DIR__.'/ChannelPreferences.php';
require_once __DIR__.'/LayoutCatalog.php';
require_once __DIR__.'/Agreement.php';
require_once __DIR__.'/Fields400.php';
require_once __DIR__.'/Origination.php';
require_once __DIR__.'/Cnab400Generator.php';
require_once __DIR__.'/Cnab400GeneratedSource.php';
require_once APPPATH.'services/CompanyInvoiceBalance.php';
require_once __DIR__.'/Cnab400PreparationSchema.php';
require_once APPPATH.'services/InvoicePaymentWriter.php';
require_once APPPATH.'services/issuers/IssuerTransaction.php';
require_once APPPATH.'services/issuers/IssuerResourceAccess.php';
require_once APPPATH.'services/issuers/InvoiceIssuerDocuments.php';

/** Immutable invoice preparation with optional shared-number reservation; never emits a file or records payment. */
final class Cnab400Preparation
{
    public function __construct(private $db,private string $prefix)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new \InvalidArgumentException('bb_prefix_invalid');
    }
    private function rows(string $sql,array $bind=[]):array{return \IssuerStorage::query($this->db,$sql,$bind)->result_array();}
    private function transaction(callable $work)
    {
        return \IssuerTransaction::run($this->db,$this->prefix,[
            'issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions',
            'issuers_installation_identity','issuers_audit','staff','modules','invoices','issuers_invoice_documents',
            'currencies','company_currency_units','bb_connections','bb_layout_bindings',
            'bb_cnab_preparations','bb_contract_declarations','bb_batch_actions','bb_origins','bb_origin_snapshots','bb_batches','bb_batch_origins','bb_audit',
        ],$work);
    }
    private function authorize(int $issuer,int $actor,string $cap='generate'):\IssuerResourceAccess
    {
        $acl=new \IssuerResourceAccess($this->db,$this->prefix);$acl->lockFinancialIdentity($issuer,$actor);
        $pdo=$this->db->conn_id??$this->db->pdo??null;
        if(!($pdo instanceof \PDO&&$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME)==='sqlite'))SchemaValidator::assertCompatible($this->db,$this->prefix,Cnab400PreparationSchema::requirements());
        $module=$this->rows('SELECT active,installed_version FROM '.$this->prefix.'modules WHERE module_name=? FOR UPDATE',['banco_brasil'])[0]??[];
        if((int)($module['active']??0)!==1||($module['installed_version']??null)!==ModuleRelease::VERSION
            ||!$acl->can($issuer,$actor,'banco_brasil','view')||!$acl->can($issuer,$actor,'banco_brasil',$cap)
            ||!$acl->can($issuer,$actor,'invoices','view'))throw new \DomainException('bb400_preparation_access_denied');
        return $acl;
    }
    private function source(int $issuer,int $actor,int $connection,int $binding,int $invoice,\IssuerResourceAccess $acl):array
    {
        $c=$this->rows('SELECT * FROM '.$this->prefix.'bb_connections WHERE id=? AND issuer_id=? FOR UPDATE',[$connection,$issuer])[0]??null;
        if(!$c)throw new \DomainException('bb400_preparation_connection_unavailable');
        $identity=hash('sha256',json_encode([$issuer,$c['account_id'],$c['environment'],$c['agreement_number'],$c['wallet'],$c['variation']],JSON_THROW_ON_ERROR));
        if(($c['provider']??null)!=='banco_brasil'||!hash_equals($identity,(string)$c['identity_hash'])
            ||!hash_equals(hash('sha256',json_encode([$identity,$c['issuer_tax_id'],$c['issuer_name']],JSON_THROW_ON_ERROR)),(string)$c['configuration_hash']))throw new \DomainException('bb400_preparation_connection_unavailable');
        $agreement=new Agreement(new Scope($issuer,$connection,$c['account_id'],$c['environment'],$c['agreement_number']),$c['issuer_tax_id'],$c['issuer_name'],$c['variation'],$c['wallet']);
        Fields400::account($agreement);
        $profile=$this->rows('SELECT * FROM '.$this->prefix.'bb_layout_bindings WHERE id=? AND issuer_id=? AND connection_id=? FOR UPDATE',[$binding,$issuer,$connection])[0]??null;
        $catalog=LayoutCatalog::get(LayoutCatalog::CNAB400);
        if(!$profile||($profile['layout_id']??null)!==LayoutCatalog::CNAB400||($profile['scope_key']??null)!==$agreement->scope->key()
            ||($profile['connection_hash']??null)!==$c['configuration_hash']||($profile['profile_hash']??null)!==LayoutCatalog::fingerprint(LayoutCatalog::CNAB400))throw new \DomainException('bb400_preparation_profile_denied');
        foreach(['remittance_source_hash'=>'remittance_sha256','return_source_hash'=>'return_sha256','date_window'=>'date_window','declared_currency'=>'currency'] as $column=>$key)
            if(($profile[$column]??null)!==$catalog[$key])throw new \DomainException('bb400_preparation_profile_denied');
        $i=$this->rows('SELECT id,issuer_id,clientid,total,status,date,duedate,currency FROM '.$this->prefix.'invoices WHERE id=? AND issuer_id=? FOR UPDATE',[$invoice,$issuer])[0]??null;
        if(!$i||!in_array((int)$i['status'],[1,4],true))throw new \DomainException('bb400_preparation_invoice_unavailable');
        $currency=$this->rows('SELECT c.name,u.iso_code FROM '.$this->prefix.'currencies c JOIN '.$this->prefix.'company_currency_units u ON u.currency_id=c.id WHERE c.id=? FOR UPDATE',[$i['currency']])[0]??null;
        if(($currency['name']??null)!=='BRL'||($currency['iso_code']??null)!=='BRL')throw new \DomainException('bb400_preparation_currency_denied');
        if(!is_scalar($i['total'])||is_bool($i['total']))throw new \DomainException('bb400_preparation_invoice_unavailable');
        try{$i['total']=Fields::amount(\app\services\InvoicePaymentWriter::minorUnits($i['total']));if(Fields::cents($i['total'])<1)throw new \DomainException('invalid');Fields::date($i['date']);Fields::date($i['duedate']);if($i['duedate']<$i['date'])throw new \DomainException('invalid');}
        catch(\Throwable $error){throw new \DomainException('bb400_preparation_invoice_unavailable',0,$error);}
        $docs=new \InvoiceIssuerDocuments($this->db,$this->prefix,$actor,static fn($id,$staff,$cap)=>$id===$issuer&&$staff===$actor&&$acl->can($issuer,$actor,'invoices','view'));
        $document=$docs->get($invoice,$issuer);
        if(($document['identity']['tax_id']??null)!==$agreement->taxId)throw new \DomainException('bb400_preparation_beneficiary_mismatch');
        return ['issuer_id'=>$issuer,'invoice'=>$i,'document'=>$document,'connection_id'=>$connection,
            'connection_hash'=>$c['configuration_hash'],'layout_binding_id'=>$binding,'profile_hash'=>$profile['profile_hash'],
            'scope_key'=>$agreement->scope->key(),'layout_id'=>LayoutCatalog::CNAB400];
    }
    private static function digest(array $source):string
    {return hash('sha256',json_encode($source,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));}
    private function projection(array $row,?array $current):array
    {
        $source=json_decode($row['source_json']??'',true,64,JSON_THROW_ON_ERROR);
        if(!is_array($source)||!hash_equals((string)($row['source_hash']??''),self::digest($source))
            ||(int)($source['issuer_id']??0)!==(int)$row['issuer_id']||(int)($source['invoice']['id']??0)!==(int)$row['invoice_id']
            ||(int)($source['invoice']['issuer_id']??0)!==(int)$row['issuer_id']||(int)($source['document']['invoice_id']??0)!==(int)$row['invoice_id']
            ||(int)($source['document']['issuer_id']??0)!==(int)$row['issuer_id']||(int)($source['connection_id']??0)!==(int)$row['connection_id']
            ||(int)($source['layout_binding_id']??0)!==(int)$row['layout_binding_id']||($source['layout_id']??null)!==LayoutCatalog::CNAB400
            ||($source['scope_key']??null)!==$row['scope_key']||!preg_match('/^[a-f0-9]{64}$/D',(string)($row['request_key']??''))
            ||!hash_equals((string)($row['request_hash']??''),hash('sha256',json_encode([(int)$row['issuer_id'],(int)$row['connection_id'],(int)$row['layout_binding_id'],(int)$row['invoice_id']],JSON_THROW_ON_ERROR)))
            ||(int)($row['created_by']??0)<1)throw new \DomainException('bb400_preparation_corrupt');
        $origin=$row['origin_id']??null;$batch=$row['batch_id']??null;
        $policy=$this->approval($row);
        if(($origin===null)!==($batch===null)||($origin!==null&&((int)$origin<1||(int)$batch<1)))throw new \DomainException('bb400_preparation_corrupt');
        return ['id'=>(int)$row['id'],'issuer_id'=>(int)$row['issuer_id'],'invoice_id'=>(int)$row['invoice_id'],
            'connection_id'=>(int)$row['connection_id'],'layout_binding_id'=>(int)$row['layout_binding_id'],
            'source_hash'=>$row['source_hash'],'homologation_declared'=>$policy!==null,'stale'=>$current===null||!hash_equals($row['source_hash'],self::digest($current)),
            'state'=>$origin===null?'prepared_not_issued':'reserved_awaiting_layout','origin_id'=>$origin===null?null:(int)$origin,
            'batch_id'=>$batch===null?null:(int)$batch,'bank_number_reserved'=>$origin!==null,'remittance_generated'=>false,'financial_effect'=>'not_applied','available'=>true,'configurable'=>true,'transmission_allowed'=>false,'publication_blocked'=>false];
    }
    private static function reference($value):string
    {
        if(!is_string($value)||trim($value)===''||strlen($value)>191||preg_match('/[\x00-\x1f\x7f]/',$value)||preg_match('//u',$value)!==1)throw new \DomainException('bb400_approval_invalid');
        return trim($value);
    }
    private function approval(array $row):?array
    {
        $fields=['approval_json','approval_hash','approved_by','approved_at'];$present=0;foreach($fields as $field)if(($row[$field]??null)!==null)$present++;
        if($present===0)return null;if($present!==count($fields)||(int)$row['approved_by']<1||!is_string($row['approval_json'])||!is_string($row['approval_hash'])||!hash_equals($row['approval_hash'],hash('sha256',$row['approval_json'])))throw new \DomainException('bb400_approval_corrupt');
        $policy=json_decode($row['approval_json'],true,32,JSON_THROW_ON_ERROR);
        if(!is_array($policy)||(int)($policy['issuer_id']??0)!==(int)$row['issuer_id']||(int)($policy['preparation_id']??0)!==(int)$row['id']
            ||(int)($policy['invoice_id']??0)!==(int)$row['invoice_id']||(int)($policy['connection_id']??0)!==(int)$row['connection_id']
            ||(int)($policy['layout_binding_id']??0)!==(int)$row['layout_binding_id']||(int)($policy['batch_id']??0)!==(int)$row['batch_id']
            ||($policy['scope_key']??null)!==$row['scope_key']||($policy['source_hash']??null)!==$row['source_hash']
            ||($policy['layout_id']??null)!==LayoutCatalog::CNAB400||($policy['verification']??null)!=='operator_declaration_not_bank_verification'
            ||($policy['currency_confirmed']??null)!==true||($policy['interest_exempt']??null)!==true||($policy['homologation_confirmed']??null)!==true
            ||($policy['currency_field']??null)!==LayoutCatalog::CNAB400_BRL_CURRENCY_FIELD)throw new \DomainException('bb400_approval_corrupt');
        foreach(['currency_reference','interest_reference','homologation_reference'] as $field)self::reference($policy[$field]??null);
        $action=$this->rows("SELECT actor_id,reference,created_at FROM ".$this->prefix."bb_batch_actions WHERE batch_id=? AND issuer_id=? AND action='cnab400_homologation_declared'",[(int)$row['batch_id'],(int)$row['issuer_id']]);
        if(count($action)!==1||(int)$action[0]['actor_id']!==(int)$row['approved_by']||$action[0]['reference']!==$policy['homologation_reference']||$action[0]['created_at']!==$row['approved_at'])throw new \DomainException('bb400_approval_corrupt');
        return $policy;
    }
    /** Explicit operator declaration; never represents automatic bank verification. */
    public function declareApproval($issuer,$actor,$preparation,array $input):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$preparation=\IssuerPolicy::id($preparation);
        $keys=['currency_reference','interest_reference','homologation_reference','currency_confirmed','interest_confirmed','homologation_confirmed'];
        if(array_diff(array_keys($input),$keys)||array_diff($keys,array_keys($input))||($input['currency_confirmed']??null)!=='1'||($input['interest_confirmed']??null)!=='1'||($input['homologation_confirmed']??null)!=='1')throw new \DomainException('bb400_approval_required');
        $refs=[];foreach(['currency_reference','interest_reference','homologation_reference'] as $field)$refs[$field]=self::reference($input[$field]??null);
        return $this->transaction(function()use($issuer,$actor,$preparation,$refs):array{
            $acl=$this->authorize($issuer,$actor);if(!$acl->can($issuer,$actor,'banco_brasil','manage'))throw new \DomainException('bb400_preparation_access_denied');
            (new ChannelPreferences($this->db,$this->prefix))->assertEnabledInOwned($issuer,$actor,'cnab');
            $row=$this->rows('SELECT * FROM '.$this->prefix.'bb_cnab_preparations WHERE id=? AND issuer_id=? FOR UPDATE',[$preparation,$issuer])[0]??null;if(!$row)throw new \DomainException('bb400_preparation_unavailable');
            $current=$this->currentOrStale($issuer,$actor,(int)$row['connection_id'],(int)$row['layout_binding_id'],(int)$row['invoice_id'],$acl);$state=$this->recoverProjection($issuer,$row,$current);
            if(!$state['bank_number_reserved'])throw new \DomainException('bb400_reservation_required');if($state['stale']||$current===null)throw new \DomainException('bb400_preparation_stale');
            $policy=['issuer_id'=>$issuer,'preparation_id'=>$preparation,'invoice_id'=>(int)$row['invoice_id'],'connection_id'=>(int)$row['connection_id'],
                'layout_binding_id'=>(int)$row['layout_binding_id'],'batch_id'=>(int)$row['batch_id'],'scope_key'=>$row['scope_key'],'source_hash'=>$row['source_hash'],
                'layout_id'=>LayoutCatalog::CNAB400,'currency_field'=>LayoutCatalog::CNAB400_BRL_CURRENCY_FIELD,'currency_reference'=>$refs['currency_reference'],'interest_reference'=>$refs['interest_reference'],
                'homologation_reference'=>$refs['homologation_reference'],'currency_confirmed'=>true,'interest_exempt'=>true,'homologation_confirmed'=>true,
                'verification'=>'operator_declaration_not_bank_verification'];
            $json=json_encode($policy,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$hash=hash('sha256',$json);$old=$this->approval($row);
            if($old!==null){if(!hash_equals((string)$row['approval_hash'],$hash))throw new \DomainException('bb400_approval_conflict');return $state;}
            $when=gmdate('Y-m-d H:i:s');
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'bb_batch_actions(batch_id,issuer_id,actor_id,action,reference,created_at) VALUES(?,?,?,?,?,?)',[(int)$row['batch_id'],$issuer,$actor,'cnab400_homologation_declared',$refs['homologation_reference'],$when]);
            \IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'bb_cnab_preparations SET approval_json=?,approval_hash=?,approved_by=?,approved_at=? WHERE id=? AND issuer_id=? AND approval_json IS NULL',[$json,$hash,$actor,$when,$preparation,$issuer]);
            $saved=$this->rows('SELECT * FROM '.$this->prefix.'bb_cnab_preparations WHERE id=? AND issuer_id=?',[$preparation,$issuer])[0]??null;
            if(!$saved||($saved['approval_json']??null)!==$json||($saved['approval_hash']??null)!==$hash)throw new \RuntimeException('issuer_bb_write_unconfirmed');
            $this->approval($saved);
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'bb_audit(issuer_id,connection_id,actor_id,action,resource_type,resource_id,created_at) VALUES(?,?,?,?,?,?,?)',[$issuer,(int)$row['connection_id'],$actor,'cnab400_homologation_declared','preparation',$preparation,$when]);
            $audit=(int)$this->db->insert_id();if($audit<1||count($this->rows('SELECT id FROM '.$this->prefix.'bb_audit WHERE id=? AND issuer_id=? AND actor_id=? AND resource_id=?',[$audit,$issuer,$actor,$preparation]))!==1)throw new \RuntimeException('issuer_bb_write_unconfirmed');
            $acl=$this->authorize($issuer,$actor);if(!$acl->can($issuer,$actor,'banco_brasil','manage'))throw new \DomainException('bb400_preparation_access_denied');
            $final=$this->rows('SELECT * FROM '.$this->prefix.'bb_cnab_preparations WHERE id=? AND issuer_id=?',[$preparation,$issuer])[0]??null;
            $source=$this->source($issuer,$actor,(int)$row['connection_id'],(int)$row['layout_binding_id'],(int)$row['invoice_id'],$acl);
            if(!$final||($final['approval_hash']??null)!==$hash||!hash_equals($row['source_hash'],self::digest($source)))throw new \RuntimeException('issuer_bb_write_unconfirmed');
            return $this->recoverProjection($issuer,$final,$source);
        });
    }
    private function currentOrStale(int $issuer,int $actor,int $connection,int $binding,int $invoice,\IssuerResourceAccess $acl):?array
    {
        try{return $this->source($issuer,$actor,$connection,$binding,$invoice,$acl);}
        catch(\DomainException $error){
            if(!in_array($error->getMessage(),['bb400_preparation_invoice_unavailable','bb400_preparation_currency_denied','bb400_preparation_beneficiary_mismatch'],true))throw $error;
            return null;
        }
    }
    public function prepare($issuer,$actor,$connection,$binding,$invoice,string $requestKey):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$connection=\IssuerPolicy::id($connection);
        $binding=\IssuerPolicy::id($binding);$invoice=\IssuerPolicy::id($invoice);
        if(!preg_match('/^[a-f0-9]{64}$/D',$requestKey))throw new \DomainException('bb400_preparation_request_invalid');
        $requestHash=hash('sha256',json_encode([$issuer,$connection,$binding,$invoice],JSON_THROW_ON_ERROR));
        return $this->transaction(function()use($issuer,$actor,$connection,$binding,$invoice,$requestKey,$requestHash):array{
            $acl=$this->authorize($issuer,$actor);(new ChannelPreferences($this->db,$this->prefix))->assertEnabledInOwned($issuer,$actor,'cnab');
            $old=$this->rows('SELECT * FROM '.$this->prefix.'bb_cnab_preparations WHERE issuer_id=? AND request_key=? FOR UPDATE',[$issuer,$requestKey])[0]??null;
            if($old&&!hash_equals((string)$old['request_hash'],$requestHash))throw new \DomainException('bb400_preparation_request_conflict');
            if($old)return $this->recoverProjection($issuer,$old,$this->currentOrStale($issuer,$actor,$connection,$binding,$invoice,$acl));
            $source=$this->source($issuer,$actor,$connection,$binding,$invoice,$acl);
            $json=json_encode($source,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$sourceHash=hash('sha256',$json);
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'bb_cnab_preparations(issuer_id,connection_id,layout_binding_id,invoice_id,scope_key,request_key,request_hash,source_hash,source_json,created_by,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)',[$issuer,$connection,$binding,$invoice,$source['scope_key'],$requestKey,$requestHash,$sourceHash,$json,$actor,gmdate('Y-m-d H:i:s')]);
            $id=(int)$this->db->insert_id();
            $saved=$this->rows('SELECT * FROM '.$this->prefix.'bb_cnab_preparations WHERE id=? AND issuer_id=?',[$id,$issuer])[0]??null;
            if($id<1||!$saved||($saved['source_json']??null)!==$json||($saved['request_hash']??null)!==$requestHash)throw new \RuntimeException('issuer_bb_write_unconfirmed');
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'bb_audit(issuer_id,connection_id,actor_id,action,resource_type,resource_id,created_at) VALUES(?,?,?,?,?,?,?)',[$issuer,$connection,$actor,'cnab400_source_prepared','preparation',$id,gmdate('Y-m-d H:i:s')]);
            $audit=(int)$this->db->insert_id();
            if($audit<1||count($this->rows('SELECT id FROM '.$this->prefix.'bb_audit WHERE id=? AND issuer_id=? AND actor_id=? AND resource_id=?',[$audit,$issuer,$actor,$id]))!==1)throw new \RuntimeException('issuer_bb_write_unconfirmed');
            $acl=$this->authorize($issuer,$actor);
            $final=$this->rows('SELECT * FROM '.$this->prefix.'bb_cnab_preparations WHERE id=? AND issuer_id=?',[$id,$issuer])[0]??null;
            $current=$this->source($issuer,$actor,$connection,$binding,$invoice,$acl);
            if(!$final||($final['source_json']??null)!==$json||!hash_equals($sourceHash,self::digest($current)))throw new \RuntimeException('issuer_bb_write_unconfirmed');
            return $this->recoverProjection($issuer,$final,$current);
        });
    }
    /** Reserve one number and link the immutable invoice. No 400 wire bytes or payment are produced. */
    public function reserve($issuer,$actor,$preparation,string $confirmation):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$preparation=\IssuerPolicy::id($preparation);
        if($confirmation!=='1')throw new \DomainException('bb400_reservation_confirmation_required');
        $tables=['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','issuers_audit','staff','modules','invoices','issuers_invoice_documents','currencies','company_currency_units','invoicepaymentrecords','credits','bb_connections','bb_layout_bindings','bb_cnab_preparations','bb_number_reservations','bb_contract_declarations','bb_origins','bb_batches','bb_origin_snapshots','bb_batch_origins','bb_audit'];
        if($this->db->table_exists($this->prefix.'asaas_payment_origins'))$tables[]='asaas_payment_origins';
        if($this->db->table_exists($this->prefix.'efi_company_origins'))$tables[]='efi_company_origins';
        $tables=array_values(array_unique(array_merge($tables,(new \CompanyInvoiceBalance($this->db,$this->prefix))->tables())));
        return \IssuerTransaction::run($this->db,$this->prefix,$tables,function()use($issuer,$actor,$preparation):array{
            $acl=$this->authorize($issuer,$actor);(new ChannelPreferences($this->db,$this->prefix))->assertEnabledInOwned($issuer,$actor,'cnab');
            $row=$this->rows('SELECT * FROM '.$this->prefix.'bb_cnab_preparations WHERE id=? AND issuer_id=? FOR UPDATE',[$preparation,$issuer])[0]??null;
            if(!$row)throw new \DomainException('bb400_preparation_unavailable');
            $current=$this->currentOrStale($issuer,$actor,(int)$row['connection_id'],(int)$row['layout_binding_id'],(int)$row['invoice_id'],$acl);
            $state=$this->projection($row,$current);
            if($state['bank_number_reserved'])return $this->recoverProjection($issuer,$row,$current);
            if($state['stale']||$current===null)throw new \DomainException('bb400_preparation_stale');
            $invoice=(int)$row['invoice_id'];(new \CompanyInvoiceBalance($this->db,$this->prefix))->inOwned($issuer,$actor,$invoice);
            foreach([['invoicepaymentrecords','invoiceid'],['credits','invoice_id']] as [$table,$column])
                if($this->rows('SELECT amount FROM '.$this->prefix.$table.' WHERE '.$column.'=? FOR UPDATE',[$invoice]))throw new \DomainException('bb_invoice_not_eligible');
            if($this->rows('SELECT id FROM '.$this->prefix.'bb_origins WHERE invoice_id=? FOR UPDATE',[$invoice]))throw new \DomainException('bb_invoice_already_bound');
            foreach(['asaas_payment_origins','efi_company_origins'] as $table)
                if($this->db->table_exists($this->prefix.$table)&&$this->rows('SELECT id FROM '.$this->prefix.$table.' WHERE invoice_id=? FOR UPDATE',[$invoice]))throw new \DomainException('bb_invoice_already_bound');
            $connection=$this->rows('SELECT * FROM '.$this->prefix.'bb_connections WHERE id=? AND issuer_id=? FOR UPDATE',[(int)$row['connection_id'],$issuer])[0]??null;
            if(!$connection)throw new \DomainException('bb400_preparation_connection_unavailable');
            $agreement=new Agreement(new Scope($issuer,(int)$connection['id'],$connection['account_id'],$connection['environment'],$connection['agreement_number']),$connection['issuer_tax_id'],$connection['issuer_name'],$connection['variation'],$connection['wallet']);
            $contract=$this->rows('SELECT * FROM '.$this->prefix.'bb_contract_declarations WHERE issuer_id=? AND connection_id=? AND layout_binding_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE',[$issuer,(int)$row['connection_id'],(int)$row['layout_binding_id']])[0]??null;
            if(!$contract||!hash_equals((string)$contract['declaration_hash'],hash('sha256',(string)$contract['declaration_json'])))throw new \DomainException('bb_contract_declaration_required');
            $declaration=json_decode($contract['declaration_json'],true,32,JSON_THROW_ON_ERROR);
            if(!is_array($declaration)||($declaration['connection_hash']??null)!==$current['connection_hash']||($declaration['profile_hash']??null)!==$current['profile_hash']||($declaration['exclusive_range']??null)!==true||($declaration['interest_exempt']??null)!==true||!is_string($declaration['beneficiary_name']??null))throw new \DomainException('bb_contract_declaration_required');
            if($agreement->scope->environment==='sandbox'&&($declaration['test_exchange']??null)!==true)throw new \DomainException('bb_contract_declaration_required');
            Fields::alpha($declaration['beneficiary_name'],30);
            $counter=$this->rows('SELECT * FROM '.$this->prefix.'bb_number_reservations WHERE bank_namespace=? FOR UPDATE',[Origination::bankNamespace($agreement)])[0]??null;
            if(!$counter||(int)$counter['issuer_id']!==$issuer||$counter['beneficiary_tax_id']!==$agreement->taxId||(int)$counter['next_number']<1||(int)$counter['next_number']>(int)$counter['last_number']||(int)$counter['next_file']<1||(int)$counter['next_file']>999999)throw new \DomainException('bb_number_range_exhausted');
            $number=(int)$counter['next_number'];$file=(int)$counter['next_file'];
            $title=['sequence'=>(string)$number,'control'=>'CRM'.$invoice,'document'=>'CRM'.$invoice,'due_date'=>$current['invoice']['duedate'],'issue_date'=>$current['invoice']['date'],'amount'=>$current['invoice']['total']];
            $snapshot=['invoice'=>$current['invoice'],'invoice_document'=>$current['document']['formatted_number'],'document_snapshot'=>$current['document'],'title'=>$title,'connection'=>$connection,'beneficiary_name'=>$declaration['beneficiary_name'],'layout_binding_id'=>(int)$row['layout_binding_id'],'contract_id'=>(int)$contract['id'],'scope_key'=>$row['scope_key'],'preparation_id'=>$preparation,'preparation_hash'=>$row['source_hash']];
            $json=json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$hash=hash('sha256',$json);
            \IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'bb_number_reservations SET next_number=?,next_file=? WHERE bank_namespace=?',[$number+1,$file+1,Origination::bankNamespace($agreement)]);
            $catalog=LayoutCatalog::get(LayoutCatalog::CNAB400);$batchKey=hash('sha256','bb400-preparation-batch:'.$issuer.':'.$preparation);
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'bb_batches(issuer_id,connection_id,scope_key,idempotency_key,payload_hash,layout_id,layout_source_hash,layout_binding_id,file_sequence,content_hash,content,generation_state,delivery_state,financial_state,created_by,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$issuer,(int)$row['connection_id'],$row['scope_key'],$batchKey,$hash,LayoutCatalog::CNAB400,$catalog['remittance_sha256'],(int)$row['layout_binding_id'],$file,hash('sha256',''),'','awaiting_layout','not_submitted','not_applied',$actor,gmdate('Y-m-d H:i:s')]);
            $batch=(int)$this->db->insert_id();if($batch<1)throw new \RuntimeException('issuer_bb_write_unconfirmed');
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'bb_origins(invoice_id,issuer_id,connection_id,account_id,environment,agreement_number,provider,channel,layout_binding_id,remote_id,control_reference,scope_key,state,active_slot,payload_hash,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$invoice,$issuer,(int)$row['connection_id'],$connection['account_id'],$connection['environment'],$connection['agreement_number'],'banco_brasil','cnab400',(int)$row['layout_binding_id'],$agreement->ourNumber((string)$number),$title['control'],$row['scope_key'],'reserved_awaiting_layout',1,$hash,gmdate('Y-m-d H:i:s')]);
            $origin=(int)$this->db->insert_id();if($origin<1)throw new \RuntimeException('issuer_bb_write_unconfirmed');
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'bb_origin_snapshots(origin_id,layout_binding_id,contract_id,snapshot_json) VALUES(?,?,?,?)',[$origin,(int)$row['layout_binding_id'],(int)$contract['id'],$json]);
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'bb_batch_origins(batch_id,origin_id) VALUES(?,?)',[$batch,$origin]);
            \IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'bb_cnab_preparations SET origin_id=?,batch_id=? WHERE id=? AND issuer_id=? AND origin_id IS NULL AND batch_id IS NULL',[$origin,$batch,$preparation,$issuer]);
            $saved=$this->rows('SELECT * FROM '.$this->prefix.'bb_cnab_preparations WHERE id=? AND issuer_id=?',[$preparation,$issuer])[0]??null;
            $next=$this->rows('SELECT next_number,next_file FROM '.$this->prefix.'bb_number_reservations WHERE bank_namespace=?',[Origination::bankNamespace($agreement)])[0]??[];
            if(!$saved||(int)($saved['origin_id']??0)!==$origin||(int)($saved['batch_id']??0)!==$batch||(int)($next['next_number']??0)!==$number+1||(int)($next['next_file']??0)!==$file+1)throw new \RuntimeException('issuer_bb_write_unconfirmed');
            $this->verifyReservation($issuer,$saved);
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'bb_audit(issuer_id,connection_id,actor_id,action,resource_type,resource_id,created_at) VALUES(?,?,?,?,?,?,?)',[$issuer,(int)$row['connection_id'],$actor,'cnab400_number_reserved','preparation',$preparation,gmdate('Y-m-d H:i:s')]);
            $audit=(int)$this->db->insert_id();if($audit<1||count($this->rows('SELECT id FROM '.$this->prefix.'bb_audit WHERE id=? AND issuer_id=? AND actor_id=? AND resource_id=?',[$audit,$issuer,$actor,$preparation]))!==1)throw new \RuntimeException('issuer_bb_write_unconfirmed');
            $acl=$this->authorize($issuer,$actor);
            $final=$this->rows('SELECT * FROM '.$this->prefix.'bb_cnab_preparations WHERE id=? AND issuer_id=?',[$preparation,$issuer])[0]??null;
            $source=$this->source($issuer,$actor,(int)$row['connection_id'],(int)$row['layout_binding_id'],(int)$row['invoice_id'],$acl);
            if(!$final||!hash_equals($row['source_hash'],self::digest($source)))throw new \RuntimeException('issuer_bb_write_unconfirmed');
            return $this->recoverProjection($issuer,$final,$source);
        });
    }
    /** Generate only after an explicit, immutable operator declaration for this invoice and batch. */
    public function generate($issuer,$actor,$preparation,string $date,array $payer):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$preparation=\IssuerPolicy::id($preparation);
        $keys=['payer_name','payer_address','payer_district','payer_zip','payer_city','payer_state','species','acceptance'];
        if(array_diff(array_keys($payer),$keys)||array_diff($keys,array_keys($payer)))throw new \DomainException('bb400_candidate_invalid_input');
        foreach($payer as $value)if(!is_string($value))throw new \DomainException('bb400_candidate_invalid_input');
        return $this->transaction(function()use($issuer,$actor,$preparation,$date,$payer):array{
            $acl=$this->authorize($issuer,$actor);(new ChannelPreferences($this->db,$this->prefix))->assertEnabledInOwned($issuer,$actor,'cnab');
            $row=$this->rows('SELECT * FROM '.$this->prefix.'bb_cnab_preparations WHERE id=? AND issuer_id=? FOR UPDATE',[$preparation,$issuer])[0]??null;if(!$row)throw new \DomainException('bb400_preparation_unavailable');
            $current=$this->currentOrStale($issuer,$actor,(int)$row['connection_id'],(int)$row['layout_binding_id'],(int)$row['invoice_id'],$acl);
            $state=$this->recoverProjection($issuer,$row,$current);if(!$state['bank_number_reserved'])throw new \DomainException('bb400_reservation_required');
            if($state['stale']||$current===null)throw new \DomainException('bb400_preparation_stale');
            $policy=$this->approval($row);if($policy===null)throw new \DomainException('bb400_homologation_required');
            $stored=$this->rows('SELECT s.snapshot_json,b.file_sequence,b.generation_state,b.content_hash FROM '.$this->prefix.'bb_origin_snapshots s JOIN '.$this->prefix.'bb_batch_origins x ON x.origin_id=s.origin_id JOIN '.$this->prefix.'bb_batches b ON b.id=x.batch_id WHERE s.origin_id=? AND b.id=? AND b.issuer_id=? FOR UPDATE',[(int)$row['origin_id'],(int)$row['batch_id'],$issuer])[0]??null;
            if(!$stored)throw new \DomainException('bb400_reservation_corrupt');$snapshot=json_decode($stored['snapshot_json'],true,64,JSON_THROW_ON_ERROR);$c=$snapshot['connection'];
            $agreement=new Agreement(new Scope($issuer,(int)$c['id'],$c['account_id'],$c['environment'],$c['agreement_number']),$c['issuer_tax_id'],$snapshot['beneficiary_name'],$c['variation'],$c['wallet']);
            $title=$snapshot['title']+$payer+['payer_tax_id'=>$snapshot['document_snapshot']['recipient']['tax_id']];
            try{$candidate=(new Cnab400Generator())->candidate($agreement,(int)$stored['file_sequence'],$date,[$title]);}
            catch(\InvalidArgumentException $error){throw new \DomainException($error->getMessage(),0,$error);}
            $bytes=$candidate['content'];$hash=hash('sha256',$bytes);
            if($stored['generation_state']==='generated'){
                if(!hash_equals((string)$stored['content_hash'],$hash))throw new \DomainException('bb400_generation_conflict');
                return $state;
            }
            \IssuerStorage::query($this->db,"UPDATE {$this->prefix}bb_batches SET content=?,content_hash=?,generation_state='generated' WHERE id=? AND issuer_id=? AND generation_state='awaiting_layout'",[$bytes,$hash,(int)$row['batch_id'],$issuer]);
            \IssuerStorage::query($this->db,"UPDATE {$this->prefix}bb_origins SET state='generated_not_submitted' WHERE id=? AND issuer_id=? AND state='reserved_awaiting_layout'",[(int)$row['origin_id'],$issuer]);
            $this->verifyReservation($issuer,$row);
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'bb_audit(issuer_id,connection_id,actor_id,action,resource_type,resource_id,created_at) VALUES(?,?,?,?,?,?,?)',[$issuer,(int)$row['connection_id'],$actor,'cnab400_remittance_generated','preparation',$preparation,gmdate('Y-m-d H:i:s')]);
            $audit=(int)$this->db->insert_id();if($audit<1||count($this->rows('SELECT id FROM '.$this->prefix.'bb_audit WHERE id=? AND issuer_id=? AND actor_id=? AND resource_id=?',[$audit,$issuer,$actor,$preparation]))!==1)throw new \RuntimeException('issuer_bb_write_unconfirmed');
            $acl=$this->authorize($issuer,$actor);$this->approval($row);
            $final=$this->source($issuer,$actor,(int)$row['connection_id'],(int)$row['layout_binding_id'],(int)$row['invoice_id'],$acl);
            if(!hash_equals($row['source_hash'],self::digest($final)))throw new \RuntimeException('issuer_bb_write_unconfirmed');
            return $this->recoverProjection($issuer,$row,$final);
        });
    }
    /** Private download: records only local disclosure, never bank transmission. */
    public function download($issuer,$actor,$preparation):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$preparation=\IssuerPolicy::id($preparation);
        return $this->transaction(function()use($issuer,$actor,$preparation):array{
            $acl=$this->authorize($issuer,$actor,'download');$row=$this->rows('SELECT * FROM '.$this->prefix.'bb_cnab_preparations WHERE id=? AND issuer_id=? FOR UPDATE',[$preparation,$issuer])[0]??null;if(!$row)throw new \DomainException('bb400_preparation_unavailable');
            $source=$this->currentOrStale($issuer,$actor,(int)$row['connection_id'],(int)$row['layout_binding_id'],(int)$row['invoice_id'],$acl);if($this->projection($row,$source)['stale'])throw new \DomainException('bb400_preparation_stale');
            $policy=$this->approval($row);if($policy===null)throw new \DomainException('bb400_homologation_required');
            $batch=$this->verifyReservation($issuer,$row);if($batch['generation_state']!=='generated')throw new \DomainException('bb_batch_unavailable');
            if($batch['delivery_state']==='not_submitted')\IssuerStorage::query($this->db,"UPDATE {$this->prefix}bb_batches SET delivery_state='downloaded' WHERE id=? AND issuer_id=?",[(int)$row['batch_id'],$issuer]);
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'bb_audit(issuer_id,connection_id,actor_id,action,resource_type,resource_id,created_at) VALUES(?,?,?,?,?,?,?)',[$issuer,(int)$row['connection_id'],$actor,'cnab400_remittance_downloaded','preparation',$preparation,gmdate('Y-m-d H:i:s')]);
            $id=(int)$this->db->insert_id();if($id<1||count($this->rows('SELECT id FROM '.$this->prefix.'bb_audit WHERE id=? AND issuer_id=? AND actor_id=? AND resource_id=?',[$id,$issuer,$actor,$preparation]))!==1)throw new \RuntimeException('issuer_bb_write_unconfirmed');
            $this->authorize($issuer,$actor,'download');$after=$this->verifyReservation($issuer,$row);$this->approval($row);
            if($after['content']!==$batch['content']||$after['content_hash']!==$batch['content_hash']||!in_array($after['delivery_state'],['downloaded','reported_submitted'],true))throw new \RuntimeException('issuer_bb_write_unconfirmed');
            return ['content'=>$after['content'],'hash'=>$after['content_hash'],'filename'=>'bb-cnab400-'.$preparation.'.rem'];
        });
    }
    public function reportTransmission($issuer,$actor,$preparation,string $reference):void
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$preparation=\IssuerPolicy::id($preparation);$reference=self::reference($reference);
        $this->transaction(function()use($issuer,$actor,$preparation,$reference):void{
            $this->authorize($issuer,$actor);$row=$this->rows('SELECT * FROM '.$this->prefix.'bb_cnab_preparations WHERE id=? AND issuer_id=? FOR UPDATE',[$preparation,$issuer])[0]??null;if(!$row)throw new \DomainException('bb400_preparation_unavailable');
            if($this->approval($row)===null)throw new \DomainException('bb400_homologation_required');$batch=$this->verifyReservation($issuer,$row);
            if($batch['generation_state']!=='generated'||!in_array($batch['delivery_state'],['downloaded','reported_submitted'],true))throw new \DomainException('bb_batch_unavailable');
            $old=$this->rows("SELECT reference FROM {$this->prefix}bb_batch_actions WHERE batch_id=? AND issuer_id=? AND action='manual_submission_reported' FOR UPDATE",[(int)$row['batch_id'],$issuer]);
            if($old){if(count($old)!==1||$old[0]['reference']!==$reference)throw new \DomainException('bb_submission_report_conflict');return;}
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'bb_batch_actions(batch_id,issuer_id,actor_id,action,reference,created_at) VALUES(?,?,?,?,?,?)',[(int)$row['batch_id'],$issuer,$actor,'manual_submission_reported',$reference,gmdate('Y-m-d H:i:s')]);
            \IssuerStorage::query($this->db,"UPDATE {$this->prefix}bb_batches SET delivery_state='reported_submitted' WHERE id=? AND issuer_id=?",[(int)$row['batch_id'],$issuer]);
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'bb_audit(issuer_id,connection_id,actor_id,action,resource_type,resource_id,created_at) VALUES(?,?,?,?,?,?,?)',[$issuer,(int)$row['connection_id'],$actor,'cnab400_submission_declared','preparation',$preparation,gmdate('Y-m-d H:i:s')]);
            $audit=(int)$this->db->insert_id();if($audit<1||count($this->rows('SELECT id FROM '.$this->prefix.'bb_audit WHERE id=? AND issuer_id=? AND actor_id=? AND resource_id=?',[$audit,$issuer,$actor,$preparation]))!==1)throw new \RuntimeException('issuer_bb_write_unconfirmed');
            $this->authorize($issuer,$actor);$after=$this->verifyReservation($issuer,$row);$this->approval($row);
            $reported=$this->rows("SELECT reference FROM {$this->prefix}bb_batch_actions WHERE batch_id=? AND issuer_id=? AND action='manual_submission_reported'",[(int)$row['batch_id'],$issuer]);
            if($after['delivery_state']!=='reported_submitted'||count($reported)!==1||$reported[0]['reference']!==$reference)throw new \RuntimeException('issuer_bb_write_unconfirmed');
        });
    }
    /** Structural policy lookup for a caller already owning and authorizing its transaction. */
    public function approvedBatchInOwned(int $issuer,int $batch):array
    {
        \IssuerTransaction::assertOwned($this->db,$this->prefix,['bb_cnab_preparations','bb_batch_actions','bb_contract_declarations','bb_origins','bb_origin_snapshots','bb_batches','bb_batch_origins']);
        $row=$this->rows('SELECT * FROM '.$this->prefix.'bb_cnab_preparations WHERE issuer_id=? AND batch_id=? FOR UPDATE',[$issuer,$batch])[0]??null;
        if(!$row)throw new \DomainException('bb400_homologation_required');$policy=$this->approval($row);
        if($policy===null)throw new \DomainException('bb400_homologation_required');
        $this->verifyReservation($issuer,$row);return $policy;
    }
    /** Validate one complete candidate in memory; never expose or persist its unhomologated bytes. */
    public function candidate($issuer,$actor,$preparation,string $date,array $payer):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$preparation=\IssuerPolicy::id($preparation);
        $keys=['payer_name','payer_address','payer_district','payer_zip','payer_city','payer_state','species','acceptance'];
        if(array_diff(array_keys($payer),$keys)||array_diff($keys,array_keys($payer)))throw new \DomainException('bb400_candidate_invalid_input');
        foreach($payer as $value)if(!is_string($value))throw new \DomainException('bb400_candidate_invalid_input');
        return $this->transaction(function()use($issuer,$actor,$preparation,$date,$payer):array{
            $acl=$this->authorize($issuer,$actor);(new ChannelPreferences($this->db,$this->prefix))->assertEnabledInOwned($issuer,$actor,'cnab');
            $row=$this->rows('SELECT * FROM '.$this->prefix.'bb_cnab_preparations WHERE id=? AND issuer_id=? FOR UPDATE',[$preparation,$issuer])[0]??null;
            if(!$row)throw new \DomainException('bb400_preparation_unavailable');
            $current=$this->currentOrStale($issuer,$actor,(int)$row['connection_id'],(int)$row['layout_binding_id'],(int)$row['invoice_id'],$acl);
            $state=$this->projection($row,$current);
            if(!$state['bank_number_reserved'])throw new \DomainException('bb400_reservation_required');
            if($state['stale']||$current===null)throw new \DomainException('bb400_preparation_stale');
            $this->verifyReservation($issuer,$row);
            $stored=$this->rows('SELECT s.snapshot_json,b.file_sequence FROM '.$this->prefix.'bb_origin_snapshots s JOIN '.$this->prefix.'bb_batch_origins x ON x.origin_id=s.origin_id JOIN '.$this->prefix.'bb_batches b ON b.id=x.batch_id WHERE s.origin_id=? AND b.id=? AND b.issuer_id=?',[(int)$row['origin_id'],(int)$row['batch_id'],$issuer])[0]??null;
            if(!$stored)throw new \DomainException('bb400_reservation_corrupt');
            $snapshot=json_decode($stored['snapshot_json'],true,64,JSON_THROW_ON_ERROR);$c=$snapshot['connection'];
            $agreement=new Agreement(new Scope($issuer,(int)$c['id'],$c['account_id'],$c['environment'],$c['agreement_number']),$c['issuer_tax_id'],$snapshot['beneficiary_name'],$c['variation'],$c['wallet']);
            $title=$snapshot['title']+$payer+['payer_tax_id'=>$snapshot['document_snapshot']['recipient']['tax_id']];
            try{$candidate=(new Cnab400Generator())->candidate($agreement,(int)$stored['file_sequence'],$date,[$title]);}
            catch(\InvalidArgumentException $error){throw new \DomainException($error->getMessage(),0,$error);}
            $this->authorize($issuer,$actor);$this->verifyReservation($issuer,$row);
            return ['preparation_id'=>$preparation,'invoice_id'=>(int)$row['invoice_id'],'origin_id'=>(int)$row['origin_id'],
                'content_sha256'=>hash('sha256',$candidate['content']),'record_count'=>3,'layout'=>LayoutCatalog::CNAB400,
                'available'=>true,'configurable'=>true,'operational'=>false,'transmission_allowed'=>false,'publication_blocked'=>false,'financial_effect'=>'not_applied','unresolved'=>$candidate['unresolved']];
        });
    }
    private function verifyReservation(int $issuer,array $row):array
    {
        $values=$this->rows('SELECT o.id,o.invoice_id,o.issuer_id,o.connection_id,o.layout_binding_id,o.channel,o.scope_key,o.remote_id,o.control_reference,o.payload_hash,o.state,o.account_id,o.environment,o.agreement_number,s.snapshot_json,s.layout_binding_id snapshot_binding_id,s.contract_id,b.id batch_id,b.connection_id batch_connection_id,b.layout_binding_id batch_binding_id,b.scope_key batch_scope_key,b.layout_id,b.layout_source_hash,b.file_sequence,b.payload_hash batch_payload_hash,b.generation_state,b.delivery_state,b.financial_state,b.content,b.content_hash FROM '.$this->prefix.'bb_origins o JOIN '.$this->prefix.'bb_origin_snapshots s ON s.origin_id=o.id JOIN '.$this->prefix.'bb_batch_origins x ON x.origin_id=o.id JOIN '.$this->prefix.'bb_batches b ON b.id=x.batch_id WHERE o.id=? AND o.issuer_id=? AND b.id=? AND b.issuer_id=? FOR UPDATE',[(int)$row['origin_id'],$issuer,(int)$row['batch_id'],$issuer]);
        if(count($values)!==1)throw new \DomainException('bb400_reservation_corrupt');$v=$values[0];
        if((int)$v['invoice_id']!==(int)$row['invoice_id']||(int)$v['connection_id']!==(int)$row['connection_id']||(int)$v['layout_binding_id']!==(int)$row['layout_binding_id']||$v['channel']!=='cnab400'||$v['scope_key']!==$row['scope_key']||$v['layout_id']!==LayoutCatalog::CNAB400||$v['financial_state']!=='not_applied'||(int)$v['batch_connection_id']!==(int)$row['connection_id']||(int)$v['batch_binding_id']!==(int)$row['layout_binding_id']||$v['batch_scope_key']!==$row['scope_key']||$v['layout_source_hash']!==LayoutCatalog::get(LayoutCatalog::CNAB400)['remittance_sha256']||$v['batch_payload_hash']!==$v['payload_hash']||!hash_equals((string)$v['payload_hash'],hash('sha256',(string)$v['snapshot_json'])))throw new \DomainException('bb400_reservation_corrupt');
        $snapshot=json_decode($v['snapshot_json'],true,64,JSON_THROW_ON_ERROR);
        if(!is_array($snapshot)||(int)($snapshot['preparation_id']??0)!==(int)$row['id']||($snapshot['preparation_hash']??null)!==$row['source_hash']||($snapshot['title']['control']??null)!==$v['control_reference']||($snapshot['scope_key']??null)!==$row['scope_key']||(int)($snapshot['invoice']['id']??0)!==(int)$row['invoice_id']||(int)($snapshot['invoice']['issuer_id']??0)!==$issuer||(int)($snapshot['connection']['id']??0)!==(int)$row['connection_id']||($snapshot['connection']['account_id']??null)!==$v['account_id']||($snapshot['connection']['environment']??null)!==$v['environment']||($snapshot['connection']['agreement_number']??null)!==$v['agreement_number'])throw new \DomainException('bb400_reservation_corrupt');
        $prepared=json_decode((string)$row['source_json'],true,64,JSON_THROW_ON_ERROR);
        if(!is_array($prepared)||($snapshot['invoice']??null)!==($prepared['invoice']??null)||($snapshot['document_snapshot']??null)!==($prepared['document']??null)
            ||($snapshot['invoice_document']??null)!==($prepared['document']['formatted_number']??null)||(int)$v['snapshot_binding_id']!==(int)$row['layout_binding_id']
            ||($snapshot['title']['document']??null)!=='CRM'.(int)$row['invoice_id']||($snapshot['title']['control']??null)!=='CRM'.(int)$row['invoice_id']
            ||($snapshot['title']['due_date']??null)!==($prepared['invoice']['duedate']??null)||($snapshot['title']['issue_date']??null)!==($prepared['invoice']['date']??null)
            ||($snapshot['title']['amount']??null)!==($prepared['invoice']['total']??null))throw new \DomainException('bb400_reservation_corrupt');
        $contract=$this->rows('SELECT * FROM '.$this->prefix.'bb_contract_declarations WHERE id=? AND issuer_id=? AND connection_id=? AND layout_binding_id=?',[(int)$v['contract_id'],$issuer,(int)$row['connection_id'],(int)$row['layout_binding_id']])[0]??null;
        if(!$contract||!hash_equals((string)($contract['declaration_hash']??''),hash('sha256',(string)($contract['declaration_json']??''))))throw new \DomainException('bb400_reservation_corrupt');
        $decl=json_decode($contract['declaration_json'],true,32,JSON_THROW_ON_ERROR);
        if(!is_array($decl)||($decl['connection_hash']??null)!==($prepared['connection_hash']??null)||($decl['profile_hash']??null)!==($prepared['profile_hash']??null)||($decl['beneficiary_name']??null)!==($snapshot['beneficiary_name']??null)||($decl['exclusive_range']??null)!==true||($decl['interest_exempt']??null)!==true)throw new \DomainException('bb400_reservation_corrupt');
        $c=$snapshot['connection'];$a=new Agreement(new Scope($issuer,(int)$c['id'],$c['account_id'],$c['environment'],$c['agreement_number']),$c['issuer_tax_id'],$snapshot['beneficiary_name']??$c['issuer_name'],$c['variation'],$c['wallet']);
        if($a->scope->key()!==$row['scope_key']||$v['remote_id']!==$a->ourNumber((string)($snapshot['title']['sequence']??'')))throw new \DomainException('bb400_reservation_corrupt');
        if($v['generation_state']==='awaiting_layout'){
            if($v['state']!=='reserved_awaiting_layout'||$v['delivery_state']!=='not_submitted'||$v['content']!==''||!hash_equals((string)$v['content_hash'],hash('sha256','')))throw new \DomainException('bb400_reservation_corrupt');
        }elseif($v['generation_state']==='generated'){
            if($v['state']!=='generated_not_submitted'||!in_array($v['delivery_state'],['not_submitted','downloaded','reported_submitted'],true))throw new \DomainException('bb400_reservation_corrupt');
            Cnab400GeneratedSource::validate($v,$snapshot,$v);$policy=$this->approval($row);
            if($policy===null||substr(explode("\r\n",$v['content'])[1],84,2)!==LayoutCatalog::CNAB400_BRL_CURRENCY_FIELD)throw new \DomainException('bb400_reservation_corrupt');
        }else throw new \DomainException('bb400_reservation_corrupt');
        return $v;
    }
    private function recoverProjection(int $issuer,array $row,?array $source):array
    {
        $state=$this->projection($row,$source);
        if($state['bank_number_reserved']){
            $reservation=$this->verifyReservation($issuer,$row);
            if($reservation['generation_state']==='generated'){$state['state']='generated';$state['remittance_generated']=true;}
        }
        return $state;
    }
    public function recover($issuer,$actor,$preparation):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$preparation=\IssuerPolicy::id($preparation);
        return $this->transaction(function()use($issuer,$actor,$preparation):array{
            $acl=$this->authorize($issuer,$actor);
            $row=$this->rows('SELECT * FROM '.$this->prefix.'bb_cnab_preparations WHERE id=? AND issuer_id=? FOR UPDATE',[$preparation,$issuer])[0]??null;
            if(!$row)throw new \DomainException('bb400_preparation_unavailable');
            $source=$this->currentOrStale($issuer,$actor,(int)$row['connection_id'],(int)$row['layout_binding_id'],(int)$row['invoice_id'],$acl);
            $state=$this->recoverProjection($issuer,$row,$source);
            $this->authorize($issuer,$actor);return $state;
        });
    }
}
