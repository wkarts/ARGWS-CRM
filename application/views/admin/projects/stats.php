<?php
$nativeProjectStats = isset($native_project_summary);
if ($nativeProjectStats) {
    if (!is_array($native_project_summary)
        || ($native_project_summary['statuses'] ?? null) !== $statuses
        || !is_array($native_project_summary['status_counts'] ?? null)) {
        throw new DomainException('native_project_summary_invalid');
    }
    foreach ($statuses as $status) {
        if (!isset($native_project_summary['status_counts'][$status['id']])
            || !is_int($native_project_summary['status_counts'][$status['id']])
            || $native_project_summary['status_counts'][$status['id']] < 0) {
            throw new DomainException('native_project_summary_invalid');
        }
    }
} else {
    $_where = '';
    if (staff_cant('view', 'projects')) {
        $_where = 'id IN (SELECT project_id FROM ' . db_prefix() . 'project_members WHERE staff_id=' . get_staff_user_id() . ')';
    }
}
?>
<div
    class="_filters _hidden_inputs tw-mb-3 tw-flex tw-flex-col tw-gap-y-2 tw-order-1 sm:tw-flex-row sm:tw-gap-x-2 sm:-tw-order-none sm:tw-mr-2 md:tw-mb-0">
    <?php foreach ($statuses as $status) { ?>
    <?php if (!$nativeProjectStats) { $where = ($_where == '' ? '' : $_where . ' AND ') . 'status = ' . $status['id']; } ?>
    <a href="#"
        class="tw-bg-transparent tw-border tw-border-solid tw-border-neutral-300 tw-shadow-sm tw-py-1 tw-px-2 tw-rounded-lg tw-text-sm hover:tw-bg-neutral-200/60 tw-text-neutral-600 hover:tw-text-neutral-600 focus:tw-text-neutral-600"
        @click.prevent="extra.projectsRules = <?= app\services\utilities\Js::from($table->findRule('status')->setValue([(int) $status['id']])); ?>">
        <span class="tw-font-semibold tw-mr-1 rtl:tw-ml-1">
            <?= $nativeProjectStats ? $native_project_summary['status_counts'][$status['id']] : total_rows(db_prefix() . 'projects', $where); ?>
        </span>
        <span
            style="color: <?= e($status['color']); ?>"
            class="<?= 'project-status-' . $status['color']; ?>">
            <?= e($status['name']); ?>
        </span>
    </a>
    <?php } ?>
</div>