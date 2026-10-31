<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div>
    <?php
    $nativeSummary = isset($native_ticket_summary);
    if ($nativeSummary) {
        if (isset($project_id)) throw new DomainException('native_ticket_summary_project_scope_unavailable');
        if (!CompanyRequestScope::nativeFor(get_instance()) || !CompanyRequestScope::sealedFor(get_instance()) || !is_array($native_ticket_summary) || !isset($native_ticket_summary['statuses'],$native_ticket_summary['counts'])) throw new DomainException('native_ticket_summary_invalid');
        $statuses = $native_ticket_summary['statuses'];
        foreach ($statuses as $status) if (!array_key_exists($status['ticketstatusid'], $native_ticket_summary['counts']) || !is_int($native_ticket_summary['counts'][$status['ticketstatusid']]) || $native_ticket_summary['counts'][$status['ticketstatusid']] < 0) throw new DomainException('native_ticket_summary_invalid');
    } else $statuses = $this->tickets_model->get_ticket_status();
?>
    <div class="_filters _hidden_inputs hidden tickets_filters">
        <?php
  foreach ($statuses as $status) {
      $val = '';
      if ($chosen_ticket_status != '') {
          if ($chosen_ticket_status == $status['ticketstatusid']) {
              $val = $chosen_ticket_status;
          }
      } else {
          if (in_array($status['ticketstatusid'], $default_tickets_list_statuses)) {
              $val = 1;
          }
      }
      echo form_hidden('ticket_status_' . $status['ticketstatusid'], $val);
  } ?>
    </div>
    <div
        class="tw-mb-3 tw-flex  tw-flex-col tw-flex-wrap tw-gap-y-2 tw-order-1 sm:tw-flex-row sm:tw-gap-x-2 sm:-tw-order-none sm:tw-mr-2 md:tw-mb-0">
        <?php
  $where = '';
if (!$nativeSummary && ! is_admin()) {
    if (get_option('staff_access_only_assigned_departments') == 1) {
        $departments_ids = [];
        if (count($staff_deparments_ids) == 0) {
            $departments = $this->departments_model->get();

            foreach ($departments as $department) {
                array_push($departments_ids, $department['departmentid']);
            }
        } else {
            $departments_ids = $staff_deparments_ids;
        }
        if (count($departments_ids) > 0) {
            $where = 'AND department IN (SELECT departmentid FROM ' . db_prefix() . 'staff_departments WHERE departmentid IN (' . implode(',', $departments_ids) . ') AND staffid="' . get_staff_user_id() . '")';
        }
    }
}

foreach ($statuses as $status) {
    $_where = '';
    if ($where == '') {
        $_where = 'status=' . $status['ticketstatusid'];
    } else {
        $_where = 'status=' . $status['ticketstatusid'] . ' ' . $where;
    }
    if (isset($project_id)) {
        $_where = $_where . ' AND project_id=' . $project_id;
    }
    $_where = $_where . ' AND merged_ticket_id IS NULL'; ?>
        <a href="#"
            data-cview="ticket_status_<?= e($status['ticketstatusid']); ?>"
            class="tw-bg-transparent tw-border tw-border-solid tw-border-neutral-300 tw-shadow-sm tw-py-1 tw-px-2 tw-rounded-lg tw-text-sm hover:tw-bg-neutral-200/60 tw-text-neutral-600 hover:tw-text-neutral-600 focus:tw-text-neutral-600"
            <?= ($hrefAttrs ?? null instanceof Closure) ? $hrefAttrs($status) : ''; ?>>
            <span class="tw-font-semibold tw-mr-1 rtl:tw-ml-1">
                <?= $nativeSummary ? $native_ticket_summary['counts'][$status['ticketstatusid']] : total_rows(db_prefix() . 'tickets', $_where); ?>
            </span>
            <span
                style="color:<?= e($status['statuscolor']); ?>">
                <?= e($nativeSummary ? $status['name'] : ticket_status_translate($status['ticketstatusid'])); ?>
            </span>
        </a>
        <?php
} ?>
    </div>
</div>