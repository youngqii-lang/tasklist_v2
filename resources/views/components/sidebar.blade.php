@php
    $account = auth()->user()->loadMissing(['role', 'division']);
    $appName = config('app.name');
    $appNameWords = preg_split('/\s+/', trim($appName));
    preg_match_all('/\p{Lu}/u', $appName, $appNameCapitals);
    $appInitials = match (true) {
        count($appNameWords) > 1 => mb_substr($appNameWords[0], 0, 1).mb_substr($appNameWords[1], 0, 1),
        count($appNameCapitals[0]) > 1 => $appNameCapitals[0][0].$appNameCapitals[0][1],
        default => mb_substr($appName, 0, 2),
    };
@endphp

<aside
    id="sidebar"
    class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col bg-burgundy-900 text-burgundy-50 shadow-2xl shadow-black/30 transition-transform duration-200 lg:translate-x-0 lg:shadow-none"
>
    <div class="flex items-center gap-3 px-6 pt-7 pb-6">
        <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-[#0c5963] text-lg font-bold text-white uppercase shadow-lg shadow-black/25 ring-1 ring-white/15">
            {{ $appInitials }}
        </div>
        <div class="min-w-0">
            <p class="truncate text-lg leading-tight font-bold text-white">{{ $appName }}</p>
            <p class="mt-0.5 text-[11px] font-medium tracking-wider text-burgundy-300 uppercase">Task Management</p>
        </div>
    </div>

    <nav class="flex flex-1 flex-col gap-6 overflow-y-auto px-4 py-2">
        <div>
            <p class="mb-2 px-3 text-xs font-medium text-burgundy-300">Utama</p>
            <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-5 shrink-0">
                    <rect x="3" y="3" width="7" height="9" rx="1.5" />
                    <rect x="14" y="3" width="7" height="5" rx="1.5" />
                    <rect x="14" y="12" width="7" height="9" rx="1.5" />
                    <rect x="3" y="16" width="7" height="5" rx="1.5" />
                </svg>
                Dashboard
            </x-sidebar-link>
        </div>

        <div>
            <p class="mb-2 px-3 text-xs font-medium text-burgundy-300">Sistem</p>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-burgundy-100 transition hover:bg-burgundy-800 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-5 shrink-0">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                        <path d="m16 17 5-5-5-5" />
                        <path d="M21 12H9" />
                    </svg>
                    Logout
                </button>
            </form>
        </div>
    </nav>

    <div class="border-t border-burgundy-800 p-4">
        <div class="rounded-xl bg-burgundy-800/50 p-4 ring-1 ring-burgundy-700">
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs text-burgundy-300">Akun Aktif</p>
                <span class="rounded-md bg-burgundy-700 px-2 py-0.5 text-xs font-medium text-burgundy-50 ring-1 ring-burgundy-600">
                    {{ $account->role->name }}
                </span>
            </div>
            <p class="mt-2 truncate font-semibold text-white">{{ $account->fullname }}</p>
            <p class="truncate text-xs text-burgundy-300">{{ '@'.$account->username }}</p>
            <p class="mt-3 truncate text-sm font-medium text-burgundy-200">
                {{ $account->division?->name ?? 'Tanpa Divisi' }}
            </p>
        </div>
    </div>
</aside>

<div data-sidebar-overlay class="fixed inset-0 z-30 hidden bg-burgundy-900/50 backdrop-blur-sm lg:hidden"></div>
