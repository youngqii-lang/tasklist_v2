<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Division;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TasklistController extends Controller
{
    /**
     * Show the Tasklist page. Its Board, Table, Timeline and Calendar views load their data from the Tasklist API.
     */
    public function __invoke(Request $request): View
    {
        Gate::authorize('viewAny', Task::class);

        $user = $request->user();

        return view('tasks.index', [
            'config' => [
                'user' => ['id' => $user->id, 'is_admin' => $user->isAdmin()],
                'can_create' => $user->can('create', Task::class),
                'divisions' => Division::query()->orderBy('name')->get(['id', 'name']),
                'pics' => Account::query()->assignableAsPic()->with('division')->orderBy('fullname')->get()
                    ->map(fn (Account $account) => [
                        'id' => $account->id,
                        'fullname' => $account->fullname,
                        'division' => $account->division?->name,
                    ]),
                'statuses' => $this->options(Task::STATUS_LABELS),
                'priorities' => $this->options(Task::PRIORITY_LABELS),
                'urls' => [
                    'tasks' => route('api.tasks.index'),
                    'summary' => route('api.tasks.summary'),
                    'options' => route('api.task-options.index'),
                    'pics' => route('api.task-options.pics'),
                    'timeline' => route('api.timeline'),
                    'calendar' => route('api.calendar.month'),
                    'calendar_deadlines' => route('api.calendar.deadlines'),
                ],
                'evidence' => [
                    'extensions' => config('tasklist.evidence.extensions'),
                    'max_size_kb' => config('tasklist.evidence.max_size_kb'),
                ],
            ],
        ]);
    }

    /**
     * @param  array<string, string>  $labels
     * @return list<array{value: string, label: string}>
     */
    protected function options(array $labels): array
    {
        return collect($labels)->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values()->all();
    }
}
