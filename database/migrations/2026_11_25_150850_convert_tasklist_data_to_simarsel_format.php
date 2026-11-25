<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Review decisions before and after.
     *
     * @var array<string, string>
     */
    private const DECISIONS = [
        'approved' => 'approve',
        'revision_requested' => 'revision',
    ];

    /**
     * History actions that were renamed.
     *
     * @var array<string, string>
     */
    private const ACTIONS = [
        'pic_changed' => 'reassigned',
        'evidence_added' => 'evidence_submitted',
        'submitted' => 'status_changed',
    ];

    /**
     * Permissions replaced by task.update, task.delete, and task.change_status.
     *
     * @var list<string>
     */
    private const OBSOLETE_PERMISSIONS = ['task.update_all', 'task.update_assigned'];

    /**
     * Bring the stored review decisions, history entries, and permissions to the format SiMarsel reads: history changes
     * as {old, new} pairs, references by name, and the review note under changes.review.
     */
    public function up(): void
    {
        foreach (self::DECISIONS as $from => $to) {
            DB::table('task_reviews')->where('decision', $from)->update(['decision' => $to]);
        }

        DB::table('permissions')->whereIn('code', self::OBSOLETE_PERMISSIONS)->delete();

        DB::table('task_activities')->orderBy('id')->chunkById(500, function ($activities) {
            foreach ($activities as $activity) {
                $action = self::ACTIONS[$activity->action] ?? $activity->action;
                $changes = $this->convertChanges($action, json_decode((string) $activity->changes, true) ?? []);

                DB::table('task_activities')->where('id', $activity->id)->update([
                    'action' => $action,
                    'changes' => $changes === null ? null : json_encode($changes),
                ]);
            }
        });
    }

    /**
     * The conversion cannot be undone: the old format kept less detail apart (for example which PICs were added or
     * released), so the data is left as it is.
     */
    public function down(): void
    {
        foreach (self::DECISIONS as $from => $to) {
            DB::table('task_reviews')->where('decision', $to)->update(['decision' => $from]);
        }
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>|null
     */
    private function convertChanges(string $action, array $changes): ?array
    {
        if ($action === 'created') {
            return null;
        }

        $converted = [];

        foreach ($changes as $field => $change) {
            $converted[$field] = match (true) {
                $field === 'evidence' => [$action === 'evidence_deleted' ? 'old' : 'new' => $this->references($change)],
                $field === 'note' => null,
                is_array($change) && array_key_exists('from', $change) => [
                    'old' => $this->references($change['from']),
                    'new' => $this->references($change['to'] ?? null),
                ],
                default => $change,
            };
        }

        if (in_array($action, ['approved', 'revision_requested'], true) && filled($changes['note'] ?? null)) {
            $converted['review'] = ['new' => ['id' => null, 'cycle' => null, 'note' => $changes['note']]];
        }

        return array_filter($converted, fn (mixed $change) => $change !== null);
    }

    /**
     * Name a referenced account or record by `name`, as SiMarsel does, instead of `fullname`.
     */
    private function references(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item) => $this->references($item), $value);
        }

        if (array_key_exists('fullname', $value)) {
            $value['name'] = $value['fullname'];
            unset($value['fullname']);
        }

        return $value;
    }
};
