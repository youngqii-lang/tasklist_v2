@extends('layouts.dashboard', ['title' => 'Dashboard'])

@section('page')
    <div class="mx-auto max-w-5xl">
        <h1 class="text-2xl font-semibold text-burgundy-900">Dashboard</h1>

        <div class="mt-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-burgundy-100">
            <p class="text-xl font-semibold text-slate-900">Halo, {{ auth()->user()->fullname }}!</p>
            <p class="mt-1 text-sm text-slate-500">Senang melihatmu kembali. Semoga harimu produktif.</p>
        </div>
    </div>
@endsection
