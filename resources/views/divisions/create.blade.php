@extends('layouts.dashboard', ['title' => 'Tambah Divisi'])

@section('page')
    <div class="mx-auto max-w-2xl">
        <a href="{{ route('divisions.index') }}" class="text-sm font-medium text-burgundy-600 hover:text-burgundy-800">&larr; Kembali ke daftar divisi</a>
        <h1 class="mt-3 text-2xl font-semibold text-burgundy-900">Tambah Divisi</h1>
        <p class="mt-1 text-sm text-slate-500">Divisi baru langsung bisa dipilih saat membuat atau mengedit akun.</p>

        <form method="POST" action="{{ route('divisions.store') }}" class="mt-6 flex flex-col gap-5 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-burgundy-100">
            @csrf

            @include('divisions.partials.fields')

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('divisions.index') }}" class="rounded-lg px-4 py-2.5 text-sm font-medium text-slate-600 ring-1 ring-slate-300 transition hover:bg-slate-50">Batal</a>
                <button type="submit" class="rounded-lg bg-burgundy-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-burgundy-700 focus:outline-none focus:ring-2 focus:ring-burgundy-300">
                    Simpan Divisi
                </button>
            </div>
        </form>
    </div>
@endsection
