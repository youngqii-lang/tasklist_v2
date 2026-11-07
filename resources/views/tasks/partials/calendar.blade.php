<section class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]" aria-label="Calendar View">
    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-burgundy-100">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-burgundy-100 px-4 py-3">
            <p class="text-base font-semibold text-slate-800 capitalize" x-text="monthLabel"></p>
            <div class="flex items-center gap-2">
                <button type="button" @click="shiftMonth(-1)" class="rounded-lg p-2 ring-1 ring-burgundy-200 hover:bg-burgundy-50" aria-label="Bulan sebelumnya">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-4"><path d="m15 18-6-6 6-6" /></svg>
                </button>
                <button type="button" @click="goToToday()" class="rounded-lg px-3 py-1.5 text-sm font-medium ring-1 ring-burgundy-200 hover:bg-burgundy-50">Hari ini</button>
                <button type="button" @click="shiftMonth(1)" class="rounded-lg p-2 ring-1 ring-burgundy-200 hover:bg-burgundy-50" aria-label="Bulan berikutnya">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-4"><path d="m9 18 6-6-6-6" /></svg>
                </button>
            </div>
        </header>

        <div x-show="error" x-cloak class="flex flex-col items-center gap-3 px-6 py-12 text-center" role="alert">
            <p class="font-semibold text-red-700">Kalender gagal dimuat.</p>
            <p class="text-sm text-slate-500" x-text="error"></p>
            <button type="button" @click="load()" class="rounded-lg bg-burgundy-600 px-4 py-2 text-sm font-semibold text-white hover:bg-burgundy-700">Coba lagi</button>
        </div>

        <div x-show="!error" class="p-3" :aria-busy="loading" :class="loading && 'opacity-60'">
            <div class="grid grid-cols-7 gap-1 pb-1 text-center text-[11px] font-semibold text-slate-500 uppercase">
                <template x-for="weekday in weekdays" :key="weekday"><div x-text="weekday"></div></template>
            </div>
            <div class="grid grid-cols-7 gap-1" role="grid">
                <template x-for="cell in cells" :key="cell.key">
                    <button
                        type="button"
                        role="gridcell"
                        @click="selectDate(cell.key)"
                        :aria-selected="cell.key === selectedDate"
                        :aria-label="cell.key + (cell.deadline ? ', ' + cell.deadline.total + ' deadline' : '') + (cell.events.length ? ', ' + cell.events.length + ' agenda' : '')"
                        class="flex min-h-14 flex-col items-stretch rounded-lg p-1 text-left transition sm:min-h-20 sm:p-1.5"
                        :class="[
                            cell.key === selectedDate ? 'bg-burgundy-600 text-white ring-2 ring-burgundy-300' : (cell.inMonth ? 'bg-white ring-1 ring-burgundy-100 hover:bg-burgundy-50' : 'bg-slate-50 text-slate-400 ring-1 ring-slate-100'),
                        ]"
                    >
                        <span class="text-xs font-semibold" :class="cell.isToday && cell.key !== selectedDate && 'flex size-5 items-center justify-center rounded-full bg-burgundy-100 text-burgundy-800'" x-text="cell.day"></span>
                        <span class="mt-auto flex flex-wrap items-center gap-1">
                            <span
                                x-show="cell.deadline"
                                class="rounded px-1 text-[10px] font-semibold"
                                :class="cell.key === selectedDate ? 'bg-white/20 text-white' : (cell.deadline?.overdue ? 'bg-red-100 text-red-700' : 'bg-burgundy-100 text-burgundy-800')"
                                x-text="cell.deadline?.total"
                            ></span>
                            <template x-for="event in cell.events.slice(0, 3)" :key="event.id">
                                <span class="size-1.5 rounded-full" :class="cell.key === selectedDate ? 'bg-white' : 'bg-[#0c5963]'"></span>
                            </template>
                        </span>
                    </button>
                </template>
            </div>
            <div class="mt-3 flex flex-wrap gap-4 text-xs text-slate-500">
                <span class="flex items-center gap-1.5"><span class="rounded bg-burgundy-100 px-1 text-[10px] font-semibold text-burgundy-800">n</span> Deadline tugas</span>
                <span class="flex items-center gap-1.5"><span class="rounded bg-red-100 px-1 text-[10px] font-semibold text-red-700">n</span> Ada yang terlambat</span>
                <span class="flex items-center gap-1.5"><span class="size-1.5 rounded-full bg-[#0c5963]"></span> Agenda bersama</span>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        <h2 class="text-sm font-semibold text-slate-800 capitalize" x-text="selectedLabel"></h2>

        <div x-show="day.error" class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200" role="alert">
            <span x-text="day.error"></span>
            <button type="button" @click="loadDay()" class="ml-1 font-semibold underline">Coba lagi</button>
        </div>

        <section x-show="!day.error" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-burgundy-100">
            <h3 class="text-xs font-semibold tracking-wide text-[#0c5963] uppercase">Agenda Bersama</h3>
            <p x-show="day.loading" class="py-3 text-sm text-slate-400">Memuat…</p>
            <p x-show="!day.loading && day.events.length === 0" class="py-3 text-sm text-slate-400">Tidak ada agenda bersama pada tanggal ini.</p>
            <ul x-show="!day.loading" class="mt-2 space-y-2">
                <template x-for="event in day.events" :key="event.id">
                    <li class="rounded-lg bg-[#0c5963]/5 p-3 ring-1 ring-[#0c5963]/15">
                        <p class="text-sm font-semibold text-slate-800" x-text="event.title"></p>
                        <p class="text-xs text-slate-500" x-text="(event.division?.name ?? 'Semua Divisi') + ' · ' + (event.start === event.end ? eventTime(event) : formatDate(event.start) + ' – ' + formatDate(event.end))"></p>
                        <p x-show="event.description" class="mt-1 text-xs text-slate-700" x-text="event.description"></p>
                    </li>
                </template>
            </ul>
        </section>

        <section x-show="!day.error" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-burgundy-100">
            <h3 class="text-xs font-semibold tracking-wide text-burgundy-700 uppercase">Deadline Tugas</h3>
            <p x-show="day.loading" class="py-3 text-sm text-slate-400">Memuat…</p>
            <p x-show="!day.loading && day.deadlines.length === 0" class="py-3 text-sm text-slate-500">Tidak ada deadline tugas pada tanggal ini.</p>
            <ul x-show="!day.loading" class="mt-2 divide-y divide-burgundy-50">
                <template x-for="task in day.deadlines" :key="task.id">
                    <li>
                        <button type="button" @click="openPreview(task)" class="w-full py-2.5 text-left hover:bg-burgundy-50/50" aria-haspopup="dialog">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm font-medium text-slate-900" x-text="task.title"></p>
                                <span class="shrink-0 rounded-md px-1.5 py-0.5 text-[11px] font-semibold ring-1" :class="priorityBadge(task.priority)" x-text="task.priority_label"></span>
                            </div>
                            <p class="mt-0.5 text-xs text-slate-500" x-text="task.division?.name + ' · ' + picNames(task)"></p>
                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                                <span class="rounded-md px-1.5 py-0.5 font-medium ring-1" :class="statusBadge(task.status)" x-text="task.status_label"></span>
                                <span :class="task.is_overdue ? 'font-semibold text-red-600' : 'text-slate-500'" x-text="formatDate(task.deadline) + (task.is_overdue ? ' · Terlambat' : '')"></span>
                            </div>
                        </button>
                    </li>
                </template>
            </ul>
        </section>
    </div>
