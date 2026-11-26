@php
    $fieldClass = 'mt-1 w-full rounded-lg border border-burgundy-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none transition focus:border-burgundy-500 focus:ring-2 focus:ring-burgundy-200 disabled:bg-slate-50 disabled:text-slate-500';
    $labelClass = 'block text-sm font-medium text-slate-700';
@endphp

<div
    x-data="agendaForm(config)"
    x-on:open-agenda-form.window="show($event.detail)"
    @keydown.escape.window="open && close()"
>
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-sm sm:py-10" @click.self="close()">
        <form
            x-show="open"
            x-transition
            @submit.prevent="submit()"
            class="w-full max-w-xl rounded-2xl bg-white shadow-xl ring-1 ring-burgundy-100"
            role="dialog"
            aria-modal="true"
            aria-labelledby="agenda-form-title"
            novalidate
        >
            <header class="flex items-center justify-between border-b border-burgundy-100 px-6 py-4">
                <h2 id="agenda-form-title" class="text-lg font-semibold text-burgundy-900" x-text="isEdit ? 'Edit Agenda' : 'Tambah Agenda'"></h2>
                <button type="button" @click="close()" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100" aria-label="Tutup">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-5"><path d="M18 6 6 18M6 6l12 12" /></svg>
                </button>
            </header>

            <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                <div x-show="generalError" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200 sm:col-span-2" role="alert" x-text="generalError"></div>

                <div class="sm:col-span-2">
                    <label for="agenda-title" class="{{ $labelClass }}">Judul Agenda <span class="text-red-600">*</span></label>
                    <input id="agenda-title" x-ref="title" type="text" x-model="form.title" maxlength="150" required class="{{ $fieldClass }}" :aria-invalid="!!error('title')">
                    <p x-show="error('title')" x-text="error('title')" class="mt-1 text-xs text-red-600"></p>
                </div>

                <div class="sm:col-span-2">
                    <label for="agenda-description" class="{{ $labelClass }}">Deskripsi</label>
                    <textarea id="agenda-description" x-model="form.description" rows="3" maxlength="2000" placeholder="Tempat, waktu, atau tautan rapat" class="{{ $fieldClass }}"></textarea>
                    <p x-show="error('description')" x-text="error('description')" class="mt-1 text-xs text-red-600"></p>
                </div>

                <div>
                    <label for="agenda-start" class="{{ $labelClass }}">Tanggal Mulai <span class="text-red-600">*</span></label>
                    <input id="agenda-start" type="date" x-model="form.start_date" @change="if (!form.end_date || form.end_date < form.start_date) form.end_date = form.start_date" required class="{{ $fieldClass }}" :aria-invalid="!!error('start_date')">
                    <p x-show="error('start_date')" x-text="error('start_date')" class="mt-1 text-xs text-red-600"></p>
                </div>

                <div>
                    <label for="agenda-end" class="{{ $labelClass }}">Tanggal Selesai</label>
                    <input id="agenda-end" type="date" x-model="form.end_date" :min="form.start_date" class="{{ $fieldClass }}" :aria-invalid="!!error('end_date')">
                    <p x-show="error('end_date')" x-text="error('end_date')" class="mt-1 text-xs text-red-600"></p>
                </div>

                <fieldset class="sm:col-span-2">
                    <legend class="{{ $labelClass }}">Siapa yang melihat agenda ini?</legend>
                    <div class="mt-1 grid gap-2 sm:grid-cols-2">
                        <label class="flex cursor-pointer items-start gap-2 rounded-lg p-3 ring-1" :class="form.is_shared ? 'bg-burgundy-50 ring-burgundy-400' : 'ring-burgundy-200'">
                            <input type="radio" name="agenda-sharing" class="mt-0.5" :checked="form.is_shared" @change="form.is_shared = true">
                            <span><span class="block text-sm font-medium text-slate-800">Agenda bersama</span><span class="text-xs text-slate-500">Terlihat oleh semua akun.</span></span>
                        </label>
                        <label class="flex cursor-pointer items-start gap-2 rounded-lg p-3 ring-1" :class="!form.is_shared ? 'bg-burgundy-50 ring-burgundy-400' : 'ring-burgundy-200'">
                            <input type="radio" name="agenda-sharing" class="mt-0.5" :checked="!form.is_shared" @change="form.is_shared = false">
                            <span><span class="block text-sm font-medium text-slate-800">Agenda internal</span><span class="text-xs text-slate-500">Hanya divisi yang dipilih dan Admin.</span></span>
                        </label>
                    </div>
                </fieldset>

                <fieldset x-show="!form.is_shared" class="sm:col-span-2">
                    <legend class="{{ $labelClass }}">Divisi Tujuan <span class="text-red-600">*</span></legend>
                    <div class="mt-1 flex flex-wrap gap-2">
                        <template x-for="division in targetChoices" :key="division.id">
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg px-3 py-1.5 text-sm ring-1" :class="form.target_division_ids.includes(division.id) ? 'bg-burgundy-50 ring-burgundy-400' : 'ring-burgundy-200'">
                                <input type="checkbox" :checked="form.target_division_ids.includes(division.id)" @change="toggleTarget(division.id)">
                                <span x-text="division.name"></span>
                            </label>
                        </template>
                    </div>
                    <p x-show="!agenda.is_admin" class="mt-1 text-xs text-slate-500">Divisi Anda wajib dipilih agar agenda tetap terlihat oleh Anda.</p>
                    <p x-show="error('target_division_ids')" x-text="error('target_division_ids')" class="mt-1 text-xs text-red-600"></p>
                </fieldset>

                <div class="sm:col-span-2">
                    <label for="agenda-division" class="{{ $labelClass }}">Divisi Penyelenggara</label>
                    <select id="agenda-division" x-model="form.division_id" :disabled="agenda.is_supervisor" class="{{ $fieldClass }}">
                        <option value="">Tanpa divisi (umum)</option>
                        <template x-for="division in config.divisions" :key="division.id">
                            <option :value="String(division.id)" x-text="division.name" :selected="String(division.id) === form.division_id"></option>
                        </template>
                    </select>
                    <p x-show="agenda.is_supervisor" class="mt-1 text-xs text-slate-500">Agenda Supervisor selalu diselenggarakan oleh divisinya sendiri.</p>
                    <p x-show="error('division_id')" x-text="error('division_id')" class="mt-1 text-xs text-red-600"></p>
                </div>
            </div>

            <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-burgundy-100 px-6 py-4">
                <div>
                    <button
                        x-show="isEdit && event?.can_delete"
                        type="button"
                        @click="destroy()"
                        :disabled="saving"
                        class="rounded-lg px-4 py-2 text-sm font-semibold ring-1 transition disabled:opacity-60"
                        :class="confirmingDelete ? 'bg-red-600 text-white ring-red-600 hover:bg-red-700' : 'text-red-700 ring-red-200 hover:bg-red-50'"
                        x-text="confirmingDelete ? 'Ya, hapus agenda' : 'Hapus'"
                    ></button>
                </div>
                <div class="flex gap-2">
                    <button type="button" @click="close()" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">Batal</button>
                    <button type="submit" :disabled="saving" class="rounded-lg bg-burgundy-600 px-4 py-2 text-sm font-semibold text-white hover:bg-burgundy-700 disabled:opacity-60" x-text="saving ? 'Menyimpan…' : 'Simpan'"></button>
                </div>
            </footer>
        </form>
    </div>
</div>
