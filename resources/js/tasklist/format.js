/**
 * Display helpers shared by the Tasklist views. Class strings are written out in full so Tailwind can find them.
 */
export const STATUS_BADGES = {
    backlog: 'bg-slate-100 text-slate-700 ring-slate-200',
    todo: 'bg-indigo-50 text-indigo-700 ring-indigo-200',
    in_progress: 'bg-amber-50 text-amber-800 ring-amber-200',
    ready_review: 'bg-violet-50 text-violet-700 ring-violet-200',
    on_review: 'bg-fuchsia-50 text-fuchsia-700 ring-fuchsia-200',
    done: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
};

export const STATUS_DOTS = {
    backlog: 'bg-slate-400',
    todo: 'bg-indigo-500',
    in_progress: 'bg-amber-500',
    ready_review: 'bg-violet-500',
    on_review: 'bg-fuchsia-500',
    done: 'bg-emerald-500',
};

export const STATUS_BARS = {
    backlog: 'bg-slate-100 text-slate-800 ring-slate-300 hover:bg-slate-200',
    todo: 'bg-indigo-100 text-indigo-900 ring-indigo-300 hover:bg-indigo-200',
    in_progress: 'bg-amber-100 text-amber-900 ring-amber-300 hover:bg-amber-200',
    ready_review: 'bg-violet-100 text-violet-900 ring-violet-300 hover:bg-violet-200',
    on_review: 'bg-fuchsia-100 text-fuchsia-900 ring-fuchsia-300 hover:bg-fuchsia-200',
    done: 'bg-emerald-100 text-emerald-900 ring-emerald-300 hover:bg-emerald-200',
};

export const PRIORITY_BADGES = {
    low: 'bg-slate-100 text-slate-700 ring-slate-200',
    medium: 'bg-sky-50 text-sky-700 ring-sky-200',
    high: 'bg-orange-50 text-orange-700 ring-orange-200',
    urgent: 'bg-red-50 text-red-700 ring-red-200',
};

const dateFormatter = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
const dateTimeFormatter = new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

/** Parse a YYYY-MM-DD string as a local date (not UTC). */
export function parseDate(value) {
    if (!value) return null;
    const [year, month, day] = value.slice(0, 10).split('-').map(Number);

    return new Date(year, month - 1, day);
}

/** Format a local date as YYYY-MM-DD. */
export function toDateString(date) {
    const pad = (number) => String(number).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

export function addDays(date, days) {
    const copy = new Date(date);
    copy.setDate(copy.getDate() + days);

    return copy;
}

/** Monday of the week the date falls in. */
export function startOfWeek(date) {
    const copy = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    const offset = (copy.getDay() + 6) % 7;

    return addDays(copy, -offset);
}

export function formatDate(value) {
    if (!value) return '—';
    const date = value.length <= 10 ? parseDate(value) : new Date(value);

    return dateFormatter.format(date);
}

export function formatDateTime(value) {
    return value ? dateTimeFormatter.format(new Date(value)) : '—';
}

export function formatFileSize(bytes) {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export function picNames(task) {
    return task.pics?.length ? task.pics.map((pic) => pic.fullname).join(', ') : 'Belum ada PIC';
}

/**
 * Helpers exposed on every Tasklist component so Blade templates can use them.
 */
export const formatting = {
    statusBadge: (status) => STATUS_BADGES[status] ?? STATUS_BADGES.backlog,
    statusDot: (status) => STATUS_DOTS[status] ?? STATUS_DOTS.backlog,
    statusBar: (status) => STATUS_BARS[status] ?? STATUS_BARS.backlog,
    priorityBadge: (priority) => PRIORITY_BADGES[priority] ?? PRIORITY_BADGES.medium,
    formatDate,
    formatDateTime,
    formatFileSize,
    picNames,
};