</section>

<div x-show="preview.open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-4 backdrop-blur-sm sm:items-center" @click.self="closePreview()" @keydown.escape.window="preview.open && closePreview()">
    <div x-show="preview.open" x-transition class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl ring-1 ring-burgundy-100" role="dialog" aria-modal="true" aria-labelledby="task-preview-title">
        <div class="flex items-start justify-between gap-3">
            <h2 id="task-preview-title" class="text-base font-semibold break-words text-burgundy-900" x-text="preview.data?.title ?? 'Ringkasan Tugas'"></h2>
            <button type="button" @click="closePreview()" class="shrink-0 rounded-lg p-1 text-slate-500 hover:bg-slate-100" aria-label="Tutup ringkasan">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-5"><path d="M18 6 6 18M6 6l12 12" /></svg>
            </button>
        </div>

        <div x-show="preview.loading" class="mt-4 space-y-2" aria-busy="true">
            <div class="h-4 w-2/3 animate-pulse rounded bg-slate-100"></div>
            <div class="h-4 w-1/2 animate-pulse rounded bg-slate-100"></div>
            <div class="h-4 w-3/4 animate-pulse rounded bg-slate-100"></div>
        </div>

        <p x-show="preview.error" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 ring-1 ring-red-200" role="alert" x-text="preview.error"></p>

        <template x-if="preview.data && !preview.loading">
            <div>
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-xs text-slate-500">Tanggal Mulai</dt><dd class="text-slate-800" x-text="preview.data.start_date ? formatDate(preview.data.start_date) : 'Belum ditentukan'"></dd></div>
                    <div><dt class="text-xs text-slate-500">Deadline</dt><dd :class="preview.data.is_overdue ? 'font-semibold text-red-600' : 'text-slate-800'" x-text="preview.data.deadline ? formatDate(preview.data.deadline) + (preview.data.is_overdue ? ' · Terlambat' : '') : 'Belum ditentukan'"></dd></div>
                    <div class="col-span-2"><dt class="text-xs text-slate-500">PIC</dt><dd class="text-slate-800" x-text="picNames(preview.data)"></dd></div>
                    <div class="col-span-2"><dt class="text-xs text-slate-500">Status</dt><dd><span class="rounded-md px-2 py-0.5 text-xs font-medium ring-1" :class="statusBadge(preview.data.status)" x-text="preview.data.status_label"></span></dd></div>
                </dl>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" @click="closePreview()" class="rounded-lg px-4 py-2 text-sm font-medium ring-1 ring-slate-200 hover:bg-slate-50">Tutup</button>
                    <button type="button" @click="viewOnBoard()" class="rounded-lg bg-burgundy-600 px-4 py-2 text-sm font-semibold text-white hover:bg-burgundy-700">Lihat Detail</button>
                </div>
            </div>
        </template>
    </div>
</div>
