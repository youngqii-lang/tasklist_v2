@extends('layouts.dashboard', ['title' => 'Edit Divisi'])

@section('page')
    <div class="mx-auto max-w-2xl">
        <a href="{{ route('divisions.index') }}" class="text-sm font-medium text-burgundy-600 hover:text-burgundy-800">&larr; Kembali ke daftar divisi</a>
        <h1 class="mt-3 text-2xl font-semibold text-burgundy-900">Edit Divisi</h1>
        <p class="mt-1 text-sm text-slate-500">Perbarui data divisi {{ $division->name }}.</p>

        <form method="POST" action="{{ route('divisions.update', $division) }}" class="mt-6 flex flex-col gap-5 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-burgundy-100">
            @csrf
            @method('PUT')

            @include('divisions.partials.fields', ['division' => $division])

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('divisions.index') }}" class="rounded-lg px-4 py-2.5 text-sm font-medium text-slate-600 ring-1 ring-slate-300 transition hover:bg-slate-50">Batal</a>
                <button type="submit" class="rounded-lg bg-burgundy-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-burgundy-700 focus:outline-none focus:ring-2 focus:ring-burgundy-300">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
@endsection
