<?php

use app\services\RecurringInvoiceDates;
use app\services\InvoiceIssuerScope;
use app\services\RecurringInvoiceLock;
use app\services\RecurringRulePolicy as Policy;

defined('BASEPATH') or exit('No direct script access allowed');

/** Dedicated metadata writes. Never calls Invoices_model::update or a provider. */
class Recurring_rules_model extends App_Model
{
    private $transactionalStorageVerified = false;

    private const TEMPLATE_FIELDS = ['clientid', 'project_id', 'show_quantity_as', 'currency', 'subtotal', 'total', 'total_tax', 'adjustment',
        'discount_percent', 'discount_total', 'discount_type', 'terms', 'sale_agent', 'billing_street', 'billing_city',
        'billing_state', 'billing_zip', 'billing_country', 'shipping_street', 'shipping_city', 'shipping_state', 'shipping_zip',
        'shipping_country', 'include_shipping', 'show_shipping_on_invoice', 'clientnote'];

    public function __construct()
    {
        parent::__construct();
        $this->load->model('invoices_model');
    }

    public function can_view(array $row): bool
    {
        return staff_can('view', 'invoices')
            || (staff_can('view_own', 'invoices') && (int) $row['addedfrom'] === (int) get_staff_user_id())
            || (get_option('allow_staff_view_invoices_assigned') === '1' && (int) $row['sale_agent'] === (int) get_staff_user_id());
    }

    public function listing(string $source, string $state = '', string $group = '', int $page = 1): array
    {
        $table = $source === 'legacy' ? 'invoices' : 'recurring_rules';
        $this->db->from(db_prefix() . $table);
        if ($source === 'legacy') {
            $this->db->where('recurring >', 0);
            $this->legacy_invoice_scope();
        } else {
            $this->db->where('emitter_key', Policy::LEGACY_EMITTER);
            if (in_array($state, ['active', 'paused', 'ended'], true)) {
                $this->db->where('state', $state);
            }
            if ($group !== '') {
                $this->db->where('calendar_group', $group);
            }
        }
        if (!staff_can('view', 'invoices')) {
            $this->db->group_start();
            $this->db->where('id', -1);
            if (staff_can('view_own', 'invoices')) {
                $this->db->or_where('addedfrom', get_staff_user_id());
            }
            if (get_option('allow_staff_view_invoices_assigned') === '1') {
                $this->db->or_where('sale_agent', get_staff_user_id());
            }
            $this->db->group_end();
        }
        $rows = $this->db->order_by('id', 'desc')->limit(101, (max(1, $page) - 1) * 100)->get()->result_array();
        $result = ['more' => count($rows) > 100, 'rows' => []];
        foreach (array_slice($rows, 0, 100) as $row) {
            try {
                $schedule = $source === 'legacy' ? Policy::legacy($row) : $row;
                $row['preview'] = Policy::upcoming($schedule);
                $row['schedule'] = Policy::summary($schedule);
                $row['error'] = null;
            } catch (InvalidArgumentException $e) {
                $row['error'] = $e->getMessage();
                $row['preview'] = [];
            }
            unset($row['template_json']);
            $result['rows'][] = $row;
        }
        return $result;
    }

    public function get_visible(int $id): array
    {
        $row = $this->db->where('id', $id)->get(db_prefix() . 'recurring_rules')->row_array();
        if (!$row || ($row['emitter_key'] ?? null) !== Policy::LEGACY_EMITTER || !$this->can_view($row)) {
            throw new RuntimeException('Registro indisponível ou sem permissão.');
        }
        return $row;
    }

    public function details(int $id): array
    {
        $rule = $this->get_visible($id);
        return ['rule' => $rule, 'template' => json_decode($rule['template_json'], true, 512, JSON_THROW_ON_ERROR),
            'runs' => $this->db->where('rule_id', $id)->order_by('id', 'desc')->limit(100)->get(db_prefix() . 'recurring_rule_runs')->result_array(),
            'audit' => $this->db->where('rule_id', $id)->order_by('id', 'desc')->limit(100)->get(db_prefix() . 'recurring_rule_audit')->result_array()];
    }

