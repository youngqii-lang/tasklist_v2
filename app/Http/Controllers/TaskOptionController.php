<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListPicOptionsRequest;
use App\Models\Account;
use App\Models\Division;
use App\Models\Task;
use App\Services\TaskOptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TaskOptionController extends Controller
{
    /**
     * Return the master data for the task form.
     */
    public function index(Request $request, TaskOptionService $taskOptionService): JsonResponse
    {
        Gate::authorize('create', Task::class);

        return response()->json(['data' => $taskOptionService->formOptions($request->user())]);
    }

    /**
     * Return the accounts that can be chosen as PIC for a division.
     */
    public function pics(ListPicOptionsRequest $request, TaskOptionService $taskOptionService): JsonResponse
    {
        $divisionId = $request->integer('division_id');

        Gate::authorize('create', [Task::class, Division::findOrFail($divisionId)]);

        $pics = $taskOptionService->picOptions($divisionId)->map(fn (Account $account) => [
            'id' => $account->id,
            'fullname' => $account->fullname,
            'username' => $account->username,
            'division' => $account->division ? ['id' => $account->division->id, 'name' => $account->division->name] : null,
        ]);

        return response()->json(['data' => $pics]);
    }
}
