@php
    $primaryMetrics = [
        'total' => ['Total Tasks', 'text-slate-900'],
        'completed' => ['Completed', 'text-emerald-700'],
        'unfinished' => ['Unfinished', 'text-slate-900'],
        'overdue' => ['Overdue', 'text-red-700'],
        'due_today' => ['Due Today', 'text-amber-700'],
        'urgent' => ['Urgent', 'text-red-700'],
    ];
@endphp

<section class="mt-4" aria-labelledby="summary-heading">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 id="summary-heading" class="text-sm font-semibold text-slate-700">Ringkasan Tugas</h2>
        <span
            x-show="summary.data"
            class="rounded-md bg-[#0c5963]/10 px-2 py-0.5 text-xs font-medium text-[#0c5963]"
            x-text="'Cakupan: ' + (summary.data?.scope.label ?? '')"
        ></span>
    </div>

    <div x-show="summary.error" x-cloak class="mt-2 flex items-center justify-between gap-3 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200" role="alert">
        <span x-text="'Ringkasan gagal dimuat. ' + (summary.error ?? '')"></span>
        <button type="button" @click="loadSummary()" class="font-semibold underline">Coba lagi</button>
    </div>

    <div x-show="!summary.error" class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-7" :aria-busy="summary.loading">
        @foreach ($primaryMetrics as $key => [$label, $color])
            <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-burgundy-100">
                <p class="text-xs text-slate-500">{{ $label }}</p>
                <p class="mt-1 text-2xl font-semibold {{ $color }}">
                    <span x-show="summary.loading && !summary.data" class="inline-block h-7 w-10 animate-pulse rounded bg-slate-100"></span>
                    <span x-show="summary.data" x-text="summary.data?.metrics.{{ $key }} ?? 0"></span>
                </p>
            </div>
        @endforeach

        <div class="col-span-2 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-burgundy-100 sm:col-span-3 lg:col-span-1">
            <p class="text-xs text-slate-500">Completion Rate</p>
            <p class="mt-1 text-2xl font-semibold text-burgundy-800" x-text="(summary.data?.completion_rate ?? 0) + '%'"></p>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-burgundy-100">
                <div class="h-full rounded-full bg-burgundy-600 transition-all" :style="`width: ${summary.data?.completion_rate ?? 0}%`"></div>
            </div>
        </div>
    </div>

    <dl x-show="!summary.error" class="mt-3 flex flex-wrap gap-2 text-xs">
        <template x-for="status in config.statuses.filter((option) => option.value !== 'done')" :key="status.value">
            <div class="flex items-center gap-1.5 rounded-full bg-white px-3 py-1 ring-1 ring-burgundy-100">
                <span class="size-2 rounded-full" :class="statusDot(status.value)"></span>
                <dt class="text-slate-500" x-text="status.label"></dt>
                <dd class="font-semibold text-slate-800" x-text="summary.data?.metrics[status.value] ?? 0"></dd>
            </div>
        </template>
    </dl>
</section>
