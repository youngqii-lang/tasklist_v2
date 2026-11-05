@php
    /** @var \App\Models\Account|null $account */
    $account ??= null;
    $roles ??= null;
    $fieldClass = 'block w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 outline-none transition focus:ring-2 focus:ring-burgundy-200';
    $validClass = 'border-burgundy-200 focus:border-burgundy-500';
    $invalidClass = 'border-red-400 focus:border-red-500';
@endphp

<div>
    <label for="fullname" class="mb-1.5 block text-sm font-medium text-slate-700">Nama Lengkap</label>
    <input
        id="fullname"
        name="fullname"
        type="text"
        value="{{ old('fullname', $account?->fullname) }}"
        required
        autofocus
        @class([$fieldClass, $errors->has('fullname') ? $invalidClass : $validClass])
    >
    @error('fullname')
        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>

<div>
    <label for="username" class="mb-1.5 block text-sm font-medium text-slate-700">Username</label>
    <input
        id="username"
        name="username"
        type="text"
        value="{{ old('username', $account?->username) }}"
        required
        pattern="[A-Za-z0-9]+"
        title="Hanya huruf dan angka, tanpa spasi atau simbol."
        autocomplete="off"
        @class([$fieldClass, $errors->has('username') ? $invalidClass : $validClass])
    >
    @error('username')
        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
    @else
        <p class="mt-1.5 text-xs text-slate-500">Hanya huruf dan angka, tanpa spasi atau simbol.</p>
    @enderror
</div>

<div>
    <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">Password</label>
    <div class="relative">
        <input
            id="password"
            name="password"
            type="password"
            @required(! $account)
            minlength="8"
            autocomplete="new-password"
            @class([$fieldClass, 'pr-10 [&::-ms-reveal]:hidden', $errors->has('password') ? $invalidClass : $validClass])
        >
        <x-password-toggle for="password" class="text-burgundy-400 hover:text-burgundy-700 focus:text-burgundy-700" />
    </div>
    @error('password')
        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
    @else
        <p class="mt-1.5 text-xs text-slate-500">
            {{ $account ? 'Kosongkan jika tidak ingin mengubah password.' : 'Minimal 8 karakter.' }}
        </p>
    @enderror
</div>

@if ($roles)
    <div>
        <label for="role_id" class="mb-1.5 block text-sm font-medium text-slate-700">Role</label>
        <select
            id="role_id"
            name="role_id"
            required
            @class([$fieldClass, $errors->has('role_id') ? $invalidClass : $validClass])
        >
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected((string) old('role_id', $account?->role_id) === (string) $role->id)>
                    {{ $role->name }}
                </option>
            @endforeach
        </select>
        @error('role_id')
            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
@endif

<div>
    <label for="division_id" class="mb-1.5 block text-sm font-medium text-slate-700">Divisi</label>
    <select
        id="division_id"
        name="division_id"
        required
        @class([$fieldClass, $errors->has('division_id') ? $invalidClass : $validClass])
    >
        <option value="" disabled @selected(! old('division_id', $account?->division_id))>Pilih divisi</option>
        @foreach ($divisions as $division)
            <option value="{{ $division->id }}" @selected((string) old('division_id', $account?->division_id) === (string) $division->id)>
                {{ $division->name }}
            </option>
        @endforeach
    </select>
    @error('division_id')
        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