    public function preview(string $source, array $ids, string $action, ?string $mode): array
    {
        $this->require_edit();
        $ids = Policy::ids($ids);
        if (!in_array($source, ['rules', 'legacy'], true)
            || !in_array($action, $source === 'legacy' ? ['due_mode', 'migrate'] : ['due_mode', 'pause', 'resume', 'end'], true)) {
            throw new InvalidArgumentException('Operação inválida.');
        }
        if ($action === 'migrate' && !staff_can('create', 'invoices')) {
            throw new RuntimeException('Sem permissão para criar recorrências.');
        }
        $payload = ['ids' => $ids, 'mode' => $mode, 'today' => date('Y-m-d'), 'records' => []];
        $rows = [];
        $valid = true;
        foreach ($ids as $id) {
            try {
                [$record, $before, $after] = $this->proposed($source, $id, $action, $mode, $payload['today']);
                $payload['records'][(string) $id] = Policy::fingerprint($record);
                $rows[] = ['id' => $id, 'before' => Policy::summary($before), 'after' => Policy::summary($after),
                    'before_dates' => Policy::upcoming($before), 'after_dates' => Policy::upcoming($after), 'error' => null];
            } catch (InvalidArgumentException | RuntimeException $e) {
                // Do not expose data or distinguish absent records from unauthorized ones.
                $rows[] = ['id' => $id, 'error' => $e->getMessage()];
                $valid = false;
            }
        }
        $token = null;
        if ($valid) {
            $token = bin2hex(random_bytes(32));
            $this->must($this->db->insert(db_prefix() . 'recurring_rule_batches', [
                'token_hash' => hash('sha256', $token), 'staff_id' => get_staff_user_id(), 'source_type' => $source,
                'action' => $action, 'payload_json' => $this->json($payload), 'created_at' => date('Y-m-d H:i:s'),
                'expires_at' => date('Y-m-d H:i:s', time() + 900),
            ]));
        }
        return ['rows' => $rows, 'token' => $token, 'source' => $source, 'action' => $action, 'mode' => $mode];
    }

