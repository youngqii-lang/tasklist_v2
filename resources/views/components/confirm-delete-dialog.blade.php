@props(['subject'])

<dialog
    id="confirm-delete-dialog"
    aria-labelledby="confirm-delete-title"
    class="m-auto w-full max-w-md rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-burgundy-900/50 backdrop:backdrop-blur-sm"
>
    <div class="p-6">
        <div class="flex size-11 items-center justify-center rounded-full bg-red-50 text-red-600">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-6">
                <path d="M12 9v4M12 17h.01" />
                <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" />
            </svg>
        </div>
        <h2 id="confirm-delete-title" class="mt-4 text-lg font-semibold text-slate-900">Hapus {{ $subject }}?</h2>
        <p class="mt-2 text-sm text-slate-600">
            Apakah Anda yakin ingin menghapus {{ $subject }} <strong data-confirm-delete-name class="text-slate-900"></strong>?
        </p>
        <p data-confirm-delete-note class="mt-2 text-sm text-slate-600"></p>
    </div>
    <div class="flex justify-end gap-3 bg-slate-50 px-6 py-4">
        <button type="button" data-confirm-delete-cancel class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 ring-1 ring-slate-300 transition hover:bg-white">
            Batal
        </button>
        <button type="button" data-confirm-delete-submit class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700">
            Ya, Hapus
        </button>
    </div>
</dialog>
