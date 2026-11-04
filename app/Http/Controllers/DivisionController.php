<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDivisionRequest;
use App\Http\Requests\UpdateDivisionRequest;
use App\Models\Account;
use App\Models\Division;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DivisionController extends Controller
{
    /**
     * Display the division list, optionally filtered by a search keyword.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Division::class);

        $search = trim($request->string('search'));

        $divisions = Division::query()
            ->withCount('accounts')
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $query) => $query
                    ->whereLike('name', "%{$search}%")
                    ->orWhereLike('code_division', "%{$search}%"),
            ))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('divisions.index', [
            'totalDivisions' => Division::count(),
            'accountsWithDivision' => Account::whereNotNull('division_id')->count(),
            'accountsWithoutDivision' => Account::whereNull('division_id')->count(),
            'emptyDivisions' => Division::doesntHave('accounts')->count(),
            'divisions' => $divisions,
            'search' => $search,
        ]);
    }

    /**
     * Show the form for creating a new division.
     */
    public function create(): View
    {
        Gate::authorize('create', Division::class);

        return view('divisions.create');
    }

    /**
     * Store a newly created division.
     */
    public function store(StoreDivisionRequest $request): RedirectResponse
    {
        $division = Division::create($request->safe()->only(['code_division', 'name']));

        return redirect()
            ->route('divisions.index')
            ->with('status', "Divisi {$division->name} berhasil dibuat.");
    }

    /**
     * Show the form for editing the specified division.
     */
    public function edit(Division $division): View
    {
        Gate::authorize('update', $division);

        return view('divisions.edit', ['division' => $division]);
    }

    /**
     * Update the specified division.
     */
    public function update(UpdateDivisionRequest $request, Division $division): RedirectResponse
    {
        $division->update($request->safe()->only(['code_division', 'name']));

        return redirect()
            ->route('divisions.index')
            ->with('status', "Divisi {$division->name} berhasil diperbarui.");
    }

    /**
     * Permanently delete the specified division. Its members become "Tanpa Divisi".
     */
    public function destroy(Division $division): RedirectResponse
    {
        Gate::authorize('delete', $division);

        $division->delete();

        return redirect()
            ->route('divisions.index')
            ->with('status', "Divisi {$division->name} berhasil dihapus.");
    }
}
