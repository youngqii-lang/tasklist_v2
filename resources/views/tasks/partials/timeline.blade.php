<section class="mt-6 rounded-2xl bg-white shadow-sm ring-1 ring-burgundy-100" aria-label="Timeline View">
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-burgundy-100 px-4 py-3">
        <div class="flex items-center gap-2">
            <button type="button" @click="shift(-1)" class="rounded-lg p-2 ring-1 ring-burgundy-200 hover:bg-burgundy-50" aria-label="Periode sebelumnya">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-4"><path d="m15 18-6-6 6-6" /></svg>
            </button>
            <button type="button" @click="goToToday()" class="rounded-lg px-3 py-1.5 text-sm font-medium ring-1 ring-burgundy-200 hover:bg-burgundy-50">Minggu ini</button>
            <button type="button" @click="shift(1)" class="rounded-lg p-2 ring-1 ring-burgundy-200 hover:bg-burgundy-50" aria-label="Periode berikutnya">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-4"><path d="m9 18 6-6-6-6" /></svg>
            </button>
            <p class="ml-1 text-sm font-semibold text-slate-800" x-text="periodLabel"></p>
        </div>
        <div class="flex rounded-lg p-0.5 ring-1 ring-burgundy-200">
            <button type="button" @click="setMode('weekly')" :class="mode === 'weekly' ? 'bg-burgundy-600 text-white' : 'text-slate-600'" class="rounded-md px-3 py-1 text-xs font-medium">Mingguan</button>
            <button type="button" @click="setMode('biweekly')" :class="mode === 'biweekly' ? 'bg-burgundy-600 text-white' : 'text-slate-600'" class="rounded-md px-3 py-1 text-xs font-medium">Dua Mingguan</button>
        </div>
    </header>

    <div x-show="error" x-cloak class="flex flex-col items-center gap-3 px-6 py-12 text-center" role="alert">
        <p class="font-semibold text-red-700">Jadwal gagal dimuat.</p>
        <p class="text-sm text-slate-500" x-text="error"></p>
        <button type="button" @click="load()" class="rounded-lg bg-burgundy-600 px-4 py-2 text-sm font-semibold text-white hover:bg-burgundy-700">Coba lagi</button>
    </div>

    <div x-show="!error" class="overflow-x-auto" :aria-busy="loading">
        <div :class="mode === 'weekly' ? 'min-w-[44rem]' : 'min-w-[64rem]'" class="p-4">
            <div class="grid gap-1 border-b border-burgundy-100 pb-2" :style="gridStyle">
                <template x-for="day in days" :key="day.key">
                    <div class="rounded-md py-1 text-center" :class="day.isToday ? 'bg-burgundy-600 text-white' : (day.isWeekend ? 'text-slate-400' : 'text-slate-600')">
                        <p class="text-[11px] uppercase" x-text="day.weekday"></p>
                        <p class="text-sm font-semibold" x-text="day.day"></p>
                    </div>
                </template>
            </div>

            <p x-show="loading" class="py-10 text-center text-sm text-slate-400">Memuat jadwal…</p>
            <p x-show="!loading && isEmpty" class="py-10 text-center text-sm text-slate-500">Tidak ada jadwal pada periode ini.</p>

            <div x-show="!loading && events.length" class="mt-3">
                <h3 class="mb-1 text-xs font-semibold tracking-wide text-slate-500 uppercase">Agenda Bersama</h3>
                <div class="space-y-1">
                    <template x-for="event in events" :key="'event-' + event.id">
                        <div class="grid gap-1" :style="gridStyle">
                            <button
                                type="button"
                                @click="selectedEvent = selectedEvent?.id === event.id ? null : event"
                                :style="placement(event).style"
                                :class="{ 'rounded-l-none': placement(event).continuesBefore, 'rounded-r-none': placement(event).continuesAfter }"
                                class="truncate rounded-md bg-[#0c5963]/10 px-2 py-1 text-left text-xs font-medium text-[#0c5963] ring-1 ring-[#0c5963]/30 hover:bg-[#0c5963]/20"
                                :title="event.title"
                            >
                                <span x-text="event.title"></span>
                                <span class="opacity-70" x-text="' · ' + (event.division?.name ?? 'Semua Divisi')"></span>
                            </button>
                        </div>
                    </template>
                </div>
                <div x-show="selectedEvent" x-cloak class="mt-2 rounded-lg bg-[#0c5963]/5 p-3 text-sm ring-1 ring-[#0c5963]/20">
                    <p class="font-semibold text-slate-800" x-text="selectedEvent?.title"></p>
                    <p class="text-xs text-slate-500" x-text="(selectedEvent?.division?.name ?? 'Semua Divisi') + ' · ' + formatDate(selectedEvent?.start) + (selectedEvent?.end !== selectedEvent?.start ? ' – ' + formatDate(selectedEvent?.end) : '')"></p>
                    <p x-show="selectedEvent?.description" class="mt-1 text-slate-700" x-text="selectedEvent?.description"></p>
                </div>
            </div>

            <div x-show="!loading && tasks.length" class="mt-4">
                <h3 class="mb-1 text-xs font-semibold tracking-wide text-slate-500 uppercase">Tugas</h3>
                <div class="space-y-1">
                    <template x-for="task in tasks" :key="'task-' + task.id">
                        <div class="grid gap-1" :style="gridStyle">
                            <button
                                type="button"
                                @click="openTask(task)"
                                :style="placement(task).style"
                                :class="[statusBar(task.status), placement(task).continuesBefore && 'rounded-l-none', placement(task).continuesAfter && 'rounded-r-none', !task.can_view_detail && 'cursor-default']"
                                class="flex min-w-0 items-center gap-1.5 rounded-md px-2 py-1 text-left text-xs ring-1"
                                :title="`${task.title} · ${task.division.name} · ${formatDate(task.start)} – ${formatDate(task.end)} · ${task.status_label}`"
                            >
                                <span class="size-2 shrink-0 rounded-full" :class="statusDot(task.status)"></span>
                                <span class="truncate font-medium" x-text="task.title"></span>
                                <span class="hidden truncate opacity-70 sm:inline" x-text="'· ' + task.division.name"></span>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <footer class="flex flex-wrap gap-3 border-t border-burgundy-100 px-4 py-3 text-xs text-slate-500">
        <template x-for="status in config.statuses" :key="status.value">
            <span class="flex items-center gap-1.5"><span class="size-2 rounded-full" :class="statusDot(status.value)"></span><span x-text="status.label"></span></span>
        </template>
        <span class="ml-auto">Tugas dengan satu tanggal ditampilkan pada hari tersebut. Timeline hanya untuk dilihat.</span>
    </footer>
</section>
