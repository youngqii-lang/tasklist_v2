@php
    $columns = [
        ['title', 'Judul Tugas', true],
        ['division', 'Divisi', false],
        ['pic', 'PIC', false],
        ['priority', 'Prioritas', true],
        ['start_date', 'Mulai', true],
        ['deadline', 'Deadline', true],
        ['status', 'Status', true],
    ];
    $mobileSorts = ['created_at' => 'Terbaru dibuat', 'deadline' => 'Deadline', 'start_date' => 'Tanggal mulai', 'priority' => 'Prioritas', 'status' => 'Status', 'title' => 'Judul'];
@endphp

<section id="table-view" class="mt-6 scroll-mt-4 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-burgundy-100" aria-label="Table View">
    <div x-show="table.error" x-cloak class="flex flex-col items-center gap-3 px-6 py-12 text-center" role="alert">
        <p class="font-semibold text-red-700">Data tugas gagal dimuat.</p>
        <p class="text-sm text-slate-500" x-text="table.error"></p>
        <button type="button" @click="loadTable()" class="rounded-lg bg-burgundy-600 px-4 py-2 text-sm font-semibold text-white hover:bg-burgundy-700">Coba lagi</button>
    </div>

    {{-- Phones: one card per task, with a sort picker instead of column headers --}}
    <div x-show="!table.error" class="sm:hidden" :aria-busy="table.loading">
        <div class="flex items-center gap-2 border-b border-burgundy-100 px-4 py-3">
            <label for="table-sort-mobile" class="text-xs font-medium text-slate-500">Urutkan</label>
            <select id="table-sort-mobile" @change="setSort($event.target.value)" class="flex-1 rounded-lg border border-burgundy-200 bg-white px-2 py-1.5 text-sm">
                @foreach ($mobileSorts as $value => $label)
                    <option value="{{ $value }}" :selected="sort === '{{ $value }}'">{{ $label }}</option>
                @endforeach
            </select>
            <button type="button" @click="sortBy(sort)" class="rounded-lg px-2.5 py-1.5 text-sm ring-1 ring-burgundy-200" :aria-label="direction === 'asc' ? 'Urutan naik' : 'Urutan turun'" x-text="direction === 'asc' ? '▲' : '▼'"></button>
        </div>

        <p x-show="table.loading && table.tasks.length === 0" class="px-4 py-10 text-center text-sm text-slate-400">Memuat data tugas…</p>
        <p x-show="!table.loading && table.tasks.length === 0" class="px-4 py-12 text-center text-sm text-slate-500" x-text="emptyMessage"></p>

        <ul class="divide-y divide-burgundy-50" :class="table.loading && 'opacity-60'">
            <template x-for="task in table.tasks" :key="'m-' + task.id">
                <li @click="onTableRowClick($event, task)" class="cursor-pointer px-4 py-3 active:bg-burgundy-50/60">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm font-semibold text-slate-900" x-text="task.title"></p>
                        <span class="shrink-0 rounded-md px-1.5 py-0.5 text-[11px] font-semibold ring-1" :class="priorityBadge(task.priority)" x-text="task.priority_label"></span>
                    </div>
                    <p class="mt-0.5 truncate text-xs text-slate-500" x-text="(task.division?.name ?? '—') + (collaboratorBadge(task) ? ' ' + collaboratorBadge(task) : '') + ' · ' + picNames(task)"></p>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                        <span class="rounded-md px-1.5 py-0.5 font-medium ring-1" :class="statusBadge(task.status)" x-text="task.status_label"></span>
                        <span :class="task.is_overdue ? 'font-semibold text-red-600' : 'text-slate-500'" x-text="'Deadline ' + formatDeadline(task) + (task.is_overdue ? ' · Terlambat' : '')"></span>
                        <button x-show="task.abilities.transitions.length && !movingIds[task.id]" type="button" @click.stop="toggleTableMenu($event, task)" class="ml-auto rounded-md px-2 py-1 font-semibold text-burgundy-700 ring-1 ring-burgundy-200">Pindahkan</button>
                    </div>
                </li>
            </template>
        </ul>
    </div>

    {{-- Tablets and desktops --}}
    <div x-show="!table.error" @scroll.passive="tableMenu.task && closeTableMenu()" class="hidden max-h-[70vh] overflow-auto sm:block">
        <table class="min-w-full divide-y divide-burgundy-100 text-sm" :aria-busy="table.loading">
            <thead class="sticky top-0 z-10 bg-burgundy-50 text-left text-xs font-semibold tracking-wide text-slate-600 uppercase shadow-[0_1px_0] shadow-burgundy-100">
                <tr>
                    @foreach ($columns as [$key, $label, $sortable])
                        <th scope="col" class="px-4 py-3 whitespace-nowrap" @if ($sortable) :aria-sort="sort === '{{ $key }}' ? (direction === 'asc' ? 'ascending' : 'descending') : 'none'" @endif>
                            @if ($sortable)
                                <button type="button" @click="sortBy('{{ $key }}')" class="inline-flex items-center gap-1 uppercase hover:text-burgundy-700" :class="sort === '{{ $key }}' && 'text-burgundy-700'">
                                    {{ $label }}
                                    <span class="text-[10px]" :class="sort === '{{ $key }}' ? 'text-burgundy-600' : 'text-slate-300'" x-text="sortIndicator('{{ $key }}') || '↕'"></span>
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
                    <tr><td colspan="8" class="px-4 py-10 text-center text-slate-400">Memuat data tugas…</td></tr>
                </template>

                <template x-if="!table.loading && table.tasks.length === 0">
                    <tr><td colspan="8" class="px-4 py-12 text-center text-slate-500" x-text="emptyMessage"></td></tr>
                </template>

                <template x-for="task in table.tasks" :key="task.id">
                    <tr
                        @click="onTableRowClick($event, task)"
                        class="cursor-pointer transition hover:bg-burgundy-50/50"
                        :class="{ 'opacity-60': table.loading || movingIds[task.id], 'bg-amber-50': highlightId === task.id }"
                    >
                        <td class="max-w-xs px-4 py-3">
                            <button type="button" @click="openTask(task.id)" class="line-clamp-2 text-left font-medium text-slate-900 hover:text-burgundy-700 hover:underline" x-text="task.title"></button>
                            <span x-show="task.evidences_count > 0" class="text-[11px] text-slate-500" x-text="task.evidences_count + ' evidence'"></span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-slate-600" :title="divisionNames(task)">
                            <span x-text="task.division?.name"></span>
                            <span x-show="collaboratorBadge(task)" class="ml-1 rounded bg-slate-100 px-1.5 py-px text-[10px] font-medium text-slate-600" x-text="collaboratorBadge(task)"></span>
                        </td>
                        <td class="max-w-[12rem] truncate px-4 py-3 text-slate-600" x-text="picNames(task)" :title="picNames(task)"></td>
                        <td class="px-4 py-3">
                            <span class="rounded-md px-2 py-0.5 text-xs font-semibold ring-1" :class="priorityBadge(task.priority)" x-text="task.priority_label"></span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-slate-600" x-text="formatDate(task.start_date)"></td>
                        <td class="px-4 py-3 whitespace-nowrap" :class="task.is_overdue ? 'font-semibold text-red-600' : 'text-slate-600'">
                            <span x-text="formatDeadline(task)"></span>
                            <span x-show="task.is_overdue" class="block text-[11px]">Terlambat</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-md px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1" :class="statusBadge(task.status)" x-text="task.status_label"></span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <span x-show="movingIds[task.id]" class="text-xs text-slate-500">Menyimpan…</span>
                            <button
                                x-show="task.abilities.transitions.length && !movingIds[task.id]"
                                type="button"
                                @click.stop="toggleTableMenu($event, task)"
                                :aria-expanded="tableMenu.task?.id === task.id"
                                aria-haspopup="menu"
                                class="rounded-md px-2.5 py-1 text-xs font-semibold text-burgundy-700 ring-1 ring-burgundy-200 hover:bg-burgundy-50"
                            >Pindahkan</button>
                            <button x-show="task.abilities.update" type="button" @click="openEditForm(task)" class="ml-1 rounded-md px-2.5 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">Edit</button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <footer x-show="!table.error && table.meta && table.meta.total > 0" class="flex flex-wrap items-center justify-between gap-3 border-t border-burgundy-100 px-4 py-3 text-sm text-slate-600">
        <div class="flex items-center gap-3">
            <p x-text="tableRange + ' tugas'"></p>
            <label class="flex items-center gap-1.5 text-xs text-slate-500">
                Per halaman
                <select @change="setPerPage($event.target.value)" class="rounded-md border border-burgundy-200 bg-white px-1.5 py-1 text-xs text-slate-700">
                    <template x-for="size in tablePageSizes" :key="size">
                        <option :value="size" x-text="size" :selected="size === perPage"></option>
                    </template>
                </select>
            </label>
        </div>
        <nav class="flex items-center gap-1" aria-label="Halaman tabel">
            <button type="button" @click="goToPage(page - 1)" :disabled="page <= 1 || table.loading" class="rounded-lg px-2.5 py-1.5 ring-1 ring-burgundy-200 hover:bg-burgundy-50 disabled:cursor-not-allowed disabled:opacity-40" aria-label="Halaman sebelumnya">‹</button>
            <template x-for="(number, index) in tablePages" :key="index">
                <span>
                    <span x-show="number === null" class="px-1 text-slate-400">…</span>
                    <button
                        x-show="number !== null"
                        type="button"
                        @click="goToPage(number)"
                        :disabled="table.loading"
                        :aria-current="number === page ? 'page' : null"
                        class="min-w-8 rounded-lg px-2 py-1.5 text-sm"
                        :class="number === page ? 'bg-burgundy-600 font-semibold text-white' : 'ring-1 ring-burgundy-200 hover:bg-burgundy-50'"
                        x-text="number"
                    ></button>
                </span>
            </template>
            <button type="button" @click="goToPage(page + 1)" :disabled="page >= (table.meta?.last_page ?? 1) || table.loading" class="rounded-lg px-2.5 py-1.5 ring-1 ring-burgundy-200 hover:bg-burgundy-50 disabled:cursor-not-allowed disabled:opacity-40" aria-label="Halaman berikutnya">›</button>
        </nav>
    </footer>

    {{-- Row menu, placed on the page so the scrollable table does not cut it off --}}
    <div
        x-show="tableMenu.task"
        x-cloak
        x-transition.opacity
        @click.outside="closeTableMenu()"
        @keydown.escape.window="closeTableMenu()"
        class="fixed z-40 w-64 overflow-hidden rounded-xl bg-white py-1 shadow-xl ring-1 ring-burgundy-100"
        :style="`top: ${tableMenu.top}px; right: ${tableMenu.right}px;`"
        role="menu"
        :aria-label="'Pindahkan ' + (tableMenu.task?.title ?? '')"
    >
        <p class="px-3 pt-1.5 pb-1 text-[11px] font-semibold tracking-wide text-slate-400 uppercase">Pindahkan ke</p>
        <template x-for="option in (tableMenu.task ? moveOptions(tableMenu.task) : [])" :key="option.status">
            <button type="button" role="menuitem" @click="chooseTableMove(option.status)" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-burgundy-50 focus:bg-burgundy-50 focus:outline-none">
                <span class="size-2 shrink-0 rounded-full" :class="statusDot(option.status)"></span>
                <span class="flex-1">
                    <span class="block font-medium text-slate-800" x-text="option.label"></span>
                    <span class="block text-xs text-slate-500" x-text="'→ ' + option.target"></span>
                    <span x-show="option.hint" class="block text-xs font-medium text-amber-700" x-text="option.hint"></span>
                </span>
            </button>
        </template>
    </div>
</section>