    public function apply(string $token, bool $confirmed): array
    {
        $this->require_edit();
        if (!$confirmed || !preg_match('/^[a-f0-9]{64}$/D', $token)) {
            throw new InvalidArgumentException('Confirme que somente ciclos futuros serão afetados.');
        }
        return RecurringInvoiceLock::run($this->db, function () use ($token) {
            return $this->transaction(function () use ($token) {
                $hash = hash('sha256', $token);
                $batch = $this->db->query('SELECT * FROM `' . db_prefix() . 'recurring_rule_batches` WHERE token_hash = ? FOR UPDATE', [$hash])->row_array();
                if (!$batch || (int) $batch['staff_id'] !== (int) get_staff_user_id()) {
                    throw new RuntimeException('Prévia indisponível.');
                }
                $payload = json_decode($batch['payload_json'], true, 512, JSON_THROW_ON_ERROR);
                // Recheck current visibility even for an idempotent retry.
                foreach ($payload['ids'] as $id) {
                    $table = $batch['source_type'] === 'legacy' ? 'invoices' : 'recurring_rules';
                    $this->db->query('SELECT id FROM `' . db_prefix() . $table . '` WHERE id = ? FOR UPDATE', [$id]);
                    $this->record($batch['source_type'], (int) $id);
                }
                if ($batch['result_json'] !== null) {
                    return json_decode($batch['result_json'], true, 512, JSON_THROW_ON_ERROR);
                }
                if ($batch['expires_at'] < date('Y-m-d H:i:s') || $payload['today'] !== date('Y-m-d')) {
                    throw new RuntimeException('A prévia expirou. Gere uma nova prévia.');
                }
                if ($batch['action'] === 'migrate' && !staff_can('create', 'invoices')) {
                    throw new RuntimeException('Sem permissão para criar recorrências.');
                }
                $changes = [];
                foreach ($payload['ids'] as $id) {
                    [$record, $before, $after] = $this->proposed($batch['source_type'], (int) $id, $batch['action'], $payload['mode'], $payload['today']);
                    if (!hash_equals($payload['records'][(string) $id], Policy::fingerprint($record))) {
                        throw new RuntimeException('Um registro mudou após a prévia. Nenhuma alteração foi aplicada; gere nova prévia.');
                    }
                    $changes[] = [$id, $record, $before, $after];
                }
                $result = ['action' => $batch['action'], 'ids' => $payload['ids'], 'rule_ids' => [], 'applied_at' => date('Y-m-d H:i:s')];
                foreach ($changes as [$id, $record, $before, $after]) {
                    $ruleId = $batch['source_type'] === 'rules' ? $id : null;
                    $sourceId = $batch['source_type'] === 'legacy' ? $id : ($record['source_invoice_id'] ?? null);
                    if ($batch['action'] === 'migrate') {
                        $ruleId = $this->insert_rule($after, $record['_recurring_template'], (int) $record['addedfrom'], (int) $record['sale_agent'], 'Recorrência da fatura #' . $id, '', $id);
                        // Metadata only. Do not update dates, totals, status, children or providers.
                        $this->must($this->db->where('id', $id)->update(db_prefix() . 'invoices', ['recurring' => 0]));
                    } elseif ($batch['source_type'] === 'legacy') {
                        $this->must($this->db->where('id', $id)->update(db_prefix() . 'invoices', ['recurring_due_mode' => $after['due_mode']]));
                    } else {
                        $after['version'] = (int) $record['version'] + 1;
                        $this->must($this->db->where('id', $id)->where('version', $record['version'])->update(db_prefix() . 'recurring_rules', [
                            'state' => $after['state'], 'due_mode' => $after['due_mode'], 'next_issue_date' => $after['next_issue_date'],
                            'version' => $after['version'], 'updated_at' => date('Y-m-d H:i:s'),
                        ]));
                        if ($this->db->affected_rows() !== 1) {
                            throw new RuntimeException('Alteração concorrente. Gere uma nova prévia.');
                        }
                    }
                    $this->audit($ruleId, $sourceId, $hash, $batch['action'], $before, $after);
                    if ($ruleId) {
                        $result['rule_ids'][] = $ruleId;
                    }
                }
                $this->must($this->db->where('token_hash', $hash)->update(db_prefix() . 'recurring_rule_batches', [
                    'result_json' => $this->json($result), 'applied_at' => $result['applied_at'],
                ]));
                return $result;
            });
        });
    }

    private function proposed(string $source, int $id, string $action, ?string $mode, string $today): array
    {
        $record = $this->record($source, $id);
        $before = $source === 'legacy' ? Policy::legacy($record) : $record;
        if ($action === 'migrate') {
            if ($this->db->where('source_invoice_id', $id)->get(db_prefix() . 'recurring_rules')->row_array()) {
                throw new RuntimeException('Esta matriz já foi migrada.');
            }
            // Paid-only gate has contract semantics the new draft-only executor does not emulate.
            if (get_option('invoices_create_invoice_from_recurring_only_on_paid_invoices') == 1) {
                throw new RuntimeException('Migração bloqueada enquanto a regra global exige matriz paga.');
            }
            $record['_recurring_template'] = $this->snapshot($record);
            $after = $before;
            $after['state'] = 'paused';
            $after['integration_group'] = 'manual';
        } else {
            $after = Policy::change($before, $action, $mode, $today);
        }
        return [$record, $before, $after];
    }

    private function record(string $source, int $id): array
    {
        if ($source === 'rules') {
            return $this->get_visible($id);
        }
        // Read dedicated metadata directly: even get_invoice filters must not run
        // while reviewing/applying a batch of future schedules.
        $this->legacy_invoice_scope();
        $record = $this->db->where('id', $id)->get(db_prefix() . 'invoices')->row_array();
        if (!$record || ($record['issuer_id'] ?? null) !== null || !$this->can_view($record)) {
            throw new RuntimeException('Registro indisponível ou sem permissão.');
        }
        $record['items'] = $this->db->where('rel_id', $id)->where('rel_type', 'invoice')
            ->order_by('item_order', 'asc')->get(db_prefix() . 'itemable')->result_array();
        return $record;
    }

