@extends('layouts.dashboard', ['title' => 'Organization Management · Divisi'])

@section('page')
    <div class="mx-auto max-w-5xl">
        <x-organization-header description="Daftar divisi yang ada di organisasi.">
            @can('create', App\Models\Division::class)
                <x-slot:action>
                    <a href="{{ route('divisions.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-burgundy-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-burgundy-700 focus:outline-none focus:ring-2 focus:ring-burgundy-300">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4">
                            <path d="M12 5v14M5 12h14" />
                        </svg>
                        Tambah Divisi
                    </a>
                </x-slot:action>
            @endcan
        </x-organization-header>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat-card label="Total Divisions" :value="$totalDivisions">
                <x-slot:icon>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-6">
                        <rect x="4" y="3" width="16" height="18" rx="2" />
                        <path d="M9 21v-4h6v4M8 7h.01M12 7h.01M16 7h.01M8 11h.01M12 11h.01M16 11h.01" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>

            <x-stat-card label="Assigned Accounts" :value="$accountsWithDivision">
                <x-slot:icon>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-6">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="m16 11 2 2 4-4" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>

            <x-stat-card label="Unassigned Accounts" :value="$accountsWithoutDivision">
                <x-slot:icon>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-6">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M17 11h5" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>

            <x-stat-card label="Empty Divisions" :value="$emptyDivisions">
                <x-slot:icon>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-6">
                        <path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>
        </div>

        <div class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-burgundy-100">
            <form method="GET" action="{{ route('divisions.index') }}" class="flex flex-wrap items-center gap-3 border-b border-burgundy-100 px-5 py-4">
                <label for="division-search" class="text-sm font-medium text-slate-700">Cari</label>
                <input
                    id="division-search"
                    name="search"
                    type="search"
                    value="{{ $search }}"
                    placeholder="Kode atau nama divisi"
                    class="w-full max-w-xs rounded-lg border border-burgundy-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-burgundy-500 focus:ring-2 focus:ring-burgundy-200"
                >
                <button type="submit" class="rounded-lg px-3 py-2 text-sm font-medium text-burgundy-700 ring-1 ring-burgundy-200 transition hover:bg-burgundy-50">
                    Cari
                </button>
                @if ($search !== '')
                    <a href="{{ route('divisions.index') }}" class="text-sm font-medium text-slate-500 hover:text-burgundy-700">Reset</a>
                @endif
                <span class="ml-auto text-sm text-slate-500">{{ $divisions->total() }} divisi</span>
            </form>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-burgundy-50/60 text-xs font-semibold tracking-wide text-burgundy-800 uppercase">
                        <tr>
                            <th scope="col" class="px-5 py-3">Kode</th>
                            <th scope="col" class="px-5 py-3">Nama Divisi</th>
                            <th scope="col" class="px-5 py-3">Jumlah Akun</th>
                            @can('create', App\Models\Division::class)
                                <th scope="col" class="px-5 py-3 text-right">Aksi</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-burgundy-50">
                        @forelse ($divisions as $division)
                            <tr class="transition hover:bg-burgundy-50/40">
                                <td class="px-5 py-3.5">
                                    <span class="rounded-md bg-burgundy-50 px-2 py-1 font-mono text-xs font-medium text-burgundy-800 ring-1 ring-burgundy-100">{{ $division->code_division }}</span>
                                </td>
                                <td class="px-5 py-3.5 font-medium text-slate-900">{{ $division->name }}</td>
                                <td class="px-5 py-3.5">
                                    <a href="{{ route('accounts.index', ['division' => $division->id]) }}" class="text-slate-700 hover:text-burgundy-700 hover:underline">
                                        {{ $division->accounts_count }} akun
                                    </a>
                                </td>
                                @can('create', App\Models\Division::class)
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center justify-end gap-2">
                                            @can('update', $division)
                                                <a href="{{ route('divisions.edit', $division) }}" class="rounded-lg px-3 py-1.5 text-xs font-medium text-burgundy-700 ring-1 ring-burgundy-200 transition hover:bg-burgundy-50">
                                                    Edit
                                                </a>
                                            @endcan

                                            @can('delete', $division)
                                                <form
                                                    method="POST"
                                                    action="{{ route('divisions.destroy', $division) }}"
                                                    data-confirm-delete="{{ $division->name }}"
                                                    data-confirm-delete-note="{{ $division->accounts_count > 0
                                                        ? "{$division->accounts_count} akun di divisi ini akan menjadi Tanpa Divisi."
                                                        : 'Divisi ini belum memiliki anggota.' }}"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 ring-1 ring-red-200 transition hover:bg-red-100">
                                                        Hapus
                                                    </button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-10 text-center text-sm text-slate-500">
                                    {{ $search !== '' ? 'Tidak ada divisi yang cocok dengan pencarian.' : 'Belum ada divisi.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($divisions->hasPages())
                <div class="border-t border-burgundy-100 px-5 py-4">
                    {{ $divisions->links() }}
                </div>
            @endif
        </div>
    </div>

    @can('create', App\Models\Division::class)
        <x-confirm-delete-dialog subject="divisi" />
    @endcan
@endsection
