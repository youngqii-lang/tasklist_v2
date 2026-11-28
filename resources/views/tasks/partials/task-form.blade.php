@php
    $fieldClass = 'mt-1 w-full rounded-lg border border-burgundy-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none transition focus:border-burgundy-500 focus:ring-2 focus:ring-burgundy-200 disabled:bg-slate-50 disabled:text-slate-500';
    $labelClass = 'block text-sm font-medium text-slate-700';
@endphp

<div
    x-data="taskForm(config)"
    @open-task-form.window="show($event.detail)"
    @keydown.escape.window="open && close()"
>
    <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm" @click="close()"></div>

    <form
        x-show="open"
        x-cloak
        x-transition:enter="transition duration-200 ease-out"
        x-transition:enter-start="translate-x-full"
        x-transition:leave="transition duration-150 ease-in"
        x-transition:leave-end="translate-x-full"
        @submit.prevent="submit()"
        class="fixed inset-y-0 right-0 z-50 flex w-full max-w-2xl flex-col bg-white shadow-2xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="task-form-title"
        novalidate
    >
        <header class="flex shrink-0 items-center justify-between border-b border-burgundy-100 px-6 py-4">
            <h2 id="task-form-title" class="text-lg font-semibold text-burgundy-900" x-text="isEdit ? 'Edit Tugas' : 'Create Task'"></h2>
            <button type="button" @click="close()" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100" aria-label="Tutup">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-5"><path d="M18 6 6 18M6 6l12 12" /></svg>
            </button>
        </header>

        <div class="min-h-0 flex-1 overflow-y-auto">
            <div x-show="loadingTask || optionsLoading" class="px-6 py-10 text-center text-sm text-slate-500">Memuat data formulir…</div>

            <div x-show="!loadingTask && !optionsLoading" class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                <div x-show="generalError" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200 sm:col-span-2" role="alert" x-text="generalError"></div>
                <div x-show="optionsError" class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200 sm:col-span-2" x-text="'Data pilihan gagal dimuat: ' + optionsError"></div>

                <div class="sm:col-span-2">
                    <label for="form-title" class="{{ $labelClass }}">Judul Tugas <span class="text-red-600">*</span></label>
                    <input id="form-title" x-ref="title" type="text" x-model="form.title" maxlength="150" required class="{{ $fieldClass }}" :aria-invalid="!!error('title')">
                    <p x-show="error('title')" x-text="error('title')" class="mt-1 text-xs text-red-600"></p>
                </div>

                <div class="sm:col-span-2">
                    <label for="form-description" class="{{ $labelClass }}">Deskripsi</label>
                    <textarea id="form-description" x-model="form.description" rows="3" class="{{ $fieldClass }}"></textarea>
                    <p x-show="error('description')" x-text="error('description')" class="mt-1 text-xs text-red-600"></p>
                </div>

                <div>
                    <label for="form-category" class="{{ $labelClass }}">Kategori/Jenis Tugas</label>
                    <template x-if="options && canEdit('category_id')">
                        <select id="form-category" x-model="form.category_id" class="{{ $fieldClass }}">
                            <option value="">Tanpa kategori</option>
                            <template x-for="category in options.categories" :key="category.id">
                                <option :value="String(category.id)" x-text="category.name" :selected="String(category.id) === form.category_id"></option>
                            </template>
                        </select>
                    </template>
                    <template x-if="!(options && canEdit('category_id'))">
                        <p class="{{ $fieldClass }} bg-slate-50 text-slate-500" x-text="task?.category?.name ?? 'Tanpa kategori'"></p>
                    </template>
                    <p x-show="error('category_id')" x-text="error('category_id')" class="mt-1 text-xs text-red-600"></p>
                </div>

                <div>
                    <label for="form-division" class="{{ $labelClass }}">Divisi Penanggung Jawab <span class="text-red-600">*</span></label>
                    <template x-if="options && canChangeDivision">
                        <select id="form-division" x-model="form.division_id" @change="onDivisionChange()" class="{{ $fieldClass }}" :aria-invalid="!!error('division_id')">
                            <option value="">Pilih divisi</option>
                            <template x-for="division in options.divisions" :key="division.id">
                                <option :value="String(division.id)" x-text="division.name" :selected="String(division.id) === form.division_id"></option>
                            </template>
                        </select>
                    </template>
                    <template x-if="!(options && canChangeDivision)">
                        <p class="{{ $fieldClass }} bg-slate-50 text-slate-500" x-text="task?.division?.name ?? '—'"></p>
                    </template>
                    <p class="mt-1 text-xs text-slate-500">Divisi yang memiliki dan me-review tugas ini.</p>
                    <p x-show="error('division_id')" x-text="error('division_id')" class="mt-1 text-xs text-red-600"></p>
                </div>

                <div x-show="!isEdit" class="sm:col-span-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="{{ $labelClass }}">PIC <span x-show="form.status === 'todo'" class="text-red-600">*</span></span>
                        <label class="flex items-center gap-2 text-xs text-slate-600">
                            <input type="checkbox" x-model="showAllPics" class="rounded border-burgundy-300 text-burgundy-600">
                            Tampilkan PIC semua divisi
                        </label>
                    </div>
                    <input type="search" x-model="picQuery" placeholder="Cari nama atau divisi…" aria-label="Cari PIC" class="{{ $fieldClass }}">
                    <div class="mt-1 max-h-40 overflow-y-auto rounded-lg border border-burgundy-200 p-2">
                        <p x-show="!form.division_id && !showAllPics" class="px-1 py-2 text-xs text-slate-500">Pilih divisi terlebih dahulu.</p>
                        <p x-show="(form.division_id || showAllPics) && picChoices.length === 0" class="px-1 py-2 text-xs text-slate-500">Tidak ada akun yang dapat ditugaskan.</p>
                        <template x-for="pic in picChoices" :key="pic.id">
                            <label class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-burgundy-50">
                                <input type="checkbox" :checked="form.pic_ids.includes(pic.id)" @change="togglePic(pic.id)" class="rounded border-burgundy-300 text-burgundy-600">
                                <span x-text="pic.fullname"></span>
                                <span class="text-xs text-slate-400" x-text="pic.division?.name ?? 'Tanpa divisi'"></span>
                            </label>
                        </template>
                    </div>
                    <p class="mt-1 text-xs text-slate-500" x-show="form.pic_ids.length" x-text="form.pic_ids.length + ' PIC dipilih'"></p>
                    <p x-show="error('pic_ids')" x-text="error('pic_ids')" class="mt-1 text-xs text-red-600"></p>
                </div>

                <fieldset x-show="options && canEdit('collaborating_division_ids')" class="sm:col-span-2">
                    <legend class="{{ $labelClass }}">Divisi Kolaborator</legend>
                    <p class="text-xs text-slate-500">
                        Divisi PIC otomatis ikut. Tambahkan divisi lain yang juga terlibat bila perlu<span x-show="!form.division_id">, setelah memilih divisi penanggung jawab</span>.
                    </p>
                    <div x-show="form.division_id" class="mt-2 flex flex-wrap gap-2">
                        <template x-for="division in collaboratorChoices" :key="division.id">
                            <label
                                class="flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-medium ring-1 transition"
                                :class="[
                                    isCollaborator(division) ? 'bg-burgundy-50 text-burgundy-800 ring-burgundy-300' : 'text-slate-600 ring-burgundy-200 hover:bg-burgundy-50',
                                    division.fromPic ? 'cursor-not-allowed' : 'cursor-pointer',
                                ]"
                                :title="division.fromPic ? 'Otomatis karena ada PIC dari divisi ini' : ''"
                            >
                                <input type="checkbox" :checked="isCollaborator(division)" :disabled="division.fromPic" @change="toggleCollaborator(division)" class="rounded border-burgundy-300 text-burgundy-600 disabled:opacity-60">
                                <span x-text="division.name"></span>
                                <span x-show="division.fromPic" class="text-[10px] text-slate-500">(PIC)</span>
                            </label>
                        </template>
                    </div>
                    <p x-show="error('collaborating_division_ids')" x-text="error('collaborating_division_ids')" class="mt-1 text-xs text-red-600"></p>
                </fieldset>

                <div x-show="options">
                    <label for="form-platform" class="{{ $labelClass }}">Platform</label>
                    <select id="form-platform" x-model="form.platform_id" class="{{ $fieldClass }}">
                        <option value="">Tidak relevan</option>
                        <template x-for="platform in options?.platforms ?? []" :key="platform.id">
                            <option :value="String(platform.id)" x-text="platform.name" :selected="String(platform.id) === form.platform_id"></option>
                        </template>
                    </select>
                    <p x-show="error('platform_id')" x-text="error('platform_id')" class="mt-1 text-xs text-red-600"></p>
                </div>

                <div>
                    <label for="form-priority" class="{{ $labelClass }}">Prioritas</label>
                    <select id="form-priority" x-model="form.priority" class="{{ $fieldClass }}">
                        <template x-for="priority in config.priorities" :key="priority.value">
                            <option :value="priority.value" x-text="priority.label" :selected="priority.value === form.priority"></option>
                        </template>
                    </select>
                    <p x-show="error('priority')" x-text="error('priority')" class="mt-1 text-xs text-red-600"></p>
                </div>

                <fieldset x-show="!isEdit" class="sm:col-span-2">
                    <legend class="{{ $labelClass }}">Status Awal</legend>
                    <div class="mt-1 grid gap-2 sm:grid-cols-2">
                        <label class="flex cursor-pointer items-start gap-2 rounded-lg p-3 ring-1" :class="form.status === 'backlog' ? 'bg-burgundy-50 ring-burgundy-400' : 'ring-burgundy-200'">
                            <input type="radio" value="backlog" x-model="form.status" class="mt-0.5 text-burgundy-600">
                            <span class="text-sm"><span class="font-medium">Back Log</span><span class="block text-xs text-slate-500">Belum siap dikerjakan.</span></span>
                        </label>
                        <label class="flex cursor-pointer items-start gap-2 rounded-lg p-3 ring-1" :class="form.status === 'todo' ? 'bg-burgundy-50 ring-burgundy-400' : 'ring-burgundy-200'">
                            <input type="radio" value="todo" x-model="form.status" class="mt-0.5 text-burgundy-600">
                            <span class="text-sm"><span class="font-medium">To Do</span><span class="block text-xs text-slate-500">Siap dikerjakan, wajib minimal satu PIC.</span></span>
                        </label>
                    </div>
                    <p x-show="error('status')" x-text="error('status')" class="mt-1 text-xs text-red-600"></p>
                </fieldset>

                <div>
                    <label for="form-start" class="{{ $labelClass }}">Tanggal Mulai</label>
                    <input id="form-start" type="date" x-model="form.start_date" class="{{ $fieldClass }}">
                    <p x-show="error('start_date')" x-text="error('start_date')" class="mt-1 text-xs text-red-600"></p>
                </div>

                <div>
                    <span class="{{ $labelClass }}">Deadline <span x-show="form.deadline" class="text-red-600">*</span></span>
                    <div class="grid grid-cols-[minmax(0,1fr)_8rem] gap-2">
                        <div>
                            <label for="form-deadline" class="sr-only">Tanggal deadline</label>
                            <input id="form-deadline" type="date" x-model="form.deadline" @change="onDeadlineChange()" :min="form.start_date || null" class="{{ $fieldClass }}" :aria-invalid="!!error('deadline')">
                        </div>
                        <div>
                            <label for="form-deadline-time" class="sr-only">Jam deadline</label>
                            <input id="form-deadline-time" type="time" step="60" x-model="form.deadline_time" :disabled="!form.deadline" :required="!!form.deadline" class="{{ $fieldClass }}" :aria-invalid="!!error('deadline_time')">
                        </div>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Isi tanggal lalu jam batasnya (00:00–23:59).</p>
                    <p x-show="error('deadline')" x-text="error('deadline')" class="mt-1 text-xs text-red-600"></p>
                    <p x-show="error('deadline_time')" x-text="error('deadline_time')" class="mt-1 text-xs text-red-600"></p>
                </div>

                <p class="flex items-start gap-2 rounded-lg bg-violet-50 px-3 py-2.5 text-xs text-violet-800 ring-1 ring-violet-200 sm:col-span-2">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-px size-4 shrink-0"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" /><path d="m9 12 2 2 4-4" /></svg>
                    <span>Setiap tugas wajib melampirkan evidence dan melewati review (QA) sebelum Done.</span>
                </p>
            </div>
        </div>

        <footer class="flex shrink-0 justify-end gap-2 border-t border-burgundy-100 px-6 py-4">
            <button type="button" @click="close()" :disabled="saving" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50 disabled:opacity-50">Batal</button>
            <button type="submit" :disabled="saving || loadingTask || loadFailed" class="inline-flex items-center gap-2 rounded-lg bg-burgundy-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-burgundy-700 disabled:cursor-not-allowed disabled:opacity-60">
                <svg x-show="saving" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25" /><path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" /></svg>
                <span x-text="saving ? 'Menyimpan…' : (isEdit ? 'Simpan Perubahan' : 'Buat Tugas')"></span>
            </button>
        </footer>
    </form>
</div>
