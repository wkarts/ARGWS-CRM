<?php

defined('BASEPATH') or exit('No direct script access allowed');
require_once APPPATH.'services/NativeCompanyAdminPresentation.php';
$nativeTimers=NativeCompanyAdminPresentation::current(function_exists('get_instance')?get_instance():$this);
$timerLabel=$nativeTimers?static function($key,...$args){$text=NativeCompanyAdminPresentation::label($key);return $args?vsprintf($text,$args):$text;}:static fn($key,...$args)=>_l($key,...$args);
$timerUrl=$nativeTimers?static fn($path)=>CompanyRequestScope::adminUrl(function_exists('get_instance')?get_instance():$this,$path):static fn($path)=>admin_url($path);
if($nativeTimers){
    require_once APPPATH.'services/NativeCompanyResourceId.php';
    if(!isset($nativeTimerPolicy)||!is_array($nativeTimerPolicy)||!preg_match('/^[a-f0-9]{64}$/D',$nativeTimerPolicy['policy_hash']??''))throw new LogicException('native_timer_policy_missing');
    foreach(['can_edit','can_view_all','can_start_blank','auto_stop'] as $field)if(!array_key_exists($field,$nativeTimerPolicy)||!is_bool($nativeTimerPolicy[$field]))throw new LogicException('native_timer_policy_invalid');
    foreach($startedTimers as $timer){
        foreach(['id','task_id'] as $field)if(!preg_match('/^(0|[1-9][0-9]{0,15})$/D',(string)($timer[$field]??''))||(float)$timer[$field]>9007199254740991)throw new LogicException('native_timer_reference_invalid');
        $timerSource=NativeCompanyResourceId::decode('timer',$timer['id'])['source_kind'];$validRevision=$timerSource==='timer'?(is_string($timer['revision']??null)&&preg_match('/^[a-f0-9]{64}$/D',$timer['revision'])):(is_int($timer['revision']??null)&&$timer['revision']>=1);
        if(!$validRevision||!isset($timer['note_revision'],$timer['total_seconds'],$timer['started_at_label'])||!is_int($timer['note_revision'])||$timer['note_revision']<0||!is_int($timer['total_seconds'])||$timer['total_seconds']<0||!is_string($timer['started_at_label'])||$timer['started_at_label']==='')throw new LogicException('native_timer_projection_incomplete');
    }
    require_once APPPATH.'services/utilities/Format.php';
}


$totalTimers         = count($startedTimers);
$noTimersWithoutTask = true;

if ($totalTimers == 0) {
    echo '<li class="text-center inline-block full-width tw-mb-2"><div class="text-center">
    <svg class="tw-mx-auto tw-h-10 tw-w-10 tw-text-neutral-400 -tw-mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
      <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
    </svg>
    <p class="tw-my-0 tw-text-sm tw-font-medium tw-text-gray-900">' . $timerLabel('no_timers_found') . '</p>
  </div></li>';
}

$i = 0;

foreach ($startedTimers as $timer) {
    $data = '';

    $data .= '<li class="timer relative" id="timer-' . $timer['id'] . '"'.($nativeTimers?' data-timer-revision="'.e($timer['revision']).'" data-note-revision="'.e($timer['note_revision']).'" data-policy-hash="'.e($nativeTimerPolicy['policy_hash']).'"':'').'>';

    if ($timer['task_id'] != '0') {
        $data .= '<a href="' . $timerUrl('tasks/view/' . $timer['task_id']) . '" class="_timer font-medium" onclick="init_task_modal(' . $timer['task_id'] . ');return false;">' . e($timer['task_subject']) . '</a>';
    } else {
        $noTimersWithoutTask = false;
    }

    if(!$nativeTimers||$nativeTimerPolicy['can_edit'])$data .= '<span class="pointer pull-right unfinished-timesheet-delete" onclick="delete_user_unfinished_timesheet(' . $timer['id'] . '); return false;"><i class="fa-regular fa-trash-can"></i></span>';

    $data .= '<span class="tw-text-neutral-600">' . e($timerLabel('timer_top_started', $nativeTimers?$timer['started_at_label']:_dt($timer['start_time'], true))) . '</span><br /><span class="text-success tw-text-sm">' . $timerLabel('task_total_logged_time') . ' ' . e(($nativeTimers?\app\services\utilities\Format::secondsToTime($timer['total_seconds']):seconds_to_time_format($this->tasks_model->calc_task_total_time($timer['task_id'], ' AND staff_id=' . get_staff_user_id())))) . '</span>';

    if(!$nativeTimers||$nativeTimerPolicy['can_edit']){
    $data .= '<p class="mtop10"><a href="#" class="label label-danger top-stop-timer" ';

    if ($timer['task_id'] != '0') {
        $data .= 'data-toggle="popover" data-html="true" data-trigger="manual" data-title="' . $timerLabel('task_stop_timer') . '"';

        $data .= 'data-container="body" data-template="<div class=\'popover popover-top-timer-note\' role=\'tooltip\'><div class=\'arrow\'></div><h3 class=\'popover-title\'></h3><div class=\'popover-content\'></div></div>" ';

        $data .= 'data-content="';
        $data .= htmlspecialchars(render_textarea('timesheet_note'));
        $data .= '<button type=\'button\' onclick=\'timer_action(this,' . $timer['task_id'] . ',' . $timer['id'] . ');\' class=\'btn btn-primary btn-sm\'>' . $timerLabel('save') . '</button>" ';
        $data .= 'onclick="return false;">';
    } else {
        $data .= 'onclick=\'timer_action(this,' . $timer['task_id'] . ',' . $timer['id'] . '); return false;\' data-timer-id=\'' . $timer['id'] . '\'>';
    }

    $data .= '<i class="fa-regular fa-clock tw-mr-1"></i> ' . $timerLabel('task_stop_timer') . '</a>';
    $data .= '</p>';
    }
    $data .= '</li>';
    if ($i >= 0 && $i != $totalTimers - 1) {
        $data .= '<hr />';
    }
    echo $data;
    $i++;
}
// You can't start multiple blank timers
if ($nativeTimers?$nativeTimerPolicy['can_start_blank']:($noTimersWithoutTask
    && ! (get_option('auto_stop_tasks_timers_on_new_timer') == 1
        && total_rows(db_prefix() . 'taskstimers', 'staff_id=' . get_staff_user_id() . ' AND end_time IS NULL') > 0))) {
    echo '<button class="tw-mt-3 text-center btn btn-primary started-timers-button  btn-sm top-dropdown-btn" '.($nativeTimers?'data-policy-hash="'.e($nativeTimerPolicy['policy_hash']).'" ':'').'onclick="timer_action(this,0); return false;"><i class="fa-regular fa-clock"></i> ' . $timerLabel('task_start_timer') . '</button>';
}

if ($nativeTimers?$nativeTimerPolicy['can_view_all']:is_admin()) {
    echo '<div class="text-center tw-mt-1 view-all-timesheets">';
    echo '<a href="' . $timerUrl('staff/timesheets?view=all') . '" class="tw-text-sm">' . $timerLabel('view_members_timesheets') . '</a>';
    echo '</div>';
}
