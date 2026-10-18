<?php

namespace app\services;

defined('BASEPATH') or exit('No direct script access allowed');

/** Calendar-only calculations. No database writes, clocks or provider calls. */
final class RecurringInvoiceDates
{
    public static function normalizeDueMode($mode): string
    {
        return $mode === 'fixed_day' ? 'fixed_day' : 'days';
    }

    public static function dueDate(string $originalIssue, string $originalDue, string $newIssue, string $mode, string $unit): string
    {
        $issue = self::date($originalIssue);
        $due = self::date($originalDue);
        $next = self::date($newIssue);

        if (self::normalizeDueMode($mode) === 'fixed_day' && in_array(strtoupper($unit), ['MONTH', 'YEAR'], true)) {
            // Preserve the original due-day AND its calendar-month offset from issue.
            // Jan 26 -> Feb 5 therefore remains due on the 5th of the following month.
            $months = ((int) $due->format('Y') - (int) $issue->format('Y')) * 12
                + (int) $due->format('m') - (int) $issue->format('m');
            $target = $next->modify('first day of this month')->modify(sprintf('%+d months', $months));
            return self::clamp($target, (int) $due->format('d'))->format('Y-m-d');
        }

        // Compatibility: the previous scheduler used the absolute original day interval.
        return $next->modify('+' . $issue->diff($due)->days . ' days')->format('Y-m-d');
    }

    public static function nextIssueDate(string $originalDate, string $lastDate, int $interval, string $unit): string
    {
        if ($interval < 1) {
            throw new \InvalidArgumentException('Recurring interval must be positive.');
        }

        $original = self::date($originalDate);
        $last = self::date($lastDate);
        $unit = strtoupper($unit);

        if ($unit === 'MONTH') {
            // Move from day 1, then restore the ORIGINAL anchor, never a clamped day.
            $target = $last->modify('first day of this month')->modify('+' . $interval . ' months');
            return self::clamp($target, (int) $original->format('d'))->format('Y-m-d');
        }

        if ($unit === 'YEAR') {
            $target = $last->setDate((int) $last->format('Y') + $interval, (int) $original->format('m'), 1);
            return self::clamp($target, (int) $original->format('d'))->format('Y-m-d');
        }

        if (!in_array($unit, ['DAY', 'WEEK'], true)) {
            throw new \InvalidArgumentException('Unsupported recurring interval unit.');
        }

        return $last->modify('+' . $interval . ' ' . $unit)->format('Y-m-d');
    }

    private static function clamp(\DateTimeImmutable $month, int $day): \DateTimeImmutable
    {
        return $month->setDate((int) $month->format('Y'), (int) $month->format('m'), min($day, (int) $month->format('t')));
    }

    private static function date(string $value): \DateTimeImmutable
    {
        // A billing date is a civil date, not a timestamp to convert between zones.
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException('Invalid invoice date: ' . $value);
        }
        return $date;
    }
}
