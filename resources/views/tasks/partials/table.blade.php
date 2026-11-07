@php
    $columns = [
        ['title', 'Judul Tugas', true],
        ['division', 'Divisi', false],
        ['pic', 'PIC', false],
        ['priority', 'Prioritas', true],
        ['deadline', 'Deadline', true],
        ['status', 'Status', true],
    ];
@endphp

<section class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-burgundy-100" aria-label="Table View">
    <div x-show="table.error" x-cloak class="flex flex-col items-center gap-3 px-6 py-12 text-center" role="alert">
        <p class="font-semibold text-red-700">Data tugas gagal dimuat.</p>
        <p class="text-sm text-slate-500" x-text="table.error"></p>
        <button type="button" @click="loadTable()" class="rounded-lg bg-burgundy-600 px-4 py-2 text-sm font-semibold text-white hover:bg-burgundy-700">Coba lagi</button>
    </div>

    <div x-show="!table.error" class="overflow-x-auto">
        <table class="min-w-full divide-y divide-burgundy-100 text-sm" :aria-busy="table.loading">
            <thead class="bg-burgundy-50/60 text-left text-xs font-semibold tracking-wide text-slate-600 uppercase">
                <tr>
                    @foreach ($columns as [$key, $label, $sortable])
                        <th scope="col" class="px-4 py-3 whitespace-nowrap" @if ($sortable) :aria-sort="sort === '{{ $key }}' ? (direction === 'asc' ? 'ascending' : 'descending') : 'none'" @endif>
                            @if ($sortable)
                                <button type="button" @click="sortBy('{{ $key }}')" class="inline-flex items-center gap-1 uppercase hover:text-burgundy-700">
                                    {{ $label }}
                                    <span class="text-[10px] text-burgundy-600" x-text="sortIndicator('{{ $key }}')"></span>
                                </button>
                            @else
                                {{ $label }}
                            @endif
                        </th>
                    @endforeach
                    <th scope="col" class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-burgundy-50">
                <template x-if="table.loading && table.tasks.length === 0">
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Memuat data tugas…</td></tr>
                </template>

                <template x-if="!table.loading && table.tasks.length === 0">
                    <tr><td colspan="7" class="px-4 py-12 text-center text-slate-500" x-text="emptyMessage"></td></tr>
                </template>

                <template x-for="task in table.tasks" :key="task.id">
                    <tr class="transition hover:bg-burgundy-50/40" :class="table.loading && 'opacity-60'">
                        <td class="max-w-xs px-4 py-3">
                            <button type="button" @click="openTask(task.id)" class="line-clamp-2 text-left font-medium text-slate-900 hover:text-burgundy-700 hover:underline" x-text="task.title"></button>
                            <span x-show="task.evidences_count > 0" class="text-[11px] text-slate-500" x-text="task.evidences_count + ' evidence'"></span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-slate-600" x-text="task.division?.name"></td>
                        <td class="max-w-[12rem] truncate px-4 py-3 text-slate-600" x-text="picNames(task)" :title="picNames(task)"></td>
                        <td class="px-4 py-3">
                            <span class="rounded-md px-2 py-0.5 text-xs font-semibold ring-1" :class="priorityBadge(task.priority)" x-text="task.priority_label"></span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap" :class="task.is_overdue ? 'font-semibold text-red-600' : 'text-slate-600'">
                            <span x-text="formatDate(task.deadline)"></span>
                            <span x-show="task.is_overdue" class="block text-[11px]">Terlambat</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-md px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1" :class="statusBadge(task.status)" x-text="task.status_label"></span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button type="button" @click="openTask(task.id)" class="rounded-md px-2.5 py-1 text-xs font-semibold text-burgundy-700 ring-1 ring-burgundy-200 hover:bg-burgundy-50">Detail</button>
                            <button x-show="task.abilities.update" type="button" @click="openEditForm(task)" class="ml-1 rounded-md px-2.5 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">Edit</button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <footer x-show="!table.error && table.meta && table.meta.total > 0" class="flex flex-wrap items-center justify-between gap-3 border-t border-burgundy-100 px-4 py-3 text-sm text-slate-600">
        <p>
            Halaman <span class="font-semibold" x-text="table.meta?.current_page"></span>
            dari <span class="font-semibold" x-text="table.meta?.last_page"></span>
            · <span x-text="table.meta?.total"></span> tugas
        </p>
        <div class="flex gap-2">
            <button type="button" @click="goToPage(page - 1)" :disabled="page <= 1 || table.loading" class="rounded-lg px-3 py-1.5 ring-1 ring-burgundy-200 hover:bg-burgundy-50 disabled:cursor-not-allowed disabled:opacity-40">Sebelumnya</button>
            <button type="button" @click="goToPage(page + 1)" :disabled="page >= (table.meta?.last_page ?? 1) || table.loading" class="rounded-lg px-3 py-1.5 ring-1 ring-burgundy-200 hover:bg-burgundy-50 disabled:cursor-not-allowed disabled:opacity-40">Berikutnya</button>
        </div>
    </footer>
</section>
