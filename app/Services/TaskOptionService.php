<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Division;
use App\Models\Platform;
use App\Models\Task;
use App\Models\TaskCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class TaskOptionService
{
    /**
     * Get the master data a user needs to fill in the task form.
     *
     * @return array{
     *     divisions: list<array{id: int, name: string}>,
     *     categories: list<array{id: int, name: string, requires_review: bool, evidence_required: bool}>,
     *     platforms: list<array{id: int, name: string}>,
     *     priorities: list<array{value: string, label: string}>,
     *     statuses: list<array{value: string, label: string}>,
     * }
     */
    public function formOptions(Account $user): array
    {
        return [
            'divisions' => $this->divisionsFor($user)
                ->map(fn (Division $division) => ['id' => $division->id, 'name' => $division->name])
                ->all(),
            'categories' => TaskCategory::query()->orderBy('name')->get()
                ->map(fn (TaskCategory $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'requires_review' => $category->requires_review,
                    'evidence_required' => $category->evidence_required,
                ])
                ->all(),
            'platforms' => Platform::query()->orderBy('name')->get()
                ->map(fn (Platform $platform) => ['id' => $platform->id, 'name' => $platform->name])
                ->all(),
            'priorities' => $this->labelled(Task::PRIORITY_LABELS),
            'statuses' => $this->labelled(array_intersect_key(Task::STATUS_LABELS, array_flip(Task::INITIAL_STATUSES))),
        ];
    }

    /**
     * Get the accounts that can be assigned as PIC, optionally limited to one division.
     *
     * @return Collection<int, Account>
     */
    public function picOptions(?int $divisionId): Collection
    {
        return Account::query()
            ->assignableAsPic($divisionId)
            ->with('division')
            ->orderBy('fullname')
            ->get();
    }

    /**
     * Divisions the user may create tasks for.
     *
     * @return Collection<int, Division>
     */
    protected function divisionsFor(Account $user): Collection
    {
        return Division::query()
            ->when(! $user->isAdmin(), fn (Builder $query) => $query->whereKey($user->division_id))
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, string>  $labels
     * @return list<array{value: string, label: string}>
     */
    protected function labelled(array $labels): array
    {
        return collect($labels)
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }
}
