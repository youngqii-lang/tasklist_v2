@extends('layouts.app', ['title' => 'Login'])

@section('content')
    <main class="bg-[#faf7f7] flex min-h-screen items-center justify-center px-4 py-10">
        <div class="w-full max-w-sm overflow-hidden rounded-2xl bg-burgundy-700 text-burgundy-50 shadow-xl shadow-burgundy-900/20 ring-1 ring-burgundy-800">
            <div class="bg-burgundy-800/60 px-6 pt-8 pb-6 text-center">
                <h1 class="text-2xl font-semibold">Selamat datang 👋</h1>
                <p class="mt-2 text-sm text-burgundy-100">Silakan masuk untuk mengelola daftar tugasmu hari ini.</p>
            </div>

            <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5 p-6">
                @csrf

                <div>
                    <label for="username" class="mb-1.5 block text-sm font-medium text-burgundy-50">Username</label>
                    <input
                        id="username"
                        name="username"
                        type="text"
                        value="{{ old('username') }}"
                        required
                        autofocus
                        autocomplete="username"
                        @class([
                            'block w-full rounded-lg border bg-burgundy-800/60 px-3 py-2 text-sm text-burgundy-50 outline-none transition focus:bg-burgundy-800 focus:ring-2 focus:ring-burgundy-300/50 autofill:shadow-[inset_0_0_0_1000px_var(--color-burgundy-800)] autofill:[-webkit-text-fill-color:var(--color-burgundy-50)]',
                            'border-amber-300' => $errors->has('username'),
                            'border-burgundy-500 focus:border-burgundy-300' => ! $errors->has('username'),
                        ])
                    >
                    @error('username')
                        <p class="mt-1.5 text-sm text-amber-200">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-burgundy-50">Password</label>
                    <div class="relative">
                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            autocomplete="current-password"
                            @class([
                                'block w-full rounded-lg border bg-burgundy-800/60 py-2 pr-10 pl-3 text-sm [&::-ms-reveal]:hidden text-burgundy-50 outline-none transition focus:bg-burgundy-800 focus:ring-2 focus:ring-burgundy-300/50 autofill:shadow-[inset_0_0_0_1000px_var(--color-burgundy-800)] autofill:[-webkit-text-fill-color:var(--color-burgundy-50)]',
                                'border-amber-300' => $errors->has('password'),
                                'border-burgundy-500 focus:border-burgundy-300' => ! $errors->has('password'),
                            ])
                        >
                        <x-password-toggle for="password" class="text-burgundy-200 hover:text-burgundy-50 focus:text-burgundy-50" />
                    </div>
                    @error('password')
                        <p class="mt-1.5 text-sm text-amber-200">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="mt-1 w-full rounded-lg bg-burgundy-100 px-4 py-2.5 text-sm font-semibold text-burgundy-800 transition hover:bg-burgundy-200 focus:outline-none focus:ring-2 focus:ring-burgundy-300/50">
                    Masuk
                </button>
            </form>
        </div>
    </main>
@endsection
