/**
 * Adapter between the Tasklist API contract (docs/api/tasks.md in SiMarsel dev) and the shape the Tasklist views use.
 *
 * The API decides every right per task (can_* flags, next_statuses, editable_fields); this module only turns those
 * flags into one `abilities` object and adds display values (labels, overdue). It never grants anything the API did not.
 */

export const STATUS_LABELS = {
    backlog: 'Back Log',
    todo: 'To Do',
    in_progress: 'On Process',
    ready_review: 'Ready Review',
    on_review: 'On Review',
    done: 'Done',
};

export const PRIORITY_LABELS = { low: 'Low', medium: 'Medium', high: 'High', urgent: 'Urgent' };

export const REVIEW_DECISIONS = { approve: 'approve', revision: 'revision' };

/** Statuses in which an active PIC may still send evidence. */
const EVIDENCE_STATUSES = ['backlog', 'todo', 'in_progress'];

/**
 * The single regular next step of each status (TASK-05/17 matrix). On Review is left by a review decision instead.
 */
export function regularNextStatus(status, requiresReview) {
    return {
        backlog: 'todo',
        todo: 'in_progress',
        in_progress: requiresReview ? 'ready_review' : 'done',
        ready_review: 'on_review',
    }[status] ?? null;
}

/** Review decision behind a move out of On Review. */
export function reviewDecisionFor(fromStatus, toStatus) {
    if (fromStatus !== 'on_review') return null;
    if (toStatus === 'done') return REVIEW_DECISIONS.approve;
    if (toStatus === 'backlog') return REVIEW_DECISIONS.revision;

    return null;
}

const todayString = () => {
    const now = new Date();
    const pad = (value) => String(value).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
};

export const isOverdue = (task) => task.status !== 'done' && Boolean(task.deadline) && task.deadline.slice(0, 10) < todayString();

/**
 * Where the account may move the task: the statuses the API lists for a detail, otherwise the one regular step when
 * can_change_status is true; plus the two review decisions when can_review is true.
 */
function allowedTargets(task) {
    const targets = task.next_statuses
        ? task.next_statuses.map((status) => (typeof status === 'string' ? status : status.value))
        : (task.can_change_status ? [regularNextStatus(task.status, task.requires_review)].filter(Boolean) : []);

    if (task.status === 'on_review' && task.can_review) targets.push('done', 'backlog');

    return [...new Set(targets)];
}

export function normalizeEvidence(evidence) {
    return {
        ...evidence,
        name: evidence.file_name ?? evidence.url ?? 'Evidence',
        is_image: evidence.kind === 'image',
        download_url: evidence.file_url,
    };
}

export function normalizeReview(review) {
    return { ...review, is_approval: review.decision === REVIEW_DECISIONS.approve };
}

/**
 * A Task, TaskDetail, TaskPreview or calendar deadline from the API, ready for the views.
 *
 * @param {object} task
 * @param {number|null} userId the logged-in account, to tell whether it is an active PIC
 */
export function normalizeTask(task, userId) {
    const isPic = (task.pics ?? []).some((pic) => pic.id === userId);
    const editableFields = task.editable_fields ?? null;
    const managesFields = editableFields
        ? editableFields.some((field) => field !== 'status')
        : Boolean(task.can_delete || task.can_assign);

    return {
        ...task,
        status_label: STATUS_LABELS[task.status] ?? task.status,
        priority_label: PRIORITY_LABELS[task.priority] ?? task.priority,
        is_overdue: task.is_overdue ?? isOverdue(task),
        evidences_count: task.evidence_count ?? task.evidence?.length ?? 0,
        is_pic: isPic,
        evidences: (task.evidence ?? []).map(normalizeEvidence),
        reviews: (task.reviews ?? []).map(normalizeReview),
        activities: task.history ?? [],
        abilities: {
            manage: managesFields,
            update: Boolean(task.can_edit) && managesFields,
            delete: Boolean(task.can_delete),
            assign: Boolean(task.can_assign),
            review: Boolean(task.can_review),
            comment: Boolean(task.can_comment),
            add_evidence: isPic && EVIDENCE_STATUSES.includes(task.status),
            transitions: allowedTargets(task),
            editable_fields: editableFields ?? [],
        },
    };
}

/**
 * Why a move cannot be made yet, checked before calling the API so the card does not jump back after a refusal.
 * The API repeats every check; this only explains it earlier.
 */
export function moveBlocker(task, toStatus) {
    if (task.status === 'backlog' && !(task.pics ?? []).length) {
        return 'Tetapkan PIC sebelum memindahkan tugas dari Back Log.';
    }

    const leavesWork = task.status === 'in_progress' && ['ready_review', 'done'].includes(toStatus);

    if (leavesWork && task.evidence_required && !task.evidences_count) {
        return `Unggah evidence terlebih dahulu sebelum mengubah status ke ${STATUS_LABELS[toStatus]}.`;
    }

    return null;
}

/**
 * Apply the Tasklist filters in the browser, for Timeline and Calendar whose endpoints only filter by division.
 * PIC and "Tugas Saya" can only be applied to items that carry their PICs (Calendar deadlines).
 */
export function matchesFilters(task, filters, userId) {
    const search = (filters.search ?? '').trim().toLowerCase();
    const pics = task.pics ?? null;

    if (search && !task.title.toLowerCase().includes(search)) return false;
    if (filters.priority && task.priority !== filters.priority) return false;
    if (filters.status && task.status !== filters.status) return false;
    if (pics && filters.pic_id && !pics.some((pic) => String(pic.id) === String(filters.pic_id))) return false;
    if (pics && filters.scope === 'my_tasks' && !pics.some((pic) => pic.id === userId)) return false;

    return true;
}
