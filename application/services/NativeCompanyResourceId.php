<?php

declare(strict_types=1);

/**
 * Lossless browser transport IDs for existing, disjoint storage namespaces.
 * Native legacy IDs and stored source IDs NEVER change. This is not authorization:
 * callers must resolve the decoded source under the authenticated issuer and ACL.
 * Every value stays below Number.MAX_SAFE_INTEGER; do not use JS bitwise operators.
 */
final class NativeCompanyResourceId
{
    private const RADIX = 4294967296;
    // Published namespace values are append-only: never reorder or reuse a value.
    private const SOURCES = [
        'company_project' => [1, 'project'],
        'company_task' => [2, 'task'],
        'company_standalone_task' => [3, 'task'],
        'company_ticket' => [4, 'ticket'],
        'company_contract' => [5, 'contract'],
        'company_lead' => [6, 'lead'],
        'company_expense' => [7, 'expense'],
        'company_estimate' => [8, 'estimate'],
        'company_proposal' => [9, 'proposal'],
        'company_invoice_draft' => [10, 'invoice'],
        'company_task_timer' => [11, 'timer'],
        'company_standalone_timer' => [12, 'timer'],
        'company_event' => [13, 'event'],
        'company_custom_field' => [14, 'custom_field'],
        'company_blank_task_timer' => [15, 'timer'],
        'company_consent_purpose' => [16, 'consent_purpose'],
        'company_consent' => [17, 'consent'],
        'company_project_status' => [20, 'project_status'],
        'company_task_status' => [21, 'task_status'],
        'company_ticket_status' => [22, 'ticket_status'],
        'company_ticket_department' => [23, 'ticket_department'],
        'company_ticket_priority' => [24, 'ticket_priority'],
        'company_ticket_service' => [25, 'ticket_service'],
        'company_work_tag' => [26, 'work_tag'],
        'company_todo' => [30, 'todo'],
        'company_announcement' => [31, 'announcement'],
        'company_reminder' => [32, 'reminder'],
        'company_note' => [33, 'note'],
        'company_item' => [34, 'item'],
        'company_item_group' => [35, 'item_group'],
        'company_tax' => [36, 'tax'],
        'company_filter' => [37, 'filter'],
        'company_notification' => [40, 'notification'],
        'company_search_history' => [41, 'search_history'],
        'company_kb_article' => [42, 'kb_article'],
        'company_kb_group' => [43, 'kb_group'],
        'company_newsfeed_post' => [44, 'newsfeed_post'],
        'company_newsfeed_comment' => [45, 'newsfeed_comment'],
        'company_payment_mode' => [46, 'payment_mode'],
        'company_ticket_predefined_reply' => [47, 'ticket_predefined_reply'],
        'company_ticket_reply' => [48, 'ticket_reply'],
        'company_invoice_draft_line' => [49, 'invoice_item'],
        'company_task_checklist_template' => [50, 'task_checklist_template'],
        'company_staff_role' => [52, 'staff_role'],
        'company_spam_filter' => [51, 'spam_filter'],
    ];
    private const NATIVE = ['consent_purpose', 'consent', 'client', 'invoice', 'project', 'task', 'ticket', 'contract', 'lead', 'expense', 'estimate', 'proposal', 'timer', 'event', 'custom_field', 'contact', 'staff', 'credit_note', 'payment', 'project_status', 'task_status', 'ticket_status', 'ticket_department', 'ticket_priority', 'ticket_service', 'work_tag', 'todo', 'announcement', 'reminder', 'note', 'item', 'item_group', 'tax', 'filter', 'notification', 'search_history', 'kb_article', 'kb_group', 'newsfeed_post', 'newsfeed_comment', 'payment_mode', 'ticket_predefined_reply', 'ticket_reply', 'invoice_item', 'task_checklist_template', 'staff_role', 'spam_filter'];

    private static function integer($value): int
    {
        if (PHP_INT_SIZE < 8 || (!is_int($value) && !is_string($value))
            || !preg_match('/^[1-9][0-9]{0,15}$/D', (string)$value)
            || (string)(int)$value !== (string)$value || (int)$value > 9007199254740991) {
            throw new DomainException('native_resource_id_invalid');
        }
        return (int)$value;
    }

    public static function encode(string $source, $id): string
    {
        $id = self::integer($id);
        if ($id >= self::RADIX) { throw new DomainException('native_resource_source_id_out_of_range'); }
        if (in_array($source, self::NATIVE, true)) { return (string)$id; }
        if (!isset(self::SOURCES[$source])) { throw new DomainException('native_resource_source_unknown'); }
        return (string)(self::SOURCES[$source][0] * self::RADIX + $id);
    }

    /** @return array{source_kind:string,source_id:int,canonical_id:string} */
    public static function decode(string $type, $id): array
    {
        if (!in_array($type, self::NATIVE, true)) { throw new DomainException('native_resource_type_unknown'); }
        $id = self::integer($id);
        if ($id < self::RADIX) { return ['source_kind'=>$type, 'source_id'=>$id, 'canonical_id'=>(string)$id]; }
        $namespace = intdiv($id, self::RADIX);
        $sourceId = $id % self::RADIX;
        if ($sourceId === 0) { throw new DomainException('native_resource_id_invalid'); }
        foreach (self::SOURCES as $source=>$definition) {
            if ($definition[0] === $namespace && $definition[1] === $type) {
                return ['source_kind'=>$source, 'source_id'=>$sourceId, 'canonical_id'=>(string)$id];
            }
        }
        throw new DomainException('native_resource_namespace_mismatch');
    }
}
