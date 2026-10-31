<?php

declare(strict_types=1);

require_once __DIR__ . '/NativeCompanyTaskIndexRead.php';
require_once __DIR__ . '/CompanyTasks.php';
require_once __DIR__ . '/CompanyStandaloneTasks.php';
require_once __DIR__ . '/NativeCompanyTaskFormValues.php';
require_once __DIR__ . '/NativeCompanyWorkAssignments.php';
require_once __DIR__ . '/NativeCompanyResourceTags.php';
require_once __DIR__ . '/CompanyTaskRecurring.php';

/** Source-aware facts used when composing the original secondary-company task table. */
final class NativeCompanyTaskIndexProjection
{
    public function __construct(private $db, private string $prefix) {}
    private function rows(string $query, array $args): array
    { return IssuerStorage::query($this->db, $query, $args)->result_array(); }

    private function item(int $issuer, int $actor, array $reference): array
    {
        if($reference['source_kind']==='task'){
            require_once __DIR__.'/NativeCompanyLegacyTaskProjection.php';
            return (new NativeCompanyLegacyTaskProjection($this->db,$this->prefix))->read($issuer,$actor,$reference);
        }
        $kind = match ($reference['source_kind']) {
            'company_task' => 'project',
            'company_standalone_task' => 'standalone',
            default => throw new DomainException('native_task_main_legacy_projection_required'),
        };
        $id = $reference['source_id'];
        $writer = $kind === 'project' ? new CompanyTasks($this->db, $this->prefix) : new CompanyStandaloneTasks($this->db, $this->prefix);
        $row = $writer->get($issuer, $actor, $id);
        $workflow = match ($row['status']) {
            'todo' => 1, 'awaiting_feedback' => 2, 'testing' => 3, 'in_progress' => 4, 'done' => 5,
            default => throw new DomainException('native_task_form_invalid'),
        };
        $fields = $row['native_fields_json'] === null
            ? ['start_date'=>null, 'billable'=>null, 'workflow_status'=>$workflow]
            : NativeCompanyTaskFormValues::normalize(json_decode($row['native_fields_json'], true, 32, JSON_THROW_ON_ERROR) + ['workflow_status'=>$workflow]);
        $assignments = (new NativeCompanyWorkAssignments($this->db, $this->prefix))->read($issuer, $actor, $kind === 'project' ? 'task' : 'standalone', $id);
        $tags = (new NativeCompanyResourceTags($this->db, $this->prefix))->read($issuer, $actor, 'task', $reference['id']);
        $followerTable = $kind === 'project' ? 'company_task_followers' : 'company_standalone_followers';
        $followers = $this->rows('SELECT staff_id FROM ' . $this->prefix . $followerTable . ' WHERE issuer_id=? AND task_id=? ORDER BY staff_id', [$issuer, $id]);
        $schedules = $this->rows('SELECT * FROM ' . $this->prefix . 'company_task_schedules WHERE issuer_id=? AND kind=? AND source_id=? ORDER BY id', [$issuer, $kind, $id]);
        foreach ($schedules as $schedule) (new CompanyTaskRecurring($this->db, $this->prefix))->get($issuer, $actor, $kind, (int) $schedule['id']);
        $resolved = NativeCompanyResourceReferences::resolve($this->db, $this->prefix, $issuer, $actor, 'task', $reference['id']);
        if ($resolved['name'] !== $row['name']) {
            throw new DomainException('native_task_index_changed');
        }
        // Writers hydrate whole activity collections, but the index cannot
        // authorize individual comments or other staff's timesheet entries.
        unset($row['comments'],$row['timers']);
        return ['id'=>$reference['id'], 'source_kind'=>$reference['source_kind'], 'source_id'=>$id,
            'reference'=>$resolved,
            'row'=>$row, 'fields'=>$fields, 'assignees'=>$assignments['members'], 'assignment_revision'=>$assignments['revision'],
            'followers'=>array_map('intval', array_column($followers, 'staff_id')), 'tags'=>$tags['tags'], 'tag_revision'=>$tags['revision'], 'schedules'=>$schedules];
    }

    public function read(int $issuer, int $actor): array
    {
        return $this->capture($issuer, $actor, true);
    }
    /** Exact task projection for future composition of the original detail modal. */
    public function readOne(int $issuer,int $actor,$canonical):array
    {
        $reader=new NativeCompanyTasksRead($this->db,$this->prefix);
        $reference=$reader->relationOption($issuer,$actor,$canonical);
        if(!in_array($reference['source_kind'],['company_task','company_standalone_task'],true))throw new DomainException('native_task_legacy_detail_required');
        $item=$this->item($issuer,$actor,$reference);
        if($reader->relationOption($issuer,$actor,$canonical)!==$reference||$this->item($issuer,$actor,$reference)!==$item)throw new DomainException('native_task_index_changed');
        return$item;
    }
    /** The caller must compare another capture before any response is emitted. */
    public function captureForComparison(int $issuer, int $actor): array
    {
        return $this->capture($issuer, $actor, false);
    }
    private function capture(int $issuer, int $actor, bool $verify): array
    {
        $acl = new IssuerResourceAccess($this->db, $this->prefix);
        if (!$acl->can($issuer, $actor, 'tasks', 'view')) throw new DomainException('native_company_task_denied');
        $primary=$acl->primaryId();
        $index = new NativeCompanyTaskIndexRead($this->db, $this->prefix);
        $references = $verify ? $index->read($issuer, $actor) : $index->captureForComparison($issuer, $actor);
        $items = [];
        foreach ($references as $reference) $items[] = $this->item($issuer, $actor, $reference);
        if ($verify) {
            if ($index->read($issuer, $actor) !== $references) throw new DomainException('native_task_index_changed');
            foreach ($references as $key => $reference) {
                if ($this->item($issuer, $actor, $reference) !== $items[$key]) throw new DomainException('native_task_index_changed');
            }
        }
        if (!$acl->can($issuer, $actor, 'tasks', 'view') || $acl->primaryId() !== $primary) throw new DomainException('native_task_index_changed');
        return $items;
    }
}
