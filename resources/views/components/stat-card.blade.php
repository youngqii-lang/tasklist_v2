@props(['label', 'value'])

<div class="flex items-center gap-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-burgundy-100">
    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-burgundy-100 text-burgundy-700">
        {{ $icon }}
    </div>
    <div>
        <p class="text-sm text-slate-500">{{ $label }}</p>
        <p class="text-2xl font-semibold text-slate-900">{{ $value }}</p>
    </div>
</div>
