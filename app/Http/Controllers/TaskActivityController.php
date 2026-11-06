<?php

namespace App\Http\Controllers;

use App\Http\Resources\TaskActivityResource;
use App\Models\Task;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TaskActivityController extends Controller
{
    /**
     * List the activity history of a task, newest first. The history is read-only.
     */
    public function index(Task $task): AnonymousResourceCollection
    {
        Gate::authorize('view', $task);

        return TaskActivityResource::collection($task->activities()->with('account')->latest()->latest('id')->get());
    }
}
