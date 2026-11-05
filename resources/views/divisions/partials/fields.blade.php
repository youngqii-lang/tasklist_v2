@php
    /** @var \App\Models\Division|null $division */
    $division ??= null;
    $fieldClass = 'block w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 outline-none transition focus:ring-2 focus:ring-burgundy-200';
    $validClass = 'border-burgundy-200 focus:border-burgundy-500';
    $invalidClass = 'border-red-400 focus:border-red-500';
@endphp

<div>
    <label for="code_division" class="mb-1.5 block text-sm font-medium text-slate-700">Kode Divisi</label>
    <input
        id="code_division"
        name="code_division"
        type="text"
        value="{{ old('code_division', $division?->code_division) }}"
        required
        autofocus
        maxlength="50"
        pattern="[A-Za-z0-9_\-]+"
        title="Hanya huruf, angka, tanda hubung (-), atau garis bawah (_)."
        autocomplete="off"
        @class([$fieldClass, 'uppercase', $errors->has('code_division') ? $invalidClass : $validClass])
    >
    @error('code_division')
        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
    @else
        <p class="mt-1.5 text-xs text-slate-500">Contoh: IT, HRD, FIN. Otomatis disimpan dalam huruf besar.</p>
    @enderror
</div>

<div>
    <label for="name" class="mb-1.5 block text-sm font-medium text-slate-700">Nama Divisi</label>
    <input
        id="name"
        name="name"
        type="text"
        value="{{ old('name', $division?->name) }}"
        required
        maxlength="25"
        @class([$fieldClass, $errors->has('name') ? $invalidClass : $validClass])
    >
    @error('name')
        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
    @else
        <p class="mt-1.5 text-xs text-slate-500">Maksimal 25 karakter.</p>
    @enderror
</div>
