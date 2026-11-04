@extends('layouts.dashboard', ['title' => 'Manajemen Akun'])

@section('page')
    <div class="mx-auto max-w-5xl">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-burgundy-900">Manajemen Akun</h1>
                <p class="mt-1 text-sm text-slate-500">Daftar akun yang terdaftar di aplikasi.</p>
            </div>

            @can('create', App\Models\Account::class)
                <a href="{{ route('accounts.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-burgundy-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-burgundy-700 focus:outline-none focus:ring-2 focus:ring-burgundy-300">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4">
                        <path d="M12 5v14M5 12h14" />
                    </svg>
                    Tambah Akun
                </a>
            @endcan
        </div>

        <div class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-burgundy-100">
            <form method="GET" action="{{ route('accounts.index') }}" class="flex flex-wrap items-center gap-3 border-b border-burgundy-100 px-5 py-4">
                <label for="division-filter" class="text-sm font-medium text-slate-700">Divisi</label>
                <select
                    id="division-filter"
                    name="division"
                    data-auto-submit
                    class="rounded-lg border border-burgundy-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none transition focus:border-burgundy-500 focus:ring-2 focus:ring-burgundy-200"
                >
                    <option value="">Semua Divisi</option>
                    @foreach ($divisions as $division)
                        <option value="{{ $division->id }}" @selected($selectedDivisionId === $division->id)>{{ $division->name }}</option>
                    @endforeach
                </select>
                <noscript>
                    <button type="submit" class="rounded-lg bg-burgundy-600 px-3 py-2 text-sm font-medium text-white">Terapkan</button>
                </noscript>
                <span class="ml-auto text-sm text-slate-500">{{ $accounts->total() }} akun</span>
            </form>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-burgundy-50/60 text-xs font-semibold tracking-wide text-burgundy-800 uppercase">
                        <tr>
                            <th scope="col" class="px-5 py-3">Nama Lengkap</th>
                            <th scope="col" class="px-5 py-3">Divisi</th>
                            <th scope="col" class="px-5 py-3">Role</th>
                            @can('create', App\Models\Account::class)
                                <th scope="col" class="px-5 py-3 text-right">Aksi</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-burgundy-50">
                        @forelse ($accounts as $account)
                            <tr class="transition hover:bg-burgundy-50/40">
                                <td class="px-5 py-3.5">
                                    <p class="font-medium text-slate-900">{{ $account->fullname }}</p>
                                    <p class="text-xs text-slate-500">{{ '@'.$account->username }}</p>
                                </td>
                                <td class="px-5 py-3.5 text-slate-700">
                                    {{ $account->division?->name ?? 'Tanpa Divisi' }}
                                </td>
                                <td class="px-5 py-3.5">
                                    <span @class([
                                        'rounded-full px-2.5 py-1 text-xs font-medium',
                                        'bg-burgundy-100 text-burgundy-800' => $account->isManager(),
                                        'bg-slate-100 text-slate-700' => ! $account->isManager(),
                                    ])>{{ $account->role->name }}</span>
                                </td>
                                @can('create', App\Models\Account::class)
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center justify-end gap-2">
                                            @can('update', $account)
                                                <a href="{{ route('accounts.edit', $account) }}" class="rounded-lg px-3 py-1.5 text-xs font-medium text-burgundy-700 ring-1 ring-burgundy-200 transition hover:bg-burgundy-50">
                                                    Edit
                                                </a>
                                            @endcan

                                            @can('delete', $account)
                                                <form
                                                    method="POST"
                                                    action="{{ route('accounts.destroy', $account) }}"
                                                    data-confirm-delete="{{ $account->fullname }}"
                                                    data-confirm-delete-note="Akun akan dihapus permanen dan tidak bisa login lagi."
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 ring-1 ring-red-200 transition hover:bg-red-100">
                                                        Hapus
                                                    </button>
                                                </form>
                                            @else
                                                <span class="px-3 py-1.5 text-xs text-slate-400">Akun Anda</span>
                                            @endcan
                                        </div>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-10 text-center text-sm text-slate-500">
                                    Belum ada akun di divisi ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($accounts->hasPages())
                <div class="border-t border-burgundy-100 px-5 py-4">
                    {{ $accounts->links() }}
                </div>
            @endif
        </div>
    </div>

    @can('create', App\Models\Account::class)
        <x-confirm-delete-dialog subject="akun" />
    @endcan
@endsection
