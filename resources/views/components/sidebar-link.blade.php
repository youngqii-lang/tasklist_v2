@props(['active' => false])

<a
    {{ $attributes->class([
        'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
        'bg-burgundy-600 text-white shadow-md shadow-black/20 ring-1 ring-burgundy-400/30' => $active,
        'text-burgundy-100 hover:bg-burgundy-800 hover:text-white' => ! $active,
    ]) }}
    @if ($active) aria-current="page" @endif
>
    {{ $slot }}
</a>
