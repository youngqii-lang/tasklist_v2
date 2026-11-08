<section class="mt-6" aria-label="Board View">
    <div x-show="board.error" x-cloak class="flex flex-col items-center gap-3 rounded-2xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-red-200" role="alert">
        <p class="font-semibold text-red-700">Data tugas gagal dimuat.</p>
        <p class="text-sm text-slate-500" x-text="board.error"></p>
        <button type="button" @click="loadBoard()" class="rounded-lg bg-burgundy-600 px-4 py-2 text-sm font-semibold text-white hover:bg-burgundy-700">Coba lagi</button>
    </div>

    <div x-show="!board.error && !board.loading && board.total === 0" x-cloak class="rounded-2xl bg-white px-6 py-12 text-center text-sm text-slate-500 shadow-sm ring-1 ring-burgundy-100" x-text="emptyMessage"></div>

    <p x-show="!board.error && board.total > board.tasks.length" x-cloak class="mb-3 rounded-lg bg-amber-50 px-4 py-2 text-xs text-amber-800 ring-1 ring-amber-200">
        Menampilkan <span x-text="board.tasks.length"></span> dari <span x-text="board.total"></span> tugas. Gunakan filter atau Table View untuk melihat semuanya.
    </p>

    <div
        x-show="!board.error && (board.loading || board.total > 0)"
        class="grid grid-cols-1 gap-4 pb-4 sm:grid-cols-2 lg:grid-cols-3"
        :aria-busy="board.loading"
    >
        <template x-for="status in config.statuses" :key="status.value">
            <div
                class="flex min-w-0 flex-col rounded-2xl bg-burgundy-50/60 ring-1 ring-burgundy-100 transition"
                :class="{
                    'ring-2 ring-emerald-400 bg-emerald-50/60': drag.over === status.value && isDropAllowed(status.value),
                    'ring-2 ring-red-300 bg-red-50/60': drag.over === status.value && !isDropAllowed(status.value),
                    'opacity-60': drag.task && drag.task.status !== status.value && !isDropAllowed(status.value),
                }"
                @dragover="onDragOver($event, status.value)"
                @dragleave.self="onDragLeave(status.value)"
                @drop.prevent="onDrop(status.value)"
            >
                <header class="flex items-center justify-between gap-2 px-4 pt-4 pb-2">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                        <span class="size-2.5 rounded-full" :class="statusDot(status.value)"></span>
                        <span x-text="status.label"></span>
                    </h3>
                    <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-slate-600 ring-1 ring-burgundy-100" x-text="board.counts[status.value] ?? 0" :aria-label="`${board.counts[status.value] ?? 0} tugas`"></span>
                </header>

                <ul class="flex min-h-24 flex-1 flex-col gap-2 px-3 pb-3">
                    <template x-if="board.loading && board.tasks.length === 0">
                        <li class="h-24 animate-pulse rounded-xl bg-white/70"></li>
                    </template>

                    <template x-for="task in column(status.value)" :key="task.id">
                        <li
                            :id="'task-card-' + task.id"
                            :draggable="canDrag(task)"
                            @dragstart="onDragStart($event, task)"
                            @dragend="onDragEnd()"
                            :class="{ 'cursor-grab active:cursor-grabbing': canDrag(task), 'opacity-50': movingIds[task.id] || drag.task?.id === task.id }"
                        >
                            <button
                                type="button"
                                @click="openTask(task.id)"
                                class="block w-full rounded-xl bg-white p-3 text-left shadow-sm transition hover:ring-burgundy-300 focus:outline-none focus:ring-2 focus:ring-burgundy-400"
                                :class="highlightId === task.id ? 'ring-2 ring-amber-400 bg-amber-50' : 'ring-1 ring-burgundy-100'"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <p class="line-clamp-2 text-sm font-semibold text-slate-900" x-text="task.title"></p>
                                    <span class="shrink-0 rounded-md px-1.5 py-0.5 text-[11px] font-semibold ring-1" :class="priorityBadge(task.priority)" x-text="task.priority_label"></span>
                                </div>
                                <p class="mt-1 truncate text-xs text-slate-500" x-text="task.division?.name"></p>
                                <p class="mt-2 flex items-center gap-1.5 truncate text-xs text-slate-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5 shrink-0 text-slate-400"><circle cx="12" cy="8" r="4" /><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1" /></svg>
                                    <span class="truncate" x-text="picNames(task)"></span>
                                </p>
                                <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                                    <span class="flex items-center gap-1.5">
                                        <span class="rounded-md px-1.5 py-0.5 text-[11px] font-medium ring-1" :class="statusBadge(task.status)" x-text="task.status_label"></span>
                                        <span x-show="task.evidences_count > 0" class="flex items-center gap-0.5 text-[11px] text-slate-500" :title="task.evidences_count + ' evidence'">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="m21.4 11.1-9.2 9.2a6 6 0 0 1-8.5-8.5l9.2-9.2a4 4 0 0 1 5.7 5.7l-9.2 9.2a2 2 0 0 1-2.8-2.8l8.5-8.5" /></svg>
                                            <span x-text="task.evidences_count"></span>
                                        </span>
                                    </span>
                                    <span
                                        x-show="task.deadline"
                                        class="flex items-center gap-1 text-xs"
                                        :class="task.is_overdue ? 'font-semibold text-red-600' : 'text-slate-500'"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M16 2v4M8 2v4M3 10h18" /></svg>
                                        <span x-text="formatDate(task.deadline)"></span>
                                        <span x-show="task.is_overdue">· Terlambat</span>
                                    </span>
                                </div>
                            </button>
                        </li>
                    </template>
                </ul>
            </div>
        </template>
    </div>

    <p class="mt-1 hidden text-xs text-slate-400 lg:block">Seret kartu ke kolom lain untuk mengubah status sesuai kewenangan Anda. Di layar sentuh, ubah status melalui detail tugas.</p>
</section>
