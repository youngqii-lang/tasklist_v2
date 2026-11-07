<div class="pointer-events-none fixed inset-x-4 bottom-4 z-[60] flex flex-col items-end gap-2 sm:inset-x-auto sm:right-6" aria-live="polite">
    <template x-for="item in $store.toast.items" :key="item.id">
        <div
            x-transition
            class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl px-4 py-3 text-sm shadow-lg ring-1"
            :class="item.type === 'error' ? 'bg-red-50 text-red-800 ring-red-200' : 'bg-emerald-50 text-emerald-800 ring-emerald-200'"
            :role="item.type === 'error' ? 'alert' : 'status'"
        >
            <p class="flex-1" x-text="item.message"></p>
            <button type="button" @click="$store.toast.dismiss(item.id)" class="shrink-0 opacity-60 hover:opacity-100" aria-label="Tutup pesan">✕</button>
        </div>
    </template>
</div>