    /** Compatibility with pre-issuer schema; never map an explicit issuer into legacy. */
    private function legacy_invoice_scope(): void
    {
        if (class_exists(InvoiceIssuerScope::class)) {
            InvoiceIssuerScope::applyLegacy($this->db, db_prefix() . 'invoices');
        } elseif ($this->db->field_exists('issuer_id', db_prefix() . 'invoices')) {
            $this->db->where('issuer_id', null);
        }
    }

    /** New rule from the existing customer/catalogue; no source invoice required. */
    public function create_from_catalogue(array $input): int
    {
        if (!staff_can('create', 'invoices')) {
            throw new RuntimeException('Sem permissão para criar recorrências.');
        }
        $requestKey = $input['request_key'] ?? '';
        if (!is_string($requestKey) || !preg_match('/^[a-f0-9]{64}$/D', $requestKey)) {
            throw new InvalidArgumentException('Formulário inválido. Abra uma nova recorrência.');
        }
        $requestHash = hash('sha256', get_staff_user_id() . ':' . $requestKey);
        $schedule = Policy::schedule($input);
        if ($schedule['anchor_date'] < date('Y-m-d')) {
            throw new InvalidArgumentException('A primeira emissão não pode estar no passado.');
        }
        if (($input['emitter_key'] ?? '') !== Policy::LEGACY_EMITTER) {
            throw new InvalidArgumentException('Somente o emitente da instalação atual está disponível nesta etapa.');
        }
        foreach (['title', 'calendar_group', 'clientid'] as $field) {
            if (isset($input[$field]) && !is_scalar($input[$field])) {
                throw new InvalidArgumentException('Campo de cadastro inválido.');
            }
        }
        $title = trim((string) ($input['title'] ?? ''));
        $group = trim((string) ($input['calendar_group'] ?? ''));
        if ($title === '' || mb_strlen($title) > 191 || mb_strlen($group) > 64) {
            throw new InvalidArgumentException('Informe um título (até 191 caracteres) e grupo de até 64 caracteres.');
        }
        $clientId = Policy::ids([$input['clientid'] ?? ''])[0];
        $this->load->model('clients_model');
        $client = $this->clients_model->get($clientId);
        if (!$client || !(int) $client->active || (!staff_can('view', 'customers') && !is_customer_admin($clientId))) {
            throw new RuntimeException('Cliente indisponível ou sem permissão.');
        }
        $this->load->model('invoice_items_model');
        $this->load->model('currencies_model');
        $baseCurrency = $this->currencies_model->get_base_currency();
        $currency = $client->default_currency ?: $baseCurrency->id;
        $items = $input['items'] ?? [];
        if (!is_array($items) || count($items) < 1 || count($items) > 100) {
            throw new InvalidArgumentException('Inclua de 1 a 100 itens do catálogo.');
        }
        $template = ['clientid' => $clientId, 'currency' => $currency, 'project_id' => 0, 'show_quantity_as' => 1,
            'sale_agent' => get_staff_user_id(), 'adjustment' => '0.00', 'discount_percent' => '0.00', 'discount_total' => '0.00',
            'discount_type' => '', 'terms' => '', 'clientnote' => '', 'include_shipping' => 0, 'show_shipping_on_invoice' => 0, 'newitems' => []];
        foreach (['billing_street', 'billing_city', 'billing_state', 'billing_zip', 'billing_country', 'shipping_street', 'shipping_city', 'shipping_state', 'shipping_zip', 'shipping_country'] as $field) {
            $template[$field] = $client->$field ?? '';
            if (in_array($field, ['billing_country', 'shipping_country'], true)) { $template[$field] = (int) $template[$field]; }
        }
        $subtotal = 0;
        $taxTotals = [];
        $requestedItems = [];
        foreach ($items as $index => $line) {
            if (!is_array($line)) {
                throw new InvalidArgumentException('Item inválido.');
            }
            $item = $this->invoice_items_model->get(Policy::ids([$line['id'] ?? ''])[0]);
            if (!$item) {
                throw new InvalidArgumentException('Item de catálogo indisponível.');
            }
            if (!isset($line['qty']) || !is_scalar($line['qty'])) { throw new InvalidArgumentException('Quantidade inválida.'); }
            $quantity = $this->hundredths((string) $line['qty'], false);
            $requestedItems[] = ['id' => (int) $line['id'], 'qty' => $quantity];
            $price = (int) $currency === (int) $baseCurrency->id ? $item->rate : ($item->{'rate_currency_' . $currency} ?? null);
            if ($price === null) {
                throw new InvalidArgumentException('O item não possui preço para a moeda do cliente.');
            }
            $cents = $this->hundredths((string) $price, true);
            $lineCents = intdiv($quantity * $cents + 50, 100);
            if ($lineCents > 99999999999) {
                throw new InvalidArgumentException('Valor do item acima do limite permitido.');
            }
            $subtotal += $lineCents;
            $taxNames = [];
            foreach (['', '_2'] as $suffix) {
                $taxName = $item->{'taxname' . $suffix} ?? null;
                if ($taxName !== null && $taxName !== '') {
                    $rate = $this->hundredths((string) $item->{'taxrate' . $suffix}, true);
                    if ($rate > 10000) {
                        throw new InvalidArgumentException('Imposto do catálogo acima de 100%; revise o cadastro.');
                    }
                    $key = $taxName . '|' . $item->{'taxrate' . $suffix};
                    if (in_array($key, $taxNames, true)) {
                        continue;
                    }
                    $taxNames[] = $key;
                    $taxTotals[$key] = ($taxTotals[$key] ?? 0) + $lineCents * $rate;
                }
            }
            $template['newitems'][] = ['description' => $item->description, 'long_description' => clear_textarea_breaks($item->long_description ?? ''),
                'qty' => $this->money($quantity), 'unit' => $item->unit ?? '', 'rate' => $this->money($cents), 'order' => count($template['newitems']) + 1, 'taxname' => $taxNames];
        }
        // Core calculate_total() accumulates unrounded tax groups, then formats the
        // final total; do the same in integer cent fractions, not binary floats.
        $tax = intdiv(array_sum($taxTotals) + 5000, 10000);
        if ($subtotal + $tax > 99999999999) {
            throw new InvalidArgumentException('Total acima do limite permitido para uma recorrência.');
        }
        $template['subtotal'] = $this->money($subtotal);
        $template['total'] = $this->money($subtotal + $tax);
        $template['total_tax'] = $this->money($tax);
        $schedule += ['state' => 'paused', 'next_issue_date' => $schedule['anchor_date'], 'cycles_emitted' => 0,
            'emitter_key' => Policy::LEGACY_EMITTER, 'integration_group' => 'manual'];
        $payloadHash = Policy::fingerprint([$schedule, $clientId, $title, $group, $requestedItems]);
        return RecurringInvoiceLock::run($this->db, function () use ($schedule, $template, $title, $group, $requestHash, $payloadHash) {
            return $this->transaction(function () use ($schedule, $template, $title, $group, $requestHash, $payloadHash) {
                $existing = $this->db->where('create_request_hash', $requestHash)->get(db_prefix() . 'recurring_rules')->row_array();
                if ($existing) {
                    if (!$this->can_view($existing) || !hash_equals($existing['create_payload_hash'], $payloadHash)) {
                        throw new RuntimeException('Este formulário já foi usado com outros dados. Abra uma nova recorrência.');
                    }
                    return (int) $existing['id'];
                }
                $id = $this->insert_rule($schedule, $template, get_staff_user_id(), get_staff_user_id(), $title, $group, null, $requestHash, $payloadHash);
                $this->audit($id, null, null, 'create', [], $schedule);
                return $id;
            });
        });
    }

