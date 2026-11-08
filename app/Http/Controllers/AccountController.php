<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use App\Models\Division;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AccountController extends Controller
{
    /**
     * Display the account list, optionally filtered by division.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Account::class);

        $divisions = Division::orderBy('name')->get(['id', 'name']);
        $selectedDivisionId = $divisions->firstWhere('id', $request->integer('division'))?->id;

        $accounts = Account::query()
            ->with(['role', 'division'])
            ->when($selectedDivisionId, fn ($query) => $query->where('division_id', $selectedDivisionId))
            ->orderBy('fullname')
            ->paginate(15)
            ->withQueryString();

        return view('accounts.index', [
            'totalAccounts' => Account::count(),
            'roles' => Role::withCount('accounts')->orderBy('name')->get(),
            'accounts' => $accounts,
            'divisions' => $divisions,
            'selectedDivisionId' => $selectedDivisionId,
        ]);
    }

    /**
     * Show the form for creating a new account. Only admins get to choose the role.
     */
    public function create(Request $request): View
    {
        Gate::authorize('create', Account::class);

        return view('accounts.create', [
            'roles' => $request->user()->can('assignRoleOnCreate', Account::class)
                ? Role::orderBy('name')->get(['id', 'name'])
                : null,
            'divisions' => Division::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Store a newly created account. Without a chosen role (managers) it starts as an employee.
     */
    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $account = Account::create([
            ...$request->safe()->only(['fullname', 'username', 'password', 'division_id']),
            'role_id' => $request->validated('role_id') ?? Role::where('code_role', Role::EMPLOYEE)->valueOrFail('id'),
        ]);

        return redirect()
            ->route('accounts.index')
            ->with('status', "Akun {$account->fullname} berhasil dibuat.");
    }

    /**
     * Show the form for editing the specified account.
     */
    public function edit(Account $account): View
    {
        Gate::authorize('update', $account);

        return view('accounts.edit', [
            'account' => $account,
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'divisions' => Division::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Update the specified account.
     */
    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $attributes = $request->safe()->only(['fullname', 'username', 'role_id', 'division_id']);

        if ($request->filled('password')) {
            $attributes['password'] = $request->validated('password');
        }

        $account->update($attributes);

        return redirect()
            ->route('accounts.index')
            ->with('status', "Akun {$account->fullname} berhasil diperbarui.");
    }

    /**
     * Permanently delete the specified account.
     */
    public function destroy(Account $account): RedirectResponse
    {
        Gate::authorize('delete', $account);

        $account->delete();

        return redirect()
            ->route('accounts.index')
            ->with('status', "Akun {$account->fullname} berhasil dihapus.");
    }
}
