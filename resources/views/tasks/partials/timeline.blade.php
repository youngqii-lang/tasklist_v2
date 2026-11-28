<section class="mt-6 rounded-2xl bg-white shadow-sm ring-1 ring-burgundy-100" aria-label="Timeline View">
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-burgundy-100 px-4 py-3">
        <div class="flex items-center gap-2">
            <label for="timeline-month" class="sr-only">Bulan</label>
            <select id="timeline-month" @change="jumpToMonth($event.target.value, month.getFullYear())" class="rounded-lg border border-burgundy-200 bg-white px-2 py-1.5 text-sm font-semibold text-slate-800">
                <template x-for="(name, index) in monthNames" :key="index">
                    <option :value="index" x-text="name" :selected="index === month.getMonth()"></option>
                </template>
            </select>
            <label for="timeline-year" class="sr-only">Tahun</label>
            <select id="timeline-year" @change="jumpToMonth(month.getMonth(), $event.target.value)" class="rounded-lg border border-burgundy-200 bg-white px-2 py-1.5 text-sm font-semibold text-slate-800">
                <template x-for="year in yearOptions" :key="year">
                    <option :value="year" x-text="year" :selected="year === month.getFullYear()"></option>
                </template>
            </select>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" @click="shiftMonth(-1)" class="rounded-lg p-2 ring-1 ring-burgundy-200 hover:bg-burgundy-50" aria-label="Bulan sebelumnya">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-4"><path d="m15 18-6-6 6-6" /></svg>
            </button>
            <button type="button" @click="goToToday()" :disabled="isCurrentMonth" class="rounded-lg px-3 py-1.5 text-sm font-medium ring-1 ring-burgundy-200 hover:bg-burgundy-50 disabled:cursor-default disabled:opacity-50">Hari ini</button>
            <button type="button" @click="shiftMonth(1)" class="rounded-lg p-2 ring-1 ring-burgundy-200 hover:bg-burgundy-50" aria-label="Bulan berikutnya">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-4"><path d="m9 18 6-6-6-6" /></svg>
            </button>
        </div>
    </header>

    <p x-show="!error" class="flex flex-wrap gap-x-4 gap-y-1 border-b border-burgundy-50 px-4 py-2 text-xs text-slate-500">
        <span>Bulan ini: <span class="font-semibold text-slate-700" x-text="visibleTasks.length"></span> tugas</span>
        <span x-show="overdueCount" class="font-semibold text-red-600" x-text="overdueCount + ' terlambat'"></span>
        <span><span class="font-semibold text-slate-700" x-text="events.length"></span> agenda bersama</span>
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
        <div class="min-w-[44rem] p-3" :class="loading && 'opacity-60'">
            <div class="grid grid-cols-7 gap-1 pb-1 text-center text-[11px] font-semibold text-slate-500 uppercase">
                <template x-for="weekday in weekdays" :key="weekday"><div x-text="weekday"></div></template>
            </div>

            <div class="space-y-1">
                <template x-for="week in weeks" :key="week.key">
                    <div class="relative min-h-24 rounded-lg">
                        {{-- Day cells behind the bars --}}
                        <div class="absolute inset-0 grid grid-cols-7 gap-1" aria-hidden="true">
                            <template x-for="day in week.days" :key="day.key">
                                <div
                                    class="rounded-lg p-1.5 ring-1"
                                    :class="day.isToday
                                        ? 'bg-burgundy-50 ring-burgundy-300'
                                        : (day.inMonth ? (day.isWeekend ? 'bg-slate-50 ring-slate-100' : 'bg-white ring-slate-100') : 'bg-slate-50/60 ring-slate-100')"
                                >
                                    <span
                                        class="inline-flex size-6 items-center justify-center rounded-full text-xs font-semibold"
                                        :class="day.isToday ? 'bg-burgundy-600 text-white' : (day.inMonth ? 'text-slate-700' : 'text-slate-300')"
                                        x-text="day.day"
                                    ></span>
                                </div>
                            </template>
                        </div>

                        {{-- Bars, below the day numbers --}}
                        <div class="relative space-y-1 px-0.5 pt-9 pb-1.5">
                            <template x-for="(lane, laneIndex) in week.eventLanes" :key="week.key + '-event-lane-' + laneIndex">
                                <div class="grid grid-cols-7 gap-1">
                                    <template x-for="event in lane" :key="week.key + '-event-' + event.id">
                                        <button
                                            type="button"
                                            @click="selectedEvent = selectedEvent?.id === event.id ? null : event"
                                            :style="event.gridStyle"
                                            :class="{ 'rounded-l-none': event.continuesBefore, 'rounded-r-none': event.continuesAfter, 'ring-2 ring-[#0c5963]': selectedEvent?.id === event.id }"
                                            class="truncate rounded-md bg-[#0c5963]/10 px-2 py-0.5 text-left text-[11px] font-medium text-[#0c5963] ring-1 ring-[#0c5963]/30 hover:bg-[#0c5963]/20"
                                            :title="event.title + ' · ' + (event.division?.name ?? 'Semua Divisi')"
                                        >
                                            <span x-show="event.continuesBefore">‹ </span><span x-text="event.title"></span>
                                        </button>
                                    </template>
                                </div>
                            </template>

                            <template x-for="(lane, laneIndex) in week.taskLanes" :key="week.key + '-task-lane-' + laneIndex">
                                <div class="grid grid-cols-7 gap-1">
                                    <template x-for="task in lane" :key="week.key + '-task-' + task.id">
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
                                            class="flex min-w-0 items-center gap-1 rounded-md px-1.5 py-0.5 text-left text-[11px] ring-1"
                                            :title="taskTitle(task)"
                                        >
                                            <span class="size-1.5 shrink-0 rounded-full" :class="statusDot(task.status)"></span>
                                            <span x-show="task.continuesBefore" class="shrink-0">‹</span>
                                            <span class="truncate font-medium" x-text="task.title"></span>
                                            <span x-show="task.continuesAfter" class="ml-auto shrink-0">›</span>
                                        </button>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <p x-show="!loading && isEmpty" class="py-6 text-center text-sm text-slate-500">Tidak ada jadwal pada bulan ini.</p>

            <div x-show="selectedEvent" x-cloak class="mt-3 rounded-lg bg-white p-3 text-sm ring-1 ring-[#0c5963]/30">
                <div class="flex items-start justify-between gap-2">
                    <p class="font-semibold text-slate-800" x-text="selectedEvent?.title"></p>
                    <button type="button" @click="selectedEvent = null" class="text-xs text-slate-500 hover:text-slate-800" aria-label="Tutup agenda">✕</button>
                </div>
                <p class="text-xs text-slate-500" x-text="(selectedEvent?.division?.name ?? 'Semua Divisi') + ' · ' + formatDate(selectedEvent?.start) + (selectedEvent?.end !== selectedEvent?.start ? ' – ' + formatDate(selectedEvent?.end) : '')"></p>
                <p x-show="selectedEvent?.description" class="mt-1 text-slate-700" x-text="selectedEvent?.description"></p>
            </div>
        </div>
    </div>

    <footer class="flex flex-wrap gap-3 border-t border-burgundy-100 px-4 py-3 text-xs text-slate-500">
        <template x-for="status in config.statuses" :key="status.value">
            <span class="flex items-center gap-1.5"><span class="size-2 rounded-full" :class="statusDot(status.value)"></span><span x-text="status.label"></span></span>
        </template>
        <span class="flex items-center gap-1.5"><span class="size-2 rounded-full ring-2 ring-red-400"></span>Terlambat</span>
        <span class="flex items-center gap-1.5"><span class="h-2 w-4 rounded-sm bg-[#0c5963]/20 ring-1 ring-[#0c5963]/30"></span>Agenda bersama</span>
        <span class="ml-auto">Bar = tanggal mulai sampai deadline · ‹ › berlanjut ke minggu lain · Hanya untuk dilihat</span>
    </footer>
</section>