    /** Called only while the shared scheduler lock is held. Drafts have no financial hooks. */
    public function process_due_locked(string $today): int
    {
        Policy::date($today);
        $rules = $this->db->where('state', 'active')->where('emitter_key', Policy::LEGACY_EMITTER)
            ->where('integration_group', 'manual')->where('next_issue_date <=', $today)
            ->order_by('next_issue_date', 'asc')->order_by('id', 'asc')->limit(100)->get(db_prefix() . 'recurring_rules')->result_array();
        $created = 0;
        foreach ($rules as $candidate) {
            try {
                $created += $this->transaction(function () use ($candidate, $today) {
                    $rule = $this->db->query('SELECT * FROM `' . db_prefix() . 'recurring_rules` WHERE id = ? FOR UPDATE', [$candidate['id']])->row_array();
                    if (!$rule || $rule['state'] !== 'active' || $rule['emitter_key'] !== Policy::LEGACY_EMITTER
                        || $rule['integration_group'] !== 'manual' || $rule['next_issue_date'] > $today) {
                        return 0;
                    }
                    if ((int) $rule['cycles_limit'] > 0 && (int) $rule['cycles_emitted'] >= (int) $rule['cycles_limit']) {
                        return 0;
                    }
                    if ($this->db->where('rule_id', $rule['id'])->where('cycle_date', $rule['next_issue_date'])->get(db_prefix() . 'recurring_rule_runs')->row_array()) {
                        throw new RuntimeException('O ciclo já está registrado; revisão manual necessária.');
                    }
                    $client = $this->db->select('userid')->where('userid', $rule['clientid'])->where('active', 1)->get(db_prefix() . 'clients')->row_array();
                    if (!$client) {
                        throw new RuntimeException('Cliente indisponível.');
                    }
                    $template = json_decode($rule['template_json'], true, 512, JSON_THROW_ON_ERROR);
                    $dates = Policy::upcoming($rule, 1)[0];
                    $template['date'] = $dates['issue'];
                    $template['duedate'] = $dates['due'];
                    $invoiceId = $this->invoices_model->add_recurring_rule_draft($template);
                    if (!$invoiceId) {
                        throw new RuntimeException('Não foi possível criar o rascunho.');
                    }
                    $this->must($this->db->where('id', $invoiceId)->update(db_prefix() . 'invoices', ['addedfrom' => $rule['addedfrom'], 'sale_agent' => $rule['sale_agent']]));
                    $this->must($this->db->insert(db_prefix() . 'recurring_rule_runs', ['rule_id' => $rule['id'], 'cycle_date' => $dates['issue'],
                        'invoice_id' => $invoiceId, 'created_at' => date('Y-m-d H:i:s')]));
                    $after = $rule;
                    $after['cycles_emitted'] = (int) $rule['cycles_emitted'] + 1;
                    $after['version'] = (int) $rule['version'] + 1;
                    $after['next_issue_date'] = RecurringInvoiceDates::nextIssueDate($rule['anchor_date'], $dates['issue'], (int) $rule['interval_count'], $rule['interval_unit']);
                    if ((int) $rule['cycles_limit'] > 0 && $after['cycles_emitted'] >= (int) $rule['cycles_limit']) {
                        $after['state'] = 'ended';
                    }
                    $this->must($this->db->where('id', $rule['id'])->update(db_prefix() . 'recurring_rules', [
                        'cycles_emitted' => $after['cycles_emitted'], 'next_issue_date' => $after['next_issue_date'], 'state' => $after['state'],
                        'version' => $after['version'], 'updated_at' => date('Y-m-d H:i:s'),
                    ]));
                    $this->audit($rule['id'], $rule['source_invoice_id'], null, 'generate_draft', $rule, $after, 0);
                    return 1;
                });
            } catch (Throwable $e) {
                // IDs only. Templates contain personal data; do not log exception payloads.
                log_message('error', 'Independent recurring rule failed; rule ID ' . (int) $candidate['id']);
            }
        }
        return $created;
    }

