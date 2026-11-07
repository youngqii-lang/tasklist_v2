@php
    $fieldClass = 'mt-1 w-full rounded-lg border border-burgundy-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none transition focus:border-burgundy-500 focus:ring-2 focus:ring-burgundy-200 disabled:bg-slate-50 disabled:text-slate-500';
    $labelClass = 'block text-sm font-medium text-slate-700';
@endphp

<div
    x-data="taskForm(config)"
    @open-task-form.window="show($event.detail)"
    @keydown.escape.window="open && close()"
>
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-sm sm:py-10" @click.self="close()">
        <form
            x-show="open"
            x-transition
            @submit.prevent="submit()"
            class="w-full max-w-2xl rounded-2xl bg-white shadow-xl ring-1 ring-burgundy-100"
            role="dialog"
            aria-modal="true"
            aria-labelledby="task-form-title"
            novalidate
        >
            <header class="flex items-center justify-between border-b border-burgundy-100 px-6 py-4">
                <h2 id="task-form-title" class="text-lg font-semibold text-burgundy-900" x-text="isEdit ? 'Edit Tugas' : 'Create Task'"></h2>
                <button type="button" @click="close()" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100" aria-label="Tutup">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-5"><path d="M18 6 6 18M6 6l12 12" /></svg>
                </button>
            </header>

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
                    <template x-if="options && canManage">
                        <select id="form-category" x-model="form.category_id" @change="onCategoryChange()" class="{{ $fieldClass }}">
                            <option value="">Tanpa kategori</option>
                            <template x-for="category in options.categories" :key="category.id">
                                <option :value="String(category.id)" x-text="category.name" :selected="String(category.id) === form.category_id"></option>
                            </template>
                        </select>
                    </template>
                    <template x-if="!(options && canManage)">
                        <p class="{{ $fieldClass }} bg-slate-50 text-slate-500" x-text="task?.category?.name ?? 'Tanpa kategori'"></p>
                    </template>
                    <p x-show="error('category_id')" x-text="error('category_id')" class="mt-1 text-xs text-red-600"></p>
                </div>

                <div>
                    <label for="form-division" class="{{ $labelClass }}">Target Divisi <span class="text-red-600">*</span></label>
                    <template x-if="options && canManage && canChangeDivision">
                        <select id="form-division" x-model="form.division_id" @change="onDivisionChange()" class="{{ $fieldClass }}" :aria-invalid="!!error('division_id')">
                            <option value="">Pilih divisi</option>
                            <template x-for="division in options.divisions" :key="division.id">
                                <option :value="String(division.id)" x-text="division.name" :selected="String(division.id) === form.division_id"></option>
                            </template>
                        </select>
                    </template>
                    <template x-if="!(options && canManage && canChangeDivision)">
                        <p class="{{ $fieldClass }} bg-slate-50 text-slate-500" x-text="task?.division?.name ?? '—'"></p>
                    </template>
                    <p x-show="error('division_id')" x-text="error('division_id')" class="mt-1 text-xs text-red-600"></p>
                </div>

                <div x-show="!isEdit" class="sm:col-span-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="{{ $labelClass }}">PIC <span x-show="form.status === 'todo'" class="text-red-600">*</span></span>
                        <label x-show="config.user.is_admin" class="flex items-center gap-2 text-xs text-slate-600">
                            <input type="checkbox" x-model="pics.allDivisions" @change="loadPics()" class="rounded border-burgundy-300 text-burgundy-600">
                            Tampilkan Staff semua divisi
                        </label>
                    </div>
                    <div class="mt-1 max-h-40 overflow-y-auto rounded-lg border border-burgundy-200 p-2">
                        <p x-show="!form.division_id && !pics.allDivisions" class="px-1 py-2 text-xs text-slate-500">Pilih divisi terlebih dahulu.</p>
                        <p x-show="pics.loading" class="px-1 py-2 text-xs text-slate-500">Memuat daftar Staff…</p>
                        <p x-show="pics.error" class="px-1 py-2 text-xs text-red-600" x-text="pics.error"></p>
                        <p x-show="!pics.loading && !pics.error && (form.division_id || pics.allDivisions) && pics.items.length === 0" class="px-1 py-2 text-xs text-slate-500">Tidak ada Staff aktif yang dapat ditugaskan.</p>
                        <template x-for="pic in pics.items" :key="pic.id">
                            <label class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-burgundy-50">
                                <input type="checkbox" :checked="form.pic_ids.includes(pic.id)" @change="togglePic(pic.id)" class="rounded border-burgundy-300 text-burgundy-600">
                                <span x-text="pic.fullname"></span>
                                <span class="text-xs text-slate-400" x-text="pics.allDivisions ? (pic.division?.name ?? '') : '@' + pic.username"></span>
                            </label>
                        </template>
                    </div>
                    <p x-show="error('pic_ids')" x-text="error('pic_ids')" class="mt-1 text-xs text-red-600"></p>
                </div>

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
                            <span class="text-sm"><span class="font-medium">To Do</span><span class="block text-xs text-slate-500">Siap dikerjakan, wajib PIC dan deadline.</span></span>
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
                    <label for="form-deadline" class="{{ $labelClass }}">Deadline <span x-show="form.status !== 'backlog'" class="text-red-600">*</span></label>
                    <input id="form-deadline" type="date" x-model="form.deadline" :min="form.start_date || null" class="{{ $fieldClass }}" :aria-invalid="!!error('deadline')">
                    <p x-show="error('deadline')" x-text="error('deadline')" class="mt-1 text-xs text-red-600"></p>
                </div>

                <div class="sm:col-span-2">
                    <label class="flex items-start gap-3 rounded-lg p-3 ring-1 ring-burgundy-200" :class="!canManage && 'opacity-60'">
                        <input type="checkbox" x-model="form.requires_review" :disabled="!canManage" class="mt-0.5 rounded border-burgundy-300 text-burgundy-600">
                        <span class="text-sm">
                            <span class="font-medium text-slate-800">Kewajiban QA</span>
                            <span class="block text-xs text-slate-500">
                                Tugas harus melewati pemeriksaan sebelum Done.
                                <span x-show="selectedCategory" x-text="'Mengikuti kategori ' + (selectedCategory?.name ?? '') + '.'"></span>
                            </span>
                        </span>
                    </label>
                    <p x-show="error('requires_review')" x-text="error('requires_review')" class="mt-1 text-xs text-red-600"></p>
                </div>
            </div>

            <footer class="flex justify-end gap-2 border-t border-burgundy-100 px-6 py-4">
                <button type="button" @click="close()" :disabled="saving" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50 disabled:opacity-50">Batal</button>
                <button type="submit" :disabled="saving || loadingTask || loadFailed" class="inline-flex items-center gap-2 rounded-lg bg-burgundy-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-burgundy-700 disabled:cursor-not-allowed disabled:opacity-60">
                    <svg x-show="saving" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25" /><path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" /></svg>
                    <span x-text="saving ? 'Menyimpan…' : (isEdit ? 'Simpan Perubahan' : 'Buat Tugas')"></span>
                </button>
            </footer>
        </form>
    </div>
</div>
