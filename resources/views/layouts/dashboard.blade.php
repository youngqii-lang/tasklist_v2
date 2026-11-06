@extends('layouts.app')

@section('content')
    <div class="bg-[#faf7f7] min-h-screen lg:pl-72">
        <x-sidebar />

        <header class="sticky top-0 z-20 flex items-center gap-3 border-b border-burgundy-100 bg-white/80 px-4 py-3 backdrop-blur lg:hidden">
            <button
                type="button"
                data-sidebar-toggle
                aria-label="Buka menu"
                aria-controls="sidebar"
                class="rounded-lg p-2 text-burgundy-700 transition hover:bg-burgundy-50"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-6">
                    <path d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <span class="font-semibold text-burgundy-800">{{ config('app.name') }}</span>
        </header>

        <main class="px-4 py-8 lg:px-10 lg:py-10">
            @if (session('status'))
                <div role="status" class="mx-auto mb-6 flex max-w-5xl items-center gap-3 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 ring-1 ring-emerald-200">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-5 shrink-0">
                        <path d="M20 6 9 17l-5-5" />
                    </svg>
                    {{ session('status') }}
                </div>
            @endif

            @yield('page')
        </main>
    </div>
@endsection
