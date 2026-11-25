<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\Task\StoreTaskEvidenceRequest;
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
                'user' => [
                    'id' => $user->id,
                    'is_admin' => $user->isAdmin(),
                    'manages_all_divisions' => $user->managesAllDivisions(),
                ],
                'can_create' => $user->can('create', Task::class),
                'divisions' => Division::query()->orderBy('name')->get(['id', 'name']),
                'pics' => Account::query()->assignableAsPic()->with('division')->orderBy('fullname')->get()
                    ->map(fn (Account $account) => [
                        'id' => $account->id,
                        'fullname' => $account->fullname,
                        'division' => $account->division?->name,
                    ]),
                'statuses' => collect(TaskStatus::cases())->map(fn (TaskStatus $status) => ['value' => $status->value, 'label' => $status->label()])->all(),
                'priorities' => collect(TaskPriority::cases())->map(fn (TaskPriority $priority) => ['value' => $priority->value, 'label' => $priority->label()])->all(),
                'urls' => [
                    'tasks' => route('api.tasks.index'),
                    'meta' => route('api.tasks.meta'),
                    'summary' => route('api.tasks.monitoring'),
                    'options' => route('api.tasks.create-options'),
                    'timeline' => route('api.tasks.schedule'),
                    'calendar' => route('api.tasks.calendar'),
                ],
                'evidence' => [
                    'extensions' => StoreTaskEvidenceRequest::MIMES,
                    'max_size_kb' => StoreTaskEvidenceRequest::maxKilobytes(),
                ],
            ],
        ]);
    }
}
