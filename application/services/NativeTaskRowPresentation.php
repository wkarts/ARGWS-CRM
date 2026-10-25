<?php
declare(strict_types=1);
/** The original related-task row markup, shared by both native lineages. No models, DB or hooks. */
final class NativeTaskRowPresentation
{
    public static function render(array$aRow,bool$hasPermissionEdit,bool$hasPermissionDelete,array$task_statuses,array$tasksPriorities,array$callbacks,array$customFieldsColumns,string$today):array
    {
        foreach(['url','translate','escape','date','statusById','statusLabel','brightness','members','tags','priorityColor','priorityLabel']as$key){if(!isset($callbacks[$key])||!is_callable($callbacks[$key]))throw new InvalidArgumentException('Missing native row callback');${$key}=$callbacks[$key];}
            $row = [];

            $row[] = '<div class="checkbox"><input type="checkbox" value="' . $aRow['id'] . '"><label></label></div>';

            $row[] = '<a href="' . $url('tasks/view/' . $aRow['id']) . '" onclick="init_task_modal(' . $aRow['id'] . '); return false;">' . $aRow['id'] . '</a>';

            $outputName = '';

            if ($aRow['not_finished_timer_by_current_staff']) {
                $outputName .= '<span class="pull-left text-danger"><i class="fa-regular fa-clock fa-fw tw-mr-1"></i></span>';
            }

            $outputName .= '<a href="' . $url('tasks/view/' . $aRow['id']) . '" class="main-tasks-table-href-name tw-font-medium" onclick="init_task_modal(' . $aRow['id'] . '); return false;" title="' . $escape($aRow['task_name']) . '">' . $escape($aRow['task_name']) . '</a>';

            if ($aRow['recurring'] == 1) {
                $outputName .= '<span class="label label-primary inline-block mtop4"> ' . $translate('recurring_task') . '</span>';
            }

            $outputName .= '<div class="row-options">';

            $class = 'text-success bold';
            $style = '';

            $tooltip = '';
            if ($aRow['billed'] == 1 || ! $aRow['is_assigned'] || $aRow['status'] == 5 || ($aRow['native_timer_blocked'] ?? false)) {
                $class = 'text-dark disabled';
                $style = 'style="opacity:0.6;cursor: not-allowed;"';
                if (($aRow['native_timer_blocked'] ?? false)) {
                    $tooltip = ' data-toggle="tooltip" data-title="' . $escape($aRow['native_timer_blocked_reason']) . '"';
                } elseif ($aRow['status'] == 5) {
                    $tooltip = ' data-toggle="tooltip" data-title="' . $escape($statusLabel($aRow['status'], false, true)) . '"';
                } elseif ($aRow['billed'] == 1) {
                    $tooltip = ' data-toggle="tooltip" data-title="' . $translate('task_billed_cant_start_timer') . '"';
                } elseif (! $aRow['is_assigned']) {
                    $tooltip = ' data-toggle="tooltip" data-title="' . $translate('task_start_timer_only_assignee') . '"';
                }
            }

            if ($aRow['not_finished_timer_by_current_staff']) {
                $outputName .= '<a href="#" class="text-danger tasks-table-stop-timer" onclick="timer_action(this,' . $aRow['id'] . ',' . $aRow['not_finished_timer_by_current_staff'] . '); return false;">' . $translate('task_stop_timer') . '</a>';
            } else {
                $outputName .= '<span' . $tooltip . ' ' . $style . '>
        <a href="#" class="' . $class . ' tasks-table-start-timer" onclick="timer_action(this,' . $aRow['id'] . '); return false;">' . $translate('task_start_timer') . '</a>
        </span>';
            }

            if ($hasPermissionEdit) {
                $outputName .= '<span class="tw-text-neutral-300"> | </span><a href="#" onclick="edit_task(' . $aRow['id'] . '); return false">' . $translate('edit') . '</a>';
            }

            if ($hasPermissionDelete) {
                $outputName .= '<span class="tw-text-neutral-300"> | </span><a href="' . $url('tasks/delete_task/' . $aRow['id']) . '" class="text-danger _delete task-delete">' . $translate('delete') . '</a>';
            }
            $outputName .= '</div>';

            $row[]           = $outputName;
            $canChangeStatus = ($aRow['current_user_is_creator'] != '0' || $aRow['current_user_is_assigned'] || $hasPermissionEdit);
            $status          = $statusById($aRow['status']);
            $outputStatus    = '';

            if ($canChangeStatus) {
                $outputStatus .= '<div class="dropdown inline-block" data-title="'. $escape($status['name']) .'">';
                $outputStatus .= '<a href="#" class="dropdown-toggle label tw-flex tw-items-center tw-gap-1 tw-flex-nowrap hover:tw-opacity-80 tw-align-middle" style="color:' . $status['color'] . ';border:1px solid ' . $brightness($status['color'], 0.4) . ';background: ' . $brightness($status['color'], 0.04) . ';" task-status-table="' . $escape($aRow['status']) . '" id="tableTaskStatus-' . $aRow['id'] . '" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">';
                $outputStatus .= $escape($status['name']);
                $outputStatus .= '<i class="chevron tw-shrink-0"></i>';
                $outputStatus .= '</a>';

                $outputStatus .= '<ul class="dropdown-menu" aria-labelledby="tableTaskStatus-' . $aRow['id'] . '">';

                foreach ($task_statuses as $taskChangeStatus) {
                    if ($aRow['status'] != $taskChangeStatus['id']) {
                        $outputStatus .= '<li>
                  <a href="#" onclick="task_mark_as(' . $taskChangeStatus['id'] . ',' . $aRow['id'] . '); return false;">
                     ' . $escape($translate('task_mark_as', $taskChangeStatus['name'])) . '
                  </a>
               </li>';
                    }
                }
                $outputStatus .= '</ul>';
                $outputStatus .= '</div>';
            } else {
                $outputStatus .= '<span class="label" style="color:' . $status['color'] . ';border:1px solid ' . $brightness($status['color'], 0.4) . ';background: ' . $brightness($status['color'], 0.04) . ';" task-status-table="' . $escape($aRow['status']) . '">' . $escape($status['name']) . '</span>';
            }

            $row[] = $outputStatus;
            $row[] = $escape($date($aRow['startdate']));

            $row[] = $escape($date($aRow['duedate']));

            $row[] = $members($aRow['assignees_ids'], $aRow['assignees']);

            $row[] = $tags($aRow['tags']);

            if ($hasPermissionEdit && $aRow['status'] != 5) {
                $outputPriority = '<div class="dropdown inline-block">';
                $outputPriority .= '<a href="#" style="color:' . $escape($priorityColor($aRow['priority'])) . '" class="dropdown-toggle tw-flex tw-items-center tw-gap-1 tw-flex-nowrap hover:tw-opacity-80 tw-align-middle" id="tableTaskPriority-' . $aRow['id'] . '" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">';
                $outputPriority .= $escape($priorityLabel($aRow['priority']));
                $outputPriority .= '<i class="chevron tw-shrink-0"></i>';
                $outputPriority .= '</a>';

                $outputPriority .= '<ul class="dropdown-menu" aria-labelledby="tableTaskPriority-' . $aRow['id'] . '">';

                foreach ($tasksPriorities as $priority) {
                    if ($aRow['priority'] != $priority['id']) {
                        $outputPriority .= '<li>
                  <a href="#" onclick="task_change_priority(' . $priority['id'] . ',' . $aRow['id'] . '); return false;">
                     ' . $escape($priority['name']) . '
                  </a>
               </li>';
                    }
                }
                $outputPriority .= '</ul>';
                $outputPriority .= '</div>';
            } else {
                $outputPriority = '<span style="color:' . $escape($priorityColor($aRow['priority'])) . ';" class="inline-block">' . $escape($priorityLabel($aRow['priority'])) . '</span>';
            }

            $row[] = $outputPriority;

            // Custom fields add values
            foreach ($customFieldsColumns as $customFieldColumn) {
                $row[] = (strpos($customFieldColumn, 'date_picker_') !== false ? $date($aRow[$customFieldColumn]) : $aRow[$customFieldColumn]);
            }

            $row['DT_RowClass'] = 'has-row-options has-border-left';

            if ((! empty($aRow['duedate']) && $aRow['duedate'] < $today) && $aRow['status'] != 5) {
                $row['DT_RowClass'] .= ' row-border-danger';
            }

        return$row;
    }
}
