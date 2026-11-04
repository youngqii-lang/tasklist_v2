@extends('layouts.app', ['title' => 'Dashboard'])

@section('content')
    <main class="bg-motif min-h-screen px-4 py-12">
        <div class="mx-auto flex max-w-3xl items-center justify-between rounded-2xl bg-white p-6 shadow-sm ring-1 ring-burgundy-100">
            <div>
                <h1 class="text-xl font-semibold text-slate-900">Halo, {{ auth()->user()->fullname }}!</h1>
                <p class="mt-1 text-sm text-slate-500">Senang melihatmu kembali. Semoga harimu produktif.</p>
                <p class="mt-3 flex flex-wrap gap-2 text-xs">
                    <span class="rounded-full bg-burgundy-100 px-2.5 py-1 font-medium text-burgundy-800">{{ auth()->user()->role->name }}</span>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-600">{{ auth()->user()->division?->name ?? 'Tanpa Divisi' }}</span>
                </p>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-lg px-3 py-2 text-sm font-medium text-burgundy-700 ring-1 ring-burgundy-200 transition hover:bg-burgundy-50">
                    Keluar
                </button>
            </form>
        </div>
    </main>
@endsection
