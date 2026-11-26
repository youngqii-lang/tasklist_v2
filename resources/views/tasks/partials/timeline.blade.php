<section class="mt-6 rounded-2xl bg-white shadow-sm ring-1 ring-burgundy-100" aria-label="Timeline View">
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-burgundy-100 px-4 py-3">
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" @click="shift(-1)" class="rounded-lg p-2 ring-1 ring-burgundy-200 hover:bg-burgundy-50" aria-label="Periode sebelumnya">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-4"><path d="m15 18-6-6 6-6" /></svg>
            </button>
            <button type="button" @click="goToToday()" :disabled="includesToday" class="rounded-lg px-3 py-1.5 text-sm font-medium ring-1 ring-burgundy-200 hover:bg-burgundy-50 disabled:cursor-default disabled:opacity-50">Hari ini</button>
            <button type="button" @click="shift(1)" class="rounded-lg p-2 ring-1 ring-burgundy-200 hover:bg-burgundy-50" aria-label="Periode berikutnya">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-4"><path d="m9 18 6-6-6-6" /></svg>
            </button>
            <label class="sr-only" for="timeline-jump">Lompat ke tanggal</label>
            <input id="timeline-jump" type="date" @change="goToDate($event.target.value)" class="rounded-lg border border-burgundy-200 px-2 py-1.5 text-sm text-slate-700" title="Lompat ke tanggal">
            <p class="text-sm font-semibold text-slate-800" x-text="periodLabel"></p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <label class="flex items-center gap-2 text-xs text-slate-600">
                <input type="checkbox" x-model="compact" class="rounded border-burgundy-300 text-burgundy-600">
                Tampilan ringkas
            </label>
            <div class="flex rounded-lg p-0.5 ring-1 ring-burgundy-200">
                <button type="button" @click="setMode('weekly')" :class="mode === 'weekly' ? 'bg-burgundy-600 text-white' : 'text-slate-600'" class="rounded-md px-3 py-1 text-xs font-medium">Mingguan</button>
                <button type="button" @click="setMode('biweekly')" :class="mode === 'biweekly' ? 'bg-burgundy-600 text-white' : 'text-slate-600'" class="rounded-md px-3 py-1 text-xs font-medium">Dua Mingguan</button>
            </div>
        </div>
    </header>

    <p x-show="!loading && !error && !isEmpty" class="flex flex-wrap gap-x-4 gap-y-1 border-b border-burgundy-50 px-4 py-2 text-xs text-slate-500">
        <span><span class="font-semibold text-slate-700" x-text="visibleTasks.length"></span> tugas</span>
        <span><span class="font-semibold text-slate-700" x-text="events.length"></span> agenda bersama</span>
        <span x-show="overdueCount" class="font-semibold text-red-600" x-text="overdueCount + ' terlambat'"></span>
    </p>

    <p x-show="hasHiddenFilters || filters.scope === 'my_tasks' || filters.pic_id" x-cloak class="border-b border-burgundy-50 bg-amber-50 px-4 py-2 text-xs text-amber-800">
        <span x-show="hasHiddenFilters">Filter pencarian, prioritas, atau status sedang diterapkan. </span>
        <span x-show="filters.scope === 'my_tasks' || filters.pic_id">Timeline menampilkan jadwal semua PIC; filter PIC dan Tugas Saya tidak berlaku di sini.</span>
    </p>

    <div x-show="error" x-cloak class="flex flex-col items-center gap-3 px-6 py-12 text-center" role="alert">
        <p class="font-semibold text-red-700">Jadwal gagal dimuat.</p>
        <p class="text-sm text-slate-500" x-text="error"></p>
        <button type="button" @click="load()" class="rounded-lg bg-burgundy-600 px-4 py-2 text-sm font-semibold text-white hover:bg-burgundy-700">Coba lagi</button>
    </div>

    <div x-show="!error" class="overflow-x-auto" :aria-busy="loading">
        <div :class="mode === 'weekly' ? 'min-w-[44rem]' : 'min-w-[64rem]'" class="relative p-4">
            {{-- Day columns behind the bars: today and weekends stay visible on every row --}}
            <div class="pointer-events-none absolute inset-x-4 top-4 bottom-4 grid gap-1" :style="gridStyle" aria-hidden="true">
                <template x-for="day in days" :key="'bg-' + day.key">
                    <div class="rounded-md" :class="day.isToday ? 'bg-burgundy-50 ring-1 ring-burgundy-200' : (day.isWeekend ? 'bg-slate-50' : '')"></div>
                </template>
            </div>

            <div class="relative grid gap-1 border-b border-burgundy-100 pb-2" :style="gridStyle">
                <template x-for="day in days" :key="day.key">
                    <div class="rounded-md py-1 text-center" :class="day.isToday ? 'bg-burgundy-600 text-white' : (day.isWeekend ? 'text-slate-400' : 'text-slate-600')">
                        <p class="text-[11px] uppercase" x-text="day.weekday"></p>
                        <p class="text-sm font-semibold" x-text="day.day"></p>
                    </div>
                </template>
            </div>

            <div x-show="loading" class="relative space-y-2 py-4" aria-hidden="true">
                <div class="h-6 w-2/3 animate-pulse rounded-md bg-slate-100"></div>
                <div class="ml-[20%] h-6 w-1/2 animate-pulse rounded-md bg-slate-100"></div>
                <div class="ml-[40%] h-6 w-1/3 animate-pulse rounded-md bg-slate-100"></div>
            </div>
            <p x-show="!loading && isEmpty" class="relative py-10 text-center text-sm text-slate-500">Tidak ada jadwal pada periode ini.</p>

            <div x-show="!loading && events.length" class="relative mt-3">
                <h3 class="mb-1 text-xs font-semibold tracking-wide text-slate-500 uppercase">Agenda Bersama</h3>
                <div class="space-y-1">
                    <template x-for="(lane, laneIndex) in eventLanes" :key="'event-lane-' + laneIndex">
                        <div class="grid gap-1" :style="gridStyle">
                            <template x-for="event in lane" :key="'event-' + event.id">
                                <button
                                    type="button"
                                    @click="selectedEvent = selectedEvent?.id === event.id ? null : event"
                                    :style="event.gridStyle"
                                    :class="{ 'rounded-l-none': event.continuesBefore, 'rounded-r-none': event.continuesAfter, 'ring-2 ring-[#0c5963]': selectedEvent?.id === event.id }"
                                    class="truncate rounded-md bg-[#0c5963]/10 px-2 py-1 text-left text-xs font-medium text-[#0c5963] ring-1 ring-[#0c5963]/30 hover:bg-[#0c5963]/20"
                                    :title="event.title + ' · ' + (event.division?.name ?? 'Semua Divisi')"
                                >
                                    <span x-show="event.continuesBefore">‹ </span><span x-text="event.title"></span>
                                    <span class="opacity-70" x-text="' · ' + (event.division?.name ?? 'Semua Divisi')"></span>
                                </button>
                            </template>
                        </div>
                    </template>
                </div>
                <div x-show="selectedEvent" x-cloak class="mt-2 rounded-lg bg-white p-3 text-sm ring-1 ring-[#0c5963]/30">
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-semibold text-slate-800" x-text="selectedEvent?.title"></p>
                        <button type="button" @click="selectedEvent = null" class="text-xs text-slate-500 hover:text-slate-800" aria-label="Tutup agenda">✕</button>
                    </div>
                    <p class="text-xs text-slate-500" x-text="(selectedEvent?.division?.name ?? 'Semua Divisi') + ' · ' + formatDate(selectedEvent?.start) + (selectedEvent?.end !== selectedEvent?.start ? ' – ' + formatDate(selectedEvent?.end) : '')"></p>
                    <p x-show="selectedEvent?.description" class="mt-1 text-slate-700" x-text="selectedEvent?.description"></p>
                </div>
            </div>

            <div x-show="!loading && visibleTasks.length" class="relative mt-4">
                <h3 class="mb-1 text-xs font-semibold tracking-wide text-slate-500 uppercase">Tugas</h3>
                <div class="space-y-1">
                    <template x-for="(lane, laneIndex) in taskLanes" :key="'task-lane-' + laneIndex">
                        <div class="grid gap-1" :style="gridStyle">
                            <template x-for="task in lane" :key="'task-' + task.id">
                                <button
                                    type="button"
                                    @click="openTask(task)"
                                    :style="task.gridStyle"
                                    :class="[
                                        statusBar(task.status),
                                        task.continuesBefore && 'rounded-l-none',
                                        task.continuesAfter && 'rounded-r-none',
                                        task.isOverdue && 'ring-2 ring-red-400',
                                        !task.can_view_detail && 'cursor-default',
                                    ]"
                                    class="flex min-w-0 items-center gap-1.5 rounded-md px-2 py-1 text-left text-xs ring-1"
                                    :title="taskTitle(task)"
                                >
                                    <span class="size-2 shrink-0 rounded-full" :class="statusDot(task.status)"></span>
                                    <span x-show="task.continuesBefore" class="shrink-0">‹</span>
                                    <span class="truncate font-medium" x-text="task.title"></span>
                                    <span class="hidden truncate opacity-70 sm:inline" x-text="'· ' + task.division.name"></span>
                                    <span x-show="task.isOverdue" class="ml-auto shrink-0 rounded bg-red-600 px-1 text-[10px] font-semibold text-white">Terlambat</span>
                                    <span x-show="task.continuesAfter" class="shrink-0" :class="!task.isOverdue && 'ml-auto'">›</span>
                                </button>
                            </template>
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
        <span class="flex items-center gap-1.5"><span class="size-2 rounded-full ring-2 ring-red-400"></span>Terlambat</span>
        <span class="ml-auto">‹ › berlanjut di luar periode · Tugas dengan satu tanggal ditampilkan pada hari tersebut · Hanya untuk dilihat</span>
    </footer>
</section>
