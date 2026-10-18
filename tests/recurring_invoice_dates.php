<?php

declare(strict_types=1);

define('BASEPATH', dirname(__DIR__) . '/system/');
define('APPPATH', dirname(__DIR__) . '/application/');
define('APP_MODULES_PATH', dirname(__DIR__) . '/modules/');
require APPPATH . 'hooks/App_Autoloader.php';
App_Autoloader::register();

use app\services\RecurringInvoiceDates;

$checks = 0;
function same($expected, $actual, string $context): void
{
    global $checks;
    $checks++;
    if ($expected !== $actual) {
        throw new RuntimeException($context . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

// Show the previous overflow with the actual PHP primitive used by the CRON.
date_default_timezone_set('UTC');
same('2025-03-03', date('Y-m-d', strtotime('+1 MONTH', strtotime('2025-01-31'))), 'legacy common February');
same('2024-03-02', date('Y-m-d', strtotime('+1 MONTH', strtotime('2024-01-31'))), 'legacy leap February');
same('2026-05-01', date('Y-m-d', strtotime('+1 MONTH', strtotime('2026-03-31'))), 'legacy April');

$cases = [
    ['2025-01-31', '2025-01-31', 1, 'MONTH', '2025-02-28'],
    ['2024-01-31', '2024-01-31', 1, 'MONTH', '2024-02-29'],
    ['2026-03-31', '2026-03-31', 1, 'MONTH', '2026-04-30'],
    ['2025-01-31', '2025-02-28', 1, 'MONTH', '2025-03-31'],
    ['2024-01-31', '2024-02-29', 1, 'MONTH', '2024-03-31'],
    ['2026-03-31', '2026-04-30', 1, 'MONTH', '2026-05-31'],
    ['2025-01-30', '2025-02-28', 1, 'MONTH', '2025-03-30'],
    ['2025-01-29', '2025-02-28', 1, 'MONTH', '2025-03-29'],
    ['2025-01-28', '2025-02-28', 1, 'MONTH', '2025-03-28'],
    ['2025-12-31', '2025-12-31', 1, 'MONTH', '2026-01-31'],
    ['2025-01-31', '2025-01-31', 3, 'MONTH', '2025-04-30'],
    ['2025-01-31', '2025-04-30', 3, 'MONTH', '2025-07-31'],
    ['2024-02-29', '2024-02-29', 1, 'YEAR', '2025-02-28'],
    ['2024-02-29', '2027-02-28', 1, 'YEAR', '2028-02-29'],
    ['2096-02-29', '2099-02-28', 1, 'YEAR', '2100-02-28'],
    ['2024-02-29', '2024-02-29', 4, 'YEAR', '2028-02-29'],
    ['2025-01-31', '2025-03-03', 1, 'MONTH', '2025-04-30'], // No retroactive repair or second invoice in March.
    ['2025-01-31', '2025-02-28', 1, 'DAY', '2025-03-01'],
    ['2025-01-31', '2025-02-28', 2, 'WEEK', '2025-03-14'],
    ['2025-01-26', '2025-02-26', 1, 'month', '2025-03-26'],
];

foreach (['UTC', 'America/Sao_Paulo', 'America/New_York', 'Pacific/Kiritimati'] as $zone) {
    date_default_timezone_set($zone);
    foreach ($cases as [$origin, $last, $interval, $unit, $expected]) {
        same($expected, RecurringInvoiceDates::nextIssueDate($origin, $last, $interval, $unit), $zone . ' ' . $origin . ' ' . $last);
    }
}

// Repeated clamps must not become a new anchor over forty years.
foreach ([28, 29, 30, 31] as $anchor) {
    $origin = sprintf('2000-01-%02d', $anchor);
    $last = $origin;
    for ($cycle = 1; $cycle <= 480; $cycle++) {
        $index = 2000 * 12 + $cycle;
        $year = intdiv($index, 12);
        $month = $index % 12 + 1;
        $first = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $expected = sprintf('%04d-%02d-%02d', $year, $month, min($anchor, (int) $first->format('t')));
        $next = RecurringInvoiceDates::nextIssueDate($origin, $last, 1, 'MONTH');
        same($expected, $next, 'anchor ' . $anchor . ' cycle ' . $cycle);
        same(true, $next > $last, 'strictly increasing');
        $last = $next;
    }
}

foreach ([['2025-02-30', '2025-02-28', 1, 'MONTH'], ['2025-01-31', 'bad', 1, 'MONTH'], ['2025-01-31', '2025-01-31', 0, 'MONTH'], ['2025-01-31', '2025-01-31', 1, 'INVALID']] as $invalid) {
    $caught = false;
    try {
        RecurringInvoiceDates::nextIssueDate(...$invalid);
    } catch (InvalidArgumentException $exception) {
        $caught = true;
    }
    same(true, $caught, 'reject invalid input');
}

foreach (['UTC', 'America/Sao_Paulo', 'America/New_York'] as $zone) {
    date_default_timezone_set($zone);
    foreach ([['2025-01-26', '2025-02-05'], ['2025-02-26', '2025-03-05'], ['2024-02-26', '2024-03-05'], ['2025-04-26', '2025-05-05'], ['2025-12-26', '2026-01-05']] as [$newIssue, $expected]) {
        same($expected, RecurringInvoiceDates::dueDate('2025-01-26', '2025-02-05', $newIssue, 'fixed_day', 'MONTH'), 'fixed 5 ' . $zone);
    }
    same('2025-03-08', RecurringInvoiceDates::dueDate('2025-01-26', '2025-02-05', '2025-02-26', 'days', 'MONTH'), 'preserve 10-day contract');
    same('2025-02-02', RecurringInvoiceDates::dueDate('2025-02-26', '2025-03-05', '2025-01-26', 'days', 'MONTH'), 'preserve 7-day contract');
    same('2025-05-03', RecurringInvoiceDates::dueDate('2025-02-26', '2025-03-05', '2025-04-26', 'days', 'MONTH'), 'original 7 days in April');
    same('2025-03-08', RecurringInvoiceDates::dueDate('2025-01-26', '2025-02-05', '2025-02-26', 'fixed_day', 'WEEK'), 'weekly keeps days');
    same('2025-03-08', RecurringInvoiceDates::dueDate('2025-01-26', '2025-02-05', '2025-02-26', 'fixed_day', 'DAY'), 'daily keeps days');
    same('2025-03-08', RecurringInvoiceDates::dueDate('2025-01-26', '2025-02-05', '2025-02-26', 'unknown', 'MONTH'), 'unknown mode defaults days');
    same('2025-02-28', RecurringInvoiceDates::dueDate('2024-02-01', '2024-02-29', '2025-02-01', 'fixed_day', 'YEAR'), 'annual due clamp');
    same('2028-02-29', RecurringInvoiceDates::dueDate('2024-02-01', '2024-02-29', '2028-02-01', 'fixed_day', 'YEAR'), 'annual due restores leap day');
    same('2025-03-01', RecurringInvoiceDates::dueDate('2025-01-31', '2025-02-01', '2025-02-28', 'fixed_day', 'MONTH'), 'issue and due independent');
    same('2025-05-05', RecurringInvoiceDates::dueDate('2025-01-26', '2025-02-05', '2025-04-26', 'fixed_day', 'MONTH'), 'quarterly month offset');
    foreach ([28, 29, 30, 31] as $dueDay) {
        $originalDue = sprintf('2024-01-%02d', $dueDay);
        same(sprintf('2025-02-%02d', min($dueDay, 28)), RecurringInvoiceDates::dueDate('2024-01-01', $originalDue, '2025-02-01', 'fixed_day', 'MONTH'), 'due common February ' . $dueDay);
        same(sprintf('2024-02-%02d', min($dueDay, 29)), RecurringInvoiceDates::dueDate('2024-01-01', $originalDue, '2024-02-01', 'fixed_day', 'MONTH'), 'due leap February ' . $dueDay);
        same(sprintf('2025-04-%02d', min($dueDay, 30)), RecurringInvoiceDates::dueDate('2024-01-01', $originalDue, '2025-04-01', 'fixed_day', 'MONTH'), 'due April ' . $dueDay);
        same(sprintf('2025-05-%02d', $dueDay), RecurringInvoiceDates::dueDate('2024-01-01', $originalDue, '2025-05-01', 'fixed_day', 'MONTH'), 'due restored May ' . $dueDay);
    }
}

same('days', RecurringInvoiceDates::normalizeDueMode(null), 'default mode');
same('days', RecurringInvoiceDates::normalizeDueMode(['fixed_day']), 'reject array');
same('fixed_day', RecurringInvoiceDates::normalizeDueMode('fixed_day'), 'explicit fixed mode');

echo 'Recurring invoice dates: ' . $checks . ' checks passed.' . PHP_EOL;
