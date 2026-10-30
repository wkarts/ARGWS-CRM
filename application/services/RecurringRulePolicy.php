<?php
namespace app\services;
defined('BASEPATH') or exit('No direct script access allowed');

/** Pure validation/preview, deliberately unaware of invoices, providers and mail. */
final class RecurringRulePolicy
{
    public const MAX_BATCH = 100;
    public const LEGACY_EMITTER = 'legacy';

    public static function ids($values): array
    {
        if (!is_array($values) || count($values) < 1 || count($values) > self::MAX_BATCH) {
            throw new \InvalidArgumentException('Selecione de 1 a 100 registros.');
        }
        $ids = [];
        foreach ($values as $value) {
            if (!is_scalar($value) || !preg_match('/^[1-9][0-9]{0,9}$/D', (string) $value) || (int) $value > 2147483647) {
                throw new \InvalidArgumentException('Identificador inválido.');
            }
            $ids[] = (int) $value;
        }
        if (count(array_unique($ids)) !== count($ids)) {
            throw new \InvalidArgumentException('A seleção contém registros repetidos.');
        }
        sort($ids, SORT_NUMERIC);
        return $ids;
    }

    public static function schedule(array $data): array
    {
        foreach (['anchor_date', 'anchor_due', 'interval_unit', 'interval_count', 'due_mode', 'cycles_limit'] as $key) {
            if (isset($data[$key]) && !is_scalar($data[$key])) {
                throw new \InvalidArgumentException('Campo de calendário inválido.');
            }
        }
        $anchor = self::date((string) ($data['anchor_date'] ?? ''));
        $due = empty($data['anchor_due']) ? null : self::date((string) $data['anchor_due']);
        if ($due && $due < $anchor) {
            throw new \InvalidArgumentException('O vencimento não pode anteceder a emissão.');
        }
        $unit = strtoupper((string) ($data['interval_unit'] ?? 'MONTH'));
        $interval = filter_var($data['interval_count'] ?? null, FILTER_VALIDATE_INT);
        if (!in_array($unit, ['DAY', 'WEEK', 'MONTH', 'YEAR'], true) || !$interval || $interval < 1 || $interval > 120) {
            throw new \InvalidArgumentException('Intervalo inválido (1 a 120).');
        }
        $mode = (string) ($data['due_mode'] ?? 'days');
        if (!in_array($mode, ['days', 'fixed_day'], true) || ($mode === 'fixed_day' && (!$due || !in_array($unit, ['MONTH', 'YEAR'], true)))) {
            throw new \InvalidArgumentException('Dia fixo exige vencimento e intervalo mensal ou anual.');
        }
        $limit = filter_var($data['cycles_limit'] ?? 0, FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 0 || $limit > 10000) {
            throw new \InvalidArgumentException('Quantidade de ciclos inválida (0 a 10000).');
        }
        return ['anchor_date' => $anchor, 'anchor_due' => $due, 'interval_unit' => $unit,
            'interval_count' => $interval, 'due_mode' => $mode, 'cycles_limit' => $limit];
    }

    public static function date(string $date): string
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('UTC'));
        if (!$parsed || $parsed->format('Y-m-d') !== $date || $date < '1900-01-01' || $date > '2199-12-31') {
            throw new \InvalidArgumentException('Data inválida (use AAAA-MM-DD, entre 1900 e 2199).');
        }
        return $date;
    }

    public static function upcoming(array $rule, int $count = 3): array
    {
        $issue = self::date($rule['next_issue_date']);
        $dates = [];
        $remaining = (int) $rule['cycles_limit'] === 0 ? $count : max(0, (int) $rule['cycles_limit'] - (int) $rule['cycles_emitted']);
        for ($i = 0; $i < min($count, $remaining); $i++) {
            $dates[] = ['issue' => $issue, 'due' => empty($rule['anchor_due']) ? null : RecurringInvoiceDates::dueDate(
                $rule['anchor_date'], $rule['anchor_due'], $issue, $rule['due_mode'], $rule['interval_unit'])];
            $issue = RecurringInvoiceDates::nextIssueDate($rule['anchor_date'], $issue, (int) $rule['interval_count'], $rule['interval_unit']);
        }
        return $dates;
    }

    public static function change(array $rule, string $action, ?string $mode, string $today): array
    {
        self::date($today);
        if ($rule['state'] === 'ended') {
            throw new \InvalidArgumentException('A recorrência foi encerrada.');
        }
        if ((int) $rule['cycles_limit'] > 0 && (int) $rule['cycles_emitted'] >= (int) $rule['cycles_limit']) {
            throw new \InvalidArgumentException('Os ciclos desta recorrência foram esgotados.');
        }
        $after = $rule;
        if ($action === 'due_mode') {
            if ($rule['state'] !== 'active') {
                throw new \InvalidArgumentException('Selecione somente recorrências ativas para alterar vencimentos.');
            }
            $after['due_mode'] = $mode;
            self::schedule($after);
        } elseif ($action === 'pause' && $rule['state'] === 'active') {
            $after['state'] = 'paused';
        } elseif ($action === 'resume' && $rule['state'] === 'paused') {
            $after['state'] = 'active';
            // Resume never bills missed periods. The preview exposes the skipped dates.
            for ($i = 0; $after['next_issue_date'] < $today; $i++) {
                if ($i >= 100000) {
                    throw new \InvalidArgumentException('Calendário muito antigo; revise a regra antes de retomar.');
                }
                $after['next_issue_date'] = RecurringInvoiceDates::nextIssueDate($after['anchor_date'], $after['next_issue_date'], (int) $after['interval_count'], $after['interval_unit']);
            }
        } elseif ($action === 'end') {
            $after['state'] = 'ended';
        } else {
            throw new \InvalidArgumentException('Ação incompatível com o estado atual.');
        }
        return $after;
    }

    public static function legacy(array $invoice): array
    {
        if ((int) $invoice['recurring'] < 1 || !empty($invoice['is_recurring_from']) || in_array((int) $invoice['status'], [5, 6], true)
            || ((int) $invoice['cycles'] > 0 && (int) $invoice['total_cycles'] >= (int) $invoice['cycles'])) {
            throw new \InvalidArgumentException('Selecione uma matriz ativa, não cancelada, com ciclos disponíveis.');
        }
        $schedule = self::schedule(['anchor_date' => $invoice['date'], 'anchor_due' => $invoice['duedate'],
            'interval_count' => $invoice['recurring'], 'interval_unit' => empty($invoice['custom_recurring']) ? 'MONTH' : $invoice['recurring_type'],
            'due_mode' => $invoice['recurring_due_mode'] ?? 'days', 'cycles_limit' => $invoice['cycles']]);
        return $schedule + ['state' => 'active', 'cycles_emitted' => (int) $invoice['total_cycles'],
            'next_issue_date' => RecurringInvoiceDates::nextIssueDate($schedule['anchor_date'], $invoice['last_recurring_date'] ?: $schedule['anchor_date'], $schedule['interval_count'], $schedule['interval_unit']),
            'emitter_key' => self::LEGACY_EMITTER, 'integration_group' => 'manual'];
    }

    public static function summary(array $rule): array
    {
        return array_intersect_key($rule, array_flip(['state', 'anchor_date', 'anchor_due', 'interval_count', 'interval_unit', 'due_mode',
            'cycles_limit', 'cycles_emitted', 'next_issue_date', 'emitter_key', 'integration_group', 'calendar_group', 'version']));
    }

    public static function fingerprint(array $record): string
    {
        return hash('sha256', json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
