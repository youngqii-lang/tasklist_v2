@php
    $fieldClass = 'w-full rounded-lg border border-burgundy-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none transition focus:border-burgundy-500 focus:ring-2 focus:ring-burgundy-200';
    $tabs = ['info' => 'Info', 'evidence' => 'Evidence', 'review' => 'Review', 'comments' => 'Komentar', 'history' => 'Histori'];
@endphp

<div
    x-data="taskDetail(config)"
    @open-task.window="show($event.detail.id)"
    @tasks-changed.window="onTasksChanged($event.detail)"
    @keydown.escape.window="open && !confirmation.open && !lightbox && close()"
>
    <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm" @click="close()"></div>

    <aside
        x-show="open"
        x-cloak
        x-transition:enter="transition duration-200 ease-out"
        x-transition:enter-start="translate-x-full"
        x-transition:leave="transition duration-150 ease-in"
        x-transition:leave-end="translate-x-full"
        class="fixed inset-y-0 right-0 z-40 flex w-full max-w-2xl flex-col bg-white shadow-2xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="task-detail-title"
    >
        <header class="flex items-start justify-between gap-3 border-b border-burgundy-100 px-5 py-4">
            <div class="min-w-0">
                <p class="text-xs font-medium text-slate-400" x-text="taskId ? 'Tugas #' + taskId : ''"></p>
                <h2 id="task-detail-title" class="text-lg font-semibold break-words text-burgundy-900" x-text="task?.title ?? 'Detail Tugas'"></h2>
            </div>
            <button type="button" @click="close()" class="shrink-0 rounded-lg p-1.5 text-slate-500 hover:bg-slate-100" aria-label="Tutup detail">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-5"><path d="M18 6 6 18M6 6l12 12" /></svg>
            </button>
        </header>

        <div x-show="loading" class="flex-1 space-y-3 p-5" aria-busy="true">
            <div class="h-5 w-2/3 animate-pulse rounded bg-slate-100"></div>
            <div class="h-4 w-1/2 animate-pulse rounded bg-slate-100"></div>
            <div class="h-32 animate-pulse rounded-xl bg-slate-100"></div>
        </div>

        <div x-show="!loading && failure" class="flex flex-1 flex-col items-center justify-center gap-3 p-8 text-center" role="alert">
            <p class="text-lg font-semibold" :class="failure?.kind === 'error' ? 'text-red-700' : 'text-slate-800'"
               x-text="failure?.kind === 'not_found' ? 'Tugas tidak ditemukan' : (failure?.kind === 'forbidden' ? 'Akses ditolak' : 'Detail gagal dimuat')"></p>
            <p class="text-sm text-slate-500" x-text="failure?.message"></p>
            <button x-show="failure?.kind === 'error'" type="button" @click="load()" class="rounded-lg bg-burgundy-600 px-4 py-2 text-sm font-semibold text-white hover:bg-burgundy-700">Coba lagi</button>
        </div>

        <template x-if="!loading && task">
            <div class="flex min-h-0 flex-1 flex-col">
                <div class="space-y-3 border-b border-burgundy-100 px-5 py-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-md px-2 py-0.5 text-xs font-medium ring-1" :class="statusBadge(task.status)" x-text="task.status_label"></span>
                        <span class="rounded-md px-2 py-0.5 text-xs font-semibold ring-1" :class="priorityBadge(task.priority)" x-text="task.priority_label"></span>
                        <span x-show="task.requires_review" class="rounded-md bg-violet-50 px-2 py-0.5 text-xs font-medium text-violet-700 ring-1 ring-violet-200">Wajib QA</span>
                        <span x-show="task.is_overdue" class="rounded-md bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700 ring-1 ring-red-200">Terlambat</span>
                    </div>

                    <p x-show="isReadOnly" class="flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600 ring-1 ring-slate-200">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4 shrink-0"><rect x="4" y="11" width="16" height="10" rx="2" /><path d="M8 11V7a4 4 0 0 1 8 0v4" /></svg>
                        Hanya baca — Anda dapat melihat tugas ini, tetapi tidak berwenang mengubahnya.
                    </p>

                    <div x-show="pendingRevision" class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 ring-1 ring-amber-200" role="note">
                        <p class="text-xs font-semibold">Revisi diminta oleh <span x-text="pendingRevision?.reviewer?.fullname ?? 'reviewer'"></span> · <span x-text="formatDateTime(pendingRevision?.created_at)"></span></p>
                        <p class="mt-0.5 whitespace-pre-line" x-text="pendingRevision?.note"></p>
                    </div>

                    <div x-show="transitions.length || task.abilities.update || task.abilities.delete" class="flex flex-wrap gap-2">
                        <template x-for="status in transitions" :key="status">
                            <button
                                type="button"
                                @click="changeStatus(status)"
                                :disabled="busy !== null"
                                class="rounded-lg bg-burgundy-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-burgundy-700 disabled:opacity-60"
                                x-text="busy === 'status:' + status ? 'Memproses…' : transitionLabel(status)"
                            ></button>
                        </template>
                        <button x-show="task.abilities.update" type="button" @click="editTask()" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">Edit</button>
                        <button x-show="task.abilities.delete" type="button" @click="deleteTask()" :disabled="busy !== null" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-50 disabled:opacity-60">Hapus</button>
                    </div>
                </div>

                <nav class="flex gap-1 overflow-x-auto border-b border-burgundy-100 px-3" role="tablist">
                    @foreach ($tabs as $key => $label)
                        <button
                            type="button"
                            role="tab"
                            @click="selectTab('{{ $key }}')"
                            :aria-selected="tab === '{{ $key }}'"
                            :class="tab === '{{ $key }}' ? 'border-burgundy-600 text-burgundy-800' : 'border-transparent text-slate-500 hover:text-slate-800'"
                            class="border-b-2 px-3 py-2.5 text-sm font-medium whitespace-nowrap"
                        >
                            {{ $label }}
                            @if ($key === 'evidence')
                                <span class="ml-1 rounded-full bg-slate-100 px-1.5 text-xs" x-text="task.evidences.length"></span>
                            @endif
                        </button>
                    @endforeach
                </nav>

                <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                    {{-- Info --}}
                    <div x-show="tab === 'info'" class="space-y-5">
                        <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <dt class="text-xs text-slate-500">Deskripsi</dt>
                                <dd class="mt-0.5 whitespace-pre-line text-slate-800" x-text="task.description || 'Belum ada deskripsi.'" :class="!task.description && 'italic text-slate-400'"></dd>
                            </div>
                            <div><dt class="text-xs text-slate-500">Kategori/Jenis Tugas</dt><dd class="mt-0.5 text-slate-800" x-text="task.category?.name ?? '—'"></dd></div>
                            <div><dt class="text-xs text-slate-500">Divisi</dt><dd class="mt-0.5 text-slate-800" x-text="task.division?.name ?? '—'"></dd></div>
                            <div><dt class="text-xs text-slate-500">Platform</dt><dd class="mt-0.5 text-slate-800" x-text="task.platform?.name ?? '—'"></dd></div>
                            <div><dt class="text-xs text-slate-500">Prioritas</dt><dd class="mt-0.5 text-slate-800" x-text="task.priority_label"></dd></div>
                            <div><dt class="text-xs text-slate-500">Tanggal Mulai</dt><dd class="mt-0.5 text-slate-800" x-text="formatDate(task.start_date)"></dd></div>
                            <div><dt class="text-xs text-slate-500">Deadline</dt><dd class="mt-0.5" :class="task.is_overdue ? 'font-semibold text-red-600' : 'text-slate-800'" x-text="formatDate(task.deadline)"></dd></div>
                            <div><dt class="text-xs text-slate-500">Status</dt><dd class="mt-0.5 text-slate-800" x-text="task.status_label"></dd></div>
                            <div><dt class="text-xs text-slate-500">Kewajiban QA</dt><dd class="mt-0.5 text-slate-800" x-text="task.requires_review ? 'Wajib diperiksa sebelum Done' : 'Tidak wajib'"></dd></div>
                            <div><dt class="text-xs text-slate-500">Wajib Evidence</dt><dd class="mt-0.5 text-slate-800" x-text="task.evidence_required ? 'Ya' : 'Tidak'"></dd></div>
                            <div><dt class="text-xs text-slate-500">Dibuat oleh</dt><dd class="mt-0.5 text-slate-800" x-text="(task.creator?.fullname ?? '—') + ' · ' + formatDateTime(task.created_at)"></dd></div>
                            <div x-show="task.completed_at"><dt class="text-xs text-slate-500">Selesai</dt><dd class="mt-0.5 text-slate-800" x-text="formatDateTime(task.completed_at)"></dd></div>
                        </dl>

                        <section class="rounded-xl p-4 ring-1 ring-burgundy-100">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-sm font-semibold text-slate-800">PIC</h3>
                                <button x-show="task.abilities.assign && !assignment.open" type="button" @click="openAssignment()" class="text-xs font-semibold text-burgundy-700 hover:underline">Atur / Teruskan PIC</button>
                            </div>
                            <ul x-show="!assignment.open" class="mt-2 flex flex-wrap gap-2">
                                <template x-for="pic in task.pics" :key="pic.id">
                                    <li class="rounded-full bg-burgundy-50 px-3 py-1 text-xs font-medium text-burgundy-800" x-text="pic.fullname"></li>
                                </template>
                                <li x-show="task.pics.length === 0" class="text-xs text-slate-400 italic">Belum ada PIC.</li>
                            </ul>

                            <div x-show="assignment.open" class="mt-3 space-y-3">
                                <label x-show="config.user.is_admin" class="flex items-center gap-2 text-xs text-slate-600">
                                    <input type="checkbox" x-model="assignment.allDivisions" @change="loadPicOptions()" class="rounded border-burgundy-300 text-burgundy-600">
                                    Tampilkan Staff semua divisi
                                </label>
                                <div class="max-h-48 overflow-y-auto rounded-lg border border-burgundy-200 p-2">
                                    <p x-show="assignment.loading" class="px-1 py-2 text-xs text-slate-500">Memuat daftar Staff…</p>
                                    <p x-show="!assignment.loading && assignment.items.length === 0" class="px-1 py-2 text-xs text-slate-500">Tidak ada Staff aktif yang dapat ditugaskan.</p>
                                    <template x-for="pic in assignment.items" :key="pic.id">
                                        <label class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-burgundy-50">
                                            <input type="checkbox" :checked="assignment.selected.includes(pic.id)" @change="togglePic(pic.id)" class="rounded border-burgundy-300 text-burgundy-600">
                                            <span x-text="pic.fullname"></span>
                                            <span class="text-xs text-slate-400" x-text="pic.division?.name ?? ''"></span>
                                        </label>
                                    </template>
                                </div>
                                <textarea x-model="assignment.note" rows="2" maxlength="500" placeholder="Catatan penugasan (opsional)" class="{{ $fieldClass }}"></textarea>
                                <p x-show="assignment.error" class="text-xs text-red-600" x-text="assignment.error" role="alert"></p>
                                <p class="text-xs text-slate-500">Mengganti PIC tidak mengubah status tugas. PIC lama dan baru tercatat di histori.</p>
                                <div class="flex justify-end gap-2">
                                    <button type="button" @click="assignment.open = false" :disabled="assignment.saving" class="rounded-lg px-3 py-1.5 text-xs font-medium ring-1 ring-slate-200 hover:bg-slate-50">Batal</button>
                                    <button type="button" @click="saveAssignment()" :disabled="assignment.saving || assignment.loading" class="rounded-lg bg-burgundy-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-burgundy-700 disabled:opacity-60" x-text="assignment.saving ? 'Menyimpan…' : 'Simpan PIC'"></button>
                                </div>
                            </div>
                        </section>
                    </div>

                    {{-- Evidence --}}
                    <div x-show="tab === 'evidence'" class="space-y-4">
                        <form x-show="task.abilities.add_evidence" @submit.prevent="submitEvidence()" class="space-y-3 rounded-xl p-4 ring-1 ring-burgundy-100" novalidate>
                            <h3 class="text-sm font-semibold text-slate-800">Tambah Evidence</h3>
                            <div class="flex gap-4 text-sm">
                                <label class="flex items-center gap-2"><input type="radio" value="file" x-model="evidence.type" class="text-burgundy-600"> File</label>
                                <label class="flex items-center gap-2"><input type="radio" value="link" x-model="evidence.type" class="text-burgundy-600"> Tautan</label>
                            </div>
                            <div x-show="evidence.type === 'file'">
                                <input type="file" x-ref="evidenceFile" :accept="evidenceAccept" @change="pickEvidenceFile($event)" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-burgundy-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-burgundy-700 hover:file:bg-burgundy-100">
                                <p class="mt-1 text-xs text-slate-500" x-text="'Format: ' + config.evidence.extensions.join(', ') + ' · maks. ' + Math.round(config.evidence.max_size_kb / 1024) + ' MB'"></p>
                                <p x-show="evidence.errors.file" x-text="evidence.errors.file?.[0]" class="mt-1 text-xs text-red-600"></p>
                            </div>
                            <div x-show="evidence.type === 'link'" class="space-y-2">
                                <input type="url" x-model="evidence.url" placeholder="https://…" class="{{ $fieldClass }}">
                                <p x-show="evidence.errors.url" x-text="evidence.errors.url?.[0]" class="text-xs text-red-600"></p>
                                <input type="text" x-model="evidence.title" maxlength="150" placeholder="Judul tautan (opsional)" class="{{ $fieldClass }}">
                                <p x-show="evidence.errors.title" x-text="evidence.errors.title?.[0]" class="text-xs text-red-600"></p>
                            </div>
                            <textarea x-model="evidence.note" rows="2" maxlength="1000" placeholder="Catatan (opsional)" class="{{ $fieldClass }}"></textarea>
                            <p x-show="evidence.errors.type || evidence.errors.note" x-text="(evidence.errors.type ?? evidence.errors.note)?.[0]" class="text-xs text-red-600"></p>
                            <div class="flex justify-end">
                                <button type="submit" :disabled="evidence.saving" class="inline-flex items-center gap-2 rounded-lg bg-burgundy-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-burgundy-700 disabled:opacity-60">
                                    <svg x-show="evidence.saving" class="size-3.5 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25" /><path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" /></svg>
                                    <span x-text="evidence.saving ? 'Mengirim…' : 'Kirim Evidence'"></span>
                                </button>
                            </div>
                        </form>

                        <p x-show="task.evidence_required && task.evidences.length === 0" class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 ring-1 ring-amber-200">Tugas ini wajib melampirkan evidence sebelum dikirim.</p>

                        <ul class="space-y-2">
                            <li x-show="task.evidences.length === 0" class="py-6 text-center text-sm text-slate-400">Belum ada evidence.</li>
                            <template x-for="item in task.evidences" :key="item.id">
                                <li class="flex items-start gap-3 rounded-xl p-3 ring-1 ring-burgundy-100">
                                    <template x-if="item.is_image && item.preview_url">
                                        <button type="button" @click="lightbox = item" class="shrink-0 rounded-lg focus:outline-none focus:ring-2 focus:ring-burgundy-400" :aria-label="'Pratinjau ' + item.name">
                                            <img :src="item.preview_url" :alt="item.name" class="size-14 rounded-lg object-cover ring-1 ring-slate-200" loading="lazy">
                                        </button>
                                    </template>
                                    <template x-if="!(item.is_image && item.preview_url)">
                                        <div class="flex size-14 shrink-0 items-center justify-center rounded-lg bg-burgundy-50 text-[10px] font-bold text-burgundy-700 uppercase" x-text="item.type === 'link' ? 'Link' : (item.file_name?.split('.').pop() ?? 'File')"></div>
                                    </template>
                                    <div class="min-w-0 flex-1">
                                        <a :href="item.type === 'link' ? item.url : (item.preview_url ?? item.download_url)" target="_blank" rel="noopener noreferrer" class="block truncate text-sm font-medium text-burgundy-700 hover:underline" x-text="item.name"></a>
                                        <p class="text-xs text-slate-500" x-text="(item.submitter?.fullname ?? '—') + ' · ' + formatDateTime(item.created_at) + (item.file_size ? ' · ' + formatFileSize(item.file_size) : '')"></p>
                                        <p x-show="item.note" class="mt-1 text-xs whitespace-pre-line text-slate-700" x-text="item.note"></p>
                                        <div class="mt-1 flex gap-3 text-xs">
                                            <a x-show="item.download_url" :href="item.download_url" class="font-medium text-slate-600 hover:underline">Unduh</a>
                                            <button x-show="item.can_delete" type="button" @click="deleteEvidence(item)" :disabled="busy !== null" class="font-medium text-red-600 hover:underline">Hapus</button>
                                        </div>
                                    </div>
                                </li>
                            </template>
                        </ul>
                    </div>

                    {{-- Review --}}
                    <div x-show="tab === 'review'" class="space-y-4">
                        <p x-show="!task.requires_review" class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600 ring-1 ring-slate-200">Tugas ini tidak wajib QA.</p>

                        <section x-show="canDecideReview" class="space-y-3 rounded-xl p-4 ring-1 ring-violet-200">
                            <h3 class="text-sm font-semibold text-slate-800">Keputusan Review</h3>
                            <p class="text-xs text-slate-500">Periksa informasi dan evidence tugas sebelum memberi keputusan.</p>
                            <textarea x-model="review.note" rows="3" maxlength="1000" placeholder="Catatan review (wajib untuk Request Revision)" class="{{ $fieldClass }}" :aria-invalid="!!review.error"></textarea>
                            <p x-show="review.error" class="text-xs text-red-600" x-text="review.error" role="alert"></p>
                            <div class="flex flex-wrap justify-end gap-2">
                                <button type="button" @click="decideReview('revision_requested')" :disabled="review.saving" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-amber-800 ring-1 ring-amber-300 hover:bg-amber-50 disabled:opacity-60">Request Revision</button>
                                <button type="button" @click="decideReview('approved')" :disabled="review.saving" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-60">Approve</button>
                            </div>
                        </section>

                        <p x-show="task.status === 'ready_review' && task.abilities.review" class="rounded-lg bg-violet-50 px-3 py-2 text-xs text-violet-800 ring-1 ring-violet-200">Tugas siap diperiksa. Klik "Mulai Review" di atas untuk memulai pemeriksaan.</p>

                        <ol class="space-y-2">
                            <li x-show="task.reviews.length === 0" class="py-6 text-center text-sm text-slate-400">Belum ada hasil review.</li>
                            <template x-for="item in task.reviews" :key="item.id">
                                <li class="rounded-xl p-3 ring-1" :class="item.decision === 'approved' ? 'ring-emerald-200 bg-emerald-50/50' : 'ring-amber-200 bg-amber-50/50'">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="text-sm font-semibold" :class="item.decision === 'approved' ? 'text-emerald-800' : 'text-amber-800'" x-text="item.decision_label"></span>
                                        <span class="text-xs text-slate-500" x-text="(item.reviewer?.fullname ?? '—') + ' · ' + formatDateTime(item.created_at)"></span>
                                    </div>
                                    <p x-show="item.note" class="mt-1 text-sm whitespace-pre-line text-slate-700" x-text="item.note"></p>
                                </li>
                            </template>
                        </ol>
                    </div>

                    {{-- Comments --}}
                    <div x-show="tab === 'comments'" class="space-y-4">
                        <p x-show="comments.loading" class="py-6 text-center text-sm text-slate-400">Memuat komentar…</p>
                        <div x-show="comments.error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 ring-1 ring-red-200" role="alert">
                            <span x-text="comments.error"></span>
                            <button type="button" @click="loadComments()" class="ml-1 font-semibold underline">Coba lagi</button>
                        </div>
                        <ul x-show="!comments.loading && !comments.error" class="space-y-3">
                            <li x-show="comments.items.length === 0" class="py-6 text-center text-sm text-slate-400">Belum ada komentar.</li>
                            <template x-for="comment in comments.items" :key="comment.id">
                                <li class="rounded-xl p-3 ring-1 ring-burgundy-100">
                                    <p class="flex flex-wrap items-center gap-1.5 text-xs text-slate-500"><span class="font-semibold text-slate-700" x-text="comment.author?.fullname ?? '—'"></span><span x-show="comment.author_role" class="rounded bg-burgundy-50 px-1.5 text-[10px] font-medium text-burgundy-700" x-text="comment.author_role"></span><span x-text="'· ' + formatDateTime(comment.created_at)"></span></p>
                                    <p class="mt-1 text-sm whitespace-pre-line text-slate-800" x-text="comment.content"></p>
                                    <button x-show="task.abilities.comment" type="button" @click="comments.replyTo = comment; $nextTick(() => $refs.commentBox.focus())" class="mt-1 text-xs font-medium text-burgundy-700 hover:underline">Balas</button>
                                    <ul x-show="comment.replies?.length" class="mt-2 space-y-2 border-l-2 border-burgundy-100 pl-3">
                                        <template x-for="reply in comment.replies" :key="reply.id">
                                            <li>
                                                <p class="flex flex-wrap items-center gap-1.5 text-xs text-slate-500"><span class="font-semibold text-slate-700" x-text="reply.author?.fullname ?? '—'"></span><span x-show="reply.author_role" class="rounded bg-burgundy-50 px-1.5 text-[10px] font-medium text-burgundy-700" x-text="reply.author_role"></span><span x-text="'· ' + formatDateTime(reply.created_at)"></span></p>
                                                <p class="text-sm whitespace-pre-line text-slate-800" x-text="reply.content"></p>
                                            </li>
                                        </template>
                                    </ul>
                                </li>
                            </template>
                        </ul>
                        <form x-show="task.abilities.comment" @submit.prevent="submitComment()" class="space-y-2" novalidate>
                            <p x-show="comments.replyTo" class="flex items-center justify-between rounded-md bg-burgundy-50 px-2 py-1 text-xs text-burgundy-800">
                                <span x-text="'Membalas ' + (comments.replyTo?.author?.fullname ?? '')"></span>
                                <button type="button" @click="comments.replyTo = null" class="font-semibold">Batal</button>
                            </p>
                            <textarea x-ref="commentBox" x-model="comments.content" rows="2" maxlength="5000" placeholder="Tulis komentar…" class="{{ $fieldClass }}"></textarea>
                            <p x-show="comments.errors.content || comments.errors.parent_comment_id" x-text="(comments.errors.content ?? comments.errors.parent_comment_id)?.[0]" class="text-xs text-red-600"></p>
                            <div class="flex justify-end">
                                <button type="submit" :disabled="comments.saving || !comments.content.trim()" class="rounded-lg bg-burgundy-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-burgundy-700 disabled:opacity-60" x-text="comments.saving ? 'Mengirim…' : 'Kirim Komentar'"></button>
                            </div>
                        </form>
                        <p x-show="!task.abilities.comment" class="text-xs text-slate-400" x-text="task.status === 'done' ? 'Diskusi ditutup karena tugas sudah Done. Komentar sebelumnya tetap dapat dibaca.' : 'Anda hanya dapat membaca diskusi tugas ini.'"></p>
                        <p x-show="task.abilities.comment" class="text-xs text-slate-400">Komentar bersifat opsional dan tidak mengubah status tugas.</p>
                    </div>

                    {{-- History --}}
                    <div x-show="tab === 'history'">
                        <p x-show="activities.loading && activities.items.length === 0" class="py-6 text-center text-sm text-slate-400">Memuat histori…</p>
                        <div x-show="activities.error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 ring-1 ring-red-200" role="alert">
                            <span x-text="'Histori gagal dimuat. ' + activities.error"></span>
                            <button type="button" @click="loadActivities()" class="ml-1 font-semibold underline">Coba lagi</button>
                        </div>
                        <p x-show="!activities.loading && !activities.error && activities.items.length === 0" class="py-6 text-center text-sm text-slate-400">Belum ada aktivitas yang tercatat.</p>
                        <ol x-show="!activities.error" class="relative space-y-4 border-l-2 border-burgundy-100 pl-5">
                            <template x-for="activity in activities.items" :key="activity.id">
                                <li class="relative">
                                    <span class="absolute top-1.5 -left-[27px] size-3 rounded-full bg-burgundy-500 ring-4 ring-white"></span>
                                    <p class="text-sm font-semibold text-slate-800" x-text="activity.label"></p>
                                    <p class="text-xs text-slate-500" x-text="(activity.account?.fullname ?? 'Sistem') + ' · ' + formatDateTime(activity.created_at)"></p>
                                    <ul class="mt-1 space-y-0.5 text-xs text-slate-600">
                                        <template x-for="line in describeChanges(activity)">
                                            <li class="break-words" x-text="line"></li>
                                        </template>
                                    </ul>
                                </li>
                            </template>
                        </ol>
                    </div>
                </div>
            </div>
        </template>
    </aside>

    <div x-show="confirmation.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @keydown.escape.window="confirmation.open && !confirmation.running && (confirmation.open = false)">
        <div class="w-full max-w-sm space-y-3 rounded-2xl bg-white p-6 shadow-xl" role="alertdialog" aria-modal="true" aria-labelledby="confirm-title">
            <h2 id="confirm-title" class="text-lg font-semibold text-slate-900" x-text="confirmation.title"></h2>
            <p class="text-sm text-slate-600" x-text="confirmation.message"></p>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="confirmation.open = false" :disabled="confirmation.running" class="rounded-lg px-4 py-2 text-sm font-medium ring-1 ring-slate-200 hover:bg-slate-50">Batal</button>
                <button type="button" @click="runConfirmation()" :disabled="confirmation.running" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60" x-text="confirmation.running ? 'Menghapus…' : confirmation.label"></button>
            </div>
        </div>
    </div>

    <div x-show="lightbox" x-cloak class="fixed inset-0 z-50 flex flex-col items-center justify-center gap-3 bg-slate-900/80 p-4" @click.self="lightbox = null" @keydown.escape.window="lightbox && (lightbox = null)" role="dialog" aria-modal="true" :aria-label="lightbox?.name">
        <img :src="lightbox?.preview_url" :alt="lightbox?.name" class="max-h-[80vh] max-w-full rounded-lg bg-white object-contain shadow-2xl">
        <div class="flex items-center gap-3 text-sm text-white">
            <span class="max-w-xs truncate" x-text="lightbox?.name"></span>
            <a :href="lightbox?.download_url" class="rounded-lg bg-white/15 px-3 py-1.5 font-medium hover:bg-white/25">Unduh</a>
            <button type="button" @click="lightbox = null" class="rounded-lg bg-white/15 px-3 py-1.5 font-medium hover:bg-white/25">Tutup</button>
        </div>
    </div>
</div>
