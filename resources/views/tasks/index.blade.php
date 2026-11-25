@extends('layouts.dashboard', ['title' => 'Tasklist'])

@section('page')
    <div
        x-data="tasklist(@js($config))"
        @tasks-changed.window="refresh()"
        @focus-task-on-board.window="focusTaskOnBoard($event.detail)"
        @pointermove.window="onWindowPointerMove($event)"
        @pointerup.window="onWindowPointerUp($event)"
        @pointercancel.window="cancelDrag()"
        @blur.window="cancelDrag()"
        @keydown.escape.window="cancelDrag()"
        x-on:show-deadlines-in-table.window="showDeadlinesInTable($event.detail.date)"
        @scroll.window.passive="tableMenu.task && closeTableMenu()"
        @resize.window="tableMenu.task && closeTableMenu()"
        class="mx-auto max-w-7xl"
    >
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-burgundy-900">Tasklist</h1>
                <p class="mt-1 text-sm text-slate-500">Pantau pekerjaan berdasarkan status, prioritas, dan deadline.</p>
            </div>

            @if ($config['can_create'])
                <button
                    type="button"
                    @click="openCreateForm()"
                    class="inline-flex items-center gap-2 rounded-lg bg-burgundy-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-burgundy-700 focus:outline-none focus:ring-2 focus:ring-burgundy-300"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4">
                        <path d="M12 5v14M5 12h14" />
                    </svg>
                    Create Task
                </button>
            @endif
        </div>

        <nav class="mt-6 flex gap-1 overflow-x-auto rounded-xl bg-white p-1 shadow-sm ring-1 ring-burgundy-100" aria-label="Tampilan Tasklist">
            @foreach (['board' => 'Board', 'table' => 'Table', 'timeline' => 'Timeline', 'calendar' => 'Calendar'] as $view => $label)
                <button
                    type="button"
                    @click="setView('{{ $view }}')"
                    :class="view === '{{ $view }}' ? 'bg-burgundy-600 text-white shadow-sm' : 'text-slate-600 hover:bg-burgundy-50 hover:text-burgundy-800'"
                    :aria-current="view === '{{ $view }}' ? 'page' : null"
                    class="flex-1 whitespace-nowrap rounded-lg px-4 py-2 text-sm font-medium transition"
                >
                    {{ $label }}
                </button>
            @endforeach
        </nav>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <div class="inline-flex rounded-lg bg-white p-0.5 shadow-sm ring-1 ring-burgundy-100" role="group" aria-label="Cakupan tugas">
                @foreach (['' => 'Semua Tugas', 'mine' => 'Tugas Saya'] as $scope => $label)
                    <button
                        type="button"
                        @click="setScope('{{ $scope }}')"
                        :class="filters.scope === '{{ $scope }}' ? 'bg-burgundy-600 text-white' : 'text-slate-600 hover:bg-burgundy-50'"
                        :aria-pressed="filters.scope === '{{ $scope }}'"
                        class="rounded-md px-4 py-1.5 text-sm font-medium transition"
                    >{{ $label }}</button>
                @endforeach
            </div>
            <p class="text-xs text-slate-500" x-text="isMyTasks ? 'Tugas dengan Anda sebagai PIC aktif.' : 'Seluruh tugas lintas divisi. Tugas di luar kewenangan Anda hanya dapat dilihat.'"></p>
        </div>

        @include('tasks.partials.filters')

        <template x-if="usesTaskFilters">
            <div>
                @include('tasks.partials.summary')
            </div>
        </template>

        <template x-if="view === 'board'">
            <div>
                @include('tasks.partials.board')
            </div>
        </template>

        <template x-if="view === 'table'">
            <div>
                @include('tasks.partials.table')
            </div>
        </template>

        <template x-if="view === 'timeline'">
            <div x-data="taskTimeline(config, filters.division_id, filters.scope)" @tasklist-filters-changed.window="onFiltersChanged($event.detail.filters)" @tasks-changed.window="load()">
                @include('tasks.partials.timeline')
            </div>
        </template>

        <template x-if="view === 'calendar'">
            <div x-data="taskCalendar(config, filters.division_id, filters.scope)" @tasklist-filters-changed.window="onFiltersChanged($event.detail.filters)" @tasks-changed.window="load()">
                @include('tasks.partials.calendar')
            </div>
        </template>

        @include('tasks.partials.revision-dialog')
        @include('tasks.partials.task-form')
        @include('tasks.partials.task-detail')
    </div>
@endsection