    private function snapshot(array $invoice): array
    {
        $template = array_intersect_key($invoice, array_flip(self::TEMPLATE_FIELDS));
        foreach (['terms', 'billing_street', 'shipping_street', 'clientnote'] as $field) {
            $template[$field] = clear_textarea_breaks($template[$field] ?? '');
        }
        $template['rule_custom_fields'] = $this->db->select('fieldid,value')->where('relid', $invoice['id'])->where('fieldto', 'invoice')->get(db_prefix() . 'customfieldsvalues')->result_array();
        $template['newitems'] = [];
        foreach ($invoice['items'] as $item) {
            $taxes = get_invoice_item_taxes($item['id']);
            $template['newitems'][] = ['description' => $item['description'], 'long_description' => clear_textarea_breaks($item['long_description']),
                'qty' => $item['qty'], 'unit' => $item['unit'], 'rate' => $item['rate'], 'order' => $item['item_order'],
                'stored_custom_fields' => $this->db->select('fieldid,value')->where('relid', $item['id'])->where('fieldto', 'items')->get(db_prefix() . 'customfieldsvalues')->result_array(),
                'taxname' => array_column($taxes, 'taxname'), 'is_optional' => (int) ($item['is_optional'] ?? 0), 'is_selected' => (int) ($item['is_selected'] ?? 1)];
        }
        return $template;
    }

