<div x-show="revision.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @keydown.escape.window="revision.open && !revision.saving && (revision.open = false)">
    <form @submit.prevent="submitRevision()" class="w-full max-w-md space-y-3 rounded-2xl bg-white p-6 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="revision-title" novalidate>
        <h2 id="revision-title" class="text-lg font-semibold text-burgundy-900">Request Revision</h2>
        <p class="text-sm text-slate-600">Tuliskan alasan revisi untuk <span class="font-semibold" x-text="revision.task?.title"></span>.</p>
        <textarea x-model="revision.note" rows="4" maxlength="1000" class="w-full rounded-lg border border-burgundy-200 px-3 py-2 text-sm outline-none focus:border-burgundy-500 focus:ring-2 focus:ring-burgundy-200" placeholder="Catatan revisi"></textarea>
        <p x-show="revision.error" class="text-xs text-red-600" x-text="revision.error" role="alert"></p>
        <div class="flex justify-end gap-2">
            <button type="button" @click="revision.open = false" :disabled="revision.saving" class="rounded-lg px-4 py-2 text-sm font-medium ring-1 ring-slate-200 hover:bg-slate-50">Batal</button>
            <button type="submit" :disabled="revision.saving" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700 disabled:opacity-60" x-text="revision.saving ? 'Mengirim…' : 'Kirim Revisi'"></button>
        </div>
    </form>
</div>
