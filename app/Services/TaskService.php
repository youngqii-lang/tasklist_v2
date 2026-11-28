<?php

namespace App\Services;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Exceptions\DuplicateTaskSubmissionException;
use App\Exceptions\TaskVersionConflictException;
use App\Models\Account;
use App\Models\Division;
use App\Models\Platform;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskCategory;
use App\Repositories\TaskRepository;
use App\Support\TaskPeriod;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class TaskService
{
    /**
     * How long an identical submission from the same account is treated as a resend.
     */
    public const DUPLICATE_WINDOW_SECONDS = 10;

    public function __construct(private TaskRepository $tasks) {}

    /**
     * Tasks for the Board and Table views, plus per-status counts under the same filters.
     *
     * @param  array{scope?: string, search: ?string, division: ?string, priority: ?string, status: ?string, pic_id?: ?int, period?: ?TaskPeriod}  $filters
     * @return array{tasks: Collection<int, Task>|LengthAwarePaginator, status_counts: array<string, int>}
     */
    public function list(Account $account, array $filters, string $sort, string $direction, ?int $perPage): array
    {
        $query = $this->tasks->visible($account, $filters);
        $statusCounts = $this->tasks->countByStatus($query);

        $query = $this->tasks->sort($query, $sort, $direction)->with(['division', 'collaboratingDivisions', 'pics'])->withCount('evidences');

        return [
            'tasks' => $perPage ? $query->paginate($perPage)->withQueryString() : $query->get(),
            'status_counts' => $statusCounts,
        ];
    }

    /**
     * The task as the Calendar pop-up shows it, reloaded so the summary is never older than the database.
     */
    public function preview(Task $task): Task
    {
        return $task->refresh()->load(['division', 'collaboratingDivisions', 'pics'])->loadCount('evidences');
    }

    public function detail(Task $task): Task
    {
        return $task->load([
            'category', 'division', 'collaboratingDivisions', 'platform', 'pics', 'creator', 'activities.actor', 'evidences.submitter', 'reviews.reviewer',
            'assignments.account', 'assignments.assigner', 'assignments.unassigner',
        ])->loadCount('evidences');
    }

    /**
     * Create a task from validated input. The creator is the logged-in account, the status starts at
     * Back Log unless To Do is chosen, and every task requires review and evidence. Each chosen PIC gets an active
     * assignment made by the creator, and the PICs' divisions become collaborating divisions next to the chosen ones.
     *
     * @param  array<string, mixed>  $data
     *
     * The duplicate guard is an atomic cache lock that is kept for the duplicate window, so of two identical
     * requests arriving at the same time only one acquires it. The cache store must support atomic locks
     * (database, redis, memcached) and be shared by every app server; the default database store is.
     *
     * @throws DuplicateTaskSubmissionException When the same account sent the same task moments ago.
     */
    public function create(Account $creator, array $data): Task
    {
        $picIds = $this->picIds($data['pic_ids'] ?? []);
        $collaboratorIds = $this->picIds($data['collaborating_division_ids'] ?? []);
        unset($data['pic_ids'], $data['collaborating_division_ids']);

        $attributes = [
            ...$data,
            'creator_id' => $creator->id,
            'priority' => $data['priority'] ?? TaskPriority::Medium->value,
            'status' => $data['status'] ?? TaskStatus::Backlog->value,
            'deadline_time' => isset($data['deadline']) ? ($data['deadline_time'] ?? null) : null,
            'requires_review' => true,
            'evidence_required' => true,
        ];

        $lock = Cache::lock(
            $this->submissionFingerprint([
                ...$attributes,
                'pic_ids' => $picIds->sort()->values()->all(),
                'collaborating_division_ids' => $collaboratorIds->sort()->values()->all(),
            ]),
            self::DUPLICATE_WINDOW_SECONDS,
        );

        if (! $lock->get()) {
            throw new DuplicateTaskSubmissionException;
        }

        try {
            $task = DB::transaction(function () use ($creator, $attributes, $picIds, $collaboratorIds) {
                $task = Task::create($attributes);
                $now = now();

                foreach ($picIds as $picId) {
                    $task->assignments()->create(['account_id' => $picId, 'assigned_by' => $creator->id, 'assigned_at' => $now]);
                }

                $this->syncCollaborators($task, $creator, $this->collaboratorTarget($task, $collaboratorIds->all()));

                $task->activities()->create(['account_id' => $creator->id, 'action' => TaskActivity::CREATED]);

                return $task;
            });
        } catch (Throwable $e) {
            $lock->release();

            throw $e;
        }

        return $this->detail($task);
    }

    /**
     * Apply an edit from an Admin, the division's Manager, or the task's PIC (status only). Only fields whose value actually changes are
     * checked against the task's current condition, so a form that resends unchanged values still saves.
     *
     * The edit must carry the version it was based on; the row is locked while that is compared, so of two
     * edits based on the same version only the first is saved and the second gets a conflict instead of
     * silently overwriting it.
     *
     * @param  array<string, mixed>  $data  Validated input including version.
     *
     * @throws TaskVersionConflictException When the task was saved by someone else after the editor loaded it.
     * @throws ValidationException When a change breaks the field, workflow, assignment, or review rules.
     */
    public function update(Account $editor, Task $task, array $data): Task
    {
        $baseVersion = (int) $data['version'];
        $chosenCollaborators = array_key_exists('collaborating_division_ids', $data) ? ($data['collaborating_division_ids'] ?? []) : null;
        unset($data['version'], $data['collaborating_division_ids']);

        DB::transaction(function () use ($editor, $task, $data, $baseVersion, $chosenCollaborators) {
            $locked = Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

            if ($locked->version !== $baseVersion) {
                throw new TaskVersionConflictException($locked->version);
            }

            $lockedFields = $locked->lockedFieldsFor($editor);
            // The move is judged on the task as it was loaded, before the edit changes its review or PIC.
            $statusDenial = $this->statusDenial($editor, $locked, $data['status'] ?? null);

            $locked->fill($data);

            if ($locked->deadline === null) {
                $locked->deadline_time = null;
            }

            $this->rejectLockedChanges($locked, $lockedFields);

            if ($chosenCollaborators !== null && isset($lockedFields['collaborating_division_ids'])) {
                throw ValidationException::withMessages(['collaborating_division_ids' => $lockedFields['collaborating_division_ids']]);
            }

            $collaborators = $this->collaboratorTarget($locked, $chosenCollaborators);

            if (! $locked->isDirty() && $collaborators->all() === $this->collaboratorIds($locked)->all()) {
                return;
            }

            $this->guardChanges($locked, $statusDenial, $lockedFields);

            if ($locked->isDirty('status')) {
                $locked->completed_at = $locked->status === TaskStatus::Done ? now() : null;
            }

            $resubmitted = $locked->isDirty('status') && $locked->resubmitsTo($locked->status);
            $changes = $this->describeChanges($locked);

            $locked->version = $baseVersion + 1;
            $locked->save();

            $changes += $this->syncCollaborators($locked, $editor, $collaborators);

            $locked->activities()->create([
                'account_id' => $editor->id,
                'action' => $resubmitted ? TaskActivity::RESUBMITTED : TaskActivity::UPDATED,
                'changes' => $changes,
            ]);
        });

        return $this->detail($task->refresh());
    }

    /**
     * Delete the task (soft delete): it leaves every list, board, calendar, timeline, and count, while its history,
     * evidence, reviews, and assignments stay stored. Who deleted it is kept in deleted_by and in the history. A Done
     * task cannot be deleted.
     *
     * @param  array{version: int|string}  $data
     *
     * @throws TaskVersionConflictException When the task was saved by someone else after the actor loaded it.
     * @throws ValidationException When the task is already Done.
     */
    public function delete(Account $actor, Task $task, array $data): void
    {
        $baseVersion = (int) $data['version'];

        DB::transaction(function () use ($actor, $task, $baseVersion) {
            $locked = Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

            if ($locked->version !== $baseVersion) {
                throw new TaskVersionConflictException($locked->version);
            }

            if ($locked->status === TaskStatus::Done) {
                throw ValidationException::withMessages(['status' => 'Tugas yang sudah selesai tidak dapat dihapus.']);
            }

            $locked->deleted_by = $actor->id;
            $locked->version = $baseVersion + 1;
            $locked->save();
            $locked->delete();

            $locked->activities()->create([
                'account_id' => $actor->id,
                'action' => TaskActivity::DELETED,
                'changes' => null,
            ]);
        });
    }

    /**
     * Move the task to another status following the transition matrix: an active PIC, Admin, a Manager, or the
     * division's Supervisor moves it from Back Log up to Ready Review; only those reviewers start the review
     * (Ready Review → On Review) or finish a task that does not require review (On Process → Done). The decision
     * that ends a review is made through TaskReviewService. The change is appended to the history.
     *
     * @param  array{version: int|string, status: string}  $data
     *
     * @throws TaskVersionConflictException When the task was saved by someone else after the actor loaded it.
     * @throws ValidationException When the move is not in the matrix, belongs to another role, still needs
     *                             evidence, or the task has no PIC.
     */
    public function changeStatus(Account $actor, Task $task, array $data): Task
    {
        $baseVersion = (int) $data['version'];

        DB::transaction(function () use ($actor, $task, $data, $baseVersion) {
            $locked = Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

            if ($locked->version !== $baseVersion) {
                throw new TaskVersionConflictException($locked->version);
            }

            $status = TaskStatus::from($data['status']);

            if ($locked->status === $status) {
                throw ValidationException::withMessages(['status' => "Tugas sudah berstatus {$status->label()}."]);
            }

            if ($denial = $locked->statusChangeDenialFor($actor, $status)) {
                throw ValidationException::withMessages(['status' => $denial]);
            }

            if (! $locked->hasActivePic()) {
                throw ValidationException::withMessages(['pic_ids' => 'Tetapkan PIC sebelum memindahkan tugas dari Back Log.']);
            }

            $resubmitted = $locked->resubmitsTo($status);

            $locked->status = $status;
            $locked->completed_at = $status === TaskStatus::Done ? now() : null;

            $changes = $this->describeChanges($locked);

            $locked->version = $baseVersion + 1;
            $locked->save();

            $locked->activities()->create([
                'account_id' => $actor->id,
                'action' => $resubmitted ? TaskActivity::RESUBMITTED : TaskActivity::STATUS_CHANGED,
                'changes' => $changes,
            ]);
        });

        return $this->detail($task->refresh());
    }

    /**
     * Set the task's PICs to the given list, and optionally forward it to another division (Admin only). Only the
     * difference is applied: PICs missing from the list are released (their row is kept with who released them and
     * when), new ones get an active assignment, and PICs that stay are not touched. The status is left as it is.
     *
     * The task row is locked for the whole change, so two assignment requests run one after the other; the unique
     * index on active assignments is the last guard against an account being an active PIC twice.
     *
     * @param  array{version: int|string, pic_ids: array<int, int|string>, division_id?: int|string, note?: ?string}  $data
     *
     * @throws TaskVersionConflictException When the task was saved by someone else after the actor loaded it.
     * @throws ValidationException When the actor may not assign or forward, the PICs are locked by the task's status,
     *                             nothing would change, or a task past Back Log would be left without a PIC.
     */
    public function assign(Account $actor, Task $task, array $data): Task
    {
        $baseVersion = (int) $data['version'];
        $picIds = $this->picIds($data['pic_ids']);

        DB::transaction(function () use ($actor, $task, $data, $baseVersion, $picIds) {
            $locked = Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

            if ($locked->version !== $baseVersion) {
                throw new TaskVersionConflictException($locked->version);
            }

            if ($reason = $locked->assignmentLockFor($actor)) {
                throw ValidationException::withMessages(['pic_ids' => $reason]);
            }

            $active = $locked->activeAssignments()->get()->keyBy('account_id');
            $released = $active->keys()->diff($picIds)->values();
            $added = $picIds->diff($active->keys())->values();
            $divisionId = (int) ($data['division_id'] ?? $locked->division_id);
            $forwarded = $divisionId !== $locked->division_id;

            if ($forwarded && ! $locked->forwardableBy($actor)) {
                throw ValidationException::withMessages(['division_id' => 'Hanya Admin dan Manajer yang dapat memindahkan tugas ke divisi lain.']);
            }

            if (! $forwarded && $added->isEmpty() && $released->isEmpty()) {
                throw ValidationException::withMessages(['pic_ids' => 'Tidak ada perubahan PIC.']);
            }

            if ($picIds->isEmpty() && $locked->status !== TaskStatus::Backlog) {
                throw ValidationException::withMessages(['pic_ids' => 'PIC wajib diisi untuk tugas yang tidak berstatus Back Log.']);
            }

            $changes = [];

            if ($added->isNotEmpty() || $released->isNotEmpty()) {
                $changes['pics'] = ['old' => $this->picReferences($active->keys()), 'new' => $this->picReferences($picIds)];
            }

            $now = now();

            foreach ($released as $accountId) {
                $active[$accountId]->update(['unassigned_by' => $actor->id, 'unassigned_at' => $now]);
            }

            foreach ($added as $accountId) {
                $locked->assignments()->create([
                    'account_id' => $accountId,
                    'assigned_by' => $actor->id,
                    'assigned_at' => $now,
                    'note' => $data['note'] ?? null,
                ]);
            }

            $locked->division_id = $divisionId;
            $changes += $this->describeChanges($locked);

            $locked->version = $baseVersion + 1;
            $locked->save();

            // New PICs bring their divisions along; a forwarded task's new owner stops being a collaborator.
            $changes += $this->syncCollaborators($locked, $actor, $this->collaboratorTarget($locked, null));

            $locked->activities()->create([
                'account_id' => $actor->id,
                'action' => match (true) {
                    $added->isNotEmpty() && $released->isNotEmpty() => TaskActivity::REASSIGNED,
                    $added->isNotEmpty() => TaskActivity::ASSIGNED,
                    $released->isNotEmpty() => TaskActivity::UNASSIGNED,
                    default => TaskActivity::UPDATED,
                },
                'changes' => $changes,
            ]);
        });

        return $this->detail($task->refresh());
    }

    /**
     * Choices for the Assign PIC form: the current PICs, the divisions the task may be forwarded to (every division
     * for Admin and Managers, otherwise only its own), the accounts the actor may add as PIC, and the reason the PICs
     * cannot be changed right now, if any.
     *
     * @return array<string, mixed>
     */
    public function assignmentOptions(Account $actor, Task $task): array
    {
        $task->loadMissing(['pics', 'division']);

        return [
            'current_pics' => $task->pics->map(fn (Account $pic) => $pic->only(['id', 'fullname']))->values()->all(),
            'current_division' => $task->division->only(['id', 'code_division', 'name']),
            'can_change_division' => $task->forwardableBy($actor),
            'locked_reason' => $task->assignmentLockFor($actor),
            'divisions' => Division::query()
                ->unless($task->forwardableBy($actor), fn (Builder $query) => $query->whereKey($task->division_id))
                ->orderBy('name')
                ->get(['id', 'code_division', 'name'])
                ->map(fn (Division $division) => $division->only(['id', 'code_division', 'name']))
                ->all(),
            'pics' => $this->picOptions($actor),
        ];
    }

    /**
     * Options for the Create Task form: the divisions the account may create tasks for, the accounts it may assign as
     * PIC, task categories with their review configuration, platforms, and enums.
     *
     * @return array<string, mixed>
     */
    public function createOptions(Account $account): array
    {
        return [
            'divisions' => Division::query()
                ->taskCreatableBy($account)
                ->orderBy('name')
                ->get(['id', 'code_division', 'name'])
                ->map(fn (Division $division) => $division->only(['id', 'code_division', 'name']))
                ->all(),
            'pics' => $this->picOptions($account),
            // Any division may collaborate on a task, also one the account cannot create tasks for.
            'collaborator_divisions' => Division::query()
                ->orderBy('name')
                ->get(['id', 'code_division', 'name'])
                ->map(fn (Division $division) => $division->only(['id', 'code_division', 'name']))
                ->all(),
            'categories' => TaskCategory::query()
                ->orderBy('name')
                ->get(['id', 'name', 'requires_review', 'evidence_required'])
                ->map(fn (TaskCategory $category) => $category->only(['id', 'name', 'requires_review', 'evidence_required']))
                ->all(),
            'platforms' => Platform::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Platform $platform) => $platform->only(['id', 'name']))
                ->all(),
            'priorities' => collect(TaskPriority::cases())
                ->map(fn (TaskPriority $priority) => ['value' => $priority->value, 'label' => $priority->label()])
                ->all(),
            'statuses' => collect(TaskStatus::initial())
                ->map(fn (TaskStatus $status) => ['value' => $status->value, 'label' => $status->label()])
                ->all(),
            'defaults' => [
                'priority' => TaskPriority::Medium->value,
                'status' => TaskStatus::Backlog->value,
            ],
        ];
    }

    /**
     * Every account the assigner may add as PIC (Admin and Managers: every division; a Supervisor: their own), with
     * its role and division so the form can group them.
     *
     * @return array<int, array<string, mixed>>
     */
    private function picOptions(Account $assigner): array
    {
        return Account::query()
            ->assignableAsPicBy($assigner)
            ->with(['role', 'division'])
            ->orderBy('fullname')
            ->orderBy('id')
            ->get()
            ->map(fn (Account $pic) => [
                'id' => $pic->id,
                'fullname' => $pic->fullname,
                'role' => $pic->role->code_role,
                'division' => $pic->division?->only(['id', 'code_division', 'name']),
            ])
            ->all();
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return SupportCollection<int, int>
     */
    private function picIds(array $ids): SupportCollection
    {
        return collect($ids)->map(fn (int|string $id) => (int) $id)->unique()->values();
    }

    /**
     * PICs as they are recorded in the history: id and name at the time of the change, by name.
     *
     * @param  SupportCollection<int, int>  $ids
     * @return array<int, array{id: int, name: string}>
     */
    private function picReferences(SupportCollection $ids): array
    {
        return Account::query()
            ->whereKey($ids->all())
            ->orderBy('fullname')
            ->orderBy('id')
            ->get(['id', 'fullname'])
            ->map(fn (Account $pic) => ['id' => $pic->id, 'name' => $pic->fullname])
            ->all();
    }

    /**
     * The task's collaborating division ids as they are stored, in ascending order.
     *
     * @return SupportCollection<int, int>
     */
    private function collaboratorIds(Task $task): SupportCollection
    {
        return $task->collaboratingDivisions()->pluck('divisions.id')->map(fn (int|string $id) => (int) $id)->sort()->values();
    }

    /**
     * The collaborating divisions the task should have: the chosen ones (or the current ones when none are sent) plus
     * the divisions of its active PICs, never its owning division. In ascending order.
     *
     * @param  array<int, int|string>|null  $chosenIds
     * @return SupportCollection<int, int>
     */
    private function collaboratorTarget(Task $task, ?array $chosenIds): SupportCollection
    {
        $picDivisionIds = Account::query()
            ->whereIn('id', $task->activeAssignments()->select('account_id'))
            ->whereNotNull('division_id')
            ->pluck('division_id');

        return collect($chosenIds ?? $this->collaboratorIds($task)->all())
            ->merge($picDivisionIds)
            ->map(fn (int|string $id) => (int) $id)
            ->unique()
            ->reject(fn (int $id) => $id === (int) $task->division_id)
            ->sort()
            ->values();
    }

    /**
     * Store the given collaborating divisions, recording who added the new ones, and describe the change for the
     * history (nothing when they stay the same).
     *
     * @param  SupportCollection<int, int>  $target
     * @return array<string, array{old: array<int, array{id: int, name: string}>, new: array<int, array{id: int, name: string}>}>
     */
    private function syncCollaborators(Task $task, Account $actor, SupportCollection $target): array
    {
        $current = $this->collaboratorIds($task);

        if ($target->all() === $current->all()) {
            return [];
        }

        $task->collaboratingDivisions()->detach($current->diff($target)->all());

        foreach ($target->diff($current) as $divisionId) {
            $task->collaboratingDivisions()->attach($divisionId, ['added_by' => $actor->id]);
        }

        return ['collaborating_divisions' => [
            'old' => $this->divisionReferences($current),
            'new' => $this->divisionReferences($target),
        ]];
    }

    /**
     * Divisions as they are recorded in the history: id and name at the time of the change, by name.
     *
     * @param  SupportCollection<int, int>  $ids
     * @return array<int, array{id: int, name: string}>
     */
    private function divisionReferences(SupportCollection $ids): array
    {
        return Division::query()
            ->whereKey($ids->all())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Division $division) => ['id' => $division->id, 'name' => $division->name])
            ->all();
    }

    /**
     * Identify a submission by what would be saved, not by how it was sent: key order, an omitted field
     * versus an empty one, "5" versus 5, and an explicit default versus none all give the same fingerprint.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function submissionFingerprint(array $attributes): string
    {
        $normalized = collect($attributes)
            ->reject(fn (mixed $value) => $value === null || $value === '')
            ->map(fn (mixed $value) => is_string($value) && ctype_digit($value) ? (int) $value : $value)
            ->sortKeys()
            ->all();

        return 'task-create:'.sha1(json_encode($normalized));
    }

    /**
     * Why the editor may not move the task, as it was before the edit, to the sent status; null when no
     * other status is sent or the move is allowed.
     */
    private function statusDenial(Account $editor, Task $task, ?string $status): ?string
    {
        if ($status === null || $status === $task->status->value) {
            return null;
        }

        return $task->statusChangeDenialFor($editor, TaskStatus::from($status));
    }

    /**
     * @param  array<string, string>  $lockedFields
     *
     * @throws ValidationException
     */
    private function rejectLockedChanges(Task $task, array $lockedFields): void
    {
        $errors = array_intersect_key($lockedFields, $task->getDirty());

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  ?string  $statusDenial  Why the sent status change is not allowed, if it is not.
     * @param  array<string, string>  $lockedFields  Fields locked in the task's condition before the edit, with the reason.
     *
     * @throws ValidationException
     */
    private function guardChanges(Task $task, ?string $statusDenial, array $lockedFields): void
    {
        $this->rejectLockedChanges($task, $lockedFields);

        if ($statusDenial !== null) {
            throw ValidationException::withMessages(['status' => $statusDenial]);
        }

        if ($task->isDirty('status') && $task->status !== TaskStatus::Backlog && ! $task->hasActivePic()) {
            throw ValidationException::withMessages(['pic_ids' => 'Tetapkan PIC sebelum memindahkan tugas dari Back Log.']);
        }
    }

    /**
     * Old and new value of each changed field. Related records are kept with their name at the time
     * of the change, so the history stays readable after they are renamed.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function describeChanges(Task $task): array
    {
        $relations = [
            'category_id' => ['category', TaskCategory::class, 'name'],
            'division_id' => ['division', Division::class, 'name'],
            'platform_id' => ['platform', Platform::class, 'name'],
        ];

        return collect($task->getDirty())
            ->keys()
            ->reject(fn (string $field) => $field === 'completed_at')
            ->mapWithKeys(function (string $field) use ($task, $relations) {
                if (isset($relations[$field])) {
                    [$name, $model, $column] = $relations[$field];

                    return [$name => [
                        'old' => $this->reference($model, $column, $task->getOriginal($field)),
                        'new' => $this->reference($model, $column, $task->{$field}),
                    ]];
                }

                if ($field === 'deadline_time') {
                    $original = $task->getRawOriginal($field);

                    return [$field => ['old' => $original === null ? null : substr($original, 0, 5), 'new' => $task->deadline_time]];
                }

                return [$field => [
                    'old' => $this->plain($task->getOriginal($field)),
                    'new' => $this->plain($task->{$field}),
                ]];
            })
            ->all();
    }

    /**
     * @param  class-string<Model>  $model
     * @return array{id: int, name: ?string}|null
     */
    private function reference(string $model, string $column, mixed $id): ?array
    {
        return $id === null ? null : ['id' => (int) $id, 'name' => $model::whereKey($id)->value($column)];
    }

    private function plain(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof CarbonInterface => $value->toDateString(),
            default => $value,
        };
    }

    /**
     * Role, division scope, and filter options the Tasklist page needs for the logged-in account.
     *
     * @return array<string, mixed>
     */
    public function meta(Account $account): array
    {
        $account->loadMissing(['division', 'role.permissions']);

        return [
            'role' => match (true) {
                $account->isAdmin() => 'admin',
                $account->isManager() => 'manager',
                default => 'staff',
            },
            // The account's own role code (ADM, MGR, SPV, EMP, FRL) and permission codes, so the page can tell
            // Supervisor and Freelance apart from Staff. role above keeps its three values for existing callers.
            'role_code' => $account->role?->code_role,
            'permissions' => $account->permissionCodes(),
            'can_create' => $account->can('create', Task::class),
            'division' => $account->division?->code_division,
            // Admin starts on "Semua Divisi" (null); a Manager starts on the division they manage.
            'default_division' => $account->isManager() ? $account->division?->code_division : null,
            'divisions' => $this->divisionOptions($account),
            'pics' => $this->picFilterOptions($account),
            'statuses' => collect(TaskStatus::cases())
                ->map(fn (TaskStatus $status) => ['value' => $status->value, 'label' => $status->label()])
                ->all(),
            'priorities' => collect(TaskPriority::cases())
                ->map(fn (TaskPriority $priority) => ['value' => $priority->value, 'label' => $priority->label()])
                ->all(),
        ];
    }

    /**
     * PICs the account may filter the Tasklist by: the active PICs of the tasks it may see. It follows the same
     * visibility as the list, so it names no account the list would not already show as a PIC.
     *
     * @return array<int, array{id: int, fullname: string}>
     */
    private function picFilterOptions(Account $account): array
    {
        return Account::query()
            ->whereHas('taskAssignments', fn (Builder $query) => $query
                ->whereNull('unassigned_at')
                ->whereHas('task', fn (Builder $query) => $query->visibleTo($account)))
            ->orderBy('fullname')
            ->orderBy('id')
            ->get(['id', 'fullname'])
            ->map(fn (Account $pic) => $pic->only(['id', 'fullname']))
            ->all();
    }

    /**
     * Divisions the account may filter by: every division, since every active account sees the tasks of every
     * division.
     *
     * @return array<int, array{code_division: string, name: string}>
     */
    private function divisionOptions(Account $account): array
    {
        return Division::query()
            ->unless($account->is_active, fn (Builder $query) => $query->whereKey(0))
            ->orderBy('name')
            ->get(['code_division', 'name'])
            ->map(fn (Division $division) => $division->only(['code_division', 'name']))
            ->all();
    }
}
