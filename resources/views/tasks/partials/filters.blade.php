@php
    $fieldClass = 'w-full rounded-lg border border-burgundy-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none transition focus:border-burgundy-500 focus:ring-2 focus:ring-burgundy-200';
    $periodModes = ['' => 'Semua periode', 'date' => 'Tanggal', 'month' => 'Bulan', 'year' => 'Tahun', 'range' => 'Rentang'];
@endphp

<form @submit.prevent="applyFilters()" class="mt-4 space-y-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-burgundy-100" role="search">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,2fr)_repeat(4,minmax(0,1fr))_auto]">
        <div x-show="usesTaskFilters" class="sm:col-span-2 lg:col-span-1">
            <label for="task-search" class="sr-only">Cari judul tugas</label>
            <div class="relative">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400">
                    <circle cx="11" cy="11" r="7" />
                    <path d="m20 20-3.5-3.5" />
                </svg>
                <input
                    id="task-search"
                    type="search"
                    x-model="filters.search"
                    @input.debounce.400ms="applyFilters()"
                    maxlength="150"
                    placeholder="Cari judul tugas…"
                    class="{{ $fieldClass }} pl-9"
                >
            </div>
        </div>

        <div :class="usesTaskFilters ? '' : 'sm:col-span-2 lg:col-span-5'">
            <label for="task-division" class="sr-only">Divisi</label>
            <select id="task-division" x-model="filters.division_id" @change="applyFilters()" class="{{ $fieldClass }}">
                <option value="">Semua Divisi</option>
                <template x-for="division in config.divisions" :key="division.id">
                    <option :value="String(division.id)" x-text="division.name" :selected="String(division.id) === filters.division_id"></option>
                </template>
            </select>
        </div>

        <div x-show="usesTaskFilters">
            <label for="task-pic" class="sr-only">PIC</label>
            <select id="task-pic" x-model="filters.pic_id" @change="applyFilters()" class="{{ $fieldClass }}">
                <option value="">Semua PIC</option>
                <template x-for="pic in config.pics" :key="pic.id">
                    <option :value="String(pic.id)" x-text="pic.fullname + (pic.division ? ' · ' + pic.division : '')" :selected="String(pic.id) === filters.pic_id"></option>
                </template>
            </select>
        </div>

        <div x-show="usesTaskFilters">
            <label for="task-priority" class="sr-only">Prioritas</label>
            <select id="task-priority" x-model="filters.priority" @change="applyFilters()" class="{{ $fieldClass }}">
                <option value="">Semua Prioritas</option>
                <template x-for="priority in config.priorities" :key="priority.value">
                    <option :value="priority.value" x-text="priority.label" :selected="priority.value === filters.priority"></option>
                </template>
            </select>
        </div>

        <div x-show="usesTaskFilters">
            <label for="task-status" class="sr-only">Status</label>
            <select id="task-status" x-model="filters.status" @change="applyFilters()" class="{{ $fieldClass }}">
                <option value="">Semua Status</option>
                <template x-for="status in config.statuses" :key="status.value">
                    <option :value="status.value" x-text="status.label" :selected="status.value === filters.status"></option>
                </template>
            </select>
        </div>

        <button
            type="button"
            @click="resetFilters()"
            :disabled="!hasActiveFilters && sort === 'created_at' && direction === 'desc'"
            class="rounded-lg border border-burgundy-200 px-4 py-2 text-sm font-medium text-burgundy-700 transition hover:bg-burgundy-50 disabled:cursor-not-allowed disabled:opacity-50"
        >
            Reset Filter
        </button>
    </div>

    <div x-show="usesTaskFilters" class="flex flex-wrap items-end gap-3 border-t border-burgundy-50 pt-3">
        <div>
            <label for="period-field" class="mb-1 block text-xs font-medium text-slate-500">Dasar tanggal</label>
            <select id="period-field" x-model="filters.date_field" @change="hasPeriod && applyFilters()" class="{{ $fieldClass }} w-auto">
                <option value="">Deadline</option>
                <option value="start_date" :selected="filters.date_field === 'start_date'">Tanggal Mulai</option>
            </select>
        </div>

        <div>
            <span class="mb-1 block text-xs font-medium text-slate-500">Periode</span>
            <div class="flex flex-wrap rounded-lg p-0.5 ring-1 ring-burgundy-200" role="group" aria-label="Jenis periode">
                @foreach ($periodModes as $mode => $label)
                    <button
                        type="button"
                        @click="setPeriodMode('{{ $mode }}')"
                        :class="period.mode === '{{ $mode }}' ? 'bg-burgundy-600 text-white' : 'text-slate-600 hover:bg-burgundy-50'"
                        :aria-pressed="period.mode === '{{ $mode }}'"
                        class="rounded-md px-3 py-1.5 text-xs font-medium"
                    >{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <div x-show="period.mode === 'date'" x-cloak>
            <label for="period-date" class="mb-1 block text-xs font-medium text-slate-500">Tanggal</label>
            <input id="period-date" type="date" x-model="period.date" @change="applyPeriod()" class="{{ $fieldClass }} w-auto">
        </div>

        <div x-show="period.mode === 'month'" x-cloak class="flex gap-2">
            <div>
                <label for="period-month" class="mb-1 block text-xs font-medium text-slate-500">Bulan</label>
                <select id="period-month" x-model="period.monthNumber" @change="applyPeriod()" class="{{ $fieldClass }} w-auto">
                    <template x-for="(name, index) in monthNames" :key="index">
                        <option :value="String(index + 1)" x-text="name" :selected="String(index + 1) === period.monthNumber"></option>
                    </template>
                </select>
            </div>
            <div>
                <label for="period-month-year" class="mb-1 block text-xs font-medium text-slate-500">Tahun</label>
                <select id="period-month-year" x-model="period.monthYear" @change="applyPeriod()" class="{{ $fieldClass }} w-auto">
                    <template x-for="year in yearOptions" :key="year">
                        <option :value="String(year)" x-text="year" :selected="String(year) === period.monthYear"></option>
                    </template>
                </select>
            </div>
        </div>

        <div x-show="period.mode === 'year'" x-cloak>
            <label for="period-year" class="mb-1 block text-xs font-medium text-slate-500">Tahun</label>
            <select id="period-year" x-model="period.year" @change="applyPeriod()" class="{{ $fieldClass }} w-auto">
                <template x-for="year in yearOptions" :key="year">
                    <option :value="String(year)" x-text="year" :selected="String(year) === period.year"></option>
                </template>
            </select>
        </div>

        <div x-show="period.mode === 'range'" x-cloak class="flex flex-wrap gap-2">
            <div>
                <label for="period-from" class="mb-1 block text-xs font-medium text-slate-500">Dari</label>
                <input id="period-from" type="date" x-model="period.from" :max="period.to || null" @change="applyPeriod()" class="{{ $fieldClass }} w-auto">
            </div>
            <div>
                <label for="period-to" class="mb-1 block text-xs font-medium text-slate-500">Sampai</label>
                <input id="period-to" type="date" x-model="period.to" :min="period.from || null" @change="applyPeriod()" class="{{ $fieldClass }} w-auto">
            </div>
        </div>

        <p x-show="hasPeriod" x-cloak class="text-xs text-slate-500 lg:ml-auto">Tugas tanpa <span x-text="filters.date_field === 'start_date' ? 'tanggal mulai' : 'deadline'"></span> tidak termasuk hasil filter periode.</p>
    </div>
</form>