    private function insert_rule(array $schedule, array $template, int $owner, int $agent, string $title, string $group, ?int $source = null, ?string $requestHash = null, ?string $payloadHash = null): int
    {
        $data = Policy::summary($schedule);
        $data += ['clientid' => (int) $template['clientid'], 'addedfrom' => $owner, 'sale_agent' => $agent, 'source_invoice_id' => $source,
            'create_request_hash' => $requestHash, 'create_payload_hash' => $payloadHash,
            'title' => $title, 'calendar_group' => $group, 'template_json' => $this->json($template), 'version' => 1,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')];
        $this->must($this->db->insert(db_prefix() . 'recurring_rules', $data));
        return (int) $this->db->insert_id();
    }

    private function audit(?int $rule, ?int $source, ?string $batch, string $action, array $before, array $after, ?int $actor = null): void
    {
        $this->must($this->db->insert(db_prefix() . 'recurring_rule_audit', ['rule_id' => $rule, 'source_invoice_id' => $source,
            'batch_hash' => $batch, 'staff_id' => $actor ?? get_staff_user_id(), 'action' => $action,
            'before_json' => $this->json(Policy::summary($before)), 'after_json' => $this->json(Policy::summary($after)), 'created_at' => date('Y-m-d H:i:s')]));
    }

    private function transaction(callable $operation)
    {
        $this->assert_transactional_storage();
        $this->must($this->db->trans_begin());
        try {
            $result = $operation();
            if (!$this->db->trans_status()) {
                throw new RuntimeException('Falha na transação. Nenhuma alteração foi aplicada.');
            }
            $this->must($this->db->trans_commit());
            return $result;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            throw $e;
        }
    }

    private function assert_transactional_storage(): void
    {
        if ($this->transactionalStorageVerified) {
            return;
        }
        $tables = array_map(static fn($name) => db_prefix() . $name, ['recurring_rules', 'recurring_rule_batches', 'recurring_rule_audit',
            'recurring_rule_runs', 'invoices', 'itemable', 'item_tax', 'sales_activity', 'customfieldsvalues']);
        $rows = $this->db->query('SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('
            . implode(',', array_fill(0, count($tables), '?')) . ')', $tables)->result_array();
        if (count($rows) !== count($tables)) {
            throw new RuntimeException('Estrutura de recorrências incompleta. Aplique a migration antes de continuar.');
        }
        foreach ($rows as $row) {
            if (strcasecmp($row['ENGINE'], 'InnoDB') !== 0) {
                throw new RuntimeException('A operação exige tabelas InnoDB para garantir rollback. Revise o banco antes de continuar.');
            }
        }
        $this->transactionalStorageVerified = true;
    }

    private function require_edit(): void
    {
        if (!staff_can('edit', 'invoices')) {
            throw new RuntimeException('Sem permissão para editar recorrências.');
        }
    }

    private function must($result): void
    {
        if ($result === false) {
            throw new RuntimeException('Falha ao persistir a operação.');
        }
    }

    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private function hundredths(string $value, bool $allowZero): int
    {
        if (!preg_match('/^[0-9]{1,7}(?:\.[0-9]{1,2})?$/D', $value)) {
            throw new InvalidArgumentException('Quantidade ou valor inválido; use até duas casas decimais e ponto decimal.');
        }
        $parts = explode('.', $value);
        $number = (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
        if (!$allowZero && $number === 0) {
            throw new InvalidArgumentException('A quantidade deve ser maior que zero.');
        }
        return $number;
    }

    private function money(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
