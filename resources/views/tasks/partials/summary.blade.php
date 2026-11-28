@php
    $primaryMetrics = [
        'total' => ['Total Tasks', 'text-slate-900', 'Semua tugas yang sesuai filter.'],
        'completed' => ['Completed', 'text-emerald-700', 'Tugas berstatus Done.'],
        'unfinished' => ['Unfinished', 'text-slate-900', 'Tugas yang belum Done.'],
        'overdue' => ['Overdue', 'text-red-700', 'Tanggal & jam deadline sudah lewat, belum Done.'],
        'due_today' => ['Due Today', 'text-amber-700', 'Deadline hari ini dan jamnya belum lewat.'],
        'urgent' => ['Urgent', 'text-red-700', 'Prioritas Urgent yang belum Done.'],
    ];
@endphp

<section class="mt-4" aria-labelledby="summary-heading">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 id="summary-heading" class="text-sm font-semibold text-slate-700">Ringkasan Tugas</h2>
            <p class="text-xs text-slate-500">Angka mengikuti tab dan filter di atas.</p>
        </div>
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

    <div x-show="!summary.error" class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7" :aria-busy="summary.loading">
        @foreach ($primaryMetrics as $key => [$label, $color, $description])
            <div class="flex flex-col rounded-2xl bg-white p-4 shadow-sm ring-1 ring-burgundy-100">
                <div class="flex items-center justify-between gap-1">
                    <p class="text-xs font-medium text-slate-500">{{ $label }}</p>
                    <span class="text-slate-300" title="{{ $description }}" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-3.5"><circle cx="12" cy="12" r="10" /><path d="M12 16v-4M12 8h.01" /></svg>
                    </span>
                </div>
                <p class="mt-1 text-2xl font-semibold {{ $color }}">
                    <span x-show="summary.loading && !summary.data" class="inline-block h-7 w-10 animate-pulse rounded bg-slate-100"></span>
                    <span x-show="summary.data" x-text="summary.data?.metrics.{{ $key }} ?? 0"></span>
                </p>
                <p class="mt-1 text-[11px] leading-snug text-slate-500">{{ $description }}</p>
            </div>
        @endforeach

        <div class="col-span-2 flex flex-col rounded-2xl bg-white p-4 shadow-sm ring-1 ring-burgundy-100 sm:col-span-3 lg:col-span-4 xl:col-span-1">
            <div class="flex items-center justify-between gap-1">
                <p class="text-xs font-medium text-slate-500">Completion Rate</p>
                <span class="text-slate-300" title="Completed ÷ Total Tasks × 100" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-3.5"><circle cx="12" cy="12" r="10" /><path d="M12 16v-4M12 8h.01" /></svg>
                </span>
            </div>
            <p class="mt-1 text-2xl font-semibold text-burgundy-800" x-text="(summary.data?.completion_rate ?? 0) + '%'"></p>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-burgundy-100">
                <div class="h-full rounded-full bg-burgundy-600 transition-all" :style="`width: ${summary.data?.completion_rate ?? 0}%`"></div>
            </div>
            <p class="mt-1.5 text-[11px] leading-snug text-slate-500">
                <span x-text="(summary.data?.metrics.completed ?? 0) + ' dari ' + (summary.data?.metrics.total ?? 0) + ' tugas selesai'"></span>
                <span class="block text-slate-400">Completed ÷ Total × 100</span>
            </p>
        </div>
    </div>

    <div x-show="!summary.error" class="mt-3 flex flex-wrap items-center gap-2 text-xs">
        <span class="font-medium text-slate-500">Jumlah per status:</span>
        <dl class="contents">
            <template x-for="status in config.statuses.filter((option) => option.value !== 'done')" :key="status.value">
                <div class="flex items-center gap-1.5 rounded-full bg-white px-3 py-1 ring-1 ring-burgundy-100">
                    <span class="size-2 rounded-full" :class="statusDot(status.value)"></span>
                    <dt class="text-slate-500" x-text="status.label"></dt>
                    <dd class="font-semibold text-slate-800" x-text="summary.data?.metrics[status.value] ?? 0"></dd>
                </div>
            </template>
        </dl>
    </div>
</section>
