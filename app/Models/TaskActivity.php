<?php

namespace App\Models;

use Database\Factories\TaskActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['task_id', 'account_id', 'action', 'changes'])]
class TaskActivity extends Model
{
    /** @use HasFactory<TaskActivityFactory> */
    use HasFactory;

    /**
     * Activities are append-only, so they have no "updated at" column.
     */
    public const UPDATED_AT = null;

    public const string ACTION_UPDATED = 'updated';

    public const string ACTION_PIC_CHANGED = 'pic_changed';

    public const string ACTION_STATUS_CHANGED = 'status_changed';

    public const string ACTION_EVIDENCE_ADDED = 'evidence_added';

    public const string ACTION_APPROVED = 'approved';

    public const string ACTION_REVISION_REQUESTED = 'revision_requested';

    /**
     * Display labels for every recorded action.
     *
     * @var array<string, string>
     */
    public const array ACTION_LABELS = [
        self::ACTION_UPDATED => 'Informasi tugas diperbarui',
        self::ACTION_PIC_CHANGED => 'PIC tugas diganti',
        self::ACTION_STATUS_CHANGED => 'Status tugas diubah',
        self::ACTION_EVIDENCE_ADDED => 'Evidence ditambahkan',
        self::ACTION_APPROVED => 'Tugas disetujui',
        self::ACTION_REVISION_REQUESTED => 'Revisi diminta',
    ];

    /**
     * Get the task the activity belongs to.
     *
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the account that performed the activity.
     *
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }
}
