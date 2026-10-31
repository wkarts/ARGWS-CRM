<?php

declare(strict_types=1);
require_once __DIR__.'/../SalesNumberPresentation.php';

require_once __DIR__ . '/IssuerPolicy.php';
require_once __DIR__.'/IssuerTransaction.php';
require_once __DIR__.'/../CompanyCurrencyUnits.php';
require_once __DIR__.'/../CompanyInvoiceBalance.php';

require_once __DIR__.'/../CompanyInvoicePresentation.php';

/** Immutable issuer identity and issuer/series-local numbering. Never reads global options. */
final class InvoiceIssuerDocuments
{
    private $db;
    private string $prefix;
    private string $tables;
    private int $actor;
    private $authorize;
    private CompanyPortalInvoices|NativeCompanyPublicInvoice|null $portalSource;private ?array $portalSession;

    /** Authorization is mandatory; the production adapter must also enforce the rollout gate. */
    public function __construct($db, string $prefix, $actor, callable $authorize,CompanyPortalInvoices|NativeCompanyPublicInvoice|null $portalSource=null,?array $portalSession=null)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) {
            throw new InvalidArgumentException('Invalid table prefix');
        }
        $this->db = $db;
        $this->prefix = $prefix;
        $this->tables = $prefix . 'issuers_';
        $this->actor = $portalSource instanceof NativeCompanyPublicInvoice && $actor === 0 ? 0 : IssuerPolicy::id($actor);
        $this->authorize = $authorize;
        if(($portalSource===null)!==($portalSession===null))throw new InvalidArgumentException('issuer_document_portal_context_required');$this->portalSource=$portalSource;$this->portalSession=$portalSession;
    }

    private function query(string $sql, array $bindings = [])
    {
        $result = IssuerStorage::query($this->db,$sql,$bindings);
        if ($result === false) { throw new RuntimeException('issuer_document_storage_failed'); }
        return $result;
    }

    private function one(string $sql, array $bindings = []): ?array
    {
        return $this->query($sql, $bindings)->result_array()[0] ?? null;
    }

    /** Atomic rollback is supported only by transactional storage for every participant. */
    private function assertTransactionalStorage(array $extraTables = []): void
    {
        $driver = $this->db->dbdriver ?? $this->db->driver ?? null;
        // SQLite is used by the isolated PDO test adapter; it does not prove MySQL row locking.
        if ($driver === 'sqlite') { return; }
        if (!in_array($driver, ['mysql', 'mysqli'], true)
            && !($driver === 'pdo' && ($this->db->subdriver ?? null) === 'mysql')) {
            throw new DomainException('issuer_document_storage_unsupported');
        }
        $tables = [$this->tables . 'lock', $this->tables . 'registry', $this->prefix . 'invoices', $this->tables . 'invoice_documents', $this->tables . 'sequences', $this->tables . 'audit'];
        foreach ($extraTables as $table) { $tables[] = $this->prefix . $table; }
        $placeholders = implode(',', array_fill(0, count($tables), '?'));
        $rows = $this->query('SELECT TABLE_NAME AS table_name, ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (' . $placeholders . ')', $tables)->result_array();
        $engines = [];
        foreach ($rows as $row) { $engines[$row['table_name']] = strtoupper((string) $row['engine']); }
        foreach ($tables as $table) {
            if (($engines[$table] ?? '') !== 'INNODB') {
                throw new DomainException('issuer_document_transactional_storage_required');
            }
        }
    }

    /** CI3 nested transactions only adjust a counter; they never own a durable commit. */
    private function assertIndependentTransaction(): void
    {
        $connection = $this->db->conn_id ?? $this->db->pdo ?? null;
        if (property_exists($this->db, '_trans_depth')) {
            if ((new ReflectionProperty($this->db, '_trans_depth'))->getValue($this->db) !== 0) {
                throw new RuntimeException('issuer_document_outer_transaction_not_allowed');
            }
        } elseif (!$connection instanceof PDO) {
            // The isolated PDO adapter has no CI counter. Unknown transaction wrappers fail closed.
            throw new RuntimeException('issuer_document_transaction_state_unavailable');
        }
        if ($connection instanceof PDO && $connection->inTransaction()) {
            throw new RuntimeException('issuer_document_outer_transaction_not_allowed');
        }
        if (($this->db->dbdriver ?? $this->db->driver ?? null) === 'sqlite') { return; }
        $state = $this->one('SELECT @@SESSION.autocommit AS autocommit, @@SESSION.completion_type AS completion_type');
        if ((string) ($state['autocommit'] ?? '') !== '1'
            || !in_array((string) ($state['completion_type'] ?? ''), ['0', 'NO_CHAIN'], true)) {
            throw new RuntimeException('issuer_document_autocommit_required');
        }
    }

    /** Own the whole boundary: no snapshot or DTO source escapes an unconfirmed commit. */
    private function transaction(array $extraTables, callable $operation): array
    {
        $hasDebug = property_exists($this->db, 'db_debug');
        $debug = $hasDebug ? $this->db->db_debug : null;
        if ($hasDebug) { $this->db->db_debug = false; }
        $owned = false;
        try {
            $this->assertTransactionalStorage($extraTables);
            $this->assertIndependentTransaction();
            if (($this->db->dbdriver ?? $this->db->driver ?? null) !== 'sqlite'
                && !$this->db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ')) {
                // MySQL/MariaDB reject this inside a raw SQL transaction without committing it.
                throw new RuntimeException('issuer_document_outer_transaction_or_isolation_unavailable');
            }
            if (!$this->db->trans_begin()) { throw new RuntimeException('issuer_document_storage_failed'); }
            $owned = true;
            $result = $operation();
            if ($this->db->trans_status() !== true || !$this->db->trans_commit()) {
                throw new RuntimeException('issuer_document_commit_unconfirmed');
            }
            // A successful commit cannot be undone. Never roll back a caller's transaction
            // if a driver silently nested or chained instead of ending the transaction.
            $owned = false;
            $this->assertIndependentTransaction();
            return $result;
        } catch (Throwable $exception) {
            if ($owned) { $this->db->trans_rollback(); }
            throw $exception;
        } finally {
            if ($hasDebug) { $this->db->db_debug = $debug; }
        }
    }

    private function requireAccess(int $issuer, string $capability): void
    {
        if ($this->portalSource instanceof NativeCompanyPublicInvoice) {
            if (!in_array($capability,['view_invoices','render_invoices'],true))throw new DomainException('issuer_access_denied');
            $this->portalSource->assertIdentity($issuer,$this->portalSource->invoiceId(),$this->db,$this->prefix);
        }
        if (($this->authorize)($issuer, $this->actor, $capability) !== true) {
            throw new DomainException('issuer_access_denied');
        }
    }

    public static function series($series): string
    {
        if (!is_string($series) || !preg_match('/^[A-Z0-9][A-Z0-9_-]{0,31}$/D', $series)) {
            throw new DomainException('issuer_document_invalid_series');
        }
        return $series;
    }

    private static function text($value, int $max, bool $required = false, bool $multiline = false): string
    {
        if (!is_string($value) || strlen($value) > $max || preg_match('//u', $value) !== 1
            || preg_match($multiline ? '/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/' : '/[\x00-\x1f\x7f]/', $value)
            || ($required && trim($value) === '')) {
            throw new DomainException('issuer_document_invalid_profile');
        }
        return trim($value);
    }

    /** Accept bounded image bytes only: no URL, filesystem path, SVG or executable markup. */
    public static function logo($value): string
    {
        if ($value === '') { return ''; }
        if (!is_string($value) || strlen($value) > 700000
            || !preg_match('#^data:image/(png|jpeg);base64,([A-Za-z0-9+/]+={0,2})$#D', $value, $match)) {
            throw new DomainException('issuer_document_invalid_logo');
        }
        $bytes = base64_decode($match[2], true);
        $image = $bytes === false ? false : @getimagesizefromstring($bytes);
        if ($bytes === false || strlen($bytes) > 524288 || !$image
            || ($image['mime'] ?? '') !== 'image/' . $match[1]
            || $image[0] < 1 || $image[1] < 1 || $image[0] > 4096 || $image[1] > 4096
            || $image[0] * $image[1] > 16000000) {
            throw new DomainException('issuer_document_invalid_logo');
        }
        return 'data:image/' . $match[1] . ';base64,' . base64_encode($bytes);
    }

    /** Every setting is explicit. Empty optional values remain empty rather than inheriting options. */
    public static function identity(array $issuer): array
    {
        try { $settings = json_decode($issuer['settings_json'] ?? '', true, 32, JSON_THROW_ON_ERROR); }
        catch (JsonException $exception) { throw new DomainException('issuer_document_invalid_profile'); }
        $profile = is_array($settings) ? ($settings['document_profile'] ?? null) : null;
        if (!is_array($profile)) { throw new DomainException('issuer_document_profile_required'); }
        $limits = ['address'=>500, 'city'=>100, 'state'=>100, 'postcode'=>32, 'country_code'=>2, 'phone'=>50, 'email'=>191, 'prefix'=>50, 'terms'=>10000];
        $keys = array_merge(array_keys($limits), ['padding', 'logo_inline']);
        if (array_diff($keys, array_keys($profile)) || array_diff(array_keys($profile), array_merge($keys,['signature_inline','pdf_preferences','number_format','number_date']))) {
            throw new DomainException('issuer_document_invalid_profile');
        }
        $identity = [
            'legal_name' => self::text($issuer['legal_name'] ?? null, 191, true),
            'tax_id' => IssuerPolicy::taxId($issuer['tax_id'] ?? null),
        ];
        foreach ($limits as $field => $limit) {
            $identity[$field] = self::text($profile[$field], $limit, in_array($field, ['address','city','state','postcode','country_code'], true), $field === 'terms');
        }
        if (!preg_match('/^[A-Z]{2}$/D', $identity['country_code'])
            || ($identity['email'] !== '' && !filter_var($identity['email'], FILTER_VALIDATE_EMAIL))
            || !is_int($profile['padding']) || $profile['padding'] < (isset($profile['number_format'])?0:1) || $profile['padding'] > (isset($profile['number_format'])?99:20)) {
            throw new DomainException('issuer_document_invalid_profile');
        }
        $identity['padding'] = $profile['padding'];
        if(array_key_exists('number_format',$profile)||array_key_exists('number_date',$profile)){
            if(!in_array($profile['number_format']??null,[1,2,3,4],true)||!is_string($profile['number_date']??null))throw new DomainException('issuer_document_invalid_profile');
            $date=DateTimeImmutable::createFromFormat('!Y-m-d',$profile['number_date']);
            if(!$date||$date->format('Y-m-d')!==$profile['number_date'])throw new DomainException('issuer_document_invalid_profile');
            $identity['number_format']=$profile['number_format'];$identity['number_date']=$profile['number_date'];
        }

        $identity['logo_inline'] = self::logo($profile['logo_inline']);
        if(array_key_exists('signature_inline',$profile)){$identity['signature_inline']=self::logo($profile['signature_inline']);}
        if(array_key_exists('pdf_preferences',$profile)){
            $kind=is_array($profile['pdf_preferences'])?($profile['pdf_preferences']['kind']??null):null;if(!in_array($kind,['invoices','creditnotes'],true))throw new DomainException('company_document_pdf_invalid');
            $identity['pdf_preferences']=CompanyInvoicePresentation::validate($profile['pdf_preferences'],$kind);
        }
        return $identity;
    }

    private static function recipient(array $recipient): array
    {
        if (array_diff(['client_id','name','tax_id'], array_keys($recipient))
            || array_diff(array_keys($recipient), ['client_id','name','tax_id'])) {
            throw new DomainException('issuer_document_invalid_recipient');
        }
        try {
            return ['client_id'=>IssuerPolicy::id($recipient['client_id']),
                'name'=>self::text($recipient['name'], 191, true),
                'tax_id'=>IssuerPolicy::taxId($recipient['tax_id'], false) ?? ''];
        } catch (DomainException $exception) {
            throw new DomainException('issuer_document_invalid_recipient');
        }
    }

    /** A new document requires an active, explicitly linked canonical recipient under lock. */
    private function captureRecipient(array $invoice, int $issuer): array
    {
        $client = IssuerPolicy::id($invoice['clientid'] ?? null);
        $row = $this->one("SELECT c.userid,c.company,c.vat,c.active,r.tax_id AS registered_tax_id
            FROM {$this->prefix}clients c
            JOIN {$this->tables}clients r ON r.client_id=c.userid
            WHERE c.userid=? AND (r.owner_issuer_id=? OR EXISTS(SELECT 1 FROM {$this->tables}client_links l WHERE l.client_id=c.userid AND l.issuer_id=?)) FOR UPDATE", [$client,$issuer,$issuer]);
        if (!$row || (int) $row['active'] !== 1) {
            throw new DomainException('issuer_document_recipient_unavailable');
        }
        $recipient = self::recipient(['client_id'=>$row['userid'], 'name'=>$row['company'], 'tax_id'=>(string) ($row['vat'] ?? '')]);
        if ($recipient['tax_id'] !== ($row['registered_tax_id'] ?? '')) {
            throw new DomainException('issuer_document_recipient_identity_changed');
        }
        return $recipient;
    }

    private static function assertRecipient(array $snapshot, array $record): void
    {
        if ((string) $snapshot['recipient']['client_id'] !== (string) ($record['clientid'] ?? '')) {
            throw new DomainException('issuer_document_scope_mismatch');
        }
    }

    private static function number($number): string
    {
        if ((!is_int($number) && !is_string($number)) || !preg_match('/^[1-9][0-9]{0,19}$/D', (string) $number)
            || (strlen((string) $number) === 20 && strcmp((string) $number, '18446744073709551615') > 0)) {
            throw new DomainException('issuer_document_invalid_sequence');
        }
        return (string) $number;
    }

    private static function increment(string $number): string
    {
        if ($number === '18446744073709551615') { throw new DomainException('issuer_document_sequence_exhausted'); }
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            if ($number[$i] !== '9') { $number[$i] = (string) ((int) $number[$i] + 1); return $number; }
            $number[$i] = '0';
        }
        return '1' . $number;
    }

    private static function decode(array $snapshot, int $invoice, int $issuer): array
    {
        if ((int) $snapshot['invoice_id'] !== $invoice || (int) $snapshot['issuer_id'] !== $issuer) {
            throw new DomainException('issuer_document_scope_mismatch');
        }
        try {
            $identity = json_decode($snapshot['legal_identity_json'], true, 32, JSON_THROW_ON_ERROR);
            $recipient = json_decode($snapshot['recipient_identity_json'] ?? '', true, 8, JSON_THROW_ON_ERROR);
        }
        catch (JsonException $exception) { throw new DomainException('issuer_document_corrupt'); }
        if (!is_array($recipient) || !is_array($identity) || !isset($identity['legal_name'], $identity['tax_id'])) {
            throw new DomainException('issuer_document_corrupt');
        }
        // Revalidate persisted data before it reaches HTML/PDF. No current registry values are read.
        $profile = $identity;
        unset($profile['legal_name'], $profile['tax_id']);
        $identity = self::identity([
            'legal_name'=>$identity['legal_name'], 'tax_id'=>$identity['tax_id'],
            'settings_json'=>json_encode(['document_profile'=>$profile], JSON_THROW_ON_ERROR),
        ]);
        CompanyInvoicePresentation::specification($identity,'invoices');
        $snapshot['series'] = self::series($snapshot['series']);
        $snapshot['number'] = self::number($snapshot['number']);
        $expected = self::formatted($identity,$snapshot['series'],$snapshot['number']);
        if ($snapshot['prefix'] !== $identity['prefix'] || $snapshot['formatted_number'] !== $expected) {
            throw new DomainException('issuer_document_corrupt');
        }
        $snapshot['identity'] = $identity;
        $snapshot['recipient'] = self::recipient($recipient);
        return $snapshot;
    }

    private static function formatted(array $identity,string $series,string $number):string
    {
        // Existing snapshots retain the original series format byte-for-byte.
        return isset($identity['number_format'])
            ?SalesNumberPresentation::format($number,$identity['number_format'],$identity['prefix'],$identity['number_date'],$identity['padding'])
            :$identity['prefix'].$series.'-'.str_pad($number,$identity['padding'],'0',STR_PAD_LEFT);
    }

    /** A retry returns the original snapshot and does not consume another number. */
    public function allocate($invoice, $issuer, string $series = 'INV'): array
    {
        $invoice = IssuerPolicy::id($invoice);
        $issuer = IssuerPolicy::id($issuer);
        $series = self::series($series);
        $this->requireAccess($issuer, 'create_invoices');
        return IssuerTransaction::run($this->db,$this->prefix,['issuers_lock','issuers_registry','invoices','issuers_invoice_documents','issuers_sequences','issuers_audit','clients','issuers_clients','issuers_client_links','currencies','company_currency_units'],fn():array=>$this->allocateLocked($invoice,$issuer,$series));
    }

    /** Native invoice creation owns the surrounding transaction; no independent/nested commit. */
    public function allocateInOwnedTransaction($invoice,$issuer,string $series,array $profile): array
    {
        IssuerTransaction::assertOwned($this->db,$this->prefix,['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','invoices','issuers_invoice_documents','issuers_sequences','issuers_audit','clients','issuers_clients','issuers_client_links','currencies','company_currency_units']);
        $invoice=IssuerPolicy::id($invoice);$issuer=IssuerPolicy::id($issuer);$series=self::series($series);
        $this->requireAccess($issuer,'create_invoices');
        return $this->allocateLocked($invoice,$issuer,$series,$profile);
    }
    private function allocateLocked(int $invoice,int $issuer,string $series,?array $profile=null): array
    {
        // Same first lock as IssuerRegistry mutations: adoption/share revocation cannot race this capture.
        if (!$this->one("SELECT id FROM {$this->tables}lock WHERE id=1 FOR UPDATE")) {
            throw new RuntimeException('issuer_document_storage_failed');
        }
        // Fixed remaining order: issuer -> invoice -> snapshot -> recipient -> sequence.
        $registry = $this->one("SELECT * FROM {$this->tables}registry WHERE id=? FOR UPDATE", [$issuer]);
        if (!$registry || (int) $registry['active'] !== 1) { throw new DomainException('issuer_unavailable'); }
        $this->requireAccess($issuer, 'create_invoices');
        $record = $this->one("SELECT * FROM {$this->prefix}invoices WHERE id=? FOR UPDATE", [$invoice]);
        if (!$record || !isset($record['issuer_id']) || IssuerPolicy::id($record['issuer_id']) !== $issuer) {
            throw new DomainException('issuer_document_scope_mismatch');
        }
        $snapshot = $this->one("SELECT * FROM {$this->tables}invoice_documents WHERE invoice_id=? FOR UPDATE", [$invoice]);
        if ($snapshot) {
            (new CompanyCurrencyUnits($this->db,$this->prefix))->resolveRead($record['currency']);
            $snapshot = self::decode($snapshot, $invoice, $issuer);
            self::assertRecipient($snapshot, $record);
            if ($snapshot['series'] !== $series) { throw new DomainException('issuer_document_immutable'); }
        } else {
            (new CompanyCurrencyUnits($this->db,$this->prefix))->assertWrite($record['currency']);
            $identity = self::identity($profile===null?$registry:array_replace($registry,['settings_json'=>json_encode(['document_profile'=>$profile],JSON_THROW_ON_ERROR)]));
            $recipient = $this->captureRecipient($record, $issuer);
            $sequence = $this->one("SELECT next_number FROM {$this->tables}sequences WHERE issuer_id=? AND series=? FOR UPDATE", [$issuer, $series]);
            $number = self::number($sequence['next_number'] ?? '1');
            $next = self::increment($number);
            $formatted = self::formatted($identity,$series,$number);
            $created = date('Y-m-d H:i:s');
            $json = json_encode($identity, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $recipientJson = json_encode($recipient, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $this->query("INSERT INTO {$this->tables}invoice_documents (invoice_id,issuer_id,series,number,prefix,formatted_number,legal_identity_json,recipient_identity_json,created_at) VALUES (?,?,?,?,?,?,?,?,?)",
                [$invoice, $issuer, $series, $number, $identity['prefix'], $formatted, $json, $recipientJson, $created]);
            if ($sequence) {
                $this->query("UPDATE {$this->tables}sequences SET next_number=? WHERE issuer_id=? AND series=?", [$next, $issuer, $series]);
            } else {
                $this->query("INSERT INTO {$this->tables}sequences (issuer_id,series,next_number) VALUES (?,?,?)", [$issuer, $series, $next]);
            }
            $this->query("INSERT INTO {$this->tables}audit (actor_id,event,issuer_id,subject_id,created_at) VALUES (?,?,?,?,?)",
                [$this->actor, 'invoice_document_allocated', $issuer, $invoice, $created]);
            $snapshot = self::decode($this->one("SELECT * FROM {$this->tables}invoice_documents WHERE invoice_id=?", [$invoice]), $invoice, $issuer);
        }
        return $snapshot;
    }

    public function forPdf($invoice, $issuer): InvoiceIssuerDocumentData
    {
        require_once __DIR__ . '/InvoiceIssuerDocumentData.php';
        return InvoiceIssuerDocumentData::fromInvoice($this, $invoice, $issuer);
    }

    /** Internal DTO source. Explicit render capability; identifiers only, never caller-supplied invoice data. */
    public function readForPdf($invoice,$issuer):array
    {
        if($this->portalSource instanceof NativeCompanyPublicInvoice)$this->portalSource->assertIdentity(IssuerPolicy::id($issuer),IssuerPolicy::id($invoice),$this->db,$this->prefix);
        $invoice=IssuerPolicy::id($invoice);$issuer=IssuerPolicy::id($issuer);$this->requireAccess($issuer,'render_invoices');$this->requireAccess($issuer,'view_invoices');
        return IssuerTransaction::run($this->db,$this->prefix,array_values(array_unique(array_merge(['issuers_lock','issuers_registry','invoices','issuers_invoice_documents','issuers_sequences','issuers_audit','currencies','company_currency_units','itemable','item_tax','countries'],(new CompanyInvoiceBalance($this->db,$this->prefix))->tables(),$this->portalSource?$this->portalSource->balanceTables():[]))),fn():array=>$this->readForPdfInOwned($invoice,$issuer));
    }
    /** Same canonical native reader, only inside the exact core-owned transaction. */
    public function readForPdfInOwned($invoice,$issuer):array
    {
        if($this->portalSource instanceof NativeCompanyPublicInvoice)$this->portalSource->assertIdentity(IssuerPolicy::id($issuer),IssuerPolicy::id($invoice),$this->db,$this->prefix);
        $invoice=IssuerPolicy::id($invoice);$issuer=IssuerPolicy::id($issuer);IssuerTransaction::assertOwned($this->db,$this->prefix,array_values(array_unique(array_merge(['issuers_lock','issuers_registry','invoices','issuers_invoice_documents','issuers_sequences','issuers_audit','currencies','company_currency_units','itemable','item_tax','countries'],(new CompanyInvoiceBalance($this->db,$this->prefix))->tables(),$this->portalSource?$this->portalSource->balanceTables():[]))));$this->requireAccess($issuer,'render_invoices');$this->requireAccess($issuer,'view_invoices');
            // Serialize permission changes while constructing the authorized DTO, as allocation does.
            if (!$this->one("SELECT id FROM {$this->tables}lock WHERE id=1 FOR UPDATE")) {
                throw new RuntimeException('issuer_document_storage_failed');
            }
            $registry = $this->one("SELECT id,active FROM {$this->tables}registry WHERE id=? FOR UPDATE", [$issuer]);
            if (!$registry || (int) $registry['active'] !== 1) { throw new DomainException('issuer_unavailable'); }
            $record = $this->one("SELECT id,issuer_id,clientid,hash,date,duedate,currency,subtotal,total_tax,total,adjustment,discount_total,status,clientnote,terms,show_quantity_as,
                billing_street,billing_city,billing_state,billing_zip,billing_country,shipping_street,shipping_city,shipping_state,shipping_zip,shipping_country,include_shipping,show_shipping_on_invoice
                FROM {$this->prefix}invoices WHERE id=? FOR UPDATE", [$invoice]);
            if (!$record || !isset($record['issuer_id']) || IssuerPolicy::id($record['issuer_id']) !== $issuer) {
                throw new DomainException('issuer_document_scope_mismatch');
            }
            $snapshot = $this->get($invoice, $issuer);
            $unit=(new CompanyCurrencyUnits($this->db,$this->prefix))->resolveRead($record['currency']);
            $currency=['id'=>(int)$record['currency'],'name'=>$unit['iso_code'],'basis'=>$unit['basis']];
            $items = $this->query("SELECT id,rel_id,rel_type,description,long_description,qty,rate,unit,is_optional,is_selected FROM {$this->prefix}itemable WHERE rel_id=? AND rel_type='invoice' ORDER BY item_order,id FOR UPDATE", [$invoice])->result_array();
            $taxes = $this->query("SELECT itemid,rel_id,rel_type,taxname,taxrate FROM {$this->prefix}item_tax WHERE rel_id=? AND rel_type='invoice' ORDER BY id FOR UPDATE", [$invoice])->result_array();
            $countries = [];
            foreach (['billing_country', 'shipping_country'] as $field) {
                $country = $record[$field];
                if ($country !== null && (string) $country !== '' && (string) $country !== '0') {
                    $countries[(string) $country] = $this->one("SELECT country_id,iso2 FROM {$this->prefix}countries WHERE country_id=?", [$country]);
                }
            }
            $this->requireAccess($issuer, 'render_invoices');
            $this->requireAccess($issuer, 'view_invoices');
            $balance=new CompanyInvoiceBalance($this->db,$this->prefix);$position=$this->portalSource?$balance->portalInOwned($this->portalSource,$this->portalSession,$issuer,$invoice):$balance->inOwned($issuer,$this->actor,$invoice);
            $this->requireAccess($issuer, 'render_invoices');$this->requireAccess($issuer, 'view_invoices');
            $source=compact('snapshot', 'record', 'currency', 'items', 'taxes', 'countries','position');
            $layout=CompanyInvoicePresentation::layout($snapshot['identity'],'invoices');
            if($layout['show_transactions']){require_once __DIR__.'/../CompanyInvoicePaymentPresentation.php';$source['transactions']=CompanyInvoicePaymentPresentation::captureInOwned($this->db,$this->prefix,$issuer,$invoice,$position);}
            if($layout['show_pay_link']&&in_array((int)$record['status'],[1,3,4],true)&&preg_match('/^[a-f0-9]{32}$/D',(string)$record['hash']))$source['portal_path']='invoice/'.$invoice.'/'.$record['hash'].'?issuer_id='.$issuer;
            $this->requireAccess($issuer,'render_invoices');$this->requireAccess($issuer,'view_invoices');return $source;
    }

    /** Read-only. Missing/mismatched snapshots never fall back to global identity or number. */
    public function get($invoice, $issuer): array
    {
        if($this->portalSource instanceof NativeCompanyPublicInvoice)$this->portalSource->assertIdentity(IssuerPolicy::id($issuer),IssuerPolicy::id($invoice),$this->db,$this->prefix);
        $invoice = IssuerPolicy::id($invoice);
        $issuer = IssuerPolicy::id($issuer);
        $this->requireAccess($issuer, 'view_invoices');
        $record = $this->one("SELECT * FROM {$this->prefix}invoices WHERE id=?", [$invoice]);
        if (!$record || !isset($record['issuer_id']) || IssuerPolicy::id($record['issuer_id']) !== $issuer) {
            throw new DomainException('issuer_document_scope_mismatch');
        }
        $snapshot = $this->one("SELECT * FROM {$this->tables}invoice_documents WHERE invoice_id=?", [$invoice]);
        if (!$snapshot) { throw new DomainException('issuer_document_snapshot_required'); }
        $snapshot = self::decode($snapshot, $invoice, $issuer);
        self::assertRecipient($snapshot, $record);
        return $snapshot;
    }
}
