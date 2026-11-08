@php
    $errorMessage = session('error') ?? match (true) {
        $errors->count() === 1 => $errors->first(),
        $errors->count() > 1 => "Gagal menyimpan. Terdapat {$errors->count()} isian yang perlu diperbaiki.",
        default => null,
    };
@endphp

<div
    x-data
    x-init="
        @if (session('status')) $store.toast.success(@js(session('status'))); @endif
        @if ($errorMessage) $store.toast.error(@js($errorMessage)); @endif
    "
    class="pointer-events-none fixed inset-x-4 bottom-4 z-[60] flex flex-col items-end gap-2 sm:inset-x-auto sm:right-6"
    aria-live="polite"
>
    <template x-for="item in $store.toast.items" :key="item.id">
        <div
            x-transition.opacity.duration.200ms
            class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl px-4 py-3 text-sm font-medium shadow-lg ring-1"
            :class="item.type === 'error' ? 'bg-red-50 text-red-800 ring-red-200' : 'bg-emerald-50 text-emerald-800 ring-emerald-200'"
            :role="item.type === 'error' ? 'alert' : 'status'"
        >
            <svg x-show="item.type !== 'error'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 size-4 shrink-0" aria-hidden="true">
                <path d="M20 6 9 17l-5-5" />
            </svg>
            <svg x-show="item.type === 'error'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 size-4 shrink-0" aria-hidden="true">
                <circle cx="12" cy="12" r="9" />
                <path d="M12 8v4M12 16h.01" />
            </svg>
            <p class="flex-1" x-text="item.message"></p>
            <button type="button" @click="$store.toast.dismiss(item.id)" class="shrink-0 opacity-60 hover:opacity-100" aria-label="Tutup pesan">✕</button>
        </div>
    </template>
</div>
