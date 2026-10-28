<?php

declare(strict_types=1);

require_once __DIR__.'/NativeCompanyTaskDetailRead.php';
require_once __DIR__.'/NativeCompanyTaskChecklistPresentation.php';
require_once __DIR__.'/NativeCompanyTasksRead.php';
require_once __DIR__.'/NativeCompanyTimerPolicy.php';
require_once __DIR__.'/NativeCompanyBlankTimers.php';
require_once __DIR__.'/TaskPriorityPresentation.php';
require_once __DIR__.'/WorkStatusPresentation.php';

/** Complete, issuer-owned input for the original task detail template. */
final class NativeCompanyTaskDetailPresentation
{
    public function __construct(private $db, private string $prefix)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) {
            throw new InvalidArgumentException('Invalid table prefix');
        }
    }

    private function rows(string $sql, array $args): array
    {
        return IssuerStorage::query($this->db, $sql, $args)->result_array();
    }

    public function read(int $issuer, int $actor, $canonical, ?int $clock = null, string $translate = '_l'): array
    {
        $clock ??= time();
        $detailReader = new NativeCompanyTaskDetailRead($this->db, $this->prefix);
        $checklistReader = new NativeCompanyTaskChecklistPresentation($this->db, $this->prefix);
        $taskReader = new NativeCompanyTasksRead($this->db, $this->prefix, date_default_timezone_get());

        $detail = $detailReader->read($issuer, $actor, $canonical);
        $checklist = $checklistReader->read($issuer, $actor, $canonical);
        $item = $detail['item'];
        $canonicalId = (string) $item['id'];

        $files = ['revision'=>(int) $item['row']['revision'], 'items'=>[]];
        $project = null;
        if ($item['source_kind'] === 'company_task') {
            require_once __DIR__.'/CompanyProjectExtras.php';
            $extras = new CompanyProjectExtras($this->db, $this->prefix);
            $files = $extras->files($issuer, $actor, 'task', (int) $item['source_id']);
            $project = NativeCompanyResourceReferences::resolve(
                $this->db,
                $this->prefix,
                $issuer,
                $actor,
                'project',
                NativeCompanyResourceId::encode('company_project', $item['row']['project_id']),
                'view'
            );
        }

        $started = $taskReader->startedTimers($issuer, $actor, $clock);
        $ownRunning = array_values(array_filter($started, static fn(array $timer): bool => (string) $timer['task_id'] === $canonicalId));
        if (count($ownRunning) > 1) {
            throw new DomainException('native_company_task_timer_invalid');
        }

        $policyReader = new NativeCompanyTimerPolicy($this->db, $this->prefix);
        $policy = $policyReader->read($issuer, $actor);
        $policy['policy_hash'] = NativeCompanyBlankTimers::policyHash($policy);

        $canTrack = false;
        if ($detail['permissions']['edit'] && (int) $item['fields']['workflow_status'] !== 5) {
            try {
                $writer = $item['source_kind'] === 'company_task'
                    ? new CompanyTasks($this->db, $this->prefix)
                    : new CompanyStandaloneTasks($this->db, $this->prefix);
                $candidate = $writer->nativeBlankTimerCandidate($issuer, $actor, (int) $item['source_id']);
                $canTrack = (int) $candidate['task']['revision'] === (int) $item['row']['revision'];
            } catch (DomainException) {
                $canTrack = false;
            }
        }

        $ids = [];
        foreach ($item['assignees'] as $member) $ids[IssuerPolicy::id($member['staffid'])] = true;
        foreach (array_merge($detail['comments'], $detail['timers']) as $row) {
            if (($row['actor_id'] ?? null) !== null) $ids[IssuerPolicy::id($row['actor_id'])] = true;
        }
        $names = [];
        $nameFrames = [];
        foreach (array_keys($ids) as $staffId) {
            $rows = $this->rows('SELECT staffid,firstname,lastname FROM '.$this->prefix.'staff WHERE staffid=?', [$staffId]);
            if (count($rows) !== 1) throw new DomainException('native_company_task_staff_missing');
            $nameFrames[$staffId] = $rows;
            $names[(string) $staffId] = trim($rows[0]['firstname'].' '.$rows[0]['lastname']);
        }

        $statuses = WorkStatusPresentation::tasks($translate);
        $status = null;
        foreach ($statuses as $candidate) if ((int) $candidate['id'] === (int) $item['fields']['workflow_status']) $status = $candidate;
        if ($status === null) throw new DomainException('native_work_status_unknown');
        $priorities = TaskPriorityPresentation::definitions($translate);
        $priority = null;
        foreach ($priorities as $candidate) if ((int) $candidate['id'] === (int) $item['row']['priority']) $priority = $candidate;
        if ($priority === null) throw new DomainException('native_task_priority_invalid');

        $result = [
            'detail'=>$detail,
            'checklist'=>$checklist,
            'files'=>$files,
            'project'=>$project,
            'names'=>$names,
            'statuses'=>$statuses,
            'status'=>$status,
            'priorities'=>$priorities,
            'priority'=>$priority,
            'running_timer'=>$ownRunning[0] ?? null,
            'timer_policy'=>$policy,
            'can_track_time'=>$canTrack,
            'clock'=>$clock,
        ];

        if ($detailReader->read($issuer, $actor, $canonical) !== $detail
            || $checklistReader->read($issuer, $actor, $canonical) !== $checklist
            || $taskReader->startedTimers($issuer, $actor, $clock) !== $started
            || $policyReader->read($issuer, $actor) !== array_diff_key($policy, ['policy_hash'=>true])) {
            throw new DomainException('native_task_detail_changed');
        }
        if ($item['source_kind'] === 'company_task'
            && (new CompanyProjectExtras($this->db, $this->prefix))->files($issuer, $actor, 'task', (int) $item['source_id']) !== $files) {
            throw new DomainException('native_task_detail_changed');
        }
        foreach ($nameFrames as $staffId => $rows) {
            if ($this->rows('SELECT staffid,firstname,lastname FROM '.$this->prefix.'staff WHERE staffid=?', [$staffId]) !== $rows) {
                throw new DomainException('native_task_detail_changed');
            }
        }
        return $result;
    }
}
