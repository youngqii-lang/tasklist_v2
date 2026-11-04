@props(['description'])

@php
    $tabs = [
        ['label' => 'Akun', 'route' => 'accounts.index', 'pattern' => 'accounts.*'],
        ['label' => 'Divisi', 'route' => 'divisions.index', 'pattern' => 'divisions.*'],
    ];
@endphp

<div>
    <h1 class="text-2xl font-semibold text-burgundy-900">Organization Management</h1>

    <nav aria-label="Organization Management" class="mt-4 flex gap-1 border-b border-burgundy-100">
        @foreach ($tabs as $tab)
            @php($isActive = request()->routeIs($tab['pattern']))
            <a
                href="{{ route($tab['route']) }}"
                @if ($isActive) aria-current="page" @endif
                @class([
                    '-mb-px border-b-2 px-4 py-2.5 text-sm font-medium transition',
                    'border-burgundy-600 text-burgundy-700' => $isActive,
                    'border-transparent text-slate-500 hover:border-burgundy-200 hover:text-burgundy-700' => ! $isActive,
                ])
            >
                {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>

    <div class="mt-5 flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-slate-500">{{ $description }}</p>
        {{ $action ?? '' }}
    </div>
</div>
